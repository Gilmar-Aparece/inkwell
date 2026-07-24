-- Optional manual migration. includes/billing.php already self-heals these
-- columns on first request (same pattern as MIGRATION_ADD_yearly_billing.sql
-- and MIGRATION_ADD_payment_duplicate_detection.sql), so you only need to
-- run this if you'd rather apply it directly in phpMyAdmin. Safe to re-run.
--
-- Adds:
--   payment_submissions.sender_number / payment_date — the GCash sender's
--     mobile number and the date they say they paid, used as a second
--     duplicate-receipt check alongside reference_no/proof_hash.
--   users.last_payment_attempt_at / payment_fail_count / payment_fail_date —
--     rate limiting: a 7-second cooldown between submission attempts, and a
--     cap of 2 failed (duplicate-flagged) attempts per day before the user
--     has to wait until the next day.
-- Also fixes up the seeded GCash payment method's placeholder number/name
-- to the real ones, if it hasn't been edited since the original seed.

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_submissions' AND COLUMN_NAME = 'sender_number');
SET @sql := IF(@c = 0, 'ALTER TABLE `payment_submissions` ADD COLUMN `sender_number` VARCHAR(30) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_submissions' AND COLUMN_NAME = 'payment_date');
SET @sql := IF(@c = 0, 'ALTER TABLE `payment_submissions` ADD COLUMN `payment_date` DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_payment_attempt_at');
SET @sql := IF(@c = 0, 'ALTER TABLE `users` ADD COLUMN `last_payment_attempt_at` DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'payment_fail_count');
SET @sql := IF(@c = 0, 'ALTER TABLE `users` ADD COLUMN `payment_fail_count` INT NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'payment_fail_date');
SET @sql := IF(@c = 0, 'ALTER TABLE `users` ADD COLUMN `payment_fail_date` DATE DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Only touches a GCash method row still carrying the original seed
-- placeholder, so any real number an admin already entered is left alone.
UPDATE `payment_methods` SET `account_number` = '09463478938', `account_name` = 'Gilmar'
  WHERE `type` = 'gcash' AND `account_number` = '09XX-XXX-XXXX';
