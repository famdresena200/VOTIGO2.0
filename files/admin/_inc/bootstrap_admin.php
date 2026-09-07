<?php
declare(strict_types=1);

// Définir le fuseau horaire pour correspondre au système (UTC+3 - Afrique de l'Est)
date_default_timezone_set('Africa/Nairobi');

// Session admin séparée (évite de partager la session électeur).
// Utiliser la racine du site est le plus fiable sur localhost/XAMPP.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('VOTIGO_ADMINSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Erreurs (dev/prod comme bootstrap principal)
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '1') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : 0);

header('X-Content-Type-Options: nosniff');

