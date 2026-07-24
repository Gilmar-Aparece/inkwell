-- MIGRATION_ADD_class_record.sql
-- Run this once in phpMyAdmin (Import tab) ONLY if the E-Class Record page
-- shows "Class Record tables are unavailable on this host" — that means
-- InfinityFree blocked the app's own CREATE TABLE IF NOT EXISTS calls at
-- runtime (same self-healing-with-fallback pattern used by includes/notes.php
-- and includes/sections.php elsewhere in Inkwell). If the page works
-- without any error, you do NOT need to run this file.

CREATE TABLE IF NOT EXISTS `erecord_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_id` int(11) NOT NULL,
  `term` varchar(20) NOT NULL DEFAULT 'prelim',
  `instructor_name` varchar(150) DEFAULT NULL,
  `time_schedule` varchar(150) DEFAULT NULL,
  `school_attended` varchar(150) DEFAULT NULL,
  `quiz_points` decimal(6,2) NOT NULL DEFAULT 10.00,
  `pt_points` decimal(6,2) NOT NULL DEFAULT 10.00,
  `attendance_points` decimal(6,2) NOT NULL DEFAULT 5.00,
  `major_exam_points` decimal(6,2) NOT NULL DEFAULT 15.00,
  `essay_points` decimal(6,2) NOT NULL DEFAULT 10.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_term` (`subject_id`, `term`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- If `erecord_config` already existed on your host from before the
-- "School Attended" field was added, CREATE TABLE IF NOT EXISTS above won't
-- add the new column. The app tries to add it automatically at runtime; run
-- this manually only if you still see a "School Attended" save error.
ALTER TABLE `erecord_config` ADD COLUMN IF NOT EXISTS `school_attended` varchar(150) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `erecord_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `section` enum('quiz','pt','attendance','major_exam','essay') NOT NULL,
  `label` varchar(100) NOT NULL,
  `max_score` decimal(8,2) NOT NULL DEFAULT 100.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `config_id` (`config_id`),
  KEY `section` (`section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `erecord_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `score` decimal(8,2) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_student` (`item_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `erecord_overrides` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `fr` decimal(6,2) DEFAULT NULL,
  `final_grade` decimal(6,2) DEFAULT NULL,
  `remarks` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_student` (`config_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
