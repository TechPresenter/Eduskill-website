-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v6 (Member Management)
--  Adds: membership fields on members, tier config on membership_plans, and
--  the membership_renewals / membership_reminders / member_audit tables.
--  Also seeds membership / payment (Cashfree) / SMS (MSG91) settings.
--
--  Run AFTER schema_v5.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v6.sql
--  Then run the PHP seeder (email templates + code/qr backfill):
--    C:\xampp\php\php.exe database\seed_v6.php
--
--  Idempotent — safe to run more than once (MariaDB IF NOT EXISTS).
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- members — membership profile + card + renewal fields
-- -----------------------------------------------------------------------------
ALTER TABLE `members`
    ADD COLUMN IF NOT EXISTS `member_code`       VARCHAR(32)  NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `plan_id`           INT UNSIGNED NULL AFTER `type`,
    ADD COLUMN IF NOT EXISTS `dob`               DATE         NULL AFTER `plan_id`,
    ADD COLUMN IF NOT EXISTS `gender`            ENUM('male','female','other') NULL AFTER `dob`,
    ADD COLUMN IF NOT EXISTS `address`           VARCHAR(255) NULL AFTER `gender`,
    ADD COLUMN IF NOT EXISTS `city`              VARCHAR(96)  NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `state`             VARCHAR(96)  NULL AFTER `city`,
    ADD COLUMN IF NOT EXISTS `pincode`           VARCHAR(12)  NULL AFTER `state`,
    ADD COLUMN IF NOT EXISTS `occupation`        VARCHAR(128) NULL AFTER `pincode`,
    ADD COLUMN IF NOT EXISTS `join_date`         DATE         NULL AFTER `occupation`,
    ADD COLUMN IF NOT EXISTS `expiry_date`       DATE         NULL AFTER `join_date`,
    ADD COLUMN IF NOT EXISTS `qr_token`          VARCHAR(64)  NULL AFTER `expiry_date`,
    ADD COLUMN IF NOT EXISTS `membership_status` ENUM('none','active','expired','cancelled') NOT NULL DEFAULT 'none' AFTER `qr_token`,
    ADD COLUMN IF NOT EXISTS `auto_renew`        TINYINT(1)   NOT NULL DEFAULT 0 AFTER `membership_status`;

ALTER TABLE `members`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_members_code` (`member_code`),
    ADD UNIQUE INDEX IF NOT EXISTS `uq_members_qr`   (`qr_token`),
    ADD INDEX        IF NOT EXISTS `idx_members_plan`   (`plan_id`),
    ADD INDEX        IF NOT EXISTS `idx_members_expiry` (`expiry_date`),
    ADD INDEX        IF NOT EXISTS `idx_members_mstatus`(`membership_status`);

-- -----------------------------------------------------------------------------
-- membership_plans — tier presentation + numeric duration for expiry maths
-- -----------------------------------------------------------------------------
ALTER TABLE `membership_plans`
    ADD COLUMN IF NOT EXISTS `color`         VARCHAR(16) NULL          AFTER `benefits`,
    ADD COLUMN IF NOT EXISTS `duration_days` INT UNSIGNED NOT NULL DEFAULT 365 AFTER `duration`,
    ADD COLUMN IF NOT EXISTS `tier_level`    TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `duration_days`;

-- -----------------------------------------------------------------------------
-- membership_renewals — one row per renewal / payment attempt
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_renewals` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`    INT UNSIGNED NOT NULL,
    `plan_id`      INT UNSIGNED NULL,
    `amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency`     VARCHAR(8)   NOT NULL DEFAULT 'INR',
    `period_start` DATE         NULL,
    `period_end`   DATE         NULL,
    `method`       ENUM('cashfree','offline','manual','other') NOT NULL DEFAULT 'manual',
    `gateway`      VARCHAR(32)  NULL,
    `order_id`     VARCHAR(96)  NULL,
    `payment_id`   VARCHAR(96)  NULL,
    `status`       ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
    `applied_at`   TIMESTAMP    NULL DEFAULT NULL,   -- set once the term is applied (idempotency guard)
    `note`         VARCHAR(500) NULL,
    `created_by`   INT UNSIGNED NULL,   -- admin user id when recorded in the panel
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_renewal_member` (`member_id`),
    KEY `idx_renewal_status` (`status`),
    KEY `idx_renewal_order`  (`order_id`),
    CONSTRAINT `fk_renewal_member` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotency marker for existing installs (no-op if already present).
ALTER TABLE `membership_renewals`
    ADD COLUMN IF NOT EXISTS `applied_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`;

-- -----------------------------------------------------------------------------
-- membership_reminders — idempotent log of expiry reminders sent
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `membership_reminders` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`   INT UNSIGNED NOT NULL,
    `expiry_date` DATE         NOT NULL,
    `stage`       SMALLINT     NOT NULL,   -- days before expiry (30/15/7) or 0 = expired
    `channel`     ENUM('email','sms') NOT NULL,
    `status`      ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_reminder` (`member_id`, `expiry_date`, `stage`, `channel`),
    CONSTRAINT `fk_reminder_member` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- member_audit — per-member audit trail (create/edit/activate/renew/status)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `member_audit` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `member_id`  INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NULL,       -- admin who performed the action (NULL = system/self)
    `action`     VARCHAR(48)  NOT NULL,
    `changes`    LONGTEXT     NULL,       -- JSON {field:[old,new]}
    `note`       VARCHAR(500) NULL,
    `ip_address` VARCHAR(45)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_member` (`member_id`, `created_at`),
    CONSTRAINT `fk_audit_member` FOREIGN KEY (`member_id`)
        REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings seeds (INSERT IGNORE — keeps any values you have already set)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('membership', 'membership_code_prefix',      'PWF',       'text',    'Membership ID prefix'),
    ('membership', 'membership_card_template',    'classic',   'text',    'Default ID card template'),
    ('membership', 'membership_reminders_enabled','1',         'boolean', 'Send expiry reminders'),
    ('membership', 'membership_reminder_days',    '30,15,7',   'text',    'Reminder days before expiry'),
    ('membership', 'membership_cron_token',       '',          'text',    'Cron trigger token (auto-generated)'),
    ('payments',   'cashfree_enabled',            '0',         'boolean', 'Enable Cashfree payments'),
    ('payments',   'cashfree_env',                'sandbox',   'text',    'Cashfree environment (sandbox/production)'),
    ('payments',   'cashfree_app_id',             '',          'text',    'Cashfree App ID'),
    ('payments',   'cashfree_secret_key',         '',          'text',    'Cashfree Secret Key'),
    ('payments',   'cashfree_webhook_secret',     '',          'text',    'Cashfree Webhook Secret'),
    ('sms',        'sms_enabled',                 '0',         'boolean', 'Enable SMS notifications'),
    ('sms',        'sms_provider',                'msg91',     'text',    'SMS provider (msg91/fast2sms)'),
    ('sms',        'msg91_authkey',               '',          'text',    'MSG91 Auth Key'),
    ('sms',        'msg91_sender',                '',          'text',    'MSG91 Sender ID (6 chars)'),
    ('sms',        'msg91_route',                 '4',         'text',    'MSG91 route'),
    ('sms',        'msg91_dlt_template_id',       '',          'text',    'MSG91 DLT template id'),
    ('sms',        'fast2sms_key',                '',          'text',    'Fast2SMS API key'),
    ('sms',        'fast2sms_sender',             '',          'text',    'Fast2SMS sender id');

SET FOREIGN_KEY_CHECKS = 1;
