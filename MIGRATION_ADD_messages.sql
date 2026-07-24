-- Optional manual migration. includes/messages.php already self-heals this
-- table on first request (same pattern as MIGRATION_ADD_notifications.sql
-- and MIGRATION_ADD_user_notes.sql), so you only need to run this if your
-- host blocks CREATE TABLE over the app's normal DB connection (e.g. some
-- InfinityFree accounts). Safe to re-run.
--
-- Adds the `messages` table that powers /messages.php — private
-- one-to-one direct messages between any two users on the platform
-- (student, teacher, dean, registrar, admin, any combination). A
-- "conversation" is derived from these rows (every message between two
-- user ids), so there's no separate conversations/threads table.
-- Sending a message also drops a row into `notifications` (see
-- includes/notifications.php), so it shows up in the bell dropdown too.

CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `recipient_id` (`recipient_id`),
  KEY `recipient_read` (`recipient_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
