<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * POST /api/inscription/etape1
 * 
 * Validate user data and check if elector is authorized
 * 
 * Request:
 * {
 *   "nen": "0000000020",
 *   "cin": "123456789012",
 *   "nom": "User",
 *   "prenom": "Test",
 *   "email": "user@example.com",
 *   "date_naissance": "2000-01-15"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Validation successful",
 *   "data": {
 *     "session_id": "random_string"
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

try {
    $data = get_json_input();
    
    // Validate required fields
    $required = [
        'nen' => 'NEN',
        'cin' => 'CIN',
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'email' => 'Email',
        'date_naissance' => 'Date de naissance',
    ];
    validate_required($data, $required);
    
    $nen = normalize_identifier((string)$data['nen']);
    $cin = normalize_identifier((string)$data['cin']);
    $nom = (string)$data['nom'];
    $prenom = (string)$data['prenom'];
    $email = (string)$data['email'];
    $date_naissance = (string)$data['date_naissance'];
    
    // Validate formats
    $errors = [];
    
    if (!validate_nen($nen)) {
        $errors['nen'] = 'NEN must be 10 digits';
    }
    
    if (!validate_cin($cin)) {
        $errors['cin'] = 'CIN must be 12 digits';
    }
    
    if (!validate_email($email)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (!is_at_least_18_years_old($date_naissance)) {
        $errors['date_naissance'] = 'Must be at least 18 years old';
    }
    
    if (!empty($errors)) {
        api_error('Validation failed', 422, $errors);
    }
    
    $pdo = api_db();
    
    // Check if email already exists in users
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        api_error('Email already registered', 422, ['email' => 'This email is already used']);
    }
    
    // Check if NEN already exists in users
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE nen = ?');
    $stmt->execute([$nen]);
    if ($stmt->fetchColumn() > 0) {
        api_error('NEN already registered', 422, ['nen' => 'This NEN is already registered']);
    }
    
    // Check if user is authorized (electeurs_autorises table)
    $stmt = $pdo->prepare(
        'SELECT * FROM electeurs_autorises 
         WHERE cin = :cin 
           AND nen = :nen 
           AND LOWER(TRIM(nom)) = LOWER(TRIM(:nom))
           AND LOWER(TRIM(prenom)) = LOWER(TRIM(:prenom))
         LIMIT 1'
    );
    
    $stmt->execute([
        ':cin' => $cin,
        ':nen' => $nen,
        ':nom' => $nom,
        ':prenom' => $prenom,
    ]);
    
    if (!$stmt->fetch()) {
        api_error('User not authorized', 403, 
            ['authorization' => 'Your data does not match our electoral records']);
    }
    
    // Generate OTP code (6 digits)
    $otp = (string)rand(100000, 999999);
    
    // Create temporary session (store in cache or JWT)
    $sessionData = [
        'nen' => $nen,
        'cin' => $cin,
        'nom' => $nom,
        'prenom' => $prenom,
        'email' => $email,
        'date_naissance' => $date_naissance,
        'otp' => $otp,
        'otp_attempts' => 0,
        'step' => 'etape1_completed',
    ];
    
    // Return session token (we'll use JWT with special claims)
    $sessionToken = JWT::encode($sessionData, 3600); // 1 hour expiration
    
    api_success([
        'session_token' => $sessionToken,
        'otp_code' => $otp, // For development/testing (remove in production)
    ], 'Étape 1 validated successfully', 200);
    
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Database error', 500, ['db' => 'Connection failed']);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
