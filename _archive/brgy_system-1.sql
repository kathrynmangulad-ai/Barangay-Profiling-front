-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2026 at 12:11 PM
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
-- Database: `brgy_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_log`
--

CREATE TABLE `access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(20) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `resource` varchar(64) DEFAULT NULL,
  `resource_id` int(11) DEFAULT NULL,
  `result` enum('allowed','denied') NOT NULL DEFAULT 'allowed',
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_log`
--

INSERT INTO `access_log` (`id`, `user_id`, `username`, `role`, `action`, `resource`, `resource_id`, `result`, `ip`, `created_at`) VALUES
(1, 53, 'res_e2e_2437', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 18:32:08'),
(2, 53, 'res_e2e_2437', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 18:32:08'),
(3, 52, 'testuser', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:01:35'),
(4, 52, 'testuser', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:01:40'),
(5, 52, 'testuser', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:01:47'),
(6, 52, 'testuser', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:01:56'),
(7, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(8, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(9, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(10, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(11, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(12, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:00'),
(13, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(14, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(15, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(16, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(17, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(18, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(19, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(20, 54, 'rbactest833', 'resident', 'role_denied:admin|secretary', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(21, 54, 'rbactest833', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(22, 54, 'rbactest833', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(23, 54, 'rbactest833', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(24, 54, 'rbactest833', 'resident', 'role_denied:admin', '0', NULL, 'denied', '0', '2026-10-04 19:03:01'),
(25, 55, 'idor_secretary', 'secretary', 'barangay_denied', '0', 10, 'denied', '0', '2026-10-04 19:06:46'),
(26, 52, 'testuser', 'resident', 'document_requested', '0', 11, 'allowed', '0', '2026-10-04 19:09:58'),
(27, 56, 'blotfix841', 'resident', 'blotter_filed', '0', 521, 'allowed', '0', '2026-10-04 19:26:03'),
(28, 56, 'blotfix841', 'resident', 'document_requested', '0', 12, 'allowed', '0', '2026-10-04 19:27:21'),
(30, 52, 'testuser', 'resident', 'blotter_filed', '0', 522, 'allowed', '0', '2026-10-04 19:36:20'),
(31, 52, 'testuser', 'resident', 'document_printed', '0', 11, 'allowed', '0', '2026-10-04 19:41:36'),
(32, 58, 'printtest864', 'resident', 'document_printed', '0', 13, 'allowed', '0', '2026-10-04 19:45:55'),
(34, 58, 'printtest864', 'resident', 'record_denied', '0', 7, 'denied', '0', '2026-10-04 19:45:56'),
(39, 2, 'secretary1', 'secretary', 'resident_edited', '0', 27, 'allowed', '0', '2026-10-04 20:55:30'),
(40, 2, 'secretary1', 'secretary', 'resident_edited', '0', 23, 'allowed', '0', '2026-10-04 21:02:32'),
(41, 52, 'testuser', 'resident', 'profile_updated', '0', 23, 'allowed', '0', '2026-10-04 21:22:12'),
(42, 4, 'secretary3', 'secretary', 'resident_approve', '0', 65, 'allowed', '0', '2026-10-04 21:37:34'),
(43, 65, 'rose123', 'resident', 'profile_updated', '0', 33, 'allowed', '0', '2026-10-04 21:38:11'),
(44, 52, 'testuser', 'resident', 'document_printed', '0', 11, 'allowed', '0', '2026-10-05 05:46:35'),
(45, 1, 'admin', 'admin', 'user_status_reject', '0', 57, 'allowed', '0', '2026-10-05 06:54:45'),
(46, 52, 'testuser', 'resident', 'document_printed', '0', 11, 'allowed', '0', '2026-10-05 07:32:16'),
(47, 2, 'secretary1', 'secretary', 'resident_approve', '0', 66, 'allowed', '0', '2026-10-05 08:48:15'),
(48, 2, 'secretary1', 'secretary', 'resident_approve', '0', 68, 'allowed', '0', '2026-10-05 09:32:45'),
(49, 1, 'admin', 'admin', 'user_status_approve', '0', 69, 'allowed', '0', '2026-10-05 11:12:55'),
(50, 1, 'admin', 'admin', 'resident_deleted', '0', 33, 'allowed', '0', '2026-10-05 22:14:10'),
(51, 1, 'admin', 'admin', 'resident_deleted', '0', 36, 'allowed', '0', '2026-10-05 22:14:14'),
(52, 1, 'admin', 'admin', 'resident_deleted', '0', 27, 'allowed', '0', '2026-10-05 22:14:19'),
(53, 1, 'admin', 'admin', 'resident_deleted', '0', 16, 'allowed', '0', '2026-10-05 22:14:26'),
(54, 1, 'admin', 'admin', 'resident_deleted', '0', 28, 'allowed', '0', '2026-10-05 22:14:30'),
(55, 1, 'admin', 'admin', 'resident_edited', '0', 35, 'allowed', '0', '2026-10-05 22:30:07'),
(56, 68, 'jeny', 'resident', 'profile_updated', '0', 35, 'allowed', '0', '2026-10-05 22:30:24'),
(57, 1, 'admin', 'admin', 'resident_edited', '0', 35, 'allowed', '0', '2026-10-05 22:33:12'),
(58, 2, 'secretary1', 'secretary', 'resident_edited', '0', 34, 'allowed', '0', '2026-10-05 22:34:10'),
(59, 1, 'admin', 'admin', 'user_edited', '0', 2, 'allowed', '0', '2026-10-05 23:31:05'),
(60, 1, 'admin', 'admin', 'user_status_approve', '0', 71, 'allowed', '0', '2026-10-05 23:36:20'),
(61, 1, 'admin', 'admin', 'user_edited', '0', 71, 'allowed', '0', '2026-10-05 23:37:38'),
(62, 1, 'admin', 'admin', 'user_status_approve', '0', 72, 'allowed', '0', '2026-10-06 01:12:18'),
(63, 72, 'Kathryn', 'resident', 'document_requested', '0', 15, 'allowed', '0', '2026-10-06 01:21:31'),
(64, 72, 'Kathryn', 'resident', 'document_printed', '0', 15, 'allowed', '0', '2026-10-06 01:24:07'),
(65, 1, 'admin', 'admin', 'resident_deleted', '0', 38, 'allowed', '0', '2026-10-06 15:30:04');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(11) NOT NULL,
  `barangay_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `barangay_name`, `created_at`) VALUES
(1, 'ABARIONGAN RUAR', '2026-10-01 11:56:44'),
(2, 'ABARIONGAN UNEG', '2026-10-01 11:56:44'),
(3, 'BALAGAN', '2026-10-01 11:56:44'),
(4, 'BALANNI', '2026-10-01 11:56:44'),
(5, 'CABAYO', '2026-10-01 11:56:44'),
(6, 'CALAPANGAN', '2026-10-01 11:56:44'),
(7, 'CALASSITAN', '2026-10-01 11:56:44'),
(8, 'CAMPO', '2026-10-01 11:56:44'),
(9, 'CENTRO NORTE', '2026-10-01 11:56:44'),
(10, 'CENTRO SUR', '2026-10-01 11:56:44'),
(11, 'DUNGAO', '2026-10-01 11:56:44'),
(12, 'LATTAC', '2026-10-01 11:56:44'),
(13, 'LIPATAN', '2026-10-01 11:56:44'),
(14, 'LUBO', '2026-10-01 11:56:44'),
(15, 'MABITBITNONG', '2026-10-01 11:56:44'),
(16, 'MAPITAC', '2026-10-01 11:56:44'),
(17, 'MASICAL', '2026-10-01 11:56:44'),
(18, 'MATALAO', '2026-10-01 11:56:44'),
(19, 'NAG-UMA', '2026-10-01 11:56:44'),
(20, 'NAMUCCAYAN', '2026-10-01 11:56:44'),
(21, 'NUIG NORTE', '2026-10-01 11:56:44'),
(22, 'NUIG SUR', '2026-10-01 11:56:44'),
(23, 'PALUSAO', '2026-10-01 11:56:44'),
(24, 'SAN MANUEL', '2026-10-01 11:56:44'),
(25, 'SAN ROQUE', '2026-10-01 11:56:44'),
(26, 'SIDIRAN', '2026-10-01 11:56:44'),
(27, 'STA. FELICITAS', '2026-10-01 11:56:44'),
(28, 'STA. MARIA', '2026-10-01 11:56:44'),
(29, 'TABANG', '2026-10-01 11:56:44'),
(30, 'TAMUCCO', '2026-10-01 11:56:44'),
(31, 'VIRGINIA', '2026-10-01 11:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `blotter_records`
--

CREATE TABLE `blotter_records` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blotter_records`
--

INSERT INTO `blotter_records` (`id`, `barangay_id`, `resident_id`, `blotter_no`, `reporting_person`, `reporting_address`, `incident_type`, `report_datetime`, `incident_datetime`, `place_of_incident`, `suspect_data`, `victim_data`, `narrative`, `recorded_by`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(520, 1, NULL, '1', 'Kathryn Mangulad', 'Abariongan Ruar, Sto. Niño, Cagayan', 'Rape', '2026-10-01 20:09:00', '2026-09-20 23:00:00', 'Abariongan Ruar, Sto. Niño, Cagayan', 'Orlando Lagda Jr.\r\n21 years old', 'Kathryn Mangulad\r\n20 years old', '', 'Secretary - Barangay 1', 'Open', NULL, NULL, '2026-10-01 13:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
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
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `barangay_id`, `resident_id`, `requestor_name`, `document_type`, `purpose`, `status`, `requested_at`, `released_at`, `processed_by`, `processed_at`, `decision_note`, `notes`) VALUES
(7, 1, NULL, 'Orlando Lagda', 'Barangay Clearance', 'Scholarship', 'Released', '2026-10-04 11:19:31', NULL, NULL, NULL, NULL, ''),
(8, 1, NULL, 'Azrael Lagda', 'Certificate of Residency', 'schyh', 'Released', '2026-10-04 13:38:09', NULL, NULL, NULL, NULL, ''),
(9, 1, NULL, 'Gamboa Marie-Rose', 'Animal Travel Pass', 'travel', 'Pending', '2026-10-04 14:35:26', NULL, NULL, NULL, NULL, ''),
(15, 1, NULL, 'Kathryn Faye Mangulad123', 'Barangay Clearance', 'scholarship', 'Released', '2026-10-06 01:21:31', NULL, NULL, NULL, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `document_status_history`
--

CREATE TABLE `document_status_history` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `actor_name` varchar(150) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `identifier`, `ip`, `attempted_at`) VALUES
(43, 'secretary2', '::1', '2026-10-05 23:30:22'),
(44, 'secretary2', '::1', '2026-10-05 23:31:11'),
(49, 'sedgthf', '::1', '2026-10-06 14:47:56');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_by`, `created_at`) VALUES
(13, 66, 'a2dd7c29568504f1d6c66ec7dbbcc3194ac61acd23f58b01dcba6b0bf734a12d', '2026-10-05 19:27:33', '2026-10-05 19:04:58', NULL, '2026-10-05 10:27:33'),
(14, 66, 'bf3ef99ee773e94702df228dc717337b1524e933243e9914b7a99b93e93f9782', '2026-10-05 20:04:58', '2026-10-05 19:09:27', NULL, '2026-10-05 11:04:58'),
(15, 66, '996917392c64b3152342408a6b85f3c05a718f521f078bb1057d3938627b56fd', '2026-10-05 20:09:27', NULL, NULL, '2026-10-05 11:09:27');

-- --------------------------------------------------------

--
-- Table structure for table `residents`
--

CREATE TABLE `residents` (
  `id` int(11) NOT NULL,
  `barangay_id` int(100) NOT NULL,
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
  `address` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `household_no` varchar(50) DEFAULT NULL,
  `is_pwd` enum('Yes','No') NOT NULL DEFAULT 'Yes',
  `is_student` enum('Yes','No') NOT NULL DEFAULT 'Yes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `residents`
--

INSERT INTO `residents` (`id`, `barangay_id`, `user_id`, `last_name`, `first_name`, `middle_name`, `sex`, `age`, `birth_date`, `civil_status`, `occupation`, `contact_no`, `address`, `photo`, `household_no`, `is_pwd`, `is_student`, `created_at`, `updated_at`) VALUES
(34, 1, 66, 'Salvador', 'Cherilyn', '', 'Female', 63, NULL, 'Widowed', '', '', '', '', '', 'No', 'No', '2026-10-05 08:45:46', '2026-10-05 08:45:46'),
(35, 1, 68, 'Catiw_ang', 'Jenery', '', '', 20, '2004-01-08', 'Single', '', '09999712267', '', 'uploads/residents/resident_6ac424ef2cab94.82757556.png', '', 'No', 'Yes', '2026-10-05 09:32:34', '2026-10-05 09:32:34'),
(37, 9, NULL, 'Keke', 'Iskanamplong', '', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No', 'No', '2026-10-05 23:35:55', '2026-10-05 23:35:55'),
(39, 1, NULL, 'Mangulad', 'Kathryn Faye', 'Bartolome', 'Male', 0, NULL, '', '', '', '', 'uploads/residents/resident_6ac44ca4896297.88824115.png', '', 'Yes', 'Yes', '2026-10-06 01:19:32', '2026-10-06 01:19:32'),
(40, 1, NULL, 'Mangulad', 'Kathryn', 'Faye', '', 0, NULL, '', '', '', '', NULL, '', 'No', 'No', '2026-10-06 15:19:23', '2026-10-06 15:19:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role` enum('admin','secretary','resident') NOT NULL DEFAULT 'secretary',
  `barangay_id` int(11) DEFAULT NULL,
  `brgy_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','active','rejected','suspended') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `barangay_id`, `brgy_name`, `created_at`, `status`) VALUES
(1, 'admin', NULL, '$2y$10$JF9EnaheFfVsa9dW9dlXkOoWDXDIIqg3tX4YtNXg/cbYkb.IEp3o.', 'System Administrator', 'admin', NULL, NULL, '2026-10-01 12:01:13', 'active'),
(2, 'secretary1', NULL, '$2y$10$7vF2lQ12A4Ac2Lu8GpxFGOe0DN2Dgijoem0XflPLRHCsEHCIK0BM6', 'Kathryn Faye Mangulad', 'secretary', 1, NULL, '2026-10-02 13:53:25', 'active'),
(4, 'secretary3', NULL, '$2y$10$rYUBDTTmEMV9iSvCsEYV4OElcNWbyMzjyUQrL/UrRl47sW2qelh32', 'Secretary - BALAGAN', 'secretary', 3, NULL, '2026-10-01 12:01:27', 'active'),
(5, 'secretary4', NULL, '$2y$10$qvB7o5lF6y.LqvfFStsIiuazru3ng1a9znmIW2fQ0UPV5LxLYXANS', 'Secretary - BALANNI', 'secretary', 4, NULL, '2026-10-01 12:01:27', 'active'),
(6, 'secretary5', NULL, '$2y$10$BgW2WW6v7knZrOzo/5hwF.frR1KgTpSeFt9X7Kau8wbvubDVdB46e', 'Secretary - CABAYO', 'secretary', 5, NULL, '2026-10-01 12:01:27', 'active'),
(7, 'secretary6', NULL, '$2y$10$LLsImx/ex/nmfPt0Nq1TveQwmzm4ZlhydaCx5tormplX29P/TQdxu', 'Secretary - CALAPANGAN', 'secretary', 6, NULL, '2026-10-01 12:01:27', 'active'),
(8, 'secretary7', NULL, '$2y$10$LWfqoait.OSNKMnglupHSuqhWgHRmQ/NRdQ5yYO9DdnFbSkoS/GWe', 'Secretary - CALASSITAN', 'secretary', 7, NULL, '2026-10-01 12:01:27', 'active'),
(9, 'secretary8', NULL, '$2y$10$qFc5Gbbe6gD0us2zUOAl3uQaIqEzi/.E8Fan5xG7Qx2kNmXnBuHZq', 'Secretary -CAMPO', 'secretary', 8, NULL, '2026-10-01 12:01:27', 'active'),
(10, 'secretary9', NULL, '$2y$10$mc9eC3vjhaEL1Cw4V19Lq.kx7xyJfT3GySAYEvrxbJsj7aJ5rJqfa', 'Secretary - CENTRO NORTE', 'secretary', 9, NULL, '2026-10-01 12:01:27', 'active'),
(11, 'secretary10', NULL, '$2y$10$82.8QSA33rzBtdOS6jxLAuu17wTYbmLYpHfdg6urOWLuDgT7NeKfe', 'Secretary - CENTRO SUR', 'secretary', 10, NULL, '2026-10-01 12:01:28', 'active'),
(12, 'secretary11', NULL, '$2y$10$XAb8X9xUgE4ykWMtk6V/N.6bSX5OUPBgmeRMf8H5XVQG6XuOaxuaW', 'Secretary -DUNGAO', 'secretary', 11, NULL, '2026-10-01 12:01:28', 'active'),
(13, 'secretary12', NULL, '$2y$10$rIsN6ZAmsXKsZt4OgTig8uvGELiaNVIfANP9Z4Nw6GF56YryU6VRC', 'Secretary - LATTAC', 'secretary', 12, NULL, '2026-10-01 12:01:28', 'active'),
(14, 'secretary13', NULL, '$2y$10$SfOqJP9CCThLEGj030p8NutCgq3yt1zdmkY0XgkFO3L/.TPmX2.56', 'Secretary - LIPATAN', 'secretary', 13, NULL, '2026-10-01 12:01:28', 'active'),
(15, 'secretary14', NULL, '$2y$10$MXTbs6zjJdbk2xeJcIHAbOwfgFLoieIR771Tmb1Uat68IP8t86hZm', 'Secretary - LUBO', 'secretary', 14, NULL, '2026-10-01 12:01:28', 'active'),
(16, 'secretary15', NULL, '$2y$10$E2S8ER6ixBiSgSI6JE6/uueESbKROQc1wG5AxQ1RHik8j49BPqkoO', 'Secretary - MABITBITNONG', 'secretary', 15, NULL, '2026-10-01 12:01:28', 'active'),
(17, 'secretary16', NULL, '$2y$10$znkJW6HbL3JDYqDoGiyaxOPt7WUk8H2P1YTCYEsla429d.79gUrAy', 'Secretary - MAPITAC', 'secretary', 16, NULL, '2026-10-01 12:01:28', 'active'),
(18, 'secretary17', NULL, '$2y$10$IvZmFEPtnbzMqyTADz3fZO0r4YcPaBvlhp1hvFRjaiMc2jFvhkwjy', 'Secretary -MASICAL', 'secretary', 17, NULL, '2026-10-01 12:01:28', 'active'),
(19, 'secretary18', NULL, '$2y$10$K9M29/6Gpg6jvVorkqKd3.ez0ooOb9FDV29nLUM.cmP.5NhJiO3V6', 'Secretary - MATALAO', 'secretary', 18, NULL, '2026-10-01 12:01:29', 'active'),
(20, 'secretary19', NULL, '$2y$10$72LfVpJJTohc0Jb3XjAP5unsmyE97QYSItZYKtHgoTvVLrT.eAFSi', 'Secretary - NAG-UMA', 'secretary', 19, NULL, '2026-10-01 12:01:29', 'active'),
(21, 'secretary20', NULL, '$2y$10$9KogO86JB2pgXWP82AAaUu123moQTWYtNg0tf3fMt7F.f4bDpyzCW', 'Secretary - NAMUCCAYAN', 'secretary', 20, NULL, '2026-10-01 12:01:29', 'active'),
(22, 'secretary21', NULL, '$2y$10$EUuYrW9vvR2yeR7QzuTxzePSgKSCbEUw0cwLAziVte.e/G9oQtObG', 'Secretary -NUIG NORTE', 'secretary', 21, NULL, '2026-10-01 12:01:29', 'active'),
(23, 'secretary22', NULL, '$2y$10$FhqZvX5wTjqWpjBLV2qRn.xONQuE8KlzWQaq5hAC7t6xDz/UDWOQS', 'Secretary - NUIG SUR', 'secretary', 22, NULL, '2026-10-01 12:01:29', 'active'),
(24, 'secretary23', NULL, '$2y$10$ekowh.ChFLNVpZQX.3V43.CbHwtAMlJDw/hP9OVR3NtvJlmNMzine', 'Secretary - PALUSAO', 'secretary', 23, NULL, '2026-10-01 12:01:29', 'active'),
(25, 'secretary24', NULL, '$2y$10$urvRHyM8rB1vOxqXXNOOjO02Tai6HrcoDjIF9zhfUn.EyuLt9lfQm', 'Secretary - SAN MANUEL', 'secretary', 24, NULL, '2026-10-01 12:01:29', 'active'),
(26, 'secretary25', NULL, '$2y$10$d9NSOCVnUL3J/AtHYwLd8.gVsaSz.rwyC8y6HmCYc5NtW8hy8tgVC', 'Secretary - SAN ROQUE', 'secretary', 25, NULL, '2026-10-01 12:01:29', 'active'),
(27, 'secretary26', NULL, '$2y$10$ts8UtEf5k3j.YjisvJ3tL.hqtpTzha/FQihQUV6SSxrS2lPp3QBc.', 'Secretary - SIDIRAN', 'secretary', 26, NULL, '2026-10-01 12:01:30', 'active'),
(28, 'secretary27', NULL, '$2y$10$6K1dSSsOWuAxzNevo1DhqeWXztVJorHxDBXGywNbCkhEQ6tlYxPwu', 'Secretary -STA. FELICITAS', 'secretary', 27, NULL, '2026-10-01 12:01:30', 'active'),
(29, 'secretary28', NULL, '$2y$10$Ie4T31P21XcBv1VxF7zPbO5wgvlFIU4nUOVcpm5wVCsacqUAn.o9K', 'Secretary - STA. MARIA', 'secretary', 28, NULL, '2026-10-01 12:01:30', 'active'),
(30, 'secretary29', NULL, '$2y$10$.mB2VMQEGGt0SEm8hQQg8uWUE9lg3e1ahXCWgjTwwfx2ijh4gxeha', 'Secretary - TABANG', 'secretary', 29, NULL, '2026-10-01 12:01:30', 'active'),
(31, 'secretary30', NULL, '$2y$10$bKQmBBB5F4WMyA1EgHIzJekhLkHKlgrAhnCE4OK8euVoK/YNvPWdu', 'Secretary - TAMUCCO', 'secretary', 30, NULL, '2026-10-01 12:01:30', 'active'),
(32, 'secretary31', NULL, '$2y$10$D9/MJDoNJgvs1A.UtvBtfe.dDX8xe.Ab6psqBmzwUS2hkoBrV8tVO', 'Secretary - VIRGINIA', 'secretary', 31, NULL, '2026-10-01 12:01:30', 'active'),
(50, 'haha', NULL, '$2y$10$16FoLc5jRBgYhraFYpgtmetntA2EVDqTPyP8P4aKV2udXsdf2Xx3O', 'Kathryn Faye B. Mangulad', 'secretary', 10, NULL, '2026-10-04 18:10:28', 'active'),
(57, 'testusersecretarytesting', NULL, '$2y$10$1ilPmNdjFuUSqDdeH/epK.s3n5KkzJQWpn40QrmdoLoBebSpC0Y6i', 'Azrael Lagda', 'resident', 1, NULL, '2026-10-04 19:17:57', 'rejected'),
(66, 'che', 'chery@yopmail.com', '$2y$10$oZTeDaOdVscTmS/yl2WjvO6WNwGostgFWQXV6NJ3QNBebqwuwM6V.', 'Cherilyn Salvador', 'resident', 1, NULL, '2026-10-05 08:45:46', 'active'),
(68, 'jeny', 'kathrynmangulad@gmail.com', '$2y$10$8sNQf3ng5qnKlSq6Y7Y9v.kVPge8N8fqsqG5a5Y2gjbFwz8gQd13y', 'Jenery Catiw_ang', 'resident', 1, NULL, '2026-10-05 09:32:34', 'active'),
(70, 'Secretary2', NULL, '$2y$10$ty./MHdQqA84Ej77pB1o9.at3lUvTTzVnglxSTBSEaonoa29u/3dK', 'Secretary-Barangay 2', 'secretary', 2, NULL, '2026-10-05 22:12:06', 'active'),
(72, 'Kathryn', 'kathrynmangulad13@gmail.com', '$2y$10$limv9n7tr7U.EXb31sYqa.ZmN0xRWtNyX9vSvdmZKuyP2zhbtoaEa', 'Kathryn Faye Mangulad123', 'resident', 1, NULL, '2026-10-06 01:09:07', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_log`
--
ALTER TABLE `access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_al_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_al_result_time` (`result`,`created_at`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barangay_name` (`barangay_name`);

--
-- Indexes for table `blotter_records`
--
ALTER TABLE `blotter_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blotter_barangay` (`barangay_id`),
  ADD KEY `idx_blotter_resident` (`resident_id`),
  ADD KEY `idx_blotter_status` (`status`),
  ADD KEY `fk_blotter_reviewed_by` (`reviewed_by`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_docs_barangay` (`barangay_id`),
  ADD KEY `idx_docs_resident` (`resident_id`),
  ADD KEY `idx_docs_status` (`status`),
  ADD KEY `idx_docs_processed_by` (`processed_by`);

--
-- Indexes for table `document_status_history`
--
ALTER TABLE `document_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dsh_request` (`request_id`,`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_id_time` (`identifier`,`attempted_at`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token_hash` (`token_hash`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `residents`
--
ALTER TABLE `residents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_residents_user` (`user_id`),
  ADD KEY `idx_residents_barangay` (`barangay_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `barangay_name` (`brgy_name`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_log`
--
ALTER TABLE `access_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `blotter_records`
--
ALTER TABLE `blotter_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=523;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `document_status_history`
--
ALTER TABLE `document_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `residents`
--
ALTER TABLE `residents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `blotter_records`
--
ALTER TABLE `blotter_records`
  ADD CONSTRAINT `blotter_records_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `fk_blotter_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blotter_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`),
  ADD CONSTRAINT `fk_docs_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `document_status_history`
--
ALTER TABLE `document_status_history`
  ADD CONSTRAINT `fk_dsh_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `residents`
--
ALTER TABLE `residents`
  ADD CONSTRAINT `fk_residents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `residents_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`brgy_name`) REFERENCES `barangays` (`barangay_name`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
