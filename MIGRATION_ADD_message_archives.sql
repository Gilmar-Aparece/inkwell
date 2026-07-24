-- Optional manual migration. includes/messages.php already self-heals this
-- table on first request (same pattern as MIGRATION_ADD_messages.sql and
-- MIGRATION_ADD_notifications.sql), so you only need to run this if your
-- host blocks CREATE TABLE over the app's normal DB connection (e.g. some
-- InfinityFree accounts). Safe to re-run.
--
-- Adds the `message_archives` table that powers the Archive tab on
-- /messages.php. Archiving is per-user: a row here means "user_id has
-- archived their conversation with other_user_id" — it only affects what
-- that one person sees and has no effect on the other side of the
-- conversation. The thread itself still lives in `messages`; this table
-- is just a personal "hide from General" flag layered on top.

CREATE TABLE IF NOT EXISTS `message_archives` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `other_user_id` int(11) NOT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_other` (`user_id`, `other_user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
