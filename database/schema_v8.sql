-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Schema v8 (Student Management + LMS)
--  Extends school_students into full student profiles and adds admissions,
--  documents, certificates, marksheets, assignments, notices, and a complete
--  LMS (courses, lessons, resources, batches, enrolments, progress, exams,
--  question bank, attempts, live sessions).
--
--  Run AFTER schema_v7.sql:
--    C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v8.sql
--  Idempotent.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- school_students — full student profile fields + portal link + ID-card QR
-- -----------------------------------------------------------------------------
ALTER TABLE `school_students`
    ADD COLUMN IF NOT EXISTS `member_id`       INT UNSIGNED NULL AFTER `school_id`,
    ADD COLUMN IF NOT EXISTS `admission_no`    VARCHAR(48)  NULL AFTER `student_code`,
    ADD COLUMN IF NOT EXISTS `photo`           VARCHAR(255) NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `qr_token`        VARCHAR(64)  NULL AFTER `photo`,
    ADD COLUMN IF NOT EXISTS `section`         VARCHAR(32)  NULL AFTER `grade`,
    ADD COLUMN IF NOT EXISTS `academic_year`   VARCHAR(16)  NULL AFTER `section`,
    ADD COLUMN IF NOT EXISTS `previous_school` VARCHAR(191) NULL AFTER `academic_year`,
    ADD COLUMN IF NOT EXISTS `blood_group`     VARCHAR(8)   NULL AFTER `previous_school`,
    ADD COLUMN IF NOT EXISTS `category`        VARCHAR(32)  NULL AFTER `blood_group`,
    ADD COLUMN IF NOT EXISTS `father_name`     VARCHAR(128) NULL AFTER `guardian_name`,
    ADD COLUMN IF NOT EXISTS `mother_name`     VARCHAR(128) NULL AFTER `father_name`,
    ADD COLUMN IF NOT EXISTS `guardian_phone`  VARCHAR(32)  NULL AFTER `mother_name`,
    ADD COLUMN IF NOT EXISTS `guardian_email`  VARCHAR(191) NULL AFTER `guardian_phone`,
    ADD COLUMN IF NOT EXISTS `city`            VARCHAR(96)  NULL AFTER `address`,
    ADD COLUMN IF NOT EXISTS `state`           VARCHAR(96)  NULL AFTER `city`,
    ADD COLUMN IF NOT EXISTS `pincode`         VARCHAR(12)  NULL AFTER `state`;

ALTER TABLE `school_students`
    ADD UNIQUE INDEX IF NOT EXISTS `uq_sstudent_qr` (`qr_token`),
    ADD INDEX IF NOT EXISTS `idx_sstudent_member` (`member_id`);

