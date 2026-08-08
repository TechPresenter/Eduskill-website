-- =============================================================================
--  schema_v27.sql — Full job application profile
--
--  The redesigned Careers application form captures a complete candidate
--  profile. `job_applications` previously held only name / email / phone /
--  resume / cover_letter, so every other field on the form had nowhere to go
--  and was silently dropped at submit time.
--
--  Idempotent and portable. Run AFTER schema_v26.sql.
-- =============================================================================
USE `pwf`;

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

-- Location
CALL pwf_add_column('job_applications', 'address',          "VARCHAR(255) NULL AFTER `country_dial`");
CALL pwf_add_column('job_applications', 'city',             "VARCHAR(96) NULL AFTER `address`");
CALL pwf_add_column('job_applications', 'state',            "VARCHAR(96) NULL AFTER `city`");

-- Professional profile
CALL pwf_add_column('job_applications', 'qualification',    "VARCHAR(128) NULL AFTER `state`");
CALL pwf_add_column('job_applications', 'experience',       "VARCHAR(64) NULL AFTER `qualification`");
CALL pwf_add_column('job_applications', 'current_company',  "VARCHAR(191) NULL AFTER `experience`");
CALL pwf_add_column('job_applications', 'current_salary',   "VARCHAR(64) NULL AFTER `current_company`");
CALL pwf_add_column('job_applications', 'expected_salary',  "VARCHAR(64) NULL AFTER `current_salary`");
CALL pwf_add_column('job_applications', 'notice_period',    "VARCHAR(64) NULL AFTER `expected_salary`");
CALL pwf_add_column('job_applications', 'position_applied', "VARCHAR(191) NULL AFTER `notice_period`");

-- Links + free text
CALL pwf_add_column('job_applications', 'linkedin',         "VARCHAR(255) NULL AFTER `position_applied`");
CALL pwf_add_column('job_applications', 'portfolio',        "VARCHAR(255) NULL AFTER `linkedin`");
CALL pwf_add_column('job_applications', 'message',          "TEXT NULL AFTER `cover_letter`");

DROP PROCEDURE IF EXISTS `pwf_add_column`;
