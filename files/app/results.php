<?php
require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

$pdo = db();
require_electeur();
$idUser = auth_user_id();
$userName = auth_user_name();

function anon_token_result(int $idUser, int $idElection): string
{
    $payload = $idUser . ':' . $idElection;
    return hash_hmac('sha256', $payload, (string)($_ENV['VOTIGO_VOTE_SECRET'] ?? getenv('VOTIGO_VOTE_SECRET') ?? 'dev-votigo-secret'));
}

// Filtres & options
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all'; // all, active, finished
$sort = $_GET['sort'] ?? 'date'; // date, votes

// Affichage d'une élection spécifique
$idElection = (int)($_GET['id_election'] ?? 0);

if ($idElection > 0) {
    $stmt = $pdo->prepare('SELECT * FROM elections WHERE id_election = :id');
    $stmt->execute([':id' => $idElection]);
    $election = $stmt->fetch();

    if (!$election) {
        header('Location: results.php');
        exit;
    }

    votigo_layout_start('Résultats — ' . htmlspecialchars($election['titre'], ENT_QUOTES, 'UTF-8'), 'audit', ['userName' => $userName, 'verified' => true]);
    
    echo '<div class="h1">Résultats</div>';
    echo '<div class="muted" style="margin-bottom:20px">' . htmlspecialchars($election['titre'], ENT_QUOTES, 'UTF-8') . '</div>';

    echo '<div style="margin-bottom:24px">';
    echo '<a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">';
    echo '<span>←</span>';
    echo '<span>Retour au tableau de bord</span>';
    echo '</a>';
    echo '</div>';

    $now = new DateTime();
    try {
        $dateFinObj = new DateTime($election['date_fin']);
        $finished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
    } catch (Exception $e) {
        $finished = false;
    }

    if (!$finished) {
        echo '<div class="card"><div class="bd">⏱️ Cette élection n\'est pas encore terminée.</div></div>';
        votigo_layout_end();
        exit;
    }

    // Calculer les résultats
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM resultats WHERE id_election = :e');
        $stmt->execute([':e' => $idElection]);
        $row = $stmt->fetch();
        
        if (!$row || (int)$row['cnt'] == 0) {
            $stmt = $pdo->prepare('INSERT INTO resultats (id_election, id_candidat, nombre_votes, pourcentage) 
                SELECT :eid, c.id_candidat, COALESCE(COUNT(v.id_vote),0), 0 
                FROM candidats c LEFT JOIN votes v ON c.id_candidat=v.id_candidat AND v.id_election=:eid2 
                WHERE c.id_election=:eid3 GROUP BY c.id_candidat');
            $stmt->execute([':eid' => $idElection, ':eid2' => $idElection, ':eid3' => $idElection]);
        }

        $stmt = $pdo->prepare('SELECT SUM(nombre_votes) as tot FROM resultats WHERE id_election=:e');
        $stmt->execute([':e' => $idElection]);
        $row = $stmt->fetch();
        $total = ($row && $row['tot']) ? (int)$row['tot'] : 0;

        if ($total > 0) {
            $stmt = $pdo->prepare('UPDATE resultats SET pourcentage=ROUND((nombre_votes/:total)*100,2) WHERE id_election=:e');
            $stmt->execute([':total' => $total, ':e' => $idElection]);
        }
    } catch (Exception $ex) {
        error_log('Calcul résultats: ' . $ex->getMessage());
    }

    // Vérifier si l'utilisateur a voté sans exposer son identité dans la table des votes.
    $userVote = null;
    $stmt = $pdo->prepare('SELECT id_candidat FROM votes WHERE id_election = :e AND token_anonyme = :t LIMIT 1');
    $stmt->execute([':e' => $idElection, ':t' => anon_token_result($idUser, $idElection)]);
    $voteRow = $stmt->fetch();
    if ($voteRow) {
        $userVote = (int)$voteRow['id_candidat'];
    }

    // Récupérer candidats avec résultats
    $stmt = $pdo->prepare('SELECT c.id_candidat, c.nom_candidat, COALESCE(r.nombre_votes, 0) as votes, COALESCE(r.pourcentage, 0) as pct
        FROM candidats c LEFT JOIN resultats r ON c.id_candidat = r.id_candidat
        WHERE c.id_election = :e
        ORDER BY COALESCE(r.nombre_votes, 0) DESC');
    $stmt->execute([':e' => $idElection]);
    $cands = $stmt->fetchAll();

    if (empty($cands)) {
        echo '<div class="card"><div class="bd">Aucun candidat</div></div>';
        votigo_layout_end();
        exit;
    }

    // Afficher les résultats
    echo '<section class="card"><div class="bd" style="padding:24px">';

    // En-tête avec info sur le vote de l'utilisateur
    echo '<div style="margin-bottom:24px;padding:16px;background:rgba(255,255,255,.02);border-radius:8px;border:1px solid rgba(255,255,255,.05)">';
    if ($userVote) {
        // Récupérer le nom du candidat choisi
        $stmt = $pdo->prepare('SELECT nom_candidat FROM candidats WHERE id_candidat = :id');
        $stmt->execute([':id' => $userVote]);
        $chosenCandidate = $stmt->fetch();
        echo '<div style="display:flex;align-items:center;gap:8px">';
        echo '<span style="font-size:16px">🗳️</span>';
        echo '<span style="font-weight:600;color:#60a5fa">Votre choix : ' . htmlspecialchars($chosenCandidate['nom_candidat']) . '</span>';
        echo '</div>';
    } else {
        echo '<div style="color:rgba(255,255,255,.6);font-style:italic">Vous n\'avez pas participé à cette élection</div>';
    }
    echo '</div>';

    echo '<div class="results-detail-layout" style="display:grid;grid-template-columns:350px 1fr;gap:40px;align-items:center">';

    // Générer des couleurs dynamiques basées sur HSL
    function generateColorHSL($index, $total) {
        $hue = ($index / max($total, 1)) * 360;
        $saturation = 65 + (($index % 3) * 10); // entre 65% et 85%
        $lightness = 50;
        return "hsl($hue, {$saturation}%, {$lightness}%)";
    }

    // Donut avec CSS conic-gradient
    echo '<div style="display:flex;justify-content:center;align-items:center">';

    $gradientStops = [];
    $colorIdx = 0;
    $currentStop = 0;
    $totalCands = count($cands);

    $colors = [];
    foreach ($cands as $idx => $c) {
        $colors[$idx] = generateColorHSL($idx, $totalCands);
    }

    foreach ($cands as $idx => $c) {
        $pct = (float)$c['pct'];
        $color = $colors[$idx];

        $nextStop = $currentStop + $pct;
        $gradientStops[] = "$color {$currentStop}% {$nextStop}%";
        $currentStop = $nextStop;
    }

    $gradientString = implode(', ', $gradientStops);

    echo '<div style="width:280px;height:280px;border-radius:50%;background:conic-gradient(' . $gradientString . ');position:relative;box-shadow:0 8px 20px rgba(0,0,0,.3)">';
    echo '<div style="position:absolute;top:50%;left:50%;width:180px;height:180px;background:#0f172a;border-radius:50%;transform:translate(-50%,-50%);box-shadow:inset 0 2px 8px rgba(0,0,0,.4)"></div>';
    echo '</div>';

    echo '</div>';

    // Légende détaillée
    echo '<div>';
    echo '<h3 style="margin-top:0;margin-bottom:24px;font-size:18px;font-weight:700">Résultats du vote</h3>';

    foreach ($cands as $idx => $c) {
        $color = $colors[$idx];
        $isUserChoice = ($userVote && $userVote == $c['id_candidat']);

        $extraStyle = $isUserChoice ? 'border-left:3px solid #60a5fa;background:rgba(96,165,250,.1);box-shadow:0 0 0 1px rgba(96,165,250,.2)' : 'border-left:3px solid ' . $color;

        $choiceClass = $isUserChoice ? ' is-user-choice' : '';
        echo '<div class="result-candidate' . $choiceClass . '" style="display:flex;align-items:center;margin-bottom:20px;padding:12px;background:rgba(255,255,255,.02);border-radius:8px;' . $extraStyle . ';position:relative">';

        if ($isUserChoice) {
            echo '<div class="result-choice-badge">Votre choix</div>';
        }

        echo '<div style="width:20px;height:20px;border-radius:4px;background:' . $color . ';flex-shrink:0;margin-right:12px"></div>';
        echo '<div class="result-candidate-info" style="flex:1">';
        echo '<div style="font-weight:700;font-size:15px">' . htmlspecialchars($c['nom_candidat']) . '</div>';
        echo '<div style="font-size:12px;color:rgba(255,255,255,.5);margin-top:2px">' . (int)$c['votes'] . ' votes</div>';
        echo '</div>';
        echo '<div class="result-percentage" style="text-align:right">';
        echo '<div style="font-weight:900;font-size:20px;color:' . $color . '">' . $c['pct'] . '%</div>';
        echo '</div>';
        echo '</div>';
    }

    echo '<div style="margin-top:24px;padding:12px;background:rgba(26,169,184,.1);border-radius:8px;border-left:3px solid #1aa9b8;text-align:center">';
    echo '<span style="font-size:13px;color:rgba(255,255,255,.8)"><strong>Total votes:</strong> ' . $total . '</span>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div></section>';

    votigo_layout_end();
    exit;
}

// Page d'accueil - liste des élections avec filtres
$query = 'SELECT e.*, COALESCE(SUM(r.nombre_votes), 0) as total_votes FROM elections e LEFT JOIN resultats r ON e.id_election = r.id_election';
$conditions = [];
$params = [];

// Filtre recherche
if ($search !== '') {
    $conditions[] = 'e.titre LIKE ?';
    $params[] = '%' . $search . '%';
}

// Filtre statut
$now = new DateTime();
if ($status === 'finished') {
    $conditions[] = 'e.date_fin <= NOW()';
} elseif ($status === 'active') {
    $conditions[] = 'e.date_fin > NOW()';
}

if (!empty($conditions)) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}

