-- =============================================================================
--  EDUSKILL INDIA FOUNDATION — Database Schema
-- =============================================================================
--  Engine : InnoDB (foreign keys, transactions)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
--  Target : MySQL 5.7+ / MariaDB 10.4+
--
--  Import (XAMPP shell):
--    C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
--  or via phpMyAdmin: create db "pwf", Import > schema.sql
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE IF NOT EXISTS `pwf`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE `pwf`;

-- -----------------------------------------------------------------------------
-- settings  (key/value store — powers get_setting())
-- -----------------------------------------------------------------------------
CREATE TABLE `settings` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name`  VARCHAR(64)  NOT NULL DEFAULT 'general',
    `key_name`    VARCHAR(128) NOT NULL,
    `value`       LONGTEXT     NULL,
    `type`        ENUM('text','textarea','number','boolean','json','image','email','url','color') NOT NULL DEFAULT 'text',
    `label`       VARCHAR(191) NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_settings_key` (`key_name`),
    KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- roles
-- -----------------------------------------------------------------------------
CREATE TABLE `roles` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(64)  NOT NULL,
    `slug`        VARCHAR(64)  NOT NULL,
    `description` VARCHAR(255) NULL,
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- permissions
-- -----------------------------------------------------------------------------
CREATE TABLE `permissions` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(96)  NOT NULL,
    `slug`       VARCHAR(96)  NOT NULL,
    `module`     VARCHAR(64)  NOT NULL DEFAULT 'general',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- role_permissions  (many-to-many)
-- -----------------------------------------------------------------------------
CREATE TABLE `role_permissions` (
    `role_id`       INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_rp_permission` (`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`)
        REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id`        INT UNSIGNED NULL,
    `name`           VARCHAR(128) NOT NULL,
    `email`          VARCHAR(191) NOT NULL,
    `password`       VARCHAR(255) NOT NULL,
    `phone`          VARCHAR(32)  NULL,
    `avatar`         VARCHAR(255) NULL,
    `bio`            TEXT         NULL,
    `status`         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `remember_token` VARCHAR(100) NULL,
    `last_login_at`  TIMESTAMP    NULL,
    `last_login_ip`  VARCHAR(45)  NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role_id`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`)
        REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- menus  (self-referencing tree; header/footer navigation)
-- -----------------------------------------------------------------------------
CREATE TABLE `menus` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`  INT UNSIGNED NULL,
    `title`      VARCHAR(128) NOT NULL,
    `url`        VARCHAR(255) NOT NULL DEFAULT '#',
    `icon`       VARCHAR(64)  NULL,
    `location`   ENUM('header','footer','both') NOT NULL DEFAULT 'header',
    `target`     ENUM('_self','_blank') NOT NULL DEFAULT '_self',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_menus_parent` (`parent_id`),
    KEY `idx_menus_location` (`location`, `status`),
    CONSTRAINT `fk_menus_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- pages  (generic CMS pages: privacy, terms, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE `pages` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `subtitle`    VARCHAR(255) NULL,
    `content`     LONGTEXT     NULL,
    `banner_image` VARCHAR(255) NULL,
    `template`    VARCHAR(64)  NOT NULL DEFAULT 'default',
    `status`      ENUM('draft','published') NOT NULL DEFAULT 'published',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`  TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pages_slug` (`slug`),
    KEY `idx_pages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- hero_slides  (homepage slider)
