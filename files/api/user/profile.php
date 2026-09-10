<?php
declare(strict_types=1);

require_once __DIR__ . '/../_config.php';
require_once __DIR__ . '/../_response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('Methode non autorisee', 405);
}

try {
    $user = require_auth();
    $pdo = api_db();
    $stmt = $pdo->prepare(
        'SELECT id_user, nom, prenom, email, nen, cin, telephone, date_naissance, date_inscription
         FROM users WHERE id_user = ? LIMIT 1'
    );
    $stmt->execute([(int)$user['id_user']]);
    $profile = $stmt->fetch();
    if (!$profile) {
        api_error('Utilisateur introuvable.', 404);
    }
    api_success(['user' => $profile], 'Profil recupere.', 200);
} catch (PDOException $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
} catch (Throwable $e) {
    error_log($e->getMessage());
    api_error('Erreur serveur.', 500);
}
