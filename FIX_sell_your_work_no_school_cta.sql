-- Run this once in phpMyAdmin -> SQL tab.
--
-- The "Sell Your Work" plan was created with audience = 'both', which is
-- what makes the landing page show the extra "...or create a school on
-- this plan" link (includes/landing.php shows that link on any non-school
-- plan whose audience is 'both'). Sell Your Work isn't meant to double as
-- a school-creation plan, so this switches it to audience = 'student'
-- instead -- that value just means "not a school plan" for CTA purposes;
-- it still keeps its own price/features/marketplace-selling unlock
-- untouched. Only the School plan (audience = 'school') will show
-- "Create your school" after this.
--
-- Safe to re-run.

UPDATE `plans`
SET `audience` = 'student'
WHERE `name` = 'Sell Your Work' AND `audience` = 'both';
