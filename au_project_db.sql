-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 03, 2026 at 06:36 PM
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
-- Database: `au_project_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Client','Developer') NOT NULL,
  `profile_image` mediumblob DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `profile_image`, `facebook_url`, `instagram_url`) VALUES
(1, 'john', 'john1@gmail.com', 'pogiako', 'Client', NULL, NULL, NULL),
(2, 'dean', 'dean@gmail.com', '$2y$10$Jpg9WvhA4z845nJ1pTYoV.dU6lncOXQw.VYIIBs/zQ8gK43bM0DS6', 'Developer', NULL, NULL, NULL),
(3, 'luis', 'luis123@email.com', '$2y$10$Y1cViRxHfd2ddMGI4lx.tOrNAub4IEUJCR3f1.r7CEH4FNI8XDDqu', 'Developer', NULL, NULL, NULL),
(4, 'joyce123', 'joyce@gmail.com', '$2y$10$lkgx9AnimEKTV9XPg08O3ONw688ZroekKsna5QbJF9D2emwDThfM6', 'Developer', NULL, NULL, NULL),
(5, 'emman', 'emman@email.com', '$2y$10$IbD3uJp6XiiE8VAtuKfUOuUIA4Vv4xtOjXoUEjgGs8aien1xxWu5O', 'Client', NULL, NULL, NULL),
(6, 'daniella', 'daniella@gmail.com', '$2y$10$8fdJe.qfCB3.XvkyiGON7e5w4O.nujkzfGFvaDkS5xUECRH5sc6uG', 'Developer', NULL, NULL, NULL),
(7, 'dean222', 'dean222@gmail.com', '$2y$10$QwBoS1gKoq8fJOqxe62XhugN2YZbhnM4EtfxFcO30qntpGOIauDUy', 'Client', NULL, NULL, NULL),
(8, 'test123', 'test123@gmail.com', '$2y$10$IvsdjKMTPmeVqDYP6PaiaODauuAf0BacWGioOTvCoVukE31h/9lp6', 'Developer', NULL, NULL, NULL),
(9, 'john', 'test321@gmail.com', '$2y$10$v1eE6PUQoEhNNQB0bPE/POMLXFH5C0DzSox45VLBo/.DU/DdbDHWi', 'Client', NULL, '', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
