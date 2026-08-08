-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v4 (auth hardening: 2FA, remember-me,
--  member registration type/document)
--  Run AFTER schema_v3.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v4.sql
-- =============================================================================
USE `pwf`;

-- TOTP two-factor for admin users
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `totp_secret`  VARCHAR(64) NULL AFTER `remember_token`,
    ADD COLUMN IF NOT EXISTS `totp_enabled` TINYINT(1)  NOT NULL DEFAULT 0 AFTER `totp_secret`;

-- Registration type + document for members
ALTER TABLE `members`
    ADD COLUMN IF NOT EXISTS `type`     VARCHAR(32)  NOT NULL DEFAULT 'member' AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `document` VARCHAR(255) NULL AFTER `avatar`,
    ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(45) NULL AFTER `last_login_at`;
