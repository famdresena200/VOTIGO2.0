<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();

$pdo = db();
$error = '';
$info = '';

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
    $day = (int)$dt->format('j');
    $year = (int)$dt->format('Y');
    return $day . ' ' . ($months[$m] ?? $dt->format('m')) . ' ' . $year;
}

// Changer rapidement le statut d'une élection
if (($_POST['action'] ?? '') === 'set_status') {
    $idElection = (int)($_POST['id_election'] ?? 0);
    $statut = (string)($_POST['statut'] ?? '');
    if ($idElection <= 0 || !in_array($statut, ['programmé', 'actif', 'fermé'], true)) {
        $error = "Action invalide.";
    } else {
        try {
            $stmt = $pdo->prepare('UPDATE ELECTIONS SET statut = :s WHERE id_election = :id');
            $stmt->execute([':s' => $statut, ':id' => $idElection]);
            $info = "Statut de l'élection mis à jour.";
        } catch (PDOException $e) {
            $error = "Erreur serveur lors de la mise à jour du statut.";
        }
    }
}

$selectedElectionId = isset($_GET['e']) ? (int)$_GET['e'] : 0;

$qe = trim((string)($_GET['qe'] ?? ''));
$es = trim((string)($_GET['es'] ?? '')); // statut filtre: actif|programmé|fermé|''
$es = in_array($es, ['actif', 'programmé', 'fermé'], true) ? $es : '';
$sortE = (string)($_GET['sortE'] ?? 'id_desc'); // id_desc|debut_desc|fin_desc|debut_asc|fin_asc
$allowedSortE = ['id_desc', 'debut_desc', 'fin_desc', 'debut_asc', 'fin_asc'];
$sortE = in_array($sortE, $allowedSortE, true) ? $sortE : 'id_desc';
$pe = max(1, (int)($_GET['pe'] ?? 1));
$perPageE = 15;
$offsetE = ($pe - 1) * $perPageE;

$totalElections = (int)$pdo->query('SELECT COUNT(*) AS c FROM ELECTIONS')->fetch()['c'];
$whereE = [];
$paramsE = [];
if ($qe !== '') {
    $whereE[] = '(titre LIKE :q1 OR description LIKE :q2 OR statut LIKE :q3)';
    $paramsE[':q1'] = '%' . $qe . '%';
    $paramsE[':q2'] = '%' . $qe . '%';
    $paramsE[':q3'] = '%' . $qe . '%';
}
if ($es !== '') {
    $whereE[] = 'statut = :es';
    $paramsE[':es'] = $es;
}
$whereSqlE = empty($whereE) ? '' : ('WHERE ' . implode(' AND ', $whereE));

$stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM ELECTIONS $whereSqlE");
$stmt->execute($paramsE);
$filteredElections = (int)$stmt->fetch()['c'];

$totalPagesE = max(1, (int)ceil($filteredElections / $perPageE));
$pe = min($pe, $totalPagesE);
$offsetE = ($pe - 1) * $perPageE;

$limitE = (int)$perPageE;
$offE = (int)$offsetE;
$orderSqlE = 'id_election DESC';
if ($sortE === 'debut_desc') $orderSqlE = 'date_debut DESC, id_election DESC';
if ($sortE === 'fin_desc') $orderSqlE = 'date_fin DESC, id_election DESC';
if ($sortE === 'debut_asc') $orderSqlE = 'date_debut ASC, id_election ASC';
if ($sortE === 'fin_asc') $orderSqlE = 'date_fin ASC, id_election ASC';

$stmt = $pdo->prepare("SELECT id_election, titre, statut, date_debut, date_fin FROM ELECTIONS $whereSqlE ORDER BY $orderSqlE LIMIT $limitE OFFSET $offE");
$stmt->execute($paramsE);
$elections = $stmt->fetchAll();

if ($selectedElectionId <= 0) {
    $selectedElectionId = (int)($elections[0]['id_election'] ?? 0);
}

