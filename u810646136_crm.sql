-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 08, 2026 at 12:03 AM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u810646136_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_title` varchar(100) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `bank_accounts`
--

INSERT INTO `bank_accounts` (`id`, `bank_name`, `account_title`, `account_number`, `status`, `created_at`) VALUES
(1, 'HBL', 'ALI Kamal (sadapay)', '03112309012', 'Active', '2026-07-21 19:00:40'),
(2, 'HBL', 'Ali Kamal', '0311239012', 'Active', '2026-07-21 19:33:35');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `raw_password_text` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `assigned_agent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `total_balance_due` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `full_name`, `email`, `username`, `password`, `raw_password_text`, `phone`, `address`, `assigned_agent_id`, `created_at`, `total_balance_due`) VALUES
(1, 'ali', 'ali.kamaloficial24@gmail.com', 'ali', '$2y$10$Fx.yDAwUHzBtI/LA9pmlQ.6K/Muh42w8AxpK8KHs.qviyae3B224e', 'ali1122', '03112309012', 'Sahiwal', 1, '2026-07-08 18:25:00', 392723.00),
(10, 'Lahore', 'lahore1234@gmail.com', 'lahore12', '$2y$10$0DYQmUl2LG./Kfq3qNKZkumz/fBOkOwu7XLBIqsW71xb.Rgf6Zbs6', 'lahore1122', '03112309012', NULL, NULL, '2026-07-09 14:37:50', 126185.00),
(21, 'multan', 'multan@gmail.com', 'multan', '$2y$10$cV0ECQ/HahgK6AonZi7xH.X3uUnGbK0z5x36S2EnU1vqw58lPJWai', 'multan', '03112309012', NULL, NULL, '2026-07-09 15:17:42', 99776.00),
(2023, 'Ashir traders', 'ashirtraders@gmail.com', 'Ashir', '$2y$10$LbUV9cK3jidn126ZpGty7eEmR7O/F0YxagQa1WB5ilE/Xl1p2y0Jm', 'Ashir', '03171377634', NULL, NULL, '2026-07-11 11:46:48', 202206.50),
(2024, 'arif', 'aliwazir0551@gmail.com', '123wqe', '$2y$10$begtJ3pC5xOGmUBH0gr3leXE4u4gODTCklCo58cV604eQ985BxJm2', '12435ewe', '677890908621', NULL, NULL, '2026-08-11 09:16:44', 1073800.15);

-- --------------------------------------------------------

--
-- Table structure for table `customer_payments`
--

