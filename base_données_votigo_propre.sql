-- ============================================================================
-- VOTIGO - Système de Vote Électronique Sécurisé
-- Base de Données: BDVOTIGO
-- Version: 1.0
-- Description: Schéma complet et propre de la base de données VOTIGO
-- Date: 2026-03-22
-- ============================================================================

-- Créer la base de données
CREATE DATABASE IF NOT EXISTS BDVOTIGO
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

-- Utiliser la base de données
USE BDVOTIGO;

-- ============================================================================
-- ÉTAPE 1: SUPPRIMER LES TABLES EXISTANTES (si elles existent)
-- ============================================================================

DROP TABLE IF EXISTS RESULTATS;
DROP TABLE IF EXISTS VOTES;
DROP TABLE IF EXISTS CANDIDATS;
DROP TABLE IF EXISTS ELECTIONS;
DROP TABLE IF EXISTS USERS;

-- ============================================================================
-- TABLE: USERS
-- Description: Enregistrement des utilisateurs (électeurs et administrateurs)
-- ============================================================================

CREATE TABLE USERS (
    id_user INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identifiant unique de l\'utilisateur',
    nom VARCHAR(100) NOT NULL COMMENT 'Nom de famille de l\'utilisateur',
    prenom VARCHAR(100) NULL COMMENT 'Prénom de l\'utilisateur',
    email VARCHAR(150) NOT NULL UNIQUE COMMENT 'Email unique pour l\'authentification',
    cin VARCHAR(50) NULL COMMENT 'Carte d\'identité nationale',
    nen VARCHAR(50) NULL COMMENT 'Numéro d\'enregistrement national',
    telephone VARCHAR(30) NULL COMMENT 'Numéro de téléphone',
    date_naissance DATE NULL COMMENT 'Date de naissance',
    password_hash VARCHAR(255) NULL COMMENT 'Hash du mot de passe (PASSWORD_DEFAULT)',
    date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp d\'inscription'
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE utf8mb4_unicode_ci 
COMMENT='Table des utilisateurs du système VOTIGO';

-- Indexes pour la table USERS
CREATE UNIQUE INDEX ux_users_email ON USERS(email);
CREATE INDEX idx_users_cin ON USERS(cin);
CREATE INDEX idx_users_nen ON USERS(nen);

-- ============================================================================
-- TABLE: ELECTIONS
-- Description: Enregistrement des élections
-- ============================================================================

CREATE TABLE ELECTIONS (
    id_election INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identifiant unique de l election',
    titre VARCHAR(200) NOT NULL COMMENT 'Titre de l election',
    description TEXT COMMENT 'Description detaillee de l election',
    date_debut DATETIME NOT NULL COMMENT 'Date et heure de debut du scrutin',
    date_fin DATETIME NOT NULL COMMENT 'Date et heure de fin du scrutin',
    statut ENUM('programme','actif','ferme') DEFAULT 'programme' COMMENT 'Statut de l election',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de creation'
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE utf8mb4_unicode_ci 
COMMENT='Table des elections';

-- Indexes pour la table ELECTIONS
CREATE INDEX idx_elections_dates ON ELECTIONS(date_debut, date_fin);
CREATE INDEX idx_elections_statut ON ELECTIONS(statut);

-- ============================================================================
-- TABLE: CANDIDATS
-- Description: Enregistrement des candidats par élection
-- ============================================================================

CREATE TABLE CANDIDATS (
    id_candidat INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identifiant unique du candidat',
    id_election INT NOT NULL COMMENT 'Référence à l\'élection',
    nom_candidat VARCHAR(200) NOT NULL COMMENT 'Nom complet du candidat',
    bio TEXT COMMENT 'Biographie du candidat',
    numero INT NULL COMMENT 'Numéro du candidat (unique par élection)',
    ordre INT NOT NULL DEFAULT 0 COMMENT 'Ordre d\'affichage du candidat',
    image_filename VARCHAR(255) COMMENT 'Nom du fichier image',
    image_mime VARCHAR(100) COMMENT 'Type MIME de l\'image (image/jpeg, image/png)',
    image_size INT COMMENT 'Taille de l\'image en bytes (max 10MB)',
    image_data LONGBLOB COMMENT 'Données binaires de l\'image',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp de création',
    
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE utf8mb4_unicode_ci 
COMMENT='Table des candidats par élection';

-- Indexes pour la table CANDIDATS
CREATE UNIQUE INDEX ux_candidats_numero ON CANDIDATS(id_election, numero);
CREATE INDEX idx_candidats_election ON CANDIDATS(id_election);
CREATE INDEX idx_candidats_ordre ON CANDIDATS(ordre);

-- ============================================================================
-- TABLE: VOTES
-- Description: Enregistrement des votes avec tokens anonymes
-- Contrainte: Un seul vote par électeur par élection (via token_anonyme)
-- ============================================================================

CREATE TABLE VOTES (
    id_vote INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identifiant unique du vote',
    id_candidat INT NOT NULL COMMENT 'Référence au candidat voté',
    id_election INT NOT NULL COMMENT 'Référence à l\'élection',
    date_vote TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp du vote',
    token_anonyme CHAR(64) NOT NULL COMMENT 'Token HMAC-SHA256 anonyme (univoque par élection)',
    
    FOREIGN KEY (id_candidat) REFERENCES CANDIDATS(id_candidat) ON DELETE CASCADE,
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE,
    UNIQUE KEY ux_token_election (token_anonyme, id_election)
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE utf8mb4_unicode_ci 
COMMENT='Table des votes avec tokens anonymes';

-- Indexes pour la table VOTES
CREATE INDEX idx_votes_election ON VOTES(id_election);
CREATE INDEX idx_votes_candidat ON VOTES(id_candidat);
CREATE INDEX idx_votes_date ON VOTES(date_vote);

-- ============================================================================
-- TABLE: RESULTATS
-- Description: Résultats calculés des élections
-- ============================================================================

CREATE TABLE RESULTATS (
    id_resultat INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Identifiant unique du résultat',
    id_election INT NOT NULL COMMENT 'Référence à l\'élection',
    id_candidat INT NOT NULL COMMENT 'Référence au candidat',
    nombre_votes INT NOT NULL DEFAULT 0 COMMENT 'Nombre de votes reçus',
    pourcentage DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Pourcentage des votes (0-100)',
    date_calcul TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Timestamp du dernier calcul',
    
    FOREIGN KEY (id_election) REFERENCES ELECTIONS(id_election) ON DELETE CASCADE,
    FOREIGN KEY (id_candidat) REFERENCES CANDIDATS(id_candidat) ON DELETE CASCADE,
    UNIQUE KEY ux_resultat_election_candidat (id_election, id_candidat)
) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE utf8mb4_unicode_ci 
COMMENT='Table des résultats des élections';

-- Indexes pour la table RESULTATS
CREATE INDEX idx_resultats_election ON RESULTATS(id_election);
CREATE INDEX idx_resultats_candidat ON RESULTATS(id_candidat);

-- ============================================================================
-- CONTRAINTES ET VALIDATIONS GLOBALES
-- ============================================================================

-- Vérifier que les colonnes d'image respectent les limites de taille
-- (Validation côté application recommandée - max 10MB = 10485760 bytes)

-- ============================================================================
-- COMMENTAIRES SUR LA STRUCTURE
-- ============================================================================

/*
ARCHITECTURE DE SÉCURITÉ:

1. AUTHENTIFICATION:
   - Passwords stockés avec PASSWORD_DEFAULT (bcrypt)
   - 2FA avec codes 6 chiffres (gestion hors-BD)
   - Sessions HTTP-only avec SameSite=Lax

2. VOTE ANONYME:
   - Tokens HMAC-SHA256 stockés dans VOTES
   - Contrainte UNIQUE (token_anonyme, id_election)
   - Un vote par électeur par élection

3. INTÉGRITÉ DES DONNÉES:
   - Contraintes de clés étrangères avec CASCADE
   - Indexes sur dates, statuts, et références
   - Types de données optimisés (CHAR(64) pour SHA256 hex)

4. PRÉVENTION DES FAILLES:
   - Parameterized queries (PDO prepared statements)
   - NO SQL Injection possible
   - Validation côté application requise

CARACTÉRISTIQUES:

- Charset: utf8mb4 (support émojis et accents)
- Collation: utf8mb4_unicode_ci (tri insensible à la casse)
- Engine: InnoDB (transactions, contraintes FK)
- BLOBS: Images stockées directement (alternative: fichiers système)

SCALABILITÉ:

Pour grandes élections (>1M votes):
- Partitioning par date
- Archive des RESULTATS anciens
- Cache Redis pour votes/résultats en temps réel
- Réplication pour haute disponibilité
*/

-- ============================================================================
-- FIN DU SCHÉMA
-- ============================================================================

-- Configurer les délais d'attente des connexions
SET GLOBAL wait_timeout = 28800;
SET GLOBAL max_connections = 100;

-- Vérifier l'intégrité du schéma
SHOW TABLES;
DESCRIBE USERS;
DESCRIBE ELECTIONS;
DESCRIBE CANDIDATS;
DESCRIBE VOTES;
DESCRIBE RESULTATS;