votigo_layout_start('Élections — Administration — VOTIGO', 'admin', [
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

<div class="h1">Élections</div>
<div class="muted">Liste dédiée (actions rapides + accès à la gestion des candidats).</div>

<?php if ($info !== ''): ?>
  <div data-flash-type="ok" data-flash-title="Élections" data-flash-message="<?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
  <div data-flash-type="err" data-flash-title="Élections" data-flash-message="<?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>"></div>
<?php endif; ?>

<style>
  #electionsPage .toolbar{
    position:sticky;top:14px;z-index:20;margin-top:12px;
    display:flex;gap:10px;align-items:center;flex-wrap:wrap;
    padding:10px;border-radius:18px;
    background:linear-gradient(180deg, rgba(10,28,42,.78), rgba(10,28,42,.55));
    border:1px solid rgba(255,255,255,.08);
    backdrop-filter: blur(10px);
  }
  #electionsPage .toolbar .spacer{margin-left:auto}
  #electionsPage .filters{
    margin:0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;
  }
  #electionsPage .filters .grow{flex:1;min-width:240px}
  #electionsPage .filters .sel{min-width:220px}
  #electionsPage .seg{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:10px}
  #electionsPage .seg .btn{padding:9px 14px}
  #electionsPage .list-row{
    display:grid;
    grid-template-columns: 72px 1fr auto;
    gap:12px;
    align-items:center;
  }
  #electionsPage .list-row .right{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
  #electionsPage .list-row .id{font-weight:900;min-width:72px}
  #electionsPage .list-row .sub{color:var(--muted);font-size:13px;margin-top:4px}
  @media (max-width: 980px){
    #electionsPage .list-row{grid-template-columns: 72px 1fr}
    #electionsPage .list-row .right{grid-column:1/-1;justify-content:flex-start}
  }

  @media (max-width: 768px){
    #electionsPage .toolbar{
      flex-direction: column;
      align-items: stretch;
      gap: 12px;
      padding: 12px;
    }
    #electionsPage .toolbar .spacer{display: none}
    #electionsPage .filters .grow{min-width: 200px}
    #electionsPage .filters .sel{min-width: 180px}
    #electionsPage .seg{flex-wrap: wrap; gap: 8px}
  }

  @media (max-width: 480px){
    #electionsPage .toolbar{padding: 8px}
    #electionsPage .filters{gap: 8px}
    #electionsPage .filters .grow{min-width: 150px}
    #electionsPage .filters .sel{min-width: 140px}
    #electionsPage .seg{gap: 6px}
    #electionsPage .seg .btn{font-size: 13px; padding: 7px 10px}
    #electionsPage .list-row{grid-template-columns: 60px 1fr; gap: 8px}
    #electionsPage .list-row .id{min-width: 60px; font-size: 14px}
  }
</style>

