<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // XAMPP par défaut : user=root, password vide.
    // Adaptez si besoin.
    $host = '127.0.0.1';
    $dbname = 'bdvotigo';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Définir le fuseau horaire MySQL pour correspondre à PHP (UTC+3)
    $pdo->exec("SET time_zone = '+03:00'");
    
    return $pdo;
}