-- -----------------------------------------------------------------------------
CREATE TABLE `hero_slides` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`        VARCHAR(191) NOT NULL,
    `subtitle`     VARCHAR(255) NULL,
    `description`  TEXT         NULL,
    `image`        VARCHAR(255) NOT NULL,
    `button_text`  VARCHAR(64)  NULL,
    `button_url`   VARCHAR(255) NULL,
    `button2_text` VARCHAR(64)  NULL,
    `button2_url`  VARCHAR(255) NULL,
    `text_align`   ENUM('left','center','right') NOT NULL DEFAULT 'left',
    `sort_order`   INT          NOT NULL DEFAULT 0,
    `status`       TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hero_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- programs
-- -----------------------------------------------------------------------------
CREATE TABLE `programs` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`             VARCHAR(191) NOT NULL,
    `slug`              VARCHAR(191) NOT NULL,
    `short_description` VARCHAR(500) NULL,
    `description`       LONGTEXT     NULL,
    `icon`              VARCHAR(64)  NULL,
    `image`             VARCHAR(255) NULL,
    `color`             VARCHAR(20)  NULL DEFAULT '#2563eb',
    `is_featured`       TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`        INT          NOT NULL DEFAULT 0,
    `status`            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`        TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_programs_slug` (`slug`),
    KEY `idx_programs_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- projects
-- -----------------------------------------------------------------------------
CREATE TABLE `projects` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_id`    INT UNSIGNED NULL,
    `title`         VARCHAR(191) NOT NULL,
    `slug`          VARCHAR(191) NOT NULL,
    `summary`       VARCHAR(500) NULL,
    `description`   LONGTEXT     NULL,
    `image`         VARCHAR(255) NULL,
    `location`      VARCHAR(191) NULL,
    `start_date`    DATE         NULL,
    `end_date`      DATE         NULL,
    `budget`        DECIMAL(14,2) NULL,
    `beneficiaries` INT UNSIGNED NULL,
    `progress`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status`        ENUM('ongoing','completed','upcoming','paused') NOT NULL DEFAULT 'ongoing',
    `is_featured`   TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`    INT          NOT NULL DEFAULT 0,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_projects_slug` (`slug`),
    KEY `idx_projects_program` (`program_id`),
    KEY `idx_projects_status` (`status`),
    CONSTRAINT `fk_projects_program` FOREIGN KEY (`program_id`)
        REFERENCES `programs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- blog_categories
-- -----------------------------------------------------------------------------
CREATE TABLE `blog_categories` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(128) NOT NULL,
    `slug`        VARCHAR(128) NOT NULL,
    `description` VARCHAR(255) NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_blogcat_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- blog_tags
-- -----------------------------------------------------------------------------
CREATE TABLE `blog_tags` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(96)  NOT NULL,
    `slug`       VARCHAR(96)  NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_blogtag_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- blogs
-- -----------------------------------------------------------------------------
CREATE TABLE `blogs` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id`    INT UNSIGNED NULL,
    `author_id`      INT UNSIGNED NULL,
    `title`          VARCHAR(191) NOT NULL,
    `slug`           VARCHAR(191) NOT NULL,
    `excerpt`        VARCHAR(500) NULL,
    `content`        LONGTEXT     NULL,
    `featured_image` VARCHAR(255) NULL,
    `views`          INT UNSIGNED NOT NULL DEFAULT 0,
    `is_featured`    TINYINT(1)   NOT NULL DEFAULT 0,
    `reading_time`   SMALLINT UNSIGNED NULL,
    `status`         ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `published_at`   DATETIME     NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`     TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_blogs_slug` (`slug`),
    KEY `idx_blogs_category` (`category_id`),
    KEY `idx_blogs_author` (`author_id`),
    KEY `idx_blogs_status` (`status`, `published_at`),
    CONSTRAINT `fk_blogs_category` FOREIGN KEY (`category_id`)
        REFERENCES `blog_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_blogs_author` FOREIGN KEY (`author_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- blog_tag_map  (many-to-many)
-- -----------------------------------------------------------------------------
CREATE TABLE `blog_tag_map` (
    `blog_id` INT UNSIGNED NOT NULL,
    `tag_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`blog_id`, `tag_id`),
    KEY `idx_btm_tag` (`tag_id`),
    CONSTRAINT `fk_btm_blog` FOREIGN KEY (`blog_id`)
        REFERENCES `blogs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_btm_tag` FOREIGN KEY (`tag_id`)
        REFERENCES `blog_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- blog_comments  (threaded, moderated)
-- -----------------------------------------------------------------------------
CREATE TABLE `blog_comments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `blog_id`    INT UNSIGNED NOT NULL,
    `parent_id`  INT UNSIGNED NULL,
    `name`       VARCHAR(128) NOT NULL,
    `email`      VARCHAR(191) NOT NULL,
    `website`    VARCHAR(191) NULL,
    `comment`    TEXT         NOT NULL,
    `status`     ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
    `ip_address` VARCHAR(45)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_blog` (`blog_id`, `status`),
    KEY `idx_comments_parent` (`parent_id`),
    CONSTRAINT `fk_comments_blog` FOREIGN KEY (`blog_id`)
        REFERENCES `blogs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`)
        REFERENCES `blog_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- gallery_albums
