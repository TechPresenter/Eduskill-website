-- =============================================================================
--  schema_v18.sql — Document Hub (dynamic template-driven documents & certificates)
--  A centralised, template-driven system: admins design templates with {{placeholders}}
--  in CKEditor, then issue documents (QR-verifiable, auto-numbered, print-to-PDF).
--  Run AFTER schema_v17.sql.
-- =============================================================================
USE `pwf`;

CREATE TABLE IF NOT EXISTS `document_templates` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(191) NOT NULL,
    `slug`           VARCHAR(191) NOT NULL,
    `category`       VARCHAR(32)  NOT NULL DEFAULT 'certificate', -- certificate|id_card|receipt|letter|report|pass
    `doc_type`       VARCHAR(64)  NULL,     -- e.g. "Membership Certificate"
    `layout`         ENUM('landscape','portrait','id_horizontal','id_vertical') NOT NULL DEFAULT 'landscape',
    `theme`          VARCHAR(32)  NOT NULL DEFAULT 'classic',
    `body`           LONGTEXT     NULL,     -- HTML with {{placeholders}}
    `terms_enabled`  TINYINT(1)   NOT NULL DEFAULT 0,
    `terms`          LONGTEXT     NULL,
    `show_qr`        TINYINT(1)   NOT NULL DEFAULT 1,
    `show_seal`      TINYINT(1)   NOT NULL DEFAULT 1,
    `show_signature` TINYINT(1)   NOT NULL DEFAULT 1,
    `show_logo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `show_watermark` TINYINT(1)   NOT NULL DEFAULT 0,
    `watermark_text` VARCHAR(64)  NULL,
    `number_prefix`  VARCHAR(24)  NOT NULL DEFAULT 'DOC',
    `number_next`    INT UNSIGNED NOT NULL DEFAULT 1,
    `status`         ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_doctpl_slug` (`slug`),
    KEY `idx_doctpl_cat` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_issued` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id`     INT UNSIGNED NOT NULL,
    `doc_no`          VARCHAR(64)  NOT NULL,
    `category`        VARCHAR(32)  NULL,
    `doc_type`        VARCHAR(64)  NULL,
    `recipient_name`  VARCHAR(128) NULL,
    `recipient_email` VARCHAR(191) NULL,
    `data`            LONGTEXT     NULL,    -- JSON of resolved placeholder values
    `qr_token`        VARCHAR(48)  NOT NULL,
    `status`          ENUM('issued','revoked') NOT NULL DEFAULT 'issued',
    `issued_by`       INT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_docissued_no` (`doc_no`),
    UNIQUE KEY `uq_docissued_token` (`qr_token`),
    KEY `idx_docissued_tpl` (`template_id`),
    KEY `idx_docissued_email` (`recipient_email`),
    CONSTRAINT `fk_docissued_tpl` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Version history (snapshot on each template save).
CREATE TABLE IF NOT EXISTS `document_template_versions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `template_id` INT UNSIGNED NOT NULL,
    `body`        LONGTEXT     NULL,
    `terms`       LONGTEXT     NULL,
    `saved_by`    INT UNSIGNED NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_docver_tpl` (`template_id`, `created_at`),
    CONSTRAINT `fk_docver_tpl` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
