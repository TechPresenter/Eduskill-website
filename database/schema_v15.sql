-- =============================================================================
--  schema_v15.sql — Email Marketing + SMS/WhatsApp/Push + In-App Notifications
--  Run AFTER schema_v14.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v15.sql
--  Idempotent (MariaDB — IF NOT EXISTS / INSERT IGNORE).
-- =============================================================================
USE `pwf`;

-- ---- Subscribers: tags, birthday, last-emailed (segmentation) ---------------
ALTER TABLE `newsletter_subscribers`
    ADD COLUMN IF NOT EXISTS `tags`          VARCHAR(255) NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `birthday`      DATE         NULL AFTER `tags`,
    ADD COLUMN IF NOT EXISTS `last_email_at` DATETIME     NULL AFTER `birthday`;

-- ---- SMTP profiles (SendGrid / Mailgun / SES / custom) ----------------------
CREATE TABLE IF NOT EXISTS `email_smtp_profiles` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(128) NOT NULL,
    `provider`   VARCHAR(32)  NOT NULL DEFAULT 'custom',
    `host`       VARCHAR(191) NOT NULL,
    `port`       SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    `secure`     ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    `username`   VARCHAR(191) NULL,
    `password`   VARCHAR(255) NULL,
    `from_email` VARCHAR(191) NOT NULL,
    `from_name`  VARCHAR(128) NULL,
    `is_default` TINYINT(1)   NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Email campaigns --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_campaigns` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(191) NOT NULL,
    `subject`        VARCHAR(191) NOT NULL,
    `subject_b`      VARCHAR(191) NULL,
    `ab_enabled`     TINYINT(1)   NOT NULL DEFAULT 0,
    `from_name`      VARCHAR(128) NULL,
    `from_email`     VARCHAR(191) NULL,
    `smtp_profile_id` INT UNSIGNED NULL,
    `template_id`    INT UNSIGNED NULL,
    `body`           LONGTEXT     NULL,
    `segment_status` VARCHAR(24)  NOT NULL DEFAULT 'subscribed',
    `segment_tag`    VARCHAR(128) NULL,
    `status`         ENUM('draft','scheduled','sending','sent','paused') NOT NULL DEFAULT 'draft',
    `scheduled_at`   DATETIME     NULL,
    `total`          INT UNSIGNED NOT NULL DEFAULT 0,
    `sent_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `open_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `click_count`    INT UNSIGNED NOT NULL DEFAULT 0,
    `fail_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ec_status` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Per-recipient send log (delivery reports + open/click tracking) --------
CREATE TABLE IF NOT EXISTS `email_campaign_recipients` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`  INT UNSIGNED NOT NULL,
    `subscriber_id` INT UNSIGNED NULL,
    `email`        VARCHAR(191) NOT NULL,
    `name`         VARCHAR(128) NULL,
    `variant`      CHAR(1)      NOT NULL DEFAULT 'A',
    `token`        VARCHAR(48)  NOT NULL,
    `status`       ENUM('queued','sent','failed','opened','clicked','bounced','unsubscribed') NOT NULL DEFAULT 'queued',
    `sent_at`      DATETIME     NULL,
    `opened_at`    DATETIME     NULL,
    `clicked_at`   DATETIME     NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ecr_token` (`token`),
    KEY `idx_ecr_campaign` (`campaign_id`, `status`),
    KEY `idx_ecr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Email automations (triggers) ------------------------------------------
CREATE TABLE IF NOT EXISTS `email_automations` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trigger_key` VARCHAR(48)  NOT NULL,
    `name`        VARCHAR(128) NOT NULL,
    `subject`     VARCHAR(191) NOT NULL,
    `body`        LONGTEXT     NULL,
    `enabled`     TINYINT(1)   NOT NULL DEFAULT 0,
    `offset_days` INT          NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ea_trigger` (`trigger_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `email_automations` (`trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`) VALUES
 ('welcome',           'Welcome email',              'Welcome to {{site_name}}!',          '<p>Hi {{name}},</p><p>Thanks for subscribing to {{site_name}}. We are glad to have you.</p>', 0, 0),
 ('donation_receipt',  'Donation thank-you',         'Thank you for your donation',        '<p>Dear {{name}},</p><p>We have received your generous donation of {{amount}}. Thank you for supporting our work.</p>', 0, 0),
 ('birthday',          'Birthday greeting',          'Happy Birthday, {{name}}!',          '<p>Wishing you a wonderful birthday from all of us at {{site_name}}.</p>', 0, 0),
 ('membership_expiry', 'Membership expiry reminder', 'Your membership is expiring soon',   '<p>Hi {{name}},</p><p>Your membership expires on {{expiry_date}}. Renew today to keep your benefits.</p>', 0, 7),
 ('inactivity',        'We miss you',                'We miss you at {{site_name}}',        '<p>Hi {{name}},</p><p>It has been a while. Here is what you have missed.</p>', 0, 90);

-- ---- Unified SMS / WhatsApp / Push delivery log -----------------------------
CREATE TABLE IF NOT EXISTS `messaging_log` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel`         ENUM('sms','whatsapp','push') NOT NULL,
    `provider`        VARCHAR(32)  NULL,
    `recipient`       VARCHAR(191) NULL,
    `template`        VARCHAR(128) NULL,
    `body`            TEXT         NULL,
    `status`          ENUM('queued','sent','delivered','failed','read') NOT NULL DEFAULT 'queued',
    `provider_msg_id` VARCHAR(128) NULL,
    `error`           VARCHAR(500) NULL,
    `created_by`      INT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_msg_channel` (`channel`, `status`),
    KEY `idx_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- In-app notifications (real-time centre, read/unread) -------------------
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NULL,          -- NULL = broadcast to all admins
    `title`      VARCHAR(191) NOT NULL,
    `body`       VARCHAR(500) NULL,
    `url`        VARCHAR(255) NULL,
    `icon`       VARCHAR(48)  NOT NULL DEFAULT 'bell',
    `type`       VARCHAR(24)  NOT NULL DEFAULT 'info',
    `read_at`    DATETIME     NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user` (`user_id`, `read_at`),
    KEY `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Settings seeds: messaging channels ------------------------------------
INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
 ('messaging', 'sms_enabled',        '0',      'boolean', 'Enable SMS'),
 ('messaging', 'sms_provider',       'msg91',  'text',    'SMS provider (msg91/twilio)'),
 ('messaging', 'msg91_authkey',      '',       'text',    'MSG91 Auth Key'),
 ('messaging', 'msg91_sender',       '',       'text',    'MSG91 Sender ID'),
 ('messaging', 'msg91_route',        '4',      'text',    'MSG91 Route'),
 ('messaging', 'msg91_dlt_template', '',       'text',    'MSG91 DLT Template ID'),
 ('messaging', 'twilio_sid',         '',       'text',    'Twilio Account SID'),
 ('messaging', 'twilio_token',       '',       'text',    'Twilio Auth Token'),
 ('messaging', 'twilio_from',        '',       'text',    'Twilio From Number'),
 ('messaging', 'whatsapp_enabled',   '0',      'boolean', 'Enable WhatsApp'),
 ('messaging', 'whatsapp_token',     '',       'text',    'WhatsApp Cloud API Token'),
 ('messaging', 'whatsapp_phone_id',  '',       'text',    'WhatsApp Phone Number ID'),
 ('messaging', 'onesignal_enabled',  '0',      'boolean', 'Enable OneSignal Push'),
 ('messaging', 'onesignal_app_id',   '',       'text',    'OneSignal App ID'),
 ('messaging', 'onesignal_api_key',  '',       'text',    'OneSignal REST API Key');
