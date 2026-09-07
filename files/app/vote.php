<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_electeur();
$idUser = auth_user_id();
$userName = auth_user_name();

$pdo = db();

function vote_secret(): string
{
    return (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret');
}

function anon_token(int $idUser, int $idElection): string
{
    $payload = $idUser . ':' . $idElection;
    return hash_hmac('sha256', $payload, vote_secret());
}

function recalculate_election_percentages(PDO $pdo, int $idElection): void
{
    try {
        // Get total votes for this election
        $stmt = $pdo->prepare('SELECT SUM(nombre_votes) as total FROM RESULTATS WHERE id_election = :e');
        $stmt->execute([':e' => $idElection]);
        $totalVotes = (int)($stmt->fetch()['total'] ?? 0);

        if ($totalVotes > 0) {
            // Update percentages for all candidates
            $stmt = $pdo->prepare('
                UPDATE RESULTATS
                SET pourcentage = ROUND((nombre_votes / :total) * 100, 2)
                WHERE id_election = :e
            ');
            $stmt->execute([':total' => $totalVotes, ':e' => $idElection]);
        }
    } catch (PDOException $e) {
        error_log('VOTIGO: error recalculating percentages: ' . $e->getMessage());
    }
}

// Élection choisie (via dashboard) ou élection "active", sinon la plus récente.
$idElectionFromUrl = isset($_GET['id_election']) ? (int)$_GET['id_election'] : 0;
if ($idElectionFromUrl > 0) {
    $stmt = $pdo->prepare('SELECT * FROM ELECTIONS WHERE id_election = :id LIMIT 1');
    $stmt->execute([':id' => $idElectionFromUrl]);
    $election = $stmt->fetch();
} else {
    $election = $pdo->query("SELECT * FROM ELECTIONS WHERE statut='actif' ORDER BY date_debut DESC LIMIT 1")->fetch();
    if (!$election) {
        $election = $pdo->query("SELECT * FROM ELECTIONS ORDER BY id_election DESC LIMIT 1")->fetch();
    }
}

$error = '';
$success = false;
$alreadyVoted = false;
$alreadyVotedCandidateId = 0;

if (!$election) {
    $error = "Aucune élection n'est disponible pour le moment.";
} else {
    $idElection = (int)$election['id_election'];
    $stmt = $pdo->prepare('SELECT id_candidat, nom_candidat, bio, numero, image_mime FROM CANDIDATS WHERE id_election = :id ORDER BY ordre ASC, id_candidat ASC');
    $stmt->execute([':id' => $idElection]);
    $candidats = $stmt->fetchAll();

    // Vérifier si l'élection est terminée
    $now = new DateTime();
    try {
        $dateFinObj = new DateTime($election['date_fin']);
        $electionFinished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
    } catch (Exception $e) {
        error_log('DateTime parse error in vote.php: ' . $e->getMessage());
        $electionFinished = false;
    }

    // Detect if the user already voted in this election (via token) for privacy.
    $alreadyVotedCandidateId = 0;
    $token = anon_token($idUser, $idElection);
    $stmt2 = $pdo->prepare('SELECT id_candidat FROM VOTES WHERE id_election = :e AND token_anonyme = :t LIMIT 1');
    $stmt2->execute([':e' => $idElection, ':t' => $token]);
    $alreadyVotedCandidateId = (int)($stmt2->fetchColumn() ?? 0);
    $alreadyVoted = $alreadyVotedCandidateId > 0;

    if ($electionFinished) {
        // Élection terminée
        if ($alreadyVoted) {
            $alreadyVoted = 'finished-voted';
        } else {
            $error = "Cette élection est terminée. Vous ne pouvez plus voter.";
        }
    } elseif ($alreadyVoted) {
        // Prevent re-voting (token uniqueness + FK constraints) and show a read-only state.
        // Keep $error empty to avoid showing a red toast; UI will show a dedicated message.
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '' && !$alreadyVoted && !$electionFinished) {
        $idCandidat = (int)($_POST['id_candidat'] ?? 0);
        $found = false;
        foreach ($candidats as $c) {
            if ((int)$c['id_candidat'] === $idCandidat) {
                $found = true;
                break;
            }
        }

        if ($idCandidat <= 0 || !$found) {
            $error = "Veuillez sélectionner un candidat.";
        } else {
            $debug = (($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '1') === '1');

            try {
                $token = anon_token($idUser, $idElection);
                try {
                    $stmt = $pdo->prepare('INSERT INTO VOTES (id_candidat, id_election, id_user, token_anonyme) VALUES (:c, :e, :u, :t)');
                    $stmt->execute([
                        ':c' => $idCandidat,
                        ':e' => $idElection,
                        ':u' => $idUser,
                        ':t' => $token,
                    ]);
                } catch (PDOException $e1) {
                    if ((int)($e1->errorInfo[1] ?? 0) === 1054) {
                        $stmt = $pdo->prepare('INSERT INTO VOTES (id_candidat, id_election, token_anonyme) VALUES (:c, :e, :t)');
                        $stmt->execute([
                            ':c' => $idCandidat,
                            ':e' => $idElection,
                            ':t' => $token,
                        ]);
                    } else {
                        throw $e1;
                    }
                }

                // Update aggregated results when possible (non-critical for actual vote recording)
                try {
                    $stmt = $pdo->prepare(
                        'INSERT INTO RESULTATS (id_election, id_candidat, nombre_votes, pourcentage)
                         VALUES (:e, :c, 1, 0.00)
                         ON DUPLICATE KEY UPDATE nombre_votes = nombre_votes + 1'
                    );
                    $stmt->execute([':e' => $idElection, ':c' => $idCandidat]);

                    // Recalculate percentages for all candidates in this election
                    recalculate_election_percentages($pdo, $idElection);
                } catch (PDOException $e2) {
                    // If RESULTATS table/columns are missing or something else went wrong, still allow the vote.
                    error_log('VOTIGO: unable to update RESULTATS: ' . $e2->getMessage());
                }

                $success = true;
                unset($_SESSION['inscription']);
            } catch (PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    $error = "Vous avez déjà voté pour cette élection.";
                } else {
                    $code = $e->getCode();
                    $msg = $e->getMessage();
                    $info = json_encode($e->errorInfo);
                    $error = "Erreur serveur lors de l'enregistrement du vote. (code=$code)";
                    $error .= " " . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                    $error .= " [" . htmlspecialchars($info ?? '', ENT_QUOTES, 'UTF-8') . "]";
                    error_log('VOTIGO: vote error: ' . $msg . ' | info=' . $info);
                }
            }
        }
    }
}
?>
<?php
votigo_layout_start('Vote — VOTIGO', 'elections', [
    'userName' => $userName,
    'verified' => true,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
]);
?>

<div class="h1">Vote</div>
<div class="muted">Sélectionnez un seul candidat. Votre vote est unique par élection.</div>

<div style="margin-bottom:24px">
    <a href="elections.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">
        <span>←</span>
        <span>Retour aux élections</span>
    </a>
</div>

<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Vote" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>
<?php if ($success): ?>
  <div data-flash-type="ok" data-flash-title="Vote enregistré" data-flash-message="Merci. Votre vote a bien été enregistré."></div>
<?php endif; ?>

<style>
  #votePage .vote-grid{
    margin-top:16px;
    display:grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap:16px;
  }
  #votePage .vote-card{
    position:relative;
    border-radius:22px;
    padding:18px 16px 16px;
    background:linear-gradient(180deg, rgba(0,0,0,.32), rgba(0,0,0,.18));
    border:1px solid rgba(255,255,255,.12);
    box-shadow:0 18px 60px rgba(0,0,0,.45);
    display:flex;
    flex-direction:column;
    align-items:stretch;
    cursor:pointer;
    transition:transform .16s ease, box-shadow .16s ease, border-color .16s ease, background .16s ease;
  }
  #votePage .vote-card:hover{
    transform:translateY(-3px);
    box-shadow:0 22px 80px rgba(26,169,184,.18);
    border-color:rgba(26,169,184,.35);
  }
  #votePage .vote-card.selected{
    border-color:rgba(26,169,184,.65);
    background:linear-gradient(180deg, rgba(26,169,184,.32), rgba(0,0,0,.22));
  }
  #votePage.already-voted .vote-card{
    opacity:.55;
    cursor:default;
    pointer-events:none;
  }
  #votePage.already-voted .vote-card.selected{
    opacity:1;
    pointer-events:auto;
  }
  #votePage.already-voted .vote-actions .btn{
    opacity:.6;
    cursor:default;
  }
  #votePage .vote-card .vote-check{
    position:absolute;
    top:12px;
    right:12px;
    width:28px;
    height:28px;
    border-radius:50%;
    background:rgba(26,169,184,.85);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:14px;
    font-weight:900;
    opacity:0;
    transform:scale(0.8);
    transition:opacity .16s ease, transform .16s ease;
    pointer-events:none;
  }
  #votePage .vote-card.selected .vote-check{
    opacity:1;
    transform:scale(1);
  }
  #votePage .vote-avatar{
    width:96px;height:96px;border-radius:22px;
    display:grid;place-items:center;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.22);
    background:linear-gradient(135deg, rgba(26,169,184,.45), rgba(99,185,111,.38));
    margin:0 auto 12px;
    cursor:zoom-in;
  }
  #votePage .vote-avatar span{font-size:32px;font-weight:900;color:rgba(234,244,255,.9)}
  #votePage .vote-avatar img{width:100%;height:100%;object-fit:cover}
  #votePage .vote-name{text-align:center;font-weight:900;margin-bottom:4px}
  #votePage .vote-num{
    display:inline-flex;align-items:center;justify-content:center;
    padding:3px 10px;font-size:12px;
    border-radius:999px;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(0,0,0,.25);
    margin:0 auto 8px;
  }
  #votePage .vote-bio{
    font-size:13px;color:var(--muted);
    text-align:center;
    min-height:34px;
  }
  /* Lightbox for candidate photos */
  #votePage .img-lightbox{
    position:fixed;inset:0;z-index:80;
    display:none;align-items:center;justify-content:center;
    padding:18px;
    background:rgba(0,0,0,.82);
    backdrop-filter: blur(10px);
  }
  #votePage .img-lightbox.open{display:flex}
  #votePage .img-lightbox-card{
    max-width:min(900px,100%);
    max-height:min(90vh,900px);
    border-radius:22px;
    overflow:hidden;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(0,0,0,.95);
    box-shadow:0 30px 140px rgba(0,0,0,.75);
    display:flex;flex-direction:column;
  }
  #votePage .img-lightbox-hd{
    padding:10px 14px;
    display:flex;align-items:center;gap:8px;
    border-bottom:1px solid rgba(255,255,255,.12);
  }
  #votePage .img-lightbox-hd .title{font-weight:900}
  #votePage .img-lightbox-hd .spacer{margin-left:auto}
  #votePage .img-lightbox-body{
    padding:12px;
    display:flex;align-items:center;justify-content:center;
    background:radial-gradient(circle at 50% 0%, rgba(26,169,184,.25), rgba(0,0,0,0) 55%);
  }
  #votePage .img-lightbox-body img{
    max-width:100%;
    max-height:78vh;
    border-radius:18px;
  }
  #votePage .vote-actions{
    margin-top:14px;
    display:flex;
    justify-content:center;
  }
  #votePage .vote-actions .btn{
    min-width:140px;
  }
  #votePage input[type="radio"][name="id_candidat"]{
    position:absolute;
    opacity:0;
    pointer-events:none;
  }
  #votePage .confirm-row{
    margin-top:20px;
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    align-items:center;
    justify-content:space-between;
  }
  #votePage .confirm-row .btn.primary{
    min-width:220px;
    justify-content:center;
  }
  #votePage .confirm-row small{
    font-size:12px;
    color:var(--muted);
  }
  @media (max-width: 640px){
    #votePage .confirm-row{
      flex-direction:column-reverse;
      align-items:stretch;
    }
    #votePage .confirm-row .btn{width:100%}
    #votePage .confirm-row small{text-align:center}
  }
  
  /* Success Modal Styles */
  .vote-success-overlay{
    position:fixed;
    inset:0;
    z-index:100;
    display:none;
    align-items:center;
    justify-content:center;
    padding:18px;
    background:rgba(0,0,0,.75);
    backdrop-filter:blur(8px);
    animation:fadeIn .3s ease;
  }
  .vote-success-overlay.show{display:flex}
  @keyframes fadeIn{from{opacity:0}to{opacity:1}}
  
  .vote-success-modal{
    background:linear-gradient(135deg, rgba(0,0,0,.85) 0%, rgba(0,0,0,.75) 100%);
    border:1px solid rgba(26,169,184,.35);
    border-radius:28px;
    padding:40px 32px;
    max-width:480px;
    width:100%;
    text-align:center;
    box-shadow:0 40px 160px rgba(0,0,0,.8),
              inset 0 1px 0 rgba(255,255,255,.1);
    animation:slideUp .4s cubic-bezier(0.34, 1.56, 0.64, 1);
    display:flex;
    flex-direction:column;
    align-items:center;
    gap:20px;
  }
  @keyframes slideUp{
    from {
      opacity:0;
      transform:translateY(40px);
    }
    to {
      opacity:1;
      transform:translateY(0);
    }
  }
  
  .vote-success-icon{
    width:80px;
    height:80px;
    border-radius:50%;
    background:linear-gradient(135deg, rgba(26,169,184,.3) 0%, rgba(99,185,111,.25) 100%);
    border:2px solid rgba(26,169,184,.65);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:42px;
    animation:scaleIn .5s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  @keyframes scaleIn{
    from {opacity:0; transform:scale(0.5)}
    to {opacity:1; transform:scale(1)}
  }
  
  .vote-success-title{
    font-size:24px;
    font-weight:900;
    color:#fff;
    margin:0;
  }
  
  .vote-success-subtitle{
    font-size:14px;
    color:var(--muted);
    line-height:1.6;
  }
  
  .vote-success-check{
    font-size:36px;
    animation:checkMark .6s ease;
  }
  @keyframes checkMark{
    0% {transform:scale(0) rotate(-45deg); opacity:0}
    50% {transform:scale(1.2) rotate(0deg)}
    100% {transform:scale(1) rotate(0deg); opacity:1}
  }
  
  .vote-success-actions{
    display:flex;
    gap:10px;
    width:100%;
    flex-wrap:wrap;
    justify-content:center;
    margin-top:10px;
  }
  .vote-success-actions .btn{
    flex:1;
    min-width:140px;
  }
  
  @media (max-width:640px){
    .vote-success-modal{
      padding:32px 24px;
    }
    .vote-success-title{font-size:20px}
    .vote-success-actions{flex-direction:column-reverse}
    .vote-success-actions .btn{width:100%}
  }
</style>

<section class="card<?= $alreadyVoted ? ' already-voted' : '' ?><?= $success ? ' hide-main-content' : '' ?>" style="margin-top:16px<?= $success ? ';display:none' : '' ?>" id="votePage">
  <div class="hd">
    <h3><?= $election ? htmlspecialchars((string)$election['titre'], ENT_QUOTES, 'UTF-8') : 'Élection' ?></h3>
    <div class="spacer"></div>
    <?php if ($election): ?>
      <span class="badge"><?= htmlspecialchars((string)($election['statut'] ?? 'programmé'), ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
  </div>
  <div class="bd">
    <?php if ($alreadyVoted): ?>
      <div class="muted">Vous avez déjà voté pour cette élection. Votre choix demeure confidentiel.</div>
      <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="btn primary" id="toggleViewCandidates" style="cursor:pointer">Afficher les candidats</button>
        <a class="btn ghost" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Tableau de bord</a>
        <a class="btn ghost" href="../app/audit.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Transparence & Audit</a>
      </div>
      <div id="revoteContainer" style="display:none;margin-top:18px">
        <div class="vote-grid">
          <?php foreach ($candidats as $c): ?>
            <?php
              $initial = mb_strtoupper(mb_substr((string)$c['nom_candidat'], 0, 1, 'UTF-8'), 'UTF-8');
              $isUserChoice = $alreadyVotedCandidateId === (int)$c['id_candidat'];
            ?>
            <div class="vote-card<?= $isUserChoice ? ' selected' : '' ?>" data-cid="<?= (int)$c['id_candidat'] ?>">
              <div class="vote-avatar">
                <?php if (!empty($c['image_mime'])): ?>
                  <img alt="" src="../admin/candidat_image.php?id=<?= (int)$c['id_candidat'] ?>" />
                <?php else: ?>
                  <span><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
              <?php if ($isUserChoice): ?>
                <div class="vote-badge" style="position:absolute;top:10px;right:10px;background:#007bff;color:white;padding:4px 8px;border-radius:12px;font-size:12px;font-weight:600;z-index:10">Votre choix</div>
              <?php endif; ?>
              <?php if (!empty($c['numero'])): ?>
                <div class="vote-num">#<?= htmlspecialchars((string)$c['numero'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <div class="vote-name"><?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="vote-bio">
                <?= htmlspecialchars((string)($c['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php elseif (!$election): ?>
      <div class="muted">Aucune élection n'est disponible pour le moment.</div>
    <?php elseif (empty($candidats)): ?>
      <div class="muted">Aucun candidat n’est encore disponible pour cette élection.</div>
      <div style="margin-top:12px;display:flex;gap:10px">
        <a class="btn ghost" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour</a>
      </div>
    <?php else: ?>
      <form method="post" novalidate id="voteForm">
        <div class="vote-grid">
          <?php foreach ($candidats as $c): ?>
            <?php
              $initial = mb_strtoupper(mb_substr((string)$c['nom_candidat'], 0, 1, 'UTF-8'), 'UTF-8');
            ?>
            <label class="vote-card<?= ($alreadyVoted && $alreadyVotedCandidateId === (int)$c['id_candidat']) ? ' selected' : '' ?>" data-cid="<?= (int)$c['id_candidat'] ?>">
              <input type="radio" name="id_candidat" value="<?= (int)$c['id_candidat'] ?>" <?= ($alreadyVoted && $alreadyVotedCandidateId === (int)$c['id_candidat']) ? 'checked' : '' ?> <?= $alreadyVoted ? 'disabled' : '' ?> />
              <div class="vote-check" aria-hidden="true">✓</div>
              <div class="vote-avatar">
                <?php if (!empty($c['image_mime'])): ?>
                  <img alt="" src="../admin/candidat_image.php?id=<?= (int)$c['id_candidat'] ?>" />
                <?php else: ?>
                  <span><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>
              <?php if ($alreadyVoted && $alreadyVotedCandidateId === (int)$c['id_candidat']): ?>
                <div class="vote-badge" style="position:absolute;top:10px;right:10px;background:#007bff;color:white;padding:4px 8px;border-radius:12px;font-size:12px;font-weight:600;z-index:10">Votre choix</div>
              <?php endif; ?>
              <?php if (!empty($c['numero'])): ?>
                <div class="vote-num">#<?= htmlspecialchars((string)$c['numero'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <div class="vote-name"><?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="vote-bio">
                <?= htmlspecialchars((string)($c['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
              </div>
              <div class="vote-actions">
                <button type="button" class="btn ghost js-select-card" <?= $alreadyVoted ? 'disabled' : '' ?>><?= $alreadyVoted ? 'SÉLECTIONNÉ' : 'SÉLECTIONNER' ?></button>
              </div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="confirm-row">
          <small>Une fois confirmé, votre vote ne peut plus être modifié.</small>
          <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
            <a class="btn ghost" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Annuler</a>
            <button type="submit" class="btn primary" id="confirmBtn" data-loading="Enregistrement..." <?= $alreadyVoted ? 'disabled' : '' ?>>
              <?= $alreadyVoted ? 'VOTE DÉJÀ ENREGISTRÉ' : 'CONFIRMER MON VOTE' ?>
            </button>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>

<!-- Vote Success Modal -->
<div class="vote-success-overlay<?= $success ? ' show' : '' ?>" id="voteSuccessOverlay">
  <div class="vote-success-modal">
    <div class="vote-success-icon">
      <div class="vote-success-check">✓</div>
    </div>
    <h2 class="vote-success-title">Vote enregistré !</h2>
    <p class="vote-success-subtitle">
      Merci. Votre vote a bien été enregistré de manière sécurisée.<br/>
      Votre choix demeure totalement confidentiel.
    </p>
    <div class="vote-success-actions">
      <a class="btn ghost" href="../app/audit.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Transparence & Audit</a>
      <a class="btn primary" href="../app/dashboard.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Tableau de bord</a>
    </div>
  </div>
</div>

<div class="img-lightbox" id="voteImageLightbox" aria-hidden="true">
  <div class="img-lightbox-card">
    <div class="img-lightbox-body">
      <img src="" alt="" id="voteLightboxImg" />
    </div>
  </div>
</div>

<script>
  // Auto-show success modal if vote was successful
  document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success): ?>
      const overlay = document.getElementById('voteSuccessOverlay');
      if (overlay) {
        setTimeout(() => overlay.classList.add('show'), 200);
      }
    <?php endif; ?>
  });

  (function(){
    const page = document.getElementById('votePage');
    if (!page) return;
    const cards = Array.from(page.querySelectorAll('.vote-card'));
    const radios = Array.from(page.querySelectorAll('input[type="radio"][name="id_candidat"]'));
    const confirmBtn = document.getElementById('confirmBtn');
    const lightbox = document.getElementById('voteImageLightbox');
    const lightboxImg = document.getElementById('voteLightboxImg');
    const closeLightboxBtn = document.querySelector('.js-close-lightbox');
    const voteForm = document.getElementById('voteForm');

    // Ensure no card appears selected unless a radio is actually checked.
    cards.forEach(c => c.classList.remove('selected'));
    // Some browsers may auto-select radios; keep explicit control.
    radios.forEach(r => { if (!r.disabled) r.checked = false; });

    function updateState(){
      const checked = radios.find(r => r.checked);
      cards.forEach(card => {
        const cid = card.getAttribute('data-cid');
        card.classList.toggle('selected', checked && String(checked.value) === String(cid));
      });
      if (!confirmBtn) return;
      if (page.classList.contains('already-voted')) {
        confirmBtn.disabled = true;
        return;
      }
      confirmBtn.disabled = !checked;
    }

    function openLightbox(src, alt){
      if (!lightbox || !lightboxImg) return;
      lightboxImg.src = src;
      lightboxImg.alt = alt || '';
      lightbox.classList.add('open');
      lightbox.setAttribute('aria-hidden','false');
    }

    function closeLightbox(){
      if (!lightbox || !lightboxImg) return;
      lightbox.classList.remove('open');
      lightbox.setAttribute('aria-hidden','true');
      lightboxImg.src = '';
    }

    cards.forEach(card => {
      const cid = card.getAttribute('data-cid');
      const radio = radios.find(r => String(r.value) === String(cid));
      const btn = card.querySelector('.js-select-card');
      const img = card.querySelector('.vote-avatar img');
      function select(){
        if (!radio) return;
        radio.checked = true;
        updateState();
      }
      card.addEventListener('click', (e) => {
        if (e.target === btn) {
          e.preventDefault();
          select();
        } else if (!(e.target instanceof HTMLInputElement)) {
          e.preventDefault();
          select();
        }
      });
      if (btn) {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          select();
        });
      }
      if (img) {
        img.addEventListener('click', (e) => {
          e.stopPropagation();
          select();
          openLightbox(img.src, card.querySelector('.vote-name')?.textContent || '');
        });
      }
      if (radio) {
        radio.addEventListener('change', updateState);
      }
    });

    if (lightbox) {
      lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target === closeLightboxBtn) {
          closeLightbox();
        }
      });
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLightbox();
      });
    }

    // Handle toggle for viewing candidates after voting
    const toggleBtn = document.getElementById('toggleViewCandidates');
    const revoteContainer = document.getElementById('revoteContainer');
    if (toggleBtn && revoteContainer) {
      toggleBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const isHidden = revoteContainer.style.display === 'none' || revoteContainer.style.display === '';
        if (isHidden) {
          revoteContainer.style.display = 'block';
          revoteContainer.style.opacity = '0';
          revoteContainer.style.maxHeight = '0';
          revoteContainer.style.overflow = 'hidden';
          setTimeout(() => {
            revoteContainer.style.transition = 'opacity 0.3s ease, max-height 0.3s ease';
            revoteContainer.style.opacity = '1';
            revoteContainer.style.maxHeight = '2000px';
          }, 10);
          toggleBtn.textContent = 'Masquer les candidats';
        } else {
          revoteContainer.style.transition = 'opacity 0.3s ease, max-height 0.3s ease';
          revoteContainer.style.opacity = '0';
          revoteContainer.style.maxHeight = '0';
          setTimeout(() => {
            revoteContainer.style.display = 'none';
          }, 300);
          toggleBtn.textContent = 'Afficher les candidats';
        }
      });
    }

    updateState();
  })();
</script>

<?php votigo_layout_end(); ?>
