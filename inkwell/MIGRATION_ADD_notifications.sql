-- Optional manual migration. includes/notifications.php already self-heals
-- this table on first request (same pattern as MIGRATION_ADD_user_notes.sql
-- and the column self-healing in includes/billing.php), so you only need to
-- run this if your host blocks CREATE TABLE over the app's normal DB
-- connection (e.g. some InfinityFree accounts). Safe to re-run.
--
-- Adds the `notifications` table used by the bell icon in the drive shell
-- topbar and the admin/dean/registrar/teacher dashboard sidebar: one row
-- per notification, fanned out per-user (see inkwell_create_notification()
-- and inkwell_notify_admins() in includes/notifications.php). Fired
-- automatically whenever a payment is submitted, instantly activated,
-- approved, or rejected (see includes/billing.php).

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(40) NOT NULL DEFAULT 'general',
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
