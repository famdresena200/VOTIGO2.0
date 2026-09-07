<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$back = trim((string)($_GET['back'] ?? ''));
$backUrl = 'admin.php';
if ($back !== '') {
    // On accepte uniquement un retour local (pas de schéma/host)
    if (preg_match('~^(?:/|[a-zA-Z0-9_\\-]+\\.php\\b)~', $back) && !preg_match('~^[a-z]+://~i', $back)) {
        $backUrl = $back;
    }
}

$backE = isset($_GET['back_e']) ? (int)$_GET['back_e'] : 0;
$backQ = (string)($_GET['back_q'] ?? '');
$backP = isset($_GET['back_p']) ? (int)$_GET['back_p'] : 1;
if ($backUrl === 'admin.php') {
    $qs = [];
    if ($backE > 0) $qs[] = 'e=' . $backE;
    if (trim($backQ) !== '') $qs[] = 'q=' . urlencode($backQ);
    if ($backP > 1) $qs[] = 'p=' . $backP;
    if (!empty($qs)) $backUrl .= '?' . implode('&', $qs);
}

$electeur = null;
$error = '';
if ($id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT id_user, nom, email, prenom, nen, cin, telephone, date_naissance, date_inscription FROM USERS WHERE id_user = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $electeur = $stmt->fetch() ?: null;
        if (!$electeur) {
            $error = "Électeur introuvable.";
        }
    } catch (Throwable $e) {
        $error = "Erreur serveur lors du chargement de l'électeur.";
    }
} else {
    $error = "Identifiant électeur invalide.";
}

votigo_layout_start('Électeur — Administration', 'admin', [
    'userName' => auth_user_name(),
    'verified' => true,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
    'logoAlt' => 'Logo Votigo',
    'secondaryLogo' => '../../images/logo_ispm.png',
    'secondaryLogoAlt' => 'Logo ISPM',
    'navMode' => 'admin',
    'isAdmin' => true,
]);
?>

<div class="h1">Fiche Électeur</div>
<div class="muted">Consultation des informations d'inscription (sans accès aux votes).</div>

<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Électeur" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  <a class="btn ghost" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">← Retour</a>
  <a class="btn ghost" href="admin.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Accueil admin</a>
</div>

<?php if ($electeur): ?>
  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>#<?= (int)$electeur['id_user'] ?> — <?= htmlspecialchars((string)$electeur['nom'], ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="spacer"></div>
      <span class="badge">Inscrit</span>
    </div>
    <div class="bd">
      <div class="grid" style="grid-template-columns:1fr 1fr">
        <div class="mini">
          <div class="t">Identité</div>
          <div class="s" style="min-height:auto;line-height:1.55;margin-top:10px">
            <strong>Nom</strong> : <?= htmlspecialchars((string)$electeur['nom'], ENT_QUOTES, 'UTF-8') ?><br/>
            <strong>Prénom</strong> : <?= htmlspecialchars((string)($electeur['prenom'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br/>
            <strong>Date de naissance</strong> : <?= htmlspecialchars((string)($electeur['date_naissance'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
        <div class="mini">
          <div class="t">Contact</div>
          <div class="s" style="min-height:auto;line-height:1.55;margin-top:10px">
            <strong>Email</strong> : <?= htmlspecialchars((string)$electeur['email'], ENT_QUOTES, 'UTF-8') ?><br/>
            <strong>Téléphone</strong> : <?= htmlspecialchars((string)($electeur['telephone'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br/>
            <strong>Inscription</strong> : <?= htmlspecialchars((string)($electeur['date_inscription'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:14px;box-shadow:none;background:rgba(0,0,0,.14)">
        <div class="bd" style="padding:12px">
          <div style="font-weight:900;margin-bottom:8px">Pièces / Identifiants</div>
          <div class="muted" style="line-height:1.55">
            <strong>NEN</strong> : <?= htmlspecialchars((string)($electeur['nen'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br/>
            <strong>CIN</strong> : <?= htmlspecialchars((string)($electeur['cin'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<?php votigo_layout_end(); ?>

