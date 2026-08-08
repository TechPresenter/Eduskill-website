-- =============================================================================
--  schema_v20.sql — Document Hub: per-document access control (Template Builder)
--  Adds Admin Access / User (public) Access toggles per document template so
--  each document type can be enabled/disabled independently. Run AFTER v19.
-- =============================================================================
USE `pwf`;

ALTER TABLE `document_templates`
    ADD COLUMN IF NOT EXISTS `admin_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`,
    ADD COLUMN IF NOT EXISTS `user_enabled`  TINYINT(1) NOT NULL DEFAULT 1 AFTER `admin_enabled`;
