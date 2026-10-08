-- =============================================================================
-- Barangay Profiling and Document Request System
-- Seed data (reference data + bootstrap admin).
--
-- Run AFTER schema.sql on a fresh database:
--   mysql -u root brgy_system < database/seed.sql
--
-- Safe to re-run: uses INSERT IGNORE / ON DUPLICATE KEY so existing rows are
-- not duplicated. The demo household block additionally guards itself so it
-- only runs on databases that have no households yet - real data is never
-- touched, and re-running never duplicates or resets anything.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Barangays (official list). IDs are fixed so foreign keys line up across
-- every environment.
-- -----------------------------------------------------------------------------
INSERT INTO `barangays` (`id`, `barangay_name`) VALUES
  (1,  'ABARIONGAN RUAR'),
  (2,  'ABARIONGAN UNEG'),
  (3,  'BALAGAN'),
  (4,  'BALANNI'),
  (5,  'CABAYO'),
  (6,  'CALAPANGAN'),
  (7,  'CALASSITAN'),
  (8,  'CAMPO'),
  (9,  'CENTRO NORTE'),
  (10, 'CENTRO SUR'),
  (11, 'DUNGAO'),
  (12, 'LATTAC'),
  (13, 'LIPATAN'),
  (14, 'LUBO'),
  (15, 'MABITBITNONG'),
  (16, 'MAPITAC'),
  (17, 'MASICAL'),
  (18, 'MATALAO'),
  (19, 'NAG-UMA'),
  (20, 'NAMUCCAYAN'),
  (21, 'NUIG NORTE'),
  (22, 'NUIG SUR'),
  (23, 'PALUSAO'),
  (24, 'SAN MANUEL'),
  (25, 'SAN ROQUE'),
  (26, 'SIDIRAN'),
  (27, 'STA. FELICITAS'),
  (28, 'STA. MARIA'),
  (29, 'TABANG'),
  (30, 'TAMUCCO'),
  (31, 'VIRGINIA')
ON DUPLICATE KEY UPDATE `barangay_name` = VALUES(`barangay_name`);

-- -----------------------------------------------------------------------------
-- Bootstrap administrator.
--   username: admin
--   password: admin123     <-- CHANGE THIS IMMEDIATELY after first login
-- The password below is a bcrypt hash of "admin123" (PASSWORD_DEFAULT).
-- INSERT IGNORE keeps an existing 'admin' account untouched.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `barangay_id`, `status`)
VALUES (
  'admin',
  NULL,
  '$2y$10$wRqiRswWab7Muy01g0rFm.UEguKhSXkrBmUvglgBCcwfeyLdS3TCu',
  'System Administrator',
  'admin',
  NULL,
  'active'
);

-- -----------------------------------------------------------------------------
-- Demo households + resident accounts (household logic examples).
--
-- A small, instantly recognizable set of households in barangay 1
-- (ABARIONGAN RUAR) so every environment can see the household rules end to end:
--   * HH-0001 - Juan Dela Cruz (head) + Maria + Jose ...... 3 bound members
--   * HH-0002 - Rosa Santos (head) + Pedro ................. 2 bound members
--   * HH-0003 - Ana Reyes only, NO head .................... "No head yet" warning
--   * Kim Tan  - unassigned (self-registered style; the secretary assigns them)
--
-- Accounts: role=resident, status=active.
--   Password for every demo account: Demo1234   (bcrypt hash below)
--   Staff side: secretary1 (barangay 1) can manage them; admin sees everything.
--
-- Safety: the whole block is guarded by @demo - it only runs on a database
-- that has NO households yet (fresh installs / this dev DB). Once real
-- households exist, seed.sql skips the demo entirely. Every statement is
-- idempotent (INSERT IGNORE / guarded UPDATE).
-- -----------------------------------------------------------------------------
SET @demo := (SELECT COUNT(*) = 0 FROM households);

-- Demo households (unique per barangay via uq_households_brgy_no).
INSERT IGNORE INTO `households` (`barangay_id`, `household_no`, `purok`)
SELECT d.`barangay_id`, d.`household_no`, d.`purok`
  FROM (
        SELECT 1 AS `barangay_id`, 'HH-0001' AS `household_no`, 'Purok 1' AS `purok`
        UNION ALL SELECT 1, 'HH-0002', 'Purok 2'
        UNION ALL SELECT 1, 'HH-0003', 'Purok 3'
       ) d
 WHERE @demo;

