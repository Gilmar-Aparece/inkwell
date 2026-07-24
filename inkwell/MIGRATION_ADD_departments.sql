-- MIGRATION_ADD_departments.sql
-- Run this once via phpMyAdmin ONLY IF the app tells you it couldn't add
-- these automatically (some shared hosts don't grant the app's DB user
-- CREATE/ALTER rights). If the app didn't complain, it already applied
-- this itself and you don't need to run it.

CREATE TABLE IF NOT EXISTS `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `departments` (`code`, `name`) VALUES
('BSEED', 'Bachelor of Secondary Education'),
('BSIT', 'Bachelor of Science in Information Technology'),
('BSHM', 'Bachelor of Science in Hospitality Management');

-- Only run the two ALTERs below if the columns don't already exist.
ALTER TABLE `users` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `school_id`;
ALTER TABLE `subjects` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `school_id`;
