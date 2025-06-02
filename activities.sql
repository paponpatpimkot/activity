-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 01, 2025 at 05:32 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `activity_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'ชื่อกิจกรรม',
  `description` text DEFAULT NULL COMMENT 'รายละเอียดกิจกรรม',
  `start_datetime` datetime NOT NULL COMMENT 'วันเวลาเริ่มต้นกิจกรรม',
  `end_datetime` datetime NOT NULL COMMENT 'วันเวลาสิ้นสุดกิจกรรม',
  `location` varchar(255) DEFAULT NULL COMMENT 'สถานที่จัดกิจกรรม',
  `organizer_unit_id` int(10) UNSIGNED NOT NULL COMMENT 'FK อ้างอิง activity_units.id',
  `hours_organizer` decimal(4,1) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT 'ชั่วโมงสำหรับทีมงานผู้จัด',
  `hours_participant` decimal(4,1) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT 'ชั่วโมงสำหรับผู้เข้าร่วมทั่วไป',
  `penalty_hours` decimal(4,1) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT 'ชั่วโมงที่จะหักหากไม่เข้าร่วม',
  `max_participants` int(10) UNSIGNED DEFAULT NULL COMMENT 'จำนวนผู้เข้าร่วมสูงสุด (null คือไม่จำกัด)',
  `attendance_recorder_type` enum('system','advisor') NOT NULL DEFAULT 'system' COMMENT 'ประเภทของผู้มีสิทธิ์เช็คชื่อ: system (Admin/Staff), advisor',
  `created_by_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK อ้างอิง users.id (ผู้สร้างกิจกรรม - admin/staff)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ตารางเก็บรายละเอียดกิจกรรม';

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`id`, `name`, `description`, `start_datetime`, `end_datetime`, `location`, `organizer_unit_id`, `hours_organizer`, `hours_participant`, `penalty_hours`, `max_participants`, `attendance_recorder_type`, `created_by_user_id`, `created_at`, `updated_at`) VALUES
(0, 'ให้อาหารแมวจร', 'แมวเหมียวสองตัว ที่หน้าแผนก', '2025-05-08 13:07:00', '2025-05-22 13:07:00', 'หน้าตึกเอ', 1, 0.0, 0.0, 0.0, 0, 'system', 1, '0000-00-00 00:00:00', '2025-05-15 06:09:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_unit_id` (`organizer_unit_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`),
  ADD KEY `idx_activity_start_datetime` (`start_datetime`),
  ADD KEY `idx_activity_end_datetime` (`end_datetime`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
