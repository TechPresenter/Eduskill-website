-- =============================================================================
--  schema_v10.sql — Visitor Analytics: add region (state) to page_views
--  City + country already exist (schema_v9). This adds the state/region so the
--  dashboard can break traffic down by State and City. Backfills existing rows
--  from the ip_geo cache. Run AFTER schema_v9.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v10.sql
-- =============================================================================
USE `pwf`;

ALTER TABLE `page_views`
    ADD COLUMN IF NOT EXISTS `region` VARCHAR(96) NULL AFTER `city`;

-- Backfill state/city/country for rows whose IP is already resolved in the cache.
UPDATE `page_views` pv
    JOIN `ip_geo` g ON g.ip = pv.ip_address
    SET pv.region       = COALESCE(pv.region, g.region),
        pv.city         = COALESCE(pv.city, g.city),
        pv.country      = COALESCE(pv.country, g.country),
        pv.country_code = COALESCE(pv.country_code, g.country_code)
    WHERE g.status = 'success';
