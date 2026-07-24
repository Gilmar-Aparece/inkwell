-- Adds file/image attachment support to Direct Messages.
-- Apply manually only if includes/messages.php reports it can't create
-- this table itself (same InfinityFree DDL-blocking situation as the
-- base `messages` table — see MIGRATION_ADD_messages.sql).
--
-- One row per uploaded file, linked to the message it was sent with.
-- A message can have zero, one, or several attachments (e.g. a photo
-- dragged in alongside a caption, or three files sent with no text).

CREATE TABLE IF NOT EXISTS `message_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `kind` enum('image','file') NOT NULL DEFAULT 'file',
  `filename` varchar(190) NOT NULL,
  `original_name` varchar(190) NOT NULL,
  `mime` varchar(120) NOT NULL DEFAULT '',
  `size` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
