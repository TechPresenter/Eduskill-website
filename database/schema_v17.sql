-- =============================================================================
--  schema_v17.sql — Unified Payments ledger (Cashfree) across all workflows
--  A single `payments` table records every transaction (course, event, exam,
--  membership, donation, campaign, …) with one secure Cashfree path, plus a
--  webhook audit log. Existing donation/membership flows are untouched.
--  Run AFTER schema_v16.sql.
-- =============================================================================
USE `pwf`;

CREATE TABLE IF NOT EXISTS `payments` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id`           VARCHAR(64)  NOT NULL,          -- our id == Cashfree link_id
    `context_type`       VARCHAR(32)  NOT NULL DEFAULT 'other', -- course|event|exam|quiz|membership|donation|campaign|certificate|workshop|resource|other
    `context_id`         INT UNSIGNED NULL,
    `member_id`          INT UNSIGNED NULL,
    `customer_name`      VARCHAR(128) NULL,
    `customer_email`     VARCHAR(191) NULL,
    `customer_phone`     VARCHAR(32)  NULL,
    `amount`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- gross (before coupon)
    `discount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_amount`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,   -- charged
    `currency`           VARCHAR(8)   NOT NULL DEFAULT 'INR',
    `coupon_code`        VARCHAR(48)  NULL,
    `gateway`            VARCHAR(24)  NOT NULL DEFAULT 'cashfree',
    `gateway_link_id`    VARCHAR(96)  NULL,
    `gateway_payment_id` VARCHAR(96)  NULL,
    `payment_method`     VARCHAR(48)  NULL,
    `status`             ENUM('created','pending','paid','failed','cancelled','refunded','expired') NOT NULL DEFAULT 'created',
    `receipt_no`         VARCHAR(48)  NULL,
    `refund_amount`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `refunded_at`        DATETIME     NULL,
    `purpose`            VARCHAR(191) NULL,
    `meta`               LONGTEXT     NULL,             -- JSON: any fulfilment payload
    `paid_at`            DATETIME     NULL,
    `created_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pay_order` (`order_id`),
    KEY `idx_pay_status` (`status`, `created_at`),
    KEY `idx_pay_context` (`context_type`, `context_id`),
    KEY `idx_pay_member` (`member_id`),
    KEY `idx_pay_email` (`customer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Webhook audit log (every inbound gateway notification) -----------------
CREATE TABLE IF NOT EXISTS `payment_webhooks` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gateway`     VARCHAR(24)  NOT NULL DEFAULT 'cashfree',
    `event`       VARCHAR(64)  NULL,
    `order_id`    VARCHAR(96)  NULL,
    `signature_ok` TINYINT(1)  NOT NULL DEFAULT 0,
    `handled`     TINYINT(1)   NOT NULL DEFAULT 0,
    `note`        VARCHAR(191) NULL,
    `raw`         MEDIUMTEXT   NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pwh_order` (`order_id`),
    KEY `idx_pwh_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
