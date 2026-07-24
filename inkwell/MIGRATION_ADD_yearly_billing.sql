-- Optional manual migration. includes/billing.php already self-heals these
-- columns on first request (same pattern as the certificate columns in
-- exams_db.php), so you only need to run this if you'd rather apply it
-- directly in phpMyAdmin. Safe to re-run.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'price_yearly');
SET @sql := IF(@col = 0, 'ALTER TABLE `plans` ADD COLUMN `price_yearly` DECIMAL(10,2) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'unlocks_exams');
SET @sql := IF(@col = 0, 'ALTER TABLE `plans` ADD COLUMN `unlocks_exams` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_submissions' AND COLUMN_NAME = 'billing_cycle');
SET @sql := IF(@col = 0, "ALTER TABLE `payment_submissions` ADD COLUMN `billing_cycle` ENUM('month','year') NOT NULL DEFAULT 'month'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Free plans (₱0/mo and no yearly price) never unlock exams by default.
UPDATE `plans` SET `unlocks_exams` = 0 WHERE `price` <= 0 AND (`price_yearly` IS NULL OR `price_yearly` <= 0);
