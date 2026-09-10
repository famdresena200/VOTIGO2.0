<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Méthode non autorisée', 405);
}

try {
    $data = get_json_input();
    validate_required($data, ['reset_token' => 'Jeton', 'code' => 'Code']);
    $token = JWT::decode((string)$data['reset_token']);
    $code = preg_replace('/\s+/', '', (string)$data['code']) ?? '';

    if (!$token || ($token['purpose'] ?? '') !== 'password_reset') {
        api_error('Session de réinitialisation invalide ou expirée.', 401);
    }
    if (!preg_match('/^\d{6}$/', $code)) {
        api_error('Code invalide.', 422, ['code' => 'Le code doit contenir exactement 6 chiffres.']);
    }
    if (!hash_equals((string)($token['code'] ?? ''), $code)) {
        api_error('Code incorrect.', 422, ['code' => 'Le code saisi est incorrect.']);
    }

    $token['verified'] = true;
    unset($token['code']);
    api_success(['reset_token' => JWT::encode($token, 600)], 'Code vérifié.', 200);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}