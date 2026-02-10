-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql311.infinityfree.com
-- Generation Time: Feb 10, 2026 at 10:17 AM
-- Server version: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41111172_soilmoisture_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `Administrator`
--

CREATE TABLE `Administrator` (
  `administrator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'admin',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Administrator`
--

INSERT INTO `Administrator` (`administrator_id`, `user_id`, `role`, `created_at`) VALUES
(1, 1, 'admin', '2026-02-10 12:40:54'),
(6, 2, 'admin', '2026-02-10 15:05:20');

-- --------------------------------------------------------

--
-- Table structure for table `History`
--

CREATE TABLE `History` (
  `id` int(11) NOT NULL,
  `time` timestamp NULL DEFAULT current_timestamp(),
  `temp` float DEFAULT NULL,
  `humidity` float DEFAULT NULL,
  `moisture` int(11) DEFAULT NULL,
  `soil` varchar(50) DEFAULT NULL,
  `water` varchar(50) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `History`
--

INSERT INTO `History` (`id`, `time`, `temp`, `humidity`, `moisture`, `soil`, `water`) VALUES
(1, '2026-02-10 12:25:05', 25.3, 49, 0, 'DRY', 'LOW'),
(2, '2026-02-10 12:25:24', 25.3, 49, 83, 'WET', 'LOW'),
(3, '2026-02-10 12:25:33', 25.3, 49, 0, 'DRY', 'LOW'),
(4, '2026-02-10 12:28:10', 24.8, 48, 89, 'WET', 'LOW'),
(5, '2026-02-10 12:28:23', 24.8, 48, 0, 'DRY', 'LOW'),
(6, '2026-02-10 12:31:43', 24.1, 47, 0, 'DRY', 'LOW'),
(7, '2026-02-10 12:31:52', 24.1, 47, 0, 'DRY', 'LOW'),
(8, '2026-02-10 12:33:02', 24.1, 47, 0, 'DRY', 'LOW'),
(9, '2026-02-10 12:33:25', 24.1, 47, 0, 'DRY', 'LOW'),
(10, '2026-02-10 12:34:20', 24.1, 47, 0, 'DRY', 'LOW'),
(11, '2026-02-10 12:34:39', 23.9, 47, 0, 'DRY', 'LOW'),
(12, '2026-02-10 12:38:46', 23.8, 46, 0, 'DRY', 'LOW'),
(13, '2026-02-10 13:01:14', 22.6, 43, 0, 'DRY', 'LOW'),
(14, '2026-02-10 13:01:40', 22.6, 43, 0, 'DRY', 'LOW'),
(15, '2026-02-10 13:03:34', 22.6, 43, 72, 'WET', 'LOW'),
(16, '2026-02-10 13:03:34', 22.6, 43, 72, 'WET', 'LOW'),
(17, '2026-02-10 13:03:45', 22.6, 43, 57, 'GOOD', 'LOW'),
(18, '2026-02-10 13:03:46', 22.6, 43, 57, 'GOOD', 'LOW'),
(19, '2026-02-10 13:03:54', 22.6, 43, 0, 'DRY', 'LOW'),
(20, '2026-02-10 13:03:55', 22.6, 43, 0, 'DRY', 'LOW'),
(21, '2026-02-10 13:04:24', 22.6, 43, 82, 'WET', 'LOW'),
(22, '2026-02-10 13:04:25', 22.6, 43, 82, 'WET', 'LOW'),
(23, '2026-02-10 13:04:46', 22.6, 43, 12, 'DRY', 'FULL'),
(24, '2026-02-10 13:04:48', 22.6, 43, 12, 'DRY', 'FULL'),
(25, '2026-02-10 13:04:57', 22.6, 43, 49, 'GOOD', 'LOW'),
(26, '2026-02-10 13:04:58', 22.6, 43, 49, 'GOOD', 'LOW'),
(27, '2026-02-10 13:05:09', 22.6, 43, 0, 'DRY', 'LOW'),
(28, '2026-02-10 13:05:10', 22.6, 43, 0, 'DRY', 'LOW'),
(29, '2026-02-10 13:08:21', 22.6, 43, 0, 'DRY', 'LOW'),
(30, '2026-02-10 13:08:45', 22.6, 43, 53, 'GOOD', 'LOW'),
(31, '2026-02-10 13:08:46', 22.6, 43, 53, 'GOOD', 'LOW'),
(32, '2026-02-10 13:08:57', 22.6, 43, 99, 'WET', 'LOW'),
(33, '2026-02-10 13:08:57', 22.6, 43, 99, 'WET', 'LOW'),
(34, '2026-02-10 13:09:01', 22.6, 43, 99, 'WET', 'LOW'),
(35, '2026-02-10 13:09:15', 22.6, 43, 0, 'DRY', 'FULL'),
(36, '2026-02-10 13:09:16', 22.6, 43, 0, 'DRY', 'FULL'),
(37, '2026-02-10 13:10:52', 22.6, 43, 100, 'WET', 'LOW'),
(38, '2026-02-10 13:11:13', 22.6, 43, 0, 'DRY', 'LOW');

-- --------------------------------------------------------

--
-- Table structure for table `Users`
--

CREATE TABLE `Users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `surname` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_initial` varchar(5) DEFAULT NULL,
  `date_created` timestamp NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `Users`
--

INSERT INTO `Users` (`user_id`, `email`, `password`, `surname`, `first_name`, `middle_initial`, `date_created`, `is_active`, `last_login`) VALUES
(1, 'anilovtw2003@gmail.com', '$2y$10$0br/yHM9KVbHt7hcB7r1EeUCh25Whbk6kXpjAtBI.PYJIm8fhYhsC', 'Salio', 'Anilov', '', '2026-02-10 12:38:40', 1, '2026-02-10 15:10:36'),
(2, 'Denniel@gmail.com', '$2y$10$jJ5VS6mN8H5iLnB8CXNPaO5fxtd.ap/Q6k./wVTavbz6biObcQuUW', 'Dela Cruz', 'Denniel', 'D', '2026-02-10 12:54:44', 1, '2026-02-10 15:06:12'),
(3, 'testanilov@gmail.com', '$2y$10$089i6eYapNCwU8PLOt/5G.3DkjuV.3zUU.jchbhTYSyPN0NprjGHW', 'test', 'test', 'test', '2026-02-10 13:03:26', 1, NULL),
(4, 'reysimon50@gmail.com', '$2y$10$9vnm2c1Q2mfmY0ilqNFY3OB6hdCVFlZN1O5WsdtzRu1ZGRXb80IPC', 'Balagtas', 'Rey', '', '2026-02-10 14:56:21', 1, '2026-02-10 14:56:23'),
(5, 'test@gmail.com', '$2y$10$V.n6Wg2Yu3z2vhV1uS1fxOukMdXvWEzfzXmfjsFrFv3mTl6/iOot.', 'Tester', 'User', '', '2026-02-10 14:57:34', 1, '2026-02-10 14:57:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Administrator`
--
ALTER TABLE `Administrator`
  ADD PRIMARY KEY (`administrator_id`),
  ADD UNIQUE KEY `unique_user_admin` (`user_id`);

--
-- Indexes for table `History`
--
ALTER TABLE `History`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_time` (`time`);

--
-- Indexes for table `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Administrator`
--
ALTER TABLE `Administrator`
  MODIFY `administrator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `History`
--
ALTER TABLE `History`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `Users`
--
ALTER TABLE `Users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
