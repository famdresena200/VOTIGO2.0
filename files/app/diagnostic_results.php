<?php
// Script de diagnostic pour l'erreur HTTP 500 sur results.php
declare(strict_types=1);

require __DIR__ . '/../inscription/_inc/bootstrap.php';
require __DIR__ . '/../inscription/_inc/db.php';
require __DIR__ . '/../_inc/ui.php';
require __DIR__ . '/../_inc/auth.php';

echo "=== DIAGNOSTIC RESULTS.PHP ===\n\n";

try {
    $pdo = db();
    echo "✓ Connexion DB OK\n";

    // Simuler une session d'utilisateur connecté pour le test
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['auth'] = [
        'id_user' => 1,
        'name' => 'Test User',
        'role' => 'electeur'
    ];

    require_electeur();
    echo "✓ Authentification OK\n";

    $idUser = auth_user_id();
    $userName = auth_user_name();
    echo "✓ Utilisateur: $userName (ID: $idUser)\n";

    // Tester avec une élection existante
    $stmt = $pdo->query('SELECT id_election, titre, statut FROM ELECTIONS LIMIT 5');
    $elections = $stmt->fetchAll();

    echo "\nÉlections disponibles:\n";
    foreach ($elections as $e) {
        echo "- ID: {$e['id_election']}, Titre: {$e['titre']}, Statut: {$e['statut']}\n";
    }

    if (!empty($elections)) {
        $testElectionId = (int)$elections[0]['id_election'];
        echo "\n=== Test avec élection ID: $testElectionId ===\n";

        // Simuler la logique de results.php
        $election = $pdo->prepare('SELECT * FROM ELECTIONS WHERE id_election = :id LIMIT 1');
        $election->execute([':id' => $testElectionId]);
        $election = $election->fetch();

        if (!$election) {
            echo "❌ Élection non trouvée\n";
        } else {
            echo "✓ Élection trouvée: {$election['titre']}\n";

            // Tester les candidats
            $candidats = $pdo->prepare('SELECT COUNT(*) as count FROM CANDIDATS WHERE id_election = :id');
            $candidats->execute([':id' => $testElectionId]);
            $count = $candidats->fetch()['count'];
            echo "✓ Candidats: $count\n";

            // Tester les résultats
            $results = $pdo->prepare('SELECT COUNT(*) as count FROM RESULTATS WHERE id_election = :id');
            $results->execute([':id' => $testElectionId]);
            $count = $results->fetch()['count'];
            echo "✓ Résultats en BD: $count\n";

            // Tester le calcul des résultats comme dans results.php
            $now = new DateTime();
            $endDate = new DateTime($election['date_fin']);
            $electionFinished = $now > $endDate;
            echo "✓ Élection terminée: " . ($electionFinished ? 'OUI' : 'NON') . "\n";

            if ($electionFinished) {
                echo "Test du calcul des résultats...\n";
                try {
                    // Test simple d'abord
                    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM CANDIDATS WHERE id_election = ?');
                    $stmt->execute([$testElectionId]);
                    $candidatCount = $stmt->fetch()['count'];
                    echo "✓ Nombre de candidats: $candidatCount\n";

                    // Test de la requête complexe
                    $stmt = $pdo->prepare('
                        SELECT c.id_candidat, c.nom_candidat, c.bio, c.numero, c.image_mime,
                               COALESCE(r.nombre_votes, 0) as votes,
                               COALESCE(r.pourcentage, 0) as percentage
                        FROM CANDIDATS c
                        LEFT JOIN RESULTATS r ON c.id_candidat = r.id_candidat AND r.id_election = ?
                        WHERE c.id_election = ?
                        ORDER BY COALESCE(r.nombre_votes, 0) DESC, c.ordre ASC, c.id_candidat ASC
                    ');
                    $stmt->execute([$testElectionId, $testElectionId]);
                    $testResults = $stmt->fetchAll();
                    echo "✓ Requête résultats OK, " . count($testResults) . " résultats trouvés\n";

                    foreach ($testResults as $result) {
                        echo "  - Candidat: {$result['nom_candidat']}, Votes: {$result['votes']}, Pourcentage: {$result['percentage']}%\n";
                    }
                } catch (Exception $e) {
                    echo "❌ Erreur dans le calcul des résultats: " . $e->getMessage() . "\n";
                    echo "Code erreur: " . $e->getCode() . "\n";
                }
            }
        }
    }

    echo "\n=== Test terminé avec succès ===\n";

} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
?>