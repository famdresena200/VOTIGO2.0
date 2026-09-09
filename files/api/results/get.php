<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * GET /api/results/get?id_election=1
 * 
 * Get election results
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Results retrieved",
 *   "data": {
 *     "election": {
 *       "id_election": 1,
 *       "titre": "Election 2026"
 *     },
 *     "total_votes": 1250,
 *     "results": [
 *       {
 *         "id_candidat": 1,
 *         "nom_candidat": "Candidate Name",
 *         "nombre_votes": 450,
 *         "pourcentage": 36.0,
 *         "rang": 1
 *       }
 *     ]
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('Method not allowed', 405);
}

try {
    $id_election = (int)($_GET['id_election'] ?? 0);
    
    if ($id_election <= 0) {
        api_error('Invalid election ID', 422, ['id_election' => 'Election ID is required']);
    }
    
    $pdo = api_db();
    
    // Get election info
    $stmt = $pdo->prepare(
        'SELECT id_election, titre, description, statut, date_debut, date_fin 
         FROM elections 
         WHERE id_election = ? 
         LIMIT 1'
    );
    $stmt->execute([$id_election]);
    $election = $stmt->fetch();
    
    if (!$election) {
        api_error('Election not found', 404, ['id_election' => 'Election not found']);
    }
    
    // Get total votes
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as total FROM votes WHERE id_election = ?'
    );
    $stmt->execute([$id_election]);
    $totalVotes = (int)$stmt->fetch()['total'];
    
    // Get results ordered by votes
    $stmt = $pdo->prepare(
        'SELECT 
            r.id_candidat,
            c.nom_candidat,
            r.nombre_votes,
            r.pourcentage,
            ROW_NUMBER() OVER (ORDER BY r.nombre_votes DESC) as rang
         FROM resultats r
         JOIN candidats c ON r.id_candidat = c.id_candidat
         WHERE r.id_election = ?
         ORDER BY r.nombre_votes DESC'
    );
    $stmt->execute([$id_election]);
    $results = $stmt->fetchAll();
    
    api_success([
        'election' => [
            'id_election' => (int)$election['id_election'],
            'titre' => $election['titre'],
            'description' => $election['description'],
            'statut' => $election['statut'],
        ],
        'total_votes' => $totalVotes,
        'results' => array_map(function($r) {
            return [
                'id_candidat' => (int)$r['id_candidat'],
                'nom_candidat' => $r['nom_candidat'],
                'nombre_votes' => (int)$r['nombre_votes'],
                'pourcentage' => (float)$r['pourcentage'],
                'rang' => (int)$r['rang'],
            ];
        }, $results),
    ], 'Results retrieved', 200);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
