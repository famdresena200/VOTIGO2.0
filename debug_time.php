<?php
require 'files/inscription/_inc/bootstrap.php';
require 'files/inscription/_inc/db.php';

$pdo = db();

echo '<pre>';
echo "=== DIAGNOSTIC DATES/HEURES ===\n\n";

// Heure PHP
echo "PHP time:\n";
$now = new DateTime();
echo "  Now: " . $now->format('Y-m-d H:i:s') . "\n";
echo "  Timezone: " . $now->getTimezone()->getName() . "\n";
echo "  Timestamp: " . $now->getTimestamp() . "\n\n";

// Heure MySQL
echo "MySQL time:\n";
$stmt = $pdo->query("SELECT NOW() as now, UTC_TIMESTAMP() as utc, DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') as formatted");
$row = $stmt->fetch();
echo "  NOW(): " . $row['now'] . "\n";
echo "  UTC_TIMESTAMP(): " . $row['utc'] . "\n";
echo "  Formatted: " . $row['formatted'] . "\n\n";

// Élections
echo "Élections:\n";
$stmt = $pdo->query("SELECT id_election, titre, date_debut, date_fin, statut FROM elections LIMIT 5");
$elections = $stmt->fetchAll();

foreach ($elections as $e) {
    echo "  Election #" . $e['id_election'] . ": " . $e['titre'] . "\n";
    echo "    - date_debut: " . $e['date_debut'] . "\n";
    echo "    - date_fin: " . $e['date_fin'] . "\n";
    echo "    - statut: " . $e['statut'] . "\n";
    
    // Vérifier si terminée
    try {
        $dateFinObj = new DateTime($e['date_fin']);
        $finished = (int)$dateFinObj->getTimestamp() <= (int)$now->getTimestamp();
        echo "    - finished (PHP): " . ($finished ? "YES" : "NO") . "\n";
        echo "      date_fin timestamp: " . $dateFinObj->getTimestamp() . "\n";
        echo "      now timestamp: " . $now->getTimestamp() . "\n";
        echo "      diff: " . ($now->getTimestamp() - $dateFinObj->getTimestamp()) . " seconds\n";
    } catch (Exception $ex) {
        echo "    - Error: " . $ex->getMessage() . "\n";
    }
    echo "\n";
}

echo '</pre>';
?>
