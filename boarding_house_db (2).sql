-- phpMyAdmin SQL Dump
-- version 4.8.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2025 at 04:31 PM
-- Server version: 10.1.32-MariaDB
-- PHP Version: 7.2.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `boarding_house_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `posted_by` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `content` text NOT NULL,
  `posted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `boarding_houses`
--

CREATE TABLE `boarding_houses` (
  `house_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `join_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `boarding_houses`
--

INSERT INTO `boarding_houses` (`house_id`, `landlord_id`, `name`, `address`, `join_code`, `created_at`, `description`) VALUES
(1, 1046, 'mark', 'lala', 'FK6YGU', '2025-04-24 13:18:10', 'marklala'),
(2, 1047, 'earl', 'purok 2 karaos sfads', '8OOO0B', '2025-04-30 08:05:35', ''),
(3, 1048, 'Layno\'s Boarding House', 'purok 2 layno street', '3VCR2D', '2025-05-19 06:35:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `landlord_verification`
--

CREATE TABLE `landlord_verification` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permit_number` varchar(50) NOT NULL,
  `permit_image` varchar(255) NOT NULL,
  `id_image` varchar(255) NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `landlord_verification`
--

INSERT INTO `landlord_verification` (`verification_id`, `user_id`, `permit_number`, `permit_image`, `id_image`, `verified_at`) VALUES
(4, 1, '', '', '', NULL),
(5, 1045, '23123', '68074e9c969b7.png', '68074e9c9734c.png', NULL),
(6, 1046, '993213', '6807530b8e8f9.png', '6807530b8f0cc.png', '2025-04-22 08:27:55'),
(7, 1048, '6242004', '68121fb04ca59.jpg', '68121fb04fe33.jpg', '2025-04-30 13:03:44');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`request_id`, `room_id`, `reported_by`, `assigned_to`, `title`, `description`, `status`, `priority`, `created_at`, `completed_at`) VALUES
(1, 3, 1049, NULL, 'sdfddf', 'eggeggrt', 'pending', 'medium', '2025-05-19 12:47:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('paid','pending','overdue') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `tenant_id`, `room_id`, `amount`, `payment_date`, `due_date`, `status`, `payment_method`, `reference_number`, `proof_image`) VALUES
(1, 1049, 3, '1300.00', '2025-05-19', '2025-06-19', 'paid', 'GCash', '374634535437263545', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL,
  `house_id` int(11) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `capacity` int(11) NOT NULL,
  `current_occupancy` int(11) DEFAULT '0',
  `price` decimal(10,2) NOT NULL,
  `description` text,
  `status` enum('available','occupied','maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `house_id`, `room_number`, `capacity`, `current_occupancy`, `price`, `description`, `status`) VALUES
(1, 1, '1', 6, 0, '1500.00', '', 'occupied'),
(2, 2, '1', 1, 0, '1500.00', '', 'occupied'),
(3, 3, '2', 1, 0, '1300.00', 'solo room', 'occupied');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_info`
--

CREATE TABLE `tenant_info` (
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_contact` varchar(20) NOT NULL,
  `emergency_contact` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tenant_info`
--

INSERT INTO `tenant_info` (`tenant_id`, `user_id`, `parent_contact`, `emergency_contact`) VALUES
(1, 1, '213123', '01630781'),
(2, 1047, '09150361493', '09150361493'),
(3, 1049, '09150361493', '09150361493');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_rooms`
--

CREATE TABLE `tenant_rooms` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(11) DEFAULT NULL,
  `move_in_date` date DEFAULT NULL,
  `move_out_date` date DEFAULT NULL,
  `status` enum('pending','active','past') DEFAULT 'pending',
  `house_id` int(11) DEFAULT NULL,
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tenant_rooms`
--

INSERT INTO `tenant_rooms` (`id`, `tenant_id`, `room_id`, `move_in_date`, `move_out_date`, `status`, `house_id`, `joined_at`) VALUES
(2, 1, 1, '0000-00-00', NULL, '', 1, '2025-04-24 22:12:19'),
(3, 1, 1, '0000-00-00', NULL, '', 1, '2025-04-24 22:16:16'),
(4, 1, 1, '0000-00-00', NULL, '', 1, '2025-04-24 22:58:52'),
(5, 1, 1, '0000-00-00', NULL, '', 1, '2025-04-24 23:06:03'),
(7, 1, 1, NULL, NULL, 'active', 1, '2025-04-24 23:43:20'),
(8, 1047, 2, NULL, NULL, 'active', 2, '2025-04-30 16:06:08'),
(9, 1049, 3, NULL, NULL, 'active', 3, '2025-05-19 14:38:27');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `tenant_id`, `room_id`, `payment_id`, `amount`, `transaction_date`, `description`) VALUES
(1, 1049, 3, 1, '1300.00', '2025-05-19', 'Rent payment');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `user_type` enum('landlord','tenant') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_verified` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `first_name`, `last_name`, `phone`, `user_type`, `created_at`, `is_verified`) VALUES
(1047, 'earl', '$2y$10$DhTurztRilsKXGHBjro1xuHHVxev1VzfP.qMzyCIjUXie1dAVO7xW', 'deluteearllawrence@gmail.com', 'Earl Lawrence', 'Delute', '09500130536', 'tenant', '2025-04-30 08:04:07', 1),
(1048, 'earllawrence', '$2y$10$k6F22cBCX5iiq9Ed2IOpAOTRPzEecnGIccQgFWCwGlX7UZhaEBIeO', 'deluteearllawrence1@gmail.com', 'Earl', 'Delute', '09500130536', 'landlord', '2025-04-30 13:02:54', 1),
(1049, 'try', '$2y$10$j2RwMsjeUNl2AWZ8rz690OUA.uppgWTCAFoP6Zt3mYPqzUw.5H/SW', 'try@gmail.com', 'Earl Lawrence', 'Delute', '09500130536', 'tenant', '2025-05-19 06:37:39', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `house_id` (`house_id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `boarding_houses`
--
ALTER TABLE `boarding_houses`
  ADD PRIMARY KEY (`house_id`),
  ADD UNIQUE KEY `join_code` (`join_code`),
  ADD KEY `landlord_id` (`landlord_id`);

--
-- Indexes for table `landlord_verification`
--
ALTER TABLE `landlord_verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD KEY `house_id` (`house_id`);

--
-- Indexes for table `tenant_info`
--
ALTER TABLE `tenant_info`
  ADD PRIMARY KEY (`tenant_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `fk_house_id` (`house_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `payment_id` (`payment_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boarding_houses`
--
ALTER TABLE `boarding_houses`
  MODIFY `house_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `landlord_verification`
--
ALTER TABLE `landlord_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tenant_info`
--
ALTER TABLE `tenant_info`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tenant_rooms`
--
ALTER TABLE `tenant_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1050;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`house_id`) REFERENCES `boarding_houses` (`house_id`),
  ADD CONSTRAINT `announcements_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`house_id`) REFERENCES `boarding_houses` (`house_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
