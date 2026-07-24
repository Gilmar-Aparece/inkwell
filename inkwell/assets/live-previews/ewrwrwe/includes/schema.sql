-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql303.infinityfree.com
-- Generation Time: Jul 10, 2026 at 10:49 PM
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
(7, 2, 5, 'pending', 0, 0, 10, NULL, NULL, NULL, '2026-07-10 17:41:09', NULL),
(8, 2, 17, 'graded', 1, 0, 1, 100, 1, NULL, '2026-07-10 19:37:03', '2026-07-10 19:37:03');

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

INSERT INTO `attempt_answers` (`id`, `attempt_id`, `question_id`, `qtype`, `selected_index`, `text_answer`, `is_correct`, `points_awarded`, `max_points`, `feedback`) VALUES
(1, 1, 2, 'mcq', 0, NULL, 1, 1, 1, NULL),
(2, 1, 3, 'essay', NULL, 'sdcas', NULL, 10, 10, NULL),
(3, 2, 4, 'code', NULL, '00001332331321212111121121514', NULL, 10, 10, '1141111'),
(4, 3, 4, 'code', NULL, '0000', NULL, 2, 10, NULL),
(5, 4, 2, 'mcq', 0, NULL, 1, 1, 1, NULL),
(6, 4, 3, 'essay', NULL, 'qwdeqw', NULL, 10, 10, 'good'),
(7, 5, 51, 'mcq', 0, NULL, 1, 1, 1, NULL),
(8, 6, 5, 'mcq', 1, NULL, 1, 1, 1, NULL),
(9, 6, 6, 'mcq', 1, NULL, 1, 1, 1, NULL),
(10, 6, 7, 'mcq', 2, NULL, 1, 1, 1, NULL),
(11, 6, 8, 'mcq', 1, NULL, 1, 1, 1, NULL),
(12, 6, 9, 'mcq', 1, NULL, 1, 1, 1, NULL),
(13, 6, 52, 'code', NULL, 'Hdhhdhd', NULL, NULL, 10, NULL),
(14, 6, 53, 'mcq', 0, NULL, 1, 1, 1, NULL),
(15, 7, 4, 'code', NULL, '0000', NULL, NULL, 10, NULL),
(16, 8, 54, 'mcq', 0, NULL, 1, 1, 1, NULL);

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
  `issued_at` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `student_id`, `student_name`, `category_type`, `category_key`, `category_id`, `label`, `teacher_id`, `teacher_name`, `score`, `total`, `percent`, `issued_at`) VALUES
('3acc97aa79d5b792', 2, 'gil', 'teacher', NULL, 2, 'test 1', 3, 'fem', 11, 11, 100, '2026-07-07'),
('532ec850c82d120b', 2, 'gil', 'teacher', NULL, 16, 'aaaa', 8, 'feghdfw', 1, 1, 100, '2026-07-09'),
('5e1d05933209f61b', 2, 'gil', 'teacher', NULL, 1, 'dfd', 3, 'fem', 1, 1, 100, '2026-07-07'),
('84693423cb89a5b8', 2, 'gil', 'teacher', NULL, 2, 'test 1', 3, 'fem', 11, 11, 100, '2026-07-09'),
('e09f2102c8b7a048', 2, 'gil', 'teacher', NULL, 17, 'TEST 1', 12, 'Gilmar', 1, 1, 100, '2026-07-10');

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

INSERT INTO `enrollments` (`id`, `student_id`, `category_id`, `enrolled_at`, `subject_id`, `status`, `decided_at`) VALUES
(1, 2, 1, '2026-07-07 21:48:13', 1, 'pending', NULL),
(2, 2, 0, '2026-07-07 23:08:33', 2, 'approved', '2026-07-08 18:22:01'),
(3, 2, 0, '2026-07-08 18:34:13', 3, 'approved', '2026-07-08 18:38:51'),
(4, 2, 0, '2026-07-08 21:49:48', 4, 'approved', '2026-07-08 21:50:15'),
(5, 2, 0, '2026-07-09 00:46:18', 6, 'pending', NULL),
(6, 2, 0, '2026-07-09 00:53:35', 7, 'approved', '2026-07-09 00:54:04'),
(7, 10, 0, '2026-07-10 02:29:15', 7, 'pending', NULL),
(8, 10, 0, '2026-07-10 02:29:47', 6, 'pending', NULL),
(9, 2, 0, '2026-07-10 18:26:10', 8, 'approved', '2026-07-10 18:26:33');

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
  `link_url` varchar(500) DEFAULT NULL COMMENT 'Optional call-to-action link, e.g. straight to an exam',
  `link_label` varchar(100) DEFAULT NULL COMMENT 'Optional button text for link_url, e.g. "Take the exam →"',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `author_id`, `author_role`, `title`, `body`, `created_at`) VALUES
