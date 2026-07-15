-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2026 at 09:51 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pms_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `user_name`, `action`, `subject_type`, `subject_id`, `description`, `created_at`, `updated_at`) VALUES
(1, 1, 'will', 'updated', 'College', 1, 'Updated college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-24 22:46:33', '2026-06-24 22:46:33'),
(2, 1, 'will', 'updated', 'College', 1, 'Updated college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-24 22:46:42', '2026-06-24 22:46:42'),
(3, 1, 'will', 'updated', 'College', 1, 'Updated college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-24 22:46:43', '2026-06-24 22:46:43'),
(4, 1, 'will', 'updated', 'College', 1, 'Updated college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-24 22:46:44', '2026-06-24 22:46:44'),
(5, 1, 'will', 'deleted', NULL, NULL, 'Deleted college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-24 22:46:48', '2026-06-24 22:46:48'),
(6, 1, 'will', 'deleted', NULL, NULL, 'Deleted device \"2024-164-10405030-01-04-1589\"', '2026-06-24 22:46:56', '2026-06-24 22:46:56'),
(7, 1, 'will', 'created', 'Device', 2, 'Added device \"2025-0\"', '2026-06-24 22:47:24', '2026-06-24 22:47:24'),
(8, 1, 'will', 'created', 'Device', 3, 'Added device \"2024-164-10405030-01-04-1589\"', '2026-06-24 22:50:39', '2026-06-24 22:50:39'),
(9, 1, 'will', 'created', 'Device', 4, 'Added device \"2024-164-10405030-4\"', '2026-06-24 22:50:57', '2026-06-24 22:50:57'),
(10, 1, 'will', 'created', 'Device', 5, 'Added device \"10-4\"', '2026-06-25 00:10:29', '2026-06-25 00:10:29'),
(11, 1, 'will', 'updated', 'Device', 5, 'Updated device \"10-43\"', '2026-06-25 00:10:49', '2026-06-25 00:10:49'),
(12, 1, 'will', 'created', 'Device', 6, 'Added device \"12-12\"', '2026-06-25 00:11:09', '2026-06-25 00:11:09'),
(13, 1, 'will', 'created', 'Device', 7, 'Added device \"1-23\"', '2026-06-25 00:11:46', '2026-06-25 00:11:46'),
(14, 1, 'will', 'created', 'Device', 8, 'Added device \"2025-10\"', '2026-06-25 00:37:09', '2026-06-25 00:37:09'),
(15, 1, 'will', 'created', 'Device', 9, 'Added device \"123778-89\"', '2026-06-25 00:37:58', '2026-06-25 00:37:58'),
(16, 1, 'will', 'created', 'Device', 10, 'Added device \"123778-789\"', '2026-06-25 00:38:07', '2026-06-25 00:38:07'),
(17, 1, 'will', 'created', 'Device', 11, 'Added device \"666666666666-6\"', '2026-06-25 00:38:51', '2026-06-25 00:38:51'),
(18, 1, 'will', 'created', 'Device', 12, 'Added device \"2025-4\"', '2026-06-26 00:34:49', '2026-06-26 00:34:49'),
(19, 1, 'will', 'created', 'User', 2, 'Created user account \"seven\" (Custodian)', '2026-06-28 16:59:11', '2026-06-28 16:59:11'),
(20, 1, 'will', 'updated', 'Device', 12, 'Updated device \"2025-4\"', '2026-06-28 21:40:57', '2026-06-28 21:40:57'),
(21, 1, 'will', 'created', 'Device', 13, 'Added device \"2025-42\"', '2026-06-28 22:23:27', '2026-06-28 22:23:27'),
(22, 1, 'will', 'updated', 'Device', 13, 'Updated device \"2025-42\"', '2026-06-28 22:23:59', '2026-06-28 22:23:59'),
(23, 1, 'will', 'created', 'User', 3, 'Created user account \"Krylar.\" (Custodian)', '2026-06-28 23:51:43', '2026-06-28 23:51:43'),
(24, 1, 'will', 'created', 'Device', 14, 'Added device \"2025-023\"', '2026-06-29 18:50:12', '2026-06-29 18:50:12'),
(25, 1, 'will', 'updated', 'Device', 14, 'Updated device \"2025-023\"', '2026-06-29 18:51:34', '2026-06-29 18:51:34'),
(26, 1, 'will', 'created', 'Device', 15, 'Added device \"2025-02319\"', '2026-06-29 18:52:28', '2026-06-29 18:52:28'),
(27, 1, 'will', 'updated', 'Device', 15, 'Updated device \"2025-02319\"', '2026-06-29 18:59:52', '2026-06-29 18:59:52'),
(28, 1, 'will', 'updated', 'Device', 14, 'Updated device \"2025-023\"', '2026-06-29 19:04:48', '2026-06-29 19:04:48'),
(29, 1, 'will', 'created', 'Device', 16, 'Added device \"2025-0109\"', '2026-06-29 19:08:50', '2026-06-29 19:08:50'),
(30, 1, 'will', 'updated', 'Device', 16, 'Updated device \"2025-0109\"', '2026-06-29 19:21:13', '2026-06-29 19:21:13'),
(31, 1, 'will', 'created', 'Device', 17, 'Added device \"2025-012\"', '2026-06-29 19:21:40', '2026-06-29 19:21:40'),
(32, 1, 'will', 'created', 'Device', 18, 'Added device \"2025-022778\"', '2026-06-29 19:23:04', '2026-06-29 19:23:04'),
(33, 1, 'will', 'created', 'Device', 19, 'Added device \"2025-098\"', '2026-06-29 19:27:29', '2026-06-29 19:27:29'),
(34, 1, 'will', 'created', 'Device', 20, 'Added device \"2025-45\"', '2026-06-29 19:40:05', '2026-06-29 19:40:05'),
(35, 1, 'will', 'updated', 'Device', 19, 'Updated device \"2025-098\"', '2026-06-29 19:40:32', '2026-06-29 19:40:32'),
(36, 1, 'will', 'created', 'Device', 21, 'Added device \"1309-28\"', '2026-06-29 22:34:39', '2026-06-29 22:34:39'),
(37, 1, 'will', 'created', 'Device', 22, 'Added device \"1498-94\"', '2026-06-29 22:42:29', '2026-06-29 22:42:29'),
(38, 1, 'will', 'updated', 'Device', 22, 'Generated preventive maintenance checklist for device \"1498-94\"', '2026-06-30 00:05:02', '2026-06-30 00:05:02'),
(39, 1, 'will', 'created', 'College', 2, 'Created college \"COLLEGE OF HEALTH SCIENCE\"', '2026-06-30 17:16:59', '2026-06-30 17:16:59'),
(40, 1, 'will', 'created', 'College', 3, 'Created college \"COLLEGE OF INFORMATION COMMUNICATION TECHNOLOGY\"', '2026-06-30 17:17:06', '2026-06-30 17:17:06'),
(41, 1, 'will', 'created', 'College', 4, 'Created college \"College of Education\" (bulk add)', '2026-06-30 17:17:48', '2026-06-30 17:17:48'),
(42, 1, 'will', 'created', 'College', 5, 'Created college \"College of Science\" (bulk add)', '2026-06-30 17:17:48', '2026-06-30 17:17:48'),
(43, 1, 'will', 'created', 'Device', 23, 'Added device \"2025-02\"', '2026-06-30 17:20:50', '2026-06-30 17:20:50'),
(44, 1, 'will', 'updated', 'Device', 23, 'Marked device \"2025-02\" as checked/maintained', '2026-06-30 17:57:30', '2026-06-30 17:57:30'),
(45, 1, 'will', 'updated', 'Device', 23, 'Marked device \"2025-02\" as checked/maintained', '2026-06-30 17:59:18', '2026-06-30 17:59:18'),
(46, 1, 'will', 'updated', 'Device', 13, 'Marked device \"2025-42\" as checked/maintained', '2026-06-30 18:02:49', '2026-06-30 18:02:49'),
(47, 1, 'will', 'updated', 'Device', 23, 'Marked device \"2025-02\" as checked/maintained', '2026-06-30 18:04:25', '2026-06-30 18:04:25'),
(48, 1, 'will', 'updated', 'Device', 23, 'Marked device \"2025-02\" as checked/maintained', '2026-06-30 18:55:34', '2026-06-30 18:55:34'),
(49, 1, 'will', 'updated', 'Device', 20, 'Marked device \"2025-45\" as checked with checklist', '2026-06-30 21:03:03', '2026-06-30 21:03:03'),
(50, 1, 'will', 'updated', 'Device', 23, 'Marked device \"2025-02\" as checked with checklist', '2026-06-30 21:23:34', '2026-06-30 21:23:34'),
(51, 1, 'will', 'created', 'Device', 24, 'Added device \"2025-57\"', '2026-06-30 22:06:36', '2026-06-30 22:06:36'),
(52, 1, 'will', 'updated', 'Device', 24, 'Marked device \"2025-57\" as checked with checklist', '2026-06-30 22:06:53', '2026-06-30 22:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `colleges`
--

INSERT INTO `colleges` (`id`, `name`, `code`, `created_at`, `updated_at`) VALUES
(2, 'COLLEGE OF HEALTH SCIENCE', 'CHS', '2026-06-30 17:16:59', '2026-06-30 17:16:59'),
(3, 'COLLEGE OF INFORMATION COMMUNICATION TECHNOLOGY', 'CICT', '2026-06-30 17:17:06', '2026-06-30 17:17:06'),
(4, 'College of Education', 'COED', '2026-06-30 17:17:48', '2026-06-30 17:17:48'),
(5, 'College of Science', 'COS', '2026-06-30 17:17:48', '2026-06-30 17:17:48');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_type_id` bigint(20) UNSIGNED NOT NULL,
  `property_number` varchar(255) NOT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `computer_name` varchar(100) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `mac_address` varchar(255) DEFAULT NULL,
  `unit_price` decimal(12,2) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `status` enum('available','issued','repair','retired') NOT NULL DEFAULT 'available',
  `os_version` varchar(255) DEFAULT NULL,
  `os_license` varchar(255) DEFAULT NULL,
  `ms_office_version` varchar(255) DEFAULT NULL,
  `ms_office_license` varchar(255) DEFAULT NULL,
  `condition` varchar(255) NOT NULL DEFAULT 'serviceable',
  `specs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`specs`)),
  `notes` text DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `maintenance_remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `device_type_id`, `property_number`, `serial_number`, `computer_name`, `brand`, `model`, `mac_address`, `unit_price`, `date_acquired`, `status`, `os_version`, `os_license`, `ms_office_version`, `ms_office_license`, `condition`, `specs`, `notes`, `last_maintenance_date`, `maintenance_remarks`, `created_at`, `updated_at`) VALUES
(2, 2, '2025-0', '1212123345', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-24 22:47:24', '2026-06-24 22:47:24'),
(3, 4, '2024-164-10405030-01-04-1589', '123', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-24 22:50:39', '2026-06-24 22:50:39'),
(4, 6, '2024-164-10405030-4', '123', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-24 22:50:57', '2026-06-24 22:50:57'),
(5, 5, '10-43', '42211', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:10:29', '2026-06-25 00:10:49'),
(6, 3, '12-12', '233112', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:11:09', '2026-06-25 00:11:09'),
(7, 7, '1-23', '4981', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:11:46', '2026-06-25 00:11:46'),
(8, 1, '2025-10', '11222113', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'unserviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:37:09', '2026-06-25 00:37:09'),
(9, 1, '123778-89', '76889', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'unserviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:37:58', '2026-06-25 00:37:58'),
(10, 1, '123778-789', '76889', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'unserviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:38:07', '2026-06-25 00:38:07'),
(11, 5, '666666666666-6', '4578', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-25 00:38:51', '2026-06-25 00:38:51'),
(12, 2, '2025-4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'available', 'Windows 8', 'OEM Licensed', 'Office 2016', 'Cracked', 'serviceable', NULL, NULL, NULL, NULL, '2026-06-26 00:34:49', '2026-06-28 21:40:57'),
(13, 1, '2025-42', '122890', NULL, NULL, NULL, NULL, NULL, NULL, 'available', 'Windows 7', 'OEM Licensed', 'Office 2021', 'OEM Licensed', 'serviceable', NULL, NULL, '2026-07-01', 'Checked/Maintained today', '2026-06-28 22:23:27', '2026-06-30 18:02:49'),
(14, 2, '2025-023', '123221', NULL, 'apol', 'mamamo', NULL, 1122.00, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', '{\"memory\":\"31gb\",\"storage\":\"1232ssd\",\"form_factor\":\"tower\"}', NULL, NULL, NULL, '2026-06-29 18:50:12', '2026-06-29 18:51:34'),
(15, 1, '2025-02319', '323790', NULL, 'ml', NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-29 18:52:28', '2026-06-29 18:59:52'),
(16, 2, '2025-0109', '1231112', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-29 19:08:50', '2026-06-29 19:08:50'),
(17, 6, '2025-012', '123214', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-29 19:21:40', '2026-06-29 19:21:40'),
(18, 4, '2025-022778', '123221095', NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-29 19:23:04', '2026-06-29 19:23:04'),
(19, 1, '2025-098', '1234532175', 'veeww', NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, NULL, NULL, '2026-06-29 19:27:29', '2026-06-29 19:40:32'),
(20, 2, '2025-45', '33322289', 'vee5be4', NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, '2026-07-01', 'Preventive maintenance checklist completed.', '2026-06-29 19:40:05', '2026-06-30 21:03:03'),
(21, 1, '1309-28', '1404', 'mkdnajb', NULL, NULL, NULL, NULL, NULL, 'available', 'Windows 10', 'OEM Licensed', 'Office 2016', 'Cracked', 'serviceable', '{\"form_factor\":\"All-in-One (AIO) Desktops\"}', NULL, NULL, NULL, '2026-06-29 22:34:39', '2026-06-29 22:34:39'),
(22, 1, '1498-94', '1409', NULL, NULL, NULL, NULL, 271474.00, NULL, 'available', 'Windows 7', 'Cracked', 'Office 2019', 'OEM Licensed', 'serviceable', '{\"form_factor\":\"Workstations\"}', NULL, '2026-06-30', 'Preventive maintenance checklist completed.', '2026-06-29 22:42:29', '2026-06-30 00:05:02'),
(23, 1, '2025-02', NULL, 'veewwty', NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, '2026-07-01', 'Preventive maintenance checklist completed.', '2026-06-30 17:20:50', '2026-06-30 21:23:34'),
(24, 4, '2025-57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'available', NULL, NULL, NULL, NULL, 'serviceable', NULL, NULL, '2026-07-01', 'Preventive maintenance checklist completed.', '2026-06-30 22:06:36', '2026-06-30 22:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `device_assignments`
--

CREATE TABLE `device_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `staff_id` bigint(20) UNSIGNED NOT NULL,
  `issued_by` bigint(20) UNSIGNED DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `returned_at` timestamp NULL DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `device_maintenance_records`
