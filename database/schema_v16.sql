-- =============================================================================
--  schema_v16.sql — Referral, Coupon & Marketing Tools
--  Run AFTER schema_v15.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v16.sql
--  Idempotent (CREATE TABLE IF NOT EXISTS).
-- =============================================================================
USE `pwf`;

-- ---- Referral codes (one per member / volunteer / user) --------------------
CREATE TABLE IF NOT EXISTS `referral_codes` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner_type`       ENUM('member','volunteer','user','other') NOT NULL DEFAULT 'member',
    `owner_id`         INT UNSIGNED NULL,
    `owner_name`       VARCHAR(128) NULL,
    `code`             VARCHAR(32)  NOT NULL,
    `clicks`           INT UNSIGNED NOT NULL DEFAULT 0,
    `signups`          INT UNSIGNED NOT NULL DEFAULT 0,
    `donations_count`  INT UNSIGNED NOT NULL DEFAULT 0,
    `donations_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reward_total`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `status`           TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ref_code` (`code`),
    KEY `idx_ref_owner` (`owner_type`, `owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Referral conversions (each attributed signup / donation) --------------
CREATE TABLE IF NOT EXISTS `referral_conversions` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('signup','donation','membership','enrollment') NOT NULL DEFAULT 'signup',
    `ref_name`   VARCHAR(128) NULL,
    `ref_email`  VARCHAR(191) NULL,
    `amount`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `reward`     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `note`       VARCHAR(191) NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rc_code` (`code_id`, `type`),
    CONSTRAINT `fk_rc_code` FOREIGN KEY (`code_id`) REFERENCES `referral_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Coupons (discount / waiver for courses / memberships / donations) -----
CREATE TABLE IF NOT EXISTS `coupons` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`          VARCHAR(48)  NOT NULL,
    `description`   VARCHAR(191) NULL,
    `discount_type` ENUM('percent','fixed','waiver') NOT NULL DEFAULT 'percent',
    `value`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `applies_to`    ENUM('all','courses','memberships','donations') NOT NULL DEFAULT 'all',
    `min_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `usage_limit`   INT UNSIGNED NULL,             -- NULL = unlimited
    `used_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `per_user_limit` INT UNSIGNED NOT NULL DEFAULT 1,
    `starts_at`     DATETIME     NULL,
    `expires_at`    DATETIME     NULL,
    `status`        TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupon_code` (`code`),
    KEY `idx_coupon_status` (`status`, `applies_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Coupon redemptions (analytics: savings, popularity) -------------------
CREATE TABLE IF NOT EXISTS `coupon_redemptions` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id`     INT UNSIGNED NOT NULL,
    `code`          VARCHAR(48)  NOT NULL,
    `context`       ENUM('course','membership','donation','other') NOT NULL DEFAULT 'other',
    `context_id`    INT UNSIGNED NULL,
    `user_email`    VARCHAR(191) NULL,
    `amount_before` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `amount_after`  DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_credeem_coupon` (`coupon_id`),
    KEY `idx_credeem_email` (`user_email`),
    CONSTRAINT `fk_credeem_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
