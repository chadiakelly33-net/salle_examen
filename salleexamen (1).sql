-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 22, 2026 at 02:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `salleexamen`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `affecter_examen` (IN `p_id_etu` INT, IN `p_id_cours` INT, IN `p_id_salle` INT, IN `p_id_surv` INT, IN `p_date_exam` DATE, IN `p_heure_debut` TIME, IN `p_heure_fin` TIME)   BEGIN


    /* Vérifier si l'étudiant a déjà un examen au même moment */

    IF EXISTS(

        SELECT 1

        FROM affectation

        WHERE id_etu = p_id_etu

        AND date_exam = p_date_exam

        AND (
            p_heure_debut < heure_fin
            AND p_heure_fin > heure_debut
        )

    )

    THEN

        SIGNAL SQLSTATE '45000'

        SET MESSAGE_TEXT =
        'Cet étudiant possède déjà un examen à cette heure';



    /* Vérifier si le surveillant est déjà dans une autre salle */

    ELSEIF EXISTS(

        SELECT 1

        FROM affectation

        WHERE id_surv = p_id_surv

        AND date_exam = p_date_exam

        AND id_salle <> p_id_salle

        AND (
            p_heure_debut < heure_fin
            AND p_heure_fin > heure_debut
        )

    )

    THEN

        SIGNAL SQLSTATE '45000'

        SET MESSAGE_TEXT =
        'Ce surveillant est déjà affecté dans une autre salle à cette heure';



    ELSE


        /* Insertion de l'affectation */

        INSERT INTO affectation
        (
            id_etu,
            id_cours,
            id_salle,
            id_surv,
            date_exam,
            heure_debut,
            heure_fin
        )

        VALUES
        (
            p_id_etu,
            p_id_cours,
            p_id_salle,
            p_id_surv,
            p_date_exam,
            p_heure_debut,
            p_heure_fin
        );


    END IF;


END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `afficher_affectations_surveillant` (IN `idSurv` INT)   BEGIN

    SELECT
        sv.id_surv,
        sv.nom_surv,
        sv.prenom_surv,
        c.nom_cours,
        s.nom_salle,
        a.date_exam,
        a.heure_debut,
        a.heure_fin

    FROM affectation a

    JOIN surveillant sv 
        ON a.id_surv = sv.id_surv

    JOIN cours c
        ON a.id_cours = c.id_cours

    JOIN salle s
        ON a.id_salle = s.id_salle

    WHERE sv.id_surv = idSurv

    ORDER BY 
        a.date_exam ASC,
        a.heure_debut ASC;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `afficher_etudiants` ()   BEGIN
    SELECT *
    FROM etudiant
    ORDER BY nom_etu ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `afficher_surveillant` (IN `p_nomsur` VARCHAR(50))   BEGIN
    SELECT *
    FROM surveillant
    ORDER BY nom_surv ASC;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `archiver_examens` ()   BEGIN

    -- Copier les examens terminés dans la table archive

    INSERT INTO affectation_archive
    (
        id_affectation,
        id_etu,
        id_cours,
        id_salle,
        id_surv,
        date_exam,
        heure_debut,
        heure_fin
    )

    SELECT
        id_affectation,
        id_etu,
        id_cours,
        id_salle,
        id_surv,
        date_exam,
        heure_debut,
        heure_fin

    FROM affectation

    WHERE date_exam < CURDATE();



    -- Supprimer les examens déjà archivés

    DELETE FROM affectation

    WHERE date_exam < CURDATE();


END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `consulter_etudiants_salle` (IN `p_id_salle` INT, IN `p_date_exam` DATE, IN `p_heure_debut` TIME)   BEGIN

    SELECT
        e.nom_etu,
        e.prenom_etu,
        f.nom_fac,
        n.nom_niveau,
        c.nom_cours
    FROM affectation a
    JOIN etudiant e ON a.id_etu = e.id_etu
    JOIN faculte f ON e.id_fac = f.id_fac
    JOIN niveau_etude n ON e.id_niveau = n.id_niveau
    JOIN cours c ON a.id_cours = c.id_cours
    WHERE a.id_salle = p_id_salle
      AND a.date_exam = p_date_exam
      AND a.heure_debut = p_heure_debut
    ORDER BY e.nom_etu, e.prenom_etu;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `modifier_affectation` (IN `p_id_affectation` INT, IN `p_id_salle` INT, IN `p_id_surv` INT, IN `p_date_exam` DATE, IN `p_heure_debut` TIME, IN `p_heure_fin` TIME)   BEGIN

    /* Vérifier que le surveillant n'est pas déjà dans une autre salle */

    IF EXISTS(

        SELECT 1
        FROM affectation
        WHERE id_surv = p_id_surv
        AND date_exam = p_date_exam
        AND heure_debut = p_heure_debut
        AND heure_fin = p_heure_fin
        AND id_salle <> p_id_salle
        AND id_affectation <> p_id_affectation

    ) THEN

        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT='Le surveillant est déjà affecté dans une autre salle.';

    ELSE

        UPDATE affectation

        SET

        id_salle = p_id_salle,
        id_surv = p_id_surv,
        date_exam = p_date_exam,
        heure_debut = p_heure_debut,
        heure_fin = p_heure_fin

        WHERE id_affectation = p_id_affectation;

    END IF;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `restaurer_affectation` (IN `p_id_archive` INT)   BEGIN

    INSERT INTO affectation
    (
        id_etu,
        id_cours,
        id_salle,
        id_surv,
        date_exam,
        heure_debut,
        heure_fin
    )

    SELECT
        id_etu,
        id_cours,
        id_salle,
        id_surv,
        date_exam,
        heure_debut,
        heure_fin
    FROM affectation_archive
    WHERE id_archive = p_id_archive;

    DELETE FROM affectation_archive
    WHERE id_archive = p_id_archive;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `verifier_disponibilite_surveillant` (IN `idSurv` INT, IN `dateExam` DATE, IN `debut` TIME, IN `fin` TIME)   BEGIN

    SELECT *
    FROM affectation

    WHERE id_surv = idSurv

    AND date_exam = dateExam

    AND (
        debut < heure_fin
        AND fin > heure_debut
    );

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `affectation`
--

