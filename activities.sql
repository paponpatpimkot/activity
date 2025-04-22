-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2025 at 12:14 PM
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
-- Database: `act`
--

-- --------------------------------------------------------

--
-- Table structure for table `activities`
--

CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL,
  `activity_name` varchar(255) NOT NULL,
  `activity_type` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `organizer_hours` decimal(3,1) NOT NULL,
  `participant_hours` decimal(3,1) NOT NULL,
  `capacity` int(11) DEFAULT NULL,
  `allowed_departments` text DEFAULT NULL,
  `booking_deadline` datetime DEFAULT NULL,
  `is_booking_enabled` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activities`
--

INSERT INTO `activities` (`activity_id`, `activity_name`, `activity_type`, `start_date`, `end_date`, `start_time`, `end_time`, `location`, `organizer_hours`, `participant_hours`, `capacity`, `allowed_departments`, `booking_deadline`, `is_booking_enabled`, `description`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 'อบรมการเขียนโปรแกรม Python', 'อบรม', '2025-04-15', '2025-04-15', '09:00:00', '16:00:00', 'ห้องปฏิบัติการคอมพิวเตอร์ 1', 6.0, 6.0, 30, 'เทคโนโลยีสารสนเทศ', '2025-04-14 23:59:00', 1, 'อบรมการเขียนโปรแกรม Python เบื้องต้น', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49'),
(2, 'แข่งขันทักษะไฟฟ้า', 'แข่งขัน', '2025-04-20', '2025-04-21', '08:00:00', '17:00:00', 'โรงฝึกงานไฟฟ้า', 12.0, 12.0, 20, 'ไฟฟ้ากำลัง', '2025-04-19 23:59:00', 1, 'แข่งขันทักษะการต่อวงจรไฟฟ้า', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49'),
(3, 'สัมมนาเทคโนโลยีอิเล็กทรอนิกส์', 'สัมมนา', '2025-04-25', '2025-04-25', '10:00:00', '15:00:00', 'ห้องประชุมใหญ่', 5.0, 5.0, 50, 'อิเล็กทรอนิกส์', '2025-04-24 23:59:00', 1, 'สัมมนาเกี่ยวกับเทคโนโลยีอิเล็กทรอนิกส์สมัยใหม่', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49'),
(4, 'ซ่อมบำรุงรถจักรยานยนต์', 'กิจกรรมจิตอาสา', '2025-04-30', '2025-04-30', '09:00:00', '12:00:00', 'โรงฝึกงานช่างยนต์', 3.0, 3.0, 15, 'ช่างยนต์', '2025-04-29 23:59:00', 1, 'กิจกรรมซ่อมบำรุงรถจักรยานยนต์สำหรับชุมชน', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49'),
(5, 'อบรมการใช้โปรแกรมบัญชี', 'อบรม', '2025-05-05', '2025-05-05', '13:00:00', '16:00:00', 'ห้องปฏิบัติการคอมพิวเตอร์ 2', 3.0, 3.0, 25, 'คอมพิวเตอร์ธุรกิจ', '2025-05-04 23:59:00', 1, 'อบรมการใช้โปรแกรมบัญชีเบื้องต้น', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49'),
(6, 'กิจกรรมกีฬาสี', 'กิจกรรมกีฬา', '2025-05-10', '2025-05-12', '08:00:00', '17:00:00', 'สนามกีฬาวิทยาลัย', 24.0, 24.0, 100, 'เทคโนโลยีสารสนเทศ,ไฟฟ้ากำลัง,อิเล็กทรอนิกส์,ช่างยนต์,คอมพิวเตอร์ธุรกิจ', '2025-05-09 23:59:00', 1, 'กิจกรรมกีฬาสีประจำปี', 1, '2025-04-08 14:16:49', '2025-04-08 14:16:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activities`
--
ALTER TABLE `activities`
  ADD PRIMARY KEY (`activity_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activities`
--
ALTER TABLE `activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