CREATE TABLE `customer_payments` (
  `id` int(11) NOT NULL,
  `payment_group_id` bigint(20) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_method` enum('Cash','Bank Transfer','Bank Cheque') NOT NULL DEFAULT 'Cash',
  `bank_account_id` int(11) DEFAULT NULL,
  `cheque_number` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `status` enum('Cleared','Hold','Bounced','Cancelled') NOT NULL DEFAULT 'Cleared',
  `payment_date` datetime DEFAULT current_timestamp(),
  `notes` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `customer_payments`
--

INSERT INTO `customer_payments` (`id`, `payment_group_id`, `customer_id`, `payment_method`, `bank_account_id`, `cheque_number`, `cheque_date`, `amount_paid`, `status`, `payment_date`, `notes`, `user_id`) VALUES
(1, NULL, 21, 'Bank Transfer', 1, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 19:01:20', 'Bank Transfer', 1),
(2, NULL, 21, 'Cash', NULL, NULL, NULL, 100000.00, 'Cleared', '2026-07-21 19:01:44', 'By cash', 1),
(3, NULL, 1, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 19:33:50', '', 1),
(4, NULL, 1, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 19:34:28', '', 1),
(5, NULL, 1, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 19:36:26', '', 1),
(6, NULL, 1, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 19:37:54', '', 1),
(7, NULL, 10, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-21 20:02:25', '', 1),
(8, NULL, 1, 'Cash', NULL, NULL, NULL, 500.00, 'Cleared', '2026-07-25 22:57:15', '', 1),
(9, NULL, 1, 'Bank Transfer', 1, NULL, NULL, 1000.00, 'Cleared', '2026-07-25 22:57:15', '', 1),
(10, NULL, 1, 'Cash', NULL, NULL, NULL, 50000.00, 'Cleared', '2026-07-25 23:10:56', '', 1),
(11, 2147483647, 2023, '', 1, NULL, '2026-07-31', 50000.00, 'Hold', '2026-07-25 23:27:26', '', 1),
(12, 2147483647, 2023, 'Bank Transfer', 2, NULL, NULL, 50000.00, 'Cleared', '2026-07-25 23:27:26', '', 1),
(13, 2147483647, 2023, '', 1, '2222', '2026-08-25', 20000.00, 'Hold', '2026-07-25 23:49:10', '', 1),
(14, 2147483647, 2023, '', 1, '2222', '2026-08-25', 20000.00, 'Hold', '2026-07-25 23:49:27', '', 1),
(15, 2147483648, 10, '', 2, '0808', '2026-08-01', 20000.00, 'Hold', '2026-07-25 23:54:44', 'Dicussed the Current situations.', 1),
(16, 2147483649, 1, '', 1, '2222', '2026-08-01', 20000.00, 'Hold', '2026-07-26 00:00:32', 'adada', 1),
(17, 2147483650, 1, 'Cash', NULL, NULL, NULL, 1000.00, 'Cleared', '2026-07-26 00:02:05', '', 1),
(18, 2147483650, 1, 'Bank Transfer', 1, NULL, NULL, 50000.00, 'Cleared', '2026-07-26 00:02:05', '', 1),
(19, 2147483650, 1, '', 2, '2020', '2026-08-12', 5000.00, 'Hold', '2026-07-26 00:02:05', '', 1),
(20, 2147483651, 10, '', 2, '0808', '2026-08-07', 5000.00, 'Hold', '2026-07-26 00:06:04', 'Dicussed the Current situations.', 1),
(21, 2147483652, 2023, '', 1, '2323', '2026-07-26', 4000.00, 'Cleared', '2026-07-26 00:07:13', '', 1),
(22, 2147483653, 1, 'Bank Cheque', 1, '20202', '2026-07-30', 2000.00, 'Bounced', '2026-07-26 00:18:45', '', 1),
(23, 2147483654, 10, 'Cash', NULL, NULL, NULL, 50000.00, 'Cleared', '2026-07-30 12:12:57', '', 1),
(24, 2147483655, 10, 'Cash', NULL, NULL, NULL, 5000.00, 'Cleared', '2026-07-30 12:13:34', '', 1),
(25, 2147483655, 10, 'Bank Transfer', 1, NULL, NULL, 20000.00, 'Cleared', '2026-07-30 12:13:34', '', 1),
(26, 2147483656, 21, 'Bank Transfer', 1, NULL, NULL, 50000.00, 'Cleared', '2026-08-11 08:53:27', '', 1),
(27, 2147483656, 21, 'Cash', NULL, NULL, NULL, 30000.00, 'Cleared', '2026-08-11 08:53:27', '', 1),
(28, 2147483656, 21, 'Bank Cheque', 1, '09320', '2026-08-12', 20000.00, 'Cleared', '2026-08-11 08:53:27', '', 1),
(29, 2147483657, 1, 'Cash', NULL, NULL, NULL, 50000.00, 'Cleared', '2026-08-11 09:21:38', '', 1),
(30, 2147483658, 1, 'Cash', NULL, NULL, NULL, 50000.00, 'Cleared', '2026-08-11 09:21:39', '', 1),
(31, 2147483659, 2024, 'Cash', NULL, NULL, NULL, 49999.85, 'Cleared', '2026-08-11 09:21:58', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `interactions`
--

CREATE TABLE `interactions` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `type` enum('Call','Email','Meeting') NOT NULL,
  `notes` text NOT NULL,
  `interaction_date` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interactions`
--

INSERT INTO `interactions` (`id`, `customer_id`, `staff_id`, `type`, `notes`, `interaction_date`) VALUES
(1, 1, 1, 'Call', 'Dicussed the Current situations.', '2026-07-08 18:30:49'),
(2, 10, 1, 'Meeting', 'adada', '2026-07-11 01:41:20'),
(3, 2023, 1, 'Meeting', 'Cvjkjx', '2026-07-11 11:53:23'),
(5, 21, 1, '', 'Shipment Request #REQ-11 Accepted by admin (Admin). Note: sasd', '2026-07-25 22:54:13'),
(6, 21, 1, '', 'Shipment Request #REQ-12 Declined by admin (Admin). Reason: No ka avao', '2026-08-11 09:13:19');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `available_stock` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fair_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `product_name`, `sku`, `available_stock`, `unit_price`, `fair_price`, `updated_at`, `price`) VALUES
(1, 'Super Biscuit Rs20', '24*12', 769, 1670.00, 0.00, '2026-08-19 15:26:10', 0.00),
(2, 'Zeera biscuit', '1*24*12', 561, 1200.00, 0.00, '2026-08-11 09:18:28', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `status` enum('New','In-Progress','Converted') DEFAULT 'New',
  `source` varchar(50) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `customer_name`, `email`, `status`, `source`, `assigned_to`, `created_at`) VALUES
