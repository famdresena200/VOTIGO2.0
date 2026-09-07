<?php
require __DIR__ . '/_inc/bootstrap_admin.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

require_admin();
$pdo = db();

// Filtres & options
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'all'; // all, active, finished
$sort = $_GET['sort'] ?? 'date'; // date, votes
$view = $_GET['view'] ?? 'full'; // full, compact

votigo_layout_start('Résultats des élections', 'admin-results', ['userName' => auth_user_name(), 'verified' => true, 'navMode' => 'admin', 'isAdmin' => true]);

echo '<style>
  @media (max-width: 768px) {
    .card .bd > div:first-child { grid-template-columns: 1fr !important; gap: 12px !important; }
  }
  @media (max-width: 480px) {
    .card .bd { padding: 12px !important; }
    .h1 { font-size: 24px !important; }
  }
</style>';

echo '<div class="h1">Tableau de bord des résultats</div>';
echo '<div class="muted" style="margin-bottom:24px">Vue d\'administration avec filtres et interactions</div>';

// ========== BARRE DE CONTRÔLE ==========
echo '<section class="card" style="margin-bottom:24px"><div class="bd" style="padding:16px">';
echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;align-items:end">';

// Recherche
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Rechercher</label>';
echo '<input type="text" id="searchInput" placeholder="Nom d\'élection..." value="' . htmlspecialchars($search) . '" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '</div>';

// Statut
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Statut</label>';
echo '<select id="statusSelect" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '<option value="all" ' . ($status === 'all' ? 'selected' : '') . '>Tous</option>';
echo '<option value="active" ' . ($status === 'active' ? 'selected' : '') . '>En cours</option>';
echo '<option value="finished" ' . ($status === 'finished' ? 'selected' : '') . '>Terminées</option>';
echo '</select>';
echo '</div>';

// Tri
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Tri</label>';
echo '<select id="sortSelect" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '<option value="date" ' . ($sort === 'date' ? 'selected' : '') . '>Par date</option>';
echo '<option value="votes" ' . ($sort === 'votes' ? 'selected' : '') . '>Par votes</option>';
echo '</select>';
echo '</div>';

// Affichage
echo '<div><label style="display:block;font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;font-weight:600">Affichage</label>';
echo '<select id="viewSelect" style="width:100%;padding:8px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:6px;color:#fff;font-size:13px">';
echo '<option value="full" ' . ($view === 'full' ? 'selected' : '') . '>Détaillé</option>';
echo '<option value="compact" ' . ($view === 'compact' ? 'selected' : '') . '>Compact</option>';
echo '</select>';
echo '</div>';

echo '</div>';
echo '</div></section>';

// ========== RÉCUPÉRATION DONNÉES ==========
$stmt = $pdo->query('SELECT * FROM elections ORDER BY date_fin DESC');
$elections = $stmt->fetchAll() ?: [];

$now = new DateTime();
$filtered = [];

foreach ($elections as $election) {
    try {
        $dateFinObj = new DateTime($election['date_fin']);
        $finished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
    } catch (Exception $e) {
        $finished = false;
    }
    
    // Filtre statut
    if ($status === 'active' && $finished) continue;
    if ($status === 'finished' && !$finished) continue;
    
    // Filtre recherche
    if ($search && stripos($election['titre'], $search) === false) continue;
    
    $filtered[] = ['data' => $election, 'finished' => $finished];
}

// Tri
if ($sort === 'votes') {
    usort($filtered, function($a, $b) use ($pdo) {
        $getVotes = function($id) use ($pdo) {
            $stmt = $pdo->prepare('SELECT SUM(nombre_votes) as tot FROM resultats WHERE id_election=:e');
            $stmt->execute([':e' => (int)$id]);
            $row = $stmt->fetch();
            return ($row && $row['tot']) ? (int)$row['tot'] : 0;
        };
        return $getVotes($b['data']['id_election']) <=> $getVotes($a['data']['id_election']);
    });
}

// ========== AFFICHAGE RÉSULTATS ==========
if (empty($filtered)) {
    echo '<div class="card"><div class="bd" style="padding:16px;text-align:center;color:rgba(255,255,255,.6)">Aucune élection trouvée</div></div>';
    votigo_layout_end();
    exit;
}

$colors = ['#2563eb', '#06b6d4', '#d946ef', '#94a3b8', '#f59e0b', '#10b981', '#ef4444'];

