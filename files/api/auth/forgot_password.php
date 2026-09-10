<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Méthode non autorisée', 405);
}

try {
    $data = get_json_input();
    validate_required($data, ['email' => 'Adresse email']);
    $email = trim((string)$data['email']);

    if (!validate_email($email)) {
        api_error('Adresse email invalide', 422, ['email' => 'Saisissez une adresse email valide.']);
    }

    $pdo = api_db();
    $stmt = $pdo->prepare('SELECT id_user, nom, prenom FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        api_error('Aucun compte ne correspond à cette adresse email.', 404, ['email' => 'Compte introuvable.']);
    }

    $code = (string)random_int(100000, 999999);
    $resetToken = JWT::encode([
        'purpose' => 'password_reset',
        'id_user' => (int)$user['id_user'],
        'email' => $email,
        'code' => $code,
    ], 600);

    api_success([
        'reset_token' => $resetToken,
        'reset_code' => $code,
        'nom' => trim((string)$user['nom'] . ' ' . (string)$user['prenom']),
    ], 'Code de réinitialisation généré.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}