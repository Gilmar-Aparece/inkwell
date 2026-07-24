-- Run this once in phpMyAdmin -> Import (or SQL tab) on your InfinityFree
-- database. Adds subscription plans, admin-managed payment methods
-- (GCash / PayPal / Card / Bank / other), and a submissions table where
-- users upload proof of payment for an admin to approve/reject.
-- Safe to re-run: uses IF NOT EXISTS / checks before ALTERs.

CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `audience` enum('student','school','both') NOT NULL DEFAULT 'both',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_period` varchar(20) NOT NULL DEFAULT 'month',
  `description` varchar(255) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `badge` varchar(40) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('gcash','paypal','card','bank','other') NOT NULL DEFAULT 'other',
  `label` varchar(100) NOT NULL,
  `account_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(150) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `qr_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(150) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `plan_id` (`plan_id`),
  KEY `payment_method_id` (`payment_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- users.plan_id / plan_status / plan_expires_at — wrapped so re-running
-- the file doesn't error out if they already exist.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'plan_id');
SET @sql := IF(@col = 0, 'ALTER TABLE `users` ADD COLUMN `plan_id` int(11) DEFAULT NULL, ADD COLUMN `plan_status` enum(\'none\',\'pending\',\'active\',\'expired\') NOT NULL DEFAULT \'none\', ADD COLUMN `plan_expires_at` datetime DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed a few starter plans + payment methods so the pages aren't empty.
-- Delete/edit these from Admin -> Pricing / Payment methods afterward.
-- Guarded (only inserts if the table is still empty) so re-running this file is always safe.
SET @plan_count := (SELECT COUNT(*) FROM `plans`);
SET @sql := IF(@plan_count = 0,
  "INSERT INTO `plans` (`name`, `audience`, `price`, `billing_period`, `description`, `features`, `badge`, `is_active`, `sort_order`) VALUES
  ('Free', 'both', 0.00, 'forever', 'Get started with the basics, no card required.', 'Intro lessons in every track\\nCommunity posts\\nBasic code playground', NULL, 1, 1),
  ('Pro Learner', 'student', 199.00, 'month', 'For students who want the full track and certification.', 'All lesson tracks\\nCertification exams\\nDownloadable certificates\\nPriority support', 'Most popular', 1, 2),
  ('School', 'school', 2999.00, 'month', 'For schools managing teachers, deans, and students.', 'Unlimited teacher & student accounts\\nSchool branding on certificates\\nAdmin dashboard & reporting\\nDedicated support', NULL, 1, 3)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @method_count := (SELECT COUNT(*) FROM `payment_methods`);
SET @sql := IF(@method_count = 0,
  "INSERT INTO `payment_methods` (`type`, `label`, `account_name`, `account_number`, `instructions`, `is_active`, `sort_order`) VALUES
  ('gcash', 'GCash', 'Inkwell / Gilmar Aparece', '09XX-XXX-XXXX', 'Send the exact plan amount via GCash, then upload a screenshot of the receipt and enter the reference number.', 1, 1),
  ('paypal', 'PayPal', 'your-paypal@example.com', NULL, 'Send as Friends & Family to avoid fees, then upload a screenshot of the confirmation email.', 1, 2),
  ('card', 'Credit / Debit Card', NULL, NULL, 'Card payments are processed manually for now — contact support after submitting and we will send a secure payment link.', 1, 3)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
