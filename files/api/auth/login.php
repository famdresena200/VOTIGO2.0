<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_config.php';
require_once __DIR__ . '/../api/_response.php';

/**
 * POST /api/auth/login
 * 
 * Request:
 * {
 *   "nen": "0000000020",
 *   "password": "Mahery1#0"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Login successful",
 *   "data": {
 *     "token": "eyJ...",
 *     "user": {
 *       "id_user": 1,
 *       "nom": "User",
 *       "prenom": "Test",
 *       "email": "user@example.com"
 *     }
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

try {
    $data = get_json_input();
    
    // Validate input
    $required = ['nen' => 'NEN', 'password' => 'Password'];
    validate_required($data, $required);
    
    $nen = normalize_identifier((string)$data['nen']);
    $password = (string)$data['password'];
    
    // Validate NEN format
    if (!validate_nen($nen)) {
        api_error('Invalid NEN format', 422, ['nen' => 'NEN must be 10 digits']);
    }
    
    // Query database
    $pdo = api_db();
    $stmt = $pdo->prepare(
        'SELECT id_user, nom, prenom, email, password_hash FROM users WHERE nen = ? LIMIT 1'
    );
    $stmt->execute([$nen]);
    $user = $stmt->fetch();
    
    if (!$user) {
        api_error('User not found', 401, ['nen' => 'Invalid NEN or password']);
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        api_error('Invalid password', 401, ['password' => 'Invalid NEN or password']);
    }
    
    // Generate JWT token
    $token = JWT::encode([
        'id_user' => (int)$user['id_user'],
        'nen' => $nen,
        'nom' => $user['nom'],
        'prenom' => $user['prenom'],
        'email' => $user['email'],
    ]);
    
    // Return success response
    api_success([
        'token' => $token,
        'user' => [
            'id_user' => (int)$user['id_user'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
        ],
    ], 'Login successful', 200);
    
} catch (PDOException $e) {
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
