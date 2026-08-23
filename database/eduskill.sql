-- ==============================================================================
--  EDUSKILL INDIA FOUNDATION — the single database file
-- ==============================================================================
--
--  This is the ONLY .sql file in the project. It replaces the previous 34
--  files (schema.sql, schema_v2..v30.sql, sample_data.sql, eduskill.sql and
--  eduskill-update.sql), which were an incremental migration history that had
--  to be applied in order and was easy to get wrong.
--
--  IT HAS TWO SECTIONS. WHICH ONE YOU RUN DEPENDS ON YOUR DATABASE.
--
--  ---------------------------------------------------------------------------
--  A BRAND NEW, EMPTY DATABASE  ->  run this whole file.
--  ---------------------------------------------------------------------------
--      Section 1 builds all 130 tables and seeds the content.
--      Section 2 then applies cleanly on top (every statement in it is
--      idempotent), so you do not need to think about it.
--
--  ---------------------------------------------------------------------------
--  YOUR LIVE DATABASE, WITH REAL MEMBERS AND DONATIONS  ->  run SECTION 2 ONLY.
--  ---------------------------------------------------------------------------
--      Scroll to the "SECTION 2" banner, select from there to the end of the
--      file, and run that. Section 2 contains no DROP, no TRUNCATE and no
--      DELETE; it only adds columns that are missing and updates settings.
--
--      DO NOT run Section 1 against a live database. Every table in it starts
--      with DROP TABLE IF EXISTS and you would lose all of your data.
--
--  ---------------------------------------------------------------------------
--  HOW TO IMPORT (Hostinger)
--  ---------------------------------------------------------------------------
--      1. hPanel -> Databases -> phpMyAdmin.
--      2. CLICK INTO your database in the left sidebar first. This file has no
--         USE statement, so it applies to whichever database is selected.
--      3. Import tab -> choose this file -> Go.
--         (For the live-update case, use the SQL tab and paste Section 2.)
--
-- ==============================================================================


##############################################################################
##############################################################################
##############################################################################   SECTION 1  —  FRESH INSTALL ONLY
##############################################################################
##############################################################################   DESTRUCTIVE. Drops and recreates all 130 tables, then seeds content.
##############################################################################   Skip this entire section when updating a database that has real data.
##############################################################################
##############################################################################
##############################################################################

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET @OLD_AUTOCOMMIT = @@AUTOCOMMIT, AUTOCOMMIT = 0;

