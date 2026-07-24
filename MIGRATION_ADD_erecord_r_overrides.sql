-- MIGRATION_ADD_erecord_r_overrides.sql
-- Run this once in phpMyAdmin (Import tab) ONLY if the E-Class Record page's
-- new editable "R" cells fail to save (error mentioning erecord_overrides
-- columns) — that means InfinityFree blocked the app's own self-healing
-- ALTER TABLE call at runtime (same pattern used elsewhere in Inkwell, see
-- includes/sections.php). If R overrides save fine without any error, you
-- do NOT need to run this file.
--
-- Adds five nullable columns to erecord_overrides so a teacher can type a
-- manual R (points earned) value per section, per student — separate from
-- the existing FR / Final Grade / Remarks overrides. NULL = keep using the
-- auto-computed value.

ALTER TABLE `erecord_overrides`
  ADD COLUMN `quiz_r` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed' AFTER `remarks`,
  ADD COLUMN `pt_r` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed' AFTER `quiz_r`,
  ADD COLUMN `attendance_r` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed' AFTER `pt_r`,
  ADD COLUMN `major_exam_r` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed' AFTER `attendance_r`,
  ADD COLUMN `essay_r` decimal(6,2) DEFAULT NULL COMMENT 'manual R override for this section, blank = use computed' AFTER `major_exam_r`;
