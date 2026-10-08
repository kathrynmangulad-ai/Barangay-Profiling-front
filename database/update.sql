-- =============================================================================
-- Barangay Profiling and Document Request System
-- NON-DESTRUCTIVE update script.
--
-- Use this to bring an EXISTING brgy_system database up to the current
-- structure WITHOUT losing data. It only ADDS tables / columns / indexes that
-- are missing. It never DROPs tables and never deletes rows.
--
-- When to use which file:
--   - Fresh / empty database      -> run schema.sql  (then seed.sql)
--   - Existing database with data -> run THIS file (update.sql)
--
-- Safe to run more than once: every statement uses IF NOT EXISTS, so re-running
-- is a no-op for anything already present.
--
-- Requires MariaDB 10.0+ / MySQL 8.0+ (XAMPP's MariaDB supports all of this).
-- Recommended: back up first ->
--   mysqldump -u root brgy_system > brgy_system_backup.sql
--
-- Usage:
--   mysql -u root brgy_system < database/update.sql
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- users : email, soft delete, and supporting indexes
-- -----------------------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email` varchar(255) DEFAULT NULL AFTER `username`;
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `deleted_at` timestamp NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `users`
  ADD UNIQUE KEY IF NOT EXISTS `uq_users_email` (`email`);
ALTER TABLE `users`
  ADD INDEX IF NOT EXISTS `idx_users_deleted_at` (`deleted_at`);

-- -----------------------------------------------------------------------------
-- households : proper household registry (one row per household, with a head of
-- the family). residents.household_id is the link that binds each resident to
-- their household; all household counts are built on this table.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `households` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barangay_id` int(11) NOT NULL,
  `household_no` varchar(50) NOT NULL,
  `head_resident_id` int(11) DEFAULT NULL,
  `purok` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_households_brgy_no` (`barangay_id`,`household_no`),
  KEY `idx_households_barangay` (`barangay_id`),
  KEY `idx_households_head` (`head_resident_id`),
  CONSTRAINT `fk_households_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- residents : soft delete + supporting index
-- -----------------------------------------------------------------------------
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `deleted_at` timestamp NULL DEFAULT NULL AFTER `updated_at`;
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `email` varchar(255) DEFAULT NULL AFTER `contact_no`;
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `household_id` int(11) DEFAULT NULL AFTER `address`;
ALTER TABLE `residents`
  ADD INDEX IF NOT EXISTS `idx_residents_deleted_at` (`deleted_at`);
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `is_ip` enum('Yes','No') NOT NULL DEFAULT 'No' AFTER `is_student`;
ALTER TABLE `residents`
  ADD COLUMN IF NOT EXISTS `is_4ps` enum('Yes','No') NOT NULL DEFAULT 'No' AFTER `is_ip`;
ALTER TABLE `residents`
  ADD INDEX IF NOT EXISTS `idx_residents_household` (`household_id`);

-- -----------------------------------------------------------------------------
-- households backfill (existing data only; safe to re-run).
--
-- 1) Create one household per distinct (barangay_id, household_no) found on
--    live residents. created_at = earliest member's registration date so yearly
--    snapshots keep counting each household in the year it actually started.
-- 2) Link each resident to their household. Guarded so a re-run never
--    re-links a resident that was deliberately unassigned after the migration.
-- 3) Pick a provisional head per household: prefer adults, then the oldest,
--    then the earliest registered. The secretary can correct this any time.
-- -----------------------------------------------------------------------------
SET SESSION group_concat_max_len = 1000000;

INSERT IGNORE INTO `households` (`barangay_id`, `household_no`, `purok`, `created_at`)
SELECT r.`barangay_id`, TRIM(r.`household_no`), MIN(r.`address`), MIN(r.`created_at`)
  FROM `residents` r
 WHERE r.`deleted_at` IS NULL
   AND r.`household_no` IS NOT NULL
   AND TRIM(r.`household_no`) <> ''
 GROUP BY r.`barangay_id`, TRIM(r.`household_no`);

UPDATE `residents` r
  JOIN `households` h
    ON h.`barangay_id` = r.`barangay_id`
   AND h.`household_no` = TRIM(r.`household_no`)
   SET r.`household_id` = h.`id`
 WHERE r.`deleted_at` IS NULL
   AND r.`household_id` IS NULL
   AND r.`household_no` IS NOT NULL
   AND TRIM(r.`household_no`) <> ''
   AND NOT EXISTS (
       SELECT 1 FROM (
           SELECT `household_id` FROM `residents`
            WHERE `deleted_at` IS NULL AND `household_id` IS NOT NULL
       ) m WHERE m.`household_id` = h.`id`
   );

UPDATE `households` h
  JOIN (
        SELECT r.`household_id`,
               SUBSTRING_INDEX(
                   GROUP_CONCAT(r.`id` ORDER BY (r.`age` >= 18) DESC, r.`age` DESC, r.`created_at` ASC, r.`id` ASC),
                   ',', 1) AS head_id
          FROM `residents` r
         WHERE r.`deleted_at` IS NULL AND r.`household_id` IS NOT NULL
         GROUP BY r.`household_id`
       ) m ON m.`household_id` = h.`id`
   SET h.`head_resident_id` = m.`head_id`
 WHERE h.`head_resident_id` IS NULL;


-- -----------------------------------------------------------------------------
-- document_requests : workflow columns added over time
-- -----------------------------------------------------------------------------
ALTER TABLE `document_requests`
  ADD COLUMN IF NOT EXISTS `processed_by` int(11) DEFAULT NULL AFTER `released_at`;
ALTER TABLE `document_requests`
  ADD COLUMN IF NOT EXISTS `processed_at` datetime DEFAULT NULL AFTER `processed_by`;
ALTER TABLE `document_requests`
  ADD COLUMN IF NOT EXISTS `decision_note` varchar(255) DEFAULT NULL AFTER `processed_at`;
ALTER TABLE `document_requests`
  ADD INDEX IF NOT EXISTS `idx_docs_processed_by` (`processed_by`);

-- -----------------------------------------------------------------------------
-- blotter_records : review columns added over time
-- -----------------------------------------------------------------------------
ALTER TABLE `blotter_records`
  ADD COLUMN IF NOT EXISTS `reviewed_by` int(11) DEFAULT NULL AFTER `status`;
ALTER TABLE `blotter_records`
  ADD COLUMN IF NOT EXISTS `reviewed_at` datetime DEFAULT NULL AFTER `reviewed_by`;
ALTER TABLE `blotter_records`
  ADD INDEX IF NOT EXISTS `fk_blotter_reviewed_by` (`reviewed_by`);

-- -----------------------------------------------------------------------------
-- document_status_history : audit trail of document status transitions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `document_status_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dsh_request` (`request_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- access_log : RBAC / security audit log
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_log` (
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
-- login_attempts : brute-force throttling
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
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
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- barangay_yearly_stats : frozen per-year, per-barangay snapshots
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barangay_yearly_stats` (
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

-- End of update.
