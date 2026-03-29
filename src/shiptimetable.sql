-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- ホスト: mysql
-- 生成日時: 2026 年 3 月 19 日 03:38
-- サーバのバージョン： 8.4.8
-- PHP のバージョン: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `shiptimetable`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `badge`
--

CREATE TABLE `badge` (
  `badge_id` int NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `label_e` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `badge`
--

INSERT INTO `badge` (`badge_id`, `label`, `label_e`, `created_at`, `updated_at`) VALUES
(0, '未選択', 'Not selected', '2026-03-02 08:23:41', '2026-03-19 03:15:26'),
(1, '箱根ロープウェイに接続する最終便です', 'Last service connecting to Hakone Ropeway', '2026-03-02 07:20:39', '2026-03-19 03:15:26'),
(2, '往復できる最終便です', 'Last round-trip service', '2026-03-02 07:20:39', '2026-03-19 03:15:26'),
(3, '片道のみ/帰りの便はありません', 'One-way only / no return service', '2026-03-02 07:20:39', '2026-03-19 03:15:26'),
(4, '荒天のため変則ダイヤ', 'Special timetable due to stormy weather', '2026-03-02 07:20:39', '2026-03-19 03:15:26'),
(5, '荒天のためダイヤ変更の可能性あり', 'Timetable may change due to stormy weather', '2026-03-02 07:20:39', '2026-03-19 03:15:26'),
(6, '臨時便', 'Extra service', '2026-03-02 07:20:39', '2026-03-19 03:15:26');

-- --------------------------------------------------------

--
-- テーブルの構造 `content_display`
--

CREATE TABLE `content_display` (
  `content_display_id` int UNSIGNED NOT NULL,
  `station_id` int NOT NULL,
  `content_id` int UNSIGNED NOT NULL,
  `sort_order` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `content_display`
--

INSERT INTO `content_display` (`content_display_id`, `station_id`, `content_id`, `sort_order`, `created_at`, `updated_at`) VALUES
(112, 1, 34, 1, '2026-03-20 15:00:44', '2026-03-20 15:00:44');

-- --------------------------------------------------------

--
-- テーブルの構造 `content_display_setting`
--

CREATE TABLE `content_display_setting` (
  `station_id` int NOT NULL,
  `swap_interval_seconds` int NOT NULL DEFAULT '8',
  `rotation_seconds` int NOT NULL DEFAULT '8',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `content_display_setting`
--

INSERT INTO `content_display_setting` (`station_id`, `swap_interval_seconds`, `rotation_seconds`, `created_at`, `updated_at`) VALUES
(1, 0, 8, '2026-03-13 06:26:48', '2026-03-20 15:00:44');

-- --------------------------------------------------------

--
-- テーブルの構造 `content_item`
--

CREATE TABLE `content_item` (
  `content_item_id` int UNSIGNED NOT NULL,
  `station_id` int NOT NULL DEFAULT '0',
  `slot_no` int DEFAULT NULL,
  `title` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `content_type` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'text',
  `content_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '100',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `content_item`
--

INSERT INTO `content_item` (`content_item_id`, `station_id`, `slot_no`, `title`, `content_type`, `content_value`, `start_at`, `end_at`, `is_active`, `sort_order`, `note`, `created_at`, `updated_at`) VALUES
(29, 1, NULL, '2', 'movie', 'uploads/content/video_20260313_153116_25c47a302744da4e.mp4', NULL, NULL, 1, 20, '', '2026-03-13 06:31:16', '2026-03-13 06:31:16'),
(30, 0, 4, '4', 'movie', 'uploads/content/video_20260313_153231_9b8626161a2d7609.mp4', NULL, NULL, 1, 4, '', '2026-03-13 06:32:31', '2026-03-13 06:32:31'),
(31, 0, 5, '5', 'movie', 'uploads/content/video_20260313_153248_d7d924c2dfd84f80.mp4', NULL, NULL, 1, 5, '', '2026-03-13 06:32:48', '2026-03-13 06:32:48'),
(32, 3, NULL, '3', 'movie', 'uploads/content/video_20260313_153826_c55aff2b27a08a17.mp4', NULL, NULL, 1, 10, '', '2026-03-13 06:38:26', '2026-03-13 06:38:26'),
(33, 2, NULL, '3', 'movie', 'uploads/content/video_20260313_153844_fc55c641d0a13c95.mp4', NULL, NULL, 1, 10, '', '2026-03-13 06:38:44', '2026-03-13 06:38:44'),
(34, 0, 1, '1', 'movie', 'uploads/content/video_20260313_153950_fc984efebc92e61c.mp4', NULL, NULL, 1, 1, '', '2026-03-13 06:39:50', '2026-03-13 06:39:50'),
(35, 0, 2, '2', 'movie', 'uploads/content/video_20260313_154657_dcb7bd38639ca6d5.mp4', NULL, NULL, 1, 2, '', '2026-03-13 06:46:57', '2026-03-13 06:46:57'),
(36, 0, 3, '3', 'movie', 'uploads/content/video_20260313_154708_50c31452c8324c25.mp4', NULL, NULL, 1, 3, '', '2026-03-13 06:47:08', '2026-03-13 06:47:08');

-- --------------------------------------------------------

--
-- テーブルの構造 `destination`
--

CREATE TABLE `destination` (
  `destination_id` int NOT NULL,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `name_e` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `destination`
--

INSERT INTO `destination` (`destination_id`, `name`, `name_e`, `created_at`, `updated_at`) VALUES
(1, '桃源台1', 'Togendai1', '2026-03-09 07:19:40', '2026-03-09 08:01:34'),
(2, '元箱根', 'Motohakone', '2026-03-09 07:19:40', '2026-03-09 07:19:40'),
(3, '箱根町', 'Hakone Town', '2026-03-09 07:19:40', '2026-03-09 07:19:40'),
(4, '1', 'e1', '2026-03-09 08:01:46', '2026-03-09 08:01:46');

-- --------------------------------------------------------

--
-- テーブルの構造 `display`
--

CREATE TABLE `display` (
  `display_id` int DEFAULT NULL,
  `reset` datetime NOT NULL,
  `ch` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `display`
--

INSERT INTO `display` (`display_id`, `reset`, `ch`, `created_at`, `updated_at`) VALUES
(1, '2019-11-01 08:30:10', 70, '2026-03-02 07:20:39', '2026-03-18 09:22:38'),
(2, '2019-11-01 09:00:11', 49, '2026-03-02 07:20:39', '2026-03-16 16:11:06'),
(3, '0000-00-00 00:00:00', 66, '2026-03-02 07:20:39', '2026-03-09 03:19:07'),
(4, '0000-00-00 00:00:00', 94, '2026-03-02 07:20:39', '2026-03-02 07:20:39');

-- --------------------------------------------------------

--
-- テーブルの構造 `display_language_setting`
--

CREATE TABLE `display_language_setting` (
  `station_id` int NOT NULL,
  `english_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`station_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `login`
--

CREATE TABLE `login` (
  `login_id` varchar(50) NOT NULL DEFAULT '',
  `pass` varchar(50) DEFAULT NULL,
  `auth` int DEFAULT NULL,
  `station_id` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `login`
--

INSERT INTO `login` (`login_id`, `pass`, `auth`, `station_id`, `created_at`, `updated_at`) VALUES
('test', 'test', 0, 1, '2026-03-02 07:20:39', '2026-03-02 07:20:39');

-- --------------------------------------------------------

--
-- テーブルの構造 `message`
--

CREATE TABLE `message` (
  `station_id` int NOT NULL,
  `message` varchar(100) DEFAULT NULL,
  `message_e` varchar(100) DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `message_id` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `message`
--

INSERT INTO `message` (`station_id`, `message`, `message_e`, `sort_order`, `is_visible`, `created_at`, `updated_at`, `message_id`) VALUES
(1, 'アメイジングポケット！', NULL, 1, 1, '2026-03-11 07:00:20', '2026-03-19 03:30:32', 7),
(1, 'こんにちは！', NULL, 7, 1, '2026-03-11 07:07:11', '2026-03-19 03:30:32', 8),
(1, 'sdfsd', NULL, 8, 1, '2026-03-19 03:29:38', '2026-03-19 03:32:17', 12);

-- --------------------------------------------------------

--
-- テーブルの構造 `message_display_setting`
--

CREATE TABLE `message_display_setting` (
  `station_id` int NOT NULL,
  `drag_speed` int NOT NULL DEFAULT '4',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `message_display_setting`
--

INSERT INTO `message_display_setting` (`station_id`, `drag_speed`, `created_at`, `updated_at`) VALUES
(1, 9, '2026-03-11 06:21:23', '2026-03-11 09:10:16'),
(2, 2, '2026-03-11 08:06:09', '2026-03-11 08:06:09');

-- --------------------------------------------------------

--
-- テーブルの構造 `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int UNSIGNED NOT NULL,
  `station_id` int NOT NULL,
  `season_id` int UNSIGNED NOT NULL DEFAULT '0',
  `departure_time` time DEFAULT NULL,
  `ship_id` int NOT NULL DEFAULT '0',
  `destination_id` int NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `station_id`, `season_id`, `departure_time`, `ship_id`, `destination_id`, `priority`, `is_active`, `note`, `created_at`, `updated_at`) VALUES
(5, 1, 2, '21:46:00', 1, 1, 2, 1, '', '2026-03-09 08:43:39', '2026-03-09 08:43:39'),
(9, 1, 1, '02:03:00', 1, 2, 1, 1, '', '2026-03-11 02:42:46', '2026-03-11 02:42:46'),
(10, 1, 1, '01:03:00', 3, 3, 2, 1, '', '2026-03-11 02:42:55', '2026-03-11 02:43:04'),
(13, 1, 4, '15:51:00', 2, 3, 1, 1, '', '2026-03-11 02:48:50', '2026-03-11 02:48:50'),
(16, 2, 1, '16:42:00', 2, 2, 1, 1, '', '2026-03-11 07:40:00', '2026-03-11 07:40:00'),
(17, 2, 1, '16:45:00', 1, 1, 2, 1, '', '2026-03-11 07:40:12', '2026-03-11 07:40:12'),
(18, 2, 2, '16:44:00', 2, 2, 1, 1, '', '2026-03-11 07:40:22', '2026-03-11 07:40:22'),
(20, 1, 5, '17:38:00', 3, 2, 2, 1, '', '2026-03-11 08:34:15', '2026-03-11 08:34:15'),
(22, 1, 1, '13:27:00', 2, 2, 3, 1, '', '2026-03-17 04:26:23', '2026-03-17 04:26:23'),
(23, 1, 5, '13:27:00', 3, 3, 3, 1, '', '2026-03-17 04:26:54', '2026-03-17 04:26:54'),
(24, 1, 3, '17:51:00', 2, 1, 1, 1, '', '2026-03-18 08:50:44', '2026-03-18 08:50:44'),
(25, 1, 3, '17:52:00', 2, 2, 2, 1, '', '2026-03-18 08:50:53', '2026-03-18 08:50:53'),
(26, 1, 3, '17:53:00', 3, 1, 3, 1, '', '2026-03-18 08:51:03', '2026-03-18 08:51:03');

-- --------------------------------------------------------

--
-- テーブルの構造 `season`
--

CREATE TABLE `season` (
  `season_id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- テーブルのデータのダンプ `season`
--

INSERT INTO `season` (`season_id`, `name`, `start_date`, `end_date`, `created_at`, `updated_at`) VALUES
(1, 'キダイヤ', '2026-01-01', '2026-01-23', '2026-03-06 08:45:54', '2026-03-20 14:59:52'),
(2, 'シーズンダイヤ1', '2026-03-15', '2026-03-15', '2026-03-06 08:45:54', '2026-03-16 15:01:23'),
(3, 'シーズンダイヤ2', '2026-05-01', '2026-06-30', '2026-03-06 08:45:54', '2026-03-09 04:11:11'),
(4, 'トップシーズンダイヤ', '2026-07-01', '2026-08-31', '2026-03-06 08:45:54', '2026-03-09 04:11:34'),
(5, 'オフシーズンダイヤ', '2026-03-16', '2026-04-30', '2026-03-06 08:45:54', '2026-03-16 02:54:37');

-- --------------------------------------------------------

--
-- テーブルの構造 `ship`
--

CREATE TABLE `ship` (
  `ship_id` int NOT NULL,
  `name` varchar(20) NOT NULL,
  `name_e` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `ship`
--

INSERT INTO `ship` (`ship_id`, `name`, `name_e`, `created_at`, `updated_at`) VALUES
(1, 'ヒミコ', 'Himiko', '2026-03-02 07:20:39', '2026-03-02 07:20:39'),
(2, 'ホタルナ', 'Hotaluna', '2026-03-02 07:20:39', '2026-03-02 07:20:39'),
(3, 'エメラルダス', 'Emeraldas', '2026-03-02 07:20:39', '2026-03-02 07:20:39'),
(4, '2', 'e2', '2026-03-09 08:12:12', '2026-03-09 08:12:12');

-- --------------------------------------------------------

--
-- テーブルの構造 `station`
--

CREATE TABLE `station` (
  `station_id` int NOT NULL,
  `name` varchar(20) NOT NULL,
  `name_e` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- テーブルのデータのダンプ `station`
--

INSERT INTO `station` (`station_id`, `name`, `name_e`, `created_at`, `updated_at`) VALUES
(1, '桃源台', 'Togendai', '2026-03-02 07:20:39', '2026-03-09 08:01:03'),
(2, '元箱根', 'Motohakone', '2026-03-02 07:20:39', '2026-03-09 01:54:14'),
(3, '箱根町', 'Hakone Town', '2026-03-02 07:20:39', '2026-03-09 01:54:16');

-- --------------------------------------------------------

--
-- テーブルの構造 `timetable`
--

CREATE TABLE `timetable` (
  `timetable_id` int UNSIGNED NOT NULL,
  `station_id` int NOT NULL,
  `departure_time` time NOT NULL,
  `ship_id` int NOT NULL DEFAULT '0',
  `destination_id` int NOT NULL DEFAULT '0',
  `badge_id` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `ontime` tinyint UNSIGNED NOT NULL DEFAULT '15',
  `offtime` tinyint UNSIGNED NOT NULL DEFAULT '10',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- テーブルのデータのダンプ `timetable`
--

INSERT INTO `timetable` (`timetable_id`, `station_id`, `departure_time`, `ship_id`, `destination_id`, `badge_id`, `ontime`, `offtime`, `created_at`, `updated_at`) VALUES
(31, 2, '14:46:00', 1, 1, 2, 10, 5, '2026-03-16 05:44:27', '2026-03-16 05:44:27'),
(35, 2, '16:10:00', 2, 2, 2, 10, 5, '2026-03-16 07:04:31', '2026-03-16 07:04:31'),
(265, 1, '18:38:00', 2, 3, 2, 10, 5, '2026-03-18 09:43:14', '2026-03-18 09:57:24'),
(266, 1, '13:27:00', 3, 2, 4, 10, 5, '2026-03-18 09:43:14', '2026-03-18 10:03:08'),
(278, 1, '11:54:00', 2, 2, 0, 10, 5, '2026-03-19 02:54:15', '2026-03-19 02:54:15'),
(279, 1, '13:58:00', 1, 1, 0, 10, 5, '2026-03-19 02:57:43', '2026-03-19 02:57:43'),
(280, 1, '11:58:00', 3, 1, 0, 10, 5, '2026-03-19 02:58:04', '2026-03-19 03:19:24'),
(281, 1, '15:05:00', 1, 2, 0, 10, 5, '2026-03-19 03:05:26', '2026-03-19 03:05:26');

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `badge`
--
ALTER TABLE `badge`
  ADD PRIMARY KEY (`badge_id`);

--
-- テーブルのインデックス `content_display`
--
ALTER TABLE `content_display`
  ADD PRIMARY KEY (`content_display_id`),
  ADD UNIQUE KEY `uq_content_display_station_sort` (`station_id`,`sort_order`),
  ADD UNIQUE KEY `uq_content_display_station_content` (`station_id`,`content_id`),
  ADD KEY `idx_content_display_station` (`station_id`,`sort_order`);

--
-- テーブルのインデックス `content_display_setting`
--
ALTER TABLE `content_display_setting`
  ADD PRIMARY KEY (`station_id`);

--
-- テーブルのインデックス `content_item`
--
ALTER TABLE `content_item`
  ADD PRIMARY KEY (`content_item_id`),
  ADD KEY `idx_content_station` (`station_id`,`is_active`,`sort_order`);

--
-- テーブルのインデックス `destination`
--
ALTER TABLE `destination`
  ADD PRIMARY KEY (`destination_id`);

--
-- テーブルのインデックス `login`
--
ALTER TABLE `login`
  ADD PRIMARY KEY (`login_id`);

--
-- テーブルのインデックス `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_message_station` (`station_id`,`message_id`),
  ADD KEY `idx_message_station_order` (`station_id`,`sort_order`,`message_id`);

--
-- テーブルのインデックス `message_display_setting`
--
ALTER TABLE `message_display_setting`
  ADD PRIMARY KEY (`station_id`);

--
-- テーブルのインデックス `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_dial_period_station` (`station_id`,`is_active`),
  ADD KEY `idx_schedule_station` (`station_id`,`is_active`,`season_id`),
  ADD KEY `fk_schedule_season` (`season_id`),
  ADD KEY `fk_schedule_ship` (`ship_id`),
  ADD KEY `fk_schedule_destination` (`destination_id`);

--
-- テーブルのインデックス `season`
--
ALTER TABLE `season`
  ADD PRIMARY KEY (`season_id`);

--
-- テーブルのインデックス `ship`
--
ALTER TABLE `ship`
  ADD PRIMARY KEY (`ship_id`);

--
-- テーブルのインデックス `station`
--
ALTER TABLE `station`
  ADD PRIMARY KEY (`station_id`);

--
-- テーブルのインデックス `timetable`
--
ALTER TABLE `timetable`
  ADD PRIMARY KEY (`timetable_id`),
  ADD KEY `idx_timetable_station_dial_time` (`station_id`,`departure_time`),
  ADD KEY `idx_timetable_station_time` (`station_id`,`departure_time`),
  ADD KEY `fk_timetable_ship` (`ship_id`),
  ADD KEY `fk_timetable_destination` (`destination_id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `content_display`
--
ALTER TABLE `content_display`
  MODIFY `content_display_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- テーブルの AUTO_INCREMENT `content_item`
--
ALTER TABLE `content_item`
  MODIFY `content_item_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- テーブルの AUTO_INCREMENT `destination`
--
ALTER TABLE `destination`
  MODIFY `destination_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- テーブルの AUTO_INCREMENT `message`
--
ALTER TABLE `message`
  MODIFY `message_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- テーブルの AUTO_INCREMENT `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- テーブルの AUTO_INCREMENT `season`
--
ALTER TABLE `season`
  MODIFY `season_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- テーブルの AUTO_INCREMENT `timetable`
--
ALTER TABLE `timetable`
  MODIFY `timetable_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=282;

--
-- ダンプしたテーブルの制約
--

--
-- テーブルの制約 `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `fk_schedule_destination` FOREIGN KEY (`destination_id`) REFERENCES `destination` (`destination_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_season` FOREIGN KEY (`season_id`) REFERENCES `season` (`season_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_ship` FOREIGN KEY (`ship_id`) REFERENCES `ship` (`ship_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_schedule_station` FOREIGN KEY (`station_id`) REFERENCES `station` (`station_id`) ON DELETE CASCADE;

--
-- テーブルの制約 `timetable`
--
ALTER TABLE `timetable`
  ADD CONSTRAINT `fk_timetable_destination` FOREIGN KEY (`destination_id`) REFERENCES `destination` (`destination_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_timetable_ship` FOREIGN KEY (`ship_id`) REFERENCES `ship` (`ship_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