(1, 3, 'teacher', 'trytr', 'trytry', '2026-07-08 19:01:51'),
(2, 5, 'teacher', '21321', '00', '2026-07-08 21:45:49'),
(3, 3, 'teacher', 'new', 'dfgdgdgd', '2026-07-08 22:39:35'),
(4, 12, 'teacher', 'jUNE 2', 'WAY KLSI', '2026-07-10 18:28:29');

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

INSERT INTO `exam_categories` (`id`, `teacher_id`, `title`, `description`, `pass_score`, `created_at`, `subject_id`, `owner_type`, `language_key`, `purpose`, `max_attempts`) VALUES
(1, 3, 'dfd', 'dsfds', 70, '2026-07-07 21:46:42', 1, 'teacher', NULL, 'cert', NULL),
(2, 3, 'test 1', '', 70, '2026-07-07 23:07:01', 2, 'teacher', NULL, 'cert', NULL),
(3, 3, 'dsadas', 'asdasd', 70, '2026-07-08 18:35:51', 3, 'teacher', NULL, 'grade', NULL),
(4, NULL, 'aSas', 'aSs', 70, '2026-07-08 19:14:06', NULL, 'admin', NULL, 'cert', NULL),
(5, 5, '0', '', 100, '2026-07-08 21:48:12', 4, 'teacher', NULL, 'grade', NULL),
(6, NULL, 'HTML Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'html', 'cert', NULL),
(7, NULL, 'CSS Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'css', 'cert', NULL),
(8, NULL, 'JavaScript Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'js', 'cert', NULL),
(9, NULL, 'PHP Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'php', 'cert', NULL),
(10, NULL, 'C Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'c', 'cert', NULL),
(11, NULL, 'C++ Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'cpp', 'cert', NULL),
(12, NULL, 'Java Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'java', 'cert', NULL),
(13, NULL, 'Python Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'python', 'cert', NULL),
(14, NULL, 'C# Certification Exam', NULL, 70, '2026-07-08 23:42:19', NULL, 'selfstudy', 'csharp', 'cert', NULL),
(15, 3, 'sdfs', 'sds', 70, '2026-07-09 00:42:59', 6, 'teacher', NULL, 'cert', NULL),
(16, 8, 'aaaa', 'aa', 70, '2026-07-09 00:52:20', 7, 'teacher', NULL, 'cert', 1),
(17, 12, 'TEST 1', 'SWDWE', 70, '2026-07-10 18:24:00', 8, 'teacher', NULL, 'cert', 1);

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

INSERT INTO `exam_questions` (`id`, `category_id`, `question`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_index`, `sort_order`, `qtype`, `code_language`, `code_starter`, `max_points`) VALUES
(1, 1, 'dsfdsf', 'dsf', 'dsf', 'dsf', 'sdf', 0, 0, 'mcq', NULL, NULL, 1),
(2, 2, 'zcdasasasasasasasasasasasasasas', 'asdad', 'sadsa', 'asdasd', 'sadasd', 0, 0, 'mcq', NULL, NULL, 1),
(3, 2, 'xc vx', NULL, NULL, NULL, NULL, NULL, 1, 'essay', NULL, NULL, 10),
(4, 5, '1516165', NULL, NULL, NULL, NULL, NULL, 0, 'code', 'javascript', '0000', 10),
(5, 6, 'What does the <!DOCTYPE html> declaration do?', 'Loads a CSS file', 'Tells the browser to use standard HTML rules', 'Starts a comment', 'Defines the page title', 1, 0, 'mcq', NULL, NULL, 1),
(6, 6, 'Which attribute specifies where a link points to?', 'src', 'href', 'link', 'target', 1, 1, 'mcq', NULL, NULL, 1),
(7, 6, 'Which tag creates an unordered (bulleted) list?', '<ol>', '<list>', '<ul>', '<li>', 2, 2, 'mcq', NULL, NULL, 1),
(8, 6, 'Which tag defines a row inside a table?', '<td>', '<tr>', '<th>', '<row>', 1, 3, 'mcq', NULL, NULL, 1),
(9, 6, 'Which attribute provides alternate text for an image?', 'title', 'alt', 'caption', 'desc', 1, 4, 'mcq', NULL, NULL, 1),
(10, 7, 'In the CSS box model, which layer sits directly outside the border?', 'Padding', 'Content', 'Margin', 'Outline', 2, 0, 'mcq', NULL, NULL, 1),
(11, 7, 'Which selector targets all elements with class=\"card\"?', '#card', '.card', '*card', ':card', 1, 1, 'mcq', NULL, NULL, 1),
(12, 7, 'Which property turns a container into a flex container?', 'display: flex', 'position: flex', 'layout: flex', 'flex: true', 0, 2, 'mcq', NULL, NULL, 1),
(13, 7, 'Which property controls the main-axis direction of flex items?', 'align-items', 'flex-wrap', 'flex-direction', 'justify-self', 2, 3, 'mcq', NULL, NULL, 1),
(14, 7, 'Which property animates a property change smoothly over time?', 'animation-name', 'transition', 'transform', 'ease', 1, 4, 'mcq', NULL, NULL, 1),
(15, 8, 'Which keyword declares a block-scoped variable?', 'var', 'let', 'static', 'define', 1, 0, 'mcq', NULL, NULL, 1),
(16, 8, 'Which method prints a message to the browser console?', 'print()', 'console.log()', 'log.console()', 'echo()', 1, 1, 'mcq', NULL, NULL, 1),
(17, 8, 'Which method selects the first matching element by CSS selector?', 'document.querySelector()', 'document.getStyle()', 'document.find()', 'document.select()', 0, 2, 'mcq', NULL, NULL, 1),
(18, 8, 'Which event fires when a button is clicked?', 'change', 'submit', 'click', 'press', 2, 3, 'mcq', NULL, NULL, 1),
(19, 8, 'Which keyword defines a reusable block of code?', 'function', 'method', 'block', 'routine', 0, 4, 'mcq', NULL, NULL, 1),
(20, 9, 'Which symbol prefixes a variable name in PHP?', '@', '$', '#', '&', 1, 0, 'mcq', NULL, NULL, 1),
(21, 9, 'Which statement outputs text in PHP?', 'print_r only', 'echo', 'write', 'output', 1, 1, 'mcq', NULL, NULL, 1),
(22, 9, 'Which superglobal array holds data submitted via a POST form?', '$_GET', '$_POST', '$_FORM', '$_REQUEST_POST', 1, 2, 'mcq', NULL, NULL, 1),
(23, 9, 'Which built-in PHP extension is commonly used to talk to a MySQL database?', 'PDO', 'PHPMailer', 'Composer', 'cURL', 0, 3, 'mcq', NULL, NULL, 1),
(24, 9, 'What file extension do PHP source files typically use?', '.phtml only', '.php', '.phc', '.pph', 1, 4, 'mcq', NULL, NULL, 1),
(25, 10, 'Which function prints formatted text to the console in C?', 'echo()', 'print()', 'printf()', 'cout', 2, 0, 'mcq', NULL, NULL, 1),
(26, 10, 'Which header must be included to use printf?', '<stdlib.h>', '<stdio.h>', '<string.h>', '<stdint.h>', 1, 1, 'mcq', NULL, NULL, 1),
(27, 10, 'Which loop is best suited for repeating a known, fixed number of times?', 'while', 'do-while', 'for', 'goto', 2, 2, 'mcq', NULL, NULL, 1),
(28, 10, 'Which keyword declares a whole-number variable?', 'int', 'num', 'integer', 'whole', 0, 3, 'mcq', NULL, NULL, 1),
(29, 10, 'Every C program needs which function as its entry point?', 'start()', 'main()', 'run()', 'init()', 1, 4, 'mcq', NULL, NULL, 1),
(30, 11, 'Which keyword defines a class in C++?', 'struct only', 'class', 'object', 'type', 1, 0, 'mcq', NULL, NULL, 1),
(31, 11, 'Which Standard Library container is a resizable array?', 'array', 'vector', 'list', 'set', 1, 1, 'mcq', NULL, NULL, 1),
(32, 11, 'Which operator sends output to the console with cout?', '>>', '<<', '::', '->', 1, 2, 'mcq', NULL, NULL, 1),
(33, 11, 'Which keyword allocates memory for a new object on the heap?', 'alloc', 'new', 'create', 'malloc_obj', 1, 3, 'mcq', NULL, NULL, 1),
(34, 11, 'Which header provides std::vector?', '<vector>', '<array>', '<list>', '<memory>', 0, 4, 'mcq', NULL, NULL, 1),
(35, 12, 'Which method is the entry point of a Java application?', 'start()', 'main()', 'run()', 'init()', 1, 0, 'mcq', NULL, NULL, 1),
(36, 12, 'Which keyword declares a class in Java?', 'class', 'struct', 'object', 'define', 0, 1, 'mcq', NULL, NULL, 1),
(37, 12, 'Which loop checks its condition before running and may execute zero times?', 'do-while', 'for', 'while', 'repeat', 2, 2, 'mcq', NULL, NULL, 1),
(38, 12, 'Which keyword declares a constant in Java?', 'const', 'final', 'static', 'fixed', 1, 3, 'mcq', NULL, NULL, 1),
(39, 12, 'Which type holds a true/false value in Java?', 'bit', 'flag', 'boolean', 'bool', 2, 4, 'mcq', NULL, NULL, 1),
(40, 13, 'Which function prints text to the console in Python?', 'echo()', 'print()', 'write()', 'log()', 1, 0, 'mcq', NULL, NULL, 1),
(41, 13, 'Which keyword defines a function in Python?', 'func', 'def', 'function', 'lambda only', 1, 1, 'mcq', NULL, NULL, 1),
(42, 13, 'Which data type is an ordered, changeable collection of items?', 'tuple', 'set', 'list', 'dict', 2, 2, 'mcq', NULL, NULL, 1),
(43, 13, 'Which loop iterates directly over the items of a sequence?', 'for item in sequence', 'while sequence', 'loop sequence', 'foreach sequence', 0, 3, 'mcq', NULL, NULL, 1),
(44, 13, 'Which symbol starts a single-line comment in Python?', '//', '#', '--', '/*', 1, 4, 'mcq', NULL, NULL, 1),
(45, 14, 'Which method is the entry point of a C# console app?', 'Start()', 'Main()', 'Run()', 'Init()', 1, 0, 'mcq', NULL, NULL, 1),
(46, 14, 'Which keyword declares a variable whose type is inferred by the compiler?', 'auto', 'var', 'infer', 'let', 1, 1, 'mcq', NULL, NULL, 1),
(47, 14, 'Which loop repeats a block while a condition remains true?', 'for', 'while', 'switch', 'foreach', 1, 2, 'mcq', NULL, NULL, 1),
(48, 14, 'Which keyword defines a class in C#?', 'class', 'struct only', 'object', 'type', 0, 3, 'mcq', NULL, NULL, 1),
(49, 14, 'Which namespace provides Console.WriteLine?', 'System.IO', 'System', 'System.Text', 'Microsoft.Console', 1, 4, 'mcq', NULL, NULL, 1),
(50, 15, 'sdfsf', 'sdfsdf', 'sdf', 'f', 'd', 0, 0, 'mcq', NULL, NULL, 1),
(51, 16, 'sdfsf', 'sdf', 'sdfds', 'sdf', 'sdf', 0, 0, 'mcq', NULL, NULL, 1),
(52, 6, 'Write title html', NULL, NULL, NULL, NULL, NULL, 5, 'code', 'html', '', 10),
(53, 6, 'asd', 'asd', 'asd', 'asd', 'sad', 0, 6, 'mcq', NULL, NULL, 1),
(54, 17, 'DSCSA', 'SDS', 'SD', 'SCSD', 'SDS', 0, 0, 'mcq', NULL, NULL, 1);

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
(3, 3, 13, 11, NULL, NULL, NULL, '2026-07-10 19:08:54');

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

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `created_at`) VALUES
(1, 'BSEED', 'Bachelor of Secondary Education', current_timestamp()),
(2, 'BSIT', 'Bachelor of Science in Information Technology', current_timestamp()),
(3, 'BSHM', 'Bachelor of Science in Hospitality Management', current_timestamp());

-- --------------------------------------------------------

--
-- Table structure for table `schools`
--

CREATE TABLE `schools` (
  `id` int(11) NOT NULL,
  `dean_id` int(11) DEFAULT NULL,
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

INSERT INTO `schools` (`id`, `dean_id`, `name`, `mission`, `logo`, `signer_name`, `signer_title`, `signer_signature`, `created_at`) VALUES
(1, 4, 'dfsdfsdfdf', NULL, 'upload_583ca721e987.png', 'dsfdf', 'sdfs', 'upload_05563551ca4f.png', '2026-07-07 23:40:34'),
(2, 9, 'Bcc', NULL, 'upload_22c4e9c7eff7.jpg', NULL, NULL, NULL, '2026-07-09 01:23:48'),
(3, 11, 'BISU', 'WE LOVE YOU', 'upload_7c4bc6c7cbde.png', NULL, NULL, NULL, '2026-07-10 17:59:54');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `code` varchar(20) DEFAULT NULL COMMENT 'e.g. "SE101" — shown on the student COR',
  `units` int(11) NOT NULL DEFAULT 3 COMMENT 'Credit units — shown on the student COR',
  `description` varchar(500) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL COMMENT 'registrar user id who created this subject',
  `term` varchar(20) DEFAULT NULL COMMENT 'e.g. "1st Semester" — set by the registrar',
  `academic_year` varchar(20) DEFAULT NULL COMMENT 'e.g. "2026-2027"',
  `school_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL COMMENT 'e.g. BSEED/BSIT/BSHM — defaults to the assigned teacher''s department when created',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `teacher_id`, `title`, `description`, `created_at`) VALUES
(2, 3, 'science', '', '2026-07-07 23:06:35'),
(3, 3, 'dfgfdg', 'dfgdf', '2026-07-08 18:32:39'),
(4, 5, '10', '', '2026-07-08 21:47:42'),
(5, 3, 'frgr', 'ergter', '2026-07-09 00:33:36'),
(6, 3, 'wsd', 'wsd', '2026-07-09 00:41:26'),
(7, 8, 'aaaa', 'aaaa', '2026-07-09 00:51:57'),
(8, 12, 'Computer Programing', 'adstrdtqwd', '2026-07-10 18:02:45');

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
  `department_id` int(11) DEFAULT NULL COMMENT 'Set on teacher and dean accounts by the registrar; not used for students',
  `avatar` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `signer_name` varchar(100) DEFAULT NULL,
  `signer_title` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `name`, `email`, `password_hash`, `status`, `created_at`, `id_number`, `course`, `school_id`, `avatar`, `created_by`, `signer_name`, `signer_title`) VALUES
(1, 'teacher', 'Gilmar', 'tiktoktubeph@gmail.com', '$2y$10$zwLpw9d7bjUIW5IcjMMr5O2WH01lvYAA4.30Mi2KMl3IY3DM5d2aq', 'active', '2026-07-07 21:26:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'student', 'gil', 'abcd@gmail.com', '$2y$10$wrgMtmF.OtCikvLHwio04O0Ac0Q7gDUjt5mPGIVhHlL9VnhwnVAy.', 'active', '2026-07-07 21:42:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'teacher', 'fem', 'abcde@gmail.com', '$2y$10$aj63u2EuiMoI3pUAlR3bs.T7qYdQmxbIJ5OxSmjTybB44jPMvIT8K', 'active', '2026-07-07 21:44:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'dean', 'jars', 'jars@gmail.com', '$2y$10$MF50cTkxfwFKR8eAI6hYaeoneTtNGKLZRp9IoRbHeVYO6pF4Eidm6', 'active', '2026-07-07 23:39:20', 'swdasdad', 'sadsadasdsadasd', NULL, NULL, NULL, NULL, NULL),
(5, 'teacher', 'noy', 'noy@gmail.com', '$2y$10$IkvkcHq3E.G40ws0VpYHNu7R8735e3MTZ1rktdEw3mZirZCSY8Hwe', 'active', '2026-07-07 23:41:13', '111', 'sci', 1, NULL, 4, NULL, NULL),
(6, 'student', 'shy', 'd44th_void@gmail.com', '$2y$10$v0jzCm3WEPfF6OwU8QqEQOtiQbTn.R2BKlF8IF7a0l3UqKY1mO/3.', 'active', '2026-07-07 23:47:38', '0212123-222', 'fdgfdgfdgdg', NULL, NULL, NULL, NULL, NULL),
(7, 'dean', 'Yoh', 'gilmaraparece@gmail.com', '$2y$10$4XzZfvvkszQ6IOy.S2XHzOYjpdkDw.5JZcpFD3NtiyZBul5k/vCKK', 'active', '2026-07-08 01:56:58', 'Ndbhdns', 'Bdbdbdh', NULL, NULL, NULL, NULL, NULL),
(8, 'teacher', 'feghdfw', 'sdf@gmail.com', '$2y$10$AZAVyN49QOvCOoA.Nhfr9uK4sp86ovoL69HafH5Wg7c1PUHmySBeG', 'active', '2026-07-09 00:50:17', '12356213-1231', 'EDUC', NULL, NULL, NULL, 'wefer', 'rwereer'),
(9, 'dean', 'Chona', 'aparecegilmar12@gmail.com', '$2y$10$NJ2HzrEZ1S.5H0PlEFQys.KWOIgImJIlLP0B.qremOEP0VESsNfLm', 'active', '2026-07-09 01:21:12', '1083836-83893', 'Educ', NULL, NULL, NULL, NULL, NULL),
(10, 'student', 'Hdjjduud', 'aparecegilmar10@gmail.com', '$2y$10$Hl287EGBPDYENimig5osv.1JCGraO6kNzofVyIAEsYalD5..wNQH.', 'active', '2026-07-10 02:28:25', '6373-33', 'BSIT', 2, NULL, NULL, NULL, NULL),
(11, 'dean', 'Jayner', 'jayner@gmail.com', '$2y$10$GfaJKAeDFnaipC72rOcTKe6LCYZSs0EgdABLmvBc4O82VDCLwPpcK', 'active', '2026-07-10 17:58:12', '202225', 'Dean', NULL, NULL, NULL, NULL, NULL),
(12, 'teacher', 'Gilmar', 'gilmar@gmail.com', '$2y$10$0MO7Pp6cRKDcNLitVBA63.SLHQ.fwRdYPAR71W4Jku05JUvowNhNu', 'active', '2026-07-10 18:00:45', '202225-679', 'BSIT', 3, NULL, 11, NULL, NULL),
(13, 'student', 'shyn', 'shyndy@gmail.com', '$2y$10$UH.2yZdP0qQ1jwa6pHY9zueSrjOqvZmDGJ809NWNEPhCZdZE/T4Ye', 'active', '2026-07-10 19:03:36', '202222-287', 'BSIT', 3, NULL, NULL, NULL, NULL);

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
-- Indexes for table `student_notes`
--
ALTER TABLE `student_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `schools`
--
ALTER TABLE `schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_dean` (`dean_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attempts`
--
ALTER TABLE `attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `attempt_answers`
--
ALTER TABLE `attempt_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `class_codes`
--
ALTER TABLE `class_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `exam_categories`
--
ALTER TABLE `exam_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `featured_students`
--
ALTER TABLE `featured_students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_notes`
--
ALTER TABLE `student_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `schools`
--
ALTER TABLE `schools`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
-- Indexes for table `user_notes`
--
ALTER TABLE `user_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for table `user_notes`
--
ALTER TABLE `user_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_school_fk` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