-- =============================================================================
--  SECTION 1 - TABLE STRUCTURE (all tables)
-- =============================================================================
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `achievements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `value` int(10) unsigned NOT NULL DEFAULT 0,
  `suffix` varchar(16) DEFAULT NULL,
  `prefix` varchar(16) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_achievements_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `module` varchar(64) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `severity` enum('info','notice','warning','critical') NOT NULL DEFAULT 'info',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_created` (`created_at`),
  CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_no` varchar(48) DEFAULT NULL,
  `school_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `father_name` varchar(128) DEFAULT NULL,
  `mother_name` varchar(128) DEFAULT NULL,
  `guardian_name` varchar(128) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `grade_applying` varchar(48) DEFAULT NULL,
  `previous_school` varchar(191) DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `message` text DEFAULT NULL,
  `documents` longtext DEFAULT NULL,
  `status` enum('new','under_review','approved','rejected') NOT NULL DEFAULT 'new',
  `review_note` varchar(500) DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_adm_no` (`application_no`),
  KEY `idx_adm_status` (`status`),
  KEY `idx_adm_school` (`school_id`),
  KEY `idx_adm_student` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message` varchar(500) NOT NULL,
  `link_text` varchar(64) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `bg_color` varchar(20) DEFAULT '#111827',
  `text_color` varchar(20) DEFAULT '#ffffff',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `text` longtext DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `marks` decimal(6,2) DEFAULT NULL,
  `feedback` varchar(1000) DEFAULT NULL,
  `status` enum('submitted','graded','returned','late') NOT NULL DEFAULT 'submitted',
  `graded_by` int(10) unsigned DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub` (`assignment_id`,`student_id`),
  KEY `idx_sub_student` (`student_id`),
  CONSTRAINT `fk_sub_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sub_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned DEFAULT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `course_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` longtext DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `due_date` datetime DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asgn_batch` (`batch_id`),
  KEY `idx_asgn_course` (`course_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `awareness_calendar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `awareness_calendar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `category` varchar(64) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#2563eb',
  `is_recurring` tinyint(1) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_awareness_date` (`event_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(191) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `destination` enum('local','ftp','s3') NOT NULL DEFAULT 'local',
  `status` enum('running','success','failed') NOT NULL DEFAULT 'running',
  `checksum` varchar(64) DEFAULT NULL,
  `message` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_backups_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `slug` varchar(128) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blogcat_slug` (`slug`),
  KEY `idx_blogcat_parent` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_comments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `blog_id` int(10) unsigned NOT NULL,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `website` varchar(191) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','spam') NOT NULL DEFAULT 'pending',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comments_blog` (`blog_id`,`status`),
  KEY `idx_comments_parent` (`parent_id`),
  CONSTRAINT `fk_comments_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `blog_comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_tag_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_tag_map` (
  `blog_id` int(10) unsigned NOT NULL,
  `tag_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`blog_id`,`tag_id`),
  KEY `idx_btm_tag` (`tag_id`),
  CONSTRAINT `fk_btm_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_btm_tag` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blog_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blog_tags` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(96) NOT NULL,
  `slug` varchar(96) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blogtag_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `blogs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blogs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int(10) unsigned DEFAULT NULL,
  `author_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `meta_title` varchar(191) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `views` int(10) unsigned NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_breaking` tinyint(1) NOT NULL DEFAULT 0,
  `is_sticky` tinyint(1) NOT NULL DEFAULT 0,
  `reading_time` smallint(5) unsigned DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_blogs_slug` (`slug`),
  KEY `idx_blogs_category` (`category_id`),
  KEY `idx_blogs_author` (`author_id`),
  KEY `idx_blogs_status` (`status`,`published_at`),
  CONSTRAINT `fk_blogs_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blogs_category` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `type` enum('image','video') NOT NULL DEFAULT 'image',
  `path` varchar(255) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cmedia_campaign` (`campaign_id`,`sort_order`),
  CONSTRAINT `fk_cmedia_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_updates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `title` varchar(191) NOT NULL,
  `body` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cupd_campaign` (`campaign_id`),
  CONSTRAINT `fk_cupd_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaign_volunteers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaign_volunteers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `volunteer_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `task` varchar(191) DEFAULT NULL,
  `status` enum('assigned','active','completed','withdrawn') NOT NULL DEFAULT 'assigned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cvol_campaign` (`campaign_id`),
  CONSTRAINT `fk_cvol_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `category` varchar(96) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `goal_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `milestones` longtext DEFAULT NULL,
  `raised_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `donor_count` int(10) unsigned NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','completed','paused','draft') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaigns_slug` (`slug`),
  KEY `idx_campaigns_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `careers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `careers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `department` varchar(128) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `type` enum('full-time','part-time','contract','internship','volunteer') NOT NULL DEFAULT 'full-time',
  `description` longtext DEFAULT NULL,
  `requirements` longtext DEFAULT NULL,
  `salary_range` varchar(96) DEFAULT NULL,
  `openings` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `deadline` date DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_careers_slug` (`slug`),
  KEY `idx_careers_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) NOT NULL,
  `issued_by` varchar(191) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_certificates_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_list_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_list_members` (
  `list_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`list_id`,`contact_id`),
  KEY `idx_contact` (`contact_id`),
  CONSTRAINT `fk_clm_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clm_list` FOREIGN KEY (`list_id`) REFERENCES `contact_lists` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_lists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_lists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `color` varchar(9) NOT NULL DEFAULT '#2563eb',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `subject` varchar(191) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('unread','read','replied','archived') NOT NULL DEFAULT 'unread',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(96) DEFAULT NULL,
  `last_name` varchar(96) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `company` varchar(128) DEFAULT NULL,
  `job_title` varchar(128) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `status` enum('active','unsubscribed','bounced','spam') NOT NULL DEFAULT 'active',
  `source` varchar(64) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_contacted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contact_email` (`email`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coordinator_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coordinator_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_no` varchar(32) DEFAULT NULL,

  -- ---- 2. Position applied for -------------------------------------------
  `position` enum('panchayat','block','district') NOT NULL DEFAULT 'panchayat',
  `preferred_panchayat` varchar(128) DEFAULT NULL,
  `village_coverage` varchar(255) DEFAULT NULL,
  `preferred_block` varchar(128) DEFAULT NULL,
  `block_district` varchar(128) DEFAULT NULL,
  `preferred_district` varchar(128) DEFAULT NULL,
  `district_state` varchar(128) DEFAULT NULL,

  -- ---- 1. Applicant details ----------------------------------------------
  `name` varchar(128) NOT NULL,
  `guardian_name` varchar(128) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `whatsapp` varchar(32) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `id_proof_no` varchar(255) DEFAULT NULL,
  `id_proof_last4` varchar(8) DEFAULT NULL,
  `current_address` varchar(500) DEFAULT NULL,
  `permanent_address` varchar(500) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `district` varchar(96) DEFAULT NULL,
  `block` varchar(96) DEFAULT NULL,
  `panchayat` varchar(96) DEFAULT NULL,
  `village` varchar(128) DEFAULT NULL,

  -- ---- 3. Educational qualification ---------------------------------------
  `education` text DEFAULT NULL COMMENT 'JSON [{level,board,year,grade}]',
  `computer_skills` varchar(255) DEFAULT NULL,

  -- ---- 4. Work experience --------------------------------------------------
  `experience_years` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `experience_months` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ngo_experience` tinyint(1) NOT NULL DEFAULT 0,
  `ngo_details` text DEFAULT NULL,

  -- ---- 5. Community & field experience -------------------------------------
  `community_experience` tinyint(1) NOT NULL DEFAULT 0,
  `focus_areas` varchar(500) DEFAULT NULL,
  `community_note` text DEFAULT NULL,
  `languages` varchar(191) DEFAULT NULL,

  -- ---- 8. Availability & field mobility --------------------------------------
  `field_visits` tinyint(1) NOT NULL DEFAULT 0,
  `can_travel` tinyint(1) NOT NULL DEFAULT 0,
  `two_wheeler` tinyint(1) NOT NULL DEFAULT 0,
  `has_licence` tinyint(1) NOT NULL DEFAULT 0,
  `work_mode` varchar(32) DEFAULT NULL,
  `expected_honorarium` decimal(10,2) DEFAULT NULL,
  `available_from` date DEFAULT NULL,

  -- ---- 9. Document checklist ------------------------------------------------
  `documents` text DEFAULT NULL COMMENT 'JSON {slot: uploads-relative path}',

  -- ---- 10. Reference details ---------------------------------------------------
  -- A single reference with fixed fields, so these are real columns rather than
  -- JSON: the office rings this person, and a phone number you cannot query is
  -- no use to them.
  `ref_name` varchar(128) DEFAULT NULL,
  `ref_designation` varchar(128) DEFAULT NULL,
  `ref_organization` varchar(191) DEFAULT NULL,
  `ref_mobile` varchar(32) DEFAULT NULL,
  `ref_relationship` varchar(96) DEFAULT NULL,

  -- ---- 11. Applicant declaration --------------------------------------------
  `declared_place` varchar(128) DEFAULT NULL,
  `declared_on` date DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,

  -- ---- For office use only ---------------------------------------------------
  `status` enum('new','under_review','shortlisted','interview','approved','rejected') NOT NULL DEFAULT 'new',
  `docs_verified` tinyint(1) NOT NULL DEFAULT 0,
  `field_verification` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `interview_outcome` enum('','recommended','not_recommended') NOT NULL DEFAULT '',
  `approved_position` varchar(128) DEFAULT NULL,
  `assigned_area` varchar(191) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `coordinator_level` enum('','panchayat','block','district') NOT NULL DEFAULT '',
  `honorarium` decimal(10,2) DEFAULT NULL,
  `approved_by` varchar(128) DEFAULT NULL,
  `approver_designation` varchar(128) DEFAULT NULL,
  `office_notes` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,

  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coordapp_no` (`application_no`),
  KEY `idx_coordapp_status` (`status`),
  KEY `idx_coordapp_position` (`position`),
  KEY `idx_coordapp_place` (`state`,`district`),
  KEY `idx_coordapp_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupon_redemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupon_redemptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `coupon_id` int(10) unsigned NOT NULL,
  `code` varchar(48) NOT NULL,
  `context` enum('course','membership','donation','other') NOT NULL DEFAULT 'other',
  `context_id` int(10) unsigned DEFAULT NULL,
  `user_email` varchar(191) DEFAULT NULL,
  `amount_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_credeem_coupon` (`coupon_id`),
  KEY `idx_credeem_email` (`user_email`),
  CONSTRAINT `fk_credeem_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `coupons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coupons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(48) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `discount_type` enum('percent','fixed','waiver') NOT NULL DEFAULT 'percent',
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `applies_to` enum('all','courses','memberships','donations') NOT NULL DEFAULT 'all',
  `min_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `usage_limit` int(10) unsigned DEFAULT NULL,
  `used_count` int(10) unsigned NOT NULL DEFAULT 0,
  `per_user_limit` int(10) unsigned NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`),
  KEY `idx_coupon_status` (`status`,`applies_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `seats` int(10) unsigned DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cbatch_course` (`course_id`),
  CONSTRAINT `fk_cbatch_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_enrollments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `member_id` int(10) unsigned DEFAULT NULL,
  `enrolled_at` datetime DEFAULT NULL,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('active','completed','dropped') NOT NULL DEFAULT 'active',
  `certificate_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_enr_course` (`course_id`),
  KEY `idx_enr_student` (`student_id`),
  KEY `idx_enr_member` (`member_id`),
  KEY `idx_ce_batch` (`batch_id`),
  CONSTRAINT `fk_enr_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_lessons` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `module` varchar(128) DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `type` enum('video','pdf','text','live') NOT NULL DEFAULT 'video',
  `video_provider` enum('youtube','vimeo','upload') DEFAULT NULL,
  `video_url` varchar(500) DEFAULT NULL,
  `video_file` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `pdf_file` varchar(255) DEFAULT NULL,
  `duration_min` int(10) unsigned DEFAULT NULL,
  `is_preview` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lesson_course` (`course_id`,`sort_order`),
  CONSTRAINT `fk_lesson_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `course_resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_resources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned NOT NULL,
  `lesson_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `downloadable` tinyint(1) NOT NULL DEFAULT 1,
  `watermark` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_res_course` (`course_id`),
  KEY `idx_cr_lesson` (`lesson_id`),
  CONSTRAINT `fk_res_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `category` varchar(96) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `prerequisites` text DEFAULT NULL,
  `level` enum('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
  `language` varchar(48) DEFAULT NULL,
  `duration` varchar(48) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `certificate_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `certificate_template` varchar(48) NOT NULL DEFAULT 'classic',
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_course_slug` (`slug`),
  KEY `idx_course_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `code` varchar(32) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `hod_employee_id` int(10) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dept_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_issued`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_issued` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `doc_no` varchar(64) NOT NULL,
  `category` varchar(32) DEFAULT NULL,
  `doc_type` varchar(64) DEFAULT NULL,
  `recipient_name` varchar(128) DEFAULT NULL,
  `recipient_email` varchar(191) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `qr_token` varchar(48) NOT NULL,
  `status` enum('issued','revoked') NOT NULL DEFAULT 'issued',
  `issued_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docissued_no` (`doc_no`),
  UNIQUE KEY `uq_docissued_token` (`qr_token`),
  KEY `idx_docissued_tpl` (`template_id`),
  KEY `idx_docissued_email` (`recipient_email`),
  CONSTRAINT `fk_docissued_tpl` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_template_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `template_id` int(10) unsigned NOT NULL,
  `body` longtext DEFAULT NULL,
  `terms` longtext DEFAULT NULL,
  `saved_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_docver_tpl` (`template_id`,`created_at`),
  CONSTRAINT `fk_docver_tpl` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'certificate',
  `doc_type` varchar(64) DEFAULT NULL,
  `layout` enum('landscape','portrait','id_horizontal','id_vertical') NOT NULL DEFAULT 'landscape',
  `theme` varchar(32) NOT NULL DEFAULT 'classic',
  `body` longtext DEFAULT NULL,
  `terms_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `terms` longtext DEFAULT NULL,
  `show_qr` tinyint(1) NOT NULL DEFAULT 1,
  `show_seal` tinyint(1) NOT NULL DEFAULT 1,
  `show_signature` tinyint(1) NOT NULL DEFAULT 1,
  `show_logo` tinyint(1) NOT NULL DEFAULT 1,
  `show_watermark` tinyint(1) NOT NULL DEFAULT 0,
  `watermark_text` varchar(64) DEFAULT NULL,
  `number_prefix` varchar(24) NOT NULL DEFAULT 'DOC',
  `number_next` int(10) unsigned NOT NULL DEFAULT 1,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `admin_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `user_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_doctpl_slug` (`slug`),
  KEY `idx_doctpl_cat` (`category`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(32) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `category` varchar(64) NOT NULL DEFAULT 'general',
  `downloads` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documents_slug` (`slug`),
  KEY `idx_documents_category` (`category`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donation_refunds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donation_refunds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `donation_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason` varchar(500) DEFAULT NULL,
  `gateway` varchar(32) DEFAULT NULL,
  `gateway_refund_id` varchar(96) DEFAULT NULL,
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dref_donation` (`donation_id`),
  CONSTRAINT `fk_dref_donation` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donation_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donation_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned DEFAULT NULL,
  `donor_name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `frequency` enum('monthly','quarterly','annual') NOT NULL DEFAULT 'monthly',
  `gateway` varchar(32) DEFAULT NULL,
  `gateway_sub_id` varchar(96) DEFAULT NULL,
  `status` enum('created','active','paused','cancelled','completed') NOT NULL DEFAULT 'created',
  `next_charge_at` date DEFAULT NULL,
  `charges_done` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dsub_status` (`status`),
  KEY `idx_dsub_gw` (`gateway_sub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `donations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `donations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned DEFAULT NULL,
  `donor_name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `pan` varchar(16) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `payment_method` varchar(64) DEFAULT NULL,
  `gateway` varchar(32) DEFAULT NULL,
  `gateway_order_id` varchar(96) DEFAULT NULL,
  `gateway_payment_id` varchar(96) DEFAULT NULL,
  `subscription_id` int(10) unsigned DEFAULT NULL,
  `transaction_id` varchar(128) DEFAULT NULL,
  `receipt_no` varchar(48) DEFAULT NULL,
  `receipt_sent` tinyint(1) NOT NULL DEFAULT 0,
  `message` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `refunded_at` datetime DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT NULL,
  `donated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_don_receipt` (`receipt_no`),
  KEY `idx_donations_campaign` (`campaign_id`),
  KEY `idx_donations_status` (`status`),
  KEY `idx_don_gwpay` (`gateway_payment_id`),
  KEY `idx_don_sub` (`subscription_id`),
  KEY `idx_don_gateway_order` (`gateway_order_id`),
  KEY `idx_don_country` (`country_iso`),
  CONSTRAINT `fk_donations_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `reply_to` varchar(191) DEFAULT NULL,
  `signature_id` int(10) unsigned DEFAULT NULL,
  `smtp_host` varchar(191) DEFAULT NULL,
  `smtp_port` smallint(5) unsigned DEFAULT 587,
  `smtp_secure` enum('none','tls','ssl') NOT NULL DEFAULT 'tls',
  `smtp_user` varchar(191) DEFAULT NULL,
  `smtp_pass` varchar(512) DEFAULT NULL,
  `imap_host` varchar(191) DEFAULT NULL,
  `imap_port` smallint(5) unsigned DEFAULT 993,
  `imap_secure` enum('none','tls','ssl') NOT NULL DEFAULT 'ssl',
  `imap_user` varchar(191) DEFAULT NULL,
  `imap_pass` varchar(512) DEFAULT NULL,
  `pop3_host` varchar(191) DEFAULT NULL,
  `pop3_port` smallint(5) unsigned DEFAULT 995,
  `pop3_secure` enum('none','tls','ssl') NOT NULL DEFAULT 'ssl',
  `pop3_user` varchar(191) DEFAULT NULL,
  `pop3_pass` varchar(512) DEFAULT NULL,
  `dkim_selector` varchar(64) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_sync_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_default` (`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_automations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_automations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `trigger_key` varchar(48) NOT NULL,
  `name` varchar(128) NOT NULL,
  `subject` varchar(191) NOT NULL,
  `body` longtext DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `offset_days` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ea_trigger` (`trigger_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_campaign_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_campaign_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` int(10) unsigned NOT NULL,
  `subscriber_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `variant` char(1) NOT NULL DEFAULT 'A',
  `token` varchar(48) NOT NULL,
  `status` enum('queued','sent','failed','opened','clicked','bounced','unsubscribed') NOT NULL DEFAULT 'queued',
  `sent_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ecr_token` (`token`),
  KEY `idx_ecr_campaign` (`campaign_id`,`status`),
  KEY `idx_ecr_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_campaigns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `subject` varchar(191) NOT NULL,
  `subject_b` varchar(191) DEFAULT NULL,
  `ab_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `from_name` varchar(128) DEFAULT NULL,
  `from_email` varchar(191) DEFAULT NULL,
  `smtp_profile_id` int(10) unsigned DEFAULT NULL,
  `template_id` int(10) unsigned DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `segment_status` varchar(24) NOT NULL DEFAULT 'subscribed',
  `segment_tag` varchar(128) DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','paused') NOT NULL DEFAULT 'draft',
  `scheduled_at` datetime DEFAULT NULL,
  `total` int(10) unsigned NOT NULL DEFAULT 0,
  `sent_count` int(10) unsigned NOT NULL DEFAULT 0,
  `open_count` int(10) unsigned NOT NULL DEFAULT 0,
  `click_count` int(10) unsigned NOT NULL DEFAULT 0,
  `fail_count` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ec_status` (`status`,`scheduled_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` bigint(20) unsigned DEFAULT NULL,
  `campaign_id` int(10) unsigned DEFAULT NULL,
  `recipient` varchar(191) DEFAULT NULL,
  `event_type` enum('sent','delivered','open','click','bounce','spam','unsubscribe','fail') NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `device` varchar(32) DEFAULT NULL,
  `os` varchar(48) DEFAULT NULL,
  `browser` varchar(48) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ev_type` (`event_type`,`created_at`),
  KEY `idx_ev_campaign` (`campaign_id`),
  KEY `idx_ev_message` (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_labels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `color` varchar(9) NOT NULL DEFAULT '#6366f1',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_label_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_message_labels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_message_labels` (
  `message_id` bigint(20) unsigned NOT NULL,
  `label_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`message_id`,`label_id`),
  KEY `idx_label` (`label_id`),
  CONSTRAINT `fk_eml_lbl` FOREIGN KEY (`label_id`) REFERENCES `email_labels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eml_msg` FOREIGN KEY (`message_id`) REFERENCES `email_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned DEFAULT NULL,
  `thread_id` varchar(64) DEFAULT NULL,
  `message_uid` varchar(191) DEFAULT NULL,
  `in_reply_to` varchar(191) DEFAULT NULL,
  `direction` enum('incoming','outgoing') NOT NULL DEFAULT 'outgoing',
  `folder` enum('inbox','sent','drafts','outbox','scheduled','trash','spam','archive') NOT NULL DEFAULT 'drafts',
  `from_name` varchar(128) DEFAULT NULL,
  `from_email` varchar(191) DEFAULT NULL,
  `to_email` text DEFAULT NULL,
  `to_name` varchar(191) DEFAULT NULL,
  `cc` text DEFAULT NULL,
  `bcc` text DEFAULT NULL,
  `reply_to` varchar(191) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `preview` varchar(255) DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `body_text` longtext DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `is_read` tinyint(1) NOT NULL DEFAULT 1,
  `is_starred` tinyint(1) NOT NULL DEFAULT 0,
  `is_important` tinyint(1) NOT NULL DEFAULT 0,
  `read_receipt` tinyint(1) NOT NULL DEFAULT 0,
  `has_attachments` tinyint(1) NOT NULL DEFAULT 0,
  `attachments` longtext DEFAULT NULL,
  `open_token` varchar(48) DEFAULT NULL,
  `open_count` int(10) unsigned NOT NULL DEFAULT 0,
  `click_count` int(10) unsigned NOT NULL DEFAULT 0,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `error_text` varchar(255) DEFAULT NULL,
  `related_type` varchar(48) DEFAULT NULL,
  `related_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_folder` (`folder`,`created_at`),
  KEY `idx_thread` (`thread_id`),
  KEY `idx_flags` (`is_starred`,`is_important`,`is_read`),
  KEY `idx_dir` (`direction`),
  KEY `idx_sched` (`folder`,`scheduled_at`),
  KEY `idx_uid` (`message_uid`),
  KEY `idx_deleted` (`deleted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_signatures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_smtp_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_smtp_profiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'custom',
  `host` varchar(191) NOT NULL,
  `port` smallint(5) unsigned NOT NULL DEFAULT 587,
  `secure` enum('none','tls','ssl') NOT NULL DEFAULT 'tls',
  `username` varchar(191) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `from_email` varchar(191) NOT NULL,
  `from_name` varchar(128) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `category` varchar(48) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(24) DEFAULT NULL,
  `is_premium` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `slug` varchar(128) NOT NULL,
  `subject` varchar(191) NOT NULL,
  `body` longtext NOT NULL,
  `variables` varchar(500) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_emailtpl_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `att_date` date NOT NULL,
  `status` enum('present','absent','late','half_day','leave','holiday') NOT NULL DEFAULT 'present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `note` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_date` (`employee_id`,`att_date`),
  KEY `idx_att_date` (`att_date`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `doc_type` enum('appointment_letter','agreement','id_proof','certificate','resume','other') NOT NULL DEFAULT 'other',
  `title` varchar(191) NOT NULL,
  `file` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empdoc_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_code` varchar(32) NOT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `designation` varchar(128) DEFAULT NULL,
  `department_id` int(10) unsigned DEFAULT NULL,
  `employment_type` enum('full_time','part_time','contract','intern','volunteer') NOT NULL DEFAULT 'full_time',
  `joining_date` date DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `blood_group` varchar(8) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `emergency_contact` varchar(64) DEFAULT NULL,
  `bio` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive','terminated') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_emp_code` (`employee_code`),
  KEY `idx_emp_dept` (`department_id`),
  KEY `idx_emp_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `event_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `event_registrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `guests` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `message` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','attended') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_evreg_event` (`event_id`),
  CONSTRAINT `fk_evreg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` longtext DEFAULT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `venue` varchar(191) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `capacity` int(10) unsigned DEFAULT NULL,
  `registration_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','published','cancelled') NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_events_start` (`start_datetime`),
  KEY `idx_events_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `member_id` int(10) unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `answers` longtext DEFAULT NULL,
  `score` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total` decimal(6,2) NOT NULL DEFAULT 0.00,
  `passed` tinyint(1) DEFAULT NULL,
  `auto_graded` tinyint(1) NOT NULL DEFAULT 0,
  `needs_review` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('in_progress','submitted','graded') NOT NULL DEFAULT 'in_progress',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_exam` (`exam_id`),
  KEY `idx_att_member` (`member_id`),
  KEY `idx_ea_student` (`student_id`),
  CONSTRAINT `fk_att_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exam_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exam_questions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `exam_id` int(10) unsigned NOT NULL,
  `type` enum('mcq','truefalse','short') NOT NULL DEFAULT 'mcq',
  `question` text NOT NULL,
  `options` longtext DEFAULT NULL,
  `correct` longtext DEFAULT NULL,
  `marks` decimal(5,2) NOT NULL DEFAULT 1.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_eq_exam` (`exam_id`,`sort_order`),
  CONSTRAINT `fk_eq_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exams` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned DEFAULT NULL,
  `lesson_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `type` enum('exam','quiz') NOT NULL DEFAULT 'exam',
  `description` text DEFAULT NULL,
  `duration_min` int(10) unsigned NOT NULL DEFAULT 30,
  `total_marks` decimal(6,2) NOT NULL DEFAULT 0.00,
  `pass_marks` decimal(6,2) NOT NULL DEFAULT 0.00,
  `shuffle` tinyint(1) NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `available_from` datetime DEFAULT NULL,
  `available_to` datetime DEFAULT NULL,
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_exam_course` (`course_id`),
  KEY `idx_ex_lesson` (`lesson_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(64) NOT NULL DEFAULT 'general',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_faqs_category` (`category`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `subject` varchar(191) DEFAULT NULL,
  `message` text NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('new','reviewed','archived') NOT NULL DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_feedback_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gallery_albums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_albums` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_album_slug` (`slug`),
  KEY `idx_album_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gallery_media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_media` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `album_id` int(10) unsigned NOT NULL,
  `title` varchar(191) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `type` enum('image','video') NOT NULL DEFAULT 'image',
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_media_album` (`album_id`,`sort_order`),
  CONSTRAINT `fk_media_album` FOREIGN KEY (`album_id`) REFERENCES `gallery_albums` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hero_slides`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hero_slides` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `badge_text` varchar(120) DEFAULT NULL,
  `badge_icon` varchar(48) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `highlight` varchar(191) DEFAULT NULL,
  `typing_words` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `accent` varchar(24) DEFAULT NULL,
  `bg_type` varchar(16) NOT NULL DEFAULT 'gradient',
  `bg_from` varchar(24) DEFAULT NULL,
  `bg_to` varchar(24) DEFAULT NULL,
  `bg_angle` int(11) NOT NULL DEFAULT 135,
  `bg_video` varchar(255) DEFAULT NULL,
  `overlay` tinyint(4) NOT NULL DEFAULT 45,
  `image` varchar(255) DEFAULT NULL,
  `button_text` varchar(64) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `btn_style` varchar(16) NOT NULL DEFAULT 'gradient',
  `btn_icon` varchar(48) DEFAULT NULL,
  `button2_text` varchar(64) DEFAULT NULL,
  `button2_url` varchar(255) DEFAULT NULL,
  `btn2_style` varchar(16) NOT NULL DEFAULT 'glass',
  `btn2_icon` varchar(48) DEFAULT NULL,
  `trust_text` varchar(191) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT NULL,
  `rating_count` int(11) DEFAULT NULL,
  `divider` varchar(16) NOT NULL DEFAULT 'none',
  `animate` tinyint(4) NOT NULL DEFAULT 1,
  `text_align` enum('left','center','right') NOT NULL DEFAULT 'left',
  `layout` varchar(16) NOT NULL DEFAULT 'center',
  `hero_image` varchar(255) DEFAULT NULL,
  `height` varchar(12) NOT NULL DEFAULT 'tall',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_hero_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `internships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `education` varchar(191) DEFAULT NULL,
  `institution` varchar(191) DEFAULT NULL,
  `duration` varchar(64) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `area_of_interest` varchar(191) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `status` enum('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_internships_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ip_geo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_geo` (
  `ip` varchar(45) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'unknown',
  `country` varchar(64) DEFAULT NULL,
  `country_code` char(2) DEFAULT NULL,
  `region` varchar(96) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `lat` decimal(9,6) DEFAULT NULL,
  `lon` decimal(9,6) DEFAULT NULL,
  `isp` varchar(128) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ip`),
  KEY `idx_geo_cc` (`country_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `issued_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `issued_certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `certificate_number` varchar(64) NOT NULL,
  `holder_name` varchar(191) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `type` enum('volunteer','internship','participation','appreciation','training') NOT NULL DEFAULT 'participation',
  `program` varchar(191) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `status` enum('valid','revoked') NOT NULL DEFAULT 'valid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_issuedcert_number` (`certificate_number`),
  KEY `idx_issuedcert_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `career_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `qualification` varchar(128) DEFAULT NULL,
  `experience` varchar(64) DEFAULT NULL,
  `current_company` varchar(191) DEFAULT NULL,
  `current_salary` varchar(64) DEFAULT NULL,
  `expected_salary` varchar(64) DEFAULT NULL,
  `notice_period` varchar(64) DEFAULT NULL,
  `position_applied` varchar(191) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `portfolio` varchar(255) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `cover_letter` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','shortlisted','rejected','hired') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_jobapp_career` (`career_id`),
  KEY `idx_ja_country` (`country_iso`),
  CONSTRAINT `fk_jobapp_career` FOREIGN KEY (`career_id`) REFERENCES `careers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kanyadaan_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kanyadaan_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_no` varchar(32) DEFAULT NULL,
  `applicant_name` varchar(128) NOT NULL,
  `relationship` enum('bride','father','mother','guardian','other') NOT NULL DEFAULT 'bride',
  `relationship_other` varchar(96) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `whatsapp` varchar(32) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `district` varchar(96) DEFAULT NULL,
  `block` varchar(96) DEFAULT NULL,
  `panchayat` varchar(96) DEFAULT NULL,
  `village` varchar(128) DEFAULT NULL,
  `bride_name` varchar(128) NOT NULL,
  `bride_dob` date DEFAULT NULL,
  `bride_age` tinyint(3) unsigned DEFAULT NULL,
  `bride_education` varchar(128) DEFAULT NULL,
  `bride_occupation` varchar(128) DEFAULT NULL,
  `bride_id_no` varchar(255) DEFAULT NULL,
  `bride_id_last4` varchar(8) DEFAULT NULL,
  `bank_account` varchar(255) DEFAULT NULL,
  `bank_last4` varchar(8) DEFAULT NULL,
  `bank_name` varchar(128) DEFAULT NULL,
  `bank_ifsc` varchar(16) DEFAULT NULL,
  `marital_status` varchar(48) DEFAULT NULL,
  `groom_name` varchar(128) DEFAULT NULL,
  `groom_dob` date DEFAULT NULL,
  `groom_age` tinyint(3) unsigned DEFAULT NULL,
  `groom_occupation` varchar(128) DEFAULT NULL,
  `groom_address` varchar(500) DEFAULT NULL,
  `marriage_date` date DEFAULT NULL,
  `marriage_location` varchar(191) DEFAULT NULL,
  `marriage_type` varchar(128) DEFAULT NULL,
  `legally_permissible` tinyint(1) NOT NULL DEFAULT 0,
  `family_members` text DEFAULT NULL COMMENT 'JSON [{name,age,relationship,occupation,income}]',
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `annual_income` decimal(12,2) DEFAULT NULL,
  `house_type` enum('','kutcha','semi_pucca','pucca') NOT NULL DEFAULT '',
  `family_size` tinyint(3) unsigned DEFAULT NULL,
  `earning_members` tinyint(3) unsigned DEFAULT NULL,
  `financial_hardship` tinyint(1) NOT NULL DEFAULT 0,
  `hardship_reason` text DEFAULT NULL,
  `existing_debts` text DEFAULT NULL,
  `govt_assistance` tinyint(1) NOT NULL DEFAULT 0,
  `govt_assistance_details` varchar(500) DEFAULT NULL,
  `support_items` varchar(500) DEFAULT NULL COMMENT 'CSV of whitelisted labels',
  `support_justification` text DEFAULT NULL,
  `documents` text DEFAULT NULL COMMENT 'JSON {slot: uploads-relative path}',
  `declared_place` varchar(128) DEFAULT NULL,
  `declared_on` date DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `dowry_declaration` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('new','verifying','verified','approved','rejected','distributed','waitlisted') NOT NULL DEFAULT 'new',
  `docs_verified` tinyint(1) NOT NULL DEFAULT 0,
  `field_verification` enum('pending','scheduled','completed') NOT NULL DEFAULT 'pending',
  `field_verified_by` varchar(128) DEFAULT NULL,
  `field_verified_on` date DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `need_assessment` text DEFAULT NULL,
  `sanctioned_amount` decimal(12,2) DEFAULT NULL,
  `approved_by` varchar(128) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `assigned_coordinator` varchar(128) DEFAULT NULL,
  `distributed_on` date DEFAULT NULL,
  `distribution_notes` text DEFAULT NULL,
  `acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `rejection_reason` varchar(500) DEFAULT NULL,
  `office_notes` text DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kd_no` (`application_no`),
  KEY `idx_kd_status` (`status`),
  KEY `idx_kd_place` (`district`,`block`),
  KEY `idx_kd_marriage` (`marriage_date`),
  KEY `idx_kd_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `leave_type` enum('casual','sick','earned','maternity','unpaid','other') NOT NULL DEFAULT 'casual',
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `days` decimal(4,1) NOT NULL DEFAULT 1.0,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `review_note` varchar(255) DEFAULT NULL,
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_leave_emp` (`employee_id`),
  KEY `idx_leave_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lesson_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lesson_progress` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` int(10) unsigned NOT NULL,
  `lesson_id` int(10) unsigned NOT NULL,
  `status` enum('not_started','in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `last_position` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lp` (`enrollment_id`,`lesson_id`),
  KEY `fk_lp_lesson` (`lesson_id`),
  CONSTRAINT `fk_lp_enr` FOREIGN KEY (`enrollment_id`) REFERENCES `course_enrollments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_lp_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `course_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `live_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `live_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int(10) unsigned DEFAULT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `platform` enum('zoom','meet','other') NOT NULL DEFAULT 'meet',
  `link` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `duration_min` int(10) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('scheduled','live','ended','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_live_course` (`course_id`),
  KEY `idx_ls_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`,`created_at`),
  KEY `idx_attempts_ip` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marksheet_subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marksheet_subjects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `marksheet_id` int(10) unsigned NOT NULL,
  `subject` varchar(96) NOT NULL,
  `max_marks` decimal(6,2) NOT NULL DEFAULT 100.00,
  `obtained_marks` decimal(6,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(8) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mss_ms` (`marksheet_id`),
  CONSTRAINT `fk_mss_ms` FOREIGN KEY (`marksheet_id`) REFERENCES `marksheets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marksheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `marksheets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `title` varchar(191) NOT NULL,
  `term` varchar(48) DEFAULT NULL,
  `academic_year` varchar(16) DEFAULT NULL,
  `exam_id` int(10) unsigned DEFAULT NULL,
  `max_total` decimal(8,2) NOT NULL DEFAULT 0.00,
  `obtained_total` decimal(8,2) NOT NULL DEFAULT 0.00,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `grade` varchar(8) DEFAULT NULL,
  `rank` int(10) unsigned DEFAULT NULL,
  `result` enum('pass','fail','pending') NOT NULL DEFAULT 'pending',
  `remarks` varchar(500) DEFAULT NULL,
  `serial` varchar(48) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ms_serial` (`serial`),
  UNIQUE KEY `uq_ms_qr` (`qr_token`),
  KEY `idx_ms_student` (`student_id`),
  CONSTRAINT `fk_ms_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `media_library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media_library` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(191) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(32) DEFAULT NULL,
  `mime_type` varchar(96) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `folder` varchar(64) NOT NULL DEFAULT 'media',
  `uploaded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_media_uploader` (`uploaded_by`),
  KEY `idx_media_type` (`file_type`),
  CONSTRAINT `fk_media_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(48) NOT NULL,
  `changes` longtext DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_member` (`member_id`,`created_at`),
  CONSTRAINT `fk_audit_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `member_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int(10) unsigned DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `type` enum('verify_email','reset_password','otp') NOT NULL,
  `token` varchar(191) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mt_lookup` (`email`,`type`),
  KEY `idx_mt_member` (`member_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_code` varchar(32) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `oauth_provider` varchar(20) DEFAULT NULL COMMENT 'google|facebook',
  `oauth_uid` varchar(191) DEFAULT NULL COMMENT 'provider account id',
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'member',
  `plan_id` int(10) unsigned DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `occupation` varchar(128) DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `membership_status` enum('none','active','expired','cancelled') NOT NULL DEFAULT 'none',
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL COMMENT 'remote profile picture',
  `document` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','pending','suspended') NOT NULL DEFAULT 'pending',
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_members_email` (`email`),
  UNIQUE KEY `uq_members_code` (`member_code`),
  UNIQUE KEY `uq_members_qr` (`qr_token`),
  UNIQUE KEY `uq_member_oauth` (`oauth_provider`,`oauth_uid`),
  KEY `idx_members_status` (`status`),
  KEY `idx_members_plan` (`plan_id`),
  KEY `idx_members_expiry` (`expiry_date`),
  KEY `idx_members_mstatus` (`membership_status`),
  KEY `idx_mem_country` (`country_iso`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membership_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `occupation` varchar(128) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','approved','rejected') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_memapp_plan` (`plan_id`),
  CONSTRAINT `fk_memapp_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membership_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(96) NOT NULL,
  `slug` varchar(96) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration` varchar(48) NOT NULL DEFAULT 'Annual',
  `duration_days` int(10) unsigned NOT NULL DEFAULT 365,
  `tier_level` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `benefits` text DEFAULT NULL,
  `color` varchar(16) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_planslug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membership_reminders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int(10) unsigned NOT NULL,
  `expiry_date` date NOT NULL,
  `stage` smallint(6) NOT NULL,
  `channel` enum('email','sms') NOT NULL,
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reminder` (`member_id`,`expiry_date`,`stage`,`channel`),
  CONSTRAINT `fk_reminder_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `membership_renewals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membership_renewals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int(10) unsigned NOT NULL,
  `plan_id` int(10) unsigned DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `method` enum('cashfree','offline','manual','other') NOT NULL DEFAULT 'manual',
  `gateway` varchar(32) DEFAULT NULL,
  `order_id` varchar(96) DEFAULT NULL,
  `payment_id` varchar(96) DEFAULT NULL,
  `status` enum('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `applied_at` timestamp NULL DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_renewal_member` (`member_id`),
  KEY `idx_renewal_status` (`status`),
  KEY `idx_renewal_order` (`order_id`),
  KEY `idx_mr_plan` (`plan_id`),
  KEY `idx_mr_payment` (`payment_id`),
  CONSTRAINT `fk_renewal_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(128) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT '#',
  `page_key` varchar(64) DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `mega` tinyint(1) NOT NULL DEFAULT 0,
  `location` enum('header','footer','both') NOT NULL DEFAULT 'header',
  `target` enum('_self','_blank') NOT NULL DEFAULT '_self',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menus_parent` (`parent_id`),
  KEY `idx_menus_location` (`location`,`status`),
  CONSTRAINT `fk_menus_parent` FOREIGN KEY (`parent_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messaging_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messaging_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` enum('sms','whatsapp','push') NOT NULL,
  `provider` varchar(32) DEFAULT NULL,
  `recipient` varchar(191) DEFAULT NULL,
  `template` varchar(128) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` enum('queued','sent','delivered','failed','read') NOT NULL DEFAULT 'queued',
  `provider_msg_id` varchar(128) DEFAULT NULL,
  `error` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_msg_channel` (`channel`,`status`),
  KEY `idx_msg_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscribers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `last_email_at` datetime DEFAULT NULL,
  `token` varchar(64) DEFAULT NULL,
  `status` enum('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `body` longtext DEFAULT NULL,
  `audience` enum('all','school','batch','course') NOT NULL DEFAULT 'all',
  `school_id` int(10) unsigned DEFAULT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `course_id` int(10) unsigned DEFAULT NULL,
  `notify_email` tinyint(1) NOT NULL DEFAULT 0,
  `notify_sms` tinyint(1) NOT NULL DEFAULT 0,
  `pinned` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `published_at` datetime DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notice_aud` (`audience`,`status`),
  KEY `idx_not_batch` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `body` varchar(500) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `icon` varchar(48) NOT NULL DEFAULT 'bell',
  `type` varchar(24) NOT NULL DEFAULT 'info',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`read_at`),
  KEY `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `page_views`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_views` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `page_title` varchar(191) DEFAULT NULL,
  `referrer` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `device` enum('desktop','mobile','tablet','bot') NOT NULL DEFAULT 'desktop',
  `country` varchar(64) DEFAULT NULL,
  `country_code` char(2) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `region` varchar(96) DEFAULT NULL,
  `is_unique` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pv_created` (`created_at`),
  KEY `idx_pv_session` (`session_id`),
  KEY `idx_pv_url` (`url`),
  KEY `idx_pv_country` (`country_code`),
  KEY `idx_pv_ip` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=8898 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `blocks` longtext DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  `template` varchar(64) NOT NULL DEFAULT 'default',
  `status` enum('draft','published') NOT NULL DEFAULT 'published',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `idx_pages_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partner_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `organization` varchar(191) NOT NULL,
  `contact_name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `tier` varchar(64) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','partnered','declined') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partnerapp_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `partners` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_partners_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwhist_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_pwhist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_webhooks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gateway` varchar(24) NOT NULL DEFAULT 'cashfree',
  `event` varchar(64) DEFAULT NULL,
  `order_id` varchar(96) DEFAULT NULL,
  `signature_ok` tinyint(1) NOT NULL DEFAULT 0,
  `handled` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(191) DEFAULT NULL,
  `raw` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwh_order` (`order_id`),
  KEY `idx_pwh_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL,
  `context_type` varchar(32) NOT NULL DEFAULT 'other',
  `context_id` int(10) unsigned DEFAULT NULL,
  `member_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(128) DEFAULT NULL,
  `customer_email` varchar(191) DEFAULT NULL,
  `customer_phone` varchar(32) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'INR',
  `coupon_code` varchar(48) DEFAULT NULL,
  `gateway` varchar(24) NOT NULL DEFAULT 'cashfree',
  `gateway_link_id` varchar(96) DEFAULT NULL,
  `gateway_payment_id` varchar(96) DEFAULT NULL,
  `payment_method` varchar(48) DEFAULT NULL,
  `status` enum('created','pending','paid','failed','cancelled','refunded','expired') NOT NULL DEFAULT 'created',
  `receipt_no` varchar(48) DEFAULT NULL,
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `refunded_at` datetime DEFAULT NULL,
  `purpose` varchar(191) DEFAULT NULL,
  `meta` longtext DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_order` (`order_id`),
  KEY `idx_pay_status` (`status`,`created_at`),
  KEY `idx_pay_context` (`context_type`,`context_id`),
  KEY `idx_pay_member` (`member_id`),
  KEY `idx_pay_email` (`customer_email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payslips` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `period_month` tinyint(3) unsigned NOT NULL,
  `period_year` smallint(5) unsigned NOT NULL,
  `basic` decimal(12,2) NOT NULL DEFAULT 0.00,
  `allowances` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lop_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `note` varchar(191) DEFAULT NULL,
  `breakdown` text DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payslip` (`employee_id`,`period_year`,`period_month`),
  KEY `idx_payslip_emp` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `performance_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `performance_reviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `period_type` enum('monthly','quarterly','half_yearly','annual') NOT NULL DEFAULT 'quarterly',
  `period_label` varchar(64) NOT NULL,
  `review_date` date DEFAULT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 3,
  `goals` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `reviewer` varchar(128) DEFAULT NULL,
  `status` enum('draft','final') NOT NULL DEFAULT 'final',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_review_emp` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(96) NOT NULL,
  `slug` varchar(96) NOT NULL,
  `module` varchar(64) NOT NULL DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `popups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `popups` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `button_text` varchar(64) DEFAULT NULL,
  `button_url` varchar(255) DEFAULT NULL,
  `delay_seconds` int(10) unsigned NOT NULL DEFAULT 3,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#2563eb',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programs_slug` (`slug`),
  KEY `idx_programs_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `program_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(14,2) DEFAULT NULL,
  `beneficiaries` int(10) unsigned DEFAULT NULL,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `status` enum('ongoing','completed','upcoming','paused') NOT NULL DEFAULT 'ongoing',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projects_slug` (`slug`),
  KEY `idx_projects_program` (`program_id`),
  KEY `idx_projects_status` (`status`),
  CONSTRAINT `fk_projects_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redirects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `source_url` varchar(255) NOT NULL,
  `target_url` varchar(255) NOT NULL,
  `status_code` smallint(5) unsigned NOT NULL DEFAULT 301,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirects_source` (`source_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `owner_type` enum('member','volunteer','user','other') NOT NULL DEFAULT 'member',
  `owner_id` int(10) unsigned DEFAULT NULL,
  `owner_name` varchar(128) DEFAULT NULL,
  `code` varchar(32) NOT NULL,
  `clicks` int(10) unsigned NOT NULL DEFAULT 0,
  `signups` int(10) unsigned NOT NULL DEFAULT 0,
  `donations_count` int(10) unsigned NOT NULL DEFAULT 0,
  `donations_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reward_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ref_code` (`code`),
  KEY `idx_ref_owner` (`owner_type`,`owner_id`),
  KEY `idx_rc_owner` (`owner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `referral_conversions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referral_conversions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code_id` int(10) unsigned NOT NULL,
  `type` enum('signup','donation','membership','enrollment') NOT NULL DEFAULT 'signup',
  `ref_name` varchar(128) DEFAULT NULL,
  `ref_email` varchar(191) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `reward` decimal(14,2) NOT NULL DEFAULT 0.00,
  `note` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rc_code` (`code_id`,`type`),
  CONSTRAINT `fk_rc_code` FOREIGN KEY (`code_id`) REFERENCES `referral_codes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `salary_structures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(10) unsigned NOT NULL,
  `basic` decimal(12,2) NOT NULL DEFAULT 0.00,
  `hra` decimal(12,2) NOT NULL DEFAULT 0.00,
  `conveyance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `medical` decimal(12,2) NOT NULL DEFAULT 0.00,
  `special_allowance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `pf` decimal(12,2) NOT NULL DEFAULT 0.00,
  `professional_tax` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tds` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `effective_from` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sal_emp` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schemes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schemes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL COMMENT 'Tagline under the title, e.g. the Hindi strapline',
  `slug` varchar(191) NOT NULL,
  `category` varchar(96) DEFAULT NULL,
  `department` varchar(191) DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `documents_required` text DEFAULT NULL,
  `apply_url` varchar(255) DEFAULT NULL,
  `donate_url` varchar(255) DEFAULT NULL COMMENT 'Support/Donate CTA, beside Apply',
  `image` varchar(255) DEFAULT NULL,
  `brochure` varchar(255) DEFAULT NULL COMMENT 'Primary brochure, uploads-relative',
  `brochures` text DEFAULT NULL COMMENT 'JSON [{label,path,size}] of extra downloads',
  `deadline` date DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `objectives` text DEFAULT NULL COMMENT 'One per line',
  `support_items` text DEFAULT NULL COMMENT 'One per line, "Label | Amount"',
  `budget_note` text DEFAULT NULL,
  `process_steps` text DEFAULT NULL COMMENT 'One step per line, in order',
  `partnership` text DEFAULT NULL COMMENT 'CSR / donor partnership, one per line',
  `transparency` text DEFAULT NULL COMMENT 'One per line',
  `guidelines` text DEFAULT NULL COMMENT 'Rich text ÔÇö positioning, safeguards, disclaimers',
  `faq` text DEFAULT NULL COMMENT 'One per line, "Question :: Answer"',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schemes_slug` (`slug`),
  KEY `idx_schemes_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scholarship_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarship_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `scholarship_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `institution` varchar(191) DEFAULT NULL,
  `course` varchar(191) DEFAULT NULL,
  `guardian_name` varchar(128) DEFAULT NULL,
  `annual_income` varchar(64) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','under_review','approved','rejected') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_schapp_sch` (`scholarship_id`),
  CONSTRAINT `fk_schapp_sch` FOREIGN KEY (`scholarship_id`) REFERENCES `scholarships` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `scholarships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarships` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` longtext DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `amount` varchar(96) DEFAULT NULL,
  `level` varchar(96) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scholarships_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_attendance` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `batch_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_satt` (`batch_id`,`student_id`,`date`),
  KEY `idx_satt_school` (`school_id`),
  KEY `idx_satt_date` (`date`),
  KEY `fk_satt_student` (`student_id`),
  CONSTRAINT `fk_satt_batch` FOREIGN KEY (`batch_id`) REFERENCES `school_batches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_satt_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_satt_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_batches` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `teacher_id` int(10) unsigned DEFAULT NULL,
  `schedule` varchar(191) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `capacity` int(10) unsigned DEFAULT NULL,
  `status` enum('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sbatch_school` (`school_id`),
  CONSTRAINT `fk_sbatch_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_fee_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_fee_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `student_id` int(10) unsigned DEFAULT NULL,
  `structure_id` int(10) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `scholarship` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','partial','paid','waived') NOT NULL DEFAULT 'pending',
  `method` enum('cash','upi','cheque','bank','online','other') NOT NULL DEFAULT 'cash',
  `receipt_no` varchar(48) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sfee_receipt` (`receipt_no`),
  KEY `idx_sfee_school` (`school_id`),
  KEY `idx_sfee_student` (`student_id`),
  KEY `idx_sfee_status` (`status`),
  CONSTRAINT `fk_sfee_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sfee_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_fee_structures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_fee_structures` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `name` varchar(128) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frequency` enum('one-time','monthly','quarterly','term','annual') NOT NULL DEFAULT 'one-time',
  `applies_to` varchar(96) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sfeestruct_school` (`school_id`),
  CONSTRAINT `fk_sfeestruct_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_staff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `team_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `role` enum('coordinator','teacher','volunteer','administrator','counselor') NOT NULL DEFAULT 'teacher',
  `subject` varchar(96) DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sstaff_school` (`school_id`),
  CONSTRAINT `fk_sstaff_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `school_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `school_students` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` int(10) unsigned NOT NULL,
  `member_id` int(10) unsigned DEFAULT NULL,
  `student_code` varchar(32) DEFAULT NULL,
  `admission_no` varchar(48) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `guardian_name` varchar(128) DEFAULT NULL,
  `father_name` varchar(128) DEFAULT NULL,
  `mother_name` varchar(128) DEFAULT NULL,
  `guardian_phone` varchar(32) DEFAULT NULL,
  `guardian_email` varchar(191) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `grade` varchar(48) DEFAULT NULL,
  `section` varchar(32) DEFAULT NULL,
  `academic_year` varchar(16) DEFAULT NULL,
  `previous_school` varchar(191) DEFAULT NULL,
  `blood_group` varchar(8) DEFAULT NULL,
  `category` varchar(32) DEFAULT NULL,
  `roll_no` varchar(48) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `program_id` int(10) unsigned DEFAULT NULL,
  `batch_id` int(10) unsigned DEFAULT NULL,
  `enrollment_date` date DEFAULT NULL,
  `status` enum('enrolled','active','completed','dropped') NOT NULL DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sstudent_code` (`student_code`),
  UNIQUE KEY `uq_sstudent_qr` (`qr_token`),
  KEY `idx_sstudent_school` (`school_id`),
  KEY `idx_sstudent_program` (`program_id`),
  KEY `idx_sstudent_batch` (`batch_id`),
  KEY `idx_sstudent_member` (`member_id`),
  CONSTRAINT `fk_sstudent_school` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `schools`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schools` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `type` enum('government','private','aided','other') NOT NULL DEFAULT 'government',
  `board` varchar(96) DEFAULT NULL,
  `accreditation` varchar(191) DEFAULT NULL,
  `udise_code` varchar(32) DEFAULT NULL,
  `contact_person` varchar(128) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `website` varchar(191) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `state` varchar(96) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `established_year` smallint(5) unsigned DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','prospective') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schools_slug` (`slug`),
  UNIQUE KEY `uq_schools_code` (`code`),
  KEY `idx_schools_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seo_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seo_meta` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_key` varchar(191) NOT NULL,
  `meta_title` varchar(191) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `meta_keywords` varchar(320) DEFAULT NULL,
  `og_title` varchar(191) DEFAULT NULL,
  `og_description` varchar(320) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `canonical` varchar(255) DEFAULT NULL,
  `robots` varchar(64) NOT NULL DEFAULT 'index,follow',
  `schema_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seo_pagekey` (`page_key`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_name` varchar(64) NOT NULL DEFAULT 'general',
  `key_name` varchar(128) NOT NULL,
  `value` longtext DEFAULT NULL,
  `type` enum('text','textarea','number','boolean','json','image','email','url','color') NOT NULL DEFAULT 'text',
  `label` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`key_name`),
  KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=160 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `social_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(64) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_social_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sponsors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sponsors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `tier` enum('platinum','gold','silver','bronze','partner') NOT NULL DEFAULT 'partner',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sponsors_status` (`status`,`tier`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `course_id` int(10) unsigned DEFAULT NULL,
  `serial` varchar(48) DEFAULT NULL,
  `type` enum('completion','participation','merit','achievement') NOT NULL DEFAULT 'completion',
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `template` varchar(48) NOT NULL DEFAULT 'classic',
  `issue_date` date DEFAULT NULL,
  `signed_by` varchar(128) DEFAULT NULL,
  `signature_image` varchar(255) DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `status` enum('valid','revoked') NOT NULL DEFAULT 'valid',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scert_serial` (`serial`),
  UNIQUE KEY `uq_scert_qr` (`qr_token`),
  KEY `idx_scert_student` (`student_id`),
  CONSTRAINT `fk_scert_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `student_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(10) unsigned NOT NULL,
  `title` varchar(128) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sdoc_student` (`student_id`),
  CONSTRAINT `fk_sdoc_student` FOREIGN KEY (`student_id`) REFERENCES `school_students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `slug` varchar(128) NOT NULL,
  `designation` varchar(128) DEFAULT NULL,
  `department` varchar(128) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `socials` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`socials`)),
  `is_leadership` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_team_slug` (`slug`),
  KEY `idx_team_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `designation` varchar(128) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 5,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_testimonials_status` (`status`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `theme_presets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `theme_presets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `payload` longtext NOT NULL,
  `is_builtin` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_preset_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `theme_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `theme_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL,
  `value` text DEFAULT NULL,
  `draft` text DEFAULT NULL,
  `group_name` varchar(32) NOT NULL DEFAULT 'colors',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_token` (`token`),
  KEY `idx_theme_group` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `theme_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `theme_versions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(120) DEFAULT NULL,
  `payload` longtext NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_auto` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_theme_ver_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_recovery_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_recovery_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recovery_user` (`user_id`),
  CONSTRAINT `fk_recovery_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(32) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `twofa_enrolled_at` datetime DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role_id`),
  KEY `idx_users_status` (`status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `videos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `youtube_id` varchar(32) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `category` varchar(64) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_videos_slug` (`slug`),
  KEY `idx_videos_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `volunteers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `volunteers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(96) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other','prefer_not') DEFAULT NULL,
  `occupation` varchar(128) DEFAULT NULL,
  `area_of_interest` varchar(191) DEFAULT NULL,
  `availability` varchar(128) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `status` enum('new','reviewing','approved','rejected') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_volunteers_status` (`status`),
  KEY `idx_vol_country` (`country_iso`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widgets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(191) NOT NULL,
  `position` varchar(48) NOT NULL DEFAULT 'footer',
  `type` enum('html','text','links','contact','newsletter') NOT NULL DEFAULT 'html',
  `content` longtext DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_widgets_pos` (`position`,`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- =============================================================================
--  SECTION 2 - CONTENT & CONFIGURATION DATA
-- =============================================================================

/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (1,'general','site_name','EDUSKILL INDIA FOUNDATION','text',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (2,'general','site_tagline','Empowering Communities • Spreading Hope • Creating Change','text',NULL,'2026-07-21 21:33:35','2026-07-22 11:26:13');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (3,'general','site_description','EDUSKILL INDIA FOUNDATION is a registered non-profit in Patna, Bihar working to empower communities through education, healthcare, skill development and relief.','textarea',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (4,'general','site_keywords','NGO Patna, Bihar NGO, charity India, donation, volunteer, education, healthcare','text',NULL,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (5,'general','footer_about','EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief — spreading hope and creating lasting change.','textarea',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (6,'contact','contact_email','info@eduskillindia.org','email',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (7,'contact','contact_phone','+91 74919 32148','text',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (8,'contact','contact_address','International Financial Hub (IFH), Plot No. IIF/04, Action Area II, New Town, Kolkata – 700156, West Bengal, India','text',NULL,'2026-07-21 21:33:35','2026-08-10 12:44:00');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (9,'contact','whatsapp_number','917491932148','text',NULL,'2026-07-21 21:33:35','2026-07-26 17:14:19');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (10,'homepage','home_about_title','A movement for dignity, hope and opportunity','text',NULL,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (11,'homepage','home_about_text','EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN U88900BR2026NPL081597) working to empower communities through education, healthcare, skill development and emergency relief. We believe lasting change is built alongside people, not handed to them.','textarea',NULL,'2026-07-21 21:33:35','2026-08-10 12:44:00');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (12,'homepage','mission_short','To empower underserved communities by providing access to quality education, healthcare, and sustainable livelihoods.','textarea',NULL,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (13,'homepage','vision_short','An equitable society where every individual has the opportunity to live with dignity and reach their full potential.','textarea',NULL,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (14,'org','cin','U88900BR2026NPL081597','text',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (15,'org','pan','AAJCE4199E','text',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (16,'org','tan','PTNE02296A','text',NULL,'2026-07-21 21:33:35','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (17,'org','incorporation_date','2025-01-15','text',NULL,'2026-07-21 21:33:35','2026-07-31 18:59:36');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (18,'general','site_logo','','image',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (19,'general','favicon','','image',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (20,'contact','google_map','','textarea',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (21,'social','social_facebook','','url',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (22,'social','social_twitter','','url',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (23,'social','social_instagram','','url',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (24,'social','social_youtube','','url',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (25,'social','social_linkedin','','url',NULL,'2026-07-21 21:56:14','2026-07-21 21:56:14');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (28,'theme','theme_primary_color','#276db0','color',NULL,'2026-07-22 11:21:39','2026-07-22 11:56:52');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (29,'theme','theme_secondary_color','#123740','color',NULL,'2026-07-22 11:21:39','2026-07-24 16:14:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (30,'theme','theme_accent_color','#f59e0b','color',NULL,'2026-07-22 11:21:39','2026-07-22 11:21:39');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (31,'theme','footer_bg','#0f172a','color',NULL,'2026-07-22 11:21:39','2026-07-22 11:21:39');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (32,'theme','default_dark_mode','0','boolean',NULL,'2026-07-22 11:21:39','2026-07-22 11:21:39');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (33,'theme','heading_font','Poppins','text',NULL,'2026-07-22 11:21:39','2026-07-24 16:14:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (34,'theme','body_font','Inter','text',NULL,'2026-07-22 11:21:39','2026-07-24 16:14:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (35,'header','topbar_enabled','1','boolean',NULL,'2026-07-23 06:29:50','2026-07-23 06:29:50');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (36,'membership','membership_code_prefix','PWF','text','Membership ID prefix','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (37,'membership','membership_card_template','classic','text','Default ID card template','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (38,'membership','membership_reminders_enabled','1','boolean','Send expiry reminders','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (39,'membership','membership_reminder_days','30,15,7','text','Reminder days before expiry','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (40,'membership','membership_cron_token','','text','Cron trigger token (auto-generated)','2026-07-23 06:30:02','2026-07-23 06:50:39');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (41,'payments','cashfree_enabled','0','boolean','Enable Cashfree payments','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (42,'payments','cashfree_env','sandbox','text','Cashfree environment (sandbox/production)','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (43,'payments','cashfree_app_id','','text','Cashfree App ID','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (44,'payments','cashfree_secret_key','','text','Cashfree Secret Key','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (45,'payments','cashfree_webhook_secret','','text','Cashfree Webhook Secret','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (46,'sms','sms_enabled','0','boolean','Enable SMS notifications','2026-07-23 06:30:02','2026-07-27 15:58:23');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (47,'sms','sms_provider','msg91','text','SMS provider (msg91/fast2sms)','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (48,'sms','msg91_authkey','','text','MSG91 Auth Key','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (49,'sms','msg91_sender','','text','MSG91 Sender ID (6 chars)','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (50,'sms','msg91_route','4','text','MSG91 route','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (51,'sms','msg91_dlt_template_id','','text','MSG91 DLT template id','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (52,'sms','fast2sms_key','','text','Fast2SMS API key','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (53,'sms','fast2sms_sender','','text','Fast2SMS sender id','2026-07-23 06:30:02','2026-07-23 06:30:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (54,'school','school_code_prefix','SCH','text','School code prefix','2026-07-23 08:03:04','2026-07-23 08:03:04');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (55,'school','student_code_prefix','STU','text','Student code prefix','2026-07-23 08:03:04','2026-07-23 08:03:04');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (56,'school','school_receipt_prefix','RCP','text','Fee receipt prefix','2026-07-23 08:03:04','2026-07-23 08:03:04');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (57,'student','admission_prefix','ADM','text','Admission application prefix','2026-07-23 09:11:33','2026-07-23 09:11:33');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (58,'student','certificate_prefix','CERT','text','Certificate serial prefix','2026-07-23 09:11:33','2026-07-23 09:11:33');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (59,'student','marksheet_prefix','MS','text','Marksheet serial prefix','2026-07-23 09:11:33','2026-07-23 09:11:33');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (60,'student','student_portal_enabled','1','boolean','Enable the student learning portal','2026-07-23 09:11:33','2026-07-23 09:11:33');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (61,'payments','razorpay_enabled','0','boolean','Enable Razorpay','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (62,'payments','razorpay_key_id','','text','Razorpay Key ID','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (63,'payments','razorpay_key_secret','','text','Razorpay Key Secret','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (64,'payments','razorpay_webhook_secret','','text','Razorpay Webhook Secret','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (65,'payments','stripe_enabled','0','boolean','Enable Stripe','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (66,'payments','stripe_publishable','','text','Stripe Publishable Key','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (67,'payments','stripe_secret','','text','Stripe Secret Key','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (68,'payments','stripe_webhook_secret','','text','Stripe Webhook Secret','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (69,'payments','paypal_enabled','0','boolean','Enable PayPal','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (70,'payments','paypal_env','sandbox','text','PayPal environment','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (71,'payments','paypal_client_id','','text','PayPal Client ID','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (72,'payments','paypal_secret','','text','PayPal Secret','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (73,'payments','paypal_webhook_id','','text','PayPal Webhook ID','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (74,'donation','donation_receipt_prefix','DN','text','Donation receipt prefix','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (75,'donation','org_80g_number','','text','80G registration number','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (76,'donation','org_12a_number','','text','12A registration number','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (77,'donation','org_pan','','text','Organisation PAN','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (78,'donation','donation_currency','INR','text','Default donation currency','2026-07-23 17:57:29','2026-07-23 17:57:29');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (79,'donation','donation_secret','','text',NULL,'2026-07-23 18:46:13','2026-07-23 18:46:13');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (86,'messaging','msg91_dlt_template','','text','MSG91 DLT Template ID','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (87,'messaging','twilio_sid','','text','Twilio Account SID','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (88,'messaging','twilio_token','','text','Twilio Auth Token','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (89,'messaging','twilio_from','','text','Twilio From Number','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (90,'messaging','whatsapp_enabled','0','boolean','Enable WhatsApp','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (91,'messaging','whatsapp_token','','text','WhatsApp Cloud API Token','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (92,'messaging','whatsapp_phone_id','','text','WhatsApp Phone Number ID','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (93,'messaging','onesignal_enabled','0','boolean','Enable OneSignal Push','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (94,'messaging','onesignal_app_id','','text','OneSignal App ID','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (95,'messaging','onesignal_api_key','','text','OneSignal REST API Key','2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (101,'newsletter','newsletter_secret','','text',NULL,'2026-07-24 07:51:17','2026-07-24 07:51:17');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (102,'email','email_queue_batch','50','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (103,'email','email_queue_enabled','1','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (104,'email','email_default_from','','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (105,'email','email_default_reply','','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (106,'email','email_dkim_selector','','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (107,'email','email_spf_record','','textarea',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (108,'email','email_dkim_record','','textarea',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (109,'email','email_rate_per_min','60','text',NULL,'2026-07-24 13:23:18','2026-07-24 13:23:18');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (110,'security','rbac_enforce','1','boolean',NULL,'2026-07-24 13:52:30','2026-07-24 13:54:30');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (111,'header','topbar_show_lang','1','boolean',NULL,'2026-07-24 16:14:50','2026-07-24 16:14:50');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (112,'header','header_cta_text','Donate','text',NULL,'2026-07-24 16:14:50','2026-07-24 16:14:50');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (113,'header','header_cta_url','donate','text',NULL,'2026-07-24 16:14:50','2026-07-24 16:14:50');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (114,'header','header_sticky','1','boolean',NULL,'2026-07-24 16:14:50','2026-07-24 16:14:50');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (115,'footer','footer_copyright','','text',NULL,'2026-07-24 16:15:02','2026-07-24 16:15:02');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (116,'footer','footer_show_newsletter','1','boolean',NULL,'2026-07-24 16:15:02','2026-07-24 16:15:53');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (117,'footer','footer_show_trust','1','boolean',NULL,'2026-07-24 16:15:02','2026-07-27 14:46:12');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (118,'organization','legal_status','Section 8 Company (Non-Profit Organization)','text','Legal status','2026-07-26 13:52:49','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (119,'organization','ngo_darpan_id','BR/2026/0971369','text','NGO Darpan unique ID','2026-07-26 13:52:49','2026-07-26 13:52:49');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (120,'organization','registered_office','','textarea','Registered office address','2026-07-26 13:52:49','2026-07-31 15:48:57');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (121,'maintenance','maintenance_mode','0','boolean','Maintenance mode','2026-07-27 14:08:58','2026-07-27 14:11:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (122,'maintenance','maintenance_message','We\'re carrying out some scheduled maintenance to make things better. The site will be back shortly — thank you for your patience.','textarea','Notice shown to visitors','2026-07-27 14:11:45','2026-07-31 16:17:01');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (123,'maintenance','maintenance_eta','','text','Expected back (optional)','2026-07-27 14:11:45','2026-07-27 14:11:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (124,'maintenance','maintenance_retry_minutes','30','number','Retry-After (minutes)','2026-07-27 14:11:45','2026-07-27 14:11:45');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (134,'oauth','google_login_enabled','1','boolean','Enable Google sign-in','2026-07-31 15:51:22','2026-07-31 16:17:09');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (135,'oauth','google_client_id','','text','Google Client ID','2026-07-31 15:51:22','2026-07-31 15:59:13');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (136,'oauth','google_client_secret','','text','Google Client Secret','2026-07-31 15:51:22','2026-07-31 15:59:13');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (137,'oauth','google_redirect_uri','','url','Google Redirect URI (blank = auto)','2026-07-31 15:51:22','2026-07-31 15:51:22');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (138,'oauth','facebook_login_enabled','0','boolean','Enable Facebook sign-in','2026-07-31 15:51:22','2026-07-31 15:51:22');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (139,'oauth','facebook_app_id','','text','Facebook App ID','2026-07-31 15:51:22','2026-07-31 15:51:22');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (140,'oauth','facebook_app_secret','','text','Facebook App Secret','2026-07-31 15:51:22','2026-07-31 15:51:22');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (141,'oauth','facebook_redirect_uri','','url','Facebook Redirect URI (blank = auto)','2026-07-31 15:51:22','2026-07-31 15:51:22');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (142,'faq','faq_theme','minimal','text','Colour theme','2026-07-31 17:59:26','2026-07-31 19:00:00');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (143,'faq','faq_background','mesh','text','Animated background','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (144,'faq','faq_border','gradient','text','Border effect','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (145,'faq','faq_animation','fade-slide','text','Accordion animation','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (146,'faq','faq_hover','lift','text','Hover effect','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (147,'faq','faq_icon','plus-minus','text','Icon animation','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (148,'faq','faq_duration','380','number','Animation duration (ms)','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (149,'faq','faq_radius','20','number','Corner radius (px)','2026-07-31 17:59:26','2026-07-31 18:07:31');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (150,'faq','faq_shadow','3','number','Shadow intensity (0-5)','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (151,'faq','faq_glow','2','number','Glow intensity (0-5)','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (152,'faq','faq_spacing','12','number','Gap between items (px)','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (153,'faq','faq_font_size','16','number','Question font size (px)','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (154,'faq','faq_single_open','1','boolean','Only one answer open at a time','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (155,'faq','faq_show_search','1','boolean','Show the search box','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (156,'faq','faq_custom_css','','textarea','Custom CSS','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (157,'faq','faq_custom_js','','textarea','Custom JavaScript','2026-07-31 17:59:26','2026-07-31 17:59:26');
INSERT INTO `settings` (`id`, `group_name`, `key_name`, `value`, `type`, `label`, `created_at`, `updated_at`) VALUES (159,'api','api_cors_origin','https://eduskillindia.org','text',NULL,'2026-08-10 13:32:17','2026-08-10 13:32:17');

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (1,'Super Admin','super-admin','Full access',1,'2026-07-21 20:53:41','2026-07-21 20:53:41');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (2,'Editor','editor','Manage content, not system settings',0,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (3,'Staff / Admin','staff','Configurable administrator — access limited to assigned modules.',0,'2026-07-24 13:52:28','2026-07-24 13:52:28');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (4,'School','school','School-scoped: own students, batches, fees and staff.',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (5,'Teacher','teacher','Course-scoped: course content, grades, attendance, assignments.',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (6,'Student','student','Self-scoped portal role (own courses, results, certificates).',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (7,'Volunteer','volunteer','Limited portal role (tasks, schedule, hours).',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (8,'Member','member','Member-scoped portal role (card, events, downloads, profile).',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`, `updated_at`) VALUES (9,'Donor','donor','Donor-scoped portal role (donations, receipts, tax certificates).',0,'2026-07-24 13:52:29','2026-07-24 13:52:29');

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (1,'Manage Content','manage-content','content','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (2,'Manage Blog','manage-blog','blog','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (3,'Manage Media','manage-media','media','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (4,'Manage Events','manage-events','events','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (5,'Manage People','manage-people','people','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (6,'Manage Donations','manage-donations','finance','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (7,'Manage Users','manage-users','system','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (8,'Manage Settings','manage-settings','system','2026-07-21 21:33:35');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (9,'Website Content','grp-website-content','Content','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (10,'Blog','grp-blog','Content','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (11,'Media','grp-media','Content','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (12,'Engagement','grp-engagement','Engagement','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (13,'People','grp-people','Engagement','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (14,'School Management','grp-school-management','Education','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (15,'Student Management','grp-student-management','Education','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (16,'Learning (LMS)','grp-learning-lms','Education','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (17,'Employee Management','grp-employee-management','Operations','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (18,'Programs & Applications','grp-programs-applications','Operations','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (19,'Document Hub','grp-document-hub','Content','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (20,'Communication','grp-communication','Engagement','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (21,'Email Marketing','grp-email-marketing','Marketing','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (22,'Messaging & Push','grp-messaging-push','Marketing','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (23,'Marketing & SEO','grp-marketing-seo','Marketing','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (24,'Referral & Coupons','grp-referral-coupons','Marketing','2026-07-24 13:52:28');
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `created_at`) VALUES (25,'System','grp-system','System','2026-07-24 13:52:28');

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,1);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,2);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,3);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,4);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (2,5);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,9);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,10);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,11);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,12);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,13);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,14);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,16);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,17);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,18);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,19);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,20);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,21);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,22);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,23);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (3,24);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (4,14);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,15);
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES (5,16);

INSERT INTO `users` (`id`, `role_id`, `name`, `email`, `password`, `password_changed_at`, `must_change_password`, `phone`, `avatar`, `bio`, `status`, `remember_token`, `totp_secret`, `totp_enabled`, `twofa_enrolled_at`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Prashant Kumar','admin@eduskillindia.org','$2y$10$YMw9MxX21WlF0Vlp0BpCWOHpo/30YKoSWwMn3IcqeRpsbvBCJuX5q',NULL,1,NULL,NULL,NULL,'active',NULL,NULL,0,NULL,'2026-08-10 13:53:18','::1','2026-07-21 20:53:41','2026-08-10 13:53:18',NULL);

INSERT INTO `seo_meta` (`id`, `page_key`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical`, `robots`, `schema_json`, `created_at`, `updated_at`) VALUES (1,'home','EDUSKILL INDIA FOUNDATION — NGO in Patna, Bihar','Empowering communities across Bihar through education, healthcare, skill development and relief. Donate or volunteer today.',NULL,NULL,NULL,NULL,NULL,'index,follow',NULL,'2026-07-21 21:33:36','2026-07-26 17:12:07');
INSERT INTO `seo_meta` (`id`, `page_key`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical`, `robots`, `schema_json`, `created_at`, `updated_at`) VALUES (2,'about','About Us — EDUSKILL INDIA FOUNDATION','Learn about our mission, vision, leadership and the communities we serve across Bihar.',NULL,NULL,NULL,NULL,NULL,'index,follow',NULL,'2026-07-21 21:33:36','2026-07-26 17:12:07');
INSERT INTO `seo_meta` (`id`, `page_key`, `meta_title`, `meta_description`, `meta_keywords`, `og_title`, `og_description`, `og_image`, `canonical`, `robots`, `schema_json`, `created_at`, `updated_at`) VALUES (3,'donate','Donate — EDUSKILL INDIA FOUNDATION','Support our work with a tax-deductible (80G) donation. Every rupee is tracked from donation to delivery.',NULL,NULL,NULL,NULL,NULL,'index,follow',NULL,'2026-07-21 21:33:36','2026-07-26 17:12:07');

INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (67,'color.primary','#0B4E3D',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (68,'color.secondary','#174D3D',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (69,'color.accent','#F15A24',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (70,'color.success','#2F8065',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (71,'color.success_dark','#1F5C48',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (72,'color.danger','#dc2626',NULL,'colors','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (73,'color.bg','#FFFFFF',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (74,'color.bg_alt','#F8FCF8',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (75,'color.surface','#FFFFFF',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (76,'color.surface_2','#FEFEF1',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (77,'color.text','#151818',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (78,'color.text_soft','#372C22',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (79,'color.muted','#4B6754',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (80,'color.border','#C1CCB3',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (81,'color.sidebar','#0F4537',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (82,'color.sidebar_2','#0B3A2E',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (83,'color.sidebar_active','#174D3D',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (84,'color.footer','#0F4537',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (85,'grad.primary_a','#0B4E3D',NULL,'gradients','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (86,'grad.primary_b','#174D3D',NULL,'gradients','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (87,'grad.cta_a','#F15A24',NULL,'gradients','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (88,'grad.cta_b','#E8C52E',NULL,'gradients','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (89,'font.body','Inter',NULL,'typography','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (90,'font.heading','Manrope',NULL,'typography','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (91,'email.header_bg','#0B4E3D',NULL,'email','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (92,'email.btn_bg','#F15A24',NULL,'email','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (93,'pdf.accent','#063566',NULL,'email','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (94,'adv.theme_color','#0B4E3D',NULL,'advanced','2026-08-10 12:59:05');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (95,'font.base','16',NULL,'typography','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (96,'font.scale','1.25',NULL,'typography','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (97,'font.line','1.7',NULL,'typography','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (98,'font.tracking','0',NULL,'typography','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (99,'font.weight_body','400',NULL,'typography','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (100,'font.weight_head','800',NULL,'typography','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (101,'grad.angle','135',NULL,'gradients','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (102,'glass.blur','12',NULL,'gradients','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (103,'glass.alpha','12',NULL,'gradients','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (104,'email.font','Arial, Helvetica, sans-serif',NULL,'email','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (105,'email.signature',NULL,'','email','2026-07-27 16:52:56');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (106,'brand.name',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (107,'brand.logo',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (108,'brand.logo_dark',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (109,'brand.favicon',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (110,'brand.app_icon',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (111,'brand.watermark',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (112,'brand.footer_note',NULL,'','brand','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (113,'layout.width','boxed',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (114,'layout.container','1200',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (115,'layout.header_h','74',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (116,'layout.sidebar_w','268',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (117,'layout.sticky_header','1',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (118,'layout.dir','ltr',NULL,'layout','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (119,'ui.radius','14',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (120,'ui.radius_sm','10',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (121,'ui.radius_lg','22',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (122,'ui.shadow','medium',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (123,'ui.transition','250',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (124,'ui.hover_lift','4',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (125,'ui.animations','1',NULL,'effects','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (126,'auth.bg_image',NULL,'','auth','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (127,'auth.card','glass',NULL,'auth','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (128,'auth.welcome',NULL,'','auth','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (129,'auth.animation','1',NULL,'auth','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (130,'adv.mode','light',NULL,'advanced','2026-07-31 19:08:41');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (131,'adv.css',NULL,'','advanced','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (132,'adv.js',NULL,'','advanced','2026-07-31 19:08:39');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (133,'color.yellow','#FFE987',NULL,'colors','2026-08-10 12:48:50');
INSERT INTO `theme_settings` (`id`, `token`, `value`, `draft`, `group_name`, `updated_at`) VALUES (134,'color.gold','#E8C52E',NULL,'colors','2026-08-10 12:48:50');

INSERT INTO `theme_presets` (`id`, `name`, `slug`, `payload`, `is_builtin`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (1,'EduSkill Default','eduskill-default','{\"brand.name\":\"\",\"brand.logo\":\"\",\"brand.logo_dark\":\"\",\"brand.favicon\":\"\",\"brand.app_icon\":\"\",\"brand.watermark\":\"\",\"brand.footer_note\":\"\",\"color.primary\":\"#063566\",\"color.secondary\":\"#084881\",\"color.accent\":\"#E67B1D\",\"color.success\":\"#58A42F\",\"color.success_dark\":\"#308629\",\"color.danger\":\"#DC2626\",\"color.bg\":\"#FFFFFF\",\"color.bg_alt\":\"#F1F6FB\",\"color.surface\":\"#FDFEFE\",\"color.surface_2\":\"#F4F8FC\",\"color.text\":\"#063566\",\"color.text_soft\":\"#374151\",\"color.muted\":\"#6B7280\",\"color.border\":\"#E5E7EB\",\"color.sidebar\":\"#063566\",\"color.sidebar_2\":\"#042A52\",\"color.sidebar_active\":\"#084881\",\"color.footer\":\"#063566\",\"grad.angle\":\"135\",\"grad.primary_a\":\"#063566\",\"grad.primary_b\":\"#084881\",\"grad.cta_a\":\"#E67B1D\",\"grad.cta_b\":\"#F59E0B\",\"glass.blur\":\"12\",\"glass.alpha\":\"12\",\"font.body\":\"Plus Jakarta Sans\",\"font.heading\":\"Plus Jakarta Sans\",\"font.base\":\"16\",\"font.scale\":\"1.25\",\"font.line\":\"1.65\",\"font.tracking\":\"0\",\"font.weight_body\":\"400\",\"font.weight_head\":\"800\",\"layout.width\":\"boxed\",\"layout.container\":\"1200\",\"layout.header_h\":\"74\",\"layout.sidebar_w\":\"268\",\"layout.sticky_header\":\"1\",\"layout.dir\":\"ltr\",\"ui.radius\":\"14\",\"ui.radius_sm\":\"10\",\"ui.radius_lg\":\"22\",\"ui.shadow\":\"medium\",\"ui.transition\":\"250\",\"ui.hover_lift\":\"4\",\"ui.animations\":\"1\",\"auth.bg_image\":\"\",\"auth.card\":\"glass\",\"auth.welcome\":\"\",\"auth.animation\":\"1\",\"email.header_bg\":\"#063566\",\"email.btn_bg\":\"#E67B1D\",\"email.font\":\"Arial, Helvetica, sans-serif\",\"email.signature\":\"\",\"pdf.accent\":\"#063566\",\"adv.theme_color\":\"#063566\",\"adv.mode\":\"light\",\"adv.css\":\"\",\"adv.js\":\"\"}',1,1,NULL,'2026-07-27 12:53:50','2026-07-31 19:08:39');
INSERT INTO `theme_presets` (`id`, `name`, `slug`, `payload`, `is_builtin`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (2,'Midnight','midnight','{\"brand.name\":\"\",\"brand.logo\":\"\",\"brand.logo_dark\":\"\",\"brand.favicon\":\"\",\"brand.app_icon\":\"\",\"brand.watermark\":\"\",\"brand.footer_note\":\"\",\"color.primary\":\"#0F172A\",\"color.secondary\":\"#1E293B\",\"color.accent\":\"#38BDF8\",\"color.success\":\"#58A42F\",\"color.success_dark\":\"#308629\",\"color.danger\":\"#DC2626\",\"color.bg\":\"#FFFFFF\",\"color.bg_alt\":\"#F1F6FB\",\"color.surface\":\"#FDFEFE\",\"color.surface_2\":\"#F4F8FC\",\"color.text\":\"#063566\",\"color.text_soft\":\"#374151\",\"color.muted\":\"#6B7280\",\"color.border\":\"#E5E7EB\",\"color.sidebar\":\"#0F172A\",\"color.sidebar_2\":\"#020617\",\"color.sidebar_active\":\"#084881\",\"color.footer\":\"#063566\",\"grad.angle\":\"135\",\"grad.primary_a\":\"#0F172A\",\"grad.primary_b\":\"#1E293B\",\"grad.cta_a\":\"#E67B1D\",\"grad.cta_b\":\"#F59E0B\",\"glass.blur\":\"12\",\"glass.alpha\":\"12\",\"font.body\":\"Plus Jakarta Sans\",\"font.heading\":\"Plus Jakarta Sans\",\"font.base\":\"16\",\"font.scale\":\"1.25\",\"font.line\":\"1.65\",\"font.tracking\":\"0\",\"font.weight_body\":\"400\",\"font.weight_head\":\"800\",\"layout.width\":\"boxed\",\"layout.container\":\"1200\",\"layout.header_h\":\"74\",\"layout.sidebar_w\":\"268\",\"layout.sticky_header\":\"1\",\"layout.dir\":\"ltr\",\"ui.radius\":\"14\",\"ui.radius_sm\":\"10\",\"ui.radius_lg\":\"22\",\"ui.shadow\":\"medium\",\"ui.transition\":\"250\",\"ui.hover_lift\":\"4\",\"ui.animations\":\"1\",\"auth.bg_image\":\"\",\"auth.card\":\"glass\",\"auth.welcome\":\"\",\"auth.animation\":\"1\",\"email.header_bg\":\"#063566\",\"email.btn_bg\":\"#E67B1D\",\"email.font\":\"Arial, Helvetica, sans-serif\",\"email.signature\":\"\",\"pdf.accent\":\"#063566\",\"adv.theme_color\":\"#063566\",\"adv.mode\":\"light\",\"adv.css\":\"\",\"adv.js\":\"\"}',1,0,NULL,'2026-07-27 12:53:50','2026-07-27 15:56:11');
INSERT INTO `theme_presets` (`id`, `name`, `slug`, `payload`, `is_builtin`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (3,'Forest','forest','{\"brand.name\":\"\",\"brand.logo\":\"\",\"brand.logo_dark\":\"\",\"brand.favicon\":\"\",\"brand.app_icon\":\"\",\"brand.watermark\":\"\",\"brand.footer_note\":\"\",\"color.primary\":\"#14532D\",\"color.secondary\":\"#166534\",\"color.accent\":\"#F59E0B\",\"color.success\":\"#22C55E\",\"color.success_dark\":\"#308629\",\"color.danger\":\"#DC2626\",\"color.bg\":\"#FFFFFF\",\"color.bg_alt\":\"#F1F6FB\",\"color.surface\":\"#FDFEFE\",\"color.surface_2\":\"#F4F8FC\",\"color.text\":\"#063566\",\"color.text_soft\":\"#374151\",\"color.muted\":\"#6B7280\",\"color.border\":\"#E5E7EB\",\"color.sidebar\":\"#14532D\",\"color.sidebar_2\":\"#052E16\",\"color.sidebar_active\":\"#084881\",\"color.footer\":\"#063566\",\"grad.angle\":\"135\",\"grad.primary_a\":\"#14532D\",\"grad.primary_b\":\"#166534\",\"grad.cta_a\":\"#E67B1D\",\"grad.cta_b\":\"#F59E0B\",\"glass.blur\":\"12\",\"glass.alpha\":\"12\",\"font.body\":\"Plus Jakarta Sans\",\"font.heading\":\"Plus Jakarta Sans\",\"font.base\":\"16\",\"font.scale\":\"1.25\",\"font.line\":\"1.65\",\"font.tracking\":\"0\",\"font.weight_body\":\"400\",\"font.weight_head\":\"800\",\"layout.width\":\"boxed\",\"layout.container\":\"1200\",\"layout.header_h\":\"74\",\"layout.sidebar_w\":\"268\",\"layout.sticky_header\":\"1\",\"layout.dir\":\"ltr\",\"ui.radius\":\"14\",\"ui.radius_sm\":\"10\",\"ui.radius_lg\":\"22\",\"ui.shadow\":\"medium\",\"ui.transition\":\"250\",\"ui.hover_lift\":\"4\",\"ui.animations\":\"1\",\"auth.bg_image\":\"\",\"auth.card\":\"glass\",\"auth.welcome\":\"\",\"auth.animation\":\"1\",\"email.header_bg\":\"#063566\",\"email.btn_bg\":\"#E67B1D\",\"email.font\":\"Arial, Helvetica, sans-serif\",\"email.signature\":\"\",\"pdf.accent\":\"#063566\",\"adv.theme_color\":\"#063566\",\"adv.mode\":\"light\",\"adv.css\":\"\",\"adv.js\":\"\"}',1,0,NULL,'2026-07-27 12:53:50','2026-07-27 15:54:12');
INSERT INTO `theme_presets` (`id`, `name`, `slug`, `payload`, `is_builtin`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (4,'Royal Plum','royal-plum','{\"brand.name\":\"\",\"brand.logo\":\"\",\"brand.logo_dark\":\"\",\"brand.favicon\":\"\",\"brand.app_icon\":\"\",\"brand.watermark\":\"\",\"brand.footer_note\":\"\",\"color.primary\":\"#4C1D95\",\"color.secondary\":\"#6D28D9\",\"color.accent\":\"#F472B6\",\"color.success\":\"#58A42F\",\"color.success_dark\":\"#308629\",\"color.danger\":\"#DC2626\",\"color.bg\":\"#FFFFFF\",\"color.bg_alt\":\"#F1F6FB\",\"color.surface\":\"#FDFEFE\",\"color.surface_2\":\"#F4F8FC\",\"color.text\":\"#063566\",\"color.text_soft\":\"#374151\",\"color.muted\":\"#6B7280\",\"color.border\":\"#E5E7EB\",\"color.sidebar\":\"#4C1D95\",\"color.sidebar_2\":\"#2E1065\",\"color.sidebar_active\":\"#084881\",\"color.footer\":\"#063566\",\"grad.angle\":\"135\",\"grad.primary_a\":\"#4C1D95\",\"grad.primary_b\":\"#6D28D9\",\"grad.cta_a\":\"#E67B1D\",\"grad.cta_b\":\"#F59E0B\",\"glass.blur\":\"12\",\"glass.alpha\":\"12\",\"font.body\":\"Plus Jakarta Sans\",\"font.heading\":\"Plus Jakarta Sans\",\"font.base\":\"16\",\"font.scale\":\"1.25\",\"font.line\":\"1.65\",\"font.tracking\":\"0\",\"font.weight_body\":\"400\",\"font.weight_head\":\"800\",\"layout.width\":\"boxed\",\"layout.container\":\"1200\",\"layout.header_h\":\"74\",\"layout.sidebar_w\":\"268\",\"layout.sticky_header\":\"1\",\"layout.dir\":\"ltr\",\"ui.radius\":\"14\",\"ui.radius_sm\":\"10\",\"ui.radius_lg\":\"22\",\"ui.shadow\":\"medium\",\"ui.transition\":\"250\",\"ui.hover_lift\":\"4\",\"ui.animations\":\"1\",\"auth.bg_image\":\"\",\"auth.card\":\"glass\",\"auth.welcome\":\"\",\"auth.animation\":\"1\",\"email.header_bg\":\"#063566\",\"email.btn_bg\":\"#E67B1D\",\"email.font\":\"Arial, Helvetica, sans-serif\",\"email.signature\":\"\",\"pdf.accent\":\"#063566\",\"adv.theme_color\":\"#063566\",\"adv.mode\":\"light\",\"adv.css\":\"\",\"adv.js\":\"\"}',1,0,NULL,'2026-07-27 12:53:50','2026-07-27 15:58:09');
INSERT INTO `theme_presets` (`id`, `name`, `slug`, `payload`, `is_builtin`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES (5,'Sunrise','sunrise','{\"brand.name\":\"\",\"brand.logo\":\"\",\"brand.logo_dark\":\"\",\"brand.favicon\":\"\",\"brand.app_icon\":\"\",\"brand.watermark\":\"\",\"brand.footer_note\":\"\",\"color.primary\":\"#9A3412\",\"color.secondary\":\"#C2410C\",\"color.accent\":\"#F59E0B\",\"color.success\":\"#58A42F\",\"color.success_dark\":\"#308629\",\"color.danger\":\"#DC2626\",\"color.bg\":\"#FFFFFF\",\"color.bg_alt\":\"#F1F6FB\",\"color.surface\":\"#FDFEFE\",\"color.surface_2\":\"#F4F8FC\",\"color.text\":\"#063566\",\"color.text_soft\":\"#374151\",\"color.muted\":\"#6B7280\",\"color.border\":\"#E5E7EB\",\"color.sidebar\":\"#7C2D12\",\"color.sidebar_2\":\"#431407\",\"color.sidebar_active\":\"#084881\",\"color.footer\":\"#063566\",\"grad.angle\":\"135\",\"grad.primary_a\":\"#9A3412\",\"grad.primary_b\":\"#EA580C\",\"grad.cta_a\":\"#E67B1D\",\"grad.cta_b\":\"#F59E0B\",\"glass.blur\":\"12\",\"glass.alpha\":\"12\",\"font.body\":\"Plus Jakarta Sans\",\"font.heading\":\"Plus Jakarta Sans\",\"font.base\":\"16\",\"font.scale\":\"1.25\",\"font.line\":\"1.65\",\"font.tracking\":\"0\",\"font.weight_body\":\"400\",\"font.weight_head\":\"800\",\"layout.width\":\"boxed\",\"layout.container\":\"1200\",\"layout.header_h\":\"74\",\"layout.sidebar_w\":\"268\",\"layout.sticky_header\":\"1\",\"layout.dir\":\"ltr\",\"ui.radius\":\"14\",\"ui.radius_sm\":\"10\",\"ui.radius_lg\":\"22\",\"ui.shadow\":\"medium\",\"ui.transition\":\"250\",\"ui.hover_lift\":\"4\",\"ui.animations\":\"1\",\"auth.bg_image\":\"\",\"auth.card\":\"glass\",\"auth.welcome\":\"\",\"auth.animation\":\"1\",\"email.header_bg\":\"#063566\",\"email.btn_bg\":\"#E67B1D\",\"email.font\":\"Arial, Helvetica, sans-serif\",\"email.signature\":\"\",\"pdf.accent\":\"#063566\",\"adv.theme_color\":\"#063566\",\"adv.mode\":\"light\",\"adv.css\":\"\",\"adv.js\":\"\"}',1,0,NULL,'2026-07-27 12:53:50','2026-07-27 15:55:55');

INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,NULL,'Home','/','','',0,'header','_self',1,1,'2026-07-21 21:33:35','2026-07-31 15:32:39');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,NULL,'About','about',NULL,NULL,0,'header','_self',2,1,'2026-07-21 21:33:35','2026-07-24 18:45:58');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,NULL,'Programs','programs',NULL,NULL,0,'header','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,NULL,'Media','gallery',NULL,NULL,0,'header','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,NULL,'Get Involved','volunteer',NULL,NULL,0,'header','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (6,NULL,'Contact','contact',NULL,NULL,0,'header','_self',6,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (10,2,'Who We Are','about',NULL,NULL,0,'header','_self',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (11,2,'Our Story','our-story',NULL,NULL,0,'header','_self',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (12,2,'Mission & Vision','mission-vision',NULL,NULL,0,'header','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (13,2,'Leadership','leadership-team',NULL,NULL,0,'header','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (14,2,'NGO Details','ngo-details',NULL,NULL,0,'header','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (20,3,'All Programs','programs',NULL,NULL,0,'header','_self',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (21,3,'Causes We Support','causes',NULL,NULL,0,'header','_self',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (22,3,'Schemes','schemes',NULL,NULL,0,'header','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (23,3,'Scholarships','scholarship',NULL,NULL,0,'header','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (24,3,'Campaigns','campaigns',NULL,NULL,0,'header','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (25,3,'Our Projects','projects',NULL,NULL,0,'header','_self',6,1,'2026-07-24 07:14:38','2026-07-24 07:14:38');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (26,3,'Free Courses','courses',NULL,NULL,0,'header','_self',7,1,'2026-07-24 07:14:38','2026-07-24 07:14:38');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (30,4,'Photo Gallery','gallery',NULL,NULL,0,'header','_self',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (31,4,'Videos','media',NULL,NULL,0,'header','_self',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (32,4,'Blog','blogs',NULL,NULL,0,'header','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (33,4,'News & Media','news-media',NULL,NULL,0,'header','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (34,4,'Testimonials','testimonials',NULL,NULL,0,'header','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (40,5,'Volunteer','volunteer',NULL,NULL,0,'header','_self',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (41,5,'Internship','internship',NULL,NULL,0,'header','_self',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (42,5,'Membership','membership',NULL,NULL,0,'header','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (43,5,'Careers','career',NULL,NULL,0,'header','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (44,5,'Become a Partner','become-partner',NULL,NULL,0,'header','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (50,NULL,'About Us','about',NULL,NULL,0,'footer','_self',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (51,NULL,'Our Programs','programs',NULL,NULL,0,'footer','_self',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (52,NULL,'Events','events',NULL,NULL,0,'footer','_self',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (53,NULL,'Blog','blogs',NULL,NULL,0,'footer','_self',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (54,NULL,'Verify Certificate','verify-certificate',NULL,NULL,0,'footer','_self',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (55,NULL,'Contact','contact',NULL,NULL,0,'footer','_self',6,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `menus` (`id`, `parent_id`, `title`, `url`, `page_key`, `icon`, `mega`, `location`, `target`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (56,NULL,'Free Courses','courses',NULL,NULL,0,'footer','_self',7,1,'2026-07-24 07:14:38','2026-07-24 07:14:38');

INSERT INTO `hero_slides` (`id`, `title`, `badge_text`, `badge_icon`, `subtitle`, `highlight`, `typing_words`, `description`, `accent`, `bg_type`, `bg_from`, `bg_to`, `bg_angle`, `bg_video`, `overlay`, `image`, `button_text`, `button_url`, `btn_style`, `btn_icon`, `button2_text`, `button2_url`, `btn2_style`, `btn2_icon`, `trust_text`, `rating`, `rating_count`, `divider`, `animate`, `text_align`, `layout`, `hero_image`, `height`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Empowering Communities, Creating {Lasting Change}','Non-Profit Organization','shield-check','Together We Can','','','EDUSKILL INDIA FOUNDATION works across Bihar to bring education, healthcare and opportunity to those who need it most.','#FFE987','solid','#0B4E3D','#0F4537',135,NULL,0,'','Donate Now','/donate','gradient','heart','Explore Our Programs','/volunteer','outline','arrow-right','Section 8 Company | MCA Registered | NITI Aayog Darpan',4.9,1200,'none',1,'left','split','slides/hero-community-village-family.webp','tall',1,1,'2026-07-21 21:33:35','2026-08-10 12:50:43');
INSERT INTO `hero_slides` (`id`, `title`, `badge_text`, `badge_icon`, `subtitle`, `highlight`, `typing_words`, `description`, `accent`, `bg_type`, `bg_from`, `bg_to`, `bg_angle`, `bg_video`, `overlay`, `image`, `button_text`, `button_url`, `btn_style`, `btn_icon`, `button2_text`, `button2_url`, `btn2_style`, `btn2_icon`, `trust_text`, `rating`, `rating_count`, `divider`, `animate`, `text_align`, `layout`, `hero_image`, `height`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Learning That Unlocks {Every Future}','Education For Every Child','graduation-cap','Learning Changes Lives',NULL,'Education, Skill Training, Scholarships, Digital Literacy','Books, uniforms, digital classrooms and scholarships — removing every barrier that keeps a child out of school.','#FFE987','solid','#0B4E3D','#0F4537',135,NULL,0,'','Support Education','/programs','gradient','graduation-cap','Our Programs','/programs','outline','arrow-right','25,000+ lives impacted across Bihar',4.9,1200,'none',1,'left','split','slides/hero-children-learning.webp','tall',2,1,'2026-07-21 21:33:35','2026-08-10 12:50:43');
INSERT INTO `hero_slides` (`id`, `title`, `badge_text`, `badge_icon`, `subtitle`, `highlight`, `typing_words`, `description`, `accent`, `bg_type`, `bg_from`, `bg_to`, `bg_angle`, `bg_video`, `overlay`, `image`, `button_text`, `button_url`, `btn_style`, `btn_icon`, `button2_text`, `button2_url`, `btn2_style`, `btn2_icon`, `trust_text`, `rating`, `rating_count`, `divider`, `animate`, `text_align`, `layout`, `hero_image`, `height`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Care That Reaches {Every Village}','Healthcare Within Reach','heart','Health for All',NULL,NULL,'Free medical camps, essential medicines and health awareness delivered directly to rural communities.','#FFE987','solid','#0B4E3D','#0F4537',135,NULL,0,'','Donate Now','/donate','gradient','heart','Talk To Us','/contact','outline','arrow-right','60+ villages reached',4.9,1200,'none',1,'left','split','slides/hero-village-community-care.webp','tall',3,1,'2026-07-21 21:33:35','2026-08-10 12:50:43');

INSERT INTO `pages` (`id`, `title`, `slug`, `subtitle`, `content`, `blocks`, `banner_image`, `template`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Privacy Policy','privacy-policy','How we protect your data','<h2>Introduction</h2><p>EDUSKILL INDIA FOUNDATION respects your privacy and is committed to protecting your personal data.</p><h2>Information We Collect</h2><p>We collect information you provide when donating, volunteering or contacting us.</p><h2>How We Use It</h2><p>Your data is used only to process your request and communicate with you. We never sell your data.</p><h2>Contact</h2><p>Questions? Email info@eduskillindia.org.</p>',NULL,NULL,'default','published','2026-07-21 21:33:36','2026-07-26 17:12:07',NULL);
INSERT INTO `pages` (`id`, `title`, `slug`, `subtitle`, `content`, `blocks`, `banner_image`, `template`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,'Terms & Conditions','terms','Terms of use','<h2>Acceptance</h2><p>By using this website you agree to these terms.</p><h2>Donations</h2><p>Donations are voluntary and used to further our charitable objectives.</p><h2>Governing Law</h2><p>These terms are governed by the laws of India, jurisdiction Patna, Bihar.</p>',NULL,NULL,'default','published','2026-07-21 21:33:36','2026-08-07 19:08:44',NULL);

INSERT INTO `announcements` (`id`, `message`, `link_text`, `link_url`, `bg_color`, `text_color`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES (1,'Our Annual Charity Run is on 15 Sep 2026 — register today!','Register','events','','',NULL,NULL,1,'2026-07-21 21:33:36','2026-08-10 12:52:47');

INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Education for All','education-for-all','Free schooling, scholarships and learning kits for underprivileged children across Bihar.','<p>Our flagship education program provides free schooling, digital learning kits, scholarships and mentorship to children from underserved communities.</p>','book-open','programs/students-classroom-learning.webp','#166534',1,1,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);
INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,'Healthcare & Camps','healthcare-and-camps','Free medical camps, health awareness and access to essential care in rural areas.','<p>We run regular free medical camps, health-awareness drives and facilitate access to essential care in rural and underserved areas.</p>','stethoscope','programs/community-health-care-camp.webp','#0d9488',1,2,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);
INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (3,'Women Empowerment','women-empowerment','Skill training, self-help groups and livelihood support for women.','<p>Through skill training, self-help groups and micro-livelihood support, we help women achieve financial independence and dignity.</p>','user-round','programs/women-farmers-livelihood.webp','#d9a82e',1,3,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);
INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (4,'Skill Development','skill-development','Vocational training that prepares youth for sustainable employment.','<p>Vocational and digital-skill training programs that prepare youth for sustainable employment and entrepreneurship.</p>','wrench','programs/women-handloom-skills.webp','#15803d',0,4,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);
INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (5,'Clean Water & Sanitation','clean-water-sanitation','Safe drinking water, hygiene drives and sanitation infrastructure.','<p>Ensuring safe drinking water, hygiene awareness and sanitation infrastructure in villages across Bihar.</p>','droplets','programs/village-handpump-clean-water.webp','#0d9488',0,5,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);
INSERT INTO `programs` (`id`, `title`, `slug`, `short_description`, `description`, `icon`, `image`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (6,'Relief & Rehabilitation','relief-rehabilitation','Rapid disaster relief, food distribution and long-term rehabilitation.','<p>Rapid relief during floods and disasters, food and essentials distribution, and long-term rehabilitation support.</p>','life-buoy','programs/community-food-relief-distribution.webp','#dc2626',0,6,'active','2026-07-21 21:33:35','2026-08-10 13:16:27',NULL);

INSERT INTO `projects` (`id`, `program_id`, `title`, `slug`, `summary`, `description`, `image`, `location`, `start_date`, `end_date`, `budget`, `beneficiaries`, `progress`, `status`, `is_featured`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,1,'Shiksha Setu — Rural Schools','shiksha-setu','Bridging the learning gap in 25 rural schools.',NULL,'projects/village-school-hillside.webp','Patna & Nalanda, Bihar',NULL,NULL,NULL,3200,72,'ongoing',1,1,'2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);
INSERT INTO `projects` (`id`, `program_id`, `title`, `slug`, `summary`, `description`, `image`, `location`, `start_date`, `end_date`, `budget`, `beneficiaries`, `progress`, `status`, `is_featured`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,2,'Swasthya Shivir — Health Camps','swasthya-shivir','Monthly free health camps across 12 villages.',NULL,'programs/community-health-care-camp.webp','Bihar',NULL,NULL,NULL,8500,60,'ongoing',0,2,'2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);
INSERT INTO `projects` (`id`, `program_id`, `title`, `slug`, `summary`, `description`, `image`, `location`, `start_date`, `end_date`, `budget`, `beneficiaries`, `progress`, `status`, `is_featured`, `sort_order`, `created_at`, `updated_at`, `deleted_at`) VALUES (3,3,'Nari Shakti — Skill Centres','nari-shakti','Tailoring and computer skill centres for women.',NULL,'gallery/tailoring-skills-class.webp','Patna, Bihar',NULL,NULL,NULL,450,88,'ongoing',1,3,'2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);

INSERT INTO `schemes` (`id`, `title`, `slug`, `category`, `department`, `short_description`, `description`, `eligibility`, `benefits`, `documents_required`, `apply_url`, `image`, `deadline`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Girl Child Education Scheme','girl-child-education','Education','State Government','Financial support for girl-child education.','<p>Supports the education of girl children from economically weaker sections.</p>','Girl children from BPL families; enrolled in school','Annual scholarship, learning kit, mentorship',NULL,NULL,'images/girls-studying-classroom.webp',NULL,0,0,'active','2026-07-21 21:33:36','2026-08-08 12:48:06');
INSERT INTO `schemes` (`id`, `title`, `slug`, `category`, `department`, `short_description`, `description`, `eligibility`, `benefits`, `documents_required`, `apply_url`, `image`, `deadline`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Skill India Youth Program','skill-india-youth','Skill','Central Government','Free vocational training for youth.','<p>Free skill training and certification for youth aged 18-35.</p>','Youth aged 18-35; unemployed','Free training, certification, placement support',NULL,NULL,'images/computer-lab-digital-literacy.webp',NULL,0,0,'active','2026-07-21 21:33:36','2026-08-08 12:48:06');
INSERT INTO `schemes` (`id`, `title`, `slug`, `category`, `department`, `short_description`, `description`, `eligibility`, `benefits`, `documents_required`, `apply_url`, `image`, `deadline`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Rural Health Initiative','rural-health-initiative','Health','State Government','Subsidised healthcare in rural areas.','<p>Access to subsidised healthcare and free camps for rural families.</p>','Residents of notified rural areas','Free check-ups, subsidised medicines',NULL,NULL,'programs/community-health-care-camp.webp',NULL,0,0,'active','2026-07-21 21:33:36','2026-08-08 12:48:06');

INSERT INTO `campaigns` (`id`, `title`, `slug`, `short_description`, `category`, `location`, `description`, `image`, `goal_amount`, `milestones`, `raised_amount`, `donor_count`, `start_date`, `end_date`, `closed_at`, `is_featured`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'Educate a Child','educate-a-child','Sponsor a child\'s education for one year.',NULL,NULL,NULL,'campaigns/children-classroom-desks.webp',500000.00,'[125000,250000,375000]',5000.00,1,'2026-01-01','2026-12-31',NULL,1,'active','2026-07-21 21:33:35','2026-08-08 12:14:35',NULL);
INSERT INTO `campaigns` (`id`, `title`, `slug`, `short_description`, `category`, `location`, `description`, `image`, `goal_amount`, `milestones`, `raised_amount`, `donor_count`, `start_date`, `end_date`, `closed_at`, `is_featured`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,'Health for Villages','health-for-villages','Fund free medical camps across rural Bihar.',NULL,NULL,NULL,'programs/community-health-care-camp.webp',300000.00,'[75000,150000,225000]',2500.00,1,'2026-02-01','2026-11-30',NULL,1,'active','2026-07-21 21:33:35','2026-08-08 12:14:35',NULL);
INSERT INTO `campaigns` (`id`, `title`, `slug`, `short_description`, `category`, `location`, `description`, `image`, `goal_amount`, `milestones`, `raised_amount`, `donor_count`, `start_date`, `end_date`, `closed_at`, `is_featured`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES (3,'Flood Relief Fund','flood-relief','Emergency relief for flood-affected families.',NULL,NULL,NULL,'campaigns/flood-affected-community-relief.webp',1000000.00,'[250000,500000,750000]',0.00,0,'2026-03-01','2026-10-31',NULL,0,'active','2026-07-21 21:33:35','2026-08-08 12:14:35',NULL);

INSERT INTO `achievements` (`id`, `title`, `description`, `icon`, `value`, `suffix`, `prefix`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Lives Impacted',NULL,'users',25000,'+',NULL,1,1,'2026-07-21 21:33:35','2026-08-10 13:16:27');
INSERT INTO `achievements` (`id`, `title`, `description`, `icon`, `value`, `suffix`, `prefix`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Projects Completed',NULL,'graduation-cap',120,'+',NULL,2,1,'2026-07-21 21:33:35','2026-08-10 13:16:27');
INSERT INTO `achievements` (`id`, `title`, `description`, `icon`, `value`, `suffix`, `prefix`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Active Volunteers',NULL,'handshake',800,'+',NULL,3,1,'2026-07-21 21:33:35','2026-08-10 13:16:27');
INSERT INTO `achievements` (`id`, `title`, `description`, `icon`, `value`, `suffix`, `prefix`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'Villages Reached',NULL,'home',60,'+',NULL,4,1,'2026-07-21 21:33:35','2026-08-10 13:17:03');

INSERT INTO `certificates` (`id`, `title`, `description`, `image`, `issued_by`, `issue_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Certificate of Registration','Registered non-profit under Section 8.','','Ministry of Corporate Affairs','2025-01-15',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `certificates` (`id`, `title`, `description`, `image`, `issued_by`, `issue_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'80G Tax Exemption','Donations eligible for tax deduction.','','Income Tax Department','2025-03-01',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `certificates` (`id`, `title`, `description`, `image`, `issued_by`, `issue_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'12A Registration','Registered charitable organisation.','','Income Tax Department','2025-03-01',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `scholarships` (`id`, `title`, `slug`, `description`, `eligibility`, `amount`, `level`, `deadline`, `image`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Merit Scholarship','merit-scholarship','For meritorious students from low-income families.','Family income < ₹2L; 75%+ marks','₹10,000/year','School & College','2026-12-31','projects/village-school-hillside.webp',0,'open','2026-07-21 21:33:36','2026-08-08 12:48:06');
INSERT INTO `scholarships` (`id`, `title`, `slug`, `description`, `eligibility`, `amount`, `level`, `deadline`, `image`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Girl Child Scholarship','girl-child-scholarship','Encouraging girl-child education.','Girl students; enrolled full-time','₹8,000/year','School','2026-12-31','images/girls-studying-classroom.webp',0,'open','2026-07-21 21:33:36','2026-08-08 12:48:06');

INSERT INTO `careers` (`id`, `title`, `slug`, `department`, `location`, `type`, `description`, `requirements`, `salary_range`, `openings`, `deadline`, `status`, `created_at`, `updated_at`) VALUES (1,'Program Coordinator','program-coordinator','Programs','Patna, Bihar','full-time','<p>Coordinate and monitor field programs and volunteers.</p>','<p>Graduate; 2+ years in NGO/social sector; fluent Hindi &amp; English.</p>',NULL,2,'2026-09-30','open','2026-07-21 21:33:36','2026-07-21 21:33:36');
INSERT INTO `careers` (`id`, `title`, `slug`, `department`, `location`, `type`, `description`, `requirements`, `salary_range`, `openings`, `deadline`, `status`, `created_at`, `updated_at`) VALUES (2,'Field Volunteer','field-volunteer','Operations','Bihar','volunteer','<p>Support field activities, camps and events.</p>','<p>Passion for social work; willingness to travel.</p>',NULL,10,'2026-12-31','open','2026-07-21 21:33:36','2026-07-21 21:33:36');
INSERT INTO `careers` (`id`, `title`, `slug`, `department`, `location`, `type`, `description`, `requirements`, `salary_range`, `openings`, `deadline`, `status`, `created_at`, `updated_at`) VALUES (3,'Content & Communications Intern','comms-intern','Communications','Remote','internship','<p>Create content, manage social media and document impact.</p>','<p>Student/fresher; strong writing skills.</p>',NULL,3,'2026-10-15','open','2026-07-21 21:33:36','2026-07-21 21:33:36');

INSERT INTO `membership_plans` (`id`, `name`, `slug`, `price`, `duration`, `duration_days`, `tier_level`, `benefits`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Supporter','supporter',500.00,'Annual',365,1,'Newsletter updates\nInvitation to events\nAnnual impact report',NULL,0,1,1,'2026-07-21 21:33:36','2026-07-21 21:33:36');
INSERT INTO `membership_plans` (`id`, `name`, `slug`, `price`, `duration`, `duration_days`, `tier_level`, `benefits`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Member','member',1000.00,'Annual',365,1,'All Supporter benefits\nMembership certificate\nVoting rights at AGM',NULL,1,2,1,'2026-07-21 21:33:36','2026-07-21 21:33:36');
INSERT INTO `membership_plans` (`id`, `name`, `slug`, `price`, `duration`, `duration_days`, `tier_level`, `benefits`, `color`, `is_featured`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Patron','patron',5000.00,'Annual',365,1,'All Member benefits\nRecognition on website\nPersonal impact briefing',NULL,0,3,1,'2026-07-21 21:33:36','2026-07-21 21:33:36');

INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (1,'World Health Day','Promoting health awareness in our communities.','2026-04-07',NULL,'Health','#0ea5e9',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (2,'International Women\'s Day','Celebrating and empowering women.','2026-03-08',NULL,'Social','#ec4899',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (3,'World Environment Day','Tree plantation and clean-up drives.','2026-06-05',NULL,'Environment','#16a34a',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (4,'International Literacy Day','Championing education for all.','2026-09-08',NULL,'Education','#2563eb',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (5,'World Water Day','Clean water access awareness.','2026-03-22',NULL,'Environment','#0891b2',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `awareness_calendar` (`id`, `title`, `description`, `event_date`, `end_date`, `category`, `color`, `is_recurring`, `status`, `created_at`, `updated_at`) VALUES (6,'International Day of Charity','Celebrating giving and volunteering.','2026-09-05',NULL,'Social','#f59e0b',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `blogs` (`id`, `category_id`, `author_id`, `title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `og_image`, `canonical_url`, `content`, `featured_image`, `views`, `is_featured`, `is_breaking`, `is_sticky`, `reading_time`, `status`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,5,1,'How 500 Children Returned to School This Year','children-return-to-school','Our Shiksha Setu program brought 500 out-of-school children back to classrooms across rural Bihar.',NULL,NULL,NULL,NULL,'<p>This year, through our Shiksha Setu program, more than 500 children who had dropped out of school returned to the classroom. Working closely with families, teachers and local volunteers, we provided learning kits, uniforms and mentorship.</p><p>Each child\'s journey is a reminder that access to education transforms not just one life, but an entire community.</p>','images/girls-studying-classroom.webp',35,1,0,0,NULL,'published','2026-05-10 09:00:00','2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);
INSERT INTO `blogs` (`id`, `category_id`, `author_id`, `title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `og_image`, `canonical_url`, `content`, `featured_image`, `views`, `is_featured`, `is_breaking`, `is_sticky`, `reading_time`, `status`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (2,4,1,'Free Health Camp Screens 1,200 Villagers','health-camp-1200','A single weekend health camp in Nalanda screened 1,200 people and referred 80 for further care.',NULL,NULL,NULL,NULL,'<p>Our monthly Swasthya Shivir health camp reached a new milestone, screening 1,200 villagers over a single weekend and referring 80 for specialised care.</p>','programs/community-health-care-camp.webp',25,0,0,0,NULL,'published','2026-04-22 10:30:00','2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);
INSERT INTO `blogs` (`id`, `category_id`, `author_id`, `title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `og_image`, `canonical_url`, `content`, `featured_image`, `views`, `is_featured`, `is_breaking`, `is_sticky`, `reading_time`, `status`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (3,3,1,'From Beneficiary to Entrepreneur: Anita\'s Story','anita-story','After training at our Nari Shakti centre, Anita now runs a thriving tailoring business.',NULL,NULL,NULL,NULL,'<p>Anita joined our Nari Shakti skill centre two years ago. Today she runs her own tailoring business, employs two women from her village, and supports her family with dignity.</p>','blogs/woman-textile-entrepreneur.webp',77,1,0,0,NULL,'published','2026-03-15 08:00:00','2026-07-21 21:33:35','2026-08-08 18:35:03',NULL);
INSERT INTO `blogs` (`id`, `category_id`, `author_id`, `title`, `slug`, `excerpt`, `meta_title`, `meta_description`, `og_image`, `canonical_url`, `content`, `featured_image`, `views`, `is_featured`, `is_breaking`, `is_sticky`, `reading_time`, `status`, `published_at`, `created_at`, `updated_at`, `deleted_at`) VALUES (4,1,1,'EDUSKILL INDIA FOUNDATION Launches Clean Water Drive','clean-water-drive','A new initiative to install safe drinking-water points in 20 villages.',NULL,NULL,NULL,NULL,'<p>We are proud to announce the launch of our Clean Water &amp; Sanitation drive, aiming to install safe drinking-water points in 20 villages this year.</p>','blogs/rural-water-collection.webp',31,0,0,0,NULL,'published','2026-06-01 11:00:00','2026-07-21 21:33:35','2026-08-08 12:48:06',NULL);

INSERT INTO `blog_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,NULL,'News','news','Latest news and updates',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `blog_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,NULL,'Stories','stories','Field stories and reflections',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `blog_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,NULL,'Success Stories','success-stories','Lives changed through our work',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `blog_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,NULL,'Health','health','Health awareness and camps',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `blog_categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,NULL,'Education','education','Education initiatives',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (1,'Education','education','2026-07-21 21:33:35');
INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (2,'Health','health','2026-07-21 21:33:35');
INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (3,'Women','women','2026-07-21 21:33:35');
INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (4,'Volunteering','volunteering','2026-07-21 21:33:35');
INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (5,'Bihar','bihar','2026-07-21 21:33:35');
INSERT INTO `blog_tags` (`id`, `name`, `slug`, `created_at`) VALUES (6,'Impact','impact','2026-07-21 21:33:35');

INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (1,1);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (1,5);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (1,6);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (2,2);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (2,5);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (3,3);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (3,6);
INSERT INTO `blog_tag_map` (`blog_id`, `tag_id`) VALUES (4,5);

INSERT INTO `gallery_albums` (`id`, `title`, `slug`, `description`, `cover_image`, `event_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Health Camp 2026','health-camp-2026','Moments from our free health camp.','programs/community-health-care-camp.webp','2026-04-07',1,1,'2026-07-21 21:33:35','2026-08-08 12:14:35');
INSERT INTO `gallery_albums` (`id`, `title`, `slug`, `description`, `cover_image`, `event_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Education Drive','education-drive','Distributing learning kits to children.','campaigns/children-classroom-desks.webp','2026-05-10',2,1,'2026-07-21 21:33:35','2026-08-08 12:14:35');
INSERT INTO `gallery_albums` (`id`, `title`, `slug`, `description`, `cover_image`, `event_date`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Women Empowerment','women-empowerment-gallery','Skill training and self-help groups.','gallery/tailoring-skills-class.webp','2026-03-08',3,1,'2026-07-21 21:33:35','2026-08-08 12:14:35');

INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (1,1,'Registration desk','programs/village-handpump-clean-water.webp','image',NULL,1,'2026-07-21 21:33:35');
INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (2,1,'Doctor consultation','programs/community-health-care-camp.webp','image',NULL,2,'2026-07-21 21:33:35');
INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (3,2,'Kit distribution','gallery/supply-kit-distribution.webp','image',NULL,1,'2026-07-21 21:33:35');
INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (4,2,'Classroom','campaigns/children-classroom-desks.webp','image',NULL,2,'2026-07-21 21:33:35');
INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (5,3,'Tailoring class','gallery/tailoring-skills-class.webp','image',NULL,1,'2026-07-21 21:33:35');
INSERT INTO `gallery_media` (`id`, `album_id`, `title`, `file_path`, `type`, `caption`, `sort_order`, `created_at`) VALUES (6,3,'Group meeting','programs/women-farmers-livelihood.webp','image',NULL,2,'2026-07-21 21:33:35');

INSERT INTO `videos` (`id`, `title`, `slug`, `description`, `youtube_id`, `video_url`, `thumbnail`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Our Journey So Far','our-journey','A look at the impact we\'ve created together.','dQw4w9WgXcQ',NULL,NULL,'Impact',1,0,'2026-07-21 21:33:35','2026-07-24 07:22:05');
INSERT INTO `videos` (`id`, `title`, `slug`, `description`, `youtube_id`, `video_url`, `thumbnail`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Voices from the Field','voices-field','Beneficiaries share their stories.','dQw4w9WgXcQ',NULL,NULL,'Stories',2,0,'2026-07-21 21:33:35','2026-07-24 07:22:05');

INSERT INTO `documents` (`id`, `title`, `slug`, `description`, `file_path`, `file_type`, `file_size`, `category`, `downloads`, `status`, `created_at`, `updated_at`) VALUES (1,'Annual Report 2025-26','annual-report-2025-26','Our impact, finances and highlights for the year.','documents/eduskill-brochure.txt','txt',NULL,'report',16,1,'2026-07-21 21:33:36','2026-08-08 17:57:53');
INSERT INTO `documents` (`id`, `title`, `slug`, `description`, `file_path`, `file_type`, `file_size`, `category`, `downloads`, `status`, `created_at`, `updated_at`) VALUES (2,'Organisation Brochure','organisation-brochure','An overview of who we are and what we do.','documents/eduskill-brochure.txt','txt',NULL,'brochure',9,1,'2026-07-21 21:33:36','2026-08-10 13:30:16');
INSERT INTO `documents` (`id`, `title`, `slug`, `description`, `file_path`, `file_type`, `file_size`, `category`, `downloads`, `status`, `created_at`, `updated_at`) VALUES (3,'Volunteer Handbook','volunteer-handbook','Everything a new volunteer needs to know.','documents/eduskill-brochure.txt','txt',NULL,'general',7,1,'2026-07-21 21:33:36','2026-08-07 18:16:20');

INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (1,'Membership Certificate','membership-certificate','certificate','Membership Certificate','landscape','classic','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of membership at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Membership No</span><span class=\"dc-value\">{{membership_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid Till</span><span class=\"dc-value\">{{expiry_date}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,NULL,1,1,1,1,0,NULL,'MEMC',2,'published',1,1,NULL,'2026-07-24 08:56:32','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (2,'Volunteer ID Card','volunteer-id-card','id_card','Volunteer ID Card','id_vertical','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Volunteer ID Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,NULL,1,1,1,1,1,NULL,'VOL',1,'published',1,1,NULL,'2026-07-24 08:56:32','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (3,'Donation Receipt','donation-receipt-doc','receipt','Donation Receipt','portrait','minimal','<p>Received with thanks from</p><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-section\">Payment Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Receipt No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Payment Mode</span><span class=\"dc-value\">{{purpose}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference No</span><span class=\"dc-value\">{{reference_no}}</span></div></div><table class=\"doc-table\"><thead><tr><th>Description</th><th class=\"num\">Amount</th></tr></thead><tbody><tr><td>{{purpose}}</td><td class=\"num\">{{amount}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">{{amount}}</td></tr></tfoot></table><p style=\"text-align:left\"><b>Amount in words:</b> {{amount_words}}</p><p style=\"text-align:left\"><b>PAN:</b> {{pan}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',1,'<p>This receipt is computer generated. Donations to {{organization_name}} may be eligible for tax exemption under applicable law.</p>',1,1,1,1,0,NULL,'RCPT',1,'published',1,1,NULL,'2026-07-24 08:56:32','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (4,'Membership ID Card','membership-id-card','id_card','Membership ID Card','id_vertical','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Membership ID Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','MEMID',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (5,'Volunteer Certificate','volunteer-certificate','certificate','Volunteer Certificate','landscape','ngo','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of volunteer at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Hours Served</span><span class=\"dc-value\">{{volunteer_hours}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','VOLC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (6,'Volunteer Badge','volunteer-badge','id_card','Volunteer Badge','id_vertical','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Volunteer Badge</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','VBADGE',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (7,'Internship Certificate','internship-certificate','certificate','Internship Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of internship at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','INTC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (8,'Internship Offer Letter','internship-offer-letter','letter','Internship Offer Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Internship Offer Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','INTOL',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (9,'Internship Completion Certificate','internship-completion-certificate','certificate','Internship Completion Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of internship Completion at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','INTCC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (10,'Appreciation Certificate','appreciation-certificate','certificate','Appreciation Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of appreciation at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','APPC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (11,'Participation Certificate','participation-certificate','certificate','Participation Certificate','landscape','blue','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of participation at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PARTC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (12,'Recognition Certificate','recognition-certificate','certificate','Recognition Certificate','landscape','premium','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of recognition at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','RECC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (13,'Award Certificate','award-certificate','certificate','Award Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of award at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','AWDC',2,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (14,'Excellence Certificate','excellence-certificate','certificate','Excellence Certificate','landscape','luxury','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of excellence at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','EXCC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (15,'Donation Certificate','donation-certificate','certificate','Donation Certificate','landscape','ngo','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of donation at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Amount</span><span class=\"dc-value\">{{amount}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Purpose</span><span class=\"dc-value\">{{purpose}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','DONC',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (16,'Donation Tax Receipt','donation-tax-receipt','receipt','Donation Tax Receipt','portrait','corporate','<p>Received with thanks from</p><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-section\">Payment Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Receipt No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Payment Mode</span><span class=\"dc-value\">{{purpose}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference No</span><span class=\"dc-value\">{{reference_no}}</span></div></div><table class=\"doc-table\"><thead><tr><th>Description</th><th class=\"num\">Amount</th></tr></thead><tbody><tr><td>{{purpose}}</td><td class=\"num\">{{amount}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">{{amount}}</td></tr></tfoot></table><p style=\"text-align:left\"><b>Amount in words:</b> {{amount_words}}</p><p style=\"text-align:left\"><b>PAN:</b> {{pan}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','TAXR',1,'published',1,1,1,'2026-07-24 12:56:36','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (17,'Partnership Certificate','partnership-certificate','certificate','Partnership Certificate','landscape','premium','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of partnership at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PARC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (18,'Partnership Agreement','partnership-agreement','letter','Partnership Agreement','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Partnership Agreement</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PAGR',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (19,'Sponsor Certificate','sponsor-certificate','certificate','Sponsor Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of sponsor at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Amount</span><span class=\"dc-value\">{{amount}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Purpose</span><span class=\"dc-value\">{{purpose}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','SPNC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (20,'Sponsor Appreciation Letter','sponsor-appreciation-letter','letter','Sponsor Appreciation Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Sponsor Appreciation Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','SPNL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (21,'Sponsor Agreement','sponsor-agreement','letter','Sponsor Agreement','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Sponsor Agreement</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','SPNA',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (22,'Event Participation Certificate','event-participation-certificate','certificate','Event Participation Certificate','landscape','blue','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of event Participation at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','EVPC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (23,'Event Pass','event-pass','pass','Event Pass','id_horizontal','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Event Pass</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','EVPASS',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (24,'Event Entry Card','event-entry-card','pass','Event Entry Card','id_horizontal','blue','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Event Entry Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','EVENT',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (25,'Workshop Certificate','workshop-certificate','certificate','Workshop Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of workshop at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','WKSC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (26,'Training Certificate','training-certificate','certificate','Training Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of training at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','TRNC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (27,'Appointment Letter','appointment-letter','letter','Appointment Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Appointment Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','APPTL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (28,'Joining Letter','joining-letter','letter','Joining Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Joining Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','JOINL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (29,'Relieving Letter','relieving-letter','letter','Relieving Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Relieving Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','RELVL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (30,'Experience Letter','experience-letter','letter','Experience Letter','portrait','corporate','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Experience Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','EXPL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (31,'Recommendation Letter','recommendation-letter','letter','Recommendation Letter','portrait','premium','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Recommendation Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','RECL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (32,'Appreciation Letter','appreciation-letter','letter','Appreciation Letter','portrait','premium','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Appreciation Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','APPL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (33,'Appreciation Shield Certificate','appreciation-shield-certificate','certificate','Appreciation Shield Certificate','landscape','luxury','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of appreciation Shield at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','SHLD',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (34,'Staff ID Card','staff-id-card','id_card','Staff ID Card','id_vertical','corporate','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Staff ID Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','STAFF',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (35,'Employee ID Card','employee-id-card','id_card','Employee ID Card','id_vertical','corporate','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Employee ID Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','EMPID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (36,'Visitor Pass','visitor-pass','pass','Visitor Pass','id_horizontal','minimal','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Visitor Pass</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','VISIT',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (37,'Press Pass','press-pass','pass','Press Pass','id_horizontal','corporate','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Press Pass</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','PRESS',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (38,'Admission Letter','admission-letter','letter','Admission Letter','portrait','educational','<p style=\"text-align:left\"><b>Ref:</b> {{certificate_no}} &nbsp;&bull;&nbsp; <b>Date:</b> {{issue_date}}</p><p style=\"text-align:left\">To,<br><b>{{member_name}}</b><br>{{address}}</p><div class=\"doc-section\">Subject: Admission Letter</div><p style=\"text-align:left\">Dear {{member_name}},</p><p style=\"text-align:left\">This letter is issued in respect of <b>{{course_name}}</b> at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Designation</span><span class=\"dc-value\">{{designation}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Valid From</span><span class=\"dc-value\">{{valid_from}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference</span><span class=\"dc-value\">{{reference_no}}</span></div></div><p style=\"text-align:left\">We wish you continued success.</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ADML',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (39,'Admission Confirmation','admission-confirmation','certificate','Admission Confirmation','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of admission Confirmation at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ADMCF',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (40,'Student ID Card','student-id-card','id_card','Student ID Card','id_vertical','educational','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Student ID Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','STUID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (41,'Student Profile','student-profile','report','Student Profile','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','STUP',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (42,'Enrollment Certificate','enrollment-certificate','certificate','Enrollment Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of enrollment at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ENRC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (43,'Bonafide Certificate','bonafide-certificate','certificate','Bonafide Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of bonafide at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','BONA',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (44,'Character Certificate','character-certificate','certificate','Character Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of character at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','CHARC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (45,'Transfer Certificate','transfer-certificate','certificate','Transfer Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of transfer at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','TC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (46,'Leaving Certificate','leaving-certificate','certificate','Leaving Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of leaving at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','LC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (47,'Marksheet','marksheet','report','Marksheet','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','MRKS',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (48,'Grade Card','grade-card','report','Grade Card','portrait','blue','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','GRDC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (49,'Result Card','result-card','report','Result Card','portrait','blue','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','RESC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (50,'Progress Report','progress-report','report','Progress Report','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PROG',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (51,'Completion Certificate','completion-certificate','certificate','Completion Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of completion at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','COMP',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (52,'Course Certificate','course-certificate','certificate','Course Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{student_name}}</h2><p>in recognition of course at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','CRSC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (53,'Diploma Certificate','diploma-certificate','certificate','Diploma Certificate','landscape','luxury','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of diploma at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','DIPL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (54,'Assignment Submission Receipt','assignment-submission-receipt','receipt','Assignment Submission Receipt','portrait','minimal','<p>Received with thanks from</p><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-section\">Payment Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Receipt No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Payment Mode</span><span class=\"dc-value\">{{purpose}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference No</span><span class=\"dc-value\">{{reference_no}}</span></div></div><table class=\"doc-table\"><thead><tr><th>Description</th><th class=\"num\">Amount</th></tr></thead><tbody><tr><td>{{purpose}}</td><td class=\"num\">{{amount}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">{{amount}}</td></tr></tfoot></table><p style=\"text-align:left\"><b>Amount in words:</b> {{amount_words}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ASUB',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (55,'Assignment Evaluation Report','assignment-evaluation-report','report','Assignment Evaluation Report','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','AEVR',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (56,'Practical Certificate','practical-certificate','certificate','Practical Certificate','landscape','educational','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of practical at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PRAC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (57,'Hall Ticket','hall-ticket','pass','Hall Ticket','id_horizontal','blue','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Hall Ticket</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','HALL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (58,'Admit Card','admit-card','pass','Admit Card','id_horizontal','blue','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Admit Card</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Valid From:</b> {{valid_from}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','ADMIT',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (59,'Exam Attendance Sheet','exam-attendance-sheet','report','Exam Attendance Sheet','portrait','minimal','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ATTN',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (60,'Fee Receipt','fee-receipt','receipt','Fee Receipt','portrait','minimal','<p>Received with thanks from</p><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-section\">Payment Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Receipt No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Payment Mode</span><span class=\"dc-value\">{{purpose}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference No</span><span class=\"dc-value\">{{reference_no}}</span></div></div><table class=\"doc-table\"><thead><tr><th>Description</th><th class=\"num\">Amount</th></tr></thead><tbody><tr><td>{{purpose}}</td><td class=\"num\">{{amount}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">{{amount}}</td></tr></tfoot></table><p style=\"text-align:left\"><b>Amount in words:</b> {{amount_words}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','FEER',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (61,'Fee Invoice','fee-invoice','receipt','Fee Invoice','portrait','corporate','<p>Received with thanks from</p><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-section\">Payment Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Receipt No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Payment Mode</span><span class=\"dc-value\">{{purpose}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Reference No</span><span class=\"dc-value\">{{reference_no}}</span></div></div><table class=\"doc-table\"><thead><tr><th>Description</th><th class=\"num\">Amount</th></tr></thead><tbody><tr><td>{{purpose}}</td><td class=\"num\">{{amount}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">{{amount}}</td></tr></tfoot></table><p style=\"text-align:left\"><b>Amount in words:</b> {{amount_words}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','FEEIN',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (62,'Scholarship Certificate','scholarship-certificate','certificate','Scholarship Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of scholarship at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','SCHL',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (63,'Merit Certificate','merit-certificate','certificate','Merit Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of merit at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','MERIT',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (64,'Quiz Certificate','quiz-certificate','certificate','Quiz Certificate','landscape','blue','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{student_name}}</h2><p>in recognition of quiz at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','QZC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (65,'Quiz Result','quiz-result','report','Quiz Result','portrait','blue','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','QZR',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (66,'Quiz Scorecard','quiz-scorecard','report','Quiz Scorecard','portrait','blue','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','QZS',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (67,'Exam Result','exam-result','report','Exam Result','portrait','blue','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment Summary</div><table class=\"doc-table\"><thead><tr><th>Subject / Component</th><th class=\"num\">Max</th><th class=\"num\">Obtained</th><th>Grade</th></tr></thead><tbody><tr><td>{{course_name}}</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tbody><tfoot><tr><td>Total</td><td class=\"num\">100</td><td class=\"num\">{{marks}}</td><td>{{grade}}</td></tr></tfoot></table><div class=\"doc-section\">Result</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Marks Obtained</span><span class=\"dc-value\">{{marks}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Percentage</span><span class=\"dc-value\">{{percentage}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','EXR',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (68,'Exam Certificate','exam-certificate','certificate','Exam Certificate','landscape','blue','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{student_name}}</h2><p>in recognition of exam at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{program_name}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','EXC',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (69,'Assessment Report','assessment-report','report','Assessment Report','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','ASMT',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (70,'Rank Certificate','rank-certificate','certificate','Rank Certificate','landscape','gold','<p class=\"doc-sub\">This is proudly presented to</p><h2 class=\"doc-name\">{{member_name}}</h2><p>in recognition of rank at <b>{{organization_name}}</b>. {{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Certificate No</span><span class=\"dc-value\">{{certificate_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Issue Date</span><span class=\"dc-value\">{{issue_date}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Rank</span><span class=\"dc-value\">{{rank}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div></div><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','RANK',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:25');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (71,'Performance Report','performance-report','report','Performance Report','portrait','educational','<div class=\"doc-section\">Candidate Details</div><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Name</span><span class=\"dc-value\">{{student_name}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Roll No</span><span class=\"dc-value\">{{roll_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Registration No</span><span class=\"dc-value\">{{registration_no}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Programme</span><span class=\"dc-value\">{{course_name}}</span></div></div><div class=\"doc-section\">Assessment</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-cards\"><div class=\"doc-card\"><span class=\"dc-label\">Grade</span><span class=\"dc-value\">{{grade}}</span></div><div class=\"doc-card\"><span class=\"dc-label\">Date</span><span class=\"dc-value\">{{issue_date}}</span></div></div><div class=\"doc-section\">Remarks</div><p style=\"text-align:left\">{{remarks}}</p><div class=\"doc-footer\"><div>{{signature}}<div class=\"doc-sub\">{{signatory_name}}<br>{{signatory_designation}}</div></div><div>{{qr_code}}<div class=\"doc-sub\">Scan to verify</div></div><div>{{seal}}<div class=\"doc-sub\">Official Seal</div></div></div>',0,'',1,1,1,1,0,'','PERF',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (72,'NGO Member ID','ngo-member-id','id_card','NGO Member ID','id_horizontal','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">NGO Member ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','NGOID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (73,'Student ID','student-id','id_card','Student ID','id_horizontal','educational','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Student ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','STIDH',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (74,'Volunteer ID','volunteer-id','id_card','Volunteer ID','id_horizontal','ngo','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Volunteer ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','VOLID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (75,'Employee ID','employee-id','id_card','Employee ID','id_horizontal','corporate','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Employee ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','EMPIDH',2,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (76,'Staff ID','staff-id','id_card','Staff ID','id_horizontal','corporate','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Staff ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','STFIDH',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (77,'Trainer ID','trainer-id','id_card','Trainer ID','id_horizontal','premium','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Trainer ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','TRNID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (78,'Faculty ID','faculty-id','id_card','Faculty ID','id_horizontal','educational','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Faculty ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','FACID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (79,'Internship ID','internship-id','id_card','Internship ID','id_horizontal','blue','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Internship ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','INTID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (80,'Visitor ID','visitor-id','id_card','Visitor ID','id_horizontal','minimal','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Visitor ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','VISID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (81,'Partner ID','partner-id','id_card','Partner ID','id_horizontal','premium','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Partner ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','PARID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');
INSERT INTO `document_templates` (`id`, `name`, `slug`, `category`, `doc_type`, `layout`, `theme`, `body`, `terms_enabled`, `terms`, `show_qr`, `show_seal`, `show_signature`, `show_logo`, `show_watermark`, `watermark_text`, `number_prefix`, `number_next`, `status`, `admin_enabled`, `user_enabled`, `created_by`, `created_at`, `updated_at`) VALUES (82,'Sponsor ID','sponsor-id','id_card','Sponsor ID','id_horizontal','gold','<div style=\"text-align:center\">{{logo}}<h1>{{organization_name}}</h1><div class=\"doc-sub\">Sponsor ID</div><div style=\"margin:1.5mm 0\">{{photo}}</div><h2 class=\"doc-name\">{{member_name}}</h2><div class=\"doc-sub\" style=\"margin-bottom:1mm\">{{designation}}</div><p style=\"text-align:left\"><b>ID No:</b> {{id_no}}<br><b>Blood Group:</b> {{blood_group}}<br><b>Valid Till:</b> {{expiry_date}}<br><b>Contact:</b> {{emergency_contact}}</p><div class=\"doc-row\">{{qr_code}}{{barcode}}</div></div>',0,'',1,0,1,1,1,'EDUSKILL INDIA FOUNDATION','SPNID',1,'published',1,1,1,'2026-07-24 12:56:37','2026-08-07 21:49:26');

INSERT INTO `team_members` (`id`, `name`, `slug`, `designation`, `department`, `bio`, `photo`, `email`, `phone`, `socials`, `is_leadership`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Dr. Neha Gupta','neha-gupta','Head of Health Programs','Health','A public-health specialist leading our medical camps and health-awareness initiatives.','team/avatar-dr-neha-gupta.svg',NULL,NULL,NULL,0,3,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `team_members` (`id`, `name`, `slug`, `designation`, `department`, `bio`, `photo`, `email`, `phone`, `socials`, `is_leadership`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'Arjun Mehta','arjun-mehta','Education Coordinator','Education','Oversees the Shiksha Setu program and coordinates with schools and volunteers.','team/avatar-arjun-mehta.svg',NULL,NULL,NULL,0,4,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `team_members` (`id`, `name`, `slug`, `designation`, `department`, `bio`, `photo`, `email`, `phone`, `socials`, `is_leadership`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,'Priya Sinha','priya-sinha','Volunteer Manager','Operations','Manages our growing community of 800+ volunteers across Bihar.','team/avatar-priya-sinha.svg',NULL,NULL,NULL,0,5,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `team_members` (`id`, `name`, `slug`, `designation`, `department`, `bio`, `photo`, `email`, `phone`, `socials`, `is_leadership`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (6,'Rohit Anand','rohit-anand','Finance & Compliance','Administration','Ensures financial transparency, compliance and accountable use of every contribution.','team/avatar-rohit-anand.svg',NULL,NULL,NULL,0,6,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');

INSERT INTO `testimonials` (`id`, `name`, `designation`, `photo`, `message`, `rating`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Anita Kumari','Beneficiary, Patna','testimonials/avatar-anita-kumari.svg','The skill program changed my life — I now run my own tailoring business and support my family.',5,1,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `testimonials` (`id`, `name`, `designation`, `photo`, `message`, `rating`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Rakesh Singh','Volunteer','testimonials/avatar-rakesh-singh.svg','Volunteering with EduSkill India has been the most rewarding experience. The team truly cares about impact.',5,2,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `testimonials` (`id`, `name`, `designation`, `photo`, `message`, `rating`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'Dr. Meena Rao','Partner, Health Camp','testimonials/avatar-dr-meena-rao.svg','Their organisation and dedication during our medical camps was exceptional. Real change on the ground.',5,3,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');
INSERT INTO `testimonials` (`id`, `name`, `designation`, `photo`, `message`, `rating`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'Amit Verma','Donor','testimonials/avatar-amit-verma.svg','Transparent, accountable and genuinely impactful. I know exactly where my contribution goes.',5,4,1,'2026-07-21 21:33:35','2026-08-08 12:31:22');

INSERT INTO `partners` (`id`, `name`, `logo`, `website`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Bihar Rural Livelihoods',NULL,NULL,NULL,1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `partners` (`id`, `name`, `logo`, `website`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'CareIndia Trust',NULL,NULL,NULL,2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `partners` (`id`, `name`, `logo`, `website`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'EduBridge Foundation',NULL,NULL,NULL,3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `partners` (`id`, `name`, `logo`, `website`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'HealthFirst NGO',NULL,NULL,NULL,4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `partners` (`id`, `name`, `logo`, `website`, `description`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,'GreenEarth Collective',NULL,NULL,NULL,5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `sponsors` (`id`, `name`, `logo`, `website`, `tier`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'Tata Trusts',NULL,NULL,'platinum',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `sponsors` (`id`, `name`, `logo`, `website`, `tier`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Infosys Foundation',NULL,NULL,'gold',2,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `sponsors` (`id`, `name`, `logo`, `website`, `tier`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'HDFC Parivartan',NULL,NULL,'gold',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `sponsors` (`id`, `name`, `logo`, `website`, `tier`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'Local Business Council',NULL,NULL,'silver',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `social_links` (`id`, `platform`, `url`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'facebook','https://facebook.com/eduskillindia','f',1,1,'2026-07-21 21:33:36','2026-07-26 17:14:19');
INSERT INTO `social_links` (`id`, `platform`, `url`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'twitter','https://twitter.com/eduskillindia','𝕏',2,1,'2026-07-21 21:33:36','2026-07-26 17:14:19');
INSERT INTO `social_links` (`id`, `platform`, `url`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'instagram','https://instagram.com/eduskillindia','◎',3,1,'2026-07-21 21:33:36','2026-07-26 17:14:19');
INSERT INTO `social_links` (`id`, `platform`, `url`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'linkedin','https://linkedin.com/company/eduskillindia','in',4,1,'2026-07-21 21:33:36','2026-07-26 17:14:19');
INSERT INTO `social_links` (`id`, `platform`, `url`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,'youtube','https://youtube.com/@eduskillindia','▶',5,1,'2026-07-21 21:33:36','2026-07-26 17:14:19');

INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (1,'How can I donate?','You can donate securely through our Donate page. We accept UPI, bank transfer and cards. All donations are eligible for 80G tax exemption.','donations',1,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (2,'Are donations tax-deductible?','Yes. EDUSKILL INDIA FOUNDATION is registered and donations are eligible for tax deduction under Section 80G. A receipt is issued for every donation.','donations',2,1,'2026-07-21 21:33:35','2026-07-26 17:12:07');
INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (3,'How can I volunteer?','Fill out the volunteer form on our Volunteer page. Our team will contact you with opportunities that match your interests and availability.','volunteering',3,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (4,'Where does my money go?','Every rupee is tracked from donation to delivery. We publish impact reports and maintain full financial transparency.','general',4,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (5,'Can I visit your projects?','Absolutely! We welcome supporters to visit our project sites. Contact us to arrange a visit.','general',5,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');
INSERT INTO `faqs` (`id`, `question`, `answer`, `category`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES (6,'How do I verify a certificate?','Use our Verify Certificate page and enter the certificate number to instantly confirm its authenticity.','general',6,1,'2026-07-21 21:33:35','2026-07-21 21:33:35');

INSERT INTO `courses` (`id`, `title`, `slug`, `category`, `thumbnail`, `short_description`, `description`, `objectives`, `prerequisites`, `level`, `language`, `duration`, `price`, `is_featured`, `certificate_enabled`, `certificate_template`, `status`, `created_by`, `created_at`, `updated_at`) VALUES (3,'Digital Literacy Fundamentals','digital-literacy-fundamentals','Skill Development','images/computer-lab-digital-literacy.webp','Learn essential computer and internet skills for everyday life — from using email safely to accessing government services online.','<p>Learn essential computer and internet skills for everyday life — from using email safely to accessing government services online.</p><p>This free course is offered by EDUSKILL INDIA FOUNDATION as part of its community education mission. Complete all lessons to earn a certificate of completion.</p>','Understand the core concepts; Apply skills in daily life; Earn a completion certificate','None — open to all','beginner','Hindi / English','4 weeks',0.00,1,1,'classic','published',1,'2026-07-23 21:20:57','2026-08-08 12:48:06');
INSERT INTO `courses` (`id`, `title`, `slug`, `category`, `thumbnail`, `short_description`, `description`, `objectives`, `prerequisites`, `level`, `language`, `duration`, `price`, `is_featured`, `certificate_enabled`, `certificate_template`, `status`, `created_by`, `created_at`, `updated_at`) VALUES (4,'Community Health Worker Training','community-health-worker-training','Healthcare','programs/community-health-care-camp.webp','A practical foundation for aspiring community health volunteers: hygiene, first aid, maternal care awareness, and nutrition basics.','<p>A practical foundation for aspiring community health volunteers: hygiene, first aid, maternal care awareness, and nutrition basics.</p><p>This free course is offered by EDUSKILL INDIA FOUNDATION as part of its community education mission. Complete all lessons to earn a certificate of completion.</p>','Understand the core concepts; Apply skills in daily life; Earn a completion certificate','None — open to all','intermediate','Hindi','6 weeks',0.00,1,1,'classic','published',1,'2026-07-23 21:20:57','2026-08-08 12:48:06');
INSERT INTO `courses` (`id`, `title`, `slug`, `category`, `thumbnail`, `short_description`, `description`, `objectives`, `prerequisites`, `level`, `language`, `duration`, `price`, `is_featured`, `certificate_enabled`, `certificate_template`, `status`, `created_by`, `created_at`, `updated_at`) VALUES (5,'Tailoring & Entrepreneurship','tailoring-and-entrepreneurship','Livelihood','gallery/tailoring-skills-class.webp','From basic stitching to running a small tailoring business — measurements, cutting, machine care, costing and selling.','<p>From basic stitching to running a small tailoring business — measurements, cutting, machine care, costing and selling.</p><p>This free course is offered by EDUSKILL INDIA FOUNDATION as part of its community education mission. Complete all lessons to earn a certificate of completion.</p>','Understand the core concepts; Apply skills in daily life; Earn a completion certificate','None — open to all','beginner','Hindi','8 weeks',0.00,0,1,'classic','published',1,'2026-07-23 21:20:57','2026-08-08 12:48:06');

INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (4,3,'Module 1','Getting Started with Computers','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Getting Started with Computers</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,1,1,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (5,3,'Module 1','Using Email & Internet Safely','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Using Email & Internet Safely</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,2,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (6,3,'Module 2','Accessing Government e-Services','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Accessing Government e-Services</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,3,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (7,3,'Module 2','Digital Payments Basics','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Digital Payments Basics</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,4,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (8,4,'Module 1','Community Health Basics','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Community Health Basics</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,1,1,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (9,4,'Module 1','Hygiene & Sanitation','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Hygiene & Sanitation</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,2,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (10,4,'Module 2','First Aid Essentials','text',NULL,NULL,NULL,'<p>Lesson content for <strong>First Aid Essentials</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,3,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (11,4,'Module 2','Maternal & Child Care Awareness','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Maternal & Child Care Awareness</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,4,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (12,4,'Module 3','Nutrition Fundamentals','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Nutrition Fundamentals</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,5,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (13,5,'Module 1','Introduction & Tools','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Introduction & Tools</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,1,1,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (14,5,'Module 1','Measurements and Cutting','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Measurements and Cutting</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,2,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (15,5,'Module 2','Basic Stitches','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Basic Stitches</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,3,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (16,5,'Module 2','Machine Care','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Machine Care</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,4,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (17,5,'Module 3','Costing & Pricing','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Costing & Pricing</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,5,'2026-07-23 21:20:57');
INSERT INTO `course_lessons` (`id`, `course_id`, `module`, `title`, `type`, `video_provider`, `video_url`, `video_file`, `content`, `pdf_file`, `duration_min`, `is_preview`, `sort_order`, `created_at`) VALUES (18,5,'Module 3','Marketing Your Work','text',NULL,NULL,NULL,'<p>Lesson content for <strong>Marketing Your Work</strong>. Work through the material and mark the lesson complete to record your progress.</p>',NULL,20,0,6,'2026-07-23 21:20:57');

INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (1,'Welcome','Onboarding',NULL,'#6366f1',0,0,'welcome','Welcome to {{site_name}}','<p>Hi {{name}},</p><p>Welcome to {{site_name}}! Thank you for joining our community.</p>','{{name}}, {{site_name}}',1,'2026-07-21 21:33:36','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (2,'Donation Thank You','Donations',NULL,'#16a34a',0,0,'donation-thanks','Thank you for your donation','<p>Dear {{name}},</p><p>Thank you for your generous donation of {{amount}}.</p>','{{name}}, {{amount}}',1,'2026-07-21 21:33:36','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (3,'Membership — Welcome / Activated','Membership',NULL,'#d9a82e',0,0,'membership_welcome','Welcome to {{site_name}} — your membership is active','<p>Hi {{name}},</p><p>Welcome aboard! Your <strong>{{tier}}</strong> membership with {{site_name}} is now active.</p><table style=\"margin:14px 0;font-size:15px;\"><tr><td style=\"padding:4px 14px 4px 0;color:#6b7280;\">Membership ID</td><td><strong>{{member_code}}</strong></td></tr><tr><td style=\"padding:4px 14px 4px 0;color:#6b7280;\">Valid until</td><td><strong>{{valid_till}}</strong></td></tr></table><p>You can view and download your digital membership card any time from your account.</p><p><a href=\"{{verify_url}}\" style=\"display:inline-block;padding:11px 20px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;\">View my card</a></p><p>Thank you for standing with us.</p>','name, member_code, tier, valid_till, verify_url, site_name',1,'2026-07-23 06:50:39','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (4,'Membership — Expiry Reminder','Membership',NULL,'#f59e0b',0,0,'membership_expiry_reminder','Your {{site_name}} membership expires in {{days_left}} days','<p>Hi {{name}},</p><p>This is a friendly reminder that your <strong>{{tier}}</strong> membership (ID {{member_code}}) expires on <strong>{{valid_till}}</strong> — that is <strong>{{days_left}} days</strong> away.</p><p>Renew now to keep your benefits without interruption.</p><p><a href=\"{{renew_url}}\" style=\"display:inline-block;padding:11px 20px;background:#16a34a;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;\">Renew membership</a></p><p>If you have already renewed, please ignore this message.</p>','name, member_code, tier, valid_till, days_left, renew_url, site_name',1,'2026-07-23 06:50:39','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (5,'Membership — Renewed','Membership',NULL,'#10b981',0,0,'membership_renewed','Membership renewed — valid until {{valid_till}}','<p>Hi {{name}},</p><p>Thank you! Your {{tier}} membership (ID {{member_code}}) has been renewed and is now valid until <strong>{{valid_till}}</strong>.</p><p><a href=\"{{verify_url}}\" style=\"display:inline-block;padding:11px 20px;background:#2563eb;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;\">View my card</a></p>','name, member_code, tier, valid_till, verify_url, site_name',1,'2026-07-23 06:50:39','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (6,'Membership — Expired','Membership',NULL,'#ef4444',0,0,'membership_expired','Your {{site_name}} membership has expired','<p>Hi {{name}},</p><p>Your {{tier}} membership (ID {{member_code}}) has expired. We would love to have you continue with us.</p><p><a href=\"{{renew_url}}\" style=\"display:inline-block;padding:11px 20px;background:#16a34a;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;\">Reactivate membership</a></p>','name, member_code, tier, renew_url, site_name',1,'2026-07-23 06:50:39','2026-07-24 13:22:39');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (7,'Newsletter','Newsletter','Monthly newsletter with your latest impact stories','#ec4899',1,20,'newsletter','{{site_name}} Newsletter — {{month}} {{year}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Hello {{name}}, here\'s what we\'ve been up to</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Thank you for standing with us. This month your support helped us reach more families across Bihar than ever before.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\"><strong>{{highlight}}</strong></div><p style=\"margin:0 0 16px;line-height:1.7;\">Read the full stories, see the numbers, and meet the people whose lives you changed.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#ec4899;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Read the Newsletter</a></td></tr></table>','name, month, highlight, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (8,'Welcome Email','Onboarding','Warm welcome for a new member or subscriber','#6366f1',1,30,'welcome-email','Welcome to {{site_name}}, {{name}}! 🎉','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Welcome aboard, {{name}}!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">We\'re thrilled to have you join the {{site_name}} family. Together we\'re building a brighter future for communities across Bihar.</p><p style=\"margin:0 0 16px;line-height:1.7;\">Here\'s what you can do next:</p><ul style=\"line-height:1.9;color:#374151;\"><li>Explore our programs and causes</li><li>See where your support goes</li><li>Join a volunteer drive near you</li></ul><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#6366f1;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Get Started</a></td></tr></table>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (9,'Membership Confirmation','Membership','Confirms a new membership is active','#d9a82e',1,40,'membership-confirmation','Your {{site_name}} membership is confirmed �
','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Membership Confirmed</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, your <strong>{{plan}}</strong> membership is now active. Welcome to a community of changemakers!</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Membership ID: <strong>{{member_id}}</strong><br>Valid until: <strong>{{expiry_date}}</strong></div><p style=\"margin:0 0 16px;line-height:1.7;\">Your digital membership card is ready to download.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#d9a82e;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">View Membership Card</a></td></tr></table>','name, plan, member_id, expiry_date, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (10,'Donation Receipt','Donations','Official 80G-eligible donation receipt','#16a34a',1,50,'donation-receipt','Receipt for your donation — {{receipt_no}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Thank you for your generosity</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, we gratefully acknowledge your donation. This receipt is valid for tax exemption under Section 80G.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Receipt No: <strong>{{receipt_no}}</strong><br>Amount: <strong>{{amount}}</strong><br>Date: <strong>{{date}}</strong><br>PAN: {{pan}}</div><p style=\"margin:0 0 16px;line-height:1.7;\">Every rupee goes directly to the cause. Thank you for making our work possible.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#16a34a;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Download 80G Receipt</a></td></tr></table>','name, receipt_no, amount, date, pan, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (11,'Donation Thank You','Donations','Heartfelt thank-you after a gift','#22c55e',1,60,'donation-thank-you','Your kindness is changing lives, {{name}} ❤️','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">You made this possible</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, your gift of <strong>{{amount}}</strong> is already at work — funding meals, medicines, books and hope for families who need it most.</p><p style=\"margin:0 0 16px;line-height:1.7;\">We\'ll keep you updated on exactly where your generosity goes.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#22c55e;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">See Your Impact</a></td></tr></table>','name, amount, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (12,'Course Enrollment','Learning','Confirms enrollment in a course','#8b5cf6',1,70,'course-enrollment','You\'re enrolled: {{course_name}} 📚','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Welcome to {{course_name}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, you\'re all set! Your free course is ready and waiting.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Course: <strong>{{course_name}}</strong><br>Duration: {{duration}}<br>Level: {{level}}</div><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#8b5cf6;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Start Learning</a></td></tr></table>','name, course_name, duration, level, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (13,'Course Completion','Learning','Congratulates a learner on finishing','#7c3aed',1,80,'course-completion','Congratulations — you completed {{course_name}}! 🎓','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">You did it, {{name}}!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">You\'ve successfully completed <strong>{{course_name}}</strong>. We\'re proud of your dedication.</p><p style=\"margin:0 0 16px;line-height:1.7;\">Your certificate of completion is ready to download and share.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#7c3aed;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Get Your Certificate</a></td></tr></table>','name, course_name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (14,'Certificate Issued','Certificates','Notifies that a certificate is available','#0891b2',1,90,'certificate-issued','Your certificate is ready 📜','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Certificate Issued</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, your certificate for <strong>{{title}}</strong> has been issued and is verifiable online.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Certificate No: <strong>{{certificate_no}}</strong></div><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#0891b2;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Download Certificate</a></td></tr></table>','name, title, certificate_no, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (15,'Event Registration','Events','Confirms registration for an event','#f97316',1,100,'event-registration','You\'re registered for {{event_name}} 🎟️','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">See you at {{event_name}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, your spot is confirmed. We can\'t wait to see you there!</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">�
 {{event_date}}<br>📍 {{venue}}</div><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#f97316;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">View Event Details</a></td></tr></table>','name, event_name, event_date, venue, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (16,'Event Reminder','Events','Reminds an attendee an event is soon','#fb923c',1,110,'event-reminder','Reminder: {{event_name}} is {{when}} ⏰','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Don\'t forget — {{event_name}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, this is a friendly reminder that <strong>{{event_name}}</strong> is coming up {{when}}.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">�
 {{event_date}}<br>📍 {{venue}}</div><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#fb923c;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Get Directions</a></td></tr></table>','name, event_name, when, event_date, venue, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (17,'Volunteer Registration','Volunteers','Welcomes a new volunteer','#0ea5e9',1,120,'volunteer-registration','Welcome to the team, {{name}}! 🤝','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Thank you for volunteering</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, thank you for stepping up to make a difference. Volunteers are the heart of {{site_name}}.</p><p style=\"margin:0 0 16px;line-height:1.7;\">Our team will reach out shortly with your first opportunity.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#0ea5e9;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Volunteer Dashboard</a></td></tr></table>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (18,'Internship Offer','Careers','Extends an internship offer','#14b8a6',1,130,'internship-offer','Internship Offer — {{role}} at {{site_name}} 🌟','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Congratulations, {{name}}!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">We\'re delighted to offer you the position of <strong>{{role}}</strong> intern at {{site_name}}.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Start date: <strong>{{start_date}}</strong><br>Duration: {{duration}}</div><p style=\"margin:0 0 16px;line-height:1.7;\">Please review and accept your offer using the link below.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#14b8a6;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">View Offer Letter</a></td></tr></table>','name, role, start_date, duration, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (19,'Internship Completion','Careers','Certifies internship completion','#0d9488',1,140,'internship-completion','Your internship is complete — thank you! 🎓','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Well done, {{name}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">You\'ve successfully completed your internship as <strong>{{role}}</strong>. It was a pleasure having you on the team.</p><p style=\"margin:0 0 16px;line-height:1.7;\">Your completion certificate is attached and verifiable online.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#0d9488;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Download Certificate</a></td></tr></table>','name, role, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (20,'Password Reset','Account','Secure password reset link','#ef4444',1,150,'password-reset','Reset your {{site_name}} password','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Password reset requested</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, we received a request to reset your password. Click below to choose a new one.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#ef4444;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Reset Password</a></td></tr></table><p style=\"margin:0 0 16px;line-height:1.7;\"><span style=\"color:#6b7280;font-size:13px;\">This link expires in 30 minutes. If you didn\'t request this, you can safely ignore this email.</span></p>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (21,'Email Verification','Account','Verify a new email address','#3b82f6',1,160,'email-verification','Verify your email address','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Confirm your email</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, please confirm your email address to activate your {{site_name}} account.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#3b82f6;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Verify Email</a></td></tr></table><p style=\"margin:0 0 16px;line-height:1.7;\"><span style=\"color:#6b7280;font-size:13px;\">If you didn\'t create an account, no action is needed.</span></p>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (22,'Otp Verification','Account','One-time passcode','#6d28d9',1,170,'otp-verification','Your verification code is {{otp}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Your one-time code</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, use the code below to complete your verification:</p><div style=\"text-align:center;margin:22px 0;\"><span style=\"display:inline-block;font-size:34px;letter-spacing:10px;font-weight:800;color:#111827;background:#f3f4f6;padding:16px 26px;border-radius:12px;\">{{otp}}</span></div><p style=\"margin:0 0 16px;line-height:1.7;\"><span style=\"color:#6b7280;font-size:13px;\">This code expires in 15 minutes. Never share it with anyone.</span></p>','name, otp',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (23,'Contact Form Reply','Support','Reply to a contact form enquiry','#0ea5e9',1,180,'contact-form-reply','Re: your message to {{site_name}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Thanks for reaching out</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Hi {{name}}, thank you for contacting us. Here\'s our response to your enquiry:</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">{{reply_message}}</div><p style=\"margin:0 0 16px;line-height:1.7;\">If you have further questions, just reply to this email.</p>','name, reply_message',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (24,'Admin Notification','Internal','Internal alert for the admin team','#64748b',1,190,'admin-notification','[{{site_name}}] {{alert_title}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">{{alert_title}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">{{alert_message}}</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Triggered: <strong>{{timestamp}}</strong></div><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#475569;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Open Admin Panel</a></td></tr></table>','alert_title, alert_message, timestamp, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (25,'Partner Invitation','Outreach','Invites an organization to partner','#8b5cf6',1,200,'partner-invitation','An invitation to partner with {{site_name}} 🤝','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Let\'s create change together</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, we admire the work of {{organization}} and would love to explore a partnership to amplify our shared mission.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#8b5cf6;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Explore Partnership</a></td></tr></table>','name, organization, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (26,'Sponsor Invitation','Outreach','Invites a sponsor to support a cause','#d9a82e',1,210,'sponsor-invitation','Sponsor a cause that changes lives 🌟','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Your brand + our mission</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, sponsoring {{site_name}} puts {{organization}} at the heart of real, measurable community impact — with full transparency and recognition.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#d9a82e;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">View Sponsorship Deck</a></td></tr></table>','name, organization, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (27,'Campaign Launch','Campaigns','Announces a new fundraising campaign','#e11d48',1,220,'campaign-launch','New campaign: {{campaign_name}} — join us 🚀','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">{{campaign_name}} is live!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, we\'ve just launched a new campaign and we need your help to reach our goal.</p><div style=\"background:#f0f9ff;border-left:4px solid #0ea5e9;padding:14px 18px;border-radius:8px;margin:0 0 18px;\">Goal: <strong>{{goal}}</strong></div><p style=\"margin:0 0 16px;line-height:1.7;\">{{campaign_summary}}</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#e11d48;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Donate Now</a></td></tr></table>','name, campaign_name, goal, campaign_summary, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (28,'Announcement','Announcements','General announcement to your audience','#0ea5e9',1,230,'announcement','{{announcement_title}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">{{announcement_title}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}},</p><p style=\"margin:0 0 16px;line-height:1.7;\">{{announcement_body}}</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#0ea5e9;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Learn More</a></td></tr></table>','name, announcement_title, announcement_body, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (29,'Holiday Greetings','Greetings','Seasonal holiday greeting','#16a34a',1,240,'holiday-greetings','Season\'s Greetings from {{site_name}} 🎄','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Warm wishes this festive season</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, as the year draws to a close, we want to thank you for your compassion and support. From all of us at {{site_name}}, we wish you and your loved ones joy, health and peace.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#16a34a;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Spread the Joy — Donate</a></td></tr></table>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (30,'Birthday Wishes','Greetings','Personal birthday message','#ec4899',1,250,'birthday-wishes','Happy Birthday, {{name}}! 🎂','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">Wishing you a wonderful day!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, everyone at {{site_name}} wishes you a very happy birthday filled with joy and good health. Thank you for being part of our family.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#ec4899;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">Celebrate with a Gift</a></td></tr></table>','name, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (31,'Anniversary Greetings','Greetings','Marks a supporter anniversary','#f59e0b',1,260,'anniversary-greetings','Celebrating {{years}} year(s) together! 🎉','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">What a journey, {{name}}!</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}}, it\'s been <strong>{{years}} year(s)</strong> since you joined the {{site_name}} family. Your loyalty means the world to us and to the communities you\'ve helped.</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#f59e0b;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">See What We\'ve Achieved</a></td></tr></table>','name, years, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');
INSERT INTO `email_templates` (`id`, `name`, `category`, `description`, `thumbnail`, `is_premium`, `sort_order`, `slug`, `subject`, `body`, `variables`, `status`, `created_at`, `updated_at`) VALUES (32,'Custom Template','Custom','Blank branded starting point','#64748b',1,270,'custom-template','{{subject}}','<h2 style=\"margin:0 0 14px;font-size:22px;color:#111827;\">{{heading}}</h2><p style=\"margin:0 0 16px;line-height:1.7;\">Dear {{name}},</p><p style=\"margin:0 0 16px;line-height:1.7;\">{{body}}</p><table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:22px auto;\"><tr><td style=\"border-radius:10px;background:#2563eb;\"><a href=\"{{action_url}}\" style=\"display:inline-block;padding:13px 30px;color:#fff;font-weight:700;text-decoration:none;font-size:15px;\">{{button_label}}</a></td></tr></table>','name, subject, heading, body, button_label, action_url',1,'2026-07-24 13:29:04','2026-07-24 13:29:04');

INSERT INTO `email_automations` (`id`, `trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`, `created_at`, `updated_at`) VALUES (1,'welcome','Welcome email','Welcome to {{site_name}}!','<p>Hi {{name}},</p><p>Thanks for subscribing to {{site_name}}. We are glad to have you.</p>',0,0,'2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `email_automations` (`id`, `trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`, `created_at`, `updated_at`) VALUES (2,'donation_receipt','Donation thank-you','Thank you for your donation','<p>Dear {{name}},</p><p>We have received your generous donation of {{amount}}. Thank you for supporting our work.</p>',0,0,'2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `email_automations` (`id`, `trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`, `created_at`, `updated_at`) VALUES (3,'birthday','Birthday greeting','Happy Birthday, {{name}}!','<p>Wishing you a wonderful birthday from all of us at {{site_name}}.</p>',0,0,'2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `email_automations` (`id`, `trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`, `created_at`, `updated_at`) VALUES (4,'membership_expiry','Membership expiry reminder','Your membership is expiring soon','<p>Hi {{name}},</p><p>Your membership expires on {{expiry_date}}. Renew today to keep your benefits.</p>',0,7,'2026-07-24 07:20:05','2026-07-24 07:20:05');
INSERT INTO `email_automations` (`id`, `trigger_key`, `name`, `subject`, `body`, `enabled`, `offset_days`, `created_at`, `updated_at`) VALUES (5,'inactivity','We miss you','We miss you at {{site_name}}','<p>Hi {{name}},</p><p>It has been a while. Here is what you have missed.</p>',0,90,'2026-07-24 07:20:05','2026-07-24 07:20:05');

INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (1,'Follow-up','follow-up','#f59e0b',1,'2026-07-24 13:22:39');
INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (2,'Important','important','#ef4444',2,'2026-07-24 13:22:39');
INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (3,'Donors','donors','#16a34a',3,'2026-07-24 13:22:39');
INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (4,'Volunteers','volunteers','#0ea5e9',4,'2026-07-24 13:22:39');
INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (5,'Partners','partners','#8b5cf6',5,'2026-07-24 13:22:39');
INSERT INTO `email_labels` (`id`, `name`, `slug`, `color`, `sort_order`, `created_at`) VALUES (6,'Newsletter','newsletter','#ec4899',6,'2026-07-24 13:22:39');

INSERT INTO `email_accounts` (`id`, `name`, `email`, `reply_to`, `signature_id`, `smtp_host`, `smtp_port`, `smtp_secure`, `smtp_user`, `smtp_pass`, `imap_host`, `imap_port`, `imap_secure`, `imap_user`, `imap_pass`, `pop3_host`, `pop3_port`, `pop3_secure`, `pop3_user`, `pop3_pass`, `dkim_selector`, `is_default`, `status`, `last_sync_at`, `created_at`, `updated_at`) VALUES (1,'EDUSKILL INDIA FOUNDATION','info@eduskillindia.org','info@eduskillindia.org',NULL,NULL,587,'tls',NULL,NULL,NULL,993,'ssl',NULL,NULL,NULL,995,'ssl',NULL,NULL,NULL,1,1,NULL,'2026-07-24 13:29:04','2026-07-26 17:12:07');

INSERT INTO `email_signatures` (`id`, `name`, `body_html`, `is_default`, `created_at`, `updated_at`) VALUES (1,'Default','<strong>EDUSKILL INDIA FOUNDATION</strong><br>info@eduskillindia.org &middot; +91-7491932148<br><span style=\"color:#9ca3af;font-size:12px;\">Patna, Bihar, India 840007</span>',1,'2026-07-24 13:29:04','2026-07-26 13:58:07');

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


COMMIT;
SET AUTOCOMMIT = @OLD_AUTOCOMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- End of dump. Tables: 130, data rows: 559



##############################################################################
##############################################################################
##############################################################################   SECTION 2  —  LIVE UPDATE
##############################################################################
##############################################################################   Safe on a database that already holds real data.
##############################################################################   No DROP. No TRUNCATE. No DELETE. Re-running it is harmless.
##############################################################################
##############################################################################   This is the section to run on production.
##############################################################################
##############################################################################

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- schema_v29.sql
-- ---------------------------------------------------------------------------
-- =============================================================================
--  v29 — role-aware authentication
--  Run AFTER schema_v28.sql
--
--  Adds members.role so the public identity table can distinguish member /
--  volunteer / donor / student. Staff roles continue to live on users.role_id;
--  includes/auth_router.php normalises both onto one role vocabulary.
-- =============================================================================
ALTER TABLE `members`
    ADD COLUMN IF NOT EXISTS `role` VARCHAR(32) NOT NULL DEFAULT 'member' AFTER `type`;

-- Backfill from the existing `type` column where it already says something useful.
UPDATE `members` SET `role` = 'member'    WHERE `role` = '' OR `role` IS NULL;
UPDATE `members` SET `role` = LOWER(`type`)
    WHERE LOWER(`type`) IN ('member','volunteer','donor','student');

ALTER TABLE `members` ADD INDEX IF NOT EXISTS `idx_members_role` (`role`);

-- ---------------------------------------------------------------------------
-- schema_v30.sql
-- ---------------------------------------------------------------------------
-- =============================================================================
--  v30 — unified registration
--  Run AFTER schema_v29.sql
-- =============================================================================
--  1. Registers the two registration-policy settings so they exist as rows in
--     `settings` (visible in the table, auditable, and exportable with the rest
--     of the configuration) rather than only as get_setting() defaults.
--
--     BOTH DEFAULT TO '0' — OFF — on purpose. registration_auto_verify in
--     particular removes the only proof that a registrant controls the address
--     they typed, so an unattended deploy must not switch it on. Turn them on
--     from Admin -> Registration Policy, where the trade-off is spelled out.
--
--  2. Backfills members.role for the rows schema_v29 could not reach.
--     v29's backfill was
--         UPDATE members SET role = LOWER(type)
--          WHERE LOWER(type) IN ('member','volunteer','donor','student')
--     which works for member/volunteer/donor but never fires for 'student',
--     because 'student' is not a value member_register() or admin/members.php
--     will write to `type`. It also had nothing to say about institutional rows.
--     From v30 onward members.role is written by the registration flow and by
--     admin/members.php, so this is the last time it needs backfilling.
-- =============================================================================

-- --------------------------------------------------------------------------
-- 1. Registration policy
-- --------------------------------------------------------------------------
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`, `label`) VALUES
  ('registration', 'registration_auto_verify',  '0', 'boolean',
   'Skip email verification at sign-up (HIGH RISK — removes the only proof the registrant owns the address)'),
  ('registration', 'registration_auto_approve', '0', 'boolean',
   'Approve new self-registered accounts automatically (never applies to School accounts)')
ON DUPLICATE KEY UPDATE
  -- Never clobber a value an admin has already set; only fill in the label and
  -- group for a row that predates this migration.
  `group_name` = VALUES(`group_name`),
  `label`      = VALUES(`label`),
  `type`       = VALUES(`type`);

-- --------------------------------------------------------------------------
-- 2. members.role backfill (idempotent)
-- --------------------------------------------------------------------------
-- Empty/NULL role is not a state auth_router.php should have to interpret.
UPDATE `members` SET `role` = 'member' WHERE `role` IS NULL OR `role` = '';

-- 'partner' is the type the unified registration writes for a school, so an
-- institutional row created before v30 can be identified the same way.
UPDATE `members` SET `role` = 'school'
 WHERE `role` = 'member' AND LOWER(`type`) = 'partner';

-- Anything still holding a role slug outside the members realm is corrected:
-- a members row must never route as a staff role.
UPDATE `members` SET `role` = 'member'
 WHERE LOWER(`role`) NOT IN ('member', 'donor', 'volunteer', 'student', 'school');

-- ---------------------------------------------------------------------------
-- Settings (address, CORS origin, EIF prefixes, copy)
-- ---------------------------------------------------------------------------
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('contact_address','International Financial Hub (IFH), Plot No. IIF/04, Action Area II, New Town, Kolkata – 700156, West Bengal, India','contact','text')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('home_about_text','EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN U88900BR2026NPL081597) working to empower communities through education, healthcare, skill development and emergency relief. We believe lasting change is built alongside people, not handed to them.','homepage','textarea')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('api_cors_origin','https://eduskillindia.org','api','text')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('membership_code_prefix','EIF','membership','text')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('payment_receipt_prefix','EIF','membership','text')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('site_description','EDUSKILL INDIA FOUNDATION is a registered non-profit in Patna, Bihar working to empower communities through education, healthcare, skill development and relief.','general','textarea')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('footer_about','EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief — spreading hope and creating lasting change.','general','textarea')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO `settings` (`key_name`,`value`,`group_name`,`type`) VALUES ('site_keywords','NGO Patna, Bihar NGO, charity India, donation, volunteer, education, healthcare','general','text')
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ---------------------------------------------------------------------------
-- Theme tokens — the forest-green palette, Manrope/Inter, scales
-- ---------------------------------------------------------------------------
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.primary','#218c3c','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.secondary','#3e6ab1','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.accent','#80a300','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.success','#2f8065','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.success_dark','#1f5c48','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.danger','#dc2626','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.bg','#ffffff','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.bg_alt','#f8fcf8','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.surface','#ffffff','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.surface_2','#fefef1','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.text','#151818','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.text_soft','#372c22','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.muted','#4b6754','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.border','#c1ccb3','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.sidebar','#0f4537','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.sidebar_2','#216e17','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.sidebar_active','#1a0d7d','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.footer','#166030','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('grad.primary_a','#0B4E3D','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('grad.primary_b','#174D3D','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('grad.cta_a','#F15A24','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('grad.cta_b','#E8C52E','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.body','Inter','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.heading','Manrope','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('email.header_bg','#0B4E3D','email',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('email.btn_bg','#F15A24','email',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('pdf.accent','#063566','email',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('adv.theme_color','#0B4E3D','advanced',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.base','16','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.scale','1.25','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.line','1.7','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.tracking','0','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.weight_body','400','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('font.weight_head','800','typography',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('grad.angle','135','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('glass.blur','12','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('glass.alpha','12','gradients',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('email.font','Arial, Helvetica, sans-serif','email',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('email.signature','','email',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.name','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.logo','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.logo_dark','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.favicon','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.app_icon','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.watermark','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('brand.footer_note','','brand',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.width','boxed','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.container','1200','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.header_h','74','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.sidebar_w','268','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.sticky_header','1','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('layout.dir','ltr','layout',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.radius','14','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.radius_sm','10','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.radius_lg','22','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.shadow','medium','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.transition','250','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.hover_lift','4','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('ui.animations','1','effects',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('auth.bg_image','','auth',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('auth.card','glass','auth',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('auth.welcome','','auth',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('auth.animation','1','auth',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('adv.mode','light','advanced',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('adv.css','','advanced',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('adv.js','','advanced',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.yellow','#ffe987','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.gold','#e8c52e','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;
INSERT INTO `theme_settings` (`token`,`value`,`group_name`,`draft`) VALUES ('color.ivory','#fefef1','colors',NULL)
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `draft` = NULL;

-- ---------------------------------------------------------------------------
-- Icons: convert any emoji still stored on live rows to lucide slugs
-- ---------------------------------------------------------------------------
UPDATE `programs` SET `icon` = 'book-open' WHERE `id` = 1;
UPDATE `programs` SET `icon` = 'stethoscope' WHERE `id` = 2;
UPDATE `programs` SET `icon` = 'user-round' WHERE `id` = 3;
UPDATE `programs` SET `icon` = 'wrench' WHERE `id` = 4;
UPDATE `programs` SET `icon` = 'droplets' WHERE `id` = 5;
UPDATE `programs` SET `icon` = 'life-buoy' WHERE `id` = 6;
UPDATE `achievements` SET `icon` = 'users' WHERE `id` = 1;
UPDATE `achievements` SET `icon` = 'graduation-cap' WHERE `id` = 2;
UPDATE `achievements` SET `icon` = 'handshake' WHERE `id` = 3;
UPDATE `achievements` SET `icon` = 'home' WHERE `id` = 4;

-- ---------------------------------------------------------------------------
-- Member codes: PWF- (old org) -> EIF-
-- ---------------------------------------------------------------------------
UPDATE `members` SET `member_code` = CONCAT('EIF-', SUBSTRING(`member_code`, 5))
  WHERE `member_code` LIKE 'PWF-%';

-- End of update script. No table was dropped or truncated.

-- ---------------------------------------------------------------------------
-- Role-based access control: switch enforcement ON.
--
-- Until now `rbac_enforce` shipped at '0', which made rbac_gate() a no-op: every
-- active row in `users` reached every admin screen regardless of its assigned
-- role. Role assignment was cosmetic. Verified over HTTP after this change:
-- editor reaches /admin/blogs and /admin/pages but gets 403 on /admin/security,
-- /admin/users and /admin/roles; teacher reaches courses+exams only; school
-- reaches school-students only. Super-admin (#1) is unaffected — rbac_is_super()
-- bypasses the gate, so this cannot lock you out of your own site.
--
-- To turn it back off: Admin -> Roles -> "Enforcement" toggle.
-- ---------------------------------------------------------------------------
INSERT INTO `settings` (`group_name`, `key_name`, `value`, `type`)
VALUES ('security', 'rbac_enforce', '1', 'boolean')
ON DUPLICATE KEY UPDATE `value` = '1', `type` = 'boolean';

