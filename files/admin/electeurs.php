<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$error = '';

$selectedElectionId = isset($_GET['e']) ? (int)$_GET['e'] : 0;
$qe = trim((string)($_GET['qe'] ?? ''));
$es = trim((string)($_GET['es'] ?? ''));
$sortE = (string)($_GET['sortE'] ?? 'id_desc');
$pe = max(1, (int)($_GET['pe'] ?? 1));

$q = trim((string)($_GET['q'] ?? ''));
$us = (string)($_GET['us'] ?? 'id_desc');
$allowedUs = ['id_desc', 'nom_asc', 'nom_desc', 'date_desc', 'date_asc'];
$us = in_array($us, $allowedUs, true) ? $us : 'id_desc';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Compteurs + pagination
$totalUsers = (int)$pdo->query('SELECT COUNT(*) AS c FROM USERS')->fetch()['c'];
if ($q !== '') {
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM USERS WHERE nom LIKE :q OR email LIKE :q');
    $stmt->execute([':q' => '%' . $q . '%']);
    $filteredUsers = (int)$stmt->fetch()['c'];
} else {
    $filteredUsers = $totalUsers;
}
$totalPages = max(1, (int)ceil($filteredUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$orderU = 'id_user DESC';
if ($us === 'nom_asc') $orderU = 'nom ASC, id_user DESC';
if ($us === 'nom_desc') $orderU = 'nom DESC, id_user DESC';
if ($us === 'date_desc') $orderU = 'date_inscription DESC, id_user DESC';
if ($us === 'date_asc') $orderU = 'date_inscription ASC, id_user ASC';

$limitU = (int)$perPage;
$offU = (int)$offset;
if ($q !== '') {
    $stmt = $pdo->prepare("SELECT id_user, nom, email, date_inscription FROM USERS WHERE nom LIKE :q OR email LIKE :q ORDER BY $orderU LIMIT $limitU OFFSET $offU");
    $stmt->execute([':q' => '%' . $q . '%']);
    $users = $stmt->fetchAll();
} else {
    $users = $pdo->query("SELECT id_user, nom, email, date_inscription FROM USERS ORDER BY $orderU LIMIT $limitU OFFSET $offU")->fetchAll();
}

votigo_layout_start('Électeurs — Administration — VOTIGO', 'admin', [
    'userName' => auth_user_name(),
    'verified' => true,
    'logo' => '../../images/IMG-20260127-WA0054.jpg',
    'logoAlt' => 'Logo Votigo',
    'secondaryLogo' => '../../images/logo_ispm.png',
    'secondaryLogoAlt' => 'Logo ISPM',
    'navMode' => 'admin',
    'isAdmin' => true,
]);

$back = 'admin.php?e=' . (int)$selectedElectionId
  . '&qe=' . urlencode($qe)
  . '&es=' . urlencode($es)
  . '&sortE=' . urlencode($sortE)
  . '&pe=' . (int)$pe
  . '&q=' . urlencode($q)
  . '&us=' . urlencode($us)
  . '&p=' . (int)$page
  . '#electeurs';
?>

<div class="h1">Électeurs</div>
<div class="muted">Liste dédiée des électeurs inscrits (l’admin ne voit pas “qui a voté pour qui”).</div>

<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Électeurs" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<style>
  #electeursPage .toolbar{
    position:sticky;top:14px;z-index:20;margin-top:12px;
    display:flex;gap:10px;align-items:center;flex-wrap:wrap;
    padding:10px;border-radius:18px;
    background:linear-gradient(180deg, rgba(10,28,42,.78), rgba(10,28,42,.55));
    border:1px solid rgba(255,255,255,.08);
    backdrop-filter: blur(10px);
  }
  #electeursPage .toolbar .spacer{margin-left:auto}
  #electeursPage .filters{
    margin:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;
  }
  #electeursPage .filters .grow{flex:1;min-width:240px}
  #electeursPage .filters .sel{min-width:220px}
  #electeursPage .list-row{
    display:grid;
    grid-template-columns: 72px 64px 1fr auto;
    gap:12px;
    align-items:center;
  }
  #electeursPage .list-row .right{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
  #electeursPage .list-row .id{font-weight:900;min-width:72px}
  #electeursPage .list-row .sub{color:var(--muted);font-size:13px;margin-top:4px}
  #electeursPage .list-row .meta2{color:var(--muted);font-size:12px;white-space:nowrap}
  #electeursPage .userpic{
    width:56px;height:56px;border-radius:18px;
    display:grid;place-items:center;
    font-weight:900;letter-spacing:.02em;
    color:rgba(234,244,255,.92);
    border:1px solid rgba(255,255,255,.14);
    background:linear-gradient(135deg, rgba(26,169,184,.55), rgba(99,185,111,.35));
    box-shadow:0 14px 50px rgba(0,0,0,.28);
    transform:translateZ(0);
    transition:transform .18s ease, filter .18s ease, box-shadow .18s ease;
    position:relative;
    overflow:hidden;
  }
  #electeursPage .userpic::after{
    content:"";
    position:absolute;inset:-30%;
    background:radial-gradient(circle at 30% 30%, rgba(255,255,255,.28), rgba(255,255,255,0) 55%);
    transform:rotate(12deg);
    opacity:.55;
  }
  #electeursPage .mini:hover .userpic{
    transform:translateY(-2px) scale(1.03);
    filter:brightness(1.08);
    box-shadow:0 18px 70px rgba(26,169,184,.10);
  }
  @media (prefers-reduced-motion: reduce){
    #electeursPage .userpic, #electeursPage .mini:hover .userpic{transition:none;transform:none}
  }
  @media (max-width: 980px){
    #electeursPage .list-row{grid-template-columns: 72px 64px 1fr}
    #electeursPage .list-row .right{grid-column:1/-1;justify-content:flex-start}
  }

  @media (max-width: 768px){
    #electeursPage .toolbar{
      flex-direction: column;
      align-items: stretch;
      gap: 12px;
      padding: 12px;
    }
    #electeursPage .toolbar .spacer{display: none}
    #electeursPage .filters .grow{min-width: 200px}
    #electeursPage .filters .sel{min-width: 180px}
    #electeursPage .userpic{width: 48px; height: 48px}
  }

  @media (max-width: 480px){
    #electeursPage .toolbar{padding: 8px}
    #electeursPage .filters{gap: 8px}
    #electeursPage .filters .grow{min-width: 150px}
    #electeursPage .filters .sel{min-width: 140px}
    #electeursPage .list-row{grid-template-columns: 60px 56px 1fr; gap: 8px}
    #electeursPage .list-row .id{min-width: 60px; font-size: 14px}
    #electeursPage .userpic{width: 40px; height: 40px; font-size: 14px}
  }
