-- Optional manual migration. includes/billing.php already self-heals this
-- column on first request (same pattern as MIGRATION_ADD_yearly_billing.sql),
-- so you only need to run this if you'd rather apply it directly in
-- phpMyAdmin. Safe to re-run.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payment_submissions' AND COLUMN_NAME = 'proof_hash');
SET @sql := IF(@col = 0, 'ALTER TABLE `payment_submissions` ADD COLUMN `proof_hash` CHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