CREATE TABLE `affectation` (
  `id_affectation` int(11) NOT NULL,
  `id_etu` int(11) DEFAULT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `id_salle` int(11) DEFAULT NULL,
  `id_surv` int(11) DEFAULT NULL,
  `date_exam` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `id_fac` int(11) DEFAULT NULL,
  `id_niveau` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `affectation`
--

INSERT INTO `affectation` (`id_affectation`, `id_etu`, `id_cours`, `id_salle`, `id_surv`, `date_exam`, `heure_debut`, `heure_fin`, `id_fac`, `id_niveau`) VALUES
(1, NULL, 1, 3, 3, '2026-06-23', '15:36:00', '04:37:00', NULL, NULL),
(2, NULL, 14, 7, 7, '2026-06-26', '10:10:00', '00:10:00', NULL, NULL),
(3, NULL, 14, 7, 7, '2026-06-26', '10:10:00', '00:10:00', NULL, NULL),
(4, NULL, 10, 11, 1, '2026-06-25', '08:30:00', '09:45:00', NULL, NULL),
(5, NULL, 6, 1, 19, '2026-06-25', '15:45:00', '17:52:00', NULL, NULL),
(6, 12, NULL, 1, NULL, '2026-06-29', '08:39:00', '10:45:00', NULL, NULL),
(7, 2, NULL, 1, NULL, '2026-06-29', '08:39:00', '10:45:00', NULL, NULL),
(8, 12, NULL, 1, NULL, '2026-06-29', '08:39:00', '10:45:00', NULL, NULL),
(9, 2, NULL, 1, NULL, '2026-06-29', '08:39:00', '10:45:00', NULL, NULL),
(10, 12, NULL, 1, NULL, '2026-06-30', '08:30:00', '10:45:00', NULL, NULL),
(11, 2, NULL, 1, NULL, '2026-06-30', '08:30:00', '10:45:00', NULL, NULL),
(12, 8, 9, 12, 10, '2026-06-30', '08:30:00', '10:35:00', NULL, NULL),
(13, 24, 9, 12, 10, '2026-06-30', '08:30:00', '10:35:00', NULL, NULL),
(14, 2, 9, 1, 1, '2026-06-30', '15:05:00', '15:18:00', NULL, NULL),
(15, 12, 9, 1, 1, '2026-06-30', '15:05:00', '15:18:00', NULL, NULL),
(16, 8, 15, 12, 19, '2026-06-30', '15:15:00', '15:20:00', NULL, NULL),
(17, 24, 15, 12, 19, '2026-06-30', '15:15:00', '15:20:00', NULL, NULL),
(18, 1, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(19, 5, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(20, 6, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(21, 8, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(22, 7, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(23, 2, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(24, 3, 1, 1, 1, '2026-06-30', '17:02:00', '18:00:00', NULL, 1),
(26, 1, 1, 1, 1, '2026-07-01', '10:30:00', '10:45:00', 1, 2),
(27, 20, 1, 1, 1, '2026-07-01', '10:30:00', '10:45:00', 1, 2),
(28, 28, 17, 10, 19, '2026-07-09', '18:15:00', '22:30:00', 2, 1),
(29, 27, 17, 10, 19, '2026-07-09', '18:15:00', '22:30:00', 2, 1),
(30, 32, 17, 10, 19, '2026-07-09', '18:15:00', '22:30:00', 2, 1),
(31, 30, 17, 10, 19, '2026-07-09', '18:15:00', '22:30:00', 2, 1),
(32, 28, 16, 11, 20, '2026-07-16', '08:00:00', '10:10:00', NULL, NULL),
(34, 8, 1, 8, 19, '2026-07-23', '16:55:00', '17:05:00', NULL, NULL),
(35, 1, 1, 1, 1, '2026-08-10', '08:00:00', '10:00:00', NULL, NULL),
(36, 2, 1, 1, 1, '2026-08-10', '08:00:00', '10:00:00', NULL, NULL),
(38, 5, 30, 2, 18, '2026-08-12', '14:45:00', '17:46:00', NULL, NULL),
(39, 3, 30, 2, 21, '2026-08-12', '14:45:00', '17:46:00', NULL, NULL),
(40, 5, 30, 2, 18, '2026-08-11', '16:08:00', '19:10:00', NULL, NULL),
(41, 3, 30, 2, 18, '2026-08-11', '16:08:00', '19:10:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `affectation_archive`
--

CREATE TABLE `affectation_archive` (
  `id_archive` int(11) NOT NULL,
  `id_affectation` int(11) DEFAULT NULL,
  `id_etu` int(11) DEFAULT NULL,
  `id_cours` int(11) DEFAULT NULL,
  `id_salle` int(11) DEFAULT NULL,
  `id_surv` int(11) DEFAULT NULL,
  `date_exam` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `date_archivage` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `backup`
--

CREATE TABLE `backup` (
  `idbackup` int(11) NOT NULL,
  `date_backup` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup`
--

INSERT INTO `backup` (`idbackup`, `date_backup`) VALUES
(1, '2026-06-10 11:01:17'),
(2, '2026-06-10 11:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `connexion_log`
--

CREATE TABLE `connexion_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nom_utilisateur` varchar(100) DEFAULT NULL,
  `adresse_ip` varchar(50) DEFAULT NULL,
  `type_user` varchar(20) DEFAULT NULL,
  `statut` varchar(20) DEFAULT NULL,
  `date_connexion` datetime DEFAULT NULL,
  `date_deconnexion` datetime DEFAULT NULL,
  `duree_connexion` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `connexion_log`
--

INSERT INTO `connexion_log` (`id`, `user_id`, `nom_utilisateur`, `adresse_ip`, `type_user`, `statut`, `date_connexion`, `date_deconnexion`, `duree_connexion`) VALUES
(1, 1, NULL, NULL, 'etudiant', 'failed', '2026-07-07 15:31:31', NULL, NULL),
(2, 29, NULL, NULL, 'etudiant', 'failed', '2026-07-07 15:32:33', NULL, NULL),
(3, 29, NULL, NULL, 'etudiant', 'failed', '2026-07-07 15:32:51', NULL, NULL),
(4, 0, NULL, NULL, 'etudiant', 'failed', '2026-07-07 17:06:24', NULL, NULL),
(5, 30, NULL, NULL, 'etudiant', 'failed', '2026-07-07 17:07:00', NULL, NULL),
(6, 30, NULL, NULL, 'etudiant', 'success', '2026-07-07 17:09:52', NULL, NULL),
(7, 31, NULL, NULL, 'etudiant', 'success', '2026-07-07 19:33:26', NULL, NULL),
(8, 30, NULL, NULL, 'etudiant', 'success', '2026-07-07 19:42:46', NULL, NULL),
(9, 30, NULL, NULL, 'etudiant', 'success', '2026-07-07 19:43:59', NULL, NULL),
(10, 31, NULL, NULL, 'etudiant', 'success', '2026-07-07 19:45:17', NULL, NULL),
(11, 32, NULL, NULL, 'etudiant', 'success', '2026-07-07 19:50:06', NULL, NULL),
(12, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:10:34', NULL, NULL),
(13, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:18:10', NULL, NULL),
(14, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:26:16', NULL, NULL),
(15, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:30:57', NULL, NULL),
(16, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:31:50', NULL, NULL),
(17, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:48:33', NULL, NULL),
(18, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 15:49:11', NULL, NULL),
(19, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 16:01:45', NULL, NULL),
(20, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 16:02:21', NULL, NULL),
(21, 30, NULL, NULL, 'etudiant', 'success', '2026-07-08 16:06:37', NULL, NULL),
(22, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-08 16:42:05', NULL, NULL),
(23, 25, 'Bukuru alfred', '::1', 'surveillant', 'success', '2026-07-08 17:36:53', NULL, NULL),
(24, 25, 'Bukuru alfred', '::1', 'surveillant', 'success', '2026-07-08 17:38:39', NULL, NULL),
(25, 25, 'Bukuru alfred', '::1', 'surveillant', 'success', '2026-07-08 17:53:50', NULL, NULL),
(26, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-09 17:13:51', NULL, NULL),
(27, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-09 17:20:50', NULL, NULL),
(28, NULL, 'Utilisateur inconnu', '::1', 'surveillant', 'failed', '2026-07-09 17:34:50', NULL, NULL),
(29, 31, 'KEZA CLARY', '::1', 'etudiant', 'verification', '2026-07-13 14:15:53', '2026-07-13 14:16:50', '00:00:57'),
(30, 31, 'KEZA CLARY', '::1', 'etudiant', 'verification', '2026-07-13 16:50:25', NULL, NULL),
(31, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-13 16:55:00', NULL, NULL),
(32, 31, 'KEZA CLARY', '::1', 'etudiant', 'verification', '2026-07-13 17:10:47', '2026-07-13 17:11:32', '00:00:45'),
(33, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-13 17:39:16', NULL, NULL),
(34, 20, 'Cubahiro Beni', '::1', 'surveillant', 'success', '2026-07-13 17:55:38', NULL, NULL),
(35, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 16:07:54', '2026-07-14 16:10:31', '00:02:37'),
(36, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 16:12:50', '2026-07-14 16:16:04', '00:03:14'),
(37, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 16:34:00', NULL, NULL),
(38, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 16:35:47', NULL, NULL),
(39, 20, 'Cubahiro Beni', '::1', 'surveillant', 'success', '2026-07-14 16:37:13', '2026-07-14 16:43:09', '00:05:56'),
(40, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 16:59:40', '2026-07-14 17:00:29', '00:00:49'),
(41, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-14 17:00:47', NULL, NULL),
(42, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-14 17:11:27', '2026-07-14 17:13:27', '00:02:00'),
(43, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'failed', '2026-07-14 17:33:27', NULL, NULL),
(44, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 17:33:36', NULL, NULL),
(45, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 17:34:04', NULL, NULL),
(46, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-14 17:35:02', NULL, NULL),
(47, 20, 'Cubahiro Beni', '::1', 'surveillant', 'success', '2026-07-14 18:02:28', '2026-07-14 18:03:00', '00:00:32'),
(48, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-15 16:39:50', '2026-07-15 16:40:50', '00:01:00'),
(49, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-15 16:41:16', NULL, NULL),
(50, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-15 16:46:55', NULL, NULL),
(51, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-15 17:06:00', '2026-07-15 17:06:32', '00:00:32'),
(52, 30, 'BURAKEYE FRANCK', '::1', 'etudiant', 'verification', '2026-07-15 17:17:05', NULL, NULL),
(53, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-15 19:34:44', '2026-07-15 19:41:37', '00:06:53'),
(54, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-15 19:57:13', '2026-07-15 19:58:58', '00:01:45'),
(55, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-07-16 19:43:28', '2026-07-16 19:44:47', '00:01:19'),
(56, 20, 'Cubahiro Beni', '::1', 'surveillant', 'success', '2026-07-16 19:50:19', '2026-07-16 19:55:08', '00:04:49'),
(57, 20, 'Cubahiro Beni', '::1', 'surveillant', 'verification', '2026-07-16 20:05:59', NULL, NULL),
(58, 20, 'Cubahiro Beni', '::1', 'surveillant', 'success', '2026-07-23 15:54:46', '2026-07-23 16:29:51', '00:35:05'),
(59, 33, 'ISHIMWE CHADIA', '::1', 'etudiant', 'verification', '2026-08-07 16:09:58', NULL, NULL),
(60, 33, 'ISHIMWE CHADIA', '::1', 'etudiant', 'verification', '2026-08-07 17:19:33', NULL, NULL),
(61, 33, 'ISHIMWE CHADIA', '::1', 'etudiant', 'verification', '2026-08-07 17:29:19', NULL, NULL),
(62, 33, 'ISHIMWE CHADIA', '::1', 'etudiant', 'verification', '2026-08-07 17:35:15', '2026-08-07 17:36:42', '00:01:27'),
(63, 33, 'ISHIMWE CHADIA', '::1', 'etudiant', 'verification', '2026-08-10 08:57:18', '2026-08-10 08:57:56', '00:00:38'),
(64, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-08-11 17:08:00', NULL, NULL),
(65, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-08-11 17:14:20', NULL, NULL),
(66, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-08-11 17:36:39', NULL, NULL),
(67, 21, 'Muhizi Blaise', '::1', 'surveillant', 'verification', '2026-08-11 17:45:12', NULL, NULL),
(68, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-08-11 17:46:02', NULL, NULL),
(69, 21, 'Muhizi Blaise', '::1', 'surveillant', 'success', '2026-08-11 17:54:44', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cours`
--

CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL,
  `nom_cours` varchar(40) NOT NULL,
  `credit` int(11) NOT NULL,
  `id_niveau` int(11) NOT NULL,
  `id_fac` int(11) NOT NULL,
  `date_exam` date NOT NULL DEFAULT '2026-01-01',
  `heure_debut` time NOT NULL DEFAULT '08:00:00',
  `heure_fin` time NOT NULL DEFAULT '10:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cours`
--

INSERT INTO `cours` (`id_cours`, `nom_cours`, `credit`, `id_niveau`, `id_fac`, `date_exam`, `heure_debut`, `heure_fin`) VALUES
(1, 'Maths', 2, 3, 7, '2026-01-28', '08:00:00', '10:00:00'),
(2, 'Physique', 1, 1, 1, '2026-01-01', '08:00:00', '10:00:00'),
(3, 'Chimie', 1, 1, 1, '2026-01-01', '08:00:00', '10:00:00'),
(4, 'Biologie', 2, 2, 1, '2026-01-01', '08:00:00', '10:00:00'),
(5, 'SSH', 2, 2, 1, '2026-01-01', '08:00:00', '10:00:00'),
(6, 'DSc', 2, 2, 1, '2026-01-01', '08:00:00', '10:00:00'),
(7, 'Algo', 2, 3, 1, '2026-01-01', '08:00:00', '10:00:00'),
(8, 'Meta', 2, 3, 1, '2026-01-01', '08:00:00', '10:00:00'),
(9, 'BS', 2, 3, 1, '2026-01-01', '08:00:00', '10:00:00'),
(10, 'Brunch', 1, 1, 2, '2026-01-01', '08:00:00', '10:00:00'),
(11, 'Catch', 1, 1, 2, '2026-01-01', '08:00:00', '10:00:00'),
(12, 'Dessin', 1, 1, 2, '2026-01-01', '08:00:00', '10:00:00'),
(13, 'Dessin Archi', 5, 2, 2, '2026-01-01', '08:00:00', '10:00:00'),
(14, 'Architecture', 1, 2, 2, '2026-01-01', '08:00:00', '10:00:00'),
(15, 'ColdCp', 1, 2, 2, '2026-01-01', '08:00:00', '10:00:00'),
(16, 'ColdCap', 1, 3, 2, '2026-01-01', '08:00:00', '10:00:00'),
(17, 'Colerique', 1, 3, 2, '2026-01-01', '08:00:00', '10:00:00'),
(18, 'Franchise', 1, 3, 2, '2026-01-01', '08:00:00', '10:00:00'),
(19, 'Collaboration', 1, 1, 3, '2026-01-01', '08:00:00', '10:00:00'),
(20, 'Frustration', 1, 1, 3, '2026-01-01', '08:00:00', '10:00:00'),
(21, 'Kabaye', 1, 1, 3, '2026-01-01', '08:00:00', '10:00:00'),
(22, 'Kabaye rero', 1, 2, 3, '2026-01-01', '08:00:00', '10:00:00'),
(23, 'Kabaye noneho', 1, 2, 3, '2026-01-01', '08:00:00', '10:00:00'),
(24, 'Kabaye erega', 1, 2, 3, '2026-01-01', '08:00:00', '10:00:00'),
(25, 'Ingo', 1, 3, 3, '2026-01-01', '08:00:00', '10:00:00'),
(26, 'Egera', 1, 3, 3, '2026-01-01', '08:00:00', '10:00:00'),
(27, 'Ruhuka', 1, 3, 3, '2026-01-01', '08:00:00', '10:00:00'),
(28, 'Retraite', 1, 1, 4, '2026-01-01', '08:00:00', '10:00:00'),
(29, 'Inaguriro', 1, 1, 4, '2026-01-01', '08:00:00', '10:00:00'),
(30, 'Randonnee', 1, 1, 4, '2026-01-01', '08:00:00', '10:00:00'),
(31, 'Rebroussage', 1, 2, 4, '2026-01-01', '08:00:00', '10:00:00'),
(32, 'Travail', 1, 2, 4, '2026-01-01', '08:00:00', '10:00:00'),
(33, 'Recherche', 1, 2, 4, '2026-01-01', '08:00:00', '10:00:00'),
(34, 'Recherche appliquee', 1, 3, 4, '2026-01-01', '08:00:00', '10:00:00'),
(35, 'Recherche approfondie', 1, 3, 4, '2026-01-01', '08:00:00', '10:00:00'),
(36, 'Recherche methodologique', 1, 3, 4, '2026-01-01', '08:00:00', '10:00:00'),
(37, 'Algorithme', 1, 1, 5, '2026-01-01', '08:00:00', '10:00:00'),
(38, 'Langage et compilation', 1, 1, 5, '2026-01-01', '08:00:00', '10:00:00'),
(39, 'Complot', 1, 1, 5, '2026-01-01', '08:00:00', '10:00:00'),
(40, 'Complotisme', 1, 2, 5, '2026-01-01', '08:00:00', '10:00:00'),
(41, 'Correlation', 1, 2, 5, '2026-01-01', '08:00:00', '10:00:00'),
(42, 'Sechage', 1, 2, 5, '2026-01-01', '08:00:00', '10:00:00'),
(43, 'Asphyxie', 1, 3, 5, '2026-01-01', '08:00:00', '10:00:00'),
(44, 'Secourisme', 1, 3, 5, '2026-01-01', '08:00:00', '10:00:00'),
(45, 'Assaisonnement', 1, 3, 5, '2026-01-01', '08:00:00', '10:00:00'),
(46, 'Prefixation', 1, 1, 6, '2026-01-01', '08:00:00', '10:00:00'),
(47, 'Suffixation', 1, 1, 6, '2026-01-01', '08:00:00', '10:00:00'),
(48, 'Adverbes et Adjectifs', 1, 1, 6, '2026-01-01', '08:00:00', '10:00:00'),
(49, 'Adverbes', 1, 2, 6, '2026-01-01', '08:00:00', '10:00:00'),
(50, 'Adjectifs', 1, 2, 6, '2026-01-01', '08:00:00', '10:00:00'),
(51, 'Alegations', 1, 2, 6, '2026-01-01', '08:00:00', '10:00:00'),
(52, 'Bimeze ukuntu', 1, 3, 6, '2026-01-01', '08:00:00', '10:00:00'),
(53, 'Biriko biraza', 1, 3, 6, '2026-01-01', '08:00:00', '10:00:00'),
(54, 'Camouflage', 1, 3, 6, '2026-01-01', '08:00:00', '10:00:00'),
(55, 'Cliches', 1, 1, 7, '2026-01-01', '08:00:00', '10:00:00'),
(56, 'Sommation', 1, 1, 7, '2026-01-01', '08:00:00', '10:00:00'),
(57, 'Dissolution', 1, 1, 7, '2026-01-01', '08:00:00', '10:00:00'),
(58, 'Transparence', 1, 2, 7, '2026-01-01', '08:00:00', '10:00:00'),
(59, 'Verite', 1, 2, 7, '2026-01-01', '08:00:00', '10:00:00'),
(60, 'Facilite', 1, 2, 7, '2026-01-01', '08:00:00', '10:00:00'),
(61, 'Finalisation', 1, 3, 7, '2026-01-01', '08:00:00', '10:00:00'),
(62, 'Celebration', 1, 3, 7, '2026-01-01', '08:00:00', '10:00:00'),
(63, 'Methodologie de recherche', 1, 3, 7, '2026-01-01', '08:00:00', '10:00:00'),
(64, 'Comptabilite', 3, 2, 7, '2026-01-01', '08:00:00', '10:00:00'),
(65, 'Oya', 3, 3, 6, '2026-07-08', '08:22:00', '10:28:00');

--
-- Triggers `cours`
--
DELIMITER $$
CREATE TRIGGER `after_delete_cours` AFTER DELETE ON `cours` FOR EACH ROW BEGIN

insert into suppression_cours(nom_cours,date_suppressio) VALUES(old.nom_cours, now());

DELETE FROM inscription where nom_cours =old.nom_cours;

end
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_update_cours` BEFORE UPDATE ON `cours` FOR EACH ROW BEGIN

IF new.credit <0 THEN

SIGNAL SQLSTATE '45000'

SET MESSAGE_TEXT =' Le credit doit etre superieur a 0';

END if;

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

CREATE TABLE `document` (
  `id_document` int(11) NOT NULL,
  `nom_document` varchar(255) NOT NULL,
  `fichier` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `date_ajout` datetime DEFAULT current_timestamp(),
  `id_admin` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `etudiant`
--

CREATE TABLE `etudiant` (
  `id_etu` int(11) NOT NULL,
  `nom_etu` varchar(40) NOT NULL,
  `prenom_etu` varchar(40) NOT NULL,
  `id_niveau` int(11) NOT NULL,
  `id_fac` int(11) NOT NULL,
  `login` varchar(50) DEFAULT NULL,
  `motdepasse` varchar(255) DEFAULT NULL,
  `premiere_connexion` tinyint(1) DEFAULT 1,
  `tentatives` int(11) DEFAULT 0,
  `compte_bloque` tinyint(1) DEFAULT 0,
  `date_blocage` datetime DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `etudiant`
--

INSERT INTO `etudiant` (`id_etu`, `nom_etu`, `prenom_etu`, `id_niveau`, `id_fac`, `login`, `motdepasse`, `premiere_connexion`, `tentatives`, `compte_bloque`, `date_blocage`, `photo`) VALUES
(1, 'Araje', 'Abelard', 2, 1, 'abelard', '123', 1, 1, 0, NULL, NULL),
(2, 'Karire', 'Claire', 3, 5, 'claire', '123', 1, 0, 0, NULL, NULL),
(3, 'Mirire', 'Marie', 1, 4, 'marie', '123', 1, 0, 0, NULL, '1786474739_6a7b70f35b60c.jpg'),
(5, 'Ciza', 'Marlene', 1, 4, 'marlene', '123', 1, 0, 0, NULL, NULL),
(6, 'Ciza', 'Bella Marlene', 3, 1, 'marly', '123', 1, 0, 0, NULL, NULL),
(7, 'Gahungu', 'Joseph', 3, 7, 'joseph', '123', 1, 0, 0, NULL, NULL),
(8, 'Ciza', 'Merlin', 1, 3, 'merlin', '123', 1, 0, 0, NULL, NULL),
(11, 'Dusenge', 'Francis', 3, 2, 'francis', '123', 1, 0, 0, NULL, NULL),
(12, 'Dukundane', 'Dieu Merci', 3, 5, 'dieme', '123', 1, 0, 0, NULL, NULL),
(13, 'Dukundane', 'Jean_de_la_Croix', 1, 2, 'jean', '123', 1, 0, 0, NULL, NULL),
(16, 'DUSHIME', 'TRESOR', 3, 7, 'tresor', '123', 1, 0, 0, NULL, NULL),
(17, 'GATEKA', 'GRACE', 2, 7, 'grace', '123', 1, 0, 0, NULL, NULL),
(18, 'BWITONZI', 'RAOUL', 1, 7, 'raoul', '123', 1, 0, 0, NULL, NULL),
(20, 'KUBWAYO', 'JEAN JACQUES', 2, 1, 'jacques', '123', 1, 0, 0, NULL, NULL),
(21, 'ISHIMWE', 'CHADIA KELLY', 1, 7, 'chadia', '123', 1, 0, 0, NULL, NULL),
(22, 'ISHIMWE', 'KELLY', 3, 1, 'kel', '123', 1, 0, 0, NULL, NULL),
(23, 'DUSABE', 'RACHELLE', 3, 1, 'rachelle', '123', 1, 0, 0, NULL, '1786115931_6a75f75b5c9c3.jpg'),
(24, 'KARIRE', 'BENEDICTE', 1, 3, 'benedicte', '123', 1, 0, 0, NULL, NULL),
(25, 'NINZIZA', 'KELLY', 3, 2, 'kely', '123', 1, 0, 0, NULL, NULL),
(26, 'KAKUNZE', 'EVODE', 1, 7, 'evode', '123', 1, 0, 0, NULL, NULL),
(27, 'BUKURU', 'BRIELLA', 1, 2, 'briella', '123', 1, 0, 0, NULL, NULL),
(28, 'BIZOZA', 'ALICIA', 1, 2, 'alicia', '123', 1, 0, 0, NULL, NULL),
(29, 'KAZE', 'KERNEL', 1, 1, 'kernel', '$2y$10$SrE/VkYvyByQy0hkU8u47.4lOBBW.NKPQ7IFgDy1E/TASd.yo2aSG', 1, 2, 0, NULL, NULL),
(30, 'BURAKEYE', 'FRANCK', 1, 2, 'frack', '$2y$10$I9wH.cpJZDwCvS1yPWkCRe8Jwn0c5nWmOU7BJM93Svq43IN66K6tK', 1, 0, 0, NULL, NULL),
(31, 'KEZA', 'CLARY', 1, 2, 'clary', '$2y$10$IFV6JX.Yx8XY7Zh5FYpaA.4OB3oSOZfg1.3M5nayWO.Q2T3Y.efg.', 1, 0, 0, NULL, NULL),
(32, 'BUKURU', 'PASCAL', 1, 2, 'pascal', '$2y$10$CO/qieVvUPvbA6EXZlMZpOQK5dSKykpwGciRybzgTCPW9KpHi2.rq', 1, 0, 0, NULL, NULL),
(33, 'ISHIMWE', 'CHADIA', 3, 1, 'chacha', '$2y$10$2PWsFPPUtqEnWgIcjS/C5OzeG0Pi5fEgX7Xc4ldo0es6E46EYZM.u', 1, 0, 0, NULL, '1786111738.jpg');

--
-- Triggers `etudiant`
--
DELIMITER $$
CREATE TRIGGER `after_delete_etudiant` AFTER DELETE ON `etudiant` FOR EACH ROW begin 

insert into suppression_etudiant(nom_etu,prenom_etu,date_suppression) VALUES (old.nom_etu, old.prenom_etu,now());

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_delete_etudiant` BEFORE DELETE ON `etudiant` FOR EACH ROW BEGIN

INSERT INTO historique_etudiant(nom_etu,prenom_etu)

VALUES (old.nom_etu,old.prenom_etu);

END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_insert_etudiant` BEFORE INSERT ON `etudiant` FOR EACH ROW BEGIN

DECLARE last_id int;

SELECT ifnull (MAX(id_etu),0)

INTO last_id

FROM etudiant;

SET new.id_etu= last_id+1;

end
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_insertion_etudiant` BEFORE INSERT ON `etudiant` FOR EACH ROW BEGIN

 SET new.nom_etu = upper(new.nom_etu),new.prenom_etu = upper(new.prenom_etu);

END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `faculte`
--

CREATE TABLE `faculte` (
  `id_fac` int(11) NOT NULL,
  `nom_fac` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculte`
--

INSERT INTO `faculte` (`id_fac`, `nom_fac`) VALUES
(1, 'Informatique'),
(2, 'Droit'),
(3, 'Economie'),
(4, 'Communication'),
(5, 'Sciences Politique'),
(6, 'Genie Civil'),
(7, 'Urbanisme');

-- --------------------------------------------------------

--
-- Table structure for table `historique_etudiant`
--

CREATE TABLE `historique_etudiant` (
  `id_his` int(11) NOT NULL,
  `nom_etu` varchar(30) DEFAULT NULL,
  `prenom_etu` varchar(30) DEFAULT NULL,
  `date_suppression` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `historique_etudiant`
--

INSERT INTO `historique_etudiant` (`id_his`, `nom_etu`, `prenom_etu`, `date_suppression`) VALUES
(6, 'BUKURU', 'JEANNE', '2026-06-18 13:42:11'),
(7, 'ISHIMWE', 'CHADIA KELLY', '2026-06-18 13:50:57'),
(8, 'ISHIMWE', 'CHADIA', '2026-06-18 13:51:01'),
(9, 'ISHIMWE', 'CHADIA', '2026-06-18 13:51:03'),
(12, 'KAZE', 'KERNEL', '2026-07-04 14:12:19');

-- --------------------------------------------------------

--
-- Table structure for table `historique_inscription`
--

CREATE TABLE `historique_inscription` (
  `id_histo` int(11) NOT NULL,
  `nom_etu` varchar(40) DEFAULT NULL,
  `prenom_etu` varchar(40) DEFAULT NULL,
  `nom_cours` varchar(30) DEFAULT NULL,
  `date_delete` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `historique_inscription`
--

INSERT INTO `historique_inscription` (`id_histo`, `nom_etu`, `prenom_etu`, `nom_cours`, `date_delete`) VALUES
(1, 'Zizane', 'Basile', 'SSH', '2026-06-08 09:31:20'),
(2, 'Zizane', 'Basile', 'Assaisonnement', '2026-06-08 09:31:20'),
(3, 'Zizane', 'Basile', 'Cliches', '2026-06-08 09:31:20'),
(4, 'Mirire', 'Marie Rose', 'Physique', '2026-06-08 10:20:00'),
(5, 'Mirire', 'Marie Rose', 'Correlation', '2026-06-08 10:20:00'),
(6, 'Mirire', 'Marie Rose', 'Secourisme', '2026-06-08 10:20:00'),
(7, 'Wakana', 'Merino', 'Brunch', '2026-06-08 10:30:11'),
(8, 'Wakana', 'Merino', 'Catch', '2026-06-08 10:30:11'),
(9, 'Wakana', 'Merino', 'Colerique', '2026-06-08 10:30:11'),
(10, 'Wakana', 'Merino', 'Retraite', '2026-06-08 10:30:11'),
(11, 'Wakana', 'Merino', 'Randonnee', '2026-06-08 10:30:11'),
(12, 'Wakana', 'Merino', 'Complot', '2026-06-08 10:30:11'),
(13, 'Wakana', 'Merino', 'Cliches', '2026-06-08 10:30:11');

-- --------------------------------------------------------

--
-- Table structure for table `historique_salle_etudiant`
--

CREATE TABLE `historique_salle_etudiant` (
  `id_histo` int(11) NOT NULL,
  `nom_etu` varchar(30) DEFAULT NULL,
  `prenom_etu` varchar(40) DEFAULT NULL,
  `ancienne_salle` varchar(15) DEFAULT NULL,
  `nouvelle_salle` varchar(15) DEFAULT NULL,
  `date_modification` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `historique_salle_etudiant`
--

INSERT INTO `historique_salle_etudiant` (`id_histo`, `nom_etu`, `prenom_etu`, `ancienne_salle`, `nouvelle_salle`, `date_modification`) VALUES
(1, 'DUSHIME', 'TRESOR', 'A07', 'F62', '2026-06-08 09:11:33'),
(2, 'KUBWAYO', 'JEAN JACQUES', 'B23', 'F66', '2026-06-08 10:11:08'),
(3, 'ISHIMWE', 'CHADIA', 'A07', 'F62', '2026-06-08 13:53:47'),
(4, 'Dukundane', 'Jean_de_la_Croix', 'E55', 'E55', '2026-06-19 10:14:22'),
(5, 'Karire', 'Claire', 'F66', 'F66', '2026-06-19 10:14:32'),
(6, 'Mirire', 'Marie', 'E55', 'E55', '2026-06-19 10:14:41'),
(7, 'Ciza', 'Marlene', 'B23', 'B23', '2026-06-19 10:15:08'),
(8, 'Ciza', 'Marlene', 'E50', 'E50', '2026-06-19 10:15:47'),
(9, 'Gahungu', 'Joseph', 'E50', 'E50', '2026-06-19 10:16:02'),
(10, 'DUSHIME', 'TRESOR', 'F62', 'F62', '2026-06-19 10:16:38'),
(11, 'Araje', 'Abelard', 'F66', 'F66', '2026-06-19 10:17:04'),
(12, 'Ciza', 'Merlin', 'B23', 'B23', '2026-06-19 10:18:16'),
(13, 'Dusenge', 'Francis', 'A07', 'A07', '2026-06-19 10:18:33'),
(14, 'Dukundane', 'Dieu Merci', 'A18', 'A18', '2026-06-19 10:18:46'),
(15, 'GATEKA', 'GRACE', 'B38', 'B38', '2026-06-19 10:19:00'),
(16, 'BWITONZI', 'RAOUL', 'E43', 'E43', '2026-06-19 10:19:22'),
(17, 'KUBWAYO', 'JEAN JACQUES', 'F66', 'F66', '2026-06-19 10:19:41'),
(18, 'ISHIMWE', 'CHADIA KELLY', 'F66', 'F66', '2026-06-19 10:19:57'),
(19, 'ISHIMWE', 'KELLY', 'E45', 'E45', '2026-06-19 10:20:20'),
(20, 'DUSABE', 'RACHELLE', 'A18', 'A18', '2026-06-19 10:20:34'),
(21, 'KARIRE', 'BENEDICTE', 'A18', 'A18', '2026-06-19 10:20:50'),
(22, 'Araje', 'Abelard', 'F66', 'E50', '2026-06-20 12:14:59'),
(23, 'Karire', 'Claire', 'F66', 'E50', '2026-06-20 12:18:31'),
(24, 'Karire', 'Claire', 'E50', 'E50', '2026-06-20 12:19:05'),
(25, 'Araje', 'Abelard', 'E50', 'E50', '2026-06-24 15:05:10'),
(26, 'Karire', 'Claire', 'E50', 'E50', '2026-06-24 15:05:46'),
(27, 'Mirire', 'Marie', 'E55', 'E55', '2026-06-24 15:06:09'),
(28, 'Ciza', 'Marlene', 'B23', 'B23', '2026-06-24 15:06:46'),
(29, 'Ciza', 'Marlene', 'E50', 'E50', '2026-06-24 15:07:12'),
(30, 'Gahungu', 'Joseph', 'E50', 'E50', '2026-06-24 15:07:49'),
(31, 'Ciza', 'Merlin', 'B23', 'B23', '2026-06-24 15:08:06'),
(32, 'Dusenge', 'Francis', 'A07', 'A07', '2026-06-24 15:08:58'),
(33, 'Dukundane', 'Dieu Merci', 'A18', 'A18', '2026-06-24 15:09:38'),
(34, 'Dukundane', 'Jean_de_la_Croix', 'E55', 'E55', '2026-06-24 15:09:58'),
(35, 'DUSHIME', 'TRESOR', 'F62', 'F62', '2026-06-24 15:10:17'),
(36, 'GATEKA', 'GRACE', 'B38', 'B38', '2026-06-24 15:10:38'),
(37, 'BWITONZI', 'RAOUL', 'E43', 'E43', '2026-06-24 15:11:02'),
(38, 'KUBWAYO', 'JEAN JACQUES', 'F66', 'F66', '2026-06-24 15:11:23'),
(39, 'ISHIMWE', 'CHADIA KELLY', 'F66', 'F66', '2026-06-24 15:11:45'),
(40, 'ISHIMWE', 'KELLY', 'E45', 'E45', '2026-06-24 15:12:09'),
(41, 'DUSABE', 'RACHELLE', 'A18', 'A18', '2026-06-24 15:12:31'),
(42, 'KARIRE', 'BENEDICTE', 'A18', 'A18', '2026-06-24 15:12:53'),
(43, 'NINZIZA', 'KELLY', 'E55', 'E55', '2026-06-24 15:13:20'),
(44, 'KAKUNZE', 'EVODE', 'A09', 'A09', '2026-06-24 15:13:40'),
(45, 'Ciza', 'Marlene', 'E50', 'E50', '2026-06-26 06:54:27'),
(46, 'Ciza', 'Bella', 'E50', 'E50', '2026-06-26 06:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `inscription`
--

CREATE TABLE `inscription` (
  `id_etu` int(11) NOT NULL,
  `id_cours` int(11) NOT NULL,
  `id_niveau` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inscription`
--

INSERT INTO `inscription` (`id_etu`, `id_cours`, `id_niveau`) VALUES
(1, 1, 1),
(1, 2, 1),
(1, 3, 1),
(1, 7, 1),
(1, 39, 1),
(1, 60, 1),
(2, 9, 1),
(3, 16, 1),
(3, 20, 1),
(5, 63, 1),
(6, 6, 1),
(7, 24, 1),
(7, 61, 1),
(8, 43, 1),
(8, 61, 1),
(11, 4, 1),
(11, 42, 1),
(12, 8, 1),
(12, 20, 1),
(2, 11, 2),
(2, 19, 2),
(2, 22, 2),
(3, 21, 2),
(3, 51, 2),
(5, 17, 2),
(5, 50, 2),
(6, 2, 2),
(7, 51, 2),
(8, 19, 2),
(12, 10, 2),
(5, 30, 3),
(5, 60, 3),
(6, 31, 3),
(6, 40, 3),
(7, 50, 3),
(8, 29, 3),
(8, 34, 3),
(8, 50, 3),
(11, 48, 3),
(11, 55, 3),
(12, 48, 3),
(12, 55, 3);

--
-- Triggers `inscription`
--
DELIMITER $$
CREATE TRIGGER `after_delete_inscription` AFTER DELETE ON `inscription` FOR EACH ROW BEGIN

insert INTO historique_inscription(nom_etu,prenom_etu,nom_cours,date_delete)

SELECT e.nom_etu,e.prenom_etu,c.nom_cours,now()

from etudiant e,cours c

WHERE e.id_etu= old.id_etu and c.id_cours =old.id_cours;

end
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `journal_activite`
--

CREATE TABLE `journal_activite` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type_user` varchar(20) NOT NULL,
  `action` varchar(255) NOT NULL,
  `date_action` datetime DEFAULT current_timestamp(),
  `nom_utilisateur` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journal_activite`
--

INSERT INTO `journal_activite` (`id`, `user_id`, `type_user`, `action`, `date_action`, `nom_utilisateur`) VALUES
(1, 0, 'Administrateur', 'admin', '2026-07-13 17:12:13', ''),
(2, 0, 'Administrateur', 'admin', '2026-07-13 17:35:49', ''),
(3, 0, 'Administrateur', 'admin', '2026-07-13 17:35:49', ''),
(4, 0, 'Administrateur', 'admin', '2026-07-13 17:37:59', ''),
(5, 0, 'Administrateur', 'admin', '2026-07-13 17:37:59', ''),
(6, 0, 'Administrateur', 'admin', '2026-07-13 17:42:31', ''),
(7, 30, 'etudiant', 'Consultation des affectations', '2026-07-14 16:09:49', ''),
(8, 30, 'etudiant', 'Consultation du profil', '2026-07-14 16:13:17', ''),
(9, 30, 'etudiant', 'Consultation du profil', '2026-07-14 16:15:01', ''),
(10, 30, 'etudiant', 'Consultation du profil', '2026-07-14 16:15:54', ''),
(11, 20, 'surveillant', 'Consultation de l\'espace surveillant', '2026-07-14 16:37:27', ''),
(12, 20, 'surveillant', 'Consultation du profil', '2026-07-14 16:37:41', ''),
(13, 20, 'surveillant', 'Consultation de l\'espace surveillant', '2026-07-14 16:40:12', ''),
(14, 20, 'surveillant', 'Consultation du profil', '2026-07-14 16:40:16', ''),
(15, 20, 'surveillant', 'Consultation de l\'espace surveillant', '2026-07-14 16:40:20', ''),
(16, 20, 'surveillant', 'Consultation de l\'espace surveillant', '2026-07-14 16:43:06', ''),
(17, 30, 'etudiant', 'Consultation des affectations', '2026-07-14 17:00:03', ' '),
(18, 30, 'etudiant', 'Consultation du profil', '2026-07-14 17:00:22', ' '),
(19, 21, 'surveillant', 'Modification du mot de passe', '2026-07-14 17:13:22', ' '),
(20, 0, 'Administrateur', 'admin', '2026-07-14 17:45:06', ''),
(21, 0, 'Administrateur', 'admin', '2026-07-14 17:54:51', ''),
(22, 0, 'Administrateur', 'admin', '2026-07-14 18:00:46', ''),
(23, 0, 'Administrateur', 'Modification d\'une affectation d\'examen', '2026-07-14 18:01:53', ''),
(24, 0, 'Administrateur', 'admin', '2026-07-14 18:01:53', ''),
(25, 0, 'Administrateur', 'admin', '2026-07-15 16:24:17', ''),
(26, 0, 'Administrateur', 'admin', '2026-07-15 16:25:12', ''),
(27, 0, 'Administrateur', 'admin', '2026-07-15 16:27:24', ''),
(28, 0, 'Administrateur', 'admin', '2026-07-15 16:27:30', ''),
(29, 0, 'Administrateur', 'admin', '2026-07-15 16:28:24', ''),
(30, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:40:25', ' '),
(31, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:41:32', ' '),
(32, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:27', ' '),
(33, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:29', ' '),
(34, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:29', ' '),
(35, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:29', ' '),
(36, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:29', ' '),
(37, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:44:29', ' '),
(38, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:46:32', 'BURAKEYE FRANCK'),
(39, 30, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-07-15 16:49:46', 'BURAKEYE FRANCK'),
(40, 30, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-07-15 16:49:47', 'BURAKEYE FRANCK'),
(41, 30, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-07-15 16:49:47', 'BURAKEYE FRANCK'),
(42, 30, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-07-15 16:49:48', 'BURAKEYE FRANCK'),
(43, 30, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-07-15 16:49:48', 'BURAKEYE FRANCK'),
(44, 30, 'etudiant', 'Consultation des affectations', '2026-07-15 16:50:08', ' '),
(45, 30, 'etudiant', 'Consultation des affectations', '2026-07-15 16:53:10', ' '),
(46, 30, 'etudiant', 'Consultation des affectations', '2026-07-15 16:57:26', 'BURAKEYE FRANCK'),
(47, 30, 'etudiant', 'Consultation du profil', '2026-07-15 16:57:41', 'BURAKEYE FRANCK'),
(48, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 16:58:32', ' '),
(49, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 16:59:25', ' '),
(50, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 16:59:26', ' '),
(51, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 16:59:26', ' '),
(52, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:03:55', ' '),
(53, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:03:56', ' '),
(54, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:03:57', ' '),
(55, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:03:57', ' '),
(56, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:03:57', ' '),
(57, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:44', ' '),
(58, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:45', ' '),
(59, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:45', ' '),
(60, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:45', ' '),
(61, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:45', ' '),
(62, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:46', ' '),
(63, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:46', ' '),
(64, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:47', ' '),
(65, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:05:47', ' '),
(66, 30, 'etudiant', 'Consultation du profil', '2026-07-15 17:06:17', 'BURAKEYE FRANCK'),
(67, 30, 'etudiant', 'Changement de mot de passe', '2026-07-15 17:06:21', 'BURAKEYE FRANCK'),
(68, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 17:07:06', 'Administrateur'),
(69, 21, 'surveillant', 'Consultation du profil', '2026-07-15 19:35:13', ' '),
(70, 21, 'surveillant', 'Consultation du profil', '2026-07-15 19:38:27', 'Muhizi Blaise'),
(71, 21, 'surveillant', 'Consultation du profil', '2026-07-15 19:39:41', 'Muhizi Blaise'),
(72, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:03:30', 'Administrateur'),
(73, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:05:07', 'Administrateur'),
(74, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:05:09', 'Administrateur'),
(75, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:05:09', 'Administrateur'),
(76, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:05:09', 'Administrateur'),
(77, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-15 20:05:10', 'Administrateur'),
(78, 21, 'surveillant', 'Modification du mot de passe', '2026-07-16 19:44:19', ' '),
(79, 21, 'surveillant', 'Consultation du profil', '2026-07-16 19:44:35', 'Muhizi Blaise'),
(80, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-07-16 19:45:41', 'Administrateur'),
(81, 20, 'surveillant', 'Consultation du profil', '2026-07-16 19:53:49', 'Cubahiro Beni'),
(82, 20, 'surveillant', 'Consultation du profil', '2026-07-16 19:54:54', 'Cubahiro Beni'),
(83, 0, 'admin', 'Consultation des affectations d\'examens', '2026-07-16 20:01:55', 'Administrateur'),
(84, 0, 'admin', 'Consultation de la liste des cours', '2026-07-23 14:02:00', 'Administrateur'),
(85, 0, 'admin', 'Consultation de la liste des cours', '2026-07-23 14:15:53', 'Administrateur'),
(86, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:16:11', 'Administrateur'),
(87, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:16:51', 'Administrateur'),
(88, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:20:33', 'Administrateur'),
(89, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:22:59', 'Administrateur'),
(90, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:25:20', 'Administrateur'),
(91, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:25:41', 'Administrateur'),
(92, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:27:18', 'Administrateur'),
(93, 0, 'admin', 'Création d\'une affectation d\'examen', '2026-07-23 14:29:30', 'Administrateur'),
(94, 0, 'admin', 'Consultation des affectations d\'examens', '2026-07-23 14:29:31', 'Administrateur'),
(95, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:29:56', 'Administrateur'),
(96, 0, 'admin', 'Consultation des étudiants d\'une salle', '2026-07-23 14:30:19', 'Administrateur'),
(97, 0, 'admin', 'Création d\'une affectation d\'examen', '2026-07-23 16:33:34', 'Administrateur'),
(98, 0, 'admin', 'Consultation des affectations d\'examens', '2026-07-23 16:33:34', 'Administrateur'),
(99, 0, 'admin', 'Consultation des affectations d\'examens', '2026-07-23 17:11:45', 'Administrateur'),
(100, 0, 'admin', 'Consultation des affectations d\'examens', '2026-07-23 17:19:41', 'Administrateur'),
(101, 0, 'admin', 'Consultation des archives des examens', '2026-08-07 15:04:08', 'Administrateur'),
(102, 0, 'admin', 'Consultation des archives des examens', '2026-08-07 15:10:07', 'Administrateur'),
(103, 0, 'admin', 'Consultation des archives des examens', '2026-08-07 15:11:41', 'Administrateur'),
(104, 0, 'admin', 'Consultation de l\'historique des connexions', '2026-08-07 15:11:47', 'Administrateur'),
(105, 0, 'admin', 'Ajout de l\'étudiant Ishimwe Chadia', '2026-08-07 16:08:58', 'Administrateur'),
(106, 33, 'etudiant', 'Consultation du profil', '2026-08-07 16:10:14', 'ISHIMWE CHADIA'),
(107, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:19:49', 'ISHIMWE CHADIA'),
(108, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:24:51', 'ISHIMWE CHADIA'),
(109, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:29', 'ISHIMWE CHADIA'),
(110, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:29', 'ISHIMWE CHADIA'),
(111, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:30', 'ISHIMWE CHADIA'),
(112, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:30', 'ISHIMWE CHADIA'),
(113, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:30', 'ISHIMWE CHADIA'),
(114, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:30', 'ISHIMWE CHADIA'),
(115, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:30', 'ISHIMWE CHADIA'),
(116, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:25:31', 'ISHIMWE CHADIA'),
(117, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:27:25', 'ISHIMWE CHADIA'),
(118, 33, 'etudiant', 'Consultation du profil', '2026-08-07 17:29:34', 'ISHIMWE CHADIA'),
(119, 33, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-08-07 17:29:42', 'ISHIMWE CHADIA'),
(120, 33, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-08-07 17:35:30', 'ISHIMWE CHADIA'),
(121, 33, 'etudiant', 'Consultation du profil', '2026-08-10 08:57:34', 'ISHIMWE CHADIA'),
(122, 33, 'etudiant', 'Consultation de la liste des camarades de classe', '2026-08-10 08:57:44', 'ISHIMWE CHADIA'),
(123, 0, 'Admin', 'Création d\'une affectation d\'examen', '2026-08-11 16:08:51', 'Administrateur'),
(124, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 16:08:51', 'Administrateur'),
(125, 21, 'surveillant', 'Consultation du profil', '2026-08-11 17:08:18', 'Muhizi Blaise'),
(126, 21, 'surveillant', 'Consultation du profil', '2026-08-11 17:10:57', 'Muhizi Blaise'),
(127, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:11:38', 'Administrateur'),
(128, 0, 'Admin', 'Modification d\'une affectation d\'examen', '2026-08-11 17:13:24', 'Administrateur'),
(129, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:13:24', 'Administrateur'),
(130, 0, 'Admin', 'Modification d\'une affectation d\'examen', '2026-08-11 17:14:04', 'Administrateur'),
(131, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:14:04', 'Administrateur'),
(132, 21, 'surveillant', 'Consultation du profil', '2026-08-11 17:25:25', 'Muhizi Blaise'),
(133, 21, 'surveillant', 'Consultation du profil', '2026-08-11 17:25:30', 'Muhizi Blaise'),
(134, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:31:01', 'Administrateur'),
(135, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:42:20', 'Administrateur'),
(136, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 17:43:17', 'Administrateur'),
(137, 21, 'surveillant', 'Consultation du profil', '2026-08-11 17:47:02', 'Muhizi Blaise'),
(138, 0, 'admin', 'Consultation des archives des examens', '2026-08-11 20:36:19', 'Administrateur'),
(139, 0, 'admin', 'Consultation des affectations d\'examens', '2026-08-11 20:36:26', 'Administrateur'),
(140, 0, 'admin', 'Consultation de la liste des cours', '2026-08-11 20:43:29', 'Administrateur'),
(141, 0, 'Admin', 'Suppression du cours ', '2026-08-11 20:46:27', 'Administrateur'),
(142, 0, 'admin', 'Consultation de la liste des cours', '2026-08-11 20:46:27', 'Administrateur'),
(143, 0, 'Admin', 'Suppression du cours ', '2026-08-11 20:46:38', 'Administrateur'),
(144, 0, 'admin', 'Consultation de la liste des cours', '2026-08-11 20:46:38', 'Administrateur'),
(145, 0, 'Admin', 'Suppression du cours ', '2026-08-11 20:46:51', 'Administrateur'),
(146, 0, 'admin', 'Consultation de la liste des cours', '2026-08-11 20:46:51', 'Administrateur');

-- --------------------------------------------------------

--
-- Table structure for table `niveau_etude`
--

CREATE TABLE `niveau_etude` (
  `id_niveau` int(11) NOT NULL,
  `nom_niveau` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `niveau_etude`
--

INSERT INTO `niveau_etude` (`id_niveau`, `nom_niveau`) VALUES
(1, 'Bac1'),
(2, 'Bac2'),
(3, 'Bac3');

-- --------------------------------------------------------

--
-- Table structure for table `salle`
--

CREATE TABLE `salle` (
  `id_salle` int(11) NOT NULL,
  `nom_salle` varchar(40) NOT NULL,
  `capacite` int(11) NOT NULL,
  `id_surv` int(11) DEFAULT NULL,
  `statut` enum('disponible','occupée') DEFAULT 'disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salle`
--

INSERT INTO `salle` (`id_salle`, `nom_salle`, `capacite`, `id_surv`, `statut`) VALUES
(1, 'F66', 60, 6, 'disponible'),
(2, 'F62', 30, 10, 'disponible'),
(3, 'E55', 45, 5, 'disponible'),
(4, 'E55', 45, 1, 'disponible'),
(5, 'E50', 45, 1, 'disponible'),
(6, 'E45', 25, 3, 'disponible'),
(7, 'E43', 25, 2, 'disponible'),
(8, 'B23', 20, 9, 'disponible'),
(9, 'B38', 20, 4, 'disponible'),
(10, 'A18', 10, 7, 'disponible'),
(11, 'A07', 30, 8, 'disponible'),
(12, 'sd', 0, 2, 'disponible'),
(13, 'A09', 34, 6, 'disponible'),
(14, '', 175, 10, 'disponible');

-- --------------------------------------------------------

--
-- Table structure for table `suppression_cours`
--

CREATE TABLE `suppression_cours` (
  `id_sup_cours` int(11) NOT NULL,
  `nom_cours` varchar(20) DEFAULT NULL,
  `date_suppressio` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppression_etudiant`
--

CREATE TABLE `suppression_etudiant` (
  `id_supp_et` int(11) NOT NULL,
  `nom_etu` varchar(50) DEFAULT NULL,
  `prenom_etu` varchar(50) DEFAULT NULL,
  `date_suppression` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppression_etudiant`
--

INSERT INTO `suppression_etudiant` (`id_supp_et`, `nom_etu`, `prenom_etu`, `date_suppression`) VALUES
(1, 'Burihabwa', 'Keyna', '2026-06-08 00:21:18'),
(2, 'Zizane', 'Basile', '2026-06-08 09:31:20'),
(3, 'IZERIMANA', 'AURELIE', '2026-06-08 10:12:05'),
(4, 'Mirire', 'Marie Rose', '2026-06-08 10:20:00'),
(5, 'Wakana', 'Merino', '2026-06-08 10:30:11'),
(6, 'ISHIMWE', 'CHADIA', '2026-06-08 13:54:31'),
(7, 'BUKURU', 'JEANNE', '2026-06-18 13:42:11'),
(8, 'ISHIMWE', 'CHADIA KELLY', '2026-06-18 13:50:57'),
(9, 'ISHIMWE', 'CHADIA', '2026-06-18 13:51:01'),
(10, 'ISHIMWE', 'CHADIA', '2026-06-18 13:51:03'),
(11, 'KAZE', 'KERNEL', '2026-07-04 14:12:19');

-- --------------------------------------------------------

--
-- Table structure for table `surveillant`
--

CREATE TABLE `surveillant` (
  `id_surv` int(11) NOT NULL,
  `nom_surv` varchar(40) NOT NULL,
  `prenom_surv` varchar(40) NOT NULL,
  `login` varchar(50) NOT NULL,
  `motdepasse` varchar(255) NOT NULL,
  `premiere_connexion` tinyint(1) DEFAULT 1,
  `tentatives` int(11) DEFAULT 0,
  `compte_bloque` tinyint(1) DEFAULT 0,
  `date_blocage` datetime DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `surveillant`
--

INSERT INTO `surveillant` (`id_surv`, `nom_surv`, `prenom_surv`, `login`, `motdepasse`, `premiere_connexion`, `tentatives`, `compte_bloque`, `date_blocage`, `photo`) VALUES
(1, 'Arakaza', 'Lionel', 'lionel', '1234', 1, 0, 0, NULL, NULL),
(2, 'Abayo', 'Leandre', 'leandre', '1234', 1, 0, 0, NULL, NULL),
(3, 'Bahizi', 'Edouard', 'edouard', '1234', 1, 0, 0, NULL, NULL),
(4, 'Bamire', 'Claver', 'claver', '1234', 1, 0, 0, NULL, NULL),
(5, 'Ntunzwenimana', 'Claire', 'claire', '1234', 1, 0, 0, NULL, NULL),
(6, 'Nduwayo', 'Jeanette', 'jeanette', '1234', 1, 0, 0, NULL, NULL),
(7, 'Zirikunama', 'Zilfa', 'zilfa', '1234', 1, 0, 0, NULL, NULL),
(8, 'Karikurubu', 'Jean Jacques', 'john', '1234', 1, 0, 0, NULL, NULL),
(9, 'Kakunze', 'Marie Claudine', 'claudine', '1234', 1, 0, 0, NULL, NULL),
(10, 'Cibutso', 'Annie Grace', 'annie', '1234', 1, 0, 0, NULL, NULL),
(18, 'Nduwayo', 'Adelaide', 'adelaide', '1234', 1, 0, 0, NULL, NULL),
(19, 'Maza', 'Magdelene', 'magdelene', '1234', 1, 0, 0, NULL, NULL),
(20, 'Cubahiro', 'Beni', 'beni', '$2y$10$Tkh.Cj1TbsJk3gXyjInIKOQQEZ23SZsidD/y9QoI1No45M5uOv9PG', 1, 0, 0, NULL, NULL),
(21, 'Muhizi', 'Blaise', 'blaise', '$2y$10$Nh5kCxalQFquSgNTwxJBPOJhmmn5PZn9ew18JnzshmNp5fkWHn15.', 1, 0, 0, NULL, NULL),
(22, 'Babo', 'Bebe', 'bebe', '$2y$10$N5Yo4JfpFqKuFNRgp2njGud/xVmD/wL2QelWEQROePOMGCyQxriDO', 1, 0, 0, NULL, NULL),
(23, 'Ariho', 'Patience', 'pacy', '$2y$10$y2xStHcTBPfRACXn6B0.cei1yEbKhba1s1FZq/Vl8.NQJAMW/uCkW', 1, 0, 0, NULL, NULL),
(24, 'Nahayo', 'Emma', 'emma', '$2y$10$fCrexn8rI.gDgtAbj9u9Pe2T1akRX2IYIzqOGF9313/oU8sACBECG', 1, 0, 0, NULL, NULL),
(25, 'Bukuru', 'alfred', 'fred', '$2y$10$aPXTpAVYa43bVGW9eIjpHeAAWPu.aCtysGnRGGlh9L69SrMpKqMCa', 1, 0, 0, NULL, NULL);

--
-- Triggers `surveillant`
--
DELIMITER $$
CREATE TRIGGER `trg_generer_motdepasse` BEFORE INSERT ON `surveillant` FOR EACH ROW BEGIN
    IF NEW.motdepasse IS NULL OR NEW.motdepasse = '' THEN
        SET NEW.motdepasse = '1234';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `id_user` int(11) NOT NULL,
  `nom_user` varchar(50) NOT NULL,
  `login` varchar(50) NOT NULL,
  `motdepasse` varchar(255) NOT NULL,
  `role` enum('admin','etudiant','surveillant') NOT NULL,
  `id_etu` int(11) DEFAULT NULL,
  `id_surv` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `utilisateur`
--

INSERT INTO `utilisateur` (`id_user`, `nom_user`, `login`, `motdepasse`, `role`, `id_etu`, `id_surv`) VALUES
(1, 'Kaze', 'Carly', '123', 'etudiant', NULL, NULL),
(2, 'ninziza kelly', 'kelly', '12345', 'surveillant', NULL, NULL),
(3, 'cynthia', 'cycy', '123', 'etudiant', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `verification_code`
--

CREATE TABLE `verification_code` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type_user` varchar(20) DEFAULT NULL,
  `code` varchar(6) DEFAULT NULL,
  `date_creation` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `verification_code`
--

INSERT INTO `verification_code` (`id`, `user_id`, `type_user`, `code`, `date_creation`) VALUES
(1, 30, 'etudiant', '817272', '2026-07-07 17:09:52'),
(2, 31, 'etudiant', '694744', '2026-07-07 19:33:26'),
(3, 30, 'etudiant', '505789', '2026-07-07 19:42:46'),
(4, 30, 'etudiant', '351316', '2026-07-07 19:43:59'),
(5, 31, 'etudiant', '271271', '2026-07-07 19:45:17'),
(6, 32, 'etudiant', '310638', '2026-07-07 19:50:06'),
(7, 30, 'etudiant', '835216', '2026-07-08 15:10:34'),
(8, 30, 'etudiant', '452154', '2026-07-08 15:18:10'),
(9, 30, 'etudiant', '427701', '2026-07-08 15:26:16'),
(10, 30, 'etudiant', '358375', '2026-07-08 15:30:57'),
(11, 30, 'etudiant', '608983', '2026-07-08 15:31:50'),
(12, 30, 'etudiant', '514390', '2026-07-08 15:48:33'),
(13, 30, 'etudiant', '417703', '2026-07-08 15:49:11'),
(14, 30, 'etudiant', '211906', '2026-07-08 16:01:45'),
(15, 30, 'etudiant', '168794', '2026-07-08 16:02:21'),
(16, 30, 'etudiant', '798140', '2026-07-08 16:06:37'),
(17, 30, 'etudiant', '655656', '2026-07-08 16:42:05'),
(18, 25, 'surveillant', '941987', '2026-07-08 17:36:53'),
(19, 25, 'surveillant', '795574', '2026-07-08 17:38:39'),
(20, 25, 'surveillant', '741161', '2026-07-08 17:53:50'),
(21, 30, 'etudiant', '680081', '2026-07-09 17:13:51'),
(22, 30, 'etudiant', '984974', '2026-07-09 17:20:50'),
(23, 31, 'etudiant', '707491', '2026-07-13 14:15:53'),
(24, 31, 'etudiant', '587515', '2026-07-13 16:50:25'),
(25, 21, 'surveillant', '508201', '2026-07-13 16:55:00'),
(26, 31, 'etudiant', '504963', '2026-07-13 17:10:47'),
(27, 21, 'surveillant', '241971', '2026-07-13 17:39:16'),
(28, 20, 'surveillant', '592537', '2026-07-13 17:55:38'),
(29, 30, 'etudiant', '500130', '2026-07-14 16:07:54'),
(30, 30, 'etudiant', '272557', '2026-07-14 16:12:50'),
(31, 30, 'etudiant', '109091', '2026-07-14 16:34:00'),
(32, 30, 'etudiant', '705716', '2026-07-14 16:35:47'),
(33, 20, 'surveillant', '813325', '2026-07-14 16:37:13'),
(34, 30, 'etudiant', '406350', '2026-07-14 16:59:40'),
(35, 21, 'surveillant', '543605', '2026-07-14 17:00:47'),
(36, 21, 'surveillant', '771873', '2026-07-14 17:11:27'),
(37, 30, 'etudiant', '612420', '2026-07-14 17:33:36'),
(38, 30, 'etudiant', '959358', '2026-07-14 17:34:04'),
(39, 30, 'etudiant', '632853', '2026-07-14 17:35:03'),
(40, 20, 'surveillant', '385424', '2026-07-14 18:02:28'),
(41, 30, 'etudiant', '939705', '2026-07-15 16:39:50'),
(42, 30, 'etudiant', '936181', '2026-07-15 16:41:16'),
(43, 30, 'etudiant', '141897', '2026-07-15 16:46:55'),
(44, 30, 'etudiant', '419923', '2026-07-15 17:06:00'),
(45, 30, 'etudiant', '364198', '2026-07-15 17:17:05'),
(46, 21, 'surveillant', '924937', '2026-07-15 19:34:44'),
(47, 21, 'surveillant', '529916', '2026-07-15 19:57:13'),
(48, 21, 'surveillant', '900931', '2026-07-16 19:43:28'),
(49, 20, 'surveillant', '745282', '2026-07-16 19:50:19'),
(50, 20, 'surveillant', '428873', '2026-07-16 20:05:59'),
(51, 20, 'surveillant', '482198', '2026-07-23 15:54:46'),
(52, 33, 'etudiant', '826556', '2026-08-07 16:09:58'),
(53, 33, 'etudiant', '310409', '2026-08-07 17:19:33'),
(54, 33, 'etudiant', '300335', '2026-08-07 17:29:19'),
(55, 33, 'etudiant', '377118', '2026-08-07 17:35:15'),
(56, 33, 'etudiant', '729117', '2026-08-10 08:57:18'),
(57, 21, 'surveillant', '777728', '2026-08-11 17:08:00'),
(58, 21, 'surveillant', '967187', '2026-08-11 17:14:20'),
(59, 21, 'surveillant', '285169', '2026-08-11 17:36:39'),
(60, 21, 'surveillant', '624796', '2026-08-11 17:45:12'),
(61, 21, 'surveillant', '478630', '2026-08-11 17:46:02'),
(62, 21, 'surveillant', '319907', '2026-08-11 17:54:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `affectation`
--
ALTER TABLE `affectation`
  ADD PRIMARY KEY (`id_affectation`),
  ADD UNIQUE KEY `uk_affectation` (`id_etu`,`id_cours`,`date_exam`),
  ADD KEY `id_cours` (`id_cours`),
  ADD KEY `id_salle` (`id_salle`),
  ADD KEY `id_surv` (`id_surv`);

--
-- Indexes for table `affectation_archive`
--
ALTER TABLE `affectation_archive`
  ADD PRIMARY KEY (`id_archive`);

--
-- Indexes for table `backup`
--
ALTER TABLE `backup`
  ADD PRIMARY KEY (`idbackup`);

--
-- Indexes for table `connexion_log`
--
ALTER TABLE `connexion_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cours`
--
ALTER TABLE `cours`
  ADD PRIMARY KEY (`id_cours`),
  ADD KEY `id_fac` (`id_fac`),
  ADD KEY `id_niveau` (`id_niveau`);

--
-- Indexes for table `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`id_document`);

--
-- Indexes for table `etudiant`
--
ALTER TABLE `etudiant`
  ADD PRIMARY KEY (`id_etu`),
  ADD KEY `id_fac` (`id_fac`),
  ADD KEY `id_niveau` (`id_niveau`);

--
-- Indexes for table `faculte`
--
ALTER TABLE `faculte`
  ADD PRIMARY KEY (`id_fac`);

--
-- Indexes for table `historique_etudiant`
--
ALTER TABLE `historique_etudiant`
  ADD PRIMARY KEY (`id_his`);

--
-- Indexes for table `historique_inscription`
--
ALTER TABLE `historique_inscription`
  ADD PRIMARY KEY (`id_histo`);

--
-- Indexes for table `historique_salle_etudiant`
--
ALTER TABLE `historique_salle_etudiant`
  ADD PRIMARY KEY (`id_histo`);

--
-- Indexes for table `inscription`
--
ALTER TABLE `inscription`
  ADD PRIMARY KEY (`id_etu`,`id_cours`),
  ADD KEY `id_cours` (`id_cours`),
  ADD KEY `fk_niveau_etude` (`id_niveau`);

--
-- Indexes for table `journal_activite`
--
ALTER TABLE `journal_activite`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `niveau_etude`
--
ALTER TABLE `niveau_etude`
  ADD PRIMARY KEY (`id_niveau`);

--
-- Indexes for table `salle`
--
ALTER TABLE `salle`
  ADD PRIMARY KEY (`id_salle`),
  ADD KEY `fk_surveillant` (`id_surv`);

--
-- Indexes for table `suppression_cours`
--
ALTER TABLE `suppression_cours`
  ADD PRIMARY KEY (`id_sup_cours`);

--
-- Indexes for table `suppression_etudiant`
--
ALTER TABLE `suppression_etudiant`
  ADD PRIMARY KEY (`id_supp_et`);

--
-- Indexes for table `surveillant`
--
ALTER TABLE `surveillant`
  ADD PRIMARY KEY (`id_surv`);

--
-- Indexes for table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `fk_user_etu` (`id_etu`);

--
-- Indexes for table `verification_code`
--
ALTER TABLE `verification_code`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `affectation`
--
ALTER TABLE `affectation`
  MODIFY `id_affectation` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `affectation_archive`
--
ALTER TABLE `affectation_archive`
  MODIFY `id_archive` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup`
--
ALTER TABLE `backup`
  MODIFY `idbackup` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `connexion_log`
--
ALTER TABLE `connexion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `cours`
--
ALTER TABLE `cours`
  MODIFY `id_cours` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `document`
--
ALTER TABLE `document`
  MODIFY `id_document` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `etudiant`
--
ALTER TABLE `etudiant`
  MODIFY `id_etu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `faculte`
--
ALTER TABLE `faculte`
  MODIFY `id_fac` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `historique_etudiant`
--
ALTER TABLE `historique_etudiant`
  MODIFY `id_his` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `historique_inscription`
--
ALTER TABLE `historique_inscription`
  MODIFY `id_histo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `historique_salle_etudiant`
--
ALTER TABLE `historique_salle_etudiant`
  MODIFY `id_histo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `journal_activite`
--
ALTER TABLE `journal_activite`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `niveau_etude`
--
ALTER TABLE `niveau_etude`
  MODIFY `id_niveau` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `salle`
--
ALTER TABLE `salle`
  MODIFY `id_salle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `suppression_cours`
--
ALTER TABLE `suppression_cours`
  MODIFY `id_sup_cours` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppression_etudiant`
--
ALTER TABLE `suppression_etudiant`
  MODIFY `id_supp_et` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `surveillant`
--
ALTER TABLE `surveillant`
  MODIFY `id_surv` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `verification_code`
--
ALTER TABLE `verification_code`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `affectation`
--
ALTER TABLE `affectation`
  ADD CONSTRAINT `affectation_ibfk_1` FOREIGN KEY (`id_etu`) REFERENCES `etudiant` (`id_etu`),
  ADD CONSTRAINT `affectation_ibfk_2` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`),
  ADD CONSTRAINT `affectation_ibfk_3` FOREIGN KEY (`id_salle`) REFERENCES `salle` (`id_salle`),
  ADD CONSTRAINT `affectation_ibfk_4` FOREIGN KEY (`id_surv`) REFERENCES `surveillant` (`id_surv`);

--
-- Constraints for table `cours`
--
ALTER TABLE `cours`
  ADD CONSTRAINT `cours_ibfk_1` FOREIGN KEY (`id_fac`) REFERENCES `faculte` (`id_fac`),
  ADD CONSTRAINT `cours_ibfk_2` FOREIGN KEY (`id_niveau`) REFERENCES `niveau_etude` (`id_niveau`),
  ADD CONSTRAINT `fk_cours_faculte` FOREIGN KEY (`id_fac`) REFERENCES `faculte` (`id_fac`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cours_niveau` FOREIGN KEY (`id_niveau`) REFERENCES `niveau_etude` (`id_niveau`) ON UPDATE CASCADE;

--
-- Constraints for table `etudiant`
--
ALTER TABLE `etudiant`
  ADD CONSTRAINT `etudiant_ibfk_2` FOREIGN KEY (`id_fac`) REFERENCES `faculte` (`id_fac`),
  ADD CONSTRAINT `etudiant_ibfk_3` FOREIGN KEY (`id_niveau`) REFERENCES `niveau_etude` (`id_niveau`);

--
-- Constraints for table `inscription`
--
ALTER TABLE `inscription`
  ADD CONSTRAINT `fk_niveau_etude` FOREIGN KEY (`id_niveau`) REFERENCES `niveau_etude` (`id_niveau`),
  ADD CONSTRAINT `inscription_ibfk_1` FOREIGN KEY (`id_etu`) REFERENCES `etudiant` (`id_etu`),
  ADD CONSTRAINT `inscription_ibfk_2` FOREIGN KEY (`id_cours`) REFERENCES `cours` (`id_cours`);

--
-- Constraints for table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `fk_user_etu` FOREIGN KEY (`id_etu`) REFERENCES `etudiant` (`id_etu`);

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `maj_salles` ON SCHEDULE EVERY 1 HOUR STARTS '2026-06-25 15:13:19' ON COMPLETION NOT PRESERVE ENABLE DO UPDATE salle s
SET statut =
IF(EXISTS (
    SELECT 1
    FROM affectation a
    WHERE a.id_salle = s.id_salle
    AND NOW() BETWEEN 
        CONCAT(a.date_exam,' ',a.heure_debut)
        AND CONCAT(a.date_exam,' ',a.heure_fin)
), 'occupée', 'disponible')$$

CREATE DEFINER=`root`@`localhost` EVENT `nettoyage_connexion` ON SCHEDULE EVERY 1 DAY STARTS '2026-07-07 15:47:40' ON COMPLETION NOT PRESERVE ENABLE DO DELETE FROM connexion_log
WHERE date_connexion < DATE_SUB(NOW(), INTERVAL 1 DAY)$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
