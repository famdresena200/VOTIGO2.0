-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : lun. 07 sep. 2026 à 11:56
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `bdvotigo`
--

-- --------------------------------------------------------

--
-- Structure de la table `candidats`
--

CREATE TABLE `candidats` (
  `id_candidat` int(11) NOT NULL,
  `id_election` int(11) NOT NULL,
  `nom_candidat` varchar(200) NOT NULL,
  `bio` text DEFAULT NULL,
  `numero` int(11) DEFAULT NULL,
  `ordre` int(11) NOT NULL DEFAULT 0,
  `image_filename` varchar(255) DEFAULT NULL,
  `image_mime` varchar(100) DEFAULT NULL,
  `image_size` int(11) DEFAULT NULL,
  `image_data` longblob DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `electeurs_autorises`
--

CREATE TABLE `electeurs_autorises` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `cin` varchar(50) NOT NULL,
  `nen` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `electeurs_autorises`
--

INSERT INTO `electeurs_autorises` (`id`, `nom`, `prenom`, `cin`, `nen`) VALUES
(1, 'Rakoto', 'Aina', '100000000001', '0000000001'),
(2, 'Rasoa', 'Benoit', '100000000002', '0000000002'),
(3, 'Andrianina', 'Clara', '100000000003', '0000000003'),
(4, 'Razafindrakoto', 'Dany', '100000000004', '0000000004'),
(5, 'Manjaka', 'Elie', '100000000005', '0000000005'),
(6, 'Randriamampy', 'Fanja', '100000000006', '0000000006'),
(7, 'Nirina', 'Gaëlle', '100000000007', '0000000007'),
(8, 'Rabe', 'Hary', '100000000008', '0000000008'),
(9, 'Andriamihaja', 'Isma', '100000000009', '0000000009'),
(10, 'Ravelo', 'Joël', '100000000010', '0000000010'),
(11, 'Miarisoa', 'Koto', '100000000011', '0000000011'),
(12, 'Lalao', 'Lova', '100000000012', '0000000012'),
(13, 'Rangy', 'Mialy', '100000000013', '0000000013'),
(14, 'Solofon', 'Noro', '100000000014', '0000000014'),
(15, 'Tiana', 'Onja', '100000000015', '0000000015'),
(16, 'Fidèle', 'Pao', '100000000016', '0000000016'),
(17, 'Hanitra', 'Quentin', '100000000017', '0000000017'),
(18, 'Tojo', 'Rina', '100000000018', '0000000018'),
(19, 'Mamy', 'Soa', '100000000019', '0000000019'),
(20, 'Bertin', 'Tovo', '100000000020', '0000000020');

-- --------------------------------------------------------

--
-- Structure de la table `elections`
--

CREATE TABLE `elections` (
  `id_election` int(11) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `statut` enum('programmé','actif','fermé') DEFAULT 'programmé'
) ;

-- --------------------------------------------------------

--
-- Structure de la table `resultats`
--

CREATE TABLE `resultats` (
  `id_resultat` int(11) NOT NULL,
  `id_election` int(11) NOT NULL,
  `id_candidat` int(11) NOT NULL,
  `nombre_votes` int(11) NOT NULL DEFAULT 0,
  `pourcentage` decimal(5,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `nen` varchar(50) DEFAULT NULL,
  `cin` varchar(50) DEFAULT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `date_inscription` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id_user`, `nom`, `email`, `prenom`, `nen`, `cin`, `telephone`, `date_naissance`, `password_hash`, `date_inscription`) VALUES
(1, 'RAKOTO', 'RakotoAina@gmail.com', 'Aina', '0000000001', '100000000001', '0381714823', '2006-09-13', '$2y$10$9mt5uzjuiXEz52/1GWbvSOGZh8JjhDTNtIpOk6DdM9ir2l3M4PB0G', '2026-09-07 07:23:42');

-- --------------------------------------------------------

--
-- Structure de la table `votes`
--

CREATE TABLE `votes` (
  `id_vote` int(11) NOT NULL,
  `id_candidat` int(11) NOT NULL,
  `id_election` int(11) NOT NULL,
  `date_vote` timestamp NOT NULL DEFAULT current_timestamp(),
  `token_anonyme` char(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `candidats`
--
ALTER TABLE `candidats`
  ADD PRIMARY KEY (`id_candidat`),
  ADD UNIQUE KEY `ux_candidats_numero` (`id_election`,`numero`);

--
-- Index pour la table `electeurs_autorises`
--
ALTER TABLE `electeurs_autorises`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_electeurs_autorises_cin` (`cin`),
  ADD UNIQUE KEY `ux_electeurs_autorises_nen` (`nen`);

--
-- Index pour la table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id_election`),
  ADD KEY `idx_elections_dates` (`date_debut`,`date_fin`);

--
-- Index pour la table `resultats`
--
ALTER TABLE `resultats`
  ADD PRIMARY KEY (`id_resultat`),
  ADD UNIQUE KEY `ux_resultat_election_candidat` (`id_election`,`id_candidat`),
  ADD KEY `id_candidat` (`id_candidat`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `ux_users_nen` (`nen`),
  ADD UNIQUE KEY `ux_users_cin` (`cin`);

--
-- Index pour la table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id_vote`),
  ADD UNIQUE KEY `ux_token_election` (`token_anonyme`,`id_election`),
  ADD KEY `id_candidat` (`id_candidat`),
  ADD KEY `id_election` (`id_election`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `candidats`
--
ALTER TABLE `candidats`
  MODIFY `id_candidat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `electeurs_autorises`
--
ALTER TABLE `electeurs_autorises`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `elections`
--
ALTER TABLE `elections`
  MODIFY `id_election` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `resultats`
--
ALTER TABLE `resultats`
  MODIFY `id_resultat` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `votes`
--
ALTER TABLE `votes`
  MODIFY `id_vote` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `candidats`
--
ALTER TABLE `candidats`
  ADD CONSTRAINT `candidats_ibfk_1` FOREIGN KEY (`id_election`) REFERENCES `elections` (`id_election`) ON DELETE CASCADE;

--
-- Contraintes pour la table `resultats`
--
ALTER TABLE `resultats`
  ADD CONSTRAINT `resultats_ibfk_1` FOREIGN KEY (`id_election`) REFERENCES `elections` (`id_election`) ON DELETE CASCADE,
  ADD CONSTRAINT `resultats_ibfk_2` FOREIGN KEY (`id_candidat`) REFERENCES `candidats` (`id_candidat`) ON DELETE CASCADE;

--
-- Contraintes pour la table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`id_candidat`) REFERENCES `candidats` (`id_candidat`) ON DELETE CASCADE,
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`id_election`) REFERENCES `elections` (`id_election`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