$query .= ' GROUP BY e.id_election';

// Tri
if ($sort === 'votes') {
    $query .= ' ORDER BY total_votes DESC';
} else {
    $query .= ' ORDER BY e.date_fin DESC';
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$elections = $stmt->fetchAll() ?: [];

votigo_layout_start('Résultats des élections', 'elections', ['userName' => $userName, 'verified' => true]);
echo '<div class="h1">Résultats des élections</div>';
echo '<div class="muted" style="margin-bottom:24px">Consultez les résultats des élections terminées</div>';

echo '<div style="margin-bottom:24px">';
echo '<a href="dashboard.php" class="btn ghost" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px">';
echo '<span>←</span>';
echo '<span>Retour au tableau de bord</span>';
echo '</a>';
echo '</div>';

// ========== BARRE DE CONTRÔLE ==========
echo '<section class="card" style="margin-bottom:24px"><div class="bd" style="padding:16px">';
echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;align-items:end">';

// Recherche
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Rechercher</label>';
echo '<input type="text" id="searchInput" placeholder="Nom d\'élection..." value="' . htmlspecialchars($search) . '" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '</div>';

// Statut
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Statut</label>';
echo '<select id="statusSelect" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '<option value="all" ' . ($status === 'all' ? 'selected' : '') . '>Tous</option>';
echo '<option value="finished" ' . ($status === 'finished' ? 'selected' : '') . '>Terminées</option>';
echo '<option value="active" ' . ($status === 'active' ? 'selected' : '') . '>En cours</option>';
echo '</select>';
echo '</div>';

// Tri
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Tri</label>';
echo '<select id="sortSelect" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '<option value="date" ' . ($sort === 'date' ? 'selected' : '') . '>Par date</option>';
echo '<option value="votes" ' . ($sort === 'votes' ? 'selected' : '') . '>Par votes</option>';
echo '</select>';
echo '</div>';

echo '</div>';
echo '</div></section>';

echo '<script>';
echo 'document.getElementById("searchInput").addEventListener("change", function() { updateFilters(); });';
echo 'document.getElementById("statusSelect").addEventListener("change", function() { updateFilters(); });';
echo 'document.getElementById("sortSelect").addEventListener("change", function() { updateFilters(); });';
echo 'function updateFilters() {';
echo '  const search = document.getElementById("searchInput").value;';
echo '  const status = document.getElementById("statusSelect").value;';
echo '  const sort = document.getElementById("sortSelect").value;';
echo '  const params = new URLSearchParams();';
echo '  if (search) params.append("search", search);';
echo '  if (status) params.append("status", status);';
echo '  if (sort) params.append("sort", sort);';
echo '  window.location.href = "results.php?" + params.toString();';
echo '}';
echo '</script>';

if (empty($elections)) {
    echo '<div class="card"><div class="bd" style="text-align:center;padding:40px">';
    echo '<div style="font-size:16px;margin-bottom:8px">📊 Aucun résultat disponible</div>';
    echo '<div style="color:rgba(255,255,255,.6)">Les résultats apparaîtront ici une fois les élections terminées</div>';
    echo '</div></div>';
} else {
    echo '<div style="display:grid;gap:16px">';
    foreach ($elections as $e) {
        $now = new DateTime();
        try {
            $dateFinObj = new DateTime($e['date_fin']);
            $finished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
            $statusText = $finished ? 'Terminée' : 'En cours';
            $statusColor = $finished ? '#10b981' : '#f59e0b';
            $statusIcon = $finished ? '✓' : '⏱️';
        } catch (Exception $ex) {
            $finished = false;
            $statusText = 'Statut inconnu';
            $statusColor = '#6b7280';
            $statusIcon = '❓';
        }

        // Vérifier le vote avec le token anonyme utilisé par la table votes.
        $stmt = $pdo->prepare('SELECT COUNT(*) as has_voted FROM votes WHERE id_election = :e AND token_anonyme = :t');
        $stmt->execute([':e' => $e['id_election'], ':t' => anon_token_result($idUser, (int)$e['id_election'])]);
        $hasVoted = (bool)$stmt->fetch()['has_voted'];

        echo '<div class="card" style="transition:all .2s ease"><div class="bd" style="padding:20px">';

        if ($finished) {
            echo '<a href="results.php?id_election=' . (int)$e['id_election'] . '" style="display:block;color:inherit;text-decoration:none">';
        }

        echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">';
        echo '<div style="flex:1">';
        echo '<h3 style="margin:0;margin-bottom:6px;font-size:16px;font-weight:700">' . htmlspecialchars($e['titre']) . '</h3>';
        if (!empty($e['description'])) {
            echo '<div class="muted" style="font-size:13px;line-height:1.4;margin-bottom:8px">' . htmlspecialchars($e['description']) . '</div>';
        }
        echo '<div class="muted" style="font-size:12px">' . format_date_fr($e['date_fin']) . '</div>';
        echo '</div>';

        echo '<div style="display:flex;flex-direction:column;gap:8px;min-width:120px">';
        echo '<span style="padding:4px 8px;background:rgba(' . ($finished ? '16,185,129' : '245,158,11') . ',.1);color:' . $statusColor . ';border-radius:4px;font-size:11px;font-weight:600;text-align:center">' . $statusIcon . ' ' . $statusText . '</span>';

        if ($hasVoted) {
            echo '<span style="padding:4px 8px;background:rgba(96,165,250,.1);color:#60a5fa;border-radius:4px;font-size:11px;font-weight:600;text-align:center">🗳️ Voté</span>';
        }

        echo '</div>';
        echo '</div>';

        if ($finished) {
            echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:16px;padding-top:16px;border-top:1px solid rgba(255,255,255,.05)">';
            echo '<span class="muted" style="font-size:12px">Cliquez pour voir les résultats détaillés</span>';
            echo '<span style="font-size:14px">📊</span>';
            echo '</div>';
            echo '</a>';
        } else {
            echo '<div style="margin-top:16px;padding:12px;background:rgba(245,158,11,.1);border-radius:6px;border-left:3px solid #f59e0b">';
            echo '<span style="color:#f59e0b;font-size:13px">⏱️ Les résultats seront disponibles une fois l\'élection terminée</span>';
            echo '</div>';
        }

        echo '</div></div>';
    }
    echo '</div>';
}

echo '</div>';
votigo_layout_end();
?>
