-- =============================================================================
-- Barangay Profiling and Document Request System
-- Seed data (reference data + bootstrap admin).
--
-- Run AFTER schema.sql on a fresh database:
--   mysql -u root brgy_system < database/seed.sql
--
-- Safe to re-run: uses INSERT IGNORE / ON DUPLICATE KEY so existing rows are
-- not duplicated. It does NOT touch resident / document / blotter data.
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

-- End of seed.