foreach ($filtered as $item) {
    $election = $item['data'];
    $finished = $item['finished'];
    $idElection = (int)$election['id_election'];
    
    echo '<section class="card" style="margin-bottom:20px;transition:all .3s ease" data-election-id="' . $idElection . '"><div class="bd" style="padding:20px">';
    
    // En-tête
    echo '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px">';
    echo '<div>';
    echo '<h2 style="margin:0;margin-bottom:6px">' . htmlspecialchars($election['titre']) . '</h2>';
    echo '<div class="muted" style="font-size:13px">' . format_date_fr($election['date_fin']) . '</div>';
    echo '</div>';
    echo '<span style="padding:6px 12px;background:' . ($finished ? '#10b98144' : '#f5a90044') . ';color:' . ($finished ? '#10b981' : '#f59e0b') . ';border-radius:4px;font-size:12px;font-weight:600">';
    echo $finished ? '✓ Terminée' : '◐ En cours';
    echo '</span>';
    echo '</div>';
    
    if (!$finished) {
        echo '<div style="padding:12px;background:rgba(251,146,60,.08);border-radius:6px;color:rgba(255,255,255,.7);font-size:13px;border-left:3px solid #f59e0b">⏱️ Élection non terminée</div>';
        echo '</div></section>';
        continue;
    }
    
    // Calculer résultats
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
        error_log('Calcul: ' . $ex->getMessage());
    }
    
    // Récupérer candidats
    $stmt = $pdo->prepare('SELECT c.nom_candidat, COALESCE(r.nombre_votes, 0) as votes, COALESCE(r.pourcentage, 0) as pct 
        FROM candidats c LEFT JOIN resultats r ON c.id_candidat = r.id_candidat 
        WHERE c.id_election = :e 
        ORDER BY COALESCE(r.nombre_votes, 0) DESC');
    $stmt->execute([':e' => $idElection]);
    $cands = $stmt->fetchAll();
    
    if (empty($cands)) {
        echo '<div style="padding:12px;background:rgba(100,100,100,.08);border-radius:6px;color:rgba(255,255,255,.6);font-size:13px">Aucun candidat</div>';
        echo '</div></section>';
        continue;
    }
    
    if ($view === 'compact') {
        // VUE COMPACT
        echo '<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">';
        
        $colorIdx = 0;
        foreach ($cands as $c) {
            $color = $colors[$colorIdx % count($colors)];
            $colorIdx++;
            echo '<div style="flex:1;min-width:150px;padding:12px;background:rgba(255,255,255,.02);border-radius:6px;border-left:3px solid ' . $color . ';text-align:center">';
            echo '<div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:4px">' . htmlspecialchars($c['nom_candidat']) . '</div>';
            echo '<div style="font-size:20px;font-weight:800;color:' . $color . '">' . $c['pct'] . '%</div>';
            echo '<div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:4px">' . (int)$c['votes'] . ' votes</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '<div style="margin-top:12px;padding:8px;background:rgba(26,169,184,.08);border-radius:4px;text-align:center;font-size:12px;color:rgba(255,255,255,.6)">';
        echo 'Total: ' . $total . ' votes';
        echo '</div>';
        
    } else {
        // VUE DÉTAILLÉE
        echo '<div style="display:grid;grid-template-columns:280px 1fr;gap:32px">';
        
        // Donut
        echo '<div style="display:flex;justify-content:center;align-items:center">';
        
        $gradientStops = [];
        $colorIdx = 0;
        $currentStop = 0;
        
        foreach ($cands as $c) {
            $pct = (float)$c['pct'];
            $color = $colors[$colorIdx % count($colors)];
            $colorIdx++;
            
            $nextStop = $currentStop + $pct;
            $gradientStops[] = "$color {$currentStop}% {$nextStop}%";
            $currentStop = $nextStop;
        }
        
        $gradientString = implode(', ', $gradientStops);
        
        echo '<div style="width:260px;height:260px;border-radius:50%;background:conic-gradient(' . $gradientString . ');position:relative;box-shadow:0 4px 12px rgba(0,0,0,.2);transition:transform .3s ease;cursor:pointer" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">';
        echo '<div style="position:absolute;top:50%;left:50%;width:160px;height:160px;background:#0f172a;border-radius:50%;transform:translate(-50%,-50%);box-shadow:inset 0 2px 6px rgba(0,0,0,.4)"></div>';
        echo '</div>';
        
        echo '</div>';
        
        // Résultats
        echo '<div>';
        $colorIdx = 0;
        foreach ($cands as $c) {
            $color = $colors[$colorIdx % count($colors)];
            $colorIdx++;
            
            echo '<div style="display:flex;align-items:center;margin-bottom:14px;padding:12px;background:rgba(255,255,255,.02);border-radius:6px;border-left:3px solid ' . $color . ';transition:all .2s ease" onmouseover="this.style.background=\'rgba(255,255,255,.06)\'" onmouseout="this.style.background=\'rgba(255,255,255,.02)\'">';
            echo '<div style="width:16px;height:16px;border-radius:3px;background:' . $color . ';flex-shrink:0;margin-right:12px"></div>';
            echo '<div style="flex:1">';
            echo '<div style="font-weight:600;font-size:14px">' . htmlspecialchars($c['nom_candidat']) . '</div>';
            echo '<div style="font-size:11px;color:rgba(255,255,255,.5)">' . (int)$c['votes'] . ' votes</div>';
            echo '</div>';
            echo '<div style="text-align:right">';
            echo '<div style="font-weight:800;font-size:18px;color:' . $color . '">' . $c['pct'] . '%</div>';
            echo '<div style="font-size:11px;color:rgba(255,255,255,.5);margin-top:2px" style="width:150px;height:4px;background:rgba(255,255,255,.1);border-radius:2px;margin-top:4px;overflow:hidden"><div style="background:' . $color . ';height:100%;width:' . $c['pct'] . '%;border-radius:2px"></div></div>';
            echo '</div>';
            echo '</div>';
        }
        
        echo '<div style="margin-top:16px;padding:12px;background:rgba(26,169,184,.08);border-radius:6px;text-align:center;font-size:12px;color:rgba(255,255,255,.7);border-left:3px solid #1aa9b8">';
        echo '<strong>Total votes:</strong> ' . $total;
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    echo '</div></section>';
}

// JavaScript pour interactivité
echo '<script>
// Données pour filtrage côté client
const electionsData = ' . json_encode(array_map(function($item) {
    return [
        'id' => (int)$item['data']['id_election'],
        'titre' => $item['data']['titre'],
        'finished' => $item['finished'],
        'date' => $item['data']['date_fin']
    ];
}, $filtered)) . ';

