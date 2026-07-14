-- Add SEO fields to tour_packages table
-- Run this migration to enable per-tour SEO customization

ALTER TABLE `tour_packages`
    ADD COLUMN `meta_title` VARCHAR(255) DEFAULT NULL AFTER `status`,
    ADD COLUMN `meta_description` TEXT DEFAULT NULL AFTER `meta_title`,
    ADD COLUMN `meta_keywords` VARCHAR(255) DEFAULT NULL AFTER `meta_description`,
    ADD COLUMN `no_robots` TINYINT(1) DEFAULT 0 AFTER `meta_keywords`;
