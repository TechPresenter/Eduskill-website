-- =============================================================================
--  schema_v13.sql — Blog & News System enhancements
--  blogs: per-post SEO meta + breaking/sticky flags.
--  blog_categories: parent_id for hierarchical categories.
--  Run AFTER schema_v12.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v13.sql
--  Idempotent (MariaDB — ADD COLUMN/KEY IF NOT EXISTS).
-- =============================================================================
USE `pwf`;

ALTER TABLE `blogs`
    ADD COLUMN IF NOT EXISTS `meta_title`       VARCHAR(191) NULL AFTER `excerpt`,
    ADD COLUMN IF NOT EXISTS `meta_description` VARCHAR(300) NULL AFTER `meta_title`,
    ADD COLUMN IF NOT EXISTS `og_image`         VARCHAR(255) NULL AFTER `meta_description`,
    ADD COLUMN IF NOT EXISTS `canonical_url`    VARCHAR(255) NULL AFTER `og_image`,
    ADD COLUMN IF NOT EXISTS `is_breaking`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_featured`,
    ADD COLUMN IF NOT EXISTS `is_sticky`        TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_breaking`;

ALTER TABLE `blog_categories`
    ADD COLUMN IF NOT EXISTS `parent_id` INT UNSIGNED NULL AFTER `id`,
    ADD KEY IF NOT EXISTS `idx_blogcat_parent` (`parent_id`);
