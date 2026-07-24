-- MIGRATION_ADD_post_image_engagement.sql
--
-- Adds Facebook-style per-photo likes and comments: when a Community
-- post has more than one picture, each individual picture gets its own
-- like count and comment thread, separate from the post's overall
-- like/comment counts.
--
-- Inkwell's app code is self-healing and will try to create these
-- tables automatically on first use. Only run this manually if your
-- host (e.g. InfinityFree) blocks CREATE TABLE from the app's normal DB
-- connection: open phpMyAdmin -> your database -> SQL tab -> paste this
-- -> Go. Safe to run once; running it again on an already-migrated
-- database will just error harmlessly on "table already exists" (you
-- can ignore that).

CREATE TABLE `post_image_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `image_user` (`image_id`, `user_id`),
  KEY `image_id` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `post_image_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `image_id` (`image_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