-- Demo resident accounts (username is unique -> INSERT IGNORE is a no-op on re-run).
INSERT IGNORE INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `barangay_id`, `status`)
SELECT d.`username`, d.`email`, d.`password`, d.`full_name`, d.`role`, d.`barangay_id`, d.`status`
  FROM (
        SELECT 'demo.juan' AS `username`, NULL AS `email`, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2' AS `password`, 'Juan Dela Cruz' AS `full_name`, 'resident' AS `role`, 1 AS `barangay_id`, 'active' AS `status`
        UNION ALL SELECT 'demo.maria', NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Maria Dela Cruz', 'resident', 1, 'active'
        UNION ALL SELECT 'demo.jose',  NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Jose Dela Cruz',  'resident', 1, 'active'
        UNION ALL SELECT 'demo.rosa',  NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Rosa Santos',     'resident', 1, 'active'
        UNION ALL SELECT 'demo.pedro', NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Pedro Santos',    'resident', 1, 'active'
        UNION ALL SELECT 'demo.ana',   NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Ana Reyes',       'resident', 1, 'active'
        UNION ALL SELECT 'demo.kim',   NULL, '$2y$10$nozKNs5.vYoZrvOji2jWUujmaHX1WIQLsB2hFGtYUm9R89AJ/KwV2', 'Kim Tan',         'resident', 1, 'active'
       ) d
 WHERE @demo;

-- Demo resident profiles, bound to their household at insert time
-- (household_id resolved from the household's unique number; Kim stays NULL).
INSERT IGNORE INTO `residents`
    (`barangay_id`, `user_id`, `last_name`, `first_name`, `middle_name`, `sex`, `age`, `birth_date`,
     `civil_status`, `occupation`, `contact_no`, `address`, `household_id`, `is_pwd`, `is_student`)
SELECT d.`barangay_id`, d.`user_id`, d.`last_name`, d.`first_name`, d.`middle_name`, d.`sex`, d.`age`, d.`birth_date`,
       d.`civil_status`, d.`occupation`, d.`contact_no`, d.`address`, d.`household_id`, d.`is_pwd`, d.`is_student`
  FROM (
        SELECT 1 AS `barangay_id`, (SELECT id FROM users WHERE username='demo.juan') AS `user_id`,
               'Dela Cruz' AS `last_name`, 'Juan' AS `first_name`, 'S' AS `middle_name`, 'Male' AS `sex`,
               55 AS `age`, '1971-03-12' AS `birth_date`, 'Married' AS `civil_status`, 'Farmer' AS `occupation`,
               '09171110001' AS `contact_no`, 'Purok 1' AS `address`,
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0001') AS `household_id`,
               'No' AS `is_pwd`, 'No' AS `is_student`
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.maria'),
               'Dela Cruz', 'Maria', 'L', 'Female', 52, '1974-07-23', 'Married', 'Vendor',
               '09171110002', 'Purok 1',
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0001'),
               'No', 'No'
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.jose'),
               'Dela Cruz', 'Jose', 'M', 'Male', 25, '2001-11-05', 'Single', 'Driver',
               '09171110003', 'Purok 1',
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0001'),
               'No', 'No'
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.rosa'),
               'Santos', 'Rosa', 'P', 'Female', 60, '1966-01-30', 'Widowed', 'Vendor',
               '09171110004', 'Purok 2',
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0002'),
               'No', 'No'
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.pedro'),
               'Santos', 'Pedro', 'G', 'Male', 35, '1991-09-18', 'Married', 'Driver',
               '09171110005', 'Purok 2',
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0002'),
               'No', 'No'
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.ana'),
               'Reyes', 'Ana', 'C', 'Female', 28, '1998-05-14', 'Single', 'Teacher',
               '09171110006', 'Purok 3',
               (SELECT id FROM households WHERE barangay_id=1 AND household_no='HH-0003'),
               'No', 'No'
        UNION ALL SELECT 1, (SELECT id FROM users WHERE username='demo.kim'),
               'Tan', 'Kim', '', 'Female', 22, '2004-08-01', 'Single', 'Student',
               '09171110007', 'Purok 1',
               NULL,
               'No', 'No'
       ) d
 WHERE @demo;

-- Heads of the two demo households (only when vacant, so a head assigned by
-- the office later is never overwritten). HH-0003 is left headless on purpose.
UPDATE `households` h
  JOIN `residents` r ON r.`user_id` = (SELECT id FROM users WHERE username = 'demo.juan')
   SET h.`head_resident_id` = r.`id`
 WHERE @demo AND h.`barangay_id` = 1 AND h.`household_no` = 'HH-0001'
   AND h.`head_resident_id` IS NULL;

UPDATE `households` h
  JOIN `residents` r ON r.`user_id` = (SELECT id FROM users WHERE username = 'demo.rosa')
   SET h.`head_resident_id` = r.`id`
 WHERE @demo AND h.`barangay_id` = 1 AND h.`household_no` = 'HH-0002'
   AND h.`head_resident_id` IS NULL;

-- End of seed.
