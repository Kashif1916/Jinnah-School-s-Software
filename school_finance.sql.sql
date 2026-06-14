-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 25, 2026 at 11:02 AM
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
-- Database: `school_finance`
--

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `father_name` varchar(100) NOT NULL,
  `class` varchar(50) NOT NULL,
  `section` varchar(10) NOT NULL,
  `monthly_fee` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `contact_number` varchar(15) DEFAULT NULL,
  `status` enum('active','dropped') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `father_name`, `class`, `section`, `monthly_fee`, `description`, `contact_number`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ali Abbbas', 'Muhammad Asif', '1', 'A', 2400.00, 'good', '031823545431', 'active', '2026-02-25 09:27:26', '2026-02-25 09:33:52'),
(2, 'Ateeq', 'yasir', '2', 'A', 2300.00, 'second student', '45453568742', 'active', '2026-02-25 09:28:06', '2026-02-25 09:28:06'),
(3, 'zulqarnain', 'haider', '3', 'A', 2200.00, 'third student', '45453568742', 'active', '2026-02-25 09:28:36', '2026-02-25 09:28:36'),
(4, 'ubaid', 'Rehman', '4', 'A', 2000.00, 'fourth student', '031823545431', 'active', '2026-02-25 09:29:04', '2026-02-25 09:29:04'),
(5, 'shoaib', 'Ramzan', '5', 'A', 2700.00, 'fifth student', '45453568742', 'active', '2026-02-25 09:29:32', '2026-02-25 09:29:32'),
(6, 'Mian', 'Muhammad', '6', 'A', 2300.00, 'sixth student', '031823545431', 'active', '2026-02-25 09:29:56', '2026-02-25 09:29:56'),
(7, 'najum', 'gull', '7', 'A', 2900.00, 'seventh Student', '45453568742', 'active', '2026-02-25 09:30:22', '2026-02-25 09:30:22'),
(8, 'kashif', 'Javed', '8', 'A', 2200.00, 'eight student', '45453568742', 'active', '2026-02-25 09:30:45', '2026-02-25 09:30:45'),
(9, 'Shayan', 'haider', '9', 'A', 2500.00, 'ninth student', '031823545431', 'active', '2026-02-25 09:31:19', '2026-02-25 09:31:19'),
(10, 'zeeshan', 'mian', '10', 'A', 2400.00, 'tenth student', '45453568742', 'active', '2026-02-25 09:31:49', '2026-02-25 09:31:49'),
(11, 'uzair', 'malik', '12', 'A', 1800.00, 'eleventh Student', '45453568742', 'active', '2026-02-25 09:32:21', '2026-02-25 09:59:30'),
(12, 'asfand', 'wali yar', '12', 'A', 1700.00, 'twelveth student', '031823545431', 'dropped', '2026-02-25 09:33:33', '2026-02-25 09:58:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_student_status` (`status`),
  ADD KEY `idx_student_class` (`class`,`section`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