-- school_fee_payments — discount + scholarship deduction
ALTER TABLE `school_fee_payments`
    ADD COLUMN IF NOT EXISTS `discount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `amount`,
    ADD COLUMN IF NOT EXISTS `scholarship` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `discount`;

-- -----------------------------------------------------------------------------
-- admissions — public applications → admin review → enrolment
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admissions` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `application_no`  VARCHAR(48)  NULL,
    `school_id`       INT UNSIGNED NULL,
    `name`            VARCHAR(128) NOT NULL,
    `dob`             DATE         NULL,
    `gender`          ENUM('male','female','other') NULL,
    `father_name`     VARCHAR(128) NULL,
    `mother_name`     VARCHAR(128) NULL,
    `guardian_name`   VARCHAR(128) NULL,
    `phone`           VARCHAR(32)  NULL,
    `email`           VARCHAR(191) NULL,
    `address`         VARCHAR(255) NULL,
    `city`            VARCHAR(96)  NULL,
    `state`           VARCHAR(96)  NULL,
    `pincode`         VARCHAR(12)  NULL,
    `grade_applying`  VARCHAR(48)  NULL,
    `previous_school` VARCHAR(191) NULL,
    `program_id`      INT UNSIGNED NULL,
    `message`         TEXT         NULL,
    `documents`       LONGTEXT     NULL,   -- JSON [{title,path}]
    `status`          ENUM('new','under_review','approved','rejected') NOT NULL DEFAULT 'new',
    `review_note`     VARCHAR(500) NULL,
    `reviewed_by`     INT UNSIGNED NULL,
    `reviewed_at`     DATETIME     NULL,
    `student_id`      INT UNSIGNED NULL,   -- set once enrolled
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_adm_no` (`application_no`),
    KEY `idx_adm_status` (`status`),
    KEY `idx_adm_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- student_documents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_documents` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `title`      VARCHAR(128) NOT NULL,
    `file_path`  VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sdoc_student` (`student_id`),
    CONSTRAINT `fk_sdoc_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- student_certificates (completion / participation, with QR verification)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_certificates` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`      INT UNSIGNED NOT NULL,
    `course_id`       INT UNSIGNED NULL,
    `serial`          VARCHAR(48)  NULL,
    `type`            ENUM('completion','participation','merit','achievement') NOT NULL DEFAULT 'completion',
    `title`           VARCHAR(191) NOT NULL,
    `description`     TEXT         NULL,
    `template`        VARCHAR(48)  NOT NULL DEFAULT 'classic',
    `issue_date`      DATE         NULL,
    `signed_by`       VARCHAR(128) NULL,
    `signature_image` VARCHAR(255) NULL,
    `qr_token`        VARCHAR(64)  NULL,
    `status`          ENUM('valid','revoked') NOT NULL DEFAULT 'valid',
    `created_by`      INT UNSIGNED NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_scert_serial` (`serial`),
    UNIQUE KEY `uq_scert_qr` (`qr_token`),
    KEY `idx_scert_student` (`student_id`),
    CONSTRAINT `fk_scert_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- marksheets (+ subject rows)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `marksheets` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id`     INT UNSIGNED NOT NULL,
    `title`          VARCHAR(191) NOT NULL,
    `term`           VARCHAR(48)  NULL,
    `academic_year`  VARCHAR(16)  NULL,
    `exam_id`        INT UNSIGNED NULL,
    `max_total`      DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `obtained_total` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `percentage`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `grade`          VARCHAR(8)   NULL,
    `rank`           INT UNSIGNED NULL,
    `result`         ENUM('pass','fail','pending') NOT NULL DEFAULT 'pending',
    `remarks`        VARCHAR(500) NULL,
    `serial`         VARCHAR(48)  NULL,
    `qr_token`       VARCHAR(64)  NULL,
    `published`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ms_serial` (`serial`),
    UNIQUE KEY `uq_ms_qr` (`qr_token`),
    KEY `idx_ms_student` (`student_id`),
    CONSTRAINT `fk_ms_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `marksheet_subjects` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `marksheet_id`   INT UNSIGNED NOT NULL,
    `subject`        VARCHAR(96)  NOT NULL,
    `max_marks`      DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    `obtained_marks` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `grade`          VARCHAR(8)   NULL,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_mss_ms` (`marksheet_id`),
    CONSTRAINT `fk_mss_ms` FOREIGN KEY (`marksheet_id`)
        REFERENCES `marksheets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- assignments (+ submissions)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `school_id`   INT UNSIGNED NULL,
    `batch_id`    INT UNSIGNED NULL,
    `course_id`   INT UNSIGNED NULL,
    `title`       VARCHAR(191) NOT NULL,
    `description` LONGTEXT     NULL,
    `attachment`  VARCHAR(255) NULL,
    `max_marks`   DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    `due_date`    DATETIME     NULL,
    `status`      ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_by`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_asgn_batch` (`batch_id`),
    KEY `idx_asgn_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignment_submissions` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `assignment_id` INT UNSIGNED NOT NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `file_path`     VARCHAR(255) NULL,
    `text`          LONGTEXT     NULL,
    `submitted_at`  DATETIME     NULL,
    `marks`         DECIMAL(6,2) NULL,
    `feedback`      VARCHAR(1000) NULL,
    `status`        ENUM('submitted','graded','returned','late') NOT NULL DEFAULT 'submitted',
    `graded_by`     INT UNSIGNED NULL,
    `graded_at`     DATETIME     NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sub` (`assignment_id`, `student_id`),
    KEY `idx_sub_student` (`student_id`),
    CONSTRAINT `fk_sub_assignment` FOREIGN KEY (`assignment_id`)
        REFERENCES `assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sub_student` FOREIGN KEY (`student_id`)
        REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- notices (targeted board + email/SMS)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notices` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(191) NOT NULL,
    `body`         LONGTEXT     NULL,
    `audience`     ENUM('all','school','batch','course') NOT NULL DEFAULT 'all',
    `school_id`    INT UNSIGNED NULL,
    `batch_id`     INT UNSIGNED NULL,
    `course_id`    INT UNSIGNED NULL,
    `notify_email` TINYINT(1)   NOT NULL DEFAULT 0,
    `notify_sms`   TINYINT(1)   NOT NULL DEFAULT 0,
    `pinned`       TINYINT(1)   NOT NULL DEFAULT 0,
    `status`       ENUM('draft','published') NOT NULL DEFAULT 'published',
    `published_at` DATETIME     NULL,
    `created_by`   INT UNSIGNED NULL,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notice_aud` (`audience`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  LMS
-- =============================================================================

-- courses
CREATE TABLE IF NOT EXISTS `courses` (
    `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`                VARCHAR(191) NOT NULL,
    `slug`                 VARCHAR(191) NOT NULL,
    `category`             VARCHAR(96)  NULL,
    `thumbnail`            VARCHAR(255) NULL,
    `short_description`    VARCHAR(500) NULL,
    `description`          LONGTEXT     NULL,
    `objectives`           TEXT         NULL,
    `prerequisites`        TEXT         NULL,
    `level`                ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    `language`             VARCHAR(48)  NULL,
    `duration`             VARCHAR(48)  NULL,
    `price`                DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `is_featured`          TINYINT(1)   NOT NULL DEFAULT 0,
    `certificate_enabled`  TINYINT(1)   NOT NULL DEFAULT 1,
    `certificate_template` VARCHAR(48)  NOT NULL DEFAULT 'classic',
    `status`               ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `created_by`           INT UNSIGNED NULL,
    `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_course_slug` (`slug`),
    KEY `idx_course_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_lessons
