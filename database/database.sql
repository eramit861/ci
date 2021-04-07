-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2021 at 06:26 PM
-- Server version: 10.3.16-MariaDB
-- PHP Version: 7.2.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cidb`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_app_tokens`
--

CREATE TABLE `tbl_app_tokens` (
  `apptoken_user_id` int(11) NOT NULL,
  `apptoken_token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `apptoken_device_id` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
  `apptoken_fcm_code` text COLLATE utf8_unicode_ci NOT NULL COMMENT 'Fcm token used to send push notifications',
  `apptoken_device_type` tinyint(1) NOT NULL COMMENT '1 for android 2 for ios.. etc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_failed_login_attempts`
--

CREATE TABLE `tbl_failed_login_attempts` (
  `attempt_username` varchar(150) NOT NULL,
  `attempt_ip` varchar(50) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_login_history`
--

CREATE TABLE `tbl_login_history` (
  `lhistory_id` int(11) NOT NULL,
  `lhistory_is_admin` tinyint(4) NOT NULL COMMENT '0=> Login in front-end, 1=> Login in admin',
  `lhistory_user_id` int(11) NOT NULL,
  `lhistory_user_browser` varchar(250) NOT NULL,
  `lhistory_user_ip` varchar(20) NOT NULL,
  `lhistory_success` tinyint(4) NOT NULL,
  `lhistory_login_time` datetime NOT NULL,
  `lhistory_last_seen` datetime NOT NULL,
  `lhistory_login_by` int(11) NOT NULL COMMENT '0=>''self login'',If value is greater than 1,then logged admin and it is admin id',
  `lhistory_login_via` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 for web and 1 for android, 2 for ios'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_users`
--

CREATE TABLE `tbl_users` (
  `user_id` int(11) NOT NULL,
  `user_name` varchar(250) NOT NULL,
  `user_password` varchar(250) NOT NULL,
  `user_email` varchar(250) NOT NULL,
  `user_added_on` datetime NOT NULL,
  `user_active` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `tbl_users`
--

INSERT INTO `tbl_users` (`user_id`, `user_name`, `user_password`, `user_email`, `user_added_on`, `user_active`) VALUES
(1, 'ezcalc', 'amit@123', 'amit123@dummyid.com', '0000-00-00 00:00:00', 1),
(2, 'Retailer Admin', 'amit@123', 'doesham@dummyid.com', '0000-00-00 00:00:00', 1),
(3, 'amit', 'amit@123', 'amit7@gmail.com', '0000-00-00 00:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_user_auth_token`
--

CREATE TABLE `tbl_user_auth_token` (
  `uauth_user_id` int(11) NOT NULL,
  `uauth_token` varchar(32) NOT NULL,
  `uauth_expiry` datetime NOT NULL,
  `uauth_browser` mediumtext NOT NULL,
  `uauth_last_access` datetime NOT NULL,
  `uauth_last_ip` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='To store cookies information';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_app_tokens`
--
ALTER TABLE `tbl_app_tokens`
  ADD UNIQUE KEY `apptoken_token` (`apptoken_token`);

--
-- Indexes for table `tbl_login_history`
--
ALTER TABLE `tbl_login_history`
  ADD PRIMARY KEY (`lhistory_id`),
  ADD KEY `lhistory_user_id` (`lhistory_user_id`);

--
-- Indexes for table `tbl_users`
--
ALTER TABLE `tbl_users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `tbl_user_auth_token`
--
ALTER TABLE `tbl_user_auth_token`
  ADD PRIMARY KEY (`uauth_token`),
  ADD KEY `uauth_user_id` (`uauth_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_login_history`
--
ALTER TABLE `tbl_login_history`
  MODIFY `lhistory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_users`
--
ALTER TABLE `tbl_users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
