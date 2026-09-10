<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Méthode non autorisée', 405);
}

try {
    $data = get_json_input();
    validate_required($data, [
        'reset_token' => 'Jeton',
        'password' => 'Mot de passe',
        'password_confirm' => 'Confirmation',
    ]);
    $token = JWT::decode((string)$data['reset_token']);
    $password = (string)$data['password'];
    $confirmation = (string)$data['password_confirm'];

    if (!$token || ($token['purpose'] ?? '') !== 'password_reset' || empty($token['verified'])) {
        api_error('Session de réinitialisation invalide ou expirée.', 401);
    }
    if ($password !== $confirmation) {
        api_error('Les mots de passe ne correspondent pas.', 422, ['password' => 'Les deux mots de passe doivent être identiques.']);
    }
    $passwordErrors = validate_password($password);
    if (!empty($passwordErrors)) {
        api_error('Mot de passe insuffisamment sécurisé.', 422, ['password' => implode(' ', $passwordErrors)]);
    }

    $pdo = api_db();
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), (int)$token['id_user']]);
    api_success([], 'Mot de passe réinitialisé avec succès.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}