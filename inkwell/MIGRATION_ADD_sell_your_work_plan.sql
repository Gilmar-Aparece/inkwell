-- Run this once in phpMyAdmin -> Import (or SQL tab).
-- Adds a new pricing plan called "Sell Your Work" that unlocks marketplace
-- selling for ANY account (not just students/teachers) — it does NOT also
-- unlock certification exams or the full lesson library, since it's a
-- selling add-on, not a learning plan. Edit the price/description/features
-- afterward from Admin -> Pricing like any other plan.
-- Safe to re-run: only inserts if a plan with this exact name doesn't
-- already exist.

-- Defensive column check, in case this runs before includes/billing.php's
-- self-healing inkwell_ensure_billing_columns() has ever fired.
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'unlocks_marketplace_selling');
SET @sql := IF(@col = 0, 'ALTER TABLE `plans` ADD COLUMN `unlocks_marketplace_selling` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col2 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'unlocks_exams');
SET @sql2 := IF(@col2 = 0, 'ALTER TABLE `plans` ADD COLUMN `unlocks_exams` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col3 := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'unlocks_all_lessons');
SET @sql3 := IF(@col3 = 0, 'ALTER TABLE `plans` ADD COLUMN `unlocks_all_lessons` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM `plans` WHERE `name` = 'Sell Your Work');
SET @sql4 := IF(@exists = 0,
  "INSERT INTO `plans`
    (`name`, `audience`, `price`, `billing_period`, `description`, `features`, `badge`, `unlocks_exams`, `unlocks_all_lessons`, `unlocks_marketplace_selling`, `is_active`, `sort_order`)
  VALUES
    ('Sell Your Work', 'both', 149.00, 'month',
     'For anyone who wants to list and sell their own systems on the marketplace — no student or teacher account required.',
     'List unlimited systems\\nGenerate buyer unlock codes\\nGet paid directly to your own GCash\\nSeller earnings dashboard',
     NULL, 0, 0, 1, 1, 4)",
  'SELECT 1');
PREPARE stmt FROM @sql4; EXECUTE stmt; DEALLOCATE PREPARE stmt;
