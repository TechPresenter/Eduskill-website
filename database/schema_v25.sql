-- =============================================================================
--  schema_v25.sql — Social login (Google / Facebook OAuth)
--
--    1. Links a member account to an external identity provider.
--    2. Seeds the admin-editable OAuth settings (Settings -> Social Login), so
--       credentials are configured in the panel and never hardcoded.
--
--  Idempotent: safe to run repeatedly, on MariaDB and MySQL alike.
--  Run AFTER schema_v24.sql.
-- =============================================================================
USE `pwf`;

-- -----------------------------------------------------------------------------
-- 1. members: external identity link
-- -----------------------------------------------------------------------------
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

CALL pwf_add_column('members', 'oauth_provider', "VARCHAR(20) NULL COMMENT 'google|facebook' AFTER `password`");
CALL pwf_add_column('members', 'oauth_uid',      "VARCHAR(191) NULL COMMENT 'provider account id' AFTER `oauth_provider`");
CALL pwf_add_column('members', 'avatar_url',     "VARCHAR(500) NULL COMMENT 'remote profile picture' AFTER `avatar`");

DROP PROCEDURE IF EXISTS `pwf_add_column`;

-- A provider account may map to exactly one member.
DROP PROCEDURE IF EXISTS `pwf_add_index`;
DELIMITER $$
CREATE PROCEDURE `pwf_add_index`(
    IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_cols TEXT, IN p_unique TINYINT
)
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table)
       AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD ',
                          IF(p_unique = 1, 'UNIQUE ', ''), 'INDEX `', p_index, '` (', p_cols, ')');
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL pwf_add_index('members', 'uq_member_oauth', '`oauth_provider`, `oauth_uid`', 1);

DROP PROCEDURE IF EXISTS `pwf_add_index`;

-- -----------------------------------------------------------------------------
-- 2. Admin-editable OAuth settings
-- -----------------------------------------------------------------------------
-- Redirect URIs are left blank on purpose: includes/oauth.php derives them from
-- APP_URL at runtime, so a site works in local and production without edits.
-- Fill one in only to override (e.g. behind a proxy with a different hostname).
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('oauth', 'google_login_enabled',   '0', 'boolean', 'Enable Google sign-in'),
    ('oauth', 'google_client_id',       '',  'text',    'Google Client ID'),
    ('oauth', 'google_client_secret',   '',  'text',    'Google Client Secret'),
    ('oauth', 'google_redirect_uri',    '',  'url',     'Google Redirect URI (blank = auto)'),
    ('oauth', 'facebook_login_enabled', '0', 'boolean', 'Enable Facebook sign-in'),
    ('oauth', 'facebook_app_id',        '',  'text',    'Facebook App ID'),
    ('oauth', 'facebook_app_secret',    '',  'text',    'Facebook App Secret'),
    ('oauth', 'facebook_redirect_uri',  '',  'url',     'Facebook Redirect URI (blank = auto)')
ON DUPLICATE KEY UPDATE `group_name` = VALUES(`group_name`), `label` = VALUES(`label`);
-- Only group/label refresh on re-run, so live credentials are never wiped.
