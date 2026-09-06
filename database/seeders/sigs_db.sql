-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : jeu. 03 sep. 2026 à 20:39
-- Version du serveur : 8.0.31
-- Version de PHP : 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `sigs_db`
--

-- --------------------------------------------------------

--
-- Structure de la table `academic_years`
--

DROP TABLE IF EXISTS `academic_years`;
CREATE TABLE IF NOT EXISTS `academic_years` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `academic_years_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `academic_years`
--

INSERT INTO `academic_years` (`id`, `code`, `label`, `is_active`, `date_start`, `date_end`, `created_at`) VALUES
(1, '2026-2027', 'Année 2026-2027', 1, '2026-09-01', '2027-07-31', '2026-08-25 06:50:48');

-- --------------------------------------------------------

--
-- Structure de la table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `actor_user_id` bigint UNSIGNED DEFAULT NULL,
  `action_code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_label` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_json` json DEFAULT NULL,
  `changes_json` json DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `audit_logs_actor_user_id_foreign` (`actor_user_id`),
  KEY `audit_logs_entity_name_entity_id_index` (`entity_name`,`entity_id`),
  KEY `audit_logs_action_code_index` (`action_code`),
  KEY `audit_logs_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `school_id`, `actor_user_id`, `action_code`, `entity_name`, `entity_id`, `entity_label`, `details_json`, `changes_json`, `ip_address`, `created_at`) VALUES
(1, 1, 1, 'payment.created', 'Payment', '2', NULL, '{\"student_id\": 2, \"reference_code\": \"PAY-20260826-000001\", \"total_paid_amount\": 35000}', NULL, '127.0.0.1', '2026-08-26 18:42:22'),
(2, 1, 1, 'payment.created', 'Payment', '3', NULL, '{\"student_id\": 7, \"reference_code\": \"PAY-20260901-000001\", \"total_paid_amount\": 44000}', NULL, '127.0.0.1', '2026-09-01 08:03:13'),
(3, 1, 1, 'payment.created', 'Payment', '4', NULL, '{\"student_id\": 7, \"reference_code\": \"PAY-20260901-000002\", \"total_paid_amount\": 44000}', NULL, '127.0.0.1', '2026-09-01 08:03:42');

-- --------------------------------------------------------

--
-- Structure de la table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('sigs_admin_cache_user:1:permissions', 'a:19:{i:0;s:10:\"audit.view\";i:1;s:14:\"classes.manage\";i:2;s:14:\"dashboard.view\";i:3;s:13:\"debtors.print\";i:4;s:11:\"fees.manage\";i:5;s:12:\"finance.view\";i:6;s:15:\"payments.create\";i:7;s:15:\"payments.delete\";i:8;s:14:\"payments.print\";i:9;s:13:\"payments.view\";i:10;s:13:\"security.view\";i:11;s:15:\"settings.manage\";i:12;s:15:\"students.create\";i:13;s:15:\"students.delete\";i:14;s:15:\"students.update\";i:15;s:13:\"students.view\";i:16;s:15:\"teachers.manage\";i:17;s:15:\"tranches.manage\";i:18;s:12:\"users.manage\";}', 1788257009),
('sigs_admin_cache_user:2:permissions', 'a:6:{i:0;s:15:\"students.create\";i:1;s:14:\"classes.manage\";i:2;s:15:\"payments.create\";i:3;s:13:\"payments.view\";i:4;s:14:\"payments.print\";i:5;s:13:\"debtors.print\";}', 1787758672);

-- --------------------------------------------------------

--
-- Structure de la table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `cycle_id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tuition_amount` decimal(12,2) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `classes_code_unique` (`code`),
  KEY `classes_academic_year_id_foreign` (`academic_year_id`),
  KEY `classes_cycle_id_foreign` (`cycle_id`),
  KEY `classes_school_id_index` (`school_id`),
  KEY `classes_label_index` (`label`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `school_id`, `academic_year_id`, `cycle_id`, `code`, `label`, `tuition_amount`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, 'MPS', 'Maternelle Petite Section', '65000.00', NULL, 1, '2026-08-25 07:05:35', '2026-08-25 07:05:35'),
(2, 1, NULL, 2, 'CI', 'Cours d\'Initiation', '65000.00', NULL, 1, '2026-08-26 12:36:10', '2026-08-26 12:36:10'),
(3, 1, NULL, 2, 'CP', 'Cours Primaire', '68000.00', NULL, 1, '2026-08-26 12:36:53', '2026-08-26 12:36:53'),
(4, 1, NULL, 2, 'CE1', 'Cour Elementaire 1', '70000.00', NULL, 1, '2026-08-26 15:19:05', '2026-08-26 15:19:05'),
(5, 1, NULL, 2, 'CE2', 'Cour Elementaire 2', '72000.00', NULL, 1, '2026-08-26 15:19:40', '2026-08-26 15:19:40'),
(6, 1, NULL, 2, 'CM1', 'Cours Moyen 1', '72000.00', NULL, 1, '2026-08-26 15:20:09', '2026-08-26 15:20:09'),
(7, 1, NULL, 2, 'CM2', 'Cours Moyen 2', '73000.00', NULL, 1, '2026-08-26 15:20:56', '2026-08-26 15:20:56'),
(8, 1, NULL, 3, '6ème', '6ème', '75000.00', NULL, 1, '2026-08-26 15:21:31', '2026-08-26 15:21:31'),
(9, 1, NULL, 3, '5EME', '5ème', '75000.00', NULL, 1, '2026-09-01 06:32:42', '2026-09-01 06:32:42'),
(10, 1, NULL, 3, '4ème', '4ème', '78000.00', NULL, 1, '2026-09-01 06:34:12', '2026-09-01 06:34:12'),
(11, 1, NULL, 3, '3ème', '3ème', '85000.00', NULL, 1, '2026-09-01 06:34:47', '2026-09-01 06:34:47'),
(12, 1, NULL, 4, '2nde', '2nde', '88000.00', NULL, 1, '2026-09-01 06:35:17', '2026-09-01 06:35:17'),
(13, 1, NULL, 4, '1ère', '1ère', '90000.00', NULL, 1, '2026-09-01 06:35:58', '2026-09-01 06:35:58'),
(14, 1, NULL, 4, 'Tle', 'Terminale', '115000.00', NULL, 1, '2026-09-01 06:36:40', '2026-09-01 06:36:40');

-- --------------------------------------------------------

--
-- Structure de la table `class_schedules`
--

DROP TABLE IF EXISTS `class_schedules`;
CREATE TABLE IF NOT EXISTS `class_schedules` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `class_id` bigint UNSIGNED NOT NULL,
  `subject_id` bigint UNSIGNED NOT NULL,
  `teacher_assignment_id` bigint UNSIGNED NOT NULL,
  `day_of_week` tinyint UNSIGNED NOT NULL,
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `room` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_schedules_academic_year_id_foreign` (`academic_year_id`),
  KEY `class_schedules_subject_id_foreign` (`subject_id`),
  KEY `class_schedules_teacher_assignment_id_foreign` (`teacher_assignment_id`),
  KEY `class_schedules_class_id_day_of_week_index` (`class_id`,`day_of_week`),
  KEY `class_schedules_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `class_schedules`
--

INSERT INTO `class_schedules` (`id`, `school_id`, `academic_year_id`, `class_id`, `subject_id`, `teacher_assignment_id`, `day_of_week`, `starts_at`, `ends_at`, `room`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 7, 1, 1, 2, '08:00:00', '09:00:00', NULL, 1, '2026-08-27 11:47:55', '2026-08-27 11:47:55'),
(2, 1, NULL, 8, 1, 2, 1, '08:00:00', '09:00:00', NULL, 1, '2026-08-27 14:45:27', '2026-08-27 14:45:27'),
(3, 1, NULL, 8, 6, 3, 2, '08:02:00', '10:00:00', NULL, 1, '2026-08-30 17:57:44', '2026-08-30 17:57:44'),
(4, 1, NULL, 8, 6, 5, 3, '08:00:00', '10:00:00', NULL, 1, '2026-08-31 17:43:19', '2026-08-31 17:43:19'),
(5, 1, NULL, 13, 3, 6, 2, '10:00:00', '12:00:00', NULL, 1, '2026-09-01 06:52:25', '2026-09-01 06:52:25');

-- --------------------------------------------------------

--
-- Structure de la table `fee_types`
--

DROP TABLE IF EXISTS `fee_types`;
CREATE TABLE IF NOT EXISTS `fee_types` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_types_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fee_types`
--

