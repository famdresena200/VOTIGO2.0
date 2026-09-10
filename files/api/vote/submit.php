<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * POST /api/vote/submit
 * 
 * Submit a vote (requires authentication)
 * 
 * Request:
 * {
 *   "id_election": 1,
 *   "id_candidat": 5
 * }
 * 
 * Headers:
 * Authorization: Bearer <jwt_token>
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Vote submitted successfully",
 *   "data": {
 *     "id_vote": 123,
 *     "token_anonyme": "abc..."
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

try {
    // Require authentication
    $user = require_auth();
    
    $data = get_json_input();
    
    // Validate input
    $required = ['id_election' => 'Election', 'id_candidat' => 'Candidate'];
    validate_required($data, $required);
    
    $id_election = (int)$data['id_election'];
    $id_candidat = (int)$data['id_candidat'];
    $id_user = (int)$user['id_user'];
    
    if ($id_election <= 0 || $id_candidat <= 0) {
        api_error('Invalid election or candidate', 422);
    }
    
    $pdo = api_db();
    
    // Verify election exists and is active
    $stmt = $pdo->prepare(
        'SELECT id_election, statut, date_fin FROM elections WHERE id_election = ? LIMIT 1'
    );
    $stmt->execute([$id_election]);
    $election = $stmt->fetch();
    
    if (!$election) {
        api_error('Election not found', 404);
    }

    if ((string)$election['statut'] !== 'actif') {
        api_error('Election is not active', 403, ['election' => 'Cette élection n’est pas ouverte aux votes.']);
    }
    
    // Check if election is still open
    $now = new DateTime();
    $dateFin = new DateTime($election['date_fin']);
    
    if ($dateFin <= $now) {
        api_error('Election is closed', 403, ['election' => 'This election has ended']);
    }
    
    // Verify candidate belongs to this election
    $stmt = $pdo->prepare(
        'SELECT id_candidat FROM candidats WHERE id_candidat = ? AND id_election = ? LIMIT 1'
    );
    $stmt->execute([$id_candidat, $id_election]);
    if (!$stmt->fetch()) {
        api_error('Candidate not found', 404);
    }
    
    // Generate anonymous token
    $token = anon_token($id_user, $id_election);
    
    // Check if user has already voted in this election
    $stmt = $pdo->prepare(
        'SELECT id_vote FROM votes WHERE token_anonyme = ? AND id_election = ? LIMIT 1'
    );
    $stmt->execute([$token, $id_election]);
    if ($stmt->fetch()) {
        api_error('Already voted in this election', 409, 
            ['vote' => 'You have already voted in this election']);
    }
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO votes (id_candidat, id_election, token_anonyme, date_vote)
             VALUES (:id_candidat, :id_election, :token_anonyme, NOW())'
        );
        $stmt->execute([
            ':id_candidat' => $id_candidat,
            ':id_election' => $id_election,
            ':token_anonyme' => $token,
        ]);
    } catch (Throwable $voteError) {
        $pdo->rollBack();
        throw $voteError;
    }
    
    $voteId = (int)$pdo->lastInsertId();
    
    // Update results
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as votes_count FROM votes 
         WHERE id_candidat = ? AND id_election = ?'
    );
    $stmt->execute([$id_candidat, $id_election]);
    $voteCount = (int)$stmt->fetch()['votes_count'];
    
    // Get total votes for this election
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) as total_votes FROM votes WHERE id_election = ?'
    );
    $stmt->execute([$id_election]);
    $totalVotes = (int)$stmt->fetch()['total_votes'];
    
    $percentage = $totalVotes > 0 ? round(($voteCount / $totalVotes) * 100, 2) : 0;
    
    // Upsert results
    $stmt = $pdo->prepare(
        'INSERT INTO resultats (id_election, id_candidat, nombre_votes, pourcentage)
         VALUES (:id_election, :id_candidat, :nombre_votes, :pourcentage)
         ON DUPLICATE KEY UPDATE 
         nombre_votes = :nombre_votes,
         pourcentage = :pourcentage'
    );
    
    $stmt->execute([
        ':id_election' => $id_election,
        ':id_candidat' => $id_candidat,
        ':nombre_votes' => $voteCount,
        ':pourcentage' => $percentage,
    ]);

    $pdo->commit();
    
    api_success([
        'id_vote' => $voteId,
        'token_anonyme' => substr($token, 0, 16) . '...', // Partial token for display
    ], 'Vote submitted successfully', 201);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
