-- =============================================================================
--  schema_v11.sql — Employee / HR Management (section 5.4)
--  departments, employees, employee_documents, employee_attendance,
--  salary_structures, payslips, leave_requests, performance_reviews.
--  Run AFTER schema_v10.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v11.sql
-- =============================================================================
USE `pwf`;

CREATE TABLE IF NOT EXISTS `departments` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`            VARCHAR(128) NOT NULL,
    `code`            VARCHAR(32)  NULL,
    `description`     VARCHAR(255) NULL,
    `hod_employee_id` INT UNSIGNED NULL,
    `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_dept_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employees` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_code`     VARCHAR(32)  NOT NULL,
    `name`              VARCHAR(128) NOT NULL,
    `email`             VARCHAR(191) NULL,
    `phone`             VARCHAR(32)  NULL,
    `photo`             VARCHAR(255) NULL,
    `designation`       VARCHAR(128) NULL,
    `department_id`     INT UNSIGNED NULL,
    `employment_type`   ENUM('full_time','part_time','contract','intern','volunteer') NOT NULL DEFAULT 'full_time',
    `joining_date`      DATE NULL,
    `date_of_birth`     DATE NULL,
    `gender`            ENUM('male','female','other') NULL,
    `blood_group`       VARCHAR(8)   NULL,
    `address`           VARCHAR(255) NULL,
    `emergency_contact` VARCHAR(64)  NULL,
    `bio`               VARCHAR(500) NULL,
    `status`            ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_emp_code` (`employee_code`),
    KEY `idx_emp_dept` (`department_id`),
    KEY `idx_emp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_documents` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `doc_type`    ENUM('appointment_letter','agreement','id_proof','certificate','resume','other') NOT NULL DEFAULT 'other',
    `title`       VARCHAR(191) NOT NULL,
    `file`        VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_empdoc_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_attendance` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `att_date`    DATE NOT NULL,
    `status`      ENUM('present','absent','late','half_day','leave','holiday') NOT NULL DEFAULT 'present',
    `check_in`    TIME NULL,
    `check_out`   TIME NULL,
    `note`        VARCHAR(191) NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_emp_date` (`employee_id`, `att_date`),
    KEY `idx_att_date` (`att_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_structures` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`       INT UNSIGNED NOT NULL,
    `basic`             DECIMAL(12,2) NOT NULL DEFAULT 0,
    `hra`               DECIMAL(12,2) NOT NULL DEFAULT 0,
    `conveyance`        DECIMAL(12,2) NOT NULL DEFAULT 0,
    `medical`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `special_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0,
    `pf`                DECIMAL(12,2) NOT NULL DEFAULT 0,
    `professional_tax`  DECIMAL(12,2) NOT NULL DEFAULT 0,
    `tds`               DECIMAL(12,2) NOT NULL DEFAULT 0,
    `other_deduction`   DECIMAL(12,2) NOT NULL DEFAULT 0,
    `effective_from`    DATE NULL,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sal_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payslips` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`   INT UNSIGNED NOT NULL,
    `period_month`  TINYINT UNSIGNED NOT NULL,
    `period_year`   SMALLINT UNSIGNED NOT NULL,
    `basic`         DECIMAL(12,2) NOT NULL DEFAULT 0,
    `allowances`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `deductions`    DECIMAL(12,2) NOT NULL DEFAULT 0,
    `gross`         DECIMAL(12,2) NOT NULL DEFAULT 0,
    `net`           DECIMAL(12,2) NOT NULL DEFAULT 0,
    `lop_days`      DECIMAL(5,1) NOT NULL DEFAULT 0,
    `note`          VARCHAR(191) NULL,
    `breakdown`     TEXT NULL,
    `generated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_payslip` (`employee_id`, `period_year`, `period_month`),
    KEY `idx_payslip_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type`  ENUM('casual','sick','earned','maternity','unpaid','other') NOT NULL DEFAULT 'casual',
    `from_date`   DATE NOT NULL,
    `to_date`     DATE NOT NULL,
    `days`        DECIMAL(4,1) NOT NULL DEFAULT 1,
    `reason`      VARCHAR(255) NULL,
    `status`      ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `review_note` VARCHAR(255) NULL,
    `reviewed_by` INT UNSIGNED NULL,
    `reviewed_at` TIMESTAMP NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_leave_emp` (`employee_id`),
    KEY `idx_leave_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `performance_reviews` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `employee_id`  INT UNSIGNED NOT NULL,
    `period_type`  ENUM('monthly','quarterly','half_yearly','annual') NOT NULL DEFAULT 'quarterly',
    `period_label` VARCHAR(64) NOT NULL,
    `review_date`  DATE NULL,
    `rating`       TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `goals`        TEXT NULL,
    `achievements` TEXT NULL,
    `feedback`     TEXT NULL,
    `reviewer`     VARCHAR(128) NULL,
    `status`       ENUM('draft','final') NOT NULL DEFAULT 'final',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_review_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
