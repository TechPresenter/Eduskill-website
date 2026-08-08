-- =============================================================================
--  schema_v23.sql — Global Theme Settings
--  A centralised, versioned theme system that drives CSS custom properties for
--  the public site, admin panel, auth pages, email HTML and print/PDF output.
--
--    theme_settings  key/value token store (the LIVE + DRAFT values)
--    theme_versions  immutable JSON snapshots for history / rollback / backup
--    theme_presets   named themes (built-in + unlimited custom) as JSON
--
--  Run AFTER schema_v22.sql.
-- =============================================================================
USE `pwf`;

-- ---------------------------------------------------------------- live tokens
CREATE TABLE IF NOT EXISTS `theme_settings` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token`      VARCHAR(64)  NOT NULL,                 -- e.g. color.primary, font.body
    `value`      TEXT         NULL,                     -- the published value
    `draft`      TEXT         NULL,                     -- unpublished edit (NULL = no draft)
    `group_name` VARCHAR(32)  NOT NULL DEFAULT 'colors',-- brand|colors|typography|layout|components|effects|auth|email|advanced
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_theme_token` (`token`),
    KEY `idx_theme_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------- history / rollback
CREATE TABLE IF NOT EXISTS `theme_versions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `label`      VARCHAR(120) NULL,                     -- "Before brand refresh"
    `payload`    LONGTEXT     NOT NULL,                 -- JSON snapshot of every token
    `note`       VARCHAR(255) NULL,
    `is_auto`    TINYINT(1)   NOT NULL DEFAULT 1,       -- auto-snapshot on publish vs manual backup
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_theme_ver_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------- named themes
CREATE TABLE IF NOT EXISTS `theme_presets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(120) NOT NULL,
    `slug`       VARCHAR(120) NOT NULL,
    `payload`    LONGTEXT     NOT NULL,                 -- JSON token map
    `is_builtin` TINYINT(1)   NOT NULL DEFAULT 0,       -- shipped presets can't be deleted
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 0,       -- currently applied preset
    `created_by` INT UNSIGNED NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_theme_preset_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
