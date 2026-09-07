<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Modifier selon votre configuration XAMPP
define('DB_PASS', ''); // Mot de passe vide par défaut dans XAMPP
define('DB_NAME', 'BDVOTIGO');

// Connexion à la base de données
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Erreur de connexion à la base de données: " . $e->getMessage());
    }
}
?>