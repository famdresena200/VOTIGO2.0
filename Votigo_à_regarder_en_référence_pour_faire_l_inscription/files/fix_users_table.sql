-- Script de correction de la table USERS
-- Exécutez ce script si vous avez une erreur "Duplicate entry for key 'cin'"

USE BDVOTIGO;

-- Vérifiez d'abord la structure actuelle
DESCRIBE USERS;

-- Si les champs nen et cin sont INT, exécutez ceci:

-- Option 1: Supprimer la table et la recréer (perd toutes les données)
DROP TABLE IF EXISTS USERS;

CREATE TABLE USERS (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    prenom VARCHAR(100) NULL,
    nen VARCHAR(50) NULL UNIQUE,
    cin VARCHAR(50) NULL UNIQUE,
    telephone VARCHAR(30) NULL,
    date_naissance DATE NULL,
    password_hash VARCHAR(255) NULL,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Option 2: Si vous voulez garder les données, modifier les colonnes existantes
-- (Décommentez seulement si vous utilisez l'option 2)
/*
ALTER TABLE USERS MODIFY nen VARCHAR(50) NULL UNIQUE;
ALTER TABLE USERS MODIFY cin VARCHAR(50) NULL UNIQUE;
*/
