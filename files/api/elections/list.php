<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * GET /api/elections/list
 * 
 * Get list of elections
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Elections retrieved",
 *   "data": {
 *     "elections": [
 *       {
 *         "id_election": 1,
 *         "titre": "Election 2026",
 *         "description": "...",
 *         "statut": "actif",
 *         "date_debut": "2026-03-01T00:00:00Z",
 *         "date_fin": "2026-03-31T23:59:59Z"
 *       }
 *     ]
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('Method not allowed', 405);
}

try {
    $pdo = api_db();
    
    $stmt = $pdo->query(
        'SELECT id_election, titre, description, statut, date_debut, date_fin 
         FROM elections 
         ORDER BY date_fin DESC 
         LIMIT 50'
    );
    
    $elections = $stmt->fetchAll();
    
    // Format dates to ISO 8601
    $elections = array_map(function($e) {
        return [
            'id_election' => (int)$e['id_election'],
            'titre' => $e['titre'],
            'description' => $e['description'],
            'statut' => $e['statut'],
            'date_debut' => $e['date_debut'],
            'date_fin' => $e['date_fin'],
        ];
    }, $elections);
    
    api_success([
        'elections' => $elections,
    ], 'Elections retrieved', 200);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
