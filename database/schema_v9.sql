-- =============================================================================
--  schema_v9.sql — Visitor Analytics enrichment
--  Adds: geo columns on page_views + a per-IP geolocation cache (ip_geo).
--  Geo is resolved once per IP via a free IP-API and cached here, so the
--  public site never calls the API more than once for the same visitor.
--  Run AFTER schema_v8.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v9.sql
--  (MariaDB — uses ADD COLUMN IF NOT EXISTS so it is safe to re-run.)
-- =============================================================================
USE `pwf`;

-- ---- Geo columns on page_views (denormalised for fast dashboard queries) ----
ALTER TABLE `page_views`
    ADD COLUMN IF NOT EXISTS `country`      VARCHAR(64) NULL AFTER `device`,
    ADD COLUMN IF NOT EXISTS `country_code` CHAR(2)     NULL AFTER `country`,
    ADD COLUMN IF NOT EXISTS `city`         VARCHAR(96) NULL AFTER `country_code`;

ALTER TABLE `page_views`
    ADD KEY IF NOT EXISTS `idx_pv_country` (`country_code`);

-- ---- Per-IP geolocation cache ----------------------------------------------
CREATE TABLE IF NOT EXISTS `ip_geo` (
    `ip`           VARCHAR(45)  NOT NULL,
    `status`       VARCHAR(16)  NOT NULL DEFAULT 'unknown',   -- success | fail | private | unknown
    `country`      VARCHAR(64)  NULL,
    `country_code` CHAR(2)      NULL,
    `region`       VARCHAR(96)  NULL,
    `city`         VARCHAR(96)  NULL,
    `lat`          DECIMAL(9,6) NULL,
    `lon`          DECIMAL(9,6) NULL,
    `isp`          VARCHAR(128) NULL,
    `timezone`     VARCHAR(64)  NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`ip`),
    KEY `idx_geo_cc` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
