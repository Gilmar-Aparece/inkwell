-- Optional manual migration. includes/settings.php already self-heals this
-- column on first request (same pattern as MIGRATION_ADD_marketplace_selling_plan_flag.sql
-- and the other MIGRATION_ADD_*.sql files), so you only need to run this if
-- your host blocks ALTER TABLE over the app's normal DB connection (e.g.
-- some InfinityFree accounts). Safe to re-run.
--
-- Adds `show_email_public` to `users` — powers the Privacy tab on
-- /settings.php. Off (0) by default: like Facebook's contact-info privacy,
-- your email only shows to you until you opt in to showing it on your
-- public teacher/dean profile popup (teacher-profile.php).

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `show_email_public` TINYINT(1) NOT NULL DEFAULT 0;
