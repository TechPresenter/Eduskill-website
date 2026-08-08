-- =============================================================================
--  schema_v26.sql — Country + dial code stored alongside every submitted phone
--
--  The public forms now use a country selector (includes/countries.php +
--  country_field()). This adds three columns wherever a phone number is
--  captured, so the country NAME, ISO-3166 alpha-2 CODE and DIAL CODE are
--  stored with the number instead of being lost at submit time:
--
--      <phone>_country_name   e.g. "India"
--      <phone>_country_iso    e.g. "IN"
--      <phone>_country_dial   e.g. "91"
--
--  Idempotent and portable (MySQL has no ADD COLUMN IF NOT EXISTS).
--  Run AFTER schema_v25.sql.
-- =============================================================================
USE `pwf`;

-- Drop BOTH helpers up front. If an earlier run aborted part-way the second
-- procedure would still exist and the re-run would fail on "already exists",
-- which would make this migration non-idempotent.
DROP PROCEDURE IF EXISTS `pwf_add_country_cols`;
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

-- Adds the trio to one table in a single call. The anchor column is only used
-- when it actually exists on that table (feedback, for example, has no `phone`),
-- otherwise the columns are simply appended.
CREATE PROCEDURE `pwf_add_country_cols`(IN p_table VARCHAR(64), IN p_after VARCHAR(64))
BEGIN
    DECLARE v_after TEXT DEFAULT '';
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_after)
    THEN
        SET v_after = CONCAT(" AFTER `", p_after, "`");
    END IF;
    CALL pwf_add_column(p_table, 'country_name', CONCAT('VARCHAR(64) NULL', v_after));
    CALL pwf_add_column(p_table, 'country_iso',  "CHAR(2) NULL AFTER `country_name`");
    CALL pwf_add_column(p_table, 'country_dial', "VARCHAR(6) NULL AFTER `country_iso`");
END$$
DELIMITER ;

-- Public-facing forms.
CALL pwf_add_country_cols('contact_messages',        'phone');
CALL pwf_add_country_cols('volunteers',              'phone');
CALL pwf_add_country_cols('internships',             'phone');
CALL pwf_add_country_cols('job_applications',        'phone');
CALL pwf_add_country_cols('scholarship_applications','phone');
CALL pwf_add_country_cols('partner_applications',    'phone');
CALL pwf_add_country_cols('event_registrations',     'phone');
CALL pwf_add_country_cols('membership_applications', 'phone');
CALL pwf_add_country_cols('donations',               'phone');
CALL pwf_add_country_cols('admissions',              'phone');
CALL pwf_add_country_cols('members',                 'phone');
CALL pwf_add_country_cols('feedback',                'phone');

DROP PROCEDURE IF EXISTS `pwf_add_country_cols`;
DROP PROCEDURE IF EXISTS `pwf_add_column`;

-- Reporting by country is the whole point of storing the ISO code.
DROP PROCEDURE IF EXISTS `pwf_add_index`;
DELIMITER $$
CREATE PROCEDURE `pwf_add_index`(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_col VARCHAR(64))
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_col)
       AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index)
    THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_col, '`)');
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL pwf_add_index('donations',        'idx_don_country', 'country_iso');
CALL pwf_add_index('volunteers',       'idx_vol_country', 'country_iso');
CALL pwf_add_index('members',          'idx_mem_country', 'country_iso');
CALL pwf_add_index('job_applications', 'idx_ja_country',  'country_iso');

DROP PROCEDURE IF EXISTS `pwf_add_index`;