-- -----------------------------------------------------------------------------
CREATE TABLE `gallery_albums` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `description` TEXT         NULL,
    `cover_image` VARCHAR(255) NULL,
    `event_date`  DATE         NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_album_slug` (`slug`),
    KEY `idx_album_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- gallery_media
-- -----------------------------------------------------------------------------
CREATE TABLE `gallery_media` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `album_id`   INT UNSIGNED NOT NULL,
    `title`      VARCHAR(191) NULL,
    `file_path`  VARCHAR(255) NOT NULL,
    `type`       ENUM('image','video') NOT NULL DEFAULT 'image',
    `caption`    VARCHAR(255) NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_media_album` (`album_id`, `sort_order`),
    CONSTRAINT `fk_media_album` FOREIGN KEY (`album_id`)
        REFERENCES `gallery_albums` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- videos
-- -----------------------------------------------------------------------------
CREATE TABLE `videos` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `description` TEXT         NULL,
    `youtube_id`  VARCHAR(32)  NULL,
    `video_url`   VARCHAR(255) NULL,
    `thumbnail`   VARCHAR(255) NULL,
    `category`    VARCHAR(64)  NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_videos_slug` (`slug`),
    KEY `idx_videos_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- achievements  (impact counters)
-- -----------------------------------------------------------------------------
CREATE TABLE `achievements` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(128) NOT NULL,
    `description` VARCHAR(255) NULL,
    `icon`        VARCHAR(64)  NULL,
    `value`       INT UNSIGNED NOT NULL DEFAULT 0,
    `suffix`      VARCHAR(16)  NULL,
    `prefix`      VARCHAR(16)  NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_achievements_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- certificates
-- -----------------------------------------------------------------------------
CREATE TABLE `certificates` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `description` TEXT         NULL,
    `image`       VARCHAR(255) NOT NULL,
    `issued_by`   VARCHAR(191) NULL,
    `issue_date`  DATE         NULL,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_certificates_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- events
-- -----------------------------------------------------------------------------
CREATE TABLE `events` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`                 VARCHAR(191) NOT NULL,
    `slug`                  VARCHAR(191) NOT NULL,
    `description`           LONGTEXT     NULL,
    `excerpt`               VARCHAR(500) NULL,
    `image`                 VARCHAR(255) NULL,
    `location`              VARCHAR(191) NULL,
    `venue`                 VARCHAR(191) NULL,
    `start_datetime`        DATETIME     NOT NULL,
    `end_datetime`          DATETIME     NULL,
    `capacity`              INT UNSIGNED NULL,
    `registration_required` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_featured`           TINYINT(1)   NOT NULL DEFAULT 0,
    `status`                ENUM('draft','published','cancelled') NOT NULL DEFAULT 'published',
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`            TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_slug` (`slug`),
    KEY `idx_events_start` (`start_datetime`),
    KEY `idx_events_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- event_registrations
