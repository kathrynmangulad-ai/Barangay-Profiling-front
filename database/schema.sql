-- =============================================================================
-- Barangay Profiling and Document Request System
-- Consolidated database schema (single source of truth).
--
-- This file replaces the previous schema + incremental migration files. It
-- reflects the full, current structure (all historical migrations already
-- folded in). Import this once on a fresh database; everyone on the team runs
-- the same file and ends up with an identical structure.
--
-- Usage (fresh database):
--   mysql -u root -e "CREATE DATABASE IF NOT EXISTS brgy_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root brgy_system < database/schema.sql
--   mysql -u root brgy_system < database/seed.sql      -- reference data + bootstrap admin
--
-- Re-running schema.sql DROPS and recreates every table (destroys data).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `document_status_history`;
DROP TABLE IF EXISTS `document_requests`;
DROP TABLE IF EXISTS `blotter_records`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `access_log`;
DROP TABLE IF EXISTS `barangay_yearly_stats`;
DROP TABLE IF EXISTS `residents`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `barangays`;

-- -----------------------------------------------------------------------------
-- barangays : reference list of barangays (see seed.sql for the rows)
-- -----------------------------------------------------------------------------
CREATE TABLE `barangays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barangay_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barangay_name` (`barangay_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- users : all accounts (admin / secretary / resident). deleted_at = soft delete
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','secretary','resident') NOT NULL DEFAULT 'secretary',
  `barangay_id` int(11) DEFAULT NULL,
  `brgy_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','active','rejected','suspended') NOT NULL DEFAULT 'active',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `barangay_id` (`barangay_id`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- residents : resident profile records. deleted_at = soft delete
-- -----------------------------------------------------------------------------
CREATE TABLE `residents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barangay_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `sex` varchar(20) DEFAULT NULL,
  `age` int(11) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `contact_no` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `is_pwd` enum('Yes','No') NOT NULL DEFAULT 'No',
  `is_student` enum('Yes','No') NOT NULL DEFAULT 'No',
  `is_ip` enum('Yes','No') NOT NULL DEFAULT 'No',
  `is_4ps` enum('Yes','No') NOT NULL DEFAULT 'No',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_residents_user` (`user_id`),
  KEY `idx_residents_barangay` (`barangay_id`),
  KEY `idx_residents_deleted_at` (`deleted_at`),
  CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `fk_residents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- document_requests : certificate / clearance requests and their workflow
-- -----------------------------------------------------------------------------
CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barangay_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `requestor_name` varchar(150) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Processing','Released','Rejected','Cancelled') NOT NULL DEFAULT 'Pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `decision_note` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_docs_barangay` (`barangay_id`),
  KEY `idx_docs_resident` (`resident_id`),
  KEY `idx_docs_status` (`status`),
  KEY `idx_docs_processed_by` (`processed_by`),
  CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  CONSTRAINT `fk_docs_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- document_status_history : audit trail of document status transitions
-- -----------------------------------------------------------------------------
CREATE TABLE `document_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dsh_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_dsh_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- blotter_records : blotter / incident reports
-- -----------------------------------------------------------------------------
CREATE TABLE `blotter_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barangay_id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `blotter_no` varchar(50) NOT NULL,
  `reporting_person` varchar(150) NOT NULL,
  `reporting_address` varchar(255) DEFAULT NULL,
  `incident_type` varchar(150) NOT NULL,
  `report_datetime` datetime DEFAULT NULL,
  `incident_datetime` datetime DEFAULT NULL,
  `place_of_incident` varchar(255) DEFAULT NULL,
  `suspect_data` text DEFAULT NULL,
  `victim_data` text DEFAULT NULL,
  `narrative` text DEFAULT NULL,
  `recorded_by` varchar(150) DEFAULT NULL,
  `status` enum('Open','In Progress','Resolved','Cancelled') NOT NULL DEFAULT 'Open',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_blotter_barangay` (`barangay_id`),
  KEY `idx_blotter_resident` (`resident_id`),
  KEY `idx_blotter_status` (`status`),
  KEY `fk_blotter_reviewed_by` (`reviewed_by`),
  CONSTRAINT `blotter_records_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `fk_blotter_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotter_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- access_log : RBAC / security audit log
-- -----------------------------------------------------------------------------
CREATE TABLE `access_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `resource` varchar(64) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `result` enum('allowed','denied') NOT NULL DEFAULT 'allowed',
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_al_user_time` (`user_id`,`created_at`),
  KEY `idx_al_result_time` (`result`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- login_attempts : brute-force throttling (per-identifier and per-IP)
-- -----------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_id_time` (`identifier`,`attempted_at`),
  KEY `idx_ip_time` (`ip`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- password_reset_tokens : single-use, hashed, time-limited reset tokens
-- -----------------------------------------------------------------------------
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- barangay_yearly_stats : frozen per-year, per-barangay snapshots
-- barangay_id = 0 is the "all barangays" aggregate row for that year
-- -----------------------------------------------------------------------------
CREATE TABLE `barangay_yearly_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stat_year` smallint(6) NOT NULL,
  `barangay_id` int(11) NOT NULL DEFAULT 0,
  `residents` int(11) NOT NULL DEFAULT 0,
  `households` int(11) NOT NULL DEFAULT 0,
  `students` int(11) NOT NULL DEFAULT 0,
  `pwd` int(11) NOT NULL DEFAULT 0,
  `seniors` int(11) NOT NULL DEFAULT 0,
  `male` int(11) NOT NULL DEFAULT 0,
  `female` int(11) NOT NULL DEFAULT 0,
  `documents` int(11) NOT NULL DEFAULT 0,
  `blotters` int(11) NOT NULL DEFAULT 0,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `captured_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_year_brgy` (`stat_year`,`barangay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- End of schema.
