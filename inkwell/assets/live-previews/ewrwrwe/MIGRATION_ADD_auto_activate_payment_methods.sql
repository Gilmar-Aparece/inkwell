-- Run this once in phpMyAdmin -> Import (or SQL tab) if you want to apply
-- it manually. It also runs automatically the next time any billing page
-- loads (see inkwell_ensure_billing_columns() in includes/billing.php),
-- so this file is optional — just here for the record.
--
-- Adds an `auto_activate` toggle to `payment_methods`. When on for a
-- method (e.g. a GCash number/QR the admin reconciles themselves), a
-- user's submission to that method activates their plan immediately
-- instead of sitting in the "pending admin review" queue.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_methods' AND COLUMN_NAME = 'auto_activate');
SET @sql := IF(@col = 0, 'ALTER TABLE `payment_methods` ADD COLUMN `auto_activate` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Optional: turn instant activation on for your existing GCash method
-- right away (edit the label to match yours, or just use the checkbox
-- in Admin -> Payment methods instead).
-- UPDATE `payment_methods` SET `auto_activate` = 1 WHERE `type` = 'gcash';
