<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();

$idElection = isset($_GET['id_election']) ? (int)$_GET['id_election'] : 0;
if ($idElection > 0) {
    $stmt = $pdo->prepare('SELECT * FROM ELECTIONS WHERE id_election = :id LIMIT 1');
    $stmt->execute([':id' => $idElection]);
    $election = $stmt->fetch();
} else {
    $election = $pdo->query("SELECT * FROM ELECTIONS WHERE statut='actif' ORDER BY date_debut DESC LIMIT 1")->fetch();
    if (!$election) {
        $election = $pdo->query("SELECT * FROM ELECTIONS ORDER BY id_election DESC LIMIT 1")->fetch();
    }
}

$candidats = [];
if ($election) {
    // Supporte ordre si la colonne existe, sinon fallback à l'ordre d'insert.
    $db = (string)($pdo->query('SELECT DATABASE() AS d')->fetch()['d'] ?? '');
    $hasOrdre = false;
    $hasNumero = false;
    if ($db !== '') {
        $st = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'CANDIDATS' AND COLUMN_NAME = 'ordre'");
        $st->execute([$db]);
        $hasOrdre = ((int)($st->fetch()['c'] ?? 0)) === 1;
        $st2 = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'CANDIDATS' AND COLUMN_NAME = 'numero'");
        $st2->execute([$db]);
        $hasNumero = ((int)($st2->fetch()['c'] ?? 0)) === 1;
    }
    $sql = $hasOrdre
        ? ('SELECT id_candidat, nom_candidat, bio, ' . ($hasNumero ? 'numero, ' : '') . 'ordre FROM CANDIDATS WHERE id_election = :id ORDER BY ordre ASC, id_candidat ASC')
        : ('SELECT id_candidat, nom_candidat, bio, ' . ($hasNumero ? 'numero ' : '') . 'FROM CANDIDATS WHERE id_election = :id ORDER BY id_candidat ASC');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => (int)$election['id_election']]);
    $candidats = $stmt->fetchAll();
}

votigo_layout_start('Prévisualisation vote — VOTIGO', 'admin', [
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

<div class="h1">Prévisualisation (admin)</div>
<div class="muted">Lecture seule : aucune action de vote n’est possible depuis l’administration.</div>

<section class="card" style="margin-top:16px">
  <div class="hd">
    <h3><?= $election ? htmlspecialchars((string)$election['titre'], ENT_QUOTES, 'UTF-8') : 'Aucune élection' ?></h3>
    <div class="spacer"></div>
    <?php if ($election): ?>
      <span class="badge"><?= htmlspecialchars((string)($election['statut'] ?? 'programmé'), ENT_QUOTES, 'UTF-8') ?></span>
    <?php endif; ?>
  </div>
  <div class="bd">
    <?php if (!$election): ?>
      <div class="muted">Aucune élection disponible.</div>
      <div style="margin-top:12px">
        <a class="btn primary" href="admin.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour admin</a>
      </div>
    <?php elseif (empty($candidats)): ?>
      <div class="muted">Aucun candidat n’a encore été ajouté pour cette élection.</div>
      <div style="margin-top:12px">
        <a class="btn primary" href="election.php?e=<?= (int)$election['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Ajouter des candidats</a>
      </div>
    <?php else: ?>
      <div class="list">
        <?php $i = 1; foreach ($candidats as $c): ?>
          <div class="mini" style="display:flex;gap:12px;align-items:flex-start">
            <div style="font-weight:900;min-width:60px">#<?= htmlspecialchars((string)($c['numero'] ?? ($c['ordre'] ?? $i)), ENT_QUOTES, 'UTF-8') ?></div>
            <div style="flex:1">
              <div style="font-weight:900"><?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?></div>
              <?php if (!empty($c['bio'])): ?>
                <div class="muted" style="font-size:13px;margin-top:6px;line-height:1.35">
                  <?= htmlspecialchars((string)$c['bio'], ENT_QUOTES, 'UTF-8') ?>
                </div>
              <?php endif; ?>
            </div>
            <span class="badge">Aperçu</span>
          </div>
        <?php $i++; endforeach; ?>
      </div>

      
      <div style="margin-top:12px;display:flex;gap:10px">
        <a class="btn primary" href="admin.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Retour admin</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php votigo_layout_end(); ?>

