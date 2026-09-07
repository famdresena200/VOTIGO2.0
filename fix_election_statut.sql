-- Aligne les statuts d'élection avec le code PHP (sans accents).
-- À exécuter sur une base existante.

USE BDVOTIGO;

ALTER TABLE ELECTIONS
    MODIFY COLUMN statut VARCHAR(20) NOT NULL DEFAULT 'programme';

UPDATE ELECTIONS SET statut = 'programme' WHERE statut IN ('programmé', 'programme');
UPDATE ELECTIONS SET statut = 'ferme' WHERE statut IN ('fermé', 'ferme');
UPDATE ELECTIONS SET statut = 'actif' WHERE statut = 'actif';

ALTER TABLE ELECTIONS
    MODIFY COLUMN statut ENUM('programme','actif','ferme') NOT NULL DEFAULT 'programme';
