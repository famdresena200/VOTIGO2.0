t -- BASE DE DONNEES BDVOTIGO
-- Encodage : utf8mb4

CREATE DATABASE BDVOTIGO;
USE BDVOTIGO;

DROP TABLE IF EXISTS RESULTATS;
DROP TABLE IF EXISTS VOTES;
DROP TABLE IF EXISTS CANDIDATS;
DROP TABLE IF EXISTS ELECTIONS;
DROP TABLE IF EXISTS USERS;

CREATE TABLE USERS (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    prenom VARCHAR(100) NULL,
    nen VARCHAR(50) NULL,
    cin VARCHAR(50) NULL,
    telephone VARCHAR(30) NULL,
    date_naissance DATE NULL,
    password_hash VARCHAR(255) NULL,
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ELECTIONS (
    id_election INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(200) NOT NULL,
    description TEXT,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('programmé','actif','fermé') DEFAULT 'programmé',
    CHECK (date_debut < date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE CANDIDATS (
    id_candidat INT AUTO_INCREMENT PRIMARY KEY,
    id_election INT NOT NULL,
    nom_candidat VARCHAR(200) NOT NULL,
    bio TEXT,
    numero INT NULL,
    ordre INT NOT NULL DEFAULT 0,
    image_filename VARCHAR(255),
    image_mime VARCHAR(100),
    image_size INT,
    image_data LONGBLOB,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Numéro manuel du candidat (unique par élection)
CREATE UNIQUE INDEX ux_candidats_numero ON CANDIDATS(id_election, numero);

CREATE TABLE VOTES (
    id_vote INT AUTO_INCREMENT PRIMARY KEY,
    id_candidat INT NOT NULL,
    id_election INT NOT NULL,
    date_vote TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    token_anonyme CHAR(64) NOT NULL,
    FOREIGN KEY (id_candidat) REFERENCES CANDIDATS(id_candidat) ON DELETE CASCADE,
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE,
    UNIQUE KEY ux_token_election (token_anonyme, id_election)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE RESULTATS (
    id_resultat INT AUTO_INCREMENT PRIMARY KEY,
    id_election INT NOT NULL,
    id_candidat INT NOT NULL,
    nombre_votes INT NOT NULL DEFAULT 0,
    pourcentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE,
    FOREIGN KEY (id_candidat) REFERENCES CANDIDATS(id_candidat) ON DELETE CASCADE,
    UNIQUE KEY ux_resultat_election_candidat (id_election, id_candidat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -- Indexes recommandés
-- CREATE INDEX idx_elections_dates ON ELECTIONS(date_debut, date_fin);

-- -- Exemple d'insertion minimale (données de test)
-- INSERT INTO ELECTIONS (titre, description, date_debut, date_fin, statut)
-- VALUES ('Élection municipale 2026', 'Élection pour le conseil municipal', '2026-04-01 00:00:00', '2026-04-30 23:59:59', 'programmé');

-- INSERT INTO CANDIDATS (id_election, nom_candidat, bio)
-- VALUES (LAST_INSERT_ID(), 'Alice Dupont', 'Candidate indépendante'),
--        (LAST_INSERT_ID(), 'Bob Martin', 'Candidat Parti A');

ALTER TABLE USERS
       ADD COLUMN prenom VARCHAR(100) NULL AFTER nom,
       ADD COLUMN nen VARCHAR(50) NULL AFTER prenom,
       ADD COLUMN cin VARCHAR(50) NULL AFTER nen,
       ADD COLUMN telephone VARCHAR(30) NULL AFTER cin,
       ADD COLUMN date_naissance DATE NULL AFTER telephone;

