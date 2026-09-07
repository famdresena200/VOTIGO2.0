<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

$pdo = db();
require_electeur();
$idUser = auth_user_id();
$userName = auth_user_name();

$votes = [];
if ($idUser > 0) {
    // Get all elections
    $elections = $pdo->query('SELECT id_election FROM ELECTIONS')->fetchAll();
    $votedElections = [];
    foreach ($elections as $e) {
        $token = anon_token($idUser, (int)$e['id_election']);
        $stmt_check = $pdo->prepare('SELECT v.id_vote, v.date_vote FROM VOTES v WHERE token_anonyme = :t LIMIT 1');
        $stmt_check->execute([':t' => $token]);
        if ($row = $stmt_check->fetch()) {
            $votedElections[] = (int)$e['id_election'];
        }
    }
    
    // Get vote details for elections where user voted (filtered by user token)
    if (!empty($votedElections)) {
        $userTokens = [];
        foreach ($votedElections as $idElec) {
            $userTokens[] = anon_token($idUser, $idElec);
        }
        
        $placeholders = implode(',', array_fill(0, count($userTokens), '?'));
        $stmt = $pdo->prepare(
            "SELECT v.date_vote, e.titre, c.nom_candidat
             FROM VOTES v
             JOIN ELECTIONS e ON e.id_election = v.id_election
             JOIN CANDIDATS c ON c.id_candidat = v.id_candidat
             WHERE v.token_anonyme IN ($placeholders)
             ORDER BY v.date_vote DESC
             LIMIT 30"
        );
        $stmt->execute($userTokens);
        $votes = $stmt->fetchAll();
    }
}

function anon_token(int $idUser, int $idElection): string
{
    $payload = $idUser . ':' . $idElection;
    return hash_hmac('sha256', $payload, (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret'));
}

votigo_layout_start('Historique des votes — VOTIGO', 'history', [
    'userName' => $userName,
    'verified' => $idUser > 0,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Historique des votes</div>
<div class="muted">Consultez vos actions récentes et gardez un suivi clair de votre participation.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<section class="card" style="margin-top:16px">
  <div class="hd">
    <h3>Mes votes</h3>
    <div class="spacer"></div>
    <span class="muted" style="font-size:13px"><?= count($votes) ?> entrée(s)</span>
  </div>
  <div class="bd">
    <?php if (empty($votes)): ?>
      <div class="muted">Aucun vote enregistré pour l’instant.</div>
      <div style="margin-top:12px">
        <a class="btn primary" href="../app/elections.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Voir les élections</a>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:10px">
        <?php foreach ($votes as $v): ?>
          <div class="mini" style="display:flex;gap:12px;align-items:center">
            <div style="flex:1">
              <div style="font-weight:900"><?= htmlspecialchars((string)$v['titre'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="muted" style="font-size:13px;margin-top:6px">
                Candidat : <?= htmlspecialchars((string)$v['nom_candidat'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <div class="muted" style="font-size:12px">
              <?= htmlspecialchars(format_date_fr((string)$v['date_vote']), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php votigo_layout_end(); ?>