(1, 'Abdullah', 'abdullahkamal.gamerak47@gmail.com', 'Converted', 'Direct Call', 1, '2026-07-08 18:25:41');

-- --------------------------------------------------------

--
-- Table structure for table `product_shipments`
--

CREATE TABLE `product_shipments` (
  `id` int(11) NOT NULL,
  `shipment_group_id` varchar(50) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_sent` int(11) NOT NULL,
  `total_bill` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipment_date` timestamp NULL DEFAULT current_timestamp(),
  `truck_number` varchar(50) DEFAULT NULL,
  `builty_no` varchar(50) DEFAULT NULL,
  `driver_no` varchar(50) DEFAULT NULL,
  `transport_goods_name` varchar(100) DEFAULT NULL,
  `loading_price` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_shipments`
--

INSERT INTO `product_shipments` (`id`, `shipment_group_id`, `customer_id`, `product_id`, `quantity_sent`, `total_bill`, `amount_paid`, `shipment_date`, `truck_number`, `builty_no`, `driver_no`, `transport_goods_name`, `loading_price`) VALUES
(1, NULL, 1, 1, 20, 33400.00, 0.00, '2026-07-08 18:53:52', NULL, NULL, NULL, NULL, 0.00),
(2, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-08 19:08:16', NULL, NULL, NULL, NULL, 0.00),
(3, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-08 19:11:32', NULL, NULL, NULL, NULL, 0.00),
(4, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-08 19:11:40', NULL, NULL, NULL, NULL, 0.00),
(5, NULL, 1, 1, 4, 6680.00, 2000.00, '2026-07-08 19:20:26', NULL, NULL, NULL, NULL, 0.00),
(6, NULL, 1, 1, 4, 6680.00, 2000.00, '2026-07-08 19:23:59', NULL, NULL, NULL, NULL, 0.00),
(7, NULL, 1, 1, 4, 6680.00, 2000.00, '2026-07-08 19:24:03', NULL, NULL, NULL, NULL, 0.00),
(8, NULL, 1, 1, 10, 16700.00, 0.00, '2026-07-08 19:29:01', NULL, NULL, NULL, NULL, 0.00),
(9, NULL, 1, 1, 3, 5010.00, 0.00, '2026-07-08 19:29:59', NULL, NULL, NULL, NULL, 0.00),
(10, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-08 19:29:59', NULL, NULL, NULL, NULL, 0.00),
(11, NULL, 1, 1, 3, 5010.00, 0.00, '2026-07-08 19:34:43', NULL, NULL, NULL, NULL, 0.00),
(12, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-08 19:34:43', NULL, NULL, NULL, NULL, 0.00),
(13, NULL, 10, 1, 2, 3340.00, 0.00, '2026-07-10 19:35:06', NULL, NULL, NULL, NULL, 0.00),
(14, NULL, 10, 1, 3, 5010.00, 0.00, '2026-07-10 19:35:06', NULL, NULL, NULL, NULL, 0.00),
(15, NULL, 10, 1, 2, 3340.00, 0.00, '2026-07-10 19:35:06', NULL, NULL, NULL, NULL, 0.00),
(16, NULL, 10, 1, 3, 5010.00, 0.00, '2026-07-10 19:35:06', NULL, NULL, NULL, NULL, 0.00),
(17, NULL, 1, 1, 180, 300600.00, 50000.00, '2026-07-11 11:42:09', NULL, NULL, NULL, NULL, 0.00),
(18, NULL, 2023, 1, 15, 25050.00, 1000.00, '2026-07-11 11:48:55', NULL, NULL, NULL, NULL, 0.00),
(19, NULL, 2023, 2, 15, 25500.00, 1000.00, '2026-07-11 11:48:55', NULL, NULL, NULL, NULL, 0.00),
(20, NULL, 10, 1, 6, 10020.00, 0.00, '2026-07-13 12:57:55', NULL, NULL, NULL, NULL, 0.00),
(21, NULL, 2023, 1, 1, 1670.00, 500.00, '2026-07-13 13:47:33', NULL, NULL, NULL, NULL, 0.00),
(22, NULL, 2023, 2, 1, 1700.00, 500.00, '2026-07-13 13:47:33', NULL, NULL, NULL, NULL, 0.00),
(23, NULL, 2023, 2, 1, 1700.00, 500.00, '2026-07-13 13:47:33', NULL, NULL, NULL, NULL, 0.00),
(24, NULL, 2023, 1, 1, 1670.00, 500.00, '2026-07-13 13:47:33', NULL, NULL, NULL, NULL, 0.00),
(25, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-14 09:34:22', NULL, NULL, NULL, NULL, 0.00),
(26, NULL, 1, 2, 1, 1700.00, 0.00, '2026-07-14 09:34:22', NULL, NULL, NULL, NULL, 0.00),
(27, NULL, 2023, 2, 1, 1700.00, 0.00, '2026-07-15 00:33:27', NULL, NULL, NULL, NULL, 0.00),
(28, NULL, 1, 2, 1, 1700.00, 0.00, '2026-07-15 00:34:07', NULL, NULL, NULL, NULL, 0.00),
(29, NULL, 1, 2, 1, 1700.00, 500.00, '2026-07-15 00:40:36', NULL, NULL, NULL, NULL, 0.00),
(30, NULL, 1, 2, 5, 6000.00, 0.00, '2026-07-15 00:48:41', NULL, NULL, NULL, NULL, 0.00),
(31, NULL, 1, 2, 1, 1140.00, 200.00, '2026-07-15 01:02:43', NULL, NULL, NULL, NULL, 0.00),
(32, NULL, 2023, 1, 1, 1670.00, 0.00, '2026-07-15 14:44:38', NULL, NULL, NULL, NULL, 0.00),
(33, NULL, 2023, 2, 1, 1200.00, 0.00, '2026-07-15 14:44:38', NULL, NULL, NULL, NULL, 0.00),
(34, NULL, 1, 1, 10, 15030.00, 10000.00, '2026-07-16 09:31:15', NULL, NULL, NULL, NULL, 0.00),
(35, NULL, 1, 1, 1, 1670.00, 0.00, '2026-07-18 13:17:05', NULL, NULL, NULL, NULL, 0.00),
(36, NULL, 1, 2, 1, 1200.00, 0.00, '2026-07-18 13:17:05', NULL, NULL, NULL, NULL, 0.00),
(37, NULL, 21, 1, 20, 33066.00, 25000.00, '2026-07-19 14:56:17', NULL, NULL, NULL, NULL, 0.00),
(38, NULL, 21, 2, 200, 237600.00, 25000.00, '2026-07-19 14:56:17', NULL, NULL, NULL, NULL, 0.00),
(39, NULL, 1, 1, 10, 16366.00, 20000.00, '2026-07-21 20:04:16', NULL, NULL, NULL, NULL, 0.00),
(40, NULL, 1, 2, 10, 11760.00, 20000.00, '2026-07-21 20:04:16', NULL, NULL, NULL, NULL, 0.00),
(41, NULL, 2023, 1, 10, 16700.00, 0.00, '2026-07-22 01:33:41', NULL, NULL, NULL, NULL, 0.00),
(42, NULL, 2023, 2, 15, 18000.00, 0.00, '2026-07-22 01:33:41', NULL, NULL, NULL, NULL, 0.00),
(43, NULL, 1, 1, 5, 8350.00, 0.00, '2026-07-23 12:44:59', NULL, NULL, NULL, NULL, 0.00),
(44, NULL, 1, 2, 15, 18000.00, 0.00, '2026-07-23 12:44:59', NULL, NULL, NULL, NULL, 0.00),
(45, NULL, 2023, 1, 10, 19609.41, 10000.00, '2026-07-25 22:12:00', NULL, NULL, NULL, NULL, 0.00),
(46, NULL, 2023, 2, 10, 14090.59, 10000.00, '2026-07-25 22:12:00', NULL, NULL, NULL, NULL, 0.00),
(47, 'GRP-20260725221551-297', 2023, 1, 10, 19609.41, 10000.00, '2026-07-25 22:15:51', NULL, NULL, NULL, NULL, 0.00),
(48, 'GRP-20260725221551-297', 2023, 2, 10, 14090.59, 10000.00, '2026-07-25 22:15:51', NULL, NULL, NULL, NULL, 0.00),
(49, 'GRP-20260725221721-468', 1, 1, 10, 16932.75, 1500.00, '2026-07-25 22:17:21', NULL, NULL, NULL, NULL, 0.00),
(50, 'GRP-20260725221721-468', 1, 2, 10, 12167.25, 1500.00, '2026-07-25 22:17:21', NULL, NULL, NULL, NULL, 0.00),
(51, 'INV-20260725-310', 1, 1, 10, 16932.75, 1500.00, '2026-07-25 22:22:22', NULL, NULL, NULL, NULL, 0.00),
(52, 'INV-20260725-310', 1, 2, 10, 12167.25, 1500.00, '2026-07-25 22:22:22', NULL, NULL, NULL, NULL, 0.00),
(53, 'INV-20260725-576', 1, 1, 2, 3742.06, 2000.00, '2026-07-25 22:24:02', NULL, NULL, NULL, NULL, 0.00),
(54, 'INV-20260725-576', 1, 2, 10, 13444.54, 2000.00, '2026-07-25 22:24:02', NULL, NULL, NULL, NULL, 0.00),
(55, 'INV-20260725-120', 1, 1, 2, 3742.06, 2000.00, '2026-07-25 22:24:43', NULL, NULL, NULL, NULL, 0.00),
(56, 'INV-20260725-120', 1, 2, 10, 13444.54, 2000.00, '2026-07-25 22:24:43', NULL, NULL, NULL, NULL, 0.00),
(57, 'INV-20260725-649', 1, 1, 2, 3742.06, 2000.00, '2026-07-25 22:25:01', NULL, NULL, NULL, NULL, 0.00),
(58, 'INV-20260725-649', 1, 2, 10, 13444.54, 2000.00, '2026-07-25 22:25:01', NULL, NULL, NULL, NULL, 0.00),
(59, 'INV-20260725-131', 1, 1, 10, 18941.41, 1500.00, '2026-07-25 22:30:35', NULL, NULL, NULL, NULL, 0.00),
(60, 'INV-20260725-131', 1, 2, 10, 13610.59, 1500.00, '2026-07-25 22:30:35', NULL, NULL, NULL, NULL, 0.00),
(61, 'INV-20260725-460', 1, 2, 10, 13432.47, 100.00, '2026-07-25 22:33:25', NULL, NULL, NULL, NULL, 0.00),
(62, 'INV-20260725-460', 1, 1, 10, 18693.53, 100.00, '2026-07-25 22:33:25', NULL, NULL, NULL, NULL, 0.00),
(63, 'INV-20260726-409', 1, 2, 100, 122948.40, 0.00, '2026-07-26 11:30:21', NULL, NULL, NULL, NULL, 0.00),
(64, 'INV-20260726-409', 1, 1, 50, 85551.60, 0.00, '2026-07-26 11:30:21', NULL, NULL, NULL, NULL, 0.00),
(65, 'INV-20260726-307', 1, 1, 1, 1671.83, 0.00, '2026-07-26 12:57:34', NULL, NULL, NULL, NULL, 0.00),
(66, 'INV-20260726-307', 1, 2, 100, 120131.37, 0.00, '2026-07-26 12:57:34', NULL, NULL, NULL, NULL, 0.00),
(67, 'INV-20260730-187', 10, 1, 50, 113438.96, 25000.00, '2026-07-30 12:10:55', NULL, NULL, NULL, NULL, 0.00),
(68, 'INV-20260730-187', 10, 2, 100, 163026.04, 25000.00, '2026-07-30 12:10:55', NULL, NULL, NULL, NULL, 0.00),
(69, 'INV-20260811-765', 2024, 2, 100, 128800.00, 5000.00, '2026-08-11 09:18:28', NULL, NULL, NULL, NULL, 0.00),
(70, 'INV-20260819-697', 2023, 1, 100, 177100.00, 4000.00, '2026-08-19 15:26:10', 'Que-7812', 'BLY-1', '0200121212', 'Quetta transport', 200.00);

-- --------------------------------------------------------

--
-- Table structure for table `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_performed` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `logged_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `security_logs`
--

INSERT INTO `security_logs` (`id`, `user_id`, `action_performed`, `ip_address`, `user_agent`, `logged_at`) VALUES
(1, 1, 'Updated Inventory Stock for product: Super Biscuit Rs20', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 18:53:39'),
(2, 1, 'Dispatched 20 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 18:53:52'),
(3, 1, 'Dispatched 1 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:08:16'),
(4, 1, 'Dispatched 1 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:11:32'),
(5, 1, 'Dispatched 1 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:11:40'),
(6, 1, 'Dispatched 4 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:20:26'),
(7, 1, 'Dispatched 4 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:23:59'),
(8, 1, 'Dispatched 4 units of Super Biscuit Rs20 to Client ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:24:03'),
(9, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.16700', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:29:01'),
(10, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.6346', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:29:59'),
(11, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.6346', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-08 19:34:43'),
(12, 1, 'Processed multi-item dispatch invoice for Client ID: 10. Net: Rs.7850', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 19:35:06'),
(13, 1, 'Processed multi-item dispatch invoice for Client ID: 10. Net: Rs.7850', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-10 19:35:06'),
(14, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.294588', '103.131.212.181', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-11 11:42:09'),
(15, 1, 'Processed multi-item dispatch invoice for Client ID: 2023. Net: Rs.49033.5', '103.131.212.135', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-11 11:48:55'),
(16, 1, 'Processed multi-item dispatch invoice for Client ID: 10. Net: Rs.9720', '103.131.212.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-07-13 12:57:55'),
(17, 1, 'Processed multi-item dispatch invoice for Client ID: 2023. Net: Rs.6403', '103.131.213.239', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '2026-07-13 13:47:33'),
(18, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.3370', '223.123.15.115', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-14 09:34:22'),
(19, 1, 'Processed multi-item dispatch invoice for Client ID: 2023. Net: Rs.1700', '103.131.214.20', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 00:33:27'),
(20, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.1700', '103.131.214.20', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 00:34:07'),
(21, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.1666', '103.131.214.80', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 00:40:36'),
(22, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.5700', '103.131.214.155', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 00:48:41'),
(23, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.1140', '103.131.214.243', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 01:02:43'),
(24, 1, 'Processed multi-item dispatch invoice for Client ID: 2023. Net: Rs.2870', '223.123.20.202', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 14:44:38'),
(25, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.15030', '223.123.7.203', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-16 09:31:15'),
(26, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.2870', '103.131.213.131', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-07-18 13:17:05'),
(27, 1, 'Processed multi-item dispatch invoice for Client ID: 21. Net: Rs.270666', '103.131.214.107', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-19 14:56:17'),
(28, 1, 'Recorded payment of Rs.50000 for Customer ID: 21', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:01:20'),
(29, 1, 'Recorded payment of Rs.100000 for Customer ID: 21', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:01:44'),
(30, 1, 'Recorded payment of Rs.50000 for Customer ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:33:50'),
(31, 1, 'Recorded payment of Rs.50000 for Customer ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:34:28'),
(32, 1, 'Recorded payment of Rs.50000 for Customer ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:36:26'),
(33, 1, 'Recorded payment of Rs.50000 for Customer ID: 1', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 19:37:54'),
(34, 1, 'Recorded payment of Rs.50000 for Customer ID: 10', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 20:02:25'),
(35, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.28126', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 20:04:16'),
(36, 1, 'Processed multi-item dispatch invoice for Client ID: 2023. Net: Rs.34700', '144.48.134.208', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 01:33:41'),
(37, 1, 'Processed multi-item dispatch invoice for Client ID: 1. Net: Rs.26350', '223.123.2.199', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-23 12:44:59'),
(38, 1, 'Processed dispatch invoice for Client ID: 2023. Net: Rs.33700 | Fair Price: Rs.10000', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 22:12:00'),
(39, 1, 'Processed multi-item shipment (GRP-20260725221551-297) for Client ID: 2023. Net: Rs.33700', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 22:15:51'),
(40, 1, 'Processed multi-item shipment (GRP-20260725221721-468) for Client ID: 1. Net: Rs.29100', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 22:17:21'),
(41, 1, 'Recorded payment of Rs.1500 for Customer ID: 1', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 22:57:15'),
(42, 1, 'Recorded payment of Rs.50000 for Customer ID: 1', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 23:10:56'),
(43, 1, 'Recorded combined payment group #1785022046249 of Rs.100000 for Customer ID: 2023', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 23:27:26'),
(44, 1, 'Recorded combined payment group #1785023350396 of Rs.20000 for Customer ID: 2023', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 23:49:10'),
(45, 1, 'Recorded combined payment group #1785023367471 of Rs.20000 for Customer ID: 2023', '39.34.167.134', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-25 23:49:27');

-- --------------------------------------------------------

--
-- Table structure for table `shipment_requests`
--

CREATE TABLE `shipment_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `requested_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Accepted','Declined') DEFAULT 'Pending',
  `total_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipment_requests`
--

INSERT INTO `shipment_requests` (`id`, `user_id`, `username`, `requested_at`, `status`, `total_amount`) VALUES
(1, 15, 'multan', '2026-07-14 23:59:55', 'Accepted', 0.00),
(2, 15, 'multan', '2026-07-15 00:06:34', 'Accepted', 0.00),
(3, 15, 'multan', '2026-07-15 00:16:12', 'Accepted', 51000.00),
(4, 15, 'multan', '2026-07-15 00:41:23', 'Accepted', 1670.00),
(5, 15, 'multan', '2026-07-15 00:50:04', 'Accepted', 6000.00),
(6, 15, 'multan', '2026-07-15 01:01:17', 'Accepted', 36000.00),
(7, 15, 'multan', '2026-07-15 14:47:24', 'Accepted', 2870.00),
(8, 15, 'multan', '2026-07-16 09:34:24', 'Declined', 227000.00),
(9, 15, 'multan', '2026-07-18 13:20:07', 'Declined', 227000.00),
(10, 15, 'multan', '2026-07-19 14:59:33', 'Declined', 28700.00),
(11, 15, 'multan', '2026-07-25 22:45:35', 'Accepted', 28700.00),
(12, 15, 'multan', '2026-08-11 09:12:02', 'Declined', 191000.00);

-- --------------------------------------------------------

--
-- Table structure for table `shipment_request_items`
--

CREATE TABLE `shipment_request_items` (
  `id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipment_request_items`
--

INSERT INTO `shipment_request_items` (`id`, `request_id`, `product_id`, `product_name`, `quantity`, `price_per_unit`) VALUES
(1, 1, 2, 'Zeera biscuit', 1, 0.00),
(2, 2, 1, 'Super Biscuit Rs20', 20, 0.00),
(3, 2, 2, 'Zeera biscuit', 1, 0.00),
(4, 3, 2, 'Zeera biscuit', 30, 1700.00),
(5, 4, 1, 'Super Biscuit Rs20', 1, 1670.00),
(6, 5, 2, 'Zeera biscuit', 5, 1200.00),
(7, 6, 2, 'Zeera biscuit', 30, 1200.00),
(8, 7, 1, 'Super Biscuit Rs20', 1, 1670.00),
(9, 7, 2, 'Zeera biscuit', 1, 1200.00),
(10, 8, 1, 'Super Biscuit Rs20', 100, 1670.00),
(11, 8, 2, 'Zeera biscuit', 50, 1200.00),
(12, 9, 1, 'Super Biscuit Rs20', 100, 1670.00),
(13, 9, 2, 'Zeera biscuit', 50, 1200.00),
(14, 10, 1, 'Super Biscuit Rs20', 10, 1670.00),
(15, 10, 2, 'Zeera biscuit', 10, 1200.00),
(16, 11, 1, 'Super Biscuit Rs20', 10, 1670.00),
(17, 11, 2, 'Zeera biscuit', 10, 1200.00),
(18, 12, 1, 'Super Biscuit Rs20', 100, 1670.00),
(19, 12, 2, 'Zeera biscuit', 20, 1200.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('IN','OUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_note` varchar(255) DEFAULT NULL,
  `performed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `movement_type`, `quantity`, `reference_note`, `performed_by`, `created_at`) VALUES
(1, 2, 'IN', 500, 'Manual Inventory Adjustment', 'admin', '2026-07-25 23:17:05'),
(2, 2, 'OUT', 1, 'rain', 'admin', '2026-07-25 23:17:25'),
(3, 2, 'IN', 50, 'Today stock', 'admin', '2026-07-25 23:18:42'),
(4, 2, 'IN', 100, 'Today stock', 'admin', '2026-07-25 23:19:48'),
(5, 2, 'IN', 100, 'Manual Inventory Adjustment', 'admin', '2026-08-11 08:56:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('Admin','Sales','Support','Manager') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'Active',
  `avatar_path` varchar(255) DEFAULT 'assets/uploads/default-avatar.png',
  `display_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `password_hash`, `role`, `created_at`, `status`, `avatar_path`, `display_name`) VALUES
(1, 'admin', 'admin', 'admin@crm.local', '$2y$10$3kv9x.bkXR.IcgpYs1SH1O1QmC2dFHGEnhBR9szKTKLrnX1L7evDe', 'Admin', '2026-07-08 17:55:05', 'Active', 'assets/uploads/1783541021_6895a2239015bdd04d190600_vlad_purple.png', 'admin'),
(4, 'lahore12', '$2y$10$0DYQmUl2LG./Kfq3qNKZkumz/fBOkOwu7XLBIqsW71xb.Rgf6Zbs6', '', '', 'Admin', '2026-07-09 14:37:50', 'Active', 'assets/uploads/default-avatar.png', NULL),
(15, 'multan', '$2y$10$cV0ECQ/HahgK6AonZi7xH.X3uUnGbK0z5x36S2EnU1vqw58lPJWai', NULL, '$2y$10$dL5PRbtsDb4YjgwsbHuMHerZJbYT57Gjq/RqXC2QfM9z6SdZmHzLu', 'Sales', '2026-07-09 15:17:42', 'Active', 'assets/uploads/default-avatar.png', 'multan'),
(16, 'ali', '$2y$10$Fx.yDAwUHzBtI/LA9pmlQ.6K/Muh42w8AxpK8KHs.qviyae3B224e', NULL, '', '', '2026-07-10 20:20:57', 'Active', 'assets/uploads/default-avatar.png', NULL),
(17, 'Ashir', '$2y$10$LbUV9cK3jidn126ZpGty7eEmR7O/F0YxagQa1WB5ilE/Xl1p2y0Jm', NULL, '', '', '2026-07-11 11:46:48', 'Active', 'assets/uploads/default-avatar.png', NULL),
(18, '123wqe', '$2y$10$begtJ3pC5xOGmUBH0gr3leXE4u4gODTCklCo58cV604eQ985BxJm2', NULL, '', '', '2026-08-11 09:16:44', 'Active', 'assets/uploads/default-avatar.png', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `assigned_agent_id` (`assigned_agent_id`);

--
-- Indexes for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `interactions`
--
ALTER TABLE `interactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_name` (`product_name`),
  ADD UNIQUE KEY `sku` (`sku`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `product_shipments`
--
ALTER TABLE `product_shipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `shipment_requests`
--
ALTER TABLE `shipment_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shipment_request_items`
--
ALTER TABLE `shipment_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `request_id` (`request_id`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bank_accounts`
--
ALTER TABLE `bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2025;

--
-- AUTO_INCREMENT for table `customer_payments`
--
ALTER TABLE `customer_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `interactions`
--
ALTER TABLE `interactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_shipments`
--
ALTER TABLE `product_shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `shipment_requests`
--
ALTER TABLE `shipment_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `shipment_request_items`
--
ALTER TABLE `shipment_request_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`assigned_agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_payments`
--
ALTER TABLE `customer_payments`
  ADD CONSTRAINT `customer_payments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_payments_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `interactions`
--
ALTER TABLE `interactions`
  ADD CONSTRAINT `interactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interactions_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_shipments`
--
ALTER TABLE `product_shipments`
  ADD CONSTRAINT `product_shipments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_shipments_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `security_logs`
--
ALTER TABLE `security_logs`
  ADD CONSTRAINT `security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shipment_request_items`
--
ALTER TABLE `shipment_request_items`
  ADD CONSTRAINT `shipment_request_items_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `shipment_requests` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
