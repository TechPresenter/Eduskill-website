-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v2 (member accounts, careers, membership)
--  Run AFTER schema.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v2.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- members  (public member accounts — separate from admin `users`)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `members` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`              VARCHAR(128) NOT NULL,
    `email`             VARCHAR(191) NOT NULL,
    `password`          VARCHAR(255) NOT NULL,
    `phone`             VARCHAR(32)  NULL,
    `avatar`            VARCHAR(255) NULL,
    `email_verified_at` TIMESTAMP    NULL,
    `status`            ENUM('active','pending','suspended') NOT NULL DEFAULT 'pending',
    `remember_token`    VARCHAR(100) NULL,
    `last_login_at`     TIMESTAMP    NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_members_email` (`email`),
    KEY `idx_members_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- member_tokens  (email verification, password reset, OTP)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `member_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`  INT UNSIGNED NULL,
    `email`      VARCHAR(191) NOT NULL,
    `type`       ENUM('verify_email','reset_password','otp') NOT NULL,
    `token`      VARCHAR(191) NOT NULL,   -- sha256 of the raw token/code
    `expires_at` TIMESTAMP    NOT NULL,
    `used_at`    TIMESTAMP    NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_mt_lookup` (`email`, `type`),
    KEY `idx_mt_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- careers  (job / volunteer openings)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `careers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(191) NOT NULL,
    `slug`         VARCHAR(191) NOT NULL,
    `department`   VARCHAR(128) NULL,
    `location`     VARCHAR(191) NULL,
    `type`         ENUM('full-time','part-time','contract','internship','volunteer') NOT NULL DEFAULT 'full-time',
    `description`  LONGTEXT     NULL,
    `requirements` LONGTEXT     NULL,
    `salary_range` VARCHAR(96)  NULL,
    `openings`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `deadline`     DATE         NULL,
    `status`       ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_careers_slug` (`slug`),
    KEY `idx_careers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- job_applications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `job_applications` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `career_id`    INT UNSIGNED NULL,
    `name`         VARCHAR(128) NOT NULL,
    `email`        VARCHAR(191) NOT NULL,
    `phone`        VARCHAR(32)  NULL,
    `resume`       VARCHAR(255) NULL,
    `cover_letter` TEXT         NULL,
    `status`       ENUM('new','shortlisted','rejected','hired') NOT NULL DEFAULT 'new',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jobapp_career` (`career_id`),
    CONSTRAINT `fk_jobapp_career` FOREIGN KEY (`career_id`)
        REFERENCES `careers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- membership_plans
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_plans` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(96)  NOT NULL,
    `slug`       VARCHAR(96)  NOT NULL,
    `price`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `duration`   VARCHAR(48)  NOT NULL DEFAULT 'Annual',
    `benefits`   TEXT         NULL,   -- one benefit per line
    `is_featured` TINYINT(1)  NOT NULL DEFAULT 0,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_planslug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- membership_applications
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_applications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `plan_id`    INT UNSIGNED NULL,
    `name`       VARCHAR(128) NOT NULL,
    `email`      VARCHAR(191) NOT NULL,
    `phone`      VARCHAR(32)  NULL,
    `address`    VARCHAR(255) NULL,
    `occupation` VARCHAR(128) NULL,
    `message`    TEXT         NULL,
    `status`     ENUM('new','approved','rejected') NOT NULL DEFAULT 'new',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_memapp_plan` (`plan_id`),
    CONSTRAINT `fk_memapp_plan` FOREIGN KEY (`plan_id`)
        REFERENCES `membership_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
