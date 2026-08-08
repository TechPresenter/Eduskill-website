-- =============================================================================
--  schema_v24.sql — Maintenance mode + hot-path indexes
--
--    1. Seeds the `maintenance_*` settings that drive maintenance.php and the
--       new "Maintenance" panel in Admin → Website Settings.
--    2. Adds indexes to *_id / lookup columns that the application filters on
--       but which had no index, so each lookup was a full table scan.
--
--  Idempotent: safe to run more than once, and safe on both MariaDB and MySQL
--  (ALTER TABLE ... ADD INDEX IF NOT EXISTS is MariaDB-only, so index creation
--  goes through a helper procedure that checks information_schema first).
--
--  Run AFTER schema_v23.sql.
-- =============================================================================
USE `pwf`;

-- -----------------------------------------------------------------------------
-- 1. Maintenance mode settings
-- -----------------------------------------------------------------------------
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('maintenance', 'maintenance_mode',          '0',  'boolean',  'Enable maintenance mode'),
    ('maintenance', 'maintenance_message',
        'We''re carrying out some scheduled maintenance to make things better. The site will be back shortly — thank you for your patience.',
        'textarea', 'Notice shown to visitors'),
    ('maintenance', 'maintenance_eta',           '',   'text',     'Expected back (optional)'),
    ('maintenance', 'maintenance_retry_minutes', '30', 'number',   'Retry-After (minutes)')
ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`);
-- NOTE: only group_name is refreshed on re-run, so an operator's own wording in
-- maintenance_message (and, critically, a live maintenance_mode='1') is never
-- reset by re-applying this migration.

-- -----------------------------------------------------------------------------
-- 2. Schema drift: menus.page_key / menus.mega
-- -----------------------------------------------------------------------------
-- admin/menus.php writes both columns (lines 49 and 51) but no migration ever
-- created them — schema.sql builds `menus` without them. They exist on
-- long-running installs where they were added by hand, so a FRESH install built
-- from the migration chain fatals the first time a menu item is saved.
DROP PROCEDURE IF EXISTS `pwf_add_column`;
DELIMITER $$
CREATE PROCEDURE `pwf_add_column`(
    IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_def TEXT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND TABLE_NAME = p_table AND COLUMN_NAME = p_column)
    THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_def);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL pwf_add_column('menus', 'page_key', 'VARCHAR(64) NULL AFTER `url`');
CALL pwf_add_column('menus', 'mega',     'TINYINT(1) NOT NULL DEFAULT 0 AFTER `icon`');

DROP PROCEDURE IF EXISTS `pwf_add_column`;

-- -----------------------------------------------------------------------------
-- 3. Hot-path indexes
-- -----------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS `pwf_add_index`;
DELIMITER $$
CREATE PROCEDURE `pwf_add_index`(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_column VARCHAR(64)
)
BEGIN
    -- Skip silently when the table is absent (partial installs) or the index
    -- already exists (re-run).
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_column, '`)');
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- Looked up by the Razorpay webhook on every callback (api/v1/razorpay-webhook.php).
CALL pwf_add_index('donations',           'idx_don_gateway_order', 'gateway_order_id');
-- LMS lookups.
CALL pwf_add_index('exam_attempts',       'idx_ea_student',  'student_id');
CALL pwf_add_index('admissions',          'idx_adm_student', 'student_id');
CALL pwf_add_index('course_enrollments',  'idx_ce_batch',    'batch_id');
CALL pwf_add_index('live_sessions',       'idx_ls_batch',    'batch_id');
CALL pwf_add_index('notices',             'idx_not_batch',   'batch_id');
CALL pwf_add_index('exams',               'idx_ex_lesson',   'lesson_id');
CALL pwf_add_index('course_resources',    'idx_cr_lesson',   'lesson_id');
-- Membership renewal joins.
CALL pwf_add_index('membership_renewals', 'idx_mr_plan',     'plan_id');
CALL pwf_add_index('membership_renewals', 'idx_mr_payment',  'payment_id');
-- Referral lookups.
CALL pwf_add_index('referral_codes',      'idx_rc_owner',    'owner_id');

DROP PROCEDURE IF EXISTS `pwf_add_index`;