--

CREATE TABLE `device_maintenance_records` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `device_id` bigint(20) UNSIGNED NOT NULL,
  `maintenance_date` date NOT NULL,
  `maintenance_type` varchar(255) NOT NULL DEFAULT 'Checked',
  `remarks` text DEFAULT NULL,
  `checklist_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checklist_data`)),
  `corrective_action` text DEFAULT NULL,
  `checked_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `device_maintenance_records`
--

INSERT INTO `device_maintenance_records` (`id`, `device_id`, `maintenance_date`, `maintenance_type`, `remarks`, `checklist_data`, `corrective_action`, `checked_by`, `created_at`, `updated_at`) VALUES
(1, 22, '2026-06-30', 'Preventive Maintenance Checklist', 'Preventive maintenance checklist generated.\nSystem Unit - Check for power on: OK\nMonitor - Check display: Not OK\nKeyboard - Check keys: -\nMouse - Check mouse left/right buttons: -\nAVR/UPS - Check for power recovery: -\nPrinter - Check printout: -\nSetup Anti-Virus: -\nSystem Scan and Removal of Malicious Software: -', NULL, NULL, 1, '2026-06-30 00:05:02', '2026-06-30 00:05:02'),
(2, 23, '2026-07-01', 'Checked', 'Checked/Maintained today', NULL, NULL, 1, '2026-06-30 17:57:30', '2026-06-30 17:57:30'),
(3, 23, '2026-07-01', 'Checked', 'Checked/Maintained today', NULL, NULL, 1, '2026-06-30 17:59:18', '2026-06-30 17:59:18'),
(4, 13, '2026-07-01', 'Checked', 'Checked/Maintained today', NULL, NULL, 1, '2026-06-30 18:02:49', '2026-06-30 18:02:49'),
(5, 23, '2026-07-01', 'Checked', 'Checked/Maintained today', NULL, NULL, 1, '2026-06-30 18:04:25', '2026-06-30 18:04:25'),
(6, 23, '2026-07-01', 'Checked', 'Checked/Maintained today', NULL, NULL, 1, '2026-06-30 18:55:34', '2026-06-30 18:55:34'),
(7, 20, '2026-07-01', 'Checked', 'Preventive maintenance checklist completed.', NULL, NULL, 1, '2026-06-30 21:03:03', '2026-06-30 21:03:03'),
(8, 23, '2026-07-01', 'Checked', 'Preventive maintenance checklist completed.', NULL, NULL, 1, '2026-06-30 21:23:34', '2026-06-30 21:23:34'),
(9, 24, '2026-07-01', 'Checked', 'Preventive maintenance checklist completed.', '{\"hardware\":{\"system_unit_power_on\":\"OK\",\"monitor_display\":\"Not OK\",\"keyboard_keys\":\"Not OK\",\"mouse_buttons\":\"Not OK\",\"avr_ups_power_recovery\":\"Not OK\",\"printer_printout\":\"Not OK\"},\"software\":{\"setup_antivirus\":\"check\",\"system_scan_removal\":\"dash\"}}', NULL, 1, '2026-06-30 22:06:53', '2026-06-30 22:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `device_types`
--

CREATE TABLE `device_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `device_types`
--

