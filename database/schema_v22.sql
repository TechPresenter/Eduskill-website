-- =============================================================================
--  schema_v22.sql — Premium Hero Slider
--  Extends hero_slides with the creative controls for a world-class, fully
--  admin-customisable hero: badge, highlighted/typing title, background styles
--  (gradient/mesh/image/solid/video), overlay, accent, split layout + hero
--  image, section height, styled CTAs with icons, trust signals + rating,
--  shape dividers and animation toggle. All additive + backward compatible.
--  Run AFTER schema_v21.sql.
-- =============================================================================
USE `pwf`;

-- The background image is no longer required (gradient/mesh/solid slides need none).
ALTER TABLE `hero_slides` MODIFY `image` VARCHAR(255) NULL DEFAULT NULL;

ALTER TABLE `hero_slides`
    ADD COLUMN IF NOT EXISTS `badge_text`   VARCHAR(120) NULL           AFTER `title`,
    ADD COLUMN IF NOT EXISTS `badge_icon`   VARCHAR(48)  NULL           AFTER `badge_text`,
    ADD COLUMN IF NOT EXISTS `highlight`    VARCHAR(191) NULL           AFTER `subtitle`,   -- words in the title to gradient-highlight
    ADD COLUMN IF NOT EXISTS `typing_words` VARCHAR(255) NULL           AFTER `highlight`,  -- rotating typed words (comma-separated)
    ADD COLUMN IF NOT EXISTS `accent`       VARCHAR(24)  NULL           AFTER `description`, -- highlight + primary CTA accent
    ADD COLUMN IF NOT EXISTS `bg_type`      VARCHAR(16)  NOT NULL DEFAULT 'gradient' AFTER `accent`,
    ADD COLUMN IF NOT EXISTS `bg_from`      VARCHAR(24)  NULL           AFTER `bg_type`,
    ADD COLUMN IF NOT EXISTS `bg_to`        VARCHAR(24)  NULL           AFTER `bg_from`,
    ADD COLUMN IF NOT EXISTS `bg_angle`     INT          NOT NULL DEFAULT 135 AFTER `bg_to`,
    ADD COLUMN IF NOT EXISTS `bg_video`     VARCHAR(255) NULL           AFTER `bg_angle`,
    ADD COLUMN IF NOT EXISTS `overlay`      TINYINT      NOT NULL DEFAULT 45  AFTER `bg_video`, -- % dark overlay over media
    ADD COLUMN IF NOT EXISTS `layout`       VARCHAR(16)  NOT NULL DEFAULT 'center' AFTER `text_align`, -- center | split
    ADD COLUMN IF NOT EXISTS `hero_image`   VARCHAR(255) NULL           AFTER `layout`,     -- foreground/side image (split layout)
    ADD COLUMN IF NOT EXISTS `height`       VARCHAR(12)  NOT NULL DEFAULT 'tall' AFTER `hero_image`, -- auto | tall | full
    ADD COLUMN IF NOT EXISTS `btn_style`    VARCHAR(16)  NOT NULL DEFAULT 'gradient' AFTER `button_url`,
    ADD COLUMN IF NOT EXISTS `btn_icon`     VARCHAR(48)  NULL           AFTER `btn_style`,
    ADD COLUMN IF NOT EXISTS `btn2_style`   VARCHAR(16)  NOT NULL DEFAULT 'glass' AFTER `button2_url`,
    ADD COLUMN IF NOT EXISTS `btn2_icon`    VARCHAR(48)  NULL           AFTER `btn2_style`,
    ADD COLUMN IF NOT EXISTS `trust_text`   VARCHAR(191) NULL           AFTER `btn2_icon`,  -- e.g. "Trusted by 12,000+ members"
    ADD COLUMN IF NOT EXISTS `rating`       DECIMAL(2,1) NULL           AFTER `trust_text`,
    ADD COLUMN IF NOT EXISTS `rating_count` INT          NULL           AFTER `rating`,
    ADD COLUMN IF NOT EXISTS `divider`      VARCHAR(16)  NOT NULL DEFAULT 'none' AFTER `rating_count`, -- shape divider
    ADD COLUMN IF NOT EXISTS `animate`      TINYINT      NOT NULL DEFAULT 1   AFTER `divider`;