INSERT INTO `fee_types` (`id`, `code`, `label`, `category`, `amount`, `is_active`, `is_mandatory`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Cantine', NULL, '10000.00', 1, 1, '2026-08-26 12:38:41', '2026-08-26 12:38:41'),
(3, NULL, 'TRAVAUX DIRIGES', NULL, '5000.00', 1, 1, '2026-08-26 15:40:29', '2026-08-26 15:40:29'),
(4, NULL, 'LACOSTE', NULL, '5000.00', 1, 0, '2026-08-26 15:41:43', '2026-08-26 15:41:43'),
(5, NULL, 'Sortie pédagogique', 'Sortie Culturelle', '6000.00', 1, 0, '2026-09-01 06:41:24', '2026-09-01 06:41:24');

-- --------------------------------------------------------

--
-- Structure de la table `fee_type_classes`
--

DROP TABLE IF EXISTS `fee_type_classes`;
CREATE TABLE IF NOT EXISTS `fee_type_classes` (
  `fee_type_id` bigint UNSIGNED NOT NULL,
  `class_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`fee_type_id`,`class_id`),
  KEY `fee_type_classes_class_id_foreign` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fee_type_classes`
--

INSERT INTO `fee_type_classes` (`fee_type_id`, `class_id`) VALUES
(1, 1),
(4, 1),
(5, 1),
(1, 2),
(4, 2),
(5, 2),
(1, 3),
(4, 3),
(5, 3),
(4, 4),
(5, 4),
(4, 5),
(5, 5),
(4, 6),
(5, 6),
(3, 7),
(4, 7),
(5, 7),
(4, 8),
(5, 8),
(5, 9),
(5, 10),
(5, 11),
(5, 12),
(5, 13),
(5, 14);

-- --------------------------------------------------------

--
-- Structure de la table `guardians`
--

DROP TABLE IF EXISTS `guardians`;
CREATE TABLE IF NOT EXISTS `guardians` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `relationship_label` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `guardians`
--

INSERT INTO `guardians` (`id`, `full_name`, `relationship_label`, `phone`, `address`, `created_at`) VALUES
(1, 'eyJpdiI6Ik5nYmNya0lNelVBQWRGeHN6WXRwemc9PSIsInZhbHVlIjoiUHRnVEJqbUxKdDFjaGZNZ1IzSFk3cWRNRzhaMWJDUks2S3hwSlRSRExCMD0iLCJtYWMiOiIwOWY5YTAxNjk4OTkyZmJkOTBmMTExZTAzZDgwYzc2YWJjOTI0NGEyYjhiNDNhZDQxN2MyZjI3N2ZlYWQ2MGI5IiwidGFnIjoiIn0=', 'Père', 'eyJpdiI6IkhDOExPVjZWM0NOUUxmWmVURjRGSXc9PSIsInZhbHVlIjoieEl6dnJWVFpOTWZJdi9kVVNCY1psUT09IiwibWFjIjoiY2Y2Y2FiZGY1MzEzOTI2ZjBlZTAyYTFhYTA0OThhN2Y4ZTkzZmI4MDZkZjVlZTFhYzc2NWIxZWIwYWIzZjcxMyIsInRhZyI6IiJ9', 'eyJpdiI6Im1nUDFFUGVFZzUzbDJvTTdTN3NKaHc9PSIsInZhbHVlIjoiN2xwUUZHNnhKeEEweWpJVDl5M0JDZz09IiwibWFjIjoiMWIzMTg5ZjU3NGZkM2RiYTBkZDM3OGNmZWQzYjRlMWRjZDliNTcxMTMxNjIyMDc2NzMwZTE0OTE1ZTZjOTkwYyIsInRhZyI6IiJ9', '2026-08-26 13:35:03'),
(2, 'eyJpdiI6ImJ1OFZ1T0xldHVsa1o0d0t2UmRCUkE9PSIsInZhbHVlIjoieHJ3Sk1sRk5YM0xscTRwY0ZjdG10K3JSNk9lVDVnYmsxOEp6eEVvV0p3OD0iLCJtYWMiOiIwMzc2ZDcwMWQ3YzRiZWQyMDA5Yzg0NTc2NmQ1YzI5NzZmZmQ2YTJmOGUxZjdmZjIyODIyNTA5YjBmNDhmNDViIiwidGFnIjoiIn0=', 'Père', 'eyJpdiI6ImJZWENpOTFLb2JKVDBYY3R3NGkvaEE9PSIsInZhbHVlIjoiZFAvRGl2QkE1MWlXK1FSamtscHprZz09IiwibWFjIjoiNzk1YWZkMzc1NjM3MTE5MGRmMTI3ODI1YWFjOTQ4MmE2MDQxMDUwMThkNmFiNjhkNTk1ODhmYmI2YmYyYjQ5MCIsInRhZyI6IiJ9', 'eyJpdiI6IlpWQWpOY1hVUlVTSzdjU1VobDNwWHc9PSIsInZhbHVlIjoid2ZkQ3M5QTJmWUFxam1ONDhpMmxNdz09IiwibWFjIjoiMDVkMzJkNWUyOTkxZmRiMDcxY2Q4YjkxYzcwNWJiNTYxNzI5YzQ2NjFkYmZkYmQ2ZDJjODYwOTVhNzMyMTgyYiIsInRhZyI6IiJ9', '2026-08-26 16:43:31'),
(3, 'eyJpdiI6IlVKMVRPa25iZGlXcDdHYnZuR2M4WWc9PSIsInZhbHVlIjoiWGowWnVObVRzSEJBN1FST1Rqak1yelowRVR2OUlkRHpiRFpPR1RJREt4TT0iLCJtYWMiOiI5MDIxM2FiNDc3MzI4NjFlZTY1YTNmMzkzYzI5YmFiMTk4OWQzNzU2MDBlZTFmOTdlYmM3NTA3YjNiOTlmOTM1IiwidGFnIjoiIn0=', 'Père', 'eyJpdiI6IkRMWTJmZHU1RnN6anNxeWkvenRTTUE9PSIsInZhbHVlIjoibHpJREFRZ2duQW5mcCtvSFFHTkZJUT09IiwibWFjIjoiODE2MDkwOWJlOWIxNDc1ZDg4NDA3ZDcxMmFhOGIyYzUxZWM3ZmZiZmI4MDM0Y2ZhZjE1ZTc4YTcxNWY0ZmM0NSIsInRhZyI6IiJ9', NULL, '2026-09-01 07:43:28'),
(4, 'eyJpdiI6IlFXbkhFTmUxS09pRHdoOGFPeklnMXc9PSIsInZhbHVlIjoiN2Mxai82T1FQUXo5Z2NVTTdHOU5wUT09IiwibWFjIjoiMmNjYzllNTc4YTI5M2Y2YTM2OTg1YTk2YzIwYzRiYzE0NjhmZDIyMGU4ZWQ3ZDFmNThiOWVmYzg3NGI1Zjk4NCIsInRhZyI6IiJ9', 'Père', 'eyJpdiI6IjJ5bmMwamh0bDl5Vlo3Q0Z0dE9FU2c9PSIsInZhbHVlIjoiWkpGajVkZ3BnQ1hZQmFMZXlDZ0ZFQT09IiwibWFjIjoiNzIzMTRjY2FlNDdlMjk3MTNhMTE0MjU5NzkxMWVkYWJmZmM0ZTExY2NhYzcwNjVmZjc5Yzg2OTQwZTFjZDYyMyIsInRhZyI6IiJ9', NULL, '2026-09-01 07:44:58'),
(5, 'eyJpdiI6IlBTV2NDQWppMm5WcXkyYkM4Z1NEdkE9PSIsInZhbHVlIjoiYmZTQWd3SXhEU1UxdkFVYkYzSUMyYnlDc1NIZHF3ZEttNDhJM0tNcjVLdz0iLCJtYWMiOiI0M2QyOWViN2E0OGNmYTNmNjBkOTQ0NzJmYzRhMDNmOWZhZjFkNzJjMjk1NDBmOTMxNGM5OWVkMjVmM2Y5M2IxIiwidGFnIjoiIn0=', 'Mère', 'eyJpdiI6IkFtTzFBTEdEMjRmdlQ1elovUFNNelE9PSIsInZhbHVlIjoiaFNqaTYxSmRnQjV0bFY0YmtWMk1wUT09IiwibWFjIjoiYTAxYzk0YTBmYTI4ZTBjM2E4NjEzYzc1YjVkOGEyZjBlZWJjZmM1NGRlZTUzMmFlODYxZDQ3ZTRmMTY1MGJkZSIsInRhZyI6IiJ9', NULL, '2026-09-01 07:47:35'),
(6, 'eyJpdiI6InpTU0kzRURkM3Ixc1YzQkxzV1RZQUE9PSIsInZhbHVlIjoicmJ5bzFoYS9CaVkrWGFUMk5Nb0Nwdz09IiwibWFjIjoiYzFmNTczNzlkOGU3ZjBlNDU4NTJkNmJmMWUxZDI2YTJmMDBhYWUzNDhkYzdlOTJhNWNiMzMzNDk2ZTM1MDBiZiIsInRhZyI6IiJ9', 'Père', 'eyJpdiI6IjhReHp4UTZXaFR4YmNEa242ZFM1eFE9PSIsInZhbHVlIjoiU1BSSXVnNFpUMTJwSUxjeEkwWTdrdz09IiwibWFjIjoiZjY3YTdiZmQzZGQ5NDNhNWFhNmFhM2Q3NGJjMTUwMTA4YTlmY2Y5NWNhYTBiMGNhNGFlNWFlZDJkMTlmNTUyNyIsInRhZyI6IiJ9', NULL, '2026-09-01 07:48:49'),
(7, 'eyJpdiI6Im8zZVI1VGNiblNsaXVnWmwxaTFVVkE9PSIsInZhbHVlIjoiL09qb21JK0ZCTW9zTlRsZFh0SWhrUT09IiwibWFjIjoiY2FlNGZlOTlmZmI5MDY5MjI5MWQ5NWY0NzEwMDZkZGRmMDc0YmJlNmE2NzFlZDkwOTZhMThjYzNmMmM3ZGVmMiIsInRhZyI6IiJ9', 'Père', 'eyJpdiI6InljUmhJcGs3UmhoZE4vOXJmd1RuSXc9PSIsInZhbHVlIjoiN2NsYVY3b0w1YmRQRExtNEVuREZvQT09IiwibWFjIjoiMTgzMmM3ODNkMGUxMmRmNWNlZGVlMmM3YzljZGMxOTk3OWYzZjk0ZjQxNTU2ODk0OTlmMmE4ZGMwMDJmYjI0YiIsInRhZyI6IiJ9', NULL, '2026-09-01 08:02:40');

-- --------------------------------------------------------

--
-- Structure de la table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2024_01_01_000001_create_roles_table', 1),
(2, '2024_01_01_000002_create_permissions_table', 1),
(3, '2024_01_01_000003_create_role_permissions_table', 1),
(4, '2024_01_01_000004_create_users_table', 1),
(5, '2024_01_01_000005_create_user_permissions_table', 1),
(6, '2024_01_01_000010_create_personal_access_tokens_table', 1),
(7, '2024_01_02_000001_create_academic_years_table', 1),
(8, '2024_01_02_000002_create_school_settings_table', 1),
(9, '2024_01_03_000001_create_school_cycles_table', 1),
(10, '2024_01_03_000002_create_classes_table', 1),
(11, '2024_01_04_000001_create_guardians_table', 1),
(12, '2024_01_04_000002_create_students_table', 1),
(13, '2024_01_05_000001_create_tuition_installments_table', 1),
(14, '2024_01_06_000001_create_fee_types_table', 1),
(15, '2024_01_06_000002_create_fee_type_classes_table', 1),
(16, '2024_01_07_000001_create_payments_table', 1),
(17, '2024_01_07_000002_create_payment_items_table', 1),
(18, '2024_01_08_000001_create_teachers_table', 1),
(19, '2024_01_09_000001_create_payroll_entries_table', 1),
(20, '2024_01_10_000001_create_audit_logs_table', 1),
(21, '2024_01_10_000002_create_sessions_web_table', 1),
(22, '2024_01_00_000001_create_cache_table', 2),
(23, '2026_08_26_000001_add_mandatory_to_fee_types_table', 3),
(24, '2026_08_26_000002_create_payment_sequences_table', 4),
(25, '2026_08_27_000001_create_subjects_table', 5),
(26, '2026_08_27_000001_rename_school_cycles', 5),
(27, '2026_08_27_000002_create_teacher_assignments_table', 5),
(28, '2026_08_27_000003_create_class_schedules_table', 5),
(29, '2026_08_27_000004_create_teaching_sessions_table', 5),
(30, '2026_08_27_000005_create_teacher_attendances_table', 5),
(31, '2026_08_27_000006_create_subject_seed_data', 5);

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

DROP TABLE IF EXISTS `payments`;
CREATE TABLE IF NOT EXISTS `payments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `reference_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `student_id` bigint UNSIGNED NOT NULL,
  `cashier_user_id` bigint UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `total_paid_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_reference_code_unique` (`reference_code`),
  KEY `payments_academic_year_id_foreign` (`academic_year_id`),
  KEY `payments_cashier_user_id_foreign` (`cashier_user_id`),
  KEY `payments_student_id_payment_date_index` (`student_id`,`payment_date`),
  KEY `payments_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payments`
--

INSERT INTO `payments` (`id`, `school_id`, `academic_year_id`, `reference_code`, `student_id`, `cashier_user_id`, `payment_date`, `total_paid_amount`, `created_at`, `deleted_at`) VALUES
(1, 1, NULL, 'PAY-20260826-941213', 1, 1, '2026-08-26', '40000.00', '2026-08-26 14:44:54', NULL),
(2, 1, NULL, 'PAY-20260826-000001', 2, 1, '2026-08-26', '35000.00', '2026-08-26 18:42:22', NULL),
(3, 1, NULL, 'PAY-20260901-000001', 7, 1, '2026-09-01', '44000.00', '2026-09-01 08:03:13', NULL),
(4, 1, NULL, 'PAY-20260901-000002', 7, 1, '2026-09-01', '44000.00', '2026-09-01 08:03:42', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `payment_items`
--

DROP TABLE IF EXISTS `payment_items`;
CREATE TABLE IF NOT EXISTS `payment_items` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` bigint UNSIGNED NOT NULL,
  `item_type` enum('TRANCHE','AUTRE_FRAIS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tuition_installment_id` bigint UNSIGNED DEFAULT NULL,
  `fee_type_id` bigint UNSIGNED DEFAULT NULL,
  `expected_amount` decimal(12,2) NOT NULL,
  `paid_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `payment_items_payment_id_foreign` (`payment_id`),
  KEY `payment_items_tuition_installment_id_foreign` (`tuition_installment_id`),
  KEY `payment_items_fee_type_id_foreign` (`fee_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payment_items`
--

INSERT INTO `payment_items` (`id`, `payment_id`, `item_type`, `tuition_installment_id`, `fee_type_id`, `expected_amount`, `paid_amount`, `created_at`) VALUES
(1, 1, 'TRANCHE', 1, NULL, '30000.00', '30000.00', '2026-08-26 14:44:54'),
(2, 1, 'AUTRE_FRAIS', NULL, 1, '10000.00', '10000.00', '2026-08-26 14:44:54'),
(3, 2, 'TRANCHE', 3, NULL, '35000.00', '35000.00', '2026-08-26 18:42:22'),
(4, 3, 'TRANCHE', 5, NULL, '44000.00', '44000.00', '2026-09-01 08:03:13'),
(5, 4, 'TRANCHE', 7, NULL, '22000.00', '22000.00', '2026-09-01 08:03:42'),
(6, 4, 'TRANCHE', 8, NULL, '22000.00', '22000.00', '2026-09-01 08:03:42');

-- --------------------------------------------------------

--
-- Structure de la table `payment_sequences`
--

DROP TABLE IF EXISTS `payment_sequences`;
CREATE TABLE IF NOT EXISTS `payment_sequences` (
  `sequence_date` date NOT NULL,
  `last_number` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`sequence_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payment_sequences`
--

INSERT INTO `payment_sequences` (`sequence_date`, `last_number`) VALUES
('2026-08-26', 1),
('2026-09-01', 2);

-- --------------------------------------------------------

--
-- Structure de la table `payroll_entries`
--

DROP TABLE IF EXISTS `payroll_entries`;
CREATE TABLE IF NOT EXISTS `payroll_entries` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint UNSIGNED NOT NULL,
  `period` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_amount` decimal(12,2) NOT NULL,
  `bonus_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deduction_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_entries_teacher_id_period_unique` (`teacher_id`,`period`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `payroll_entries`
--

INSERT INTO `payroll_entries` (`id`, `teacher_id`, `period`, `base_amount`, `bonus_amount`, `deduction_amount`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 1, '2026-09', '65000.00', '2000.00', '0.00', 'paid', '2026-08-26', '2026-08-26 13:59:23', '2026-08-26 13:59:34'),
(3, 4, '2026-09', '0.00', '0.00', '0.00', 'pending', NULL, '2026-09-01 06:55:26', '2026-09-01 06:55:26'),
(4, 2, '2026-09', '4916.67', '0.00', '0.00', 'pending', NULL, '2026-09-01 06:56:15', '2026-09-01 06:56:15');

-- --------------------------------------------------------

--
-- Structure de la table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `label`, `created_at`) VALUES
(1, 'dashboard.view', 'Voir le dashboard', '2026-08-25 06:50:47'),
(2, 'students.create', 'Inscrire un élève', '2026-08-25 06:50:47'),
(3, 'students.update', 'Modifier un élève', '2026-08-25 06:50:47'),
(4, 'students.delete', 'Supprimer / archiver un élève', '2026-08-25 06:50:47'),
(5, 'students.view', 'Consulter la fiche élève', '2026-08-25 06:50:47'),
(6, 'classes.manage', 'Gérer les classes et scolarités', '2026-08-25 06:50:47'),
(7, 'tranches.manage', 'Gérer les tranches', '2026-08-25 06:50:47'),
(8, 'fees.manage', 'Gérer les autres frais', '2026-08-25 06:50:47'),
(9, 'payments.create', 'Encaisser un paiement', '2026-08-25 06:50:47'),
(10, 'payments.view', 'Consulter les paiements', '2026-08-25 06:50:47'),
(11, 'payments.delete', 'Supprimer un paiement', '2026-08-25 06:50:47'),
(12, 'payments.print', 'Imprimer un reçu', '2026-08-25 06:50:47'),
(13, 'debtors.print', 'Liste et impression des débiteurs', '2026-08-25 06:50:47'),
(14, 'teachers.manage', 'Gérer enseignants et paie', '2026-08-25 06:50:47'),
(15, 'finance.view', 'Consulter les finances', '2026-08-25 06:50:47'),
(16, 'users.manage', 'Gérer utilisateurs et droits', '2026-08-25 06:50:47'),
(17, 'audit.view', 'Consulter le journal d\'audit', '2026-08-25 06:50:47'),
(18, 'settings.manage', 'Paramètres établissement', '2026-08-25 06:50:47'),
(19, 'security.view', 'Voir la politique de sécurité', '2026-08-25 06:50:47');

-- --------------------------------------------------------

--
-- Structure de la table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `code`, `label`, `created_at`) VALUES
(1, 'admin', 'Administrateur', '2026-08-25 06:50:47'),
(2, 'secretary', 'Secrétaire', '2026-08-25 06:50:47'),
(3, 'cashier', 'Caissier', '2026-08-25 06:50:47'),
(4, 'accountant', 'Comptable', '2026-08-25 06:50:47');

-- --------------------------------------------------------

--
-- Structure de la table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19);

-- --------------------------------------------------------

--
-- Structure de la table `school_cycles`
--

DROP TABLE IF EXISTS `school_cycles`;
CREATE TABLE IF NOT EXISTS `school_cycles` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_cycles_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `school_cycles`
--

INSERT INTO `school_cycles` (`id`, `code`, `label`, `sort_order`) VALUES
(1, 'maternelle', 'Maternelle', 1),
(2, 'primaire', 'Primaire', 2),
(3, 'cycle_1', 'Collège 1er cycle', 3),
(4, 'cycle_2', 'Collège 2ème cycle', 4);

-- --------------------------------------------------------

--
-- Structure de la table `school_settings`
--

DROP TABLE IF EXISTS `school_settings`;
CREATE TABLE IF NOT EXISTS `school_settings` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `setting_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_settings_school_id_setting_key_unique` (`school_id`,`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `school_settings`
--

INSERT INTO `school_settings` (`id`, `school_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 1, 'school_name', 'Mon École', '2026-08-25 05:50:48', '2026-08-25 05:50:48'),
(2, 1, 'matricule_prefix', 'ELV', '2026-08-25 05:50:48', '2026-08-25 05:50:48'),
(3, 1, 'currency', 'XOF', '2026-08-25 05:50:48', '2026-08-25 05:50:48'),
(4, 1, 'letterhead_path', 'uploads/letterheads/letterhead-6a8f037cb31b05.24966482.jpg', '2026-08-26 14:17:04', '2026-08-26 14:17:16');

-- --------------------------------------------------------

--
-- Structure de la table `sessions_web`
--

DROP TABLE IF EXISTS `sessions_web`;
CREATE TABLE IF NOT EXISTS `sessions_web` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_web_user_id_index` (`user_id`),
  KEY `sessions_web_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `sessions_web`
--

INSERT INTO `sessions_web` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('7ciIaFPXVrjoQqqKfFVaJUOQMaO7yx2RoizmO3Xc', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'ZXlKcGRpSTZJbXM1UWtWSU5IWXpVa3hWUVZWS1JISXdjbmhDTTJjOVBTSXNJblpoYkhWbElqb2lSekJXYm14U2JqQlFXVFpTVmpWeFIwRXlla3RDVFdGRmRYSm5iakJ5ZHpWR1NGQXlTWFFyZEZsaFJXTllNMnd2WW1Fd1RFczBaMFZWUnpKSWFWQjFhbWR2UVdwbFlpdENXVWc0TkZOWVpXcE1OV1V3VVRSbGVtSjBORVJLZURWTlFYbGhUMGxsVGtoSFJYbFVRMkV3WXpGNmRWRjVXSFF5YWtkSFpYRXZMM1JMYVd0cVNtbzFVMEp2U0c4eVJFaHNlbXBDY1VaTWVGbDFSRFkzV1hOdlNtTlVVRlpPV2xkSmJuVkxSbXd2TkZSRlNIUnViMDVLVmpKUFptNVNRMnAzVDBKQ09IQnVLMHh0ZEVsR1JqZEdWbFJpY1dZelpGUk5RbTgxYzBsRlpGRldjbVU1TnpaRFprSmtUVEZCTTJsdVZEbE5jMVp2Y1dsRldsZ3ZjMFE1SzBsa1NtTk5VSFpIVDFORFNXUk1hR1J4YVcxeWFUVnVOMHR5YkdjMmNUWjRUQ3RKUWpKeFptYzJhRGxLUlM5UldIbFljRU00YjA0NFlWb3dUMDB3Y1RGdmVUSnBWa2hqTkhaTlFuSjZZa2xrU1hrMWRFaE5WRTlXTkhKR1prUTVlVTVNUTFsdWJFZEhiSGxwUTFsb1VUWTRLMlZxTmpobVozUkZNSGxqWlZZeFoxTlNRbVJTWkUxaEwzTkhVVTVRUkZOUFdqRkxkMjFRVlM5Qk9IaHJTR3RpV0haaFVVeEpaRkI0VFd4S09XOVplVUZKYUVNNFEwOW9SMGQwZDJVNGNYQnlTQ3M0VW5CM1JUTk9lbWRoVDAxaksycGlUVkU5UFNJc0ltMWhZeUk2SWprd09UUTFPVE01T1RGaFptUmxNV0UxTVRjM09UWmtZbUl4T1dGak1HTmtNemsxWVRNNE5qTTJNek0zTWpFME16RTBOemcyTldWaE16UmhNMk15TURZaUxDSjBZV2NpT2lJaWZRPT0=', 1788256949),
('AZy7D5B9Qg844GXIzkzskBhBgfCNlLi5t2cOTqC9', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'ZXlKcGRpSTZJbXBHU1VkVWNXOXJiR1ZyVWt0WVFWcGtkSEZVWm5jOVBTSXNJblpoYkhWbElqb2lkakZVTDFKVFpVTnZjM000WXpkRFJtNVNRazVxU0djMlJqTTFSa014V1UxaVZURTRPR2d6ZVRWYWEzZDVUQ3MxU0UxRk9WWjJkQ3QyZVVVNVVFNVpkMm80UnpCR1pWWkJXVGxDTVRGU2NFZERhR05YTVdkamFtWmhXVm94U0hVeldIaGxRa0oxTVdsWFJsUnpUakpJY0RablVXWjVWWHBPVFdweU4wVnNVSGMwYzFFNGFWcGlPV2hhUTNKVWEwOUtSbGRYWkdGQ1ZrTlZkRmhUVFdOcFFYbDNXV1FyUmpGSVMyOXBjREZaTDBGcVZYRnZTSE50V0ZOblNrcG5NMXBhT0d4a1Zrb3lRek5TU1hwelNtRXpMM2xJUXpWNFFsRmpiV1k1VmxOWFprUm1WM2d2TXpVMGFWSjJlSGx4VUROYU1TOW1UV1kxZVhKV1kxWXpaRUZ0Um1sUFdtZDJWbEp0UzIxT05XZDRUVVZJYzIxTWJEQjFaMU5GVkVKa05tWnFjbmxvT0ZKVlNGUkhha1ZtUmpSUkszbFdZa3ROVDJwVGIwbGpiWGREVjJKU1JucEpOM2hGVkc5MU1rTmlVMFUwV21NMVUwdDBPVFZxU0V0UFVqUkpVbnB3YWtNemJIZEhUSGRXVEcwME1GSk9jMGx3VVVoSlZtMDRXa1UzVnpkUlEzQk9kMWxTVVdwU1ltOVVRVzQyVTJkNVQzTk1iM0J0UkdodVZHSlRiSEU1ZFVobE0yNHZZV05VV1d3eFlqaDNORzF4U201a1J6WTNkM0YyVkc1VE9YRXJSRkJUYkhoeU1FVkhkVzF2TDBabU5YTkRkRUU5UFNJc0ltMWhZeUk2SWpKbU1UQXlZMk00TWpVME1qQXlZekU0T1dRMlkyVm1NakZpTWpOa05UQmxNV0ZqT1dSa1ptWmlNbVV4WWpFMlpEQTNPV1UwWW1ZM056QXlORGN5WldRaUxDSjBZV2NpT2lJaWZRPT0=', 1788253393),
('fMJh1ScFqiLOoXej7FK5rW4emWWIxTvG4OWd0bKh', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'ZXlKcGRpSTZJa2x0V0dGRWJsaHNTa05UTUZZMlozVk5TMmRtZGtFOVBTSXNJblpoYkhWbElqb2lPRUZYVVRSM2VUVk1NVlpzTVdsTFN6UjBiRFJHWW5BME1tcHNXRVZVZVdadU0zVlRPWEptWVZNeFQyUjROa2hUUlZSMWFrOVFjMlY0T1dsMVptVm9SVll3UTNwM1NXRXZSbk5xTWtKUFoxRjVZVWhtVjB4RlJXbFNja04xUTFsT1ptdGhlVmxsTW5jeFQzaFlWRkkxVW1sQ2FFTnRlR2RXYjJNMmFrUnBOR05GVVZvMk1qWXZVR2xVWjJOdmNHWkhUMk0xZUhwUlpYZzNWRGRYVW10Q2JTOXVXVGhDTnpab2VFVmpObVY0VTA5MGMyWlhhamgwVURjd2NtWjFSbEJRT1ZWemF6RkpWVmhFVFhGVUx6UXpSRGxuVEdGRVNrSlljMUV5U0hoeFVEQTJOeXQ2UldKUk0waHFka1ZaWVcxeGMxRjZNVE5RZVd4bVNqZEhhVU41TUhNdlZXNU5VV3RzU0ROTmNWSnZWREJ1Y201d01YTlRaMUpPUkVWSWJISndZVVZaTkdreFNXcDRWMjFQTDJWRGJuWk5TRlZQUjNseU4zcGFiR2RrT0VSVVRGRnFhekowVldoTFRsYzNURmhVYmpSbE55dHZWMk4wVEdWdVREUlFSRk5PTTNscU1XbFRNM3BzWmxwVldFUjVNRkZPU0dWblNrOXpRalZDZEZSRFoxRlVZV3MyZUcxVGJrMDJOVlJXYXpsdU1ESk9XVE5CWWxRd1IyNHdaMU5YYVRCWWJucDZVakUwU0hoTVNtZG1lRU5oT1RodU9XZFJPVWhySzNGeU9UVlNhbTVSZHpsR2JXOWFZbnBWTkNzM2FYZGhWV2M5UFNJc0ltMWhZeUk2SW1SaU1HSTROV016T1RsaE1qaG1OVEUwTmpGak1tVXdPR0UxTnpJNVpqZGxaVFkyWXpKaE56azRNRGhsWVRrelpHWmpZV1k1TWpsbE1USTNNRGc1WWpRaUxDSjBZV2NpT2lJaWZRPT0=', 1788256949),
('hjOUwvx09i04HPFxlvfEUKdeGPkY9qquGXKyn8HQ', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'ZXlKcGRpSTZJbFpTUVM5YUt5OVJlSFJhTjJaMk1UTTJTVEFyZFVFOVBTSXNJblpoYkhWbElqb2lNMDFKUW10blVIVXJRVXRZVFhaaVdFTnBZU3RrTW5sMVVtMXROVFZ2VTJoNGRVaFJjQ3REVUc5RmFVcG1XRE54WjFKS05sTXZhRkZwV1hBNU1YUm9jU3RYV1d4b0sxcHhZWEl3VFZVdmFXWkVjblZRZUdwcFdERk9hMncyY1hWSVRuWllWMDVZVGpkdGVWZFFSamRJVms5WVdFNDJiaXRXYW1oVFZ6VnNNUzlJWXpKMGFXRlpLMWxSYjFGSVl6QTFWMFJXVVZGNloxTlVNR28yTVhaUmJuQk1ZbTFrWTJaWFNHUnpUMnBzT0ZOc1ZHRlhSWE56VTFOaVlVNTBaRWs1TkV0SVEySXdMMDluU1hVMGRrTnBjWEJLU1VrdmJIZGthRUZCTkhob2JFeFdSMDlKVWxOaFNFdDBUR1JYYzI1SlYzcDRURzEwTUN0cVdXSnpUa2QwWjNwWFRHOWpiRFJ5YUhKRmVHd3JUbXgzTkRGYVRFdHBUbE5zT1hZNFV6TjRVVEY1YXpoeFNYVnBLek5RZFZrd1NFWnhPV3BvSzNrNVp5dEdiMW8yUVhOaGQwc3dOemhxVjNGSVNUa3ZRbmxGYW10elRuZHJTSFUyV1dSQk5VTkhValJWYmxkcVRrMTJhV3RwT0RaUmMxSXdZbXhFZFdWUWVuSnpkVTFCY3pORFMxb3dUeTlJY1VOM1IzWnNSVGxNYkhWSWJERlZLMFpDYVdSRFpHRjRlSGxvVDNoclZscFVaRmh1VkZScmJUWkxXQ3MxV0hsTlpISldWMkpIU0V0NFNGRmpZM2RSWjJGVUswOXNZMk5HTVZNMFJsSnhSR3A2UTBzd1lrTlpWWHBHZUV4Mk5VbFFhak0yVVZWSmRVOTVTVmwxZVU1WFNFbEhUVnBMWW5wVVMwc2lMQ0p0WVdNaU9pSmhOelprWkRJM1lqWXpNemxrWWpRM04yUTNOakJtTlRFMk1tTmpPRGMxWkRrME1HUXdNVEZsWTJSall6VTNOemN3Wmpaak56bGtaamM1TXpsbU5XRXhJaXdpZEdGbklqb2lJbjA9', 1788251890),
('MgR6mqcRijq169O5uNmrqSwEFUOj7583di8zBmeT', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', 'ZXlKcGRpSTZJbmhDYm1SeFFtbDFTV28xWkVOUU5tdElURU5QWkdjOVBTSXNJblpoYkhWbElqb2ljV2RaUW5sWGJERlZNMkpCVW5oRWFrZDVVMWhQYkRoMlpVTkJSamhFVlRkeWFEUjBSU3R2YUcxaFowMHdhVkZvVW5OcGFWcHVjRkZ3TlU5dFRYVlpiM1l6ZUhKVGJVeG9lR05OYzB0bmVqTXdWRFIyUkhobVRXZENXVEZqZEhJemREaEhXRXRhYUhnMlR6aHVhamR5TDJOT1RHUklOWE51U1ZOS05FZGlaelZwVTJOSUwxSmtXalowY25KeU5uRXhWbVpEVWpoME9UQnlRalpXU0Vsb2MzaDZhR3N2YW5VM1dsTm1aVGhVVGxaUWNreDNXak5hUTAxUVExaG5TRVp4UVhGaWRYcGhUVFkxSzB0V1VqUnROSE5QVFVGVGNWUmhURkEzWTBScGFHdFZMMUJEUW1GelVYRlhZV0pKUVRObldGTkZRVzlOV1dwTmFraHNjM28yWkRSRlZERTFNRXRUY1ZaNVUzTlRja3B4TldwQ09WQlVNVGhsYjNWVEwxQmpTRFEzYW05V2RsSnFjM2x2WTNsU1ZYZEljbFYyUVZoQ1JrMVpjRTVRWVZsM09XMUtaRnB4TWxSMlMyZExUMVZsU1c0dlVtRm9TbEpMV2tveFowcEdNa3R3VlUwM1RVZ3dRVk5JTkZjdkwyRkxNV0pRYUhkU1FWcHpOMEk0TVZOdlJVUmhjMmxWU2pOMVZsa3lSVmhIU0hJM1kwcEdVRzlEUkVnemFFcHRWRzFsWmxNNE9HeHpWVXRuVGxGTWJITnBWMmg2VW5sMlYyeFZlRlpFU25vclpVNW9hM1pEUkdkTmFtcGtNVUZaVjJreFdFVkpaVUU5UFNJc0ltMWhZeUk2SW1JMk5Ua3dPV0ZoWkRVellqQXpZVEUwT1daa05HTmhNamxsWW1VMk1UQXlOekF4TmpZME9EZGpaV1F4WldabFpUZGpaalE1TTJNMVkyTmpZalk0TmpBaUxDSjBZV2NpT2lJaWZRPT0=', 1788253393);

-- --------------------------------------------------------

--
-- Structure de la table `students`
--

DROP TABLE IF EXISTS `students`;
CREATE TABLE IF NOT EXISTS `students` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `class_id` bigint UNSIGNED NOT NULL,
  `guardian_id` bigint UNSIGNED NOT NULL,
  `registration_year` smallint UNSIGNED NOT NULL,
  `registration_sequence` int UNSIGNED NOT NULL,
  `matricule` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('F','M') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','transferred','graduated','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_registration_seq` (`registration_year`,`registration_sequence`),
  UNIQUE KEY `students_matricule_unique` (`matricule`),
  KEY `students_academic_year_id_foreign` (`academic_year_id`),
  KEY `students_class_id_foreign` (`class_id`),
  KEY `students_guardian_id_foreign` (`guardian_id`),
  KEY `students_status_index` (`status`),
  KEY `students_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `students`
--

INSERT INTO `students` (`id`, `school_id`, `academic_year_id`, `class_id`, `guardian_id`, `registration_year`, `registration_sequence`, `matricule`, `first_name`, `last_name`, `birth_date`, `gender`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, 1, 2026, 1, 'ELV-2026-000001', 'eyJpdiI6IlIxZnFHQy90Z2FJWlo1eVllWnFVOFE9PSIsInZhbHVlIjoiV1oyQUdteWlXWmdiQlQzREdab1I0UT09IiwibWFjIjoiY2Y2YmRmNzdkMWQ4NGE5NThhMTVlYWVjMDBiNTk1MDg4YmU0MTU2ZDM5Y2JhZmFiMzZiOGE4ZDg4MWRkMzU4YyIsInRhZyI6IiJ9', 'eyJpdiI6IkpLNzVNaTFJNFltbmNnbVNlSk5lUnc9PSIsInZhbHVlIjoibHpxaHQ2UFFxd3VGVnU1WW92Vkp1Zz09IiwibWFjIjoiOTNjYzI3MTkxN2U0ZGMzMDA0MDY1YWNkMWQ5MmY3NmFkZWRjMjEzYWIxMmQ5ZTg5NjdjMzM4ZDEyYjEzNDg0ZCIsInRhZyI6IiJ9', '2023-01-27', 'M', 'active', '2026-08-26 12:35:03', '2026-08-26 12:35:03'),
(2, 1, NULL, 6, 2, 2026, 2, 'ELV-2026-000002', 'eyJpdiI6Iml2TnJmYXEwSlh5N0xYWjJ6cmRGNXc9PSIsInZhbHVlIjoiQlpNY0tOQzhzdU1JOTR6NEZoTGI1QT09IiwibWFjIjoiZGRmMzFmYWQ5M2EzM2Q4NzZmMDMxMzIyZDRiMmZjNTI1OTRkNmU0ODAzYjRiM2E0OGYyNDM0MmNkZWU0MTUwNyIsInRhZyI6IiJ9', 'eyJpdiI6IklNL2syYjg5SUhZaVdHYlhPcHRHZ1E9PSIsInZhbHVlIjoiUElOditaS21YbEtIaW9jVnNUZUNJQT09IiwibWFjIjoiYTk3OGQwZGVmNjU0NTBiOGVjZTliZDBhZmUyNzA0ZTJkODkwN2JkNjM0NjNjZDQ0NTRlYmZhNGVkZGZmNzRkYiIsInRhZyI6IiJ9', '2018-02-08', 'M', 'active', '2026-08-26 15:43:31', '2026-08-26 15:43:31'),
(3, 1, NULL, 14, 3, 2026, 3, 'ELV-2026-000003', 'eyJpdiI6ImRsQTFsY1dFbHN2UTJWdVJvcDM1T0E9PSIsInZhbHVlIjoiOGRPTE01TFJKMDZrdUNSYXhxZFRKdz09IiwibWFjIjoiNWViZmE3YmZlNzE2MDU2ZjMyM2E0YTgxYWZkMmFmOTI3ZTliNGVkYTU3OWFhMGZmZDZlNzNjY2RlODZjNjk3NiIsInRhZyI6IiJ9', 'eyJpdiI6IjRrSnQrWmpSQmFpUXd2NStQK2diYlE9PSIsInZhbHVlIjoiL2Z5b2VnSlpxQ01WRkhYWHRjc3V1QT09IiwibWFjIjoiNDg4ZWM4MjAyMGYzMmIwNjRlNmQ5M2NkMmNmYjU4YzdjOTgzMjgwNDA5ODlhMjM2NWUyYmQ0MWNmNTUzMzIzMCIsInRhZyI6IiJ9', '2006-06-01', 'M', 'active', '2026-09-01 06:43:28', '2026-09-01 06:43:28'),
(4, 1, NULL, 9, 4, 2026, 4, 'ELV-2026-000004', 'eyJpdiI6IkJyRkVhNUZJdklwWGlrTTRzeGhwV2c9PSIsInZhbHVlIjoiQ3B3UnhBdFVBclFKRlNRL1lRUDJEUT09IiwibWFjIjoiNTIxNmE1ZTU1NDA3ZmQyZGZkMWQwOTA4NWFjZmM0ZTU1NWU5YTA5MTVlM2I3YTZiZDQ4NDJiZWYzNTIzMzg1MyIsInRhZyI6IiJ9', 'eyJpdiI6Ii9YczRmZVpMcjlyN2NWdlAxc2cxc0E9PSIsInZhbHVlIjoiVTh2V3ZOTjh1L3VPWU5IT0VRNTMwZz09IiwibWFjIjoiN2NjMmM1ZTVkMDcyMzgzODI1MWU3YjhhYzExNmNmMjYyMDEzYjVlZmU3OTVlMjM1YzdjNDg5NDY0ZmU3NjcyNCIsInRhZyI6IiJ9', '2014-10-01', 'M', 'active', '2026-09-01 06:44:58', '2026-09-01 06:44:58'),
(5, 1, NULL, 10, 5, 2026, 5, 'ELV-2026-000005', 'eyJpdiI6IkpLaGlPZE5VLzRVSFl4Mlh3OGNGTHc9PSIsInZhbHVlIjoiZEh1NEl3TUdNSFYxTUZuTnVqK3ZPdz09IiwibWFjIjoiNzY1MTQzZWVjNWJhYWRjNjdhOTljZWFjY2FjYTQ1MzYwMjdmOWQ1ZDdjYWZiZjRkZmQ3Mjc2YzA5ZTRmYzkwMCIsInRhZyI6IiJ9', 'eyJpdiI6IjBaOVdVR2hUcXNkUlFIS0ZaK3Izc0E9PSIsInZhbHVlIjoiT3VNN1pQUk1Qd0dyTjBNYmZlVUk2dz09IiwibWFjIjoiMTg2OTRhOGFiNjRlNzkwMWIwYjRlYzFiMWZmMTJiMjU4YjA5Y2RkNDUwM2NhNTYyOTk2YzA3ZTRkNmQ4ZTI4MiIsInRhZyI6IiJ9', '2014-06-21', 'F', 'active', '2026-09-01 06:47:35', '2026-09-01 06:47:35'),
(6, 1, NULL, 13, 6, 2026, 6, 'ELV-2026-000006', 'eyJpdiI6IklxVlhoOHdZdnNyRHgwQVJOdlVMY1E9PSIsInZhbHVlIjoiNDNLc3FoVklaZm1TVXdyZGZYWUp6QT09IiwibWFjIjoiYWY4MmZiZmJjYTU2ZWExYTU4OGYxZmI2ZjA5ZjY2OWYwZDk5NTgyOWU4MmZkOWVlZGMyNWVhNmYwZDdjMTAzMSIsInRhZyI6IiJ9', 'eyJpdiI6InhtWUVOUklzUnFlbkRyY25kc0ZoY2c9PSIsInZhbHVlIjoicXlXZzNlQ1gyTTFFY0dZME0zaUcxZz09IiwibWFjIjoiNDQwNDI5OGQwZGMyNDc4ZTYxMDc2Mzg1MDBlNGI3YTkwMGY5ODI5NzczNzM1ZmQ1Y2MwMTQxMmMzYjM1MjE5YSIsInRhZyI6IiJ9', '2015-07-01', 'F', 'active', '2026-09-01 06:48:49', '2026-09-01 06:48:49'),
(7, 1, NULL, 12, 7, 2026, 7, 'ELV-2026-000007', 'eyJpdiI6Im5lRjJmbWpWUVBQQzFENUFNQlZxSUE9PSIsInZhbHVlIjoiejlaOGg5TWxIam44OEFsVGNINlo0UT09IiwibWFjIjoiODBmZDMwNjAxMDYyMGU2ODcyYWYxYzdlMTY4YWUwMzk3MzY5MmI1MDY1YWEwODJlNWNjMTI4ZDBhMzMxZGVkNiIsInRhZyI6IiJ9', 'eyJpdiI6IjE2aUJGS255aHNOMzFSaFpuaWtjbkE9PSIsInZhbHVlIjoiRTh5NS9oUVdnbXFVL3psNzlQU1Q0dz09IiwibWFjIjoiZDBiZTg3N2Q1ODQ0ZTZmMDYyZmVjMjUwNzM4ZjA1NGE2YWY4YjU0YjIzN2ZkNjFiN2M1NmRmZjViODM1MTZmYyIsInRhZyI6IiJ9', '2017-06-01', 'M', 'active', '2026-09-01 07:02:40', '2026-09-01 07:02:40');

-- --------------------------------------------------------

--
-- Structure de la table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subjects_school_id_code_unique` (`school_id`,`code`),
  KEY `subjects_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `subjects`
--

INSERT INTO `subjects` (`id`, `school_id`, `code`, `label`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'francais', 'Français', 1, '2026-08-27 10:29:50', '2026-08-27 10:29:50'),
(2, 1, 'mathematiques', 'Mathématiques', 1, '2026-08-27 10:29:50', '2026-08-27 10:29:50'),
(3, 1, 'anglais', 'Anglais', 1, '2026-08-27 10:29:50', '2026-08-27 10:29:50'),
(4, 1, 'sciences', 'Sciences', 0, '2026-08-27 10:29:50', '2026-08-31 17:39:26'),
(5, 1, 'ESPAG', 'Espagnol', 1, '2026-08-27 14:44:20', '2026-08-27 14:44:20'),
(6, 1, 'BIO', 'Biologie', 1, '2026-08-30 17:56:03', '2026-08-30 17:56:03');

-- --------------------------------------------------------

--
-- Structure de la table `teachers`
--

DROP TABLE IF EXISTS `teachers`;
CREATE TABLE IF NOT EXISTS `teachers` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `full_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` text COLLATE utf8mb4_unicode_ci,
  `subject` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_salary` decimal(12,2) DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teachers_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `teachers`
--

INSERT INTO `teachers` (`id`, `school_id`, `full_name`, `phone`, `subject`, `monthly_salary`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'eyJpdiI6InptUzM4bXYxSFdjWjBZVC8wd0NHUVE9PSIsInZhbHVlIjoiOXZEL1liYTlLRDdrSjZUZGZKU212UT09IiwibWFjIjoiMTc2Zjk1YThmYTMxYTM4MDdjY2YwN2YyYzQ2MTEwMDM4ZjA0NjZhYmRiNzQ5ZTA1NjBkNTZlYjgxMDE2YTE1OSIsInRhZyI6IiJ9', 'eyJpdiI6IjRjRExDSnhJN25yaU12YlNMUE05d1E9PSIsInZhbHVlIjoibHRLMHVoUStlbWYyc05vU0ZTYTdiUT09IiwibWFjIjoiMGJlMGVjNjI1OGFmZjRlMjdjZmY0YWFjMjU5ODk5NGNiMDNkZjlkMTM1MzI3ODdjMjhiNTdiZTgwMWE3YWEwOCIsInRhZyI6IiJ9', 'Mathématiques', '75000.00', 'active', '2026-08-26 13:58:50', '2026-08-26 13:58:50'),
(2, 1, 'eyJpdiI6ImQxb0d6b1VOMklTVFRHTEFraE9rM0E9PSIsInZhbHVlIjoiaWpuWU84bzNIYVlWeUJ1ZHlmUldRdz09IiwibWFjIjoiN2I1OWFkNTNlMmY0N2YxOWY5MjE1YzRjM2QwNjhhMWRiNWY4MmU2YTc0MzhiYzc1ZWRkZjNiYjI0ZDU5M2FjNCIsInRhZyI6IiJ9', 'eyJpdiI6Ik9WcWQrbkJ3eHZ6N3U5aG10ZWgzcWc9PSIsInZhbHVlIjoiVW5ITC9mVGRrL1h1TFZrREUwcnVTUT09IiwibWFjIjoiNDIyMjBlNmY4MDljYjJmNjkxMDc4ZWMzYjI0NDhiYjEyZGU5N2Y1NDU0NmVhNDE3MWIyNzAwZjlmZjVjODQ1NCIsInRhZyI6IiJ9', 'Biologie', '77.00', 'active', '2026-08-30 17:55:30', '2026-08-30 17:55:30'),
(3, 1, 'eyJpdiI6IjNjZk9BN285eGlyWGF6SHhLUFlNYVE9PSIsInZhbHVlIjoiQWdBWEZxNHJuNmQ4WXRWMHE3MDZUdz09IiwibWFjIjoiMzgxOWE5M2I4MmNiZjE2MTUzMzFhMTJkZjE5Mzg5MTE1ZjkyMTk2ZDFlNDU4N2M5NDdhMDk1MWQ3ZmFmNTYyYiIsInRhZyI6IiJ9', 'eyJpdiI6InJLcUxaYzFFVzFGaWIzUGtjeGdIWVE9PSIsInZhbHVlIjoiM1BuVWFmN0YwSk55UVgwNVF0RTVRQT09IiwibWFjIjoiODU2ZDc3ZDQ3ODA3MGYyZDg5Zjg2NDBlYjY0NmExMzJjYjcxM2IwNGI5N2FjNDNiMmQzMDM3OWZiYjY5ZWJiNCIsInRhZyI6IiJ9', 'Histoire-Géographie', '55000.00', 'active', '2026-08-31 17:41:03', '2026-08-31 17:41:03'),
(4, 1, 'eyJpdiI6IkpCbkZNSGg2Z0hIZ0Y3dXZreXdaNGc9PSIsInZhbHVlIjoiT2JpTDVJMGdtQURKdWhIZGFBbDE3Zz09IiwibWFjIjoiNzk0ZTIyMWU1NzhkNjg1MzQ5NGQxYzk3MmYwYTZjYTdiNGEyODBkMmZlN2QzMGVkZjMxNDZjOWZhODY0ZDBlNSIsInRhZyI6IiJ9', 'eyJpdiI6IjJyL2ZqR1Q0cmx0OVpOYUxoM041YUE9PSIsInZhbHVlIjoiZngvMEVrdzJKQmdRUVp0UzVWMUFUZz09IiwibWFjIjoiZGEzYzRkNWFlM2M1ZGJiOWU4MGQ3ZGM3NTZhMDZiMTljMDhiYjE2ZTAwMTljYWI3OGY1YTk2NTAyYmQwMGUyYiIsInRhZyI6IiJ9', 'Anglais', '12500.00', 'active', '2026-09-01 06:51:15', '2026-09-01 06:51:15');

-- --------------------------------------------------------

--
-- Structure de la table `teacher_assignments`
--

DROP TABLE IF EXISTS `teacher_assignments`;
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `teacher_id` bigint UNSIGNED NOT NULL,
  `class_id` bigint UNSIGNED NOT NULL,
  `subject_id` bigint UNSIGNED NOT NULL,
  `hourly_rate` decimal(12,2) NOT NULL,
  `weekly_hours` decimal(5,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_assignment_unique` (`academic_year_id`,`teacher_id`,`class_id`,`subject_id`),
  KEY `teacher_assignments_teacher_id_foreign` (`teacher_id`),
  KEY `teacher_assignments_class_id_foreign` (`class_id`),
  KEY `teacher_assignments_subject_id_foreign` (`subject_id`),
  KEY `teacher_assignments_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `teacher_assignments`
--

INSERT INTO `teacher_assignments` (`id`, `school_id`, `academic_year_id`, `teacher_id`, `class_id`, `subject_id`, `hourly_rate`, `weekly_hours`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 1, 7, 1, '2500.00', NULL, 1, '2026-08-27 11:47:27', '2026-08-27 11:47:27'),
(2, 1, NULL, 1, 8, 1, '3000.00', NULL, 1, '2026-08-27 14:45:12', '2026-08-27 14:45:12'),
(3, 1, NULL, 2, 8, 6, '2500.00', NULL, 1, '2026-08-30 17:56:24', '2026-08-30 17:56:24'),
(4, 1, NULL, 3, 4, 6, '5499.93', NULL, 1, '2026-08-31 17:41:40', '2026-08-31 17:41:40'),
(5, 1, NULL, 3, 8, 6, '5400.00', NULL, 1, '2026-08-31 17:43:12', '2026-08-31 17:43:12'),
(6, 1, NULL, 4, 13, 3, '2500.00', NULL, 1, '2026-09-01 06:51:52', '2026-09-01 06:51:52');

-- --------------------------------------------------------

--
-- Structure de la table `teacher_attendances`
--

DROP TABLE IF EXISTS `teacher_attendances`;
CREATE TABLE IF NOT EXISTS `teacher_attendances` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `teaching_session_id` bigint UNSIGNED NOT NULL,
  `teacher_id` bigint UNSIGNED NOT NULL,
  `status` enum('present','absent','justified','replaced') COLLATE utf8mb4_unicode_ci NOT NULL,
  `absence_minutes` int UNSIGNED NOT NULL DEFAULT '0',
  `reason` text COLLATE utf8mb4_unicode_ci,
  `replacement_teacher_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_attendances_teaching_session_id_teacher_id_unique` (`teaching_session_id`,`teacher_id`),
  KEY `teacher_attendances_teacher_id_foreign` (`teacher_id`),
  KEY `teacher_attendances_replacement_teacher_id_foreign` (`replacement_teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `teacher_attendances`
--

INSERT INTO `teacher_attendances` (`id`, `teaching_session_id`, `teacher_id`, `status`, `absence_minutes`, `reason`, `replacement_teacher_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'present', 0, NULL, NULL, '2026-08-31 17:44:16', '2026-08-31 17:44:16'),
(2, 3, 2, 'present', 0, NULL, NULL, '2026-09-01 06:53:38', '2026-09-01 06:53:38'),
(3, 2, 1, 'present', 0, 'Atelier', NULL, '2026-09-01 06:54:25', '2026-09-01 06:54:25');

-- --------------------------------------------------------

--
-- Structure de la table `teaching_sessions`
--

DROP TABLE IF EXISTS `teaching_sessions`;
CREATE TABLE IF NOT EXISTS `teaching_sessions` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `class_id` bigint UNSIGNED NOT NULL,
  `subject_id` bigint UNSIGNED NOT NULL,
  `teacher_assignment_id` bigint UNSIGNED NOT NULL,
  `session_date` date NOT NULL,
  `starts_at` time NOT NULL,
  `ends_at` time NOT NULL,
  `planned_minutes` int UNSIGNED NOT NULL,
  `realized_minutes` int UNSIGNED NOT NULL DEFAULT '0',
  `status` enum('planned','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'planned',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teaching_sessions_academic_year_id_foreign` (`academic_year_id`),
  KEY `teaching_sessions_class_id_foreign` (`class_id`),
  KEY `teaching_sessions_subject_id_foreign` (`subject_id`),
  KEY `teaching_sessions_teacher_assignment_id_session_date_index` (`teacher_assignment_id`,`session_date`),
  KEY `teaching_sessions_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `teaching_sessions`
--

INSERT INTO `teaching_sessions` (`id`, `school_id`, `academic_year_id`, `class_id`, `subject_id`, `teacher_assignment_id`, `session_date`, `starts_at`, `ends_at`, `planned_minutes`, `realized_minutes`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 8, 1, 2, '2026-08-31', '08:00:00', '09:00:00', 60, 60, 'completed', NULL, '2026-08-31 17:43:34', '2026-08-31 17:44:16'),
(2, 1, NULL, 7, 1, 1, '2026-09-01', '08:00:00', '09:00:00', 60, 60, 'completed', NULL, '2026-09-01 06:49:43', '2026-09-01 06:54:25'),
(3, 1, NULL, 8, 6, 3, '2026-09-01', '08:02:00', '10:00:00', 118, 118, 'completed', NULL, '2026-09-01 06:49:43', '2026-09-01 06:53:38'),
(4, 1, NULL, 13, 3, 6, '2026-09-01', '10:00:00', '12:00:00', 120, 0, 'planned', NULL, '2026-09-01 06:52:34', '2026-09-01 06:52:34');

-- --------------------------------------------------------

--
-- Structure de la table `tuition_installments`
--

DROP TABLE IF EXISTS `tuition_installments`;
CREATE TABLE IF NOT EXISTS `tuition_installments` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` bigint UNSIGNED NOT NULL,
  `academic_year_id` bigint UNSIGNED DEFAULT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_per_class` (`class_id`,`label`),
  KEY `tuition_installments_academic_year_id_foreign` (`academic_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tuition_installments`
--

INSERT INTO `tuition_installments` (`id`, `class_id`, `academic_year_id`, `label`, `amount`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, '1ère tranche', '30000.00', '2026-09-26', '2026-08-25 07:06:12', '2026-08-25 07:06:12'),
(2, 2, NULL, '1ère tranche', '30000.00', '2026-10-26', '2026-08-26 12:37:58', '2026-08-26 12:37:58'),
(3, 6, NULL, '1ère tranche', '35000.00', '2026-10-27', '2026-08-26 16:07:04', '2026-08-26 16:07:04'),
(4, 6, NULL, '2ème tranche', '20000.00', '2026-11-23', '2026-08-26 16:08:06', '2026-08-26 16:08:06'),
(5, 12, NULL, '1ère tranche', '44000.00', '2026-10-23', '2026-09-01 06:37:42', '2026-09-01 06:37:42'),
(7, 12, NULL, '2ème tranche', '22000.00', '2026-11-20', '2026-09-01 06:39:00', '2026-09-01 06:39:00'),
(8, 12, NULL, '3ème tranche', '22000.00', '2027-01-15', '2026-09-01 06:39:53', '2026-09-01 06:39:53');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` bigint UNSIGNED NOT NULL DEFAULT '1',
  `role_id` bigint UNSIGNED NOT NULL,
  `full_name` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_school_id_index` (`school_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `school_id`, `role_id`, `full_name`, `email`, `password`, `phone`, `status`, `last_login_at`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Administrateur SIGS', 'admin@sigs.com', '$2y$12$X3MX7iLEeWcev4LwN/MuROrDMdcYf4KKq/6NJZqK/tKbZqzOcUQtO', NULL, 'active', '2026-09-01 08:38:09', 'RtGQlyfyVYg3bYIcQug7sItOhPB6C55mYdppd8jrs9IC8RekUersLp5scfg8', '2026-08-25 05:50:48', '2026-09-01 07:38:09'),
(2, 1, 2, 'ADASSA Mathilda', 'sec@sigs.com', '$2y$12$ABUDCxRBzhMIE3.rQU6fSe3DG6Bpy.R71KVl1497IoFrm.TSvOAsS', 'eyJpdiI6ImhqOFlwN2NKVVNqK3IvZXFqWU9Gd3c9PSIsInZhbHVlIjoiTzZaUlRVekhLSmVDcW1IaG9jSnI4dz09IiwibWFjIjoiNTZkYTE4YWE2MWM1YWVhOTc2YTE3YjYzNTMwMjVjZDY3MmIzYTRmMzM3ZTQ2MTU1ZWFkMzYzYzI2NWI5MjViZCIsInRhZyI6IiJ9', 'active', '2026-08-26 15:36:52', 'gtSAQHdRLg9Yz6GBiggMwfxLJZkQpKp3EgGoCoPZW7y1jO1GIlEIru1TAZ3c', '2026-08-26 14:36:35', '2026-08-26 14:36:52');

-- --------------------------------------------------------

--
-- Structure de la table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `user_id` bigint UNSIGNED NOT NULL,
  `permission_id` bigint UNSIGNED NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `user_permissions_permission_id_foreign` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`, `granted`) VALUES
(2, 2, 1),
(2, 6, 1),
(2, 9, 1),
(2, 10, 1),
(2, 12, 1),
(2, 13, 1);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `classes_cycle_id_foreign` FOREIGN KEY (`cycle_id`) REFERENCES `school_cycles` (`id`);

--
-- Contraintes pour la table `class_schedules`
--
ALTER TABLE `class_schedules`
  ADD CONSTRAINT `class_schedules_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `class_schedules_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_schedules_teacher_assignment_id_foreign` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `fee_type_classes`
--
ALTER TABLE `fee_type_classes`
  ADD CONSTRAINT `fee_type_classes_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fee_type_classes_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_cashier_user_id_foreign` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);

--
-- Contraintes pour la table `payment_items`
--
ALTER TABLE `payment_items`
  ADD CONSTRAINT `payment_items_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`),
  ADD CONSTRAINT `payment_items_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payment_items_tuition_installment_id_foreign` FOREIGN KEY (`tuition_installment_id`) REFERENCES `tuition_installments` (`id`);

--
-- Contraintes pour la table `payroll_entries`
--
ALTER TABLE `payroll_entries`
  ADD CONSTRAINT `payroll_entries_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`);

--
-- Contraintes pour la table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `students_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `students_guardian_id_foreign` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`);

--
-- Contraintes pour la table `teacher_assignments`
--
ALTER TABLE `teacher_assignments`
  ADD CONSTRAINT `teacher_assignments_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teacher_assignments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_assignments_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_assignments_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `teacher_attendances`
--
ALTER TABLE `teacher_attendances`
  ADD CONSTRAINT `teacher_attendances_replacement_teacher_id_foreign` FOREIGN KEY (`replacement_teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teacher_attendances_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teacher_attendances_teaching_session_id_foreign` FOREIGN KEY (`teaching_session_id`) REFERENCES `teaching_sessions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `teaching_sessions`
--
ALTER TABLE `teaching_sessions`
  ADD CONSTRAINT `teaching_sessions_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `teaching_sessions_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teaching_sessions_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teaching_sessions_teacher_assignment_id_foreign` FOREIGN KEY (`teacher_assignment_id`) REFERENCES `teacher_assignments` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `tuition_installments`
--
ALTER TABLE `tuition_installments`
  ADD CONSTRAINT `tuition_installments_academic_year_id_foreign` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tuition_installments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Contraintes pour la table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