INSERT INTO `device_types` (`id`, `name`, `slug`, `created_at`, `updated_at`) VALUES
(1, 'Desktop', 'desktop', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(2, 'Laptop', 'laptop', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(3, 'Printer', 'printer', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(4, 'Monitor', 'monitor', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(5, 'UPS', 'ups', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(6, 'AVR', 'avr', '2026-06-24 19:26:14', '2026-06-24 19:26:14'),
(7, 'Other', 'other', '2026-06-24 19:26:14', '2026-06-24 19:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_02_25_022341_add_role_to_users_table', 1),
(5, '2026_03_03_003442_create_colleges_table', 1),
(6, '2026_03_03_003453_create_offices_table', 1),
(7, '2026_03_03_003501_create_staff_table', 1),
(8, '2026_03_03_003508_create_device_types_table', 1),
(9, '2026_03_03_003514_create_devices_table', 1),
(10, '2026_03_03_003520_create_device_assignments_table', 1),
(11, '2026_03_04_060123_update_devices_table_structure', 1),
(12, '2026_05_11_000020_add_last_maintenance_fields_to_devices_table', 1),
(13, '2026_05_11_021036_create_device_maintenance_records_table', 1),
(14, '2026_05_13_065316_add_model_to_devices_table', 1),
(15, '2026_05_20_020152_add_specs_and_condition_to_devices_table', 1),
(16, '2026_05_20_023820_add_condition_and_specs_to_devices_table', 1),
(17, '2026_05_25_020631_add_serial_number_to_devices_table', 1),
(18, '2026_05_28_022303_create_password_reset_tokens_table', 2),
(19, '2026_06_25_000001_create_activity_logs_table', 2),
(20, '2026_06_26_010857_add_os_ms_office_to_devices_table', 3),
(21, '2026_06_30_000000_add_computer_name_to_devices_table', 4),
(22, '2026_07_01_000000_add_checklist_data_to_device_maintenance_records_table', 5);

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

CREATE TABLE `offices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `college_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`email`, `token`, `created_at`) VALUES
('tariowilliam8@gmail.com', '$2y$12$yZ8oJAkwSJip2.nJS9C8.OMlDbDsEDzE6MOD5HahmUsy14dRiUtz6', '2026-06-29 19:13:09');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('7aBkn76f5TwKKtAf9iMVW5i1qsAQ3B4tfL56IDP4', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoidno1WHhOZ0JrUzhFaVJaS0k4cjRnVjFHZ0dnVnMwY0VLYnZHSXZ6NiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NjQ6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9hZG1pbi9kZXZpY2VzP2NvbGxlZ2U9JmNvbmRpdGlvbj0mcT0mdHlwZT0iO3M6NToicm91dGUiO3M6MTk6ImFkbWluLmRldmljZXMuaW5kZXgiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO30=', 1782891002),
('GLVqJpPjoIlXh2sT4AN2OTrTWf6g7WdUMYOSB0qo', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.126.0 Chrome/148.0.7778.97 Electron/42.2.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWDJZMnVmZ1JscVdLdWVxSHBzSjhQQzJ5bWZRYmluVU54QWx6dnlZNSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjc6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1782882661);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `office_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'admin',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'will', 'qwerty@123', NULL, '$2y$12$mOcGLMFvKR1LuvmbHVBeLODIeewnW1sIQ1fK4Z8dQtsq9iBJ.UH9O', 'admin', NULL, '2026-06-24 19:25:45', '2026-06-24 19:25:45'),
(2, 'seven', 'qwerty@1gmail.com', NULL, '$2y$12$MYHafttJDWLT/8.q7CWDjOeWz9l/yDT4U.YGZ.4tWZaLpb3TTz9ES', 'custodian', NULL, '2026-06-28 16:59:11', '2026-06-28 16:59:11'),
(3, 'Krylar.', 'tariowilliam8@gmail.com', NULL, '$2y$12$Y0hdh5hUISxFOzLMA66IyOnrHa8GZlft8IV.YNT2gWMwCzL57fed2', 'custodian', NULL, '2026-06-28 23:51:43', '2026-06-28 23:51:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_user_id_foreign` (`user_id`),
  ADD KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  ADD KEY `activity_logs_created_at_index` (`created_at`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `colleges_code_unique` (`code`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `devices_property_number_unique` (`property_number`),
  ADD KEY `devices_device_type_id_status_index` (`device_type_id`,`status`);

--
-- Indexes for table `device_assignments`
--
ALTER TABLE `device_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_assignments_issued_by_foreign` (`issued_by`),
  ADD KEY `device_assignments_device_id_returned_at_index` (`device_id`,`returned_at`),
  ADD KEY `device_assignments_staff_id_returned_at_index` (`staff_id`,`returned_at`);

--
-- Indexes for table `device_maintenance_records`
--
ALTER TABLE `device_maintenance_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `device_maintenance_records_device_id_foreign` (`device_id`),
  ADD KEY `device_maintenance_records_checked_by_foreign` (`checked_by`);

--
-- Indexes for table `device_types`
--
ALTER TABLE `device_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_types_slug_unique` (`slug`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `offices_college_id_name_unique` (`college_id`,`name`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_office_id_last_name_index` (`office_id`,`last_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `device_assignments`
--
ALTER TABLE `device_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `device_maintenance_records`
--
ALTER TABLE `device_maintenance_records`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `device_types`
--
ALTER TABLE `device_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `devices`
--
ALTER TABLE `devices`
  ADD CONSTRAINT `devices_device_type_id_foreign` FOREIGN KEY (`device_type_id`) REFERENCES `device_types` (`id`);

--
-- Constraints for table `device_assignments`
--
ALTER TABLE `device_assignments`
  ADD CONSTRAINT `device_assignments_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `device_assignments_issued_by_foreign` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `device_assignments_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `device_maintenance_records`
--
ALTER TABLE `device_maintenance_records`
  ADD CONSTRAINT `device_maintenance_records_checked_by_foreign` FOREIGN KEY (`checked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `device_maintenance_records_device_id_foreign` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `offices`
--
ALTER TABLE `offices`
  ADD CONSTRAINT `offices_college_id_foreign` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff`
--
ALTER TABLE `staff`
  ADD CONSTRAINT `staff_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
