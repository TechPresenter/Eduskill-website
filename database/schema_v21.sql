-- =============================================================================
--  schema_v21.sql — Email Marketing & Email Management module
-- =============================================================================
--  Extends the v15 email-marketing engine (campaigns/subscribers/templates/
--  automations/SMTP profiles) with an enterprise mail-management layer:
--    * email_messages   — unified mail store powering every mailbox folder
--                         (inbox/sent/drafts/outbox/scheduled/trash/spam/archive)
--                         plus star / important / thread / labels / attachments.
--    * email_labels + email_message_labels — Gmail-style labels.
--    * email_accounts   — multiple SMTP/IMAP/POP3 sending+receiving identities
--                         (secrets stored via sec_encrypt()).
--    * email_signatures — reusable signatures.
--    * contacts + contact_lists + contact_list_members — CRM-style contacts.
--    * email_events     — per-event analytics log (open/click/bounce/device/geo).
--    * email_templates  — + category / description / thumbnail / is_premium.
--
--  Idempotent (IF NOT EXISTS everywhere). Run AFTER schema_v20.sql.
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v21.sql
--  Then seed the 26 premium templates + demo data:
--    C:\xampp\php\php.exe database\seed_email.php
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
--  Unified mail store — one row per message, in exactly one folder.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_messages (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id     INT UNSIGNED NULL,
    thread_id      VARCHAR(64) NULL,                    -- groups a conversation
    message_uid    VARCHAR(191) NULL,                   -- IMAP UID / Message-ID (dedup)
    in_reply_to    VARCHAR(191) NULL,
    direction      ENUM('incoming','outgoing') NOT NULL DEFAULT 'outgoing',
    folder         ENUM('inbox','sent','drafts','outbox','scheduled','trash','spam','archive') NOT NULL DEFAULT 'drafts',
    from_name      VARCHAR(128) NULL,
    from_email     VARCHAR(191) NULL,
    to_email       TEXT NULL,                           -- comma-separated
    to_name        VARCHAR(191) NULL,
    cc             TEXT NULL,
    bcc            TEXT NULL,
    reply_to       VARCHAR(191) NULL,
    subject        VARCHAR(255) NULL,
    preview        VARCHAR(255) NULL,                   -- stripped snippet for lists
    body_html      LONGTEXT NULL,
    body_text      LONGTEXT NULL,
    priority       ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
    is_read        TINYINT(1) NOT NULL DEFAULT 1,       -- outgoing default read; incoming 0
    is_starred     TINYINT(1) NOT NULL DEFAULT 0,
    is_important   TINYINT(1) NOT NULL DEFAULT 0,
    read_receipt   TINYINT(1) NOT NULL DEFAULT 0,
    has_attachments TINYINT(1) NOT NULL DEFAULT 0,
    attachments    LONGTEXT NULL,                       -- JSON [{name,path,size,type}]
    open_token     VARCHAR(48) NULL,                    -- open/click tracking token
    open_count     INT UNSIGNED NOT NULL DEFAULT 0,
    click_count    INT UNSIGNED NOT NULL DEFAULT 0,
    scheduled_at   DATETIME NULL,
    sent_at        DATETIME NULL,
    error_text     VARCHAR(255) NULL,
    related_type   VARCHAR(48) NULL,                    -- e.g. 'member','donation'
    related_id     INT UNSIGNED NULL,
    created_by     INT UNSIGNED NULL,
    deleted_at     DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_folder (folder, created_at),
    KEY idx_thread (thread_id),
    KEY idx_flags (is_starred, is_important, is_read),
    KEY idx_dir (direction),
    KEY idx_sched (folder, scheduled_at),
    KEY idx_uid (message_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Labels (Gmail-style) + message↔label join.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_labels (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(64) NOT NULL,
    slug       VARCHAR(64) NOT NULL,
    color      VARCHAR(9) NOT NULL DEFAULT '#6366f1',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_label_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_message_labels (
    message_id BIGINT UNSIGNED NOT NULL,
    label_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (message_id, label_id),
    KEY idx_label (label_id),
    CONSTRAINT fk_eml_msg  FOREIGN KEY (message_id) REFERENCES email_messages (id) ON DELETE CASCADE,
    CONSTRAINT fk_eml_lbl  FOREIGN KEY (label_id)   REFERENCES email_labels (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Mail accounts — sending (SMTP) + receiving (IMAP/POP3) identities.
--  Passwords are stored via sec_encrypt() (AES-256-GCM). Never store plaintext.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_accounts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(128) NOT NULL,
    email        VARCHAR(191) NOT NULL,
    reply_to     VARCHAR(191) NULL,
    signature_id INT UNSIGNED NULL,
    -- SMTP (outgoing)
    smtp_host    VARCHAR(191) NULL,
    smtp_port    SMALLINT UNSIGNED NULL DEFAULT 587,
    smtp_secure  ENUM('none','tls','ssl') NOT NULL DEFAULT 'tls',
    smtp_user    VARCHAR(191) NULL,
    smtp_pass    VARCHAR(512) NULL,
    -- IMAP (incoming)
    imap_host    VARCHAR(191) NULL,
    imap_port    SMALLINT UNSIGNED NULL DEFAULT 993,
    imap_secure  ENUM('none','tls','ssl') NOT NULL DEFAULT 'ssl',
    imap_user    VARCHAR(191) NULL,
    imap_pass    VARCHAR(512) NULL,
    -- POP3 (alt incoming)
    pop3_host    VARCHAR(191) NULL,
    pop3_port    SMALLINT UNSIGNED NULL DEFAULT 995,
    pop3_secure  ENUM('none','tls','ssl') NOT NULL DEFAULT 'ssl',
    pop3_user    VARCHAR(191) NULL,
    pop3_pass    VARCHAR(512) NULL,
    dkim_selector VARCHAR(64) NULL,
    is_default   TINYINT(1) NOT NULL DEFAULT 0,
    status       TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at DATETIME NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Reusable signatures.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_signatures (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(128) NOT NULL,
    body_html  LONGTEXT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Contacts (CRM) — richer than newsletter_subscribers; lists + membership.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name       VARCHAR(96) NULL,
    last_name        VARCHAR(96) NULL,
    email            VARCHAR(191) NOT NULL,
    phone            VARCHAR(32) NULL,
    company          VARCHAR(128) NULL,
    job_title        VARCHAR(128) NULL,
    country          VARCHAR(64) NULL,
    tags             VARCHAR(255) NULL,
    status           ENUM('active','unsubscribed','bounced','spam') NOT NULL DEFAULT 'active',
    source           VARCHAR(64) NULL,
    notes            TEXT NULL,
    last_contacted_at DATETIME NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_contact_email (email),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_lists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(128) NOT NULL,
    description VARCHAR(255) NULL,
    color       VARCHAR(9) NOT NULL DEFAULT '#2563eb',
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_list_members (
    list_id    INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,
    added_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (list_id, contact_id),
    KEY idx_contact (contact_id),
    CONSTRAINT fk_clm_list    FOREIGN KEY (list_id)    REFERENCES contact_lists (id) ON DELETE CASCADE,
    CONSTRAINT fk_clm_contact FOREIGN KEY (contact_id) REFERENCES contacts (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Per-event analytics log (powers device / browser / geo / comparison reports).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS email_events (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id  BIGINT UNSIGNED NULL,
    campaign_id INT UNSIGNED NULL,
    recipient   VARCHAR(191) NULL,
    event_type  ENUM('sent','delivered','open','click','bounce','spam','unsubscribe','fail') NOT NULL,
    url         VARCHAR(500) NULL,
    device      VARCHAR(32) NULL,                       -- desktop/mobile/tablet
    os          VARCHAR(48) NULL,
    browser     VARCHAR(48) NULL,
    country     VARCHAR(64) NULL,
    ip          VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ev_type (event_type, created_at),
    KEY idx_ev_campaign (campaign_id),
    KEY idx_ev_message (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Template library metadata (category / description / thumbnail / premium).
-- -----------------------------------------------------------------------------
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS category    VARCHAR(48)  NULL AFTER name;
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS description VARCHAR(255) NULL AFTER category;
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS thumbnail   VARCHAR(24)  NULL AFTER description;
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS is_premium  TINYINT(1)   NOT NULL DEFAULT 0 AFTER thumbnail;
ALTER TABLE email_templates ADD COLUMN IF NOT EXISTS sort_order  INT          NOT NULL DEFAULT 0 AFTER is_premium;

-- Map the 6 pre-existing templates into the new library categories.
UPDATE email_templates SET category='Onboarding',  thumbnail='#6366f1' WHERE slug='welcome'                    AND (category IS NULL OR category='');
UPDATE email_templates SET category='Donations',    thumbnail='#16a34a' WHERE slug='donation-thanks'            AND (category IS NULL OR category='');
UPDATE email_templates SET category='Membership',   thumbnail='#d9a82e' WHERE slug='membership_welcome'         AND (category IS NULL OR category='');
UPDATE email_templates SET category='Membership',   thumbnail='#f59e0b' WHERE slug='membership_expiry_reminder' AND (category IS NULL OR category='');
UPDATE email_templates SET category='Membership',   thumbnail='#10b981' WHERE slug='membership_renewed'         AND (category IS NULL OR category='');
UPDATE email_templates SET category='Membership',   thumbnail='#ef4444' WHERE slug='membership_expired'         AND (category IS NULL OR category='');

-- -----------------------------------------------------------------------------
--  Seed labels (idempotent) — Gmail-style quick labels.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO email_labels (name, slug, color, sort_order) VALUES
    ('Follow-up',  'follow-up',  '#f59e0b', 1),
    ('Important',  'important',  '#ef4444', 2),
    ('Donors',     'donors',     '#16a34a', 3),
    ('Volunteers', 'volunteers', '#0ea5e9', 4),
    ('Partners',   'partners',   '#8b5cf6', 5),
    ('Newsletter', 'newsletter', '#ec4899', 6);

-- -----------------------------------------------------------------------------
--  Email module settings (grouped 'email'). Defaults; edited in Email Settings.
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO settings (`group_name`, `key_name`, `value`, `type`) VALUES
    ('email', 'email_queue_batch',   '50',  'text'),
    ('email', 'email_queue_enabled', '1',   'text'),
    ('email', 'email_default_from',  '',    'text'),
    ('email', 'email_default_reply', '',    'text'),
    ('email', 'email_dkim_selector', '',    'text'),
    ('email', 'email_spf_record',    '',    'textarea'),
    ('email', 'email_dkim_record',   '',    'textarea'),
    ('email', 'email_rate_per_min',  '60',  'text');
