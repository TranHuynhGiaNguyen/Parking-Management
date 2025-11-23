-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 22, 2025 lúc 09:21 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `parking_system`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `card_reads`
--

CREATE TABLE `card_reads` (
  `id` int(11) NOT NULL,
  `uid` varchar(64) NOT NULL,
  `plate` varchar(20) NOT NULL DEFAULT '',
  `confidence` float DEFAULT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `fp` varchar(64) GENERATED ALWAYS AS (concat(ucase(`uid`),'|',ifnull(`plate`,''),'|',floor(unix_timestamp(`captured_at`) / 2))) STORED,
  `bucket2s` int(11) GENERATED ALWAYS AS (floor(unix_timestamp(`captured_at`) / 2)) STORED,
  `vehicle_type` varchar(30) DEFAULT 'unknown'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `face_captures`
--

CREATE TABLE `face_captures` (
  `id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `uid` varchar(32) DEFAULT NULL,
  `plate` varchar(16) DEFAULT NULL,
  `img_path` varchar(255) NOT NULL,
  `captured_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `fee_time_ranges`
--

CREATE TABLE `fee_time_ranges` (
  `id` int(11) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `fee_car_per_hour` int(11) NOT NULL,
  `fee_mc_per_hour` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `fee_time_ranges`
--

INSERT INTO `fee_time_ranges` (`id`, `start_time`, `end_time`, `fee_car_per_hour`, `fee_mc_per_hour`) VALUES
(2, '17:00:00', '22:00:00', 70000, 4000),
(3, '22:00:00', '05:00:00', 100000, 6000),
(4, '05:00:00', '17:00:00', 10000, 2000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lot_state`
--

CREATE TABLE `lot_state` (
  `id` tinyint(4) NOT NULL,
  `override_car` tinyint(1) NOT NULL DEFAULT 0,
  `override_mc` tinyint(1) NOT NULL DEFAULT 0,
  `last_full_car` tinyint(1) NOT NULL DEFAULT 0,
  `last_full_mc` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lot_state`
--

INSERT INTO `lot_state` (`id`, `override_car`, `override_mc`, `last_full_car`, `last_full_mc`, `updated_at`) VALUES
(1, 0, 0, 0, 0, '2025-11-10 09:15:26');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `parking_zones`
--

CREATE TABLE `parking_zones` (
  `id` int(11) NOT NULL,
  `zone` varchar(50) NOT NULL,
  `max_slots` int(11) NOT NULL DEFAULT 0,
  `used` int(11) NOT NULL DEFAULT 0,
  `type` enum('CAR','MC') NOT NULL DEFAULT 'MC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `parking_zones`
--

INSERT INTO `parking_zones` (`id`, `zone`, `max_slots`, `used`, `type`) VALUES
(1, 'A1', 10, 0, 'MC'),
(2, 'A2', 12, 0, 'MC'),
(3, 'B1', 15, 2, 'CAR'),
(4, 'A1', 10, 0, 'MC');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `system_config`
--

CREATE TABLE `system_config` (
  `id` tinyint(4) NOT NULL,
  `max_car` int(11) DEFAULT 2,
  `max_motorcycle` int(11) DEFAULT 2,
  `scan_mode` enum('in','out','both') NOT NULL DEFAULT 'both',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `fee_car_per_min` int(11) DEFAULT 0,
  `fee_mc_per_min` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `system_config`
--

INSERT INTO `system_config` (`id`, `max_car`, `max_motorcycle`, `scan_mode`, `updated_at`, `fee_car_per_min`, `fee_mc_per_min`) VALUES
(1, 0, 0, 'both', '2025-11-22 07:15:59', 0, 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','baove') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`) VALUES
(1, 'admin1', 'admin123', 'Quản trị viên', 'admin'),
(2, 'baove1', '123456', 'Bảo vệ Ca 1', 'baove'),
(4, '1', '1', '1', 'admin'),
(6, '2', '2', '2', 'baove');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vehicle_sessions`
--

CREATE TABLE `vehicle_sessions` (
  `id` int(11) NOT NULL,
  `uid` varchar(32) DEFAULT NULL,
  `plate` varchar(16) DEFAULT NULL,
  `vehicle_type` varchar(16) DEFAULT NULL,
  `in_time` datetime DEFAULT NULL,
  `out_time` datetime DEFAULT NULL,
  `fee` decimal(12,2) DEFAULT NULL,
  `zone` varchar(20) DEFAULT 'A1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `card_reads`
--
ALTER TABLE `card_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_uid_plate_bucket2s` (`uid`,`plate`,`bucket2s`);

--
-- Chỉ mục cho bảng `face_captures`
--
ALTER TABLE `face_captures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Chỉ mục cho bảng `fee_time_ranges`
--
ALTER TABLE `fee_time_ranges`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `lot_state`
--
ALTER TABLE `lot_state`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `parking_zones`
--
ALTER TABLE `parking_zones`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Chỉ mục cho bảng `vehicle_sessions`
--
ALTER TABLE `vehicle_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sessions_uid_open` (`uid`,`out_time`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `card_reads`
--
ALTER TABLE `card_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT cho bảng `face_captures`
--
ALTER TABLE `face_captures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT cho bảng `fee_time_ranges`
--
ALTER TABLE `fee_time_ranges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `parking_zones`
--
ALTER TABLE `parking_zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `vehicle_sessions`
--
ALTER TABLE `vehicle_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
