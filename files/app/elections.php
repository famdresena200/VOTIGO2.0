<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

function vote_secret(): string
{
    return (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret');
}

function anon_token(int $idUser, int $idElection): string
{
    $payload = $idUser . ':' . $idElection;
    return hash_hmac('sha256', $payload, vote_secret());
}

$pdo = db();
require_electeur();
$userName = auth_user_name();
$idUser = auth_user_id();

$elections = $pdo->query("SELECT id_election, titre, description, date_debut, date_fin, statut FROM ELECTIONS ORDER BY statut='actif' DESC, date_debut DESC")->fetchAll();

// Check which elections the user has already voted in
$votedElections = [];
if ($idUser > 0) {
    $stmt = $pdo->prepare('SELECT DISTINCT id_election FROM VOTES WHERE token_anonyme = ?');
    foreach ($elections as $e) {
        $token = anon_token($idUser, (int)$e['id_election']);
        $stmt->execute([$token]);
        if ($stmt->fetch()) {
            $votedElections[] = (int)$e['id_election'];
        }
    }
}

votigo_layout_start('Élections ouvertes — VOTIGO', 'elections', [
    'userName' => $userName,
    'verified' => true,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Élections ouvertes</div>
<div class="muted">Tout est prêt pour voter. Cherchez votre élection et participez.</div>

<div style="margin-bottom:24px">
    <a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour au tableau de bord</span>
    </a>
</div>

<section class="card" style="margin-top:18px">
  <div class="hd">
    <h3>Scrutins ouverts</h3>
    <div class="spacer"></div>
    <span class="muted" style="font-size:13px"><?= count($elections) ?> au total</span>
  </div>
  <div class="bd">
    <?php if (empty($elections)): ?>
      <div class="muted" style="text-align:center;padding:40px 0">
        <div style="font-size:14px;margin-bottom:16px">Aucune élection en base.</div>
        <div class="muted" style="margin-bottom:16px">L'administration doit ouvrir une élection pour vous permettre de voter.</div>
        <a class="btn primary" href="../app/a_propos.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">En savoir plus</a>
      </div>
    <?php else: ?>
      <div style="display:grid;gap:12px">
        <?php foreach ($elections as $e): ?>
          <div style="display:flex;gap:14px;align-items:center;padding:14px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06);border-radius:16px;transition:all .25s cubic-bezier(.4,0,.2,1)">
            <div style="flex:1">
              <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
                <div style="font-weight:800;font-size:15px"><?= htmlspecialchars((string)$e['titre'], ENT_QUOTES, 'UTF-8') ?></div>
                <span class="badge" style="font-size:11px;margin:0"><?= htmlspecialchars((string)$e['statut'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <?php if (!empty($e['description'])): ?>
                <div class="muted" style="font-size:13px;line-height:1.3;margin-bottom:8px"><?= htmlspecialchars((string)$e['description'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <div class="muted" style="font-size:12px;opacity:.7">
                <?= htmlspecialchars(format_date_fr((string)$e['date_debut']), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars(format_date_fr((string)$e['date_fin']), ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
            <div style="display:flex;gap:10px">
              <a class="btn ghost" href="vote.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px" title="Voir les candidats">👁️ Candidats</a>
              <?php if (in_array((int)$e['id_election'], $votedElections)): ?>
                <span class="btn ghost" style="opacity:.6;cursor:default;padding:10px 16px;white-space:nowrap">✓ Voté</span>
              <?php else: ?>
                <a class="btn primary" href="vote.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px">VOTER</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<style>
div[style*="display:flex;gap:14px"] :hover {
  background: rgba(255,255,255,.04) !important;
  border-color: rgba(255,255,255,.10) !important;
}
@media (max-width: 768px) {
  div[style*="display:flex;gap:14px"] {
    flex-direction: column !important;
    align-items: stretch !important;
  }
  div[style*="display:flex;gap:14px"] > div {
    flex: 1 !important;
  }
}
</style>

<?php votigo_layout_end(); ?>

