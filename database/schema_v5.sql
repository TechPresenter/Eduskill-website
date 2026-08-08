-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v5
--  Adds: visitor analytics (page_views), widget manager (widgets),
--  and block-based page builder (pages.blocks JSON).
--  Run AFTER schema_v4.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v5.sql
-- =============================================================================
USE `pwf`;

-- ---- Visitor analytics ------------------------------------------------------
CREATE TABLE IF NOT EXISTS `page_views` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `url`        VARCHAR(255) NOT NULL,
    `page_title` VARCHAR(191) NULL,
    `referrer`   VARCHAR(255) NULL,
    `ip_address` VARCHAR(45)  NULL,
    `user_agent` VARCHAR(255) NULL,
    `session_id` VARCHAR(64)  NULL,
    `device`     ENUM('desktop','mobile','tablet','bot') NOT NULL DEFAULT 'desktop',
    `is_unique`  TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pv_created` (`created_at`),
    KEY `idx_pv_session` (`session_id`),
    KEY `idx_pv_url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Widget manager ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `widgets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(191) NOT NULL,
    `position`   VARCHAR(48)  NOT NULL DEFAULT 'footer',
    `type`       ENUM('html','text','links','contact','newsletter') NOT NULL DEFAULT 'html',
    `content`    LONGTEXT     NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_widgets_pos` (`position`, `status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Page builder: ordered content blocks (JSON) ----------------------------
ALTER TABLE `pages`
    ADD COLUMN IF NOT EXISTS `blocks` LONGTEXT NULL AFTER `content`;