</style>

<div id="electeursPage">
  <div class="toolbar">
    <a class="btn ghost" href="<?= htmlspecialchars($back, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">← Retour admin</a>
    <div class="spacer"></div>
    <span class="pill muted"><?= (int)$filteredUsers ?> résultat(s) • <?= (int)$totalUsers ?> total</span>
  </div>

  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>Recherche & tri</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
    </div>
    <div class="bd">
      <form method="get" action="electeurs.php" class="filters">
        <input type="hidden" name="e" value="<?= (int)$selectedElectionId ?>" />
        <input type="hidden" name="qe" value="<?= htmlspecialchars($qe, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="es" value="<?= htmlspecialchars($es, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="sortE" value="<?= htmlspecialchars($sortE, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="pe" value="<?= (int)$pe ?>" />
        <input type="hidden" name="p" value="1" />

        <label class="grow" style="margin:0">
          <input name="q" type="text" placeholder="Rechercher par nom ou email…" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" />
        </label>
        <label class="sel" style="margin:0">
          <select name="us">
            <option value="id_desc" <?= $us === 'id_desc' ? 'selected' : '' ?>>Tri : récent</option>
            <option value="nom_asc" <?= $us === 'nom_asc' ? 'selected' : '' ?>>Tri : nom A→Z</option>
            <option value="nom_desc" <?= $us === 'nom_desc' ? 'selected' : '' ?>>Tri : nom Z→A</option>
            <option value="date_desc" <?= $us === 'date_desc' ? 'selected' : '' ?>>Tri : inscription ↓</option>
            <option value="date_asc" <?= $us === 'date_asc' ? 'selected' : '' ?>>Tri : inscription ↑</option>
          </select>
        </label>
        <button class="btn primary" type="submit">Rechercher</button>
        <?php if ($q !== '' || $us !== 'id_desc'): ?>
          <a class="btn ghost" href="electeurs.php?e=<?= (int)$selectedElectionId ?>&qe=<?= urlencode($qe) ?>&es=<?= urlencode($es) ?>&sortE=<?= urlencode($sortE) ?>&pe=<?= (int)$pe ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Réinitialiser</a>
        <?php endif; ?>
      </form>
    </div>
  </section>

  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>Liste</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px"><?= (int)$filteredUsers ?> résultat(s)</span>
    </div>
    <div class="bd">
      <?php if (empty($users)): ?>
        <div class="muted">Aucun électeur en base.</div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($users as $u): ?>
            <?php
              $name = trim((string)($u['nom'] ?? ''));
              $initial = $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') : 'U';
            ?>
            <div class="mini list-row">
              <div class="id">#<?= (int)$u['id_user'] ?></div>
              <div class="userpic" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
              <div>
                <div style="font-weight:900"><?= htmlspecialchars((string)$u['nom'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub"><?= htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') ?></div>
              </div>
              <div class="right">
                <div class="meta2"><?= htmlspecialchars((string)$u['date_inscription'], ENT_QUOTES, 'UTF-8') ?></div>
                <a class="btn ghost" href="electeur.php?id=<?= (int)$u['id_user'] ?>&back=<?= urlencode($back) ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Voir</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <?php
            $base = 'electeurs.php?e=' . (int)$selectedElectionId
              . '&qe=' . urlencode($qe)
              . '&es=' . urlencode($es)
              . '&sortE=' . urlencode($sortE)
              . '&pe=' . (int)$pe
              . '&q=' . urlencode($q)
              . '&us=' . urlencode($us)
              . '&p=';
          ?>
          <a class="btn ghost" href="<?= $base . max(1, $page - 1) ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Précédent</a>
          <span class="pill muted">Page <?= (int)$page ?> / <?= (int)$totalPages ?></span>
          <a class="btn ghost" href="<?= $base . min($totalPages, $page + 1) ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Suivant</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php votigo_layout_end(); ?>