CREATE TABLE IF NOT EXISTS `course_lessons` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`      INT UNSIGNED NOT NULL,
    `module`         VARCHAR(128) NULL,
    `title`          VARCHAR(191) NOT NULL,
    `type`           ENUM('video','pdf','text','live') NOT NULL DEFAULT 'video',
    `video_provider` ENUM('youtube','vimeo','upload') NULL,
    `video_url`      VARCHAR(500) NULL,
    `video_file`     VARCHAR(255) NULL,
    `content`        LONGTEXT     NULL,
    `pdf_file`       VARCHAR(255) NULL,
    `duration_min`   INT UNSIGNED NULL,
    `is_preview`     TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`     INT          NOT NULL DEFAULT 0,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lesson_course` (`course_id`, `sort_order`),
    CONSTRAINT `fk_lesson_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_resources
CREATE TABLE IF NOT EXISTS `course_resources` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`    INT UNSIGNED NOT NULL,
    `lesson_id`    INT UNSIGNED NULL,
    `title`        VARCHAR(191) NOT NULL,
    `file_path`    VARCHAR(255) NOT NULL,
    `downloadable` TINYINT(1)   NOT NULL DEFAULT 1,
    `watermark`    TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_res_course` (`course_id`),
    CONSTRAINT `fk_res_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_batches
CREATE TABLE IF NOT EXISTS `course_batches` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`  INT UNSIGNED NOT NULL,
    `name`       VARCHAR(128) NOT NULL,
    `start_date` DATE         NULL,
    `end_date`   DATE         NULL,
    `seats`      INT UNSIGNED NULL,
    `status`     ENUM('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_cbatch_course` (`course_id`),
    CONSTRAINT `fk_cbatch_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- course_enrollments
CREATE TABLE IF NOT EXISTS `course_enrollments` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`        INT UNSIGNED NOT NULL,
    `batch_id`         INT UNSIGNED NULL,
    `student_id`       INT UNSIGNED NULL,
    `member_id`        INT UNSIGNED NULL,
    `enrolled_at`      DATETIME     NULL,
    `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `completed_at`     DATETIME     NULL,
    `status`           ENUM('active','completed','dropped') NOT NULL DEFAULT 'active',
    `certificate_id`   INT UNSIGNED NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_enr_course` (`course_id`),
    KEY `idx_enr_student` (`student_id`),
    KEY `idx_enr_member` (`member_id`),
    CONSTRAINT `fk_enr_course` FOREIGN KEY (`course_id`)
        REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- lesson_progress
CREATE TABLE IF NOT EXISTS `lesson_progress` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollment_id`    INT UNSIGNED NOT NULL,
    `lesson_id`        INT UNSIGNED NOT NULL,
    `status`           ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'in_progress',
    `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `last_position`    INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lp` (`enrollment_id`, `lesson_id`),
    CONSTRAINT `fk_lp_enr` FOREIGN KEY (`enrollment_id`)
        REFERENCES `course_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lp_lesson` FOREIGN KEY (`lesson_id`)
        REFERENCES `course_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exams (also used for module quizzes: type = exam|quiz)
CREATE TABLE IF NOT EXISTS `exams` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`      INT UNSIGNED NULL,
    `lesson_id`      INT UNSIGNED NULL,
    `title`          VARCHAR(191) NOT NULL,
    `type`           ENUM('exam','quiz') NOT NULL DEFAULT 'exam',
    `description`    TEXT         NULL,
    `duration_min`   INT UNSIGNED NOT NULL DEFAULT 30,
    `total_marks`    DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `pass_marks`     DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `shuffle`        TINYINT(1)   NOT NULL DEFAULT 0,
    `max_attempts`   INT UNSIGNED NOT NULL DEFAULT 1,
    `available_from` DATETIME     NULL,
    `available_to`   DATETIME     NULL,
    `status`         ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    `created_by`     INT UNSIGNED NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_exam_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exam_questions (question bank)
CREATE TABLE IF NOT EXISTS `exam_questions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id`    INT UNSIGNED NOT NULL,
    `type`       ENUM('mcq','truefalse','short') NOT NULL DEFAULT 'mcq',
    `question`   TEXT         NOT NULL,
    `options`    LONGTEXT     NULL,   -- JSON array of choices
    `correct`    LONGTEXT     NULL,   -- JSON: option index(es), 'true'/'false', or model answer
    `marks`      DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_eq_exam` (`exam_id`, `sort_order`),
    CONSTRAINT `fk_eq_exam` FOREIGN KEY (`exam_id`)
        REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- exam_attempts
CREATE TABLE IF NOT EXISTS `exam_attempts` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_id`      INT UNSIGNED NOT NULL,
    `student_id`   INT UNSIGNED NULL,
    `member_id`    INT UNSIGNED NULL,
    `started_at`   DATETIME     NULL,
    `submitted_at` DATETIME     NULL,
    `answers`      LONGTEXT     NULL,   -- JSON {question_id: answer}
    `score`        DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `total`        DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    `passed`       TINYINT(1)   NULL,
    `auto_graded`  TINYINT(1)   NOT NULL DEFAULT 0,
    `needs_review` TINYINT(1)   NOT NULL DEFAULT 0,
    `status`       ENUM('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_att_exam` (`exam_id`),
    KEY `idx_att_member` (`member_id`),
    CONSTRAINT `fk_att_exam` FOREIGN KEY (`exam_id`)
        REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- live_sessions (Zoom / Google Meet)
CREATE TABLE IF NOT EXISTS `live_sessions` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id`    INT UNSIGNED NULL,
    `batch_id`     INT UNSIGNED NULL,
    `title`        VARCHAR(191) NOT NULL,
    `platform`     ENUM('zoom','meet','other') NOT NULL DEFAULT 'meet',
    `link`         VARCHAR(500) NULL,
    `scheduled_at` DATETIME     NULL,
    `duration_min` INT UNSIGNED NULL,
    `notes`        TEXT         NULL,
    `status`       ENUM('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_live_course` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Settings
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
    ('student', 'admission_prefix',       'ADM',  'text',    'Admission application prefix'),
    ('student', 'certificate_prefix',     'CERT', 'text',    'Certificate serial prefix'),
    ('student', 'marksheet_prefix',       'MS',   'text',    'Marksheet serial prefix'),
    ('student', 'student_portal_enabled', '1',    'boolean', 'Enable the student learning portal');

SET FOREIGN_KEY_CHECKS = 1;
