-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql303.infinityfree.com
-- Generation Time: Jul 16, 2026 at 11:32 PM
-- Server version: 11.4.12-MariaDB
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
-- Database: `if0_41146249_wp381`
--

-- --------------------------------------------------------

--
-- Table structure for table `attempts`
--

CREATE TABLE `attempts` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `status` enum('pending','graded') NOT NULL DEFAULT 'pending',
  `auto_points` int(11) NOT NULL DEFAULT 0,
  `manual_points` int(11) NOT NULL DEFAULT 0,
  `total_points` int(11) NOT NULL DEFAULT 0,
  `percent` int(11) DEFAULT NULL,
  `passed` tinyint(1) DEFAULT NULL,
  `certificate_id` varchar(32) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `graded_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attempts`
--

INSERT INTO `attempts` (`id`, `student_id`, `category_id`, `status`, `auto_points`, `manual_points`, `total_points`, `percent`, `passed`, `certificate_id`, `submitted_at`, `graded_at`) VALUES
(1, 2, 2, 'graded', 1, 10, 11, 100, 1, '3acc97aa79d5b792', '2026-07-07 23:09:11', '2026-07-07 23:10:50'),
(2, 2, 5, 'graded', 0, 10, 10, 100, 1, NULL, '2026-07-08 21:51:11', '2026-07-08 21:53:47'),
(3, 2, 5, 'graded', 0, 2, 10, 20, 0, NULL, '2026-07-08 21:51:54', '2026-07-09 00:55:31'),
(4, 2, 2, 'graded', 1, 10, 11, 100, 1, '84693423cb89a5b8', '2026-07-09 00:46:50', '2026-07-09 00:47:45'),
(5, 2, 16, 'graded', 1, 0, 1, 100, 1, NULL, '2026-07-09 00:54:29', '2026-07-09 00:54:29'),
(6, 10, 6, 'pending', 6, 0, 16, NULL, NULL, NULL, '2026-07-10 02:31:28', NULL),
(7, 2, 5, 'graded', 0, 3, 10, 30, 0, NULL, '2026-07-10 17:41:09', '2026-07-10 20:52:19'),
(8, 2, 17, 'graded', 1, 0, 1, 100, 1, NULL, '2026-07-10 19:37:03', '2026-07-10 19:37:03'),
(9, 13, 18, 'graded', 0, 0, 1, 0, 0, NULL, '2026-07-13 22:52:48', '2026-07-13 22:52:48'),
(10, 16, 10, 'graded', 0, 0, 5, 0, 0, NULL, '2026-07-14 01:15:24', '2026-07-14 01:15:24'),
(11, 16, 10, 'graded', 0, 0, 5, 0, 0, NULL, '2026-07-14 01:16:02', '2026-07-14 01:16:02'),
(12, 16, 10, 'graded', 1, 0, 5, 20, 0, NULL, '2026-07-14 01:16:39', '2026-07-14 01:16:39'),
(13, 13, 6, 'pending', 3, 0, 16, NULL, NULL, NULL, '2026-07-14 20:00:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attempt_answers`
--

CREATE TABLE `attempt_answers` (
  `id` int(11) NOT NULL,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `qtype` enum('mcq','code','essay') NOT NULL,
  `selected_index` tinyint(4) DEFAULT NULL,
  `text_answer` longtext DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `points_awarded` int(11) DEFAULT NULL,
  `max_points` int(11) NOT NULL DEFAULT 1,
  `feedback` varchar(500) DEFAULT NULL,
  `autograded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = this code answer was auto-graded by matching program output, no manual grading needed',
  `run_output` mediumtext DEFAULT NULL COMMENT 'What the student code actually printed when auto-run for grading'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attempt_answers`
--

INSERT INTO `attempt_answers` (`id`, `attempt_id`, `question_id`, `qtype`, `selected_index`, `text_answer`, `is_correct`, `points_awarded`, `max_points`, `feedback`, `autograded`, `run_output`) VALUES
(1, 1, 2, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL),
(2, 1, 3, 'essay', NULL, 'sdcas', NULL, 10, 10, NULL, 0, NULL),
(3, 2, 4, 'code', NULL, '00001332331321212111121121514', NULL, 10, 10, '1141111', 0, NULL),
(4, 3, 4, 'code', NULL, '0000', NULL, 2, 10, NULL, 0, NULL),
(5, 4, 2, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL),
(6, 4, 3, 'essay', NULL, 'qwdeqw', NULL, 10, 10, 'good', 0, NULL),
(7, 5, 51, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL),
(8, 6, 5, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(9, 6, 6, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(10, 6, 7, 'mcq', 2, NULL, 1, 1, 1, NULL, 0, NULL),
(11, 6, 8, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(12, 6, 9, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(13, 6, 52, 'code', NULL, 'Hdhhdhd', NULL, NULL, 10, NULL, 0, NULL),
(14, 6, 53, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL),
(15, 7, 4, 'code', NULL, '0000', NULL, 3, 10, NULL, 0, NULL),
(16, 8, 54, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL),
(17, 9, 55, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(18, 10, 25, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(19, 10, 26, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(20, 10, 27, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(21, 10, 28, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(22, 10, 29, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(23, 11, 25, 'mcq', 0, NULL, 0, 0, 1, NULL, 0, NULL),
(24, 11, 26, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(25, 11, 27, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(26, 11, 28, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(27, 11, 29, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(28, 12, 25, 'mcq', 1, NULL, 0, 0, 1, NULL, 0, NULL),
(29, 12, 26, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(30, 12, 27, 'mcq', NULL, NULL, 0, 0, 1, NULL, 0, NULL),
(31, 12, 28, 'mcq', 2, NULL, 0, 0, 1, NULL, 0, NULL),
(32, 12, 29, 'mcq', 3, NULL, 0, 0, 1, NULL, 0, NULL),
(33, 13, 5, 'mcq', 0, NULL, 0, 0, 1, NULL, 0, NULL),
(34, 13, 6, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(35, 13, 7, 'mcq', 0, NULL, 0, 0, 1, NULL, 0, NULL),
(36, 13, 8, 'mcq', 1, NULL, 1, 1, 1, NULL, 0, NULL),
(37, 13, 9, 'mcq', 2, NULL, 0, 0, 1, NULL, 0, NULL),
(38, 13, 52, 'code', NULL, 'sdfdsf', NULL, NULL, 10, NULL, 0, NULL),
(39, 13, 53, 'mcq', 0, NULL, 1, 1, 1, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` varchar(32) NOT NULL,
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `category_type` enum('selfstudy','teacher') NOT NULL,
  `category_key` varchar(50) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `label` varchar(150) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `teacher_name` varchar(100) DEFAULT NULL,
  `score` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `percent` int(11) NOT NULL,
  `issued_at` date NOT NULL,
  `source` enum('exam','manual') NOT NULL DEFAULT 'exam',
  `issued_by_name` varchar(100) DEFAULT NULL,
  `issued_by_role` varchar(20) DEFAULT NULL,
  `custom_message` varchar(255) DEFAULT NULL,
  `accent_color` varchar(9) DEFAULT NULL,
  `issuer_school_id` int(11) DEFAULT NULL,
  `template` varchar(20) DEFAULT NULL,
  `font_choice` varchar(20) DEFAULT NULL,
  `bg_style` varchar(20) DEFAULT NULL,
  `title_text` varchar(150) DEFAULT NULL,
  `seal_label` varchar(60) DEFAULT NULL,
  `signer_name_override` varchar(100) DEFAULT NULL,
  `signer_title_override` varchar(150) DEFAULT NULL,
  `signer_signature_override` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `student_id`, `student_name`, `category_type`, `category_key`, `category_id`, `label`, `teacher_id`, `teacher_name`, `score`, `total`, `percent`, `issued_at`, `source`, `issued_by_name`, `issued_by_role`, `custom_message`, `accent_color`, `issuer_school_id`, `template`, `font_choice`, `bg_style`, `title_text`, `seal_label`, `signer_name_override`, `signer_title_override`, `signer_signature_override`) VALUES
('05a688f49a11d8a4', 2, 'gil', 'teacher', NULL, NULL, 'IT', 12, 'Gilmar', 100, 100, 100, '2026-07-12', 'manual', 'Gilmar', 'teacher', 'NICE AND COOL', '#94c88c', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('3acc97aa79d5b792', 2, 'gil', 'teacher', NULL, 2, 'test 1', 3, 'fem', 11, 11, 100, '2026-07-07', 'exam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('45d342a4e77b91b3', 2, 'gil', 'teacher', NULL, NULL, 'ASD', 12, 'Gilmar', 100, 100, 100, '2026-07-12', 'manual', 'Gilmar', 'teacher', 'SADAD', '#5b7cfa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('532ec850c82d120b', 2, 'gil', 'teacher', NULL, 16, 'aaaa', 8, 'feghdfw', 1, 1, 100, '2026-07-09', 'exam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('5e1d05933209f61b', 2, 'gil', 'teacher', NULL, 1, 'dfd', 3, 'fem', 1, 1, 100, '2026-07-07', 'exam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('649c6fcddbebbaad', 2, 'gil', 'teacher', NULL, NULL, 'n', 12, 'Gilmar', 100, 100, 100, '2026-07-12', 'manual', 'Gilmar', 'teacher', NULL, '#ff0080', NULL, 'modern', 'default', 'dots', NULL, NULL, NULL, NULL, NULL),
('84693423cb89a5b8', 2, 'gil', 'teacher', NULL, 2, 'test 1', 3, 'fem', 11, 11, 100, '2026-07-09', 'exam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('e09f2102c8b7a048', 2, 'gil', 'teacher', NULL, 17, 'TEST 1', 12, 'Gilmar', 1, 1, 100, '2026-07-10', 'exam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class_codes`
--

CREATE TABLE `class_codes` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `code` varchar(12) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `subject_id` int(11) NOT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `decided_at` datetime DEFAULT NULL,
  `term` varchar(20) DEFAULT NULL COMMENT 'e.g. "1st Semester" — set by the student in the Enrollment Portal',
  `academic_year` varchar(20) DEFAULT NULL COMMENT 'e.g. "2026-2027"'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `category_id`, `enrolled_at`, `subject_id`, `status`, `decided_at`, `term`, `academic_year`) VALUES
(1, 2, 1, '2026-07-07 21:48:13', 1, 'pending', NULL, NULL, NULL),
(2, 2, 0, '2026-07-07 23:08:33', 2, 'approved', '2026-07-08 18:22:01', NULL, NULL),
(3, 2, 0, '2026-07-08 18:34:13', 3, 'approved', '2026-07-08 18:38:51', NULL, NULL),
(4, 2, 0, '2026-07-08 21:49:48', 4, 'approved', '2026-07-08 21:50:15', NULL, NULL),
(5, 2, 0, '2026-07-09 00:46:18', 6, 'approved', '2026-07-10 23:03:53', NULL, NULL),
(6, 2, 0, '2026-07-09 00:53:35', 7, 'approved', '2026-07-09 00:54:04', NULL, NULL),
(7, 10, 0, '2026-07-10 02:29:15', 7, 'pending', NULL, NULL, NULL),
(8, 10, 0, '2026-07-10 02:29:47', 6, 'approved', '2026-07-10 23:03:55', NULL, NULL),
(9, 2, 0, '2026-07-10 18:26:10', 8, 'approved', '2026-07-10 18:26:33', NULL, NULL),
(10, 13, 0, '2026-07-13 22:51:07', 9, 'approved', '2026-07-13 22:52:10', NULL, NULL),
(11, 16, 0, '2026-07-14 21:21:20', 9, 'approved', '2026-07-14 22:11:24', NULL, NULL),
(14, 13, 0, '2026-07-14 22:33:00', 7, 'pending', NULL, NULL, NULL),
(15, 13, 0, '2026-07-14 22:33:00', 6, 'pending', NULL, NULL, NULL),
(19, 17, 0, '2026-07-15 22:55:42', 9, 'approved', '2026-07-15 23:11:52', '1st Semester', '2026-2027'),
(20, 13, 0, '2026-07-16 00:48:40', 11, 'approved', '2026-07-16 00:54:33', 'Summer', '2026-2027'),
(21, 16, 0, '2026-07-16 04:06:02', 8, 'approved', '2026-07-16 17:13:30', '1st Semester', '2026-2027');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `author_id` int(11) NOT NULL,
  `author_role` enum('teacher','dean') NOT NULL,
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `link_url` varchar(500) DEFAULT NULL,
  `link_label` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `author_id`, `author_role`, `title`, `body`, `created_at`, `link_url`, `link_label`) VALUES
(2, 5, 'teacher', '21321', '00', '2026-07-08 21:45:49', NULL, NULL),
(4, 12, 'teacher', 'jUNE 2', 'WAY KLSI', '2026-07-10 18:28:29', NULL, NULL),
(5, 12, 'teacher', '🎓 Exam today: TEST 1', 'Open until 6:00 PM today — it\'ll close itself automatically after that.\n\npls ko kron pass', '2026-07-13 22:17:14', NULL, NULL),
(6, 12, 'teacher', 'dfsf', 'sdf', '2026-07-14 20:45:00', '/exam.php?teacher_cat=20', 'Take the xcvxc exam →'),
(7, 12, 'teacher', '🎓 Exam today: xcvxc', 'Open until 6:00 PM today — it\'ll close itself automatically after that.\n\nhttps://taskonw.free.nf/events.php#event-6', '2026-07-14 21:56:33', NULL, NULL),
(8, 12, 'teacher', '🎓 Exam today: xcvxc', 'Open until 6:00 PM today — it\'ll close itself automatically after that.\n\nhttps://taskonw.free.nf/events.php#event-6', '2026-07-14 22:01:10', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_categories`
--

CREATE TABLE `exam_categories` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `pass_score` int(11) NOT NULL DEFAULT 70,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `subject_id` int(11) DEFAULT NULL,
  `owner_type` enum('teacher','admin','selfstudy') NOT NULL DEFAULT 'teacher',
  `language_key` varchar(30) DEFAULT NULL,
  `purpose` enum('cert','grade') NOT NULL DEFAULT 'cert',
  `max_attempts` int(11) DEFAULT NULL COMMENT 'NULL/0 = unlimited attempts per student, 1 = one attempt only',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Manual on/off switch — 0 closes the exam immediately regardless of the schedule below',
  `available_from` datetime DEFAULT NULL COMMENT 'Optional: exam is closed before this time',
  `available_until` datetime DEFAULT NULL COMMENT 'Optional: exam automatically closes after this time',
  `time_limit_minutes` int(11) DEFAULT NULL COMMENT 'Optional: minutes allowed once a student starts the exam - NULL/0 = no timer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_categories`
--

INSERT INTO `exam_categories` (`id`, `teacher_id`, `title`, `description`, `pass_score`, `created_at`, `subject_id`, `owner_type`, `language_key`, `purpose`, `max_attempts`, `is_enabled`, `available_from`, `available_until`, `time_limit_minutes`) VALUES
(1, 3, 'dfd', 'dsfds', 70, '2026-07-07 21:46:42', 1, 'teacher', NULL, 'cert', NULL, 1, NULL, NULL, NULL),
(2, 3, 'test 1', '', 70, '2026-07-07 23:07:01', 2, 'teacher', NULL, 'cert', NULL, 1, NULL, NULL, NULL),
(3, 3, 'dsadas', 'asdasd', 70, '2026-07-08 18:35:51', 3, 'teacher', NULL, 'grade', NULL, 1, NULL, NULL, NULL),
(4, NULL, 'aSas', 'aSs', 70, '2026-07-08 19:14:06', NULL, 'admin', NULL, 'cert', NULL, 1, NULL, NULL, NULL),
(5, 5, '0', '', 100, '2026-07-08 21:48:12', 4, 'teacher', NULL, 'grade', NULL, 1, NULL, NULL, NULL),
(6, NULL, 'HTML Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'html', 'cert', NULL, 1, NULL, NULL, NULL),
(7, NULL, 'CSS Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'css', 'cert', NULL, 1, NULL, NULL, NULL),
(8, NULL, 'JavaScript Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'js', 'cert', NULL, 1, NULL, NULL, NULL),
(9, NULL, 'PHP Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'php', 'cert', NULL, 1, NULL, NULL, NULL),
(10, NULL, 'C Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'c', 'cert', NULL, 1, NULL, NULL, NULL),
(11, NULL, 'C++ Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'cpp', 'cert', NULL, 1, NULL, NULL, NULL),
(12, NULL, 'Java Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'java', 'cert', NULL, 1, NULL, NULL, NULL),
(13, NULL, 'Python Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'python', 'cert', NULL, 1, NULL, NULL, NULL),
(14, NULL, 'C# Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'csharp', 'cert', NULL, 1, NULL, NULL, NULL),
(15, 3, 'sdfs', 'sds', 70, '2026-07-09 00:42:59', 6, 'teacher', NULL, 'cert', NULL, 1, NULL, NULL, NULL),
(16, 8, 'aaaa', 'aa', 70, '2026-07-09 00:52:20', 7, 'teacher', NULL, 'cert', 1, 1, NULL, NULL, NULL),
(17, 12, 'TEST 1', 'SWDWE', 70, '2026-07-10 18:24:00', 8, 'teacher', NULL, 'cert', 1, 1, NULL, '2026-07-14 18:00:00', NULL),
(18, 12, 'gfggfgj', 'df', 70, '2026-07-13 22:49:46', 9, 'teacher', NULL, 'grade', 1, 1, NULL, NULL, NULL),
(19, NULL, 'sadasd cert', 'sdfsf', 70, '2026-07-14 19:02:46', NULL, 'selfstudy', 'sadasd', 'cert', NULL, 1, NULL, NULL, NULL),
(20, 12, 'xcvxc', 'xcvx', 70, '2026-07-14 20:39:24', 9, 'teacher', NULL, 'grade', 1, 1, NULL, '2026-07-15 18:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option_a` varchar(255) DEFAULT NULL,
  `option_b` varchar(255) DEFAULT NULL,
  `option_c` varchar(255) DEFAULT NULL,
  `option_d` varchar(255) DEFAULT NULL,
  `correct_index` tinyint(4) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `qtype` enum('mcq','code','essay') NOT NULL DEFAULT 'mcq',
  `code_language` varchar(30) DEFAULT NULL,
  `code_starter` text DEFAULT NULL,
  `max_points` int(11) NOT NULL DEFAULT 1,
  `auto_grade_output` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Code questions only: run the student code and compare its output to expected_output instead of manual grading',
  `expected_output` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `category_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_index`, `sort_order`, `qtype`, `code_language`, `code_starter`, `max_points`, `auto_grade_output`, `expected_output`) VALUES
(1, 1, 'dsfdsf', 'dsf', 'dsf', 'dsf', 'sdf', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(2, 2, 'zcdasasasasasasasasasasasasasas', 'asdad', 'sadsa', 'asdasd', 'sadasd', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(3, 2, 'xc vx', NULL, NULL, NULL, NULL, NULL, 1, 'essay', NULL, NULL, 10, 0, NULL),
(4, 5, '1516165', NULL, NULL, NULL, NULL, NULL, 0, 'code', 'javascript', '0000', 10, 0, NULL),
(5, 6, 'What does the <!DOCTYPE html> declaration do?', 'Loads a CSS file', 'Tells the browser to use standard HTML rules', 'Starts a comment', 'Defines the page title', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(6, 6, 'Which attribute specifies where a link points to?', 'src', 'href', 'link', 'target', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(7, 6, 'Which tag creates an unordered (bulleted) list?', '<ol>', '<list>', '<ul>', '<li>', 2, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(8, 6, 'Which tag defines a row inside a table?', '<td>', '<tr>', '<th>', '<row>', 1, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(9, 6, 'Which attribute provides alternate text for an image?', 'title', 'alt', 'caption', 'desc', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(10, 7, 'In the CSS box model, which layer sits directly outside the border?', 'Padding', 'Content', 'Margin', 'Outline', 2, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(11, 7, 'Which selector targets all elements with class=\"card\"?', '#card', '.card', '*card', ':card', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(12, 7, 'Which property turns a container into a flex container?', 'display: flex', 'position: flex', 'layout: flex', 'flex: true', 0, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(13, 7, 'Which property controls the main-axis direction of flex items?', 'align-items', 'flex-wrap', 'flex-direction', 'justify-self', 2, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(14, 7, 'Which property animates a property change smoothly over time?', 'animation-name', 'transition', 'transform', 'ease', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(15, 8, 'Which keyword declares a block-scoped variable?', 'var', 'let', 'static', 'define', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(16, 8, 'Which method prints a message to the browser console?', 'print()', 'console.log()', 'log.console()', 'echo()', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(17, 8, 'Which method selects the first matching element by CSS selector?', 'document.querySelector()', 'document.getStyle()', 'document.find()', 'document.select()', 0, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(18, 8, 'Which event fires when a button is clicked?', 'change', 'submit', 'click', 'press', 2, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(19, 8, 'Which keyword defines a reusable block of code?', 'function', 'method', 'block', 'routine', 0, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(20, 9, 'Which symbol prefixes a variable name in PHP?', '@', '$', '#', '&', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(21, 9, 'Which statement outputs text in PHP?', 'print_r only', 'echo', 'write', 'output', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(22, 9, 'Which superglobal array holds data submitted via a POST form?', '$_GET', '$_POST', '$_FORM', '$_REQUEST_POST', 1, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(23, 9, 'Which built-in PHP extension is commonly used to talk to a MySQL database?', 'PDO', 'PHPMailer', 'Composer', 'cURL', 0, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(24, 9, 'What file extension do PHP source files typically use?', '.phtml only', '.php', '.phc', '.pph', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(25, 10, 'Which function prints formatted text to the console in C?', 'echo()', 'print()', 'printf()', 'cout', 2, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(26, 10, 'Which header must be included to use printf?', '<stdlib.h>', '<stdio.h>', '<string.h>', '<stdint.h>', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(27, 10, 'Which loop is best suited for repeating a known, fixed number of times?', 'while', 'do-while', 'for', 'goto', 2, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(28, 10, 'Which keyword declares a whole-number variable?', 'int', 'num', 'integer', 'whole', 0, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(29, 10, 'Every C program needs which function as its entry point?', 'start()', 'main()', 'run()', 'init()', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(30, 11, 'Which keyword defines a class in C++?', 'struct only', 'class', 'object', 'type', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(31, 11, 'Which Standard Library container is a resizable array?', 'array', 'vector', 'list', 'set', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(32, 11, 'Which operator sends output to the console with cout?', '>>', '<<', '::', '->', 1, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(33, 11, 'Which keyword allocates memory for a new object on the heap?', 'alloc', 'new', 'create', 'malloc_obj', 1, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(34, 11, 'Which header provides std::vector?', '<vector>', '<array>', '<list>', '<memory>', 0, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(35, 12, 'Which method is the entry point of a Java application?', 'start()', 'main()', 'run()', 'init()', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(36, 12, 'Which keyword declares a class in Java?', 'class', 'struct', 'object', 'define', 0, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(37, 12, 'Which loop checks its condition before running and may execute zero times?', 'do-while', 'for', 'while', 'repeat', 2, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(38, 12, 'Which keyword declares a constant in Java?', 'const', 'final', 'static', 'fixed', 1, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(39, 12, 'Which type holds a true/false value in Java?', 'bit', 'flag', 'boolean', 'bool', 2, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(40, 13, 'Which function prints text to the console in Python?', 'echo()', 'print()', 'write()', 'log()', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(41, 13, 'Which keyword defines a function in Python?', 'func', 'def', 'function', 'lambda only', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(42, 13, 'Which data type is an ordered, changeable collection of items?', 'tuple', 'set', 'list', 'dict', 2, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(43, 13, 'Which loop iterates directly over the items of a sequence?', 'for item in sequence', 'while sequence', 'loop sequence', 'foreach sequence', 0, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(44, 13, 'Which symbol starts a single-line comment in Python?', '//', '#', '--', '/*', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(45, 14, 'Which method is the entry point of a C# console app?', 'Start()', 'Main()', 'Run()', 'Init()', 1, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(46, 14, 'Which keyword declares a variable whose type is inferred by the compiler?', 'auto', 'var', 'infer', 'let', 1, 1, 'mcq', NULL, NULL, 1, 0, NULL),
(47, 14, 'Which loop repeats a block while a condition remains true?', 'for', 'while', 'switch', 'foreach', 1, 2, 'mcq', NULL, NULL, 1, 0, NULL),
(48, 14, 'Which keyword defines a class in C#?', 'class', 'struct only', 'object', 'type', 0, 3, 'mcq', NULL, NULL, 1, 0, NULL),
(49, 14, 'Which namespace provides Console.WriteLine?', 'System.IO', 'System', 'System.Text', 'Microsoft.Console', 1, 4, 'mcq', NULL, NULL, 1, 0, NULL),
(50, 15, 'sdfsf', 'sdfsdf', 'sdf', 'f', 'd', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(51, 16, 'sdfsf', 'sdf', 'sdfds', 'sdf', 'sdf', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(52, 6, 'Write title html', NULL, NULL, NULL, NULL, NULL, 5, 'code', 'html', '', 10, 0, NULL),
(53, 6, 'asd', 'asd', 'asd', 'asd', 'sad', 0, 6, 'mcq', NULL, NULL, 1, 0, NULL),
(54, 17, 'DSCSA', 'SDS', 'SD', 'SCSD', 'SDS', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(56, 19, 'sdfsd', 'sdf', 'sdfs', 'sdf', 'sdfds', 0, 0, 'mcq', NULL, NULL, 1, 0, NULL),
(57, 18, 'how many years', NULL, NULL, NULL, NULL, NULL, 1, 'essay', NULL, NULL, 10, 0, NULL),
(58, 18, 'how many years old', NULL, NULL, NULL, NULL, NULL, 2, 'code', 'javascript', 'df', 10, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `featured_students`
--

CREATE TABLE `featured_students` (
  `id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `added_by` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `accomplishment` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `featured_students`
--

INSERT INTO `featured_students` (`id`, `school_id`, `student_id`, `added_by`, `note`, `description`, `accomplishment`, `created_at`) VALUES
(2, 3, 2, 12, NULL, NULL, NULL, '2026-07-10 19:07:40'),
(4, 3, 13, 12, 'df', 'df', 'fd', '2026-07-14 22:02:27');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `caption` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL,
  `shared_post_id` int(11) DEFAULT NULL,
  `shared_to_school_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `caption`, `image`, `video`, `shared_post_id`, `shared_to_school_id`, `created_at`) VALUES
(1, 12, 'ryttry', 'upload_160ac06b4c09.png', NULL, NULL, NULL, '2026-07-12 20:44:28'),
(2, 2, 'dfgdf', 'upload_255dda8be275.png', NULL, NULL, NULL, '2026-07-12 20:46:37'),
(3, 15, '7uu6', 'upload_8bdfb7844bba.png', NULL, NULL, NULL, '2026-07-12 22:23:40'),
(4, 2, 'erftegeg', NULL, NULL, NULL, NULL, '2026-07-12 22:56:38'),
(5, 2, 'weeeeeeee', NULL, NULL, NULL, NULL, '2026-07-12 22:56:52'),
(6, 2, 'gffgfg', NULL, NULL, NULL, NULL, '2026-07-12 23:01:35'),
(7, 12, 'rtyr', 'upload_9aca85c01276.png', NULL, NULL, NULL, '2026-07-12 23:48:07'),
(8, 12, 'fghfh', NULL, NULL, NULL, NULL, '2026-07-12 23:48:21'),
(9, 14, 'Hdhhdhdhd', NULL, NULL, NULL, NULL, '2026-07-13 00:52:54'),
(10, 16, 'Nice', 'upload_f2a7a6285e13.jpg', NULL, NULL, NULL, '2026-07-13 01:42:33'),
(11, 13, NULL, 'upload_f9652d4f14b3.png', NULL, NULL, NULL, '2026-07-13 17:31:20'),
(12, 2, 'dsfsfs', NULL, NULL, NULL, NULL, '2026-07-14 08:48:14'),
(13, 12, 'xcvxcvxv', 'upload_855a137d06d0.png', NULL, NULL, NULL, '2026-07-14 14:18:34'),
(14, 14, 'DFGDFGDFG', 'upload_570f3924cb7f.png', NULL, NULL, NULL, '2026-07-14 14:32:00'),
(15, 13, 'wow', NULL, NULL, 14, NULL, '2026-07-15 08:55:12'),
(16, 13, 'nice', NULL, NULL, 13, NULL, '2026-07-15 08:56:40'),
(17, 13, 'nnnnnn', NULL, NULL, NULL, NULL, '2026-07-15 08:57:18'),
(18, 13, 'assa', 'upload_76026caa3821.png', NULL, NULL, NULL, '2026-07-15 08:57:30'),
(19, 14, NULL, 'post_img_e72d7caf6cb8.png', NULL, NULL, NULL, '2026-07-15 10:30:42'),
(20, 14, NULL, NULL, NULL, 19, NULL, '2026-07-15 10:31:07'),
(21, 13, NULL, NULL, NULL, 20, 3, '2026-07-15 10:40:30'),
(22, 13, NULL, NULL, NULL, 20, NULL, '2026-07-15 10:40:55'),
(23, 13, NULL, NULL, NULL, 19, NULL, '2026-07-15 10:41:08'),
(24, 13, NULL, NULL, NULL, 19, NULL, '2026-07-15 10:41:20'),
(25, 13, NULL, 'post_img_2c8052603475.png', NULL, NULL, NULL, '2026-07-15 10:42:48'),
(26, 13, 'hhhh', NULL, NULL, 25, NULL, '2026-07-15 10:42:59'),
(27, 13, 'dfd', NULL, NULL, 26, NULL, '2026-07-15 11:05:10'),
(28, 13, 'kronn', NULL, NULL, 20, NULL, '2026-07-15 11:05:32'),
(29, 13, 'rf', NULL, NULL, 20, NULL, '2026-07-15 11:05:55'),
(30, 13, NULL, NULL, NULL, 16, 3, '2026-07-15 11:20:15'),
(31, 13, ',,', NULL, NULL, 25, NULL, '2026-07-15 11:23:08'),
(33, 13, NULL, 'post_img_713015668fa5.png', NULL, NULL, NULL, '2026-07-16 13:13:12'),
(34, 13, NULL, 'post_img_04a27f4d2195.png', NULL, NULL, NULL, '2026-07-16 13:13:56'),
(35, 12, NULL, NULL, NULL, 34, NULL, '2026-07-16 14:32:59'),
(36, 15, NULL, 'post_img_b1ecb562ad48.png', NULL, NULL, NULL, '2026-07-17 10:20:27');

-- --------------------------------------------------------

--
-- Table structure for table `post_comments`
--

CREATE TABLE `post_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_comments`
--

INSERT INTO `post_comments` (`id`, `post_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 1, 2, 'uyutu', '2026-07-12 20:46:56'),
(2, 1, 15, 'china oil', '2026-07-12 22:22:53'),
(3, 1, 2, 'how pinoy ako', '2026-07-12 23:00:01'),
(4, 6, 12, 'dfgdg', '2026-07-12 23:10:05'),
(5, 8, 12, 'ghjg', '2026-07-12 23:48:28'),
(6, 10, 14, 'Nice', '2026-07-13 01:43:45'),
(7, 11, 14, 'k', '2026-07-14 15:16:24'),
(8, 10, 14, '<>', '2026-07-14 15:16:34'),
(9, 10, 14, 'kkkk', '2026-07-14 15:16:54'),
(10, 10, 14, 'llllkkk', '2026-07-14 15:17:01'),
(11, 10, 14, '<relax>Hello</relax>', '2026-07-14 15:17:31'),
(12, 25, 13, 'sdfgsd', '2026-07-15 11:03:27'),
(13, 27, 13, 'sdvfd', '2026-07-15 11:08:02'),
(14, 31, 16, 'Hfhhdhd', '2026-07-15 12:20:01'),
(15, 35, 16, 'Haha', '2026-07-16 19:07:05');

-- --------------------------------------------------------

--
-- Table structure for table `post_images`
--

CREATE TABLE `post_images` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_images`
--

INSERT INTO `post_images` (`id`, `post_id`, `image`, `sort_order`) VALUES
(1, 19, 'post_img_e72d7caf6cb8.png', 0),
(2, 19, 'post_img_9f6d33780e37.png', 1),
(3, 19, 'post_img_d4d7dc36072d.png', 2),
(4, 25, 'post_img_2c8052603475.png', 0),
(5, 25, 'post_img_f2576c4c4f70.png', 1),
(6, 25, 'post_img_bc418fc1ec84.png', 2),
(7, 33, 'post_img_713015668fa5.png', 0),
(8, 33, 'post_img_35949640bd0e.png', 1),
(9, 34, 'post_img_04a27f4d2195.png', 0),
(10, 34, 'post_img_11cad977f5c1.png', 1),
(11, 34, 'post_img_7d657bc91bfe.png', 2),
(12, 34, 'post_img_2a1039a7733a.png', 3),
(13, 34, 'post_img_3cfe5ff62004.png', 4),
(14, 34, 'post_img_5d964aee52e9.png', 5),
(15, 34, 'post_img_74b931d8138e.png', 6),
(16, 34, 'post_img_aed7e0429428.png', 7),
(17, 34, 'post_img_420bd29a8fb6.png', 8),
(18, 34, 'post_img_97ac43b85006.png', 9);

-- --------------------------------------------------------

--
-- Table structure for table `post_likes`
--

CREATE TABLE `post_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_likes`
--

INSERT INTO `post_likes` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(1, 1, 12, '2026-07-12 20:44:42'),
(2, 1, 2, '2026-07-12 20:46:59'),
(3, 1, 15, '2026-07-12 22:22:43'),
(4, 3, 2, '2026-07-12 22:47:28'),
(5, 6, 12, '2026-07-12 23:10:01'),
(6, 8, 12, '2026-07-12 23:48:25'),
(7, 9, 14, '2026-07-13 00:53:00'),
(8, 10, 14, '2026-07-13 01:43:38'),
(9, 9, 13, '2026-07-13 17:21:49'),
(10, 11, 13, '2026-07-13 17:31:25'),
(11, 12, 2, '2026-07-13 17:48:32'),
(12, 13, 14, '2026-07-13 23:32:48'),
(13, 11, 14, '2026-07-14 00:16:13'),
(14, 15, 13, '2026-07-14 17:55:41'),
(16, 18, 12, '2026-07-14 18:42:02'),
(17, 17, 12, '2026-07-14 18:42:06'),
(18, 16, 12, '2026-07-14 18:42:11'),
(19, 27, 13, '2026-07-14 20:08:04'),
(20, 21, 13, '2026-07-14 20:22:37'),
(21, 18, 13, '2026-07-14 20:31:03'),
(22, 35, 16, '2026-07-16 04:06:58');

-- --------------------------------------------------------

--
-- Table structure for table `post_saves`
--

CREATE TABLE `post_saves` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_saves`
--

INSERT INTO `post_saves` (`id`, `post_id`, `user_id`, `created_at`) VALUES
(1, 27, 13, '2026-07-14 20:19:29'),
(2, 29, 13, '2026-07-14 20:19:35'),
(3, 21, 13, '2026-07-14 20:22:35');

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `dean_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `mission` text DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `signer_name` varchar(100) DEFAULT NULL,
  `signer_title` varchar(150) DEFAULT NULL,
  `signer_signature` varchar(255) DEFAULT NULL,
  `dean_signer_title` varchar(150) DEFAULT NULL,
  `dean_signature` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schools`
--

INSERT INTO `schools` (`id`, `dean_id`, `name`, `mission`, `logo`, `signer_name`, `signer_title`, `signer_signature`, `dean_signer_title`, `dean_signature`, `created_at`) VALUES
(1, 4, 'dfsdfsdfdf', NULL, 'upload_583ca721e987.png', 'dsfdf', 'sdfs', 'upload_05563551ca4f.png', NULL, NULL, '2026-07-07 23:40:34'),
(2, 9, 'Bcc', NULL, 'upload_22c4e9c7eff7.jpg', NULL, NULL, NULL, NULL, NULL, '2026-07-09 01:23:48'),
(3, 11, 'BISU', 'WE LOVE YOU', 'upload_f85dd1b49016.png', NULL, NULL, NULL, NULL, NULL, '2026-07-10 17:59:54');

-- --------------------------------------------------------

--
-- Table structure for table `student_notes`
--

CREATE TABLE `student_notes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_notes`
--

INSERT INTO `student_notes` (`id`, `student_id`, `body`, `created_at`) VALUES
(1, 13, 'fdgdg', '2026-07-14 20:01:36');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'registrar user id who created this subject',
  `title` varchar(150) NOT NULL,
  `code` varchar(20) DEFAULT NULL COMMENT 'e.g. "SE101" — shown on the student COR',
  `units` int(11) NOT NULL DEFAULT 3 COMMENT 'Credit units — shown on the student COR',
  `term` varchar(20) DEFAULT NULL COMMENT 'e.g. "1st Semester" — set by the registrar',
  `academic_year` varchar(20) DEFAULT NULL COMMENT 'e.g. "2026-2027"',
  `school_id` int(11) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `teacher_id`, `created_by`, `title`, `code`, `units`, `term`, `academic_year`, `school_id`, `description`, `created_at`) VALUES
(2, 3, NULL, 'science', NULL, 3, NULL, NULL, NULL, '', '2026-07-07 23:06:35'),
(3, 3, NULL, 'dfgfdg', NULL, 3, NULL, NULL, NULL, 'dfgdf', '2026-07-08 18:32:39'),
(4, 5, NULL, '10', NULL, 3, NULL, NULL, NULL, '', '2026-07-08 21:47:42'),
(5, 3, NULL, 'frgr', NULL, 3, NULL, NULL, NULL, 'ergter', '2026-07-09 00:33:36'),
(6, 3, NULL, 'wsd', NULL, 3, NULL, NULL, NULL, 'wsd', '2026-07-09 00:41:26'),
(7, 8, NULL, 'aaaa', NULL, 3, NULL, NULL, NULL, 'aaaa', '2026-07-09 00:51:57'),
(8, 12, NULL, 'Computer Programing', NULL, 3, NULL, NULL, NULL, 'adstrdtqwd', '2026-07-10 18:02:45'),
(9, 12, NULL, 'DFG', NULL, 3, NULL, NULL, NULL, 'DFG', '2026-07-12 18:47:59'),
(10, 12, 18, 'CSS', 's101', 3, '1', '2026-2027', 3, 'dfgd', '2026-07-16 00:01:43'),
(11, 12, 18, 'ART', 'ART', 6, 'Summer', '2026-2027', 3, '', '2026-07-16 00:45:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('student','teacher','dean','admin','registrar') NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','pending','disabled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `id_number` varchar(50) DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `school_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `signer_name` varchar(100) DEFAULT NULL,
  `signer_title` varchar(150) DEFAULT NULL,
  `last_lesson_cat` varchar(50) DEFAULT NULL,
  `last_lesson_slug` varchar(80) DEFAULT NULL,
  `last_lesson_at` datetime DEFAULT NULL,
  `bio` varchar(160) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `email`, `password_hash`, `status`, `created_at`, `id_number`, `course`, `school_id`, `avatar`, `created_by`, `signer_name`, `signer_title`, `last_lesson_cat`, `last_lesson_slug`, `last_lesson_at`, `bio`) VALUES
(1, 'teacher', 'Gilmar', 'tiktoktubeph@gmail.com', '$2y$10$zwLpw9d7bjUIW5IcjMMr5O2WH01lvYAA4.30Mi2KMl3IY3DM5d2aq', 'active', '2026-07-07 21:26:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'student', 'gil', 'abcd@gmail.com', '$2y$10$wrgMtmF.OtCikvLHwio04O0Ac0Q7gDUjt5mPGIVhHlL9VnhwnVAy.', 'active', '2026-07-07 21:42:40', NULL, NULL, 3, 'upload_19153c083d3c.png', NULL, NULL, NULL, 'html', 'wedfwe', '2026-07-13 22:36:09', NULL),
(3, 'teacher', 'fem', 'abcde@gmail.com', '$2y$10$aj63u2EuiMoI3pUAlR3bs.T7qYdQmxbIJ5OxSmjTybB44jPMvIT8K', 'active', '2026-07-07 21:44:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'dean', 'jars', 'jars@gmail.com', '$2y$10$MF50cTkxfwFKR8eAI6hYaeoneTtNGKLZRp9IoRbHeVYO6pF4Eidm6', 'active', '2026-07-07 23:39:20', 'swdasdad', 'sadsadasdsadasd', NULL, NULL, NULL, NULL, NULL, 'csharp', 'variables', '2026-07-12 20:01:36', NULL),
(5, 'teacher', 'noy', 'noy@gmail.com', '$2y$10$IkvkcHq3E.G40ws0VpYHNu7R8735e3MTZ1rktdEw3mZirZCSY8Hwe', 'active', '2026-07-07 23:41:13', '111', 'sci', 1, NULL, 4, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'student', 'shy', 'd44th_void@gmail.com', '$2y$10$v0jzCm3WEPfF6OwU8QqEQOtiQbTn.R2BKlF8IF7a0l3UqKY1mO/3.', 'active', '2026-07-07 23:47:38', '0212123-222', 'fdgfdgfdgdg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'dean', 'Yoh', 'gilmaraparece@gmail.com', '$2y$10$4XzZfvvkszQ6IOy.S2XHzOYjpdkDw.5JZcpFD3NtiyZBul5k/vCKK', 'active', '2026-07-08 01:56:58', 'Ndbhdns', 'Bdbdbdh', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'teacher', 'feghdfw', 'sdf@gmail.com', '$2y$10$AZAVyN49QOvCOoA.Nhfr9uK4sp86ovoL69HafH5Wg7c1PUHmySBeG', 'active', '2026-07-09 00:50:17', '12356213-1231', 'EDUC', NULL, NULL, NULL, 'wefer', 'rwereer', NULL, NULL, NULL, NULL),
(9, 'dean', 'Chona', 'aparecegilmar12@gmail.com', '$2y$10$NJ2HzrEZ1S.5H0PlEFQys.KWOIgImJIlLP0B.qremOEP0VESsNfLm', 'active', '2026-07-09 01:21:12', '1083836-83893', 'Educ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'student', 'Hdjjduud', 'aparecegilmar10@gmail.com', '$2y$10$Hl287EGBPDYENimig5osv.1JCGraO6kNzofVyIAEsYalD5..wNQH.', 'active', '2026-07-10 02:28:25', '6373-33', 'BSIT', 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'dean', 'Jayner', 'jayner@gmail.com', '$2y$10$GfaJKAeDFnaipC72rOcTKe6LCYZSs0EgdABLmvBc4O82VDCLwPpcK', 'active', '2026-07-10 17:58:12', '202225', 'Dean', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'teacher', 'Gilmar', 'gilmar@gmail.com', '$2y$10$0MO7Pp6cRKDcNLitVBA63.SLHQ.fwRdYPAR71W4Jku05JUvowNhNu', 'active', '2026-07-10 18:00:45', '202225-679', 'BSIT', 3, 'upload_bb57018faf0f.png', 11, 'VGFDFGD', 'FGDGDG', 'php', 'mysql', '2026-07-14 20:44:13', NULL),
(13, 'student', 'shyn', 'shyndy@gmail.com', '$2y$10$UH.2yZdP0qQ1jwa6pHY9zueSrjOqvZmDGJ809NWNEPhCZdZE/T4Ye', 'active', '2026-07-10 19:03:36', '202222-287', 'BSIT', 3, 'upload_e00d70ce324e.png', NULL, NULL, NULL, 'html', 'wedfwe', '2026-07-15 22:25:06', NULL),
(14, 'admin', 'admin', 'admin@gmail.com', '$2y$10$AntGzWT8IlfPkbKYOtuNlefGQWa64AIvUp5ZjvRcnk6zMEZvYamJi', 'active', '2026-07-10 23:11:01', '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'dean', 'erwe', 'shyndys.saya-ang@sportscity.com.ph', '$2y$10$Wn2a26FKYITOmZOOTb1vVekkboF4PPdhEk0a4XiMAxz9EUmt7QKE.', 'active', '2026-07-12 22:22:09', '213214r', 'fdsdf', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'student', 'Grlma', 'gilmaraparece25@gmail.com', '$2y$10$ab/ERMhDk6s58Ct93a.SoOLL70fEXhOHRD6a4g00NemdcQCg5hFKm', 'active', '2026-07-13 01:05:40', '6373-3376', 'BSED', 3, 'upload_9184d1d6aae3.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'student', 'elsie', 'elsie@gmail.com', '$2y$10$0fgSOgHgqQANN.WrIbGlG.R8WHoyU.CmsFh12XgeJw/SSsMJL2fwe', 'active', '2026-07-15 22:49:55', '5555-555', 'BSIT', 3, 'upload_2dbabd8732f2.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'registrar', 'registrar', 'registrar@gmail.com', '$2y$10$pxs8YIME1Ark3yKqn/lzfOeNIcwxJ7UVGvEvNVwE.CQSdtGwNz8k6', 'active', '2026-07-15 23:52:23', 'REG-102', 'BCC', 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_notes`
--

CREATE TABLE `user_notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT 'Untitled note',
  `type` enum('text','code') NOT NULL DEFAULT 'text',
  `content` longtext DEFAULT NULL,
  `font_family` varchar(60) DEFAULT NULL,
  `font_size` int(11) DEFAULT NULL,
  `code_language` varchar(30) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_notes`
--

INSERT INTO `user_notes` (`id`, `user_id`, `title`, `type`, `content`, `font_family`, `font_size`, `code_language`, `attachment`, `attachment_name`, `created_at`, `updated_at`) VALUES
(4, 14, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-12 20:15:25', '2026-07-12 20:15:25'),
(5, 14, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-12 20:15:27', '2026-07-12 20:15:27'),
(6, 14, 'Untitled code note', 'code', '', NULL, NULL, 'javascript', NULL, NULL, '2026-07-12 20:15:47', '2026-07-12 20:15:47'),
(8, 2, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-12 22:48:07', '2026-07-12 22:48:07'),
(10, 12, 'Untitled code note', 'code', 'fghgfh', NULL, NULL, 'javascript', NULL, NULL, '2026-07-12 23:49:10', '2026-07-12 23:49:14'),
(11, 12, 'Untitled text note', 'text', 'gfhfgh', 'Inter, sans-serif', 16, NULL, 'note_6f541bdfa39d.png', 'image (31).png', '2026-07-12 23:49:17', '2026-07-12 23:49:31'),
(12, 12, 'Untitled code note', 'code', 'a', NULL, NULL, 'php', NULL, NULL, '2026-07-12 23:54:02', '2026-07-12 23:54:11'),
(13, 13, 'sdfsfsdfd', 'code', 'sdsd', NULL, NULL, 'javascript', NULL, NULL, '2026-07-13 00:20:22', '2026-07-13 00:20:35'),
(14, 13, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-13 00:20:42', '2026-07-13 00:20:42'),
(15, 14, 'Un', 'text', 'Hrhehhehhehhr', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-13 00:52:15', '2026-07-13 00:52:28'),
(16, 2, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-13 19:40:48', '2026-07-13 19:40:48'),
(17, 13, 'Untitled code note', 'code', '', NULL, NULL, 'javascript', NULL, NULL, '2026-07-13 22:08:57', '2026-07-13 22:08:57'),
(18, 13, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-13 22:09:00', '2026-07-13 22:09:00'),
(19, 13, 'Untitled code note', 'code', '', NULL, NULL, 'javascript', NULL, NULL, '2026-07-13 22:09:02', '2026-07-13 22:09:02'),
(20, 12, 'Untitled code note', 'code', '', NULL, NULL, 'javascript', NULL, NULL, '2026-07-13 22:18:18', '2026-07-13 22:18:18'),
(21, 12, 'Untitled text note', 'text', '', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-13 22:18:23', '2026-07-13 22:18:23'),
(22, 12, 'Untitled code note', 'code', 'ghjghjhgj', NULL, NULL, 'python', NULL, NULL, '2026-07-13 22:18:31', '2026-07-14 22:05:22'),
(23, 16, 'Notes', 'text', 'Hahhaa', 'Inter, sans-serif', 16, NULL, NULL, NULL, '2026-07-14 01:26:12', '2026-07-14 01:26:29'),
(24, 16, 'Console.log', 'code', 'console.log(\"hello\");', NULL, NULL, 'javascript', NULL, NULL, '2026-07-14 01:26:33', '2026-07-14 01:27:25'),
(25, 13, 'Untitled code note', 'code', '', NULL, NULL, 'javascript', NULL, NULL, '2026-07-14 20:24:23', '2026-07-14 20:24:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attempts`
--
ALTER TABLE `attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attempt_id` (`attempt_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `class_codes`
--
ALTER TABLE `class_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_code` (`code`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_subject` (`student_id`,`subject_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `exam_categories`
--
ALTER TABLE `exam_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_language_key` (`language_key`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `owner_type` (`owner_type`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `featured_students`
--
ALTER TABLE `featured_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `school_student` (`school_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shared_post_id` (`shared_post_id`),
  ADD KEY `shared_to_school_id` (`shared_to_school_id`);

--
-- Indexes for table `post_comments`
--
ALTER TABLE `post_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_images`
--
ALTER TABLE `post_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `post_likes`
--
ALTER TABLE `post_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_user` (`post_id`,`user_id`);

--
-- Indexes for table `post_saves`
--
ALTER TABLE `post_saves`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_user` (`post_id`,`user_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_dean` (`dean_id`);

--
-- Indexes for table `student_notes`
--
ALTER TABLE `student_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `school_id` (`school_id`);

--
-- Indexes for table `user_notes`
--
ALTER TABLE `user_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `class_codes`
--
ALTER TABLE `class_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `exam_categories`
--
ALTER TABLE `exam_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `featured_students`
--
ALTER TABLE `featured_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `post_comments`
--
ALTER TABLE `post_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `post_images`
--
ALTER TABLE `post_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `post_likes`
--
ALTER TABLE `post_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `post_saves`
--
ALTER TABLE `post_saves`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_notes`
--
ALTER TABLE `student_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `user_notes`
--
ALTER TABLE `user_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attempts`
--
ALTER TABLE `attempts`
  ADD CONSTRAINT `attempts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attempts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `exam_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schools`
--
ALTER TABLE `schools`
  ADD CONSTRAINT `schools_ibfk_1` FOREIGN KEY (`dean_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_school_fk` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