-- -----------------------------------------------------------------------------
CREATE TABLE `event_registrations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id`   INT UNSIGNED NOT NULL,
    `name`       VARCHAR(128) NOT NULL,
    `email`      VARCHAR(191) NOT NULL,
    `phone`      VARCHAR(32)  NULL,
    `guests`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `message`    TEXT         NULL,
    `status`     ENUM('pending','confirmed','cancelled','attended') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evreg_event` (`event_id`),
    CONSTRAINT `fk_evreg_event` FOREIGN KEY (`event_id`)
        REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- awareness_calendar  (awareness days/weeks/months)
-- -----------------------------------------------------------------------------
CREATE TABLE `awareness_calendar` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `description` TEXT         NULL,
    `event_date`  DATE         NOT NULL,
    `end_date`    DATE         NULL,
    `category`    VARCHAR(64)  NULL,
    `color`       VARCHAR(20)  NULL DEFAULT '#2563eb',
    `is_recurring` TINYINT(1)  NOT NULL DEFAULT 1,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_awareness_date` (`event_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- team_members
-- -----------------------------------------------------------------------------
CREATE TABLE `team_members` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(128) NOT NULL,
    `slug`        VARCHAR(128) NOT NULL,
    `designation` VARCHAR(128) NULL,
    `department`  VARCHAR(128) NULL,
    `bio`         TEXT         NULL,
    `photo`       VARCHAR(255) NULL,
    `email`       VARCHAR(191) NULL,
    `phone`       VARCHAR(32)  NULL,
    `socials`     JSON         NULL,
    `is_leadership` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_team_slug` (`slug`),
    KEY `idx_team_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- testimonials
-- -----------------------------------------------------------------------------
CREATE TABLE `testimonials` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(128) NOT NULL,
    `designation` VARCHAR(128) NULL,
    `photo`       VARCHAR(255) NULL,
    `message`     TEXT         NOT NULL,
    `rating`      TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_testimonials_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- partners
-- -----------------------------------------------------------------------------
CREATE TABLE `partners` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(191) NOT NULL,
    `logo`       VARCHAR(255) NULL,
    `website`    VARCHAR(255) NULL,
    `description` VARCHAR(255) NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_partners_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- sponsors
-- -----------------------------------------------------------------------------
CREATE TABLE `sponsors` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(191) NOT NULL,
    `logo`       VARCHAR(255) NULL,
    `website`    VARCHAR(255) NULL,
    `tier`       ENUM('platinum','gold','silver','bronze','partner') NOT NULL DEFAULT 'partner',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_sponsors_status` (`status`, `tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- faqs
-- -----------------------------------------------------------------------------
CREATE TABLE `faqs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question`   VARCHAR(255) NOT NULL,
    `answer`     TEXT         NOT NULL,
    `category`   VARCHAR(64)  NOT NULL DEFAULT 'general',
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_faqs_category` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- volunteers  (applications)
-- -----------------------------------------------------------------------------
CREATE TABLE `volunteers` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(128) NOT NULL,
    `email`            VARCHAR(191) NOT NULL,
    `phone`            VARCHAR(32)  NULL,
    `address`          VARCHAR(255) NULL,
    `city`             VARCHAR(96)  NULL,
    `date_of_birth`    DATE         NULL,
    `gender`           ENUM('male','female','other','prefer_not') NULL,
    `occupation`       VARCHAR(128) NULL,
    `area_of_interest` VARCHAR(191) NULL,
    `availability`     VARCHAR(128) NULL,
    `message`          TEXT         NULL,
    `resume`           VARCHAR(255) NULL,
    `status`           ENUM('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_volunteers_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- internships  (applications)
-- -----------------------------------------------------------------------------
CREATE TABLE `internships` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`             VARCHAR(128) NOT NULL,
    `email`            VARCHAR(191) NOT NULL,
    `phone`            VARCHAR(32)  NULL,
    `education`        VARCHAR(191) NULL,
    `institution`      VARCHAR(191) NULL,
    `duration`         VARCHAR(64)  NULL,
    `start_date`       DATE         NULL,
    `area_of_interest` VARCHAR(191) NULL,
    `cover_letter`     TEXT         NULL,
    `resume`           VARCHAR(255) NULL,
    `status`           ENUM('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_internships_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- campaigns  (fundraising)
-- -----------------------------------------------------------------------------
CREATE TABLE `campaigns` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`         VARCHAR(191) NOT NULL,
    `slug`          VARCHAR(191) NOT NULL,
    `short_description` VARCHAR(500) NULL,
    `description`   LONGTEXT     NULL,
    `image`         VARCHAR(255) NULL,
    `goal_amount`   DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `raised_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `start_date`    DATE         NULL,
    `end_date`      DATE         NULL,
    `is_featured`   TINYINT(1)   NOT NULL DEFAULT 0,
    `status`        ENUM('active','completed','paused','draft') NOT NULL DEFAULT 'active',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at`    TIMESTAMP    NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_campaigns_slug` (`slug`),
    KEY `idx_campaigns_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- donations
-- -----------------------------------------------------------------------------
CREATE TABLE `donations` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id`    INT UNSIGNED NULL,
    `donor_name`     VARCHAR(128) NOT NULL,
    `email`          VARCHAR(191) NULL,
    `phone`          VARCHAR(32)  NULL,
    `pan`            VARCHAR(16)  NULL,
    `address`        VARCHAR(255) NULL,
    `amount`         DECIMAL(12,2) NOT NULL,
    `currency`       VARCHAR(8)   NOT NULL DEFAULT 'INR',
    `payment_method` VARCHAR(64)  NULL,
    `transaction_id` VARCHAR(128) NULL,
    `message`        TEXT         NULL,
    `is_anonymous`   TINYINT(1)   NOT NULL DEFAULT 0,
    `status`         ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
    `donated_at`     TIMESTAMP    NULL,
    `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_donations_campaign` (`campaign_id`),
    KEY `idx_donations_status` (`status`),
    CONSTRAINT `fk_donations_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- newsletter_subscribers
-- -----------------------------------------------------------------------------
CREATE TABLE `newsletter_subscribers` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(128) NULL,
    `email`      VARCHAR(191) NOT NULL,
    `token`      VARCHAR(64)  NULL,
    `status`     ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
    `ip_address` VARCHAR(45)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- feedback
-- -----------------------------------------------------------------------------
CREATE TABLE `feedback` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(128) NOT NULL,
    `email`      VARCHAR(191) NULL,
    `subject`    VARCHAR(191) NULL,
    `message`    TEXT         NOT NULL,
    `rating`     TINYINT UNSIGNED NULL,
    `status`     ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
    `ip_address` VARCHAR(45)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_feedback_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- contact_messages
-- -----------------------------------------------------------------------------
CREATE TABLE `contact_messages` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(128) NOT NULL,
    `email`      VARCHAR(191) NOT NULL,
    `phone`      VARCHAR(32)  NULL,
    `subject`    VARCHAR(191) NULL,
    `message`    TEXT         NOT NULL,
    `status`     ENUM('unread','read','replied','archived') NOT NULL DEFAULT 'unread',
    `ip_address` VARCHAR(45)  NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- media_library
-- -----------------------------------------------------------------------------
CREATE TABLE `media_library` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `file_name`   VARCHAR(191) NOT NULL,
    `file_path`   VARCHAR(255) NOT NULL,
    `file_type`   VARCHAR(32)  NULL,
    `mime_type`   VARCHAR(96)  NULL,
    `file_size`   INT UNSIGNED NULL,
    `alt_text`    VARCHAR(255) NULL,
    `folder`      VARCHAR(64)  NOT NULL DEFAULT 'media',
    `uploaded_by` INT UNSIGNED NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_media_uploader` (`uploaded_by`),
    KEY `idx_media_type` (`file_type`),
    CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- documents  (downloads / resources)
-- -----------------------------------------------------------------------------
CREATE TABLE `documents` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NOT NULL,
    `slug`        VARCHAR(191) NOT NULL,
    `description` TEXT         NULL,
    `file_path`   VARCHAR(255) NOT NULL,
    `file_type`   VARCHAR(32)  NULL,
    `file_size`   INT UNSIGNED NULL,
    `category`    VARCHAR(64)  NOT NULL DEFAULT 'general',
    `downloads`   INT UNSIGNED NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_documents_slug` (`slug`),
    KEY `idx_documents_category` (`category`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- seo_meta  (per-page SEO overrides; page_key like 'home','about','blog:my-slug')
-- -----------------------------------------------------------------------------
CREATE TABLE `seo_meta` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_key`         VARCHAR(191) NOT NULL,
    `meta_title`       VARCHAR(191) NULL,
    `meta_description` VARCHAR(320) NULL,
    `meta_keywords`    VARCHAR(320) NULL,
    `og_title`         VARCHAR(191) NULL,
    `og_description`   VARCHAR(320) NULL,
    `og_image`         VARCHAR(255) NULL,
    `canonical`        VARCHAR(255) NULL,
    `robots`           VARCHAR(64)  NOT NULL DEFAULT 'index,follow',
    `schema_json`      LONGTEXT     NULL,
    `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_seo_pagekey` (`page_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- redirects
-- -----------------------------------------------------------------------------
CREATE TABLE `redirects` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `source_url`  VARCHAR(255) NOT NULL,
    `target_url`  VARCHAR(255) NOT NULL,
    `status_code` SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    `hits`        INT UNSIGNED NOT NULL DEFAULT 0,
    `status`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_redirects_source` (`source_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- activity_logs
-- -----------------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED NULL,
    `action`      VARCHAR(64)  NOT NULL,
    `module`      VARCHAR(64)  NULL,
    `description` VARCHAR(500) NULL,
    `ip_address`  VARCHAR(45)  NULL,
    `user_agent`  VARCHAR(255) NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_created` (`created_at`),
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- login_attempts  (throttling)
-- -----------------------------------------------------------------------------
CREATE TABLE `login_attempts` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email`      VARCHAR(191) NULL,
    `ip_address` VARCHAR(45)  NOT NULL,
    `success`    TINYINT(1)   NOT NULL DEFAULT 0,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attempts_email` (`email`, `created_at`),
    KEY `idx_attempts_ip` (`ip_address`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- email_templates  (used by includes/mailer.php)
-- -----------------------------------------------------------------------------
CREATE TABLE `email_templates` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(128) NOT NULL,
    `slug`       VARCHAR(128) NOT NULL,
    `subject`    VARCHAR(191) NOT NULL,
    `body`       LONGTEXT     NOT NULL,
    `variables`  VARCHAR(500) NULL,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_emailtpl_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- social_links
-- -----------------------------------------------------------------------------
CREATE TABLE `social_links` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `platform`   VARCHAR(64)  NOT NULL,
    `url`        VARCHAR(255) NOT NULL,
    `icon`       VARCHAR(64)  NULL,
    `sort_order` INT          NOT NULL DEFAULT 0,
    `status`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_social_status` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- popups  (marketing popup manager)
-- -----------------------------------------------------------------------------
CREATE TABLE `popups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(191) NULL,
    `content`     TEXT         NULL,
    `image`       VARCHAR(255) NULL,
    `button_text` VARCHAR(64)  NULL,
    `button_url`  VARCHAR(255) NULL,
    `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 3,
    `start_date`  DATE         NULL,
    `end_date`    DATE         NULL,
    `status`      TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- announcements  (top announcement bar)
-- -----------------------------------------------------------------------------
CREATE TABLE `announcements` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `message`    VARCHAR(500) NOT NULL,
    `link_text`  VARCHAR(64)  NULL,
    `link_url`   VARCHAR(255) NULL,
    `bg_color`   VARCHAR(20)  NULL DEFAULT '#111827',
    `text_color` VARCHAR(20)  NULL DEFAULT '#ffffff',
    `start_date` DATE         NULL,
    `end_date`   DATE         NULL,
    `status`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  End of schema
-- =============================================================================
