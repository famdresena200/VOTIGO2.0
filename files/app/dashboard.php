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
$idUser = auth_user_id();
$userName = auth_user_name();

$now = new DateTime();

// Récupérer toutes les élections
$elections = $pdo->query("SELECT id_election, titre, description, statut, date_fin, date_debut FROM ELECTIONS 
          ORDER BY date_fin DESC LIMIT 20")->fetchAll();

// Check which elections the user has already voted in and get their choice
$votedElections = [];
$userVoteChoices = []; // id_election => nom_candidat
if ($idUser > 0) {
    $stmt = $pdo->prepare('SELECT DISTINCT id_election FROM VOTES WHERE token_anonyme = ?');
    foreach ($elections as $e) {
        $token = anon_token($idUser, (int)$e['id_election']);
        $stmt->execute([$token]);
        if ($row = $stmt->fetch()) {
            $votedElections[] = (int)$e['id_election'];
            
            // Get the candidate name they voted for
            $stmtCandidat = $pdo->prepare(
                'SELECT c.nom_candidat FROM CANDIDATS c 
                 INNER JOIN VOTES v ON c.id_candidat = v.id_candidat 
                 WHERE v.token_anonyme = ? AND v.id_election = ?'
            );
            $stmtCandidat->execute([$token, (int)$e['id_election']]);
            if ($rowCandidat = $stmtCandidat->fetch()) {
                $userVoteChoices[(int)$e['id_election']] = $rowCandidat['nom_candidat'];
            }
        }
    }
}

// Separate open and closed elections
$openElections = [];
$closedElections = [];

foreach ($elections as $e) {
    try {
        $dateFinObj = new DateTime($e['date_fin']);
        $electionFinished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
    } catch (Exception $ex) {
        $electionFinished = false;
    }

    $statusValue = $electionFinished ? 'terminé' : ((string)($e['statut'] ?? 'programmé'));

    // Toujours afficher les élections actives/programmées, mais tenir compte de la date de fin.
    if (in_array($e['statut'], ['actif', 'programmé'])) {
        if ($electionFinished) {
            $closedElections[] = $e;
        } else {
            $openElections[] = $e;
        }
    }
    // Ajouter les élections terminées où l'utilisateur a voté
    elseif ($electionFinished && in_array((int)$e['id_election'], $votedElections)) {
        $closedElections[] = $e;
    }
}

$activeCount = 0;
foreach ($openElections as $e) {
    if (($e['statut'] ?? '') === 'actif') {
        $activeCount++;
    }
}

votigo_layout_start('Tableau de bord — VOTIGO', 'dashboard', [
    'userName' => $userName,
    'verified' => $idUser > 0,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Bienvenue, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
<div class="muted">Participez aux élections en toute confiance et transparence.</div>

<section class="card" style="margin-top:18px">
  <div class="bd">
    <?php if (empty($openElections) && empty($closedElections)): ?>
      <div class="muted" style="text-align:center;padding:32px 0">
        <div style="font-size:14px;margin-bottom:16px">Aucune élection disponible pour le moment.</div>
        <a class="btn primary" href="../app/guide.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Lisez le guide d'utilisation</a>
      </div>
    <?php else: ?>
      <?php if (!empty($openElections)): ?>
        <div style="margin-bottom:24px">
          <div style="font-weight:800;font-size:14px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.10)">� Élections ouvertes</div>
          <div style="display:grid;gap:12px">
            <?php foreach ($openElections as $e): ?>
              <?php
                $dateFin = new DateTime($e['date_fin']);
                $displayStatus = ((int)$dateFin->getTimestamp() <= (int)$now->getTimestamp()) ? 'terminé' : htmlspecialchars((string)($e['statut'] ?? 'programmé'), ENT_QUOTES, 'UTF-8');
              ?>
              <div style="display:flex;gap:14px;align-items:center;padding:14px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06);border-radius:16px;transition:all .25s cubic-bezier(.4,0,.2,1)">
                <div style="flex:1">
                  <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
                    <div style="font-weight:800;font-size:15px"><?= htmlspecialchars((string)$e['titre'], ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="badge" style="font-size:11px;margin:0"><?= $displayStatus ?></span>
                  </div>
                  <?php if (!empty($e['description'])): ?>
                    <div class="muted" style="font-size:13px;line-height:1.3"><?= htmlspecialchars((string)($e['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                  <a class="btn ghost" href="elections.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px">Voir les candidats</a>
                  <?php if (in_array((int)$e['id_election'], $votedElections)): ?>
                    <span class="btn ghost" style="padding:10px 16px;opacity:.6;cursor:default">☑️ Voté</span>
                  <?php else: ?>
                    <a class="btn primary" href="vote.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px">VOTER</a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($closedElections)): ?>
        <div>
          <div style="font-weight:800;font-size:14px;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid rgba(255,255,255,.10)">🔒 Élections fermées</div>
          <div style="display:grid;gap:12px">
            <?php foreach ($closedElections as $e): ?>
              <?php
                $dateFin = new DateTime($e['date_fin']);
                $displayStatus = ((int)$dateFin->getTimestamp() <= (int)$now->getTimestamp()) ? 'terminé' : htmlspecialchars((string)($e['statut'] ?? 'programmé'), ENT_QUOTES, 'UTF-8');
              ?>
              <div style="display:flex;gap:14px;align-items:center;padding:14px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.06);border-radius:16px;transition:all .25s cubic-bezier(.4,0,.2,1)">
                <div style="flex:1">
                  <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
                    <div style="font-weight:800;font-size:15px"><?= htmlspecialchars((string)$e['titre'], ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="badge" style="font-size:11px;margin:0"><?= $displayStatus ?></span>
                  </div>
                  <?php if (!empty($e['description'])): ?>
                    <div class="muted" style="font-size:13px;line-height:1.3"><?= htmlspecialchars((string)($e['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <?php if (isset($userVoteChoices[(int)$e['id_election']])): ?>
                    <div class="muted" style="font-size:12px;margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.06)">
                      <strong>Votre choix :</strong> <?= htmlspecialchars($userVoteChoices[(int)$e['id_election']], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                  <a class="btn ghost" href="vote.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px">Voir les candidats</a>
                  <a class="btn primary" href="results.php?id_election=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;white-space:nowrap;padding:10px 16px">Voir les résultats</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<div style="margin-top:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <section class="card">
    <div class="hd">
      <h3>Guides & Aide</h3>
    </div>
    <div class="bd">
      <div class="list">
        <a class="btn ghost" href="../app/guide.php" style="text-decoration:none;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:14px">Comment voter ?</span>
          <span class="muted">→</span>
        </a>
        <a class="btn ghost" href="../app/a_propos.php" style="text-decoration:none;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:14px">À propos de VOTIGO</span>
          <span class="muted">→</span>
        </a>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="hd">
      <h3>Transparence</h3>
    </div>
    <div class="bd">
      <div class="list">
        <a class="btn ghost" href="../app/audit.php" style="text-decoration:none;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:14px">Résultats & Audit</span>
          <span class="muted">→</span>
        </a>
        <a class="btn ghost" href="../app/historique.php" style="text-decoration:none;display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:14px">Historique</span>
          <span class="muted">→</span>
        </a>
      </div>
    </div>
  </section>
</div>

<style>
.election-item:hover {
  background: rgba(255,255,255,.04);
  border-color: rgba(255,255,255,.10);
}
@media (max-width: 768px) {
  div[style*="grid-template-columns: 1fr 1fr"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php votigo_layout_end(); ?>

