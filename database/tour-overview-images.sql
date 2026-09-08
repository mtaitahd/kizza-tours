-- Incremental migration: add per-tour Overview collage images.
-- Run this on an EXISTING live database so admins can set the exact 3 images
-- used by the Tour Overview collage on the public tour page (Admin > Tours >
-- that tour > Tour Overview Images). Safe to run multiple times; it just
-- fails harmlessly if a column already exists.
--
-- The columns are OPTIONAL:
--   * overview_image_1  -> large "main" collage image
--   * overview_image_2  -> medium overlapping image
--   * overview_image_3  -> small overlapping image
--   Leave all three empty to keep the old behaviour (featured image first,
--   then the first two gallery images) on the public page.
--
-- NOTE: database/schema.sql already includes these columns for fresh
-- installs, and admin/tours.php auto-adds them via ensureToursTable() on
-- every admin page load. Running this file manually on the live server is
-- the explicit, version-controlled equivalent.

ALTER TABLE `tour_packages`
    ADD COLUMN `overview_image_1` VARCHAR(255) DEFAULT NULL AFTER `hero_image`,
    ADD COLUMN `overview_image_2` VARCHAR(255) DEFAULT NULL AFTER `overview_image_1`,
    ADD COLUMN `overview_image_3` VARCHAR(255) DEFAULT NULL AFTER `overview_image_2`;

-- Rollback:
-- ALTER TABLE `tour_packages`
--     DROP COLUMN `overview_image_3`,
--     DROP COLUMN `overview_image_2`,
--     DROP COLUMN `overview_image_1`;