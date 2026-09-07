<?php
    require_once 'files/inscription/_inc/db.php';
    try {
        $pdo = db();
        $sql= file_get_contents('bdvotigo.sql');
        $pdo->exec($sql);
        echo "Felicitations!";
    } catch (PDOException $e) {
        echo "Erreur de connexion à la base de données : " . $e->getMessage();
    }