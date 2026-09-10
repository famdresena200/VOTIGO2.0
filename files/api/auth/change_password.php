<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Methode non autorisee', 405);
}

try {
    $user = require_auth();
    $data = get_json_input();
    validate_required($data, ['current_password' => 'Mot de passe actuel', 'password' => 'Nouveau mot de passe', 'password_confirm' => 'Confirmation']);
    $pdo = api_db();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id_user = ? LIMIT 1');
    $stmt->execute([(int)$user['id_user']]);
    $stored = $stmt->fetchColumn();
    if (!$stored || !password_verify((string)$data['current_password'], (string)$stored)) {
        api_error('Mot de passe actuel incorrect.', 422, ['current_password' => 'Vérifiez votre mot de passe actuel.']);
    }
    if ((string)$data['password'] !== (string)$data['password_confirm']) {
        api_error('Les mots de passe ne correspondent pas.', 422, ['password' => 'Les deux mots de passe doivent être identiques.']);
    }
    $errors = validate_password((string)$data['password']);
    if ($errors) {
        api_error('Mot de passe insuffisamment sécurisé.', 422, ['password' => implode(' ', $errors)]);
    }
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_user = ?');
    $stmt->execute([password_hash((string)$data['password'], PASSWORD_DEFAULT), (int)$user['id_user']]);
    api_success([], 'Mot de passe modifié avec succès.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}