<div id="electionsPage">
  <div class="toolbar">
    <a class="btn ghost" href="admin.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">← Retour admin</a>
    <div class="spacer"></div>
    <span class="pill muted"><?= (int)$filteredElections ?> résultat(s) • <?= (int)$totalElections ?> total</span>
  </div>

  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>Recherche & filtres</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px">Page <?= (int)$pe ?> / <?= (int)$totalPagesE ?></span>
    </div>
    <div class="bd">
      <form method="get" action="elections.php" class="filters">
        <input type="hidden" name="e" value="<?= (int)$selectedElectionId ?>" />
        <label class="grow" style="margin:0">
          <input name="qe" type="text" placeholder="Rechercher une élection (titre, statut…)…" value="<?= htmlspecialchars($qe, ENT_QUOTES, 'UTF-8') ?>" />
        </label>
        <label class="sel" style="margin:0">
          <select name="sortE">
            <option value="id_desc" <?= $sortE === 'id_desc' ? 'selected' : '' ?>>Tri : récent</option>
            <option value="debut_desc" <?= $sortE === 'debut_desc' ? 'selected' : '' ?>>Tri : début ↓</option>
            <option value="debut_asc" <?= $sortE === 'debut_asc' ? 'selected' : '' ?>>Tri : début ↑</option>
            <option value="fin_desc" <?= $sortE === 'fin_desc' ? 'selected' : '' ?>>Tri : fin ↓</option>
            <option value="fin_asc" <?= $sortE === 'fin_asc' ? 'selected' : '' ?>>Tri : fin ↑</option>
          </select>
        </label>
        <button class="btn primary" type="submit">Rechercher</button>
        <?php if ($qe !== '' || $es !== '' || $sortE !== 'id_desc'): ?>
          <a class="btn ghost" href="elections.php?e=<?= (int)$selectedElectionId ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Réinitialiser</a>
        <?php endif; ?>
      </form>

      <?php
        $baseFilter = 'elections.php?e=' . (int)$selectedElectionId
          . '&qe=' . urlencode($qe)
          . '&sortE=' . urlencode($sortE)
          . '&pe=1'
          . '&es=';
      ?>
      <div class="seg">
        <a class="btn ghost" href="<?= $baseFilter ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Toutes</a>
        <a class="btn ghost" href="<?= $baseFilter . 'actif' ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Actives</a>
        <a class="btn ghost" href="<?= $baseFilter . urlencode('programmé') ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Programmées</a>
        <a class="btn ghost" href="<?= $baseFilter . 'fermé' ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Fermées</a>
        <?php if ($es !== ''): ?>
          <span class="pill muted">Filtre : <?= htmlspecialchars($es, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="card" style="margin-top:16px">
    <div class="hd">
      <h3>Liste</h3>
      <div class="spacer"></div>
      <span class="muted" style="font-size:13px"><?= (int)$filteredElections ?> résultat(s)</span>
    </div>
    <div class="bd">
      <?php if (empty($elections)): ?>
        <div class="muted">Aucune élection en base.</div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($elections as $e): ?>
            <div class="mini list-row">
              <div class="id">#<?= (int)$e['id_election'] ?></div>
              <div>
                <div style="font-weight:900"><?= htmlspecialchars((string)$e['titre'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="sub">
                  <?= htmlspecialchars(fmt_date_fr((string)$e['date_debut']), ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars(fmt_date_fr((string)$e['date_fin']), ENT_QUOTES, 'UTF-8') ?>
                </div>
              </div>
              <div class="right">
                <span class="badge"><?= htmlspecialchars((string)$e['statut'], ENT_QUOTES, 'UTF-8') ?></span>
                <a class="btn ghost" href="election.php?e=<?= (int)$e['id_election'] ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Voir les candidats</a>

                <form method="post" action="elections.php?e=<?= (int)$selectedElectionId ?>&qe=<?= urlencode($qe) ?>&es=<?= urlencode($es) ?>&sortE=<?= urlencode($sortE) ?>&pe=<?= (int)$pe ?>" style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                  <input type="hidden" name="action" value="set_status" />
                  <input type="hidden" name="id_election" value="<?= (int)$e['id_election'] ?>" />
                  <?php if ((string)$e['statut'] !== 'actif'): ?>
                    <button class="btn primary" type="submit" name="statut" value="actif">Activer</button>
                  <?php endif; ?>
                  <?php if ((string)$e['statut'] !== 'fermé'): ?>
                    <button class="btn ghost" type="submit" name="statut" value="fermé">Fermer</button>
                  <?php endif; ?>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <?php
            $baseE = 'elections.php?e=' . (int)$selectedElectionId
              . '&qe=' . urlencode($qe)
              . '&es=' . urlencode($es)
              . '&sortE=' . urlencode($sortE)
              . '&pe=';
          ?>
          <a class="btn ghost" href="<?= $baseE . max(1, $pe - 1) ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Précédent</a>
          <span class="pill muted">Page <?= (int)$pe ?> / <?= (int)$totalPagesE ?></span>
          <a class="btn ghost" href="<?= $baseE . min($totalPagesE, $pe + 1) ?>" style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center">Suivant</a>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<?php votigo_layout_end(); ?>

