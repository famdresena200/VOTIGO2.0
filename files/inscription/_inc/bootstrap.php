<?php
declare(strict_types=1);

// Définir le fuseau horaire pour correspondre au système (UTC+3 - Afrique de l'Est)
date_default_timezone_set('Africa/Nairobi');

// Bootstrap minimal (sessions + erreurs)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// En dev, on affiche les erreurs. En prod, mettez APP_DEBUG=0.
$debug = ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? '1') === '1';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : 0);

header('X-Content-Type-Options: nosniff');
