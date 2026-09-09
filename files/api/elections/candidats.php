<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';
require_once __DIR__ . '/../api/_response.php';

/**
 * GET /api/elections/candidats?id_election=1
 * 
 * Get candidates for an election
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Candidates retrieved",
 *   "data": {
 *     "election": {
 *       "id_election": 1,
 *       "titre": "Election 2026"
 *     },
 *     "candidats": [
 *       {
 *         "id_candidat": 1,
 *         "nom_candidat": "Candidate Name",
 *         "bio": "...",
 *         "numero": 1,
 *         "image_filename": "candidate.jpg"
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
    
    // Get candidates
    $stmt = $pdo->prepare(
        'SELECT id_candidat, nom_candidat, bio, numero, ordre, image_filename 
         FROM candidats 
         WHERE id_election = ? 
         ORDER BY ordre ASC'
    );
    $stmt->execute([$id_election]);
    $candidats = $stmt->fetchAll();
    
    api_success([
        'election' => [
            'id_election' => (int)$election['id_election'],
            'titre' => $election['titre'],
            'description' => $election['description'],
            'statut' => $election['statut'],
            'date_debut' => $election['date_debut'],
            'date_fin' => $election['date_fin'],
        ],
        'candidats' => array_map(function($c) {
            return [
                'id_candidat' => (int)$c['id_candidat'],
                'nom_candidat' => $c['nom_candidat'],
                'bio' => $c['bio'],
                'numero' => $c['numero'],
                'image_filename' => $c['image_filename'],
            ];
        }, $candidats),
    ], 'Candidates retrieved', 200);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
