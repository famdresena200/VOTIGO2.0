<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

/**
 * POST /api/inscription/etape2
 * 
 * Verify OTP code
 * 
 * Request:
 * {
 *   "session_token": "eyJ...",
 *   "otp_code": "123456"
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "OTP verified",
 *   "data": {
 *     "session_token": "eyJ..."
 *   }
 * }
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed', 405);
}

try {
    $data = get_json_input();
    
    // Validate input
    $required = ['session_token' => 'Session Token', 'otp_code' => 'OTP Code'];
    validate_required($data, $required);
    
    $session_token = (string)$data['session_token'];
    $otp_code = (string)$data['otp_code'];
    
    // Validate OTP format (6 digits)
    if (!preg_match('/^\d{6}$/', $otp_code)) {
        api_error('Invalid OTP format', 422, ['otp_code' => 'OTP must be 6 digits']);
    }
    
    // Decode session token
    $sessionData = JWT::decode($session_token);
    if (!$sessionData) {
        api_error('Invalid or expired session', 401);
    }
    
    // Verify OTP
    if ($sessionData['otp'] !== $otp_code) {
        // Increment attempts
        $attempts = ($sessionData['otp_attempts'] ?? 0) + 1;
        
        if ($attempts >= 3) {
            api_error('Too many OTP attempts', 429, ['otp_code' => 'Maximum attempts exceeded']);
        }
        
        $sessionData['otp_attempts'] = $attempts;
        $sessionToken = JWT::encode($sessionData, 3600);
        
        api_error('Invalid OTP code', 422, [
            'otp_code' => 'OTP code is incorrect',
            'attempts_left' => 3 - $attempts,
        ]);
    }
    
    // Mark etape2 as completed
    $sessionData['step'] = 'etape2_completed';
    $newSessionToken = JWT::encode($sessionData, 3600);
    
    api_success([
        'session_token' => $newSessionToken,
    ], 'OTP verified successfully', 200);
    
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Server error', 500, ['error' => $e->getMessage()]);
}
