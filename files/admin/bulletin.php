<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$idElection = isset($_GET['e']) ? (int)$_GET['e'] : 0;
$error = '';
$election = null;
$candidats = [];

function fmt_date_fr(?string $value): string
{
    $v = trim((string)$value);
    if ($v === '') return '';
    try {
        $dt = new DateTime($v);
    } catch (Throwable $e) {
        return $v;
    }
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    $m = (int)$dt->format('n');
    return (int)$dt->format('j') . ' ' . ($months[$m] ?? $dt->format('m')) . ' ' . $dt->format('Y');
}

if ($idElection > 0) {
    try {
        $stmt = $pdo->prepare('SELECT id_election, titre, description, date_debut, date_fin, statut FROM elections WHERE id_election = :e LIMIT 1');
        $stmt->execute([':e' => $idElection]);
        $election = $stmt->fetch() ?: null;
        if (!$election) {
            $error = "Élection introuvable.";
        } else {
            // On supporte ordre s'il existe, sinon fallback id_candidat.
            $db = (string)($pdo->query('SELECT DATABASE() AS d')->fetch()['d'] ?? '');
            $hasOrdre = false;
            if ($db !== '') {
                $st = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'candidats' AND COLUMN_NAME = 'ordre'");
                $st->execute([$db]);
                $hasOrdre = ((int)($st->fetch()['c'] ?? 0)) === 1;
            }
            $dbHasNumero = false;
            if ($db !== '') {
                $st2 = $pdo->prepare("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'candidats' AND COLUMN_NAME = 'numero'");
                $st2->execute([$db]);
                $dbHasNumero = ((int)($st2->fetch()['c'] ?? 0)) === 1;
            }
            $sql = $hasOrdre
                ? ('SELECT id_candidat, nom_candidat, bio, ' . ($dbHasNumero ? 'numero, ' : '') . 'ordre, image_mime FROM candidats WHERE id_election = :e ORDER BY ordre ASC, id_candidat ASC')
                : ('SELECT id_candidat, nom_candidat, bio, ' . ($dbHasNumero ? 'numero, ' : '') . 'image_mime FROM candidats WHERE id_election = :e ORDER BY id_candidat ASC');
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':e' => $idElection]);
            $candidats = $stmt->fetchAll();
        }
    } catch (Throwable $e) {
        $error = "Erreur serveur lors du chargement du bulletin.";
    }
} else {
    $error = "Identifiant élection invalide.";
}

votigo_layout_start('Bulletin — Administration', 'admin', [
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

<div class="h1">Bulletin</div>
<div class="muted">Liste des candidats pour une élection. (Affichage uniquement)</div>

<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Bulletin" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<div style="margin-top:14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
  <a class="btn ghost" href="admin.php?e=<?= (int)$idElection ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">← Retour admin</a>
  <a class="btn ghost" href="admin.php" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Accueil admin</a>
</div>

<?php if ($election): ?>
  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>#<?= (int)$election['id_election'] ?> — <?= htmlspecialchars((string)$election['titre'], ENT_QUOTES, 'UTF-8') ?></h3>
      <div class="spacer"></div>
      <span class="badge"><?= htmlspecialchars((string)$election['statut'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="bd">
      <?php if (!empty($election['description'])): ?>
        <div class="muted" style="margin-bottom:12px;line-height:1.55"><?= htmlspecialchars((string)$election['description'], ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <div class="muted" style="font-size:12px;margin-bottom:12px">
        <?= htmlspecialchars(fmt_date_fr((string)$election['date_debut']), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars(fmt_date_fr((string)$election['date_fin']), ENT_QUOTES, 'UTF-8') ?>
      </div>

      <?php if (empty($candidats)): ?>
        <div class="muted">Aucun candidat n’a encore été ajouté pour cette élection.</div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php $i = 1; foreach ($candidats as $c): ?>
            <div class="mini" style="display:flex;gap:12px;align-items:center">
              <div style="font-weight:900;min-width:56px">#<?= htmlspecialchars((string)($c['numero'] ?? ($c['ordre'] ?? $i)), ENT_QUOTES, 'UTF-8') ?></div>
              <div class="thumb" style="width:62px;height:62px;border-radius:18px;border:1px solid rgba(255,255,255,.10);background:rgba(255,255,255,.04);display:grid;place-items:center;overflow:hidden">
                <?php if (!empty($c['image_mime'])): ?>
                  <img alt="" src="candidat_image.php?id=<?= (int)$c['id_candidat'] ?>" style="width:100%;height:100%;object-fit:cover" />
                <?php else: ?>
                  <span class="muted">IMG</span>
                <?php endif; ?>
              </div>
              <div style="flex:1">
                <div style="font-weight:900"><?= htmlspecialchars((string)$c['nom_candidat'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($c['bio'])): ?>
                  <div class="muted" style="font-size:13px;margin-top:6px;line-height:1.35"><?= htmlspecialchars((string)$c['bio'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
              </div>
              <span class="badge">Candidat</span>
            </div>
          <?php $i++; endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<?php votigo_layout_end(); ?>

