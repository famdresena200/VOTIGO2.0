<?php
// Test simple pour vérifier si la page results.php fonctionne
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/files/inscription/_inc/bootstrap.php';
require __DIR__ . '/files/inscription/_inc/db.php';

$pdo = db();

// Récupérer les élections terminées
$elections = $pdo->query("SELECT id_election, titre, date_fin FROM ELECTIONS WHERE date_fin < NOW() ORDER BY date_fin DESC LIMIT 5")->fetchAll();

echo '<h1>Élections terminées :</h1>';

if (empty($elections)) {
    echo '<p>Aucune élection terminée trouvée.</p>';
} else {
    foreach ($elections as $e) {
        $id = (int)$e['id_election'];
        echo '<p><a href="files/app/results.php?id_election=' . $id . '">Élection: ' . htmlspecialchars($e['titre'], ENT_QUOTES, 'UTF-8') . ' (ID: ' . $id . ')</a></p>';
        
        // Tester la requête candidats
        try {
            $stmt = $pdo->prepare('SELECT c.id_candidat, c.nom_candidat, c.image_mime, r.nombre_votes, r.pourcentage
                FROM CANDIDATS c
                LEFT JOIN RESULTATS r ON c.id_candidat = r.id_candidat AND r.id_election = :e
                WHERE c.id_election = :e
                ORDER BY COALESCE(r.nombre_votes, 0) DESC, c.ordre ASC, c.id_candidat ASC');
            $stmt->execute([':e' => $id]);
            $candidates = $stmt->fetchAll();
            echo '<p style="color:green">✓ Candidats trouvés: ' . count($candidates) . '</p>';
        } catch (Exception $ex) {
            echo '<p style="color:red">✗ Erreur: ' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
}

echo '<hr><p><a href="/">Retour accueil</a></p>';
