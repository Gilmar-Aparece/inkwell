-- Optional manual migration. includes/marketplace.php already self-heals
-- this table on first request (same pattern as MIGRATION_ADD_notifications.sql
-- and the other self-healing tables in inkwell_ensure_marketplace_tables()),
-- so you only need to run this if your host blocks CREATE TABLE over the
-- app's normal DB connection (e.g. some InfinityFree accounts). Safe to
-- re-run.
--
-- Adds the `marketplace_purchase_requests` table that powers "Request
-- unlock code" on a listing page: a buyer submits a request (with an
-- optional message, e.g. their GCash reference number) which notifies the
-- seller in-app. The seller then taps "Generate code & send" on their
-- dashboard (sell.php), which stamps the generated code onto the request
-- and notifies the buyer with it directly — no separate off-platform
-- message needed to hand the code over. See the "Purchase requests"
-- section of includes/marketplace.php for the functions that read/write
-- this table.

CREATE TABLE IF NOT EXISTS `marketplace_purchase_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `listing_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `message` varchar(500) DEFAULT NULL,
  `status` enum('pending','fulfilled','declined') NOT NULL DEFAULT 'pending',
  `code` varchar(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `listing_idx` (`listing_id`),
  KEY `buyer_idx` (`buyer_id`),
  KEY `status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