function filterElections() {
    const search = document.getElementById("searchInput").value.toLowerCase();
    const status = document.getElementById("statusSelect").value;
    const view = document.getElementById("viewSelect").value;
    
    electionsData.forEach(election => {
        const el = document.querySelector("[data-election-id=\"" + election.id + "\"]");
        if (!el) return;
        
        // Filtre recherche
        const matchSearch = election.titre.toLowerCase().includes(search);
        
        // Filtre statut
        const matchStatus = status === "all" || 
            (status === "finished" && election.finished) || 
            (status === "active" && !election.finished);
        
        el.style.display = (matchSearch && matchStatus) ? "block" : "none";
        if (matchSearch && matchStatus) {
            el.style.animation = "fadeIn 0.3s ease";
        }
    });
}

// Event listeners sans rechargement
document.getElementById("searchInput").addEventListener("input", filterElections);
document.getElementById("statusSelect").addEventListener("change", filterElections);

// Tri et affichage - rechargement de page
document.getElementById("sortSelect").addEventListener("change", function() {
    updateFilters();
});
document.getElementById("viewSelect").addEventListener("change", function() {
    updateFilters();
});

function updateFilters() {
    const search = document.getElementById("searchInput").value;
    const status = document.getElementById("statusSelect").value;
    const sort = document.getElementById("sortSelect").value;
    const view = document.getElementById("viewSelect").value;
    
    const params = new URLSearchParams();
    if (search) params.append("search", search);
    if (status !== "all") params.append("status", status);
    if (sort !== "date") params.append("sort", sort);
    if (view !== "full") params.append("view", view);
    
    window.location.href = "results.php?" + params.toString();
}

// Animation
const style = document.createElement("style");
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);

// Refocus sur linput après rechargement
window.addEventListener("load", function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has("sort") || urlParams.has("view")) {
        // Page a été rechargée par tri/affichage
        document.getElementById("searchInput").focus();
    }
});
</script>';

votigo_layout_end();
?>
