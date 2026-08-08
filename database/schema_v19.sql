-- =============================================================================
--  schema_v19.sql — Security Center
--  Adds the columns & tables the Security Center needs on top of the existing
--  security stack (login_attempts, activity_logs, settings, users.totp_*).
--  Reuses existing tables wherever possible — these are the only true additions.
--  Idempotent (MariaDB 10.4 IF NOT EXISTS). Run AFTER schema_v18.sql.
-- =============================================================================
USE `pwf`;

-- Password lifecycle + 2FA enrollment tracking on the existing admin users table.
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `password_changed_at`  DATETIME   NULL           AFTER `password`,
    ADD COLUMN IF NOT EXISTS `must_change_password`  TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_changed_at`,
    ADD COLUMN IF NOT EXISTS `twofa_enrolled_at`     DATETIME   NULL           AFTER `totp_enabled`;

-- One-time 2FA recovery / backup codes (required before force-2FA can be safe).
CREATE TABLE IF NOT EXISTS `user_recovery_codes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `code_hash`  VARCHAR(255) NOT NULL,     -- password_hash() of a one-time code
    `used_at`    DATETIME     NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_recovery_user` (`user_id`),
    CONSTRAINT `fk_recovery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password history (reuse prevention). Stores bcrypt hashes of prior passwords.
CREATE TABLE IF NOT EXISTS `password_history` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `password`   VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pwhist_user` (`user_id`, `created_at`),
    CONSTRAINT `fk_pwhist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Database-backup registry (files stored outside the webroot; see BACKUP_DIR).
CREATE TABLE IF NOT EXISTS `backups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`    VARCHAR(191) NOT NULL,
    `size_bytes`  BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `destination` ENUM('local','ftp','s3') NOT NULL DEFAULT 'local',
    `status`      ENUM('running','success','failed') NOT NULL DEFAULT 'running',
    `checksum`    VARCHAR(64)  NULL,        -- sha256 of the archive
    `message`     VARCHAR(500) NULL,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_backups_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Severity on the existing activity_logs so the audit feed can filter security events.
ALTER TABLE `activity_logs`
    ADD COLUMN IF NOT EXISTS `severity` ENUM('info','notice','warning','critical') NOT NULL DEFAULT 'info' AFTER `description`;
