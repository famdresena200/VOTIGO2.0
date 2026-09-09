<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * POST /api/inscription/etape3
 * 
 * Create user account with password
 * 
 * Request:
 * {
 *   "session_token": "eyJ...",
 *   "password": "SecurePass123!",
 *   "password_confirm": "SecurePass123!"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Account created successfully",
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
    $required = [
        'session_token' => 'Session Token',
        'password' => 'Password',
        'password_confirm' => 'Password Confirmation',
    ];
    validate_required($data, $required);
    
    $session_token = (string)$data['session_token'];
    $password = (string)$data['password'];
    $password_confirm = (string)$data['password_confirm'];
    
    // Decode session token
    $sessionData = JWT::decode($session_token);
    if (!$sessionData || $sessionData['step'] !== 'etape2_completed') {
        api_error('Invalid session or incomplete previous steps', 401);
    }
    
    // Validate passwords match
    if ($password !== $password_confirm) {
        api_error('Passwords do not match', 422, ['password' => 'Passwords do not match']);
    }
    
    // Validate password strength
    $passwordErrors = validate_password($password);
    if (!empty($passwordErrors)) {
        api_error('Password does not meet security requirements', 422, 
            ['password' => implode(', ', $passwordErrors)]);
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Create user account
    $pdo = api_db();
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare(
            'INSERT INTO users (nom, prenom, email, cin, nen, date_naissance, password_hash, date_inscription)
             VALUES (:nom, :prenom, :email, :cin, :nen, :date_naissance, :password_hash, NOW())'
        );
        
        $stmt->execute([
            ':nom' => $sessionData['nom'],
            ':prenom' => $sessionData['prenom'],
            ':email' => $sessionData['email'],
            ':cin' => $sessionData['cin'],
            ':nen' => $sessionData['nen'],
            ':date_naissance' => $sessionData['date_naissance'],
            ':password_hash' => $passwordHash,
        ]);
        
        $userId = (int)$pdo->lastInsertId();
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        // Check if error is due to duplicate email or NEN
        if (strpos($e->getMessage(), 'email') !== false) {
            api_error('Email already registered', 409, ['email' => 'This email is already used']);
        } elseif (strpos($e->getMessage(), 'nen') !== false) {
            api_error('NEN already registered', 409, ['nen' => 'This NEN is already registered']);
        }
        
        throw $e;
    }
    
    // Generate JWT login token
    $token = JWT::encode([
        'id_user' => $userId,
        'nen' => $sessionData['nen'],
        'nom' => $sessionData['nom'],
        'prenom' => $sessionData['prenom'],
        'email' => $sessionData['email'],
    ]);
    
    // Return success response
    api_success([
        'token' => $token,
        'user' => [
            'id_user' => $userId,
            'nom' => $sessionData['nom'],
            'prenom' => $sessionData['prenom'],
            'email' => $sessionData['email'],
        ],
    ], 'Account created successfully', 201);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
