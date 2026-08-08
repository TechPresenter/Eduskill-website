-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v3
--  Schemes, Scholarships, Partner applications, Certificate verification.
--  Run AFTER schema_v2.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v3.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- schemes & government initiatives -------------------------------------------
CREATE TABLE IF NOT EXISTS `schemes` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`           VARCHAR(191) NOT NULL,
    `slug`            VARCHAR(191) NOT NULL,
    `category`        VARCHAR(96)  NULL,
    `department`      VARCHAR(191) NULL,
    `short_description` VARCHAR(500) NULL,
    `description`     LONGTEXT     NULL,
    `eligibility`     TEXT         NULL,
    `benefits`        TEXT         NULL,
    `documents_required` TEXT      NULL,
    `apply_url`       VARCHAR(255) NULL,
    `image`           VARCHAR(255) NULL,
    `deadline`        DATE         NULL,
    `is_featured`     TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`      INT          NOT NULL DEFAULT 0,
    `status`          ENUM('active','closed') NOT NULL DEFAULT 'active',
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_schemes_slug` (`slug`),
    KEY `idx_schemes_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- scholarships ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `scholarships` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `description` LONGTEXT     NULL,
    `eligibility` TEXT         NULL,
    `amount`      VARCHAR(96)  NULL,
    `level`       VARCHAR(96)  NULL,
    `deadline`    DATE         NULL,
    `image`       VARCHAR(255) NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scholarships_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `scholarship_applications` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `scholarship_id` INT UNSIGNED NULL,
    `name`           VARCHAR(128) NOT NULL,
    `email`          VARCHAR(191) NOT NULL,
    `phone`          VARCHAR(32)  NULL,
    `institution`    VARCHAR(191) NULL,
    `course`         VARCHAR(191) NULL,
    `guardian_name`  VARCHAR(128) NULL,
    `annual_income`  VARCHAR(64)  NULL,
    `document`       VARCHAR(255) NULL,
    `message`        TEXT         NULL,
    `status`         ENUM('new','under_review','approved','rejected') NOT NULL DEFAULT 'new',
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schapp_sch` (`scholarship_id`),
    CONSTRAINT `fk_schapp_sch` FOREIGN KEY (`scholarship_id`)
        REFERENCES `scholarships` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- partner applications --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `partner_applications` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `organization` VARCHAR(191) NOT NULL,
    `contact_name` VARCHAR(128) NOT NULL,
    `email`        VARCHAR(191) NOT NULL,
    `phone`        VARCHAR(32)  NULL,
    `website`      VARCHAR(191) NULL,
    `tier`         VARCHAR(64)  NULL,
    `message`      TEXT         NULL,
    `status`       ENUM('new','contacted','partnered','declined') NOT NULL DEFAULT 'new',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_partnerapp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- issued certificates (for public verification) -------------------------------
CREATE TABLE IF NOT EXISTS `issued_certificates` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `certificate_number` VARCHAR(64)  NOT NULL,
    `holder_name`        VARCHAR(191) NOT NULL,
    `email`              VARCHAR(191) NULL,
    `type`               ENUM('volunteer','internship','participation','appreciation','training') NOT NULL DEFAULT 'participation',
    `program`            VARCHAR(191) NULL,
    `issue_date`         DATE         NULL,
    `expiry_date`        DATE         NULL,
    `file_path`          VARCHAR(255) NULL,
    `status`             ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_issuedcert_number` (`certificate_number`),
    KEY `idx_issuedcert_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
