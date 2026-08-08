-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v12 (Campaign + Donation system, 5.8/5.9)
--  Extends the existing campaigns/donations tables and adds campaign media,
--  updates, volunteer assignments, recurring donation subscriptions and a
--  refund audit trail. Seeds Razorpay / Stripe / PayPal + 80G settings.
--
--  Run AFTER schema_v11.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v12.sql
--  Idempotent.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- campaigns — category + milestone markers + donor count
-- -----------------------------------------------------------------------------
ALTER TABLE `campaigns`
    ADD COLUMN IF NOT EXISTS `category`    VARCHAR(96)  NULL AFTER `short_description`,
    ADD COLUMN IF NOT EXISTS `location`    VARCHAR(191) NULL AFTER `category`,
    ADD COLUMN IF NOT EXISTS `milestones`  LONGTEXT     NULL AFTER `goal_amount`,   -- JSON [amount,...]
    ADD COLUMN IF NOT EXISTS `donor_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `raised_amount`,
    ADD COLUMN IF NOT EXISTS `closed_at`   DATETIME     NULL AFTER `end_date`;

-- -----------------------------------------------------------------------------
-- donations — gateway + receipt + recurring + refund fields
-- -----------------------------------------------------------------------------
ALTER TABLE `donations`
    ADD COLUMN IF NOT EXISTS `receipt_no`         VARCHAR(48)  NULL AFTER `transaction_id`,
    ADD COLUMN IF NOT EXISTS `receipt_sent`       TINYINT(1)   NOT NULL DEFAULT 0 AFTER `receipt_no`,
    ADD COLUMN IF NOT EXISTS `gateway`            VARCHAR(32)  NULL AFTER `payment_method`,
    ADD COLUMN IF NOT EXISTS `gateway_order_id`   VARCHAR(96)  NULL AFTER `gateway`,
    ADD COLUMN IF NOT EXISTS `gateway_payment_id` VARCHAR(96)  NULL AFTER `gateway_order_id`,
    ADD COLUMN IF NOT EXISTS `subscription_id`    INT UNSIGNED NULL AFTER `gateway_payment_id`,
    ADD COLUMN IF NOT EXISTS `refunded_at`        DATETIME     NULL AFTER `status`,
    ADD COLUMN IF NOT EXISTS `refund_amount`      DECIMAL(12,2) NULL AFTER `refunded_at`;

ALTER TABLE `donations`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_don_receipt` (`receipt_no`),
    ADD INDEX IF NOT EXISTS `idx_don_gwpay` (`gateway_payment_id`),
    ADD INDEX IF NOT EXISTS `idx_don_sub` (`subscription_id`);

-- -----------------------------------------------------------------------------
-- donation_subscriptions — recurring donations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_subscriptions` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`    INT UNSIGNED NULL,
    `donor_name`     VARCHAR(128) NOT NULL,
    `email`          VARCHAR(191) NULL,
    `phone`          VARCHAR(32)  NULL,
    `amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `currency`       VARCHAR(8)   NOT NULL DEFAULT 'INR',
    `frequency`      ENUM('monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
    `gateway`        VARCHAR(32)  NULL,
    `gateway_sub_id` VARCHAR(96)  NULL,
    `status`         ENUM('created','active','paused','cancelled','completed') NOT NULL DEFAULT 'created',
    `next_charge_at` DATE         NULL,
    `charges_done`   INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dsub_status` (`status`),
    KEY `idx_dsub_gw` (`gateway_sub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- donation_refunds — admin-initiated refund audit trail
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `donation_refunds` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `donation_id`       INT UNSIGNED NOT NULL,
    `amount`            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `reason`            VARCHAR(500) NULL,
    `gateway`           VARCHAR(32)  NULL,
    `gateway_refund_id` VARCHAR(96)  NULL,
    `status`            ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
    `created_by`        INT UNSIGNED NULL,
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dref_donation` (`donation_id`),
    CONSTRAINT `fk_dref_donation` FOREIGN KEY (`donation_id`)
        REFERENCES `donations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- campaign_media — per-campaign gallery (image / video)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_media` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `type`        ENUM('image','video') NOT NULL DEFAULT 'image',
    `path`        VARCHAR(255) NULL,     -- uploaded image path
    `url`         VARCHAR(500) NULL,     -- external video url
    `caption`     VARCHAR(255) NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cmedia_campaign` (`campaign_id`, `sort_order`),
    CONSTRAINT `fk_cmedia_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- campaign_updates — progress updates posted to donors
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_updates` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `title`       VARCHAR(191) NOT NULL,
    `body`        LONGTEXT     NULL,
    `image`       VARCHAR(255) NULL,
    `notified`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cupd_campaign` (`campaign_id`),
    CONSTRAINT `fk_cupd_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- campaign_volunteers — volunteers assigned to campaign tasks
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_volunteers` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`  INT UNSIGNED NOT NULL,
    `volunteer_id` INT UNSIGNED NULL,     -- optional link to volunteers table
    `name`         VARCHAR(128) NOT NULL,
    `email`        VARCHAR(191) NULL,
    `phone`        VARCHAR(32)  NULL,
    `task`         VARCHAR(191) NULL,
    `status`       ENUM('assigned','active','completed','withdrawn') NOT NULL DEFAULT 'assigned',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cvol_campaign` (`campaign_id`),
    CONSTRAINT `fk_cvol_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings — gateways + 80G/receipt
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('payments', 'razorpay_enabled',        '0', 'boolean', 'Enable Razorpay'),
    ('payments', 'razorpay_key_id',         '',  'text',    'Razorpay Key ID'),
    ('payments', 'razorpay_key_secret',     '',  'text',    'Razorpay Key Secret'),
    ('payments', 'razorpay_webhook_secret', '',  'text',    'Razorpay Webhook Secret'),
    ('payments', 'stripe_enabled',          '0', 'boolean', 'Enable Stripe'),
    ('payments', 'stripe_publishable',      '',  'text',    'Stripe Publishable Key'),
    ('payments', 'stripe_secret',           '',  'text',    'Stripe Secret Key'),
    ('payments', 'stripe_webhook_secret',   '',  'text',    'Stripe Webhook Secret'),
    ('payments', 'paypal_enabled',          '0', 'boolean', 'Enable PayPal'),
    ('payments', 'paypal_env',              'sandbox', 'text', 'PayPal environment'),
    ('payments', 'paypal_client_id',        '',  'text',    'PayPal Client ID'),
    ('payments', 'paypal_secret',           '',  'text',    'PayPal Secret'),
    ('payments', 'paypal_webhook_id',       '',  'text',    'PayPal Webhook ID'),
    ('donation', 'donation_receipt_prefix', 'DN', 'text',   'Donation receipt prefix'),
    ('donation', 'org_80g_number',          '',  'text',    '80G registration number'),
    ('donation', 'org_12a_number',          '',  'text',    '12A registration number'),
    ('donation', 'org_pan',                 '',  'text',    'Organisation PAN'),
    ('donation', 'donation_currency',       'INR', 'text',  'Default donation currency');

SET FOREIGN_KEY_CHECKS = 1;
