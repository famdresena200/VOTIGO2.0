<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('Methode non autorisee', 405);
}

try {
    $user = require_auth();
    $pdo = api_db();
    $votes = [];
    $stmt = $pdo->prepare(
        'SELECT v.date_vote, e.id_election, e.titre, c.id_candidat, c.nom_candidat
         FROM votes v
         INNER JOIN elections e ON e.id_election = v.id_election
         INNER JOIN candidats c ON c.id_candidat = v.id_candidat
         WHERE v.token_anonyme = ? LIMIT 1'
    );
    foreach ($pdo->query('SELECT id_election FROM elections')->fetchAll() as $election) {
        $token = anon_token((int)$user['id_user'], (int)$election['id_election']);
        $stmt->execute([$token]);
        if ($vote = $stmt->fetch()) {
            $votes[] = $vote;
        }
    }
    usort($votes, fn(array $a, array $b): int => strcmp((string)$b['date_vote'], (string)$a['date_vote']));
    api_success(['votes' => array_slice($votes, 0, 30)], 'Historique recupere.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}
