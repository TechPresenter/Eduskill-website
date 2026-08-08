-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v7 (School Management)
--  Partner schools, staff assignments, student enrolment, course batches,
--  fee structures/collection and attendance.
--
--  Run AFTER schema_v6.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v7.sql
--
--  Idempotent — CREATE TABLE IF NOT EXISTS + INSERT IGNORE.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- schools — partner school profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `schools` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`             VARCHAR(32)  NULL,
    `name`             VARCHAR(191) NOT NULL,
    `slug`             VARCHAR(191) NOT NULL,
    `type`             ENUM('government','private','aided','other') NOT NULL DEFAULT 'government',
    `board`            VARCHAR(96)  NULL,       -- affiliation / board (CBSE, State…)
    `accreditation`    VARCHAR(191) NULL,
    `udise_code`       VARCHAR(32)  NULL,       -- national school id (India UDISE+)
    `contact_person`   VARCHAR(128) NULL,
    `email`            VARCHAR(191) NULL,
    `phone`            VARCHAR(32)  NULL,
    `website`          VARCHAR(191) NULL,
    `address`          VARCHAR(255) NULL,
    `city`             VARCHAR(96)  NULL,
    `state`            VARCHAR(96)  NULL,
    `pincode`          VARCHAR(12)  NULL,
    `established_year` SMALLINT UNSIGNED NULL,
    `logo`             VARCHAR(255) NULL,
    `status`           ENUM('active','inactive','prospective') NOT NULL DEFAULT 'active',
    `notes`            TEXT         NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_schools_slug` (`slug`),
    UNIQUE KEY `uq_schools_code` (`code`),
    KEY `idx_schools_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_staff — NGO staff / teachers assigned to a school
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_staff` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`     INT UNSIGNED NOT NULL,
    `team_id`       INT UNSIGNED NULL,          -- optional link to team_members
    `name`          VARCHAR(128) NOT NULL,
    `email`         VARCHAR(191) NULL,
    `phone`         VARCHAR(32)  NULL,
    `role`          ENUM('coordinator','teacher','volunteer','administrator','counselor') NOT NULL DEFAULT 'teacher',
    `subject`       VARCHAR(96)  NULL,
    `assigned_date` DATE         NULL,
    `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sstaff_school` (`school_id`),
    CONSTRAINT `fk_sstaff_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_batches — school-specific course batches with schedules
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_batches` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(128) NOT NULL,
    `program_id` INT UNSIGNED NULL,             -- linked NGO program/course
    `teacher_id` INT UNSIGNED NULL,             -- school_staff id
    `schedule`   VARCHAR(191) NULL,             -- e.g. "Mon/Wed/Fri 4–5 PM"
    `start_date` DATE         NULL,
    `end_date`   DATE         NULL,
    `capacity`   INT UNSIGNED NULL,
    `status`     ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    `notes`      TEXT         NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sbatch_school` (`school_id`),
    CONSTRAINT `fk_sbatch_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_students — students enrolled from a school into NGO programs
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_students` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`       INT UNSIGNED NOT NULL,
    `student_code`    VARCHAR(32)  NULL,
    `name`            VARCHAR(128) NOT NULL,
    `guardian_name`   VARCHAR(128) NULL,
    `gender`          ENUM('male','female','other') NULL,
    `dob`             DATE         NULL,
    `grade`           VARCHAR(48)  NULL,         -- class / grade
    `roll_no`         VARCHAR(48)  NULL,
    `phone`           VARCHAR(32)  NULL,
    `email`           VARCHAR(191) NULL,
    `address`         VARCHAR(255) NULL,
    `program_id`      INT UNSIGNED NULL,         -- NGO program
    `batch_id`        INT UNSIGNED NULL,         -- school_batches
    `enrollment_date` DATE         NULL,
    `status`          ENUM('enrolled','active','completed','dropped') NOT NULL DEFAULT 'enrolled',
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sstudent_code` (`student_code`),
    KEY `idx_sstudent_school` (`school_id`),
    KEY `idx_sstudent_program` (`program_id`),
    KEY `idx_sstudent_batch` (`batch_id`),
    CONSTRAINT `fk_sstudent_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_fee_structures — a school's fee heads
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_fee_structures` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(128) NOT NULL,
    `amount`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `frequency`  ENUM('one-time','monthly','quarterly','term','annual') NOT NULL DEFAULT 'one-time',
    `applies_to` VARCHAR(96)  NULL,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sfeestruct_school` (`school_id`),
    CONSTRAINT `fk_sfeestruct_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_fee_payments — fee collection / dues / receipts
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_fee_payments` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`    INT UNSIGNED NOT NULL,
    `student_id`   INT UNSIGNED NULL,
    `structure_id` INT UNSIGNED NULL,
    `title`        VARCHAR(191) NOT NULL,
    `amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- total due
    `amount_paid`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,   -- collected so far
    `status`       ENUM('pending','partial','paid','waived') NOT NULL DEFAULT 'pending',
    `method`       ENUM('cash','upi','cheque','bank','online','other') NOT NULL DEFAULT 'cash',
    `receipt_no`   VARCHAR(48)  NULL,
    `paid_at`      DATETIME     NULL,
    `note`         VARCHAR(500) NULL,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sfee_receipt` (`receipt_no`),
    KEY `idx_sfee_school` (`school_id`),
    KEY `idx_sfee_student` (`student_id`),
    KEY `idx_sfee_status` (`status`),
    CONSTRAINT `fk_sfee_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sfee_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- school_attendance — per-batch daily attendance
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `school_attendance` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`  INT UNSIGNED NOT NULL,
    `batch_id`   INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `date`       DATE         NOT NULL,
    `status`     ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_satt` (`batch_id`, `student_id`, `date`),
    KEY `idx_satt_school` (`school_id`),
    KEY `idx_satt_date` (`date`),
    CONSTRAINT `fk_satt_school` FOREIGN KEY (`school_id`)
        REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_satt_batch` FOREIGN KEY (`batch_id`)
        REFERENCES `school_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_satt_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings seeds
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('school', 'school_code_prefix',   'SCH', 'text', 'School code prefix'),
    ('school', 'student_code_prefix',  'STU', 'text', 'Student code prefix'),
    ('school', 'school_receipt_prefix','RCP', 'text', 'Fee receipt prefix');

SET FOREIGN_KEY_CHECKS = 1;
