<?php
declare(strict_types=1);

function vote_secret(): string
{
    return (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret');
}

function anon_token(int $idUser, int $idElection): string
{
    return hash_hmac('sha256', $idUser . ':' . $idElection, vote_secret());
}

function votigo_otp_code(): string
{
    return (string)random_int(100000, 999999);
}

function election_statut_norm(string $statut): string
{
    $s = function_exists('mb_strtolower')
        ? mb_strtolower(trim($statut), 'UTF-8')
        : strtolower(trim($statut));
    return strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e']);
}

function election_statut_is_valid_input(string $statut): bool
{
    return in_array(election_statut_norm($statut), ['programme', 'actif', 'ferme'], true);
}

function election_statut_variants(string $statut): array
{
    $n = election_statut_norm($statut);
    if ($n === 'programme') {
        return ['programme', 'programmé'];
    }
    if ($n === 'ferme') {
        return ['ferme', 'fermé'];
    }
    if ($n === 'actif') {
        return ['actif'];
    }
    return [$statut];
}

function election_statut_for_db(PDO $pdo, string $statut): string
{
    static $allowed = null;
    if ($allowed === null) {
        $allowed = [];
        try {
            $row = $pdo->query("SHOW COLUMNS FROM elections LIKE 'statut'")->fetch();
            $type = (string)($row['Type'] ?? '');
            if (preg_match_all("/'((?:\\\\'|[^'])*)'/", $type, $m)) {
                $allowed = $m[1];
            }
        } catch (Throwable $e) {
            $allowed = [];
        }
    }

    $norm = election_statut_norm($statut);
    foreach ($allowed as $opt) {
        if (election_statut_norm((string)$opt) === $norm) {
            return (string)$opt;
        }
    }

    if ($norm === 'programme') {
        return 'programme';
    }
    if ($norm === 'ferme') {
        return 'ferme';
    }
    return 'actif';
}

function election_is_active_status(string $statut): bool
{
    return election_statut_norm($statut) === 'actif';
}

function election_is_closed_status(string $statut): bool
{
    return election_statut_norm($statut) === 'ferme';
}

function election_is_scheduled_status(string $statut): bool
{
    return election_statut_norm($statut) === 'programme';
}

function election_is_finished(array $election, ?DateTimeInterface $now = null): bool
{
    if (election_is_closed_status((string)($election['statut'] ?? ''))) {
        return true;
    }
    $now = $now ?? new DateTime();
    try {
        $fin = new DateTime((string)($election['date_fin'] ?? ''));
        return $fin->getTimestamp() <= $now->getTimestamp();
    } catch (Exception $e) {
        return false;
    }
}

function election_can_vote_now(array $election, ?DateTimeInterface $now = null): bool
{
    if (!election_is_active_status((string)($election['statut'] ?? ''))) {
        return false;
    }
    $now = $now ?? new DateTime();
    try {
        $debut = new DateTime((string)($election['date_debut'] ?? ''));
        $fin = new DateTime((string)($election['date_fin'] ?? ''));
    } catch (Exception $e) {
        return false;
    }
    $ts = $now->getTimestamp();
    return $ts >= $debut->getTimestamp() && $ts < $fin->getTimestamp();
}

function user_vote_candidat_id(PDO $pdo, int $idUser, int $idElection): int
{
    $stmt = $pdo->prepare('SELECT id_candidat FROM votes WHERE id_election = :e AND token_anonyme = :t LIMIT 1');
    $stmt->execute([
        ':e' => $idElection,
        ':t' => anon_token($idUser, $idElection),
    ]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function user_has_voted(PDO $pdo, int $idUser, int $idElection): bool
{
    return user_vote_candidat_id($pdo, $idUser, $idElection) > 0;
}

function election_count_user_votes(PDO $pdo, int $idUser): int
{
    $ids = $pdo->query('SELECT id_election FROM elections')->fetchAll(PDO::FETCH_COLUMN);
    $n = 0;
    foreach ($ids as $id) {
        if (user_has_voted($pdo, $idUser, (int)$id)) {
            $n++;
        }
    }
    return $n;
}

function recalculate_election_percentages(PDO $pdo, int $idElection): void
{
    try {
        $stmt = $pdo->prepare('SELECT SUM(nombre_votes) as total FROM resultats WHERE id_election = :e');
        $stmt->execute([':e' => $idElection]);
        $totalVotes = (int)($stmt->fetch()['total'] ?? 0);

        if ($totalVotes > 0) {
            $stmt = $pdo->prepare(
                'UPDATE resultats
                 SET pourcentage = ROUND((nombre_votes / :total) * 100, 2)
                 WHERE id_election = :e'
            );
            $stmt->execute([':total' => $totalVotes, ':e' => $idElection]);
        }
    } catch (PDOException $e) {
        error_log('VOTIGO: error recalculating percentages: ' . $e->getMessage());
    }
}
