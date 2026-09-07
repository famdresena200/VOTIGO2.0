-- Script de correction de la table VOTES
-- Supprime la clé étrangère obsolète vers USERS et le keep seulement token_anonyme

-- Commencer une transaction
SET FOREIGN_KEY_CHECKS = 0;

-- Afficher la structure actuelle
SHOW CREATE TABLE `BDVOTIGO`.`VOTES`;

-- Supprimer les contraintes existantes
ALTER TABLE VOTES DROP FOREIGN KEY votes_ibfk_1;
ALTER TABLE VOTES DROP COLUMN id_user;

-- S'assurer que le token_anonyme est unique par élection (au cas où)
ALTER TABLE VOTES DROP INDEX IF EXISTS ux_token_election;
ALTER TABLE VOTES ADD UNIQUE KEY ux_token_election (token_anonyme, id_election);

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- Vérifier la nouvelle structure
SHOW CREATE TABLE `BDVOTIGO`.`VOTES`;
