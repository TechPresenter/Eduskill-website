
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `announcements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `message` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `bg_color` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_announcements_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `announcements` WRITE;
/*!40000 ALTER TABLE `announcements` DISABLE KEYS */;
/*!40000 ALTER TABLE `announcements` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `entity` varchar(100) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity`,`entity_id`),
  KEY `idx_audit_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banners` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `mobile_image` varchar(255) DEFAULT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `cta_label` varchar(80) DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_banners_active` (`is_active`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campaigns` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category` varchar(80) DEFAULT NULL,
  `goal_amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `raised_amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `donor_count` int(10) unsigned NOT NULL DEFAULT 0,
  `beneficiary` varchar(255) DEFAULT NULL,
  `starts_at` date DEFAULT NULL,
  `ends_at` date DEFAULT NULL,
  `status` enum('draft','active','completed','closed') NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_campaigns_slug` (`slug`),
  KEY `idx_campaigns_status` (`status`,`is_featured`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
INSERT INTO `campaigns` VALUES (1,'Keep Girls in School 2026','keep-girls-in-school-2026','UPDATED: Scholarships for 300 girls.','',NULL,'',7500000,3750000,142,'',NULL,'2026-09-04','active',0,NULL,'2026-07-21 02:14:35','2026-07-21 02:28:23',NULL),(2,'Feed 500 Children','feed-500-children','Daily meals for a year.','<p>Help</p>','','',20000000,4500000,88,'',NULL,NULL,'active',1,NULL,'2026-07-21 03:31:31','2026-07-21 03:31:31',NULL);
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `certificate_number` varchar(60) NOT NULL,
  `recipient_name` varchar(150) NOT NULL,
  `program_name` varchar(200) DEFAULT NULL,
  `type` varchar(60) NOT NULL DEFAULT 'completion',
  `issue_date` date DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `qr_path` varchar(255) DEFAULT NULL,
  `status` enum('valid','revoked') NOT NULL DEFAULT 'valid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_certificates_number` (`certificate_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `author_name` varchar(120) NOT NULL,
  `author_email` varchar(190) DEFAULT NULL,
  `body` text NOT NULL,
  `status` enum('pending','approved','spam') NOT NULL DEFAULT 'pending',
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_comments_post` (`post_id`,`status`),
  KEY `idx_comments_parent` (`parent_id`),
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contacts_read` (`is_read`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `downloads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `downloads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'general',
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `downloads` bigint(20) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_downloads_cat` (`category`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `downloads` WRITE;
/*!40000 ALTER TABLE `downloads` DISABLE KEYS */;
/*!40000 ALTER TABLE `downloads` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `email_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `to_email` varchar(190) NOT NULL,
  `to_name` varchar(150) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext DEFAULT NULL,
  `status` enum('sent','failed','logged') NOT NULL DEFAULT 'logged',
  `error` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_log_status` (`status`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `email_log` WRITE;
/*!40000 ALTER TABLE `email_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `email_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_events_slug` (`slug`),
  KEY `idx_events_start` (`starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `faqs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `faqs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `question` varchar(255) NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(80) NOT NULL DEFAULT 'general',
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_faqs_cat` (`category`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `faqs` WRITE;
/*!40000 ALTER TABLE `faqs` DISABLE KEYS */;
INSERT INTO `faqs` VALUES (1,'Test?','Yes.','',0,0,'2026-07-21 02:15:32','2026-07-21 02:15:32','2026-07-21 02:15:32');
/*!40000 ALTER TABLE `faqs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `form_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `label` varchar(150) NOT NULL,
  `field_name` varchar(80) NOT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'text',
  `options_json` longtext DEFAULT NULL,
  `placeholder` varchar(150) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_fields_form` (`form_id`,`position`),
  CONSTRAINT `fk_fields_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `form_fields` WRITE;
/*!40000 ALTER TABLE `form_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_fields` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `form_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `data_json` longtext NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_submissions_form` (`form_id`,`created_at`),
  KEY `idx_submissions_read` (`is_read`),
  CONSTRAINT `fk_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `form_submissions` WRITE;
/*!40000 ALTER TABLE `form_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_submissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `submit_label` varchar(80) NOT NULL DEFAULT 'Submit',
  `success_message` varchar(500) DEFAULT NULL,
  `notify_email` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_forms_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `forms` WRITE;
/*!40000 ALTER TABLE `forms` DISABLE KEYS */;
/*!40000 ALTER TABLE `forms` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `galleries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `galleries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `kind` enum('photo','video') NOT NULL DEFAULT 'photo',
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_galleries_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `galleries` WRITE;
/*!40000 ALTER TABLE `galleries` DISABLE KEYS */;
/*!40000 ALTER TABLE `galleries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `gallery_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gallery_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gallery_id` bigint(20) unsigned NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_gallery_items` (`gallery_id`,`position`),
  CONSTRAINT `fk_gallery_items` FOREIGN KEY (`gallery_id`) REFERENCES `galleries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `gallery_items` WRITE;
/*!40000 ALTER TABLE `gallery_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `gallery_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `internships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `internships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `duration` varchar(80) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_internships_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `internships` WRITE;
/*!40000 ALTER TABLE `internships` DISABLE KEYS */;
/*!40000 ALTER TABLE `internships` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `department` varchar(120) DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `employment_type` varchar(60) DEFAULT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jobs_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identifier` varchar(190) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `successful` tinyint(1) NOT NULL DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attempt_identifier` (`identifier`,`attempted_at`),
  KEY `idx_attempt_ip` (`ip`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `folder_id` bigint(20) unsigned DEFAULT NULL,
  `disk` enum('public','private') NOT NULL DEFAULT 'public',
  `path` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime` varchar(100) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_media_folder` (`folder_id`),
  KEY `idx_media_disk` (`disk`),
  KEY `fk_media_creator` (`created_by`),
  CONSTRAINT `fk_media_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_media_folder` FOREIGN KEY (`folder_id`) REFERENCES `media_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
INSERT INTO `media` VALUES (1,NULL,'public','uploads/2026/07/5a274fa487a2e069-609803.png','5a274fa487a2e069-609803.png','test-upload.png','image/png',1495,500,350,NULL,NULL,1,'2026-07-21 04:56:43');
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `media_folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `media_folders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_folders_parent` (`parent_id`),
  CONSTRAINT `fk_folders_parent` FOREIGN KEY (`parent_id`) REFERENCES `media_folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `media_folders` WRITE;
/*!40000 ALTER TABLE `media_folders` DISABLE KEYS */;
/*!40000 ALTER TABLE `media_folders` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(120) NOT NULL,
  `url` varchar(255) DEFAULT NULL,
  `page_id` bigint(20) unsigned DEFAULT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `target` varchar(10) NOT NULL DEFAULT '_self',
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_menu_items_menu` (`menu_id`,`position`),
  KEY `idx_menu_items_parent` (`parent_id`),
  KEY `fk_menu_items_page` (`page_id`),
  CONSTRAINT `fk_menu_items_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (9,1,NULL,'Home','index.php',NULL,NULL,'_self',1,'2026-07-21 03:59:23'),(10,1,NULL,'About','about.php',NULL,NULL,'_self',2,'2026-07-21 03:59:23'),(11,1,NULL,'Programmes','programs.php',NULL,NULL,'_self',3,'2026-07-21 03:59:23'),(12,1,NULL,'Campaigns','campaigns.php',NULL,NULL,'_self',4,'2026-07-21 03:59:23'),(13,1,NULL,'Gallery','gallery.php',NULL,NULL,'_self',5,'2026-07-21 03:59:23'),(14,1,NULL,'Blog','blog.php',NULL,NULL,'_self',6,'2026-07-21 03:59:23'),(15,1,NULL,'FAQ','faq.php',NULL,NULL,'_self',7,'2026-07-21 03:59:23'),(16,1,NULL,'Contact','contact.php',NULL,NULL,'_self',8,'2026-07-21 03:59:23');
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `location` varchar(60) NOT NULL DEFAULT 'header',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menus_location` (`location`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES (1,'Header Menu','header','2026-07-21 01:46:00','2026-07-21 01:46:00');
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `batch` int(10) unsigned NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migrations_filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'001_core_auth.sql',1,'2026-07-21 01:09:26'),(2,'002_settings_seo.sql',1,'2026-07-21 01:09:26'),(3,'003_cms.sql',1,'2026-07-21 01:09:26'),(4,'004_media.sql',1,'2026-07-21 01:09:26'),(5,'005_blog.sql',1,'2026-07-21 01:09:26'),(6,'006_content.sql',1,'2026-07-21 01:09:26'),(7,'007_forms.sql',1,'2026-07-21 01:09:27'),(8,'008_seed_rbac.sql',2,'2026-07-21 01:21:57'),(9,'009_mail.sql',2,'2026-07-21 01:21:57'),(11,'010_seed_content.sql',3,'2026-07-21 02:01:53'),(12,'011_seed_dynamic.sql',4,'2026-07-21 02:27:31');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `newsletter_subscribers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `newsletter_subscribers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `status` enum('pending','subscribed','unsubscribed') NOT NULL DEFAULT 'pending',
  `token` varchar(64) DEFAULT NULL,
  `subscribed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subscriber_email` (`email`),
  KEY `idx_subscriber_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `newsletter_subscribers` WRITE;
/*!40000 ALTER TABLE `newsletter_subscribers` DISABLE KEYS */;
/*!40000 ALTER TABLE `newsletter_subscribers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `page_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` bigint(20) unsigned NOT NULL,
  `sections_json` longtext NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_revisions_page` (`page_id`,`created_at`),
  KEY `fk_revisions_user` (`created_by`),
  CONSTRAINT `fk_revisions_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_revisions_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `page_revisions` WRITE;
/*!40000 ALTER TABLE `page_revisions` DISABLE KEYS */;
INSERT INTO `page_revisions` VALUES (1,1,'[{\"type\":\"hero\",\"position\":1,\"visible\":true,\"settings\":{\"heading\":\"EDITED VIA EDITOR TEST\",\"subheading\":\"Proving the page editor works end to end.\",\"cta_label\":\"Donate now\",\"cta_url\":\"\\/donate\",\"secondary_cta_label\":\"\",\"secondary_cta_url\":\"\",\"image\":\"\"}},{\"type\":\"rich_text\",\"position\":2,\"visible\":true,\"settings\":{\"heading\":\"Sanitize check\",\"body_html\":\"<p>Safe paragraph<\\/p><p>two<\\/p>\"}}]','Published',1,'2026-07-21 02:00:58');
/*!40000 ALTER TABLE `page_revisions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `page_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `page_sections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_id` bigint(20) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `settings_json` longtext DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sections_page` (`page_id`,`position`),
  CONSTRAINT `fk_sections_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `page_sections` WRITE;
/*!40000 ALTER TABLE `page_sections` DISABLE KEYS */;
INSERT INTO `page_sections` VALUES (13,1,'hero',1,'{\"heading\":\"Education changes everything\",\"subheading\":\"We work alongside government schools and local communities to bring quality learning, skills training, and scholarships to children and youth who need them most.\",\"cta_label\":\"Donate now\",\"cta_url\":\"/donate\",\"secondary_cta_label\":\"Our programmes\",\"secondary_cta_url\":\"/campaigns\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(14,1,'counters',2,'{\"heading\":\"Our impact so far\",\"items\":[{\"value\":12500,\"suffix\":\"+\",\"label\":\"Students supported\"},{\"value\":85,\"suffix\":\"\",\"label\":\"Partner schools\"},{\"value\":340,\"suffix\":\"+\",\"label\":\"Active volunteers\"},{\"value\":27,\"suffix\":\"\",\"label\":\"Districts reached\"}]}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(15,1,'features',4,'{\"heading\":\"How we create impact\",\"subheading\":\"Every programme is designed with local partners and measured by real outcomes.\",\"cards\":[{\"icon\":\"pages\",\"title\":\"Education Access\",\"text\":\"Learning centres, digital classrooms, and remedial support that keep children in school and learning at grade level.\"},{\"icon\":\"modules\",\"title\":\"Skill Training\",\"text\":\"Job-ready vocational and digital-skills courses for youth, with placement support and industry partners.\"},{\"icon\":\"campaigns\",\"title\":\"Scholarships\",\"text\":\"Merit and need-based scholarships that cover fees, materials, and mentoring for first-generation learners.\"}]}',1,'2026-07-21 02:01:53','2026-07-21 02:27:31'),(16,1,'cta_banner',6,'{\"heading\":\"Your support creates opportunity\",\"text\":\"A gift of any size helps a child stay in school and a young person find work. Donations are eligible for tax deduction under Section 80G.\",\"cta_label\":\"Make a donation\",\"cta_url\":\"/donate\"}',1,'2026-07-21 02:01:53','2026-07-21 02:27:31'),(17,1,'faq',7,'{\"heading\":\"Questions donors often ask\",\"items\":[{\"q\":\"Are my donations tax-exempt?\",\"a\":\"Yes. Eduskill India Foundation is registered under Sections 12A and 80G of the Income Tax Act, so your donation is eligible for a tax deduction. A receipt with our 80G registration details is issued for every contribution.\"},{\"q\":\"How is my donation used?\",\"a\":\"The large majority of every rupee goes directly to programmes on the ground. We publish an annual report and share regular updates with donors on how funds are deployed.\"},{\"q\":\"Can I volunteer instead of donating?\",\"a\":\"Absolutely. Volunteers support teaching, events, and skills workshops. Use the contact page to register your interest and availability.\"}]}',1,'2026-07-21 02:01:53','2026-07-21 02:27:31'),(18,2,'rich_text',1,'{\"heading\":\"About Eduskill India Foundation\",\"body_html\":\"<p>Eduskill India Foundation is a non-profit trust working to widen access to education and skills for underserved communities across India. Since our founding we have partnered with government schools, local organisations, and volunteers to reach children and youth in rural and semi-urban districts.</p><p>Our mission is simple: no child should be held back by where they were born or what their family can afford. We combine classroom support, vocational training, and scholarships with careful measurement, so every programme is accountable to the communities we serve and to our donors.</p>\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(19,3,'rich_text',1,'{\"heading\":\"Our campaigns\",\"body_html\":\"<p>Our active fundraising campaigns support specific programmes, from keeping children in school to equipping learning centres. Detailed campaign pages with live progress are coming soon.</p><p>In the meantime, to support our work today, please visit the donate page.</p>\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(20,4,'rich_text',1,'{\"heading\":\"Contact us\",\"body_html\":\"<p>We would love to hear from you, whether you want to donate, volunteer, or partner with us.</p><p>Email: hello@eduskill.org.in<br>Phone: +91 00000 00000<br>Registered office: New Delhi, India</p>\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(21,5,'rich_text',1,'{\"heading\":\"Support our work\",\"body_html\":\"<p>Online card and UPI donations will be available shortly. To contribute today, you can transfer directly to our account. Every donation is eligible for a tax deduction under Section 80G, and a receipt will be issued.</p><p><strong>Bank transfer</strong><br>Account name: Eduskill India Foundation<br>Account number: 000000000000<br>IFSC: XXXX0000000<br>UPI: eduskill@bank</p><p>Please email your transfer details so we can send your 80G receipt.</p>\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(22,6,'rich_text',1,'{\"heading\":\"Privacy Policy\",\"body_html\":\"<p>This policy explains how Eduskill India Foundation collects and uses personal information. We collect only what we need to process donations, respond to enquiries, and keep supporters informed, and we never sell personal data.</p><p>This is placeholder wording. Please review it with a legal advisor before going live.</p>\"}',1,'2026-07-21 02:01:53','2026-07-21 02:01:53'),(23,1,'campaign_list',3,'{\"heading\":\"Campaigns you can support\",\"limit\":3}',1,'2026-07-21 02:27:31','2026-07-21 02:27:31'),(24,1,'testimonial_slider',5,'{\"heading\":\"Voices from our community\",\"limit\":3}',1,'2026-07-21 02:27:31','2026-07-21 02:27:31'),(25,2,'team_grid',2,'{\"heading\":\"The people behind Eduskill\",\"limit\":8}',1,'2026-07-21 02:27:31','2026-07-21 02:27:31');
/*!40000 ALTER TABLE `page_sections` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(190) NOT NULL,
  `title` varchar(200) NOT NULL,
  `template` varchar(60) NOT NULL DEFAULT 'default',
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `draft_json` longtext DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pages_slug` (`slug`),
  KEY `idx_pages_status` (`status`),
  KEY `fk_pages_creator` (`created_by`),
  CONSTRAINT `fk_pages_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,'home','Home','default','published',1,NULL,2,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL),(2,'about-us','About Us','default','published',0,NULL,1,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL),(3,'campaigns','Our Campaigns','default','published',0,NULL,1,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL),(4,'contact','Contact Us','default','published',0,NULL,1,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL),(5,'donate','Donate','default','published',1,NULL,1,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL),(6,'privacy-policy','Privacy Policy','default','published',1,NULL,1,'2026-07-21 02:01:53',NULL,'2026-07-21 01:46:00','2026-07-21 02:01:53',NULL);
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pwreset_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `group_name` varchar(60) NOT NULL DEFAULT 'general',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_name` (`name`),
  KEY `idx_permissions_group` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','View dashboard','dashboard','2026-07-21 01:21:57'),(2,'pages.view','View pages','cms','2026-07-21 01:21:57'),(3,'pages.create','Create pages','cms','2026-07-21 01:21:57'),(4,'pages.edit','Edit pages','cms','2026-07-21 01:21:57'),(5,'pages.delete','Delete pages','cms','2026-07-21 01:21:57'),(6,'pages.publish','Publish pages','cms','2026-07-21 01:21:57'),(7,'menus.manage','Manage menus','cms','2026-07-21 01:21:57'),(8,'banners.manage','Manage banners','cms','2026-07-21 01:21:57'),(9,'widgets.manage','Manage widgets','cms','2026-07-21 01:21:57'),(10,'announcements.manage','Manage announcements','cms','2026-07-21 01:21:57'),(11,'media.view','View media library','media','2026-07-21 01:21:57'),(12,'media.upload','Upload media','media','2026-07-21 01:21:57'),(13,'media.delete','Delete media','media','2026-07-21 01:21:57'),(14,'blog.view','View posts','blog','2026-07-21 01:21:57'),(15,'blog.create','Create posts','blog','2026-07-21 01:21:57'),(16,'blog.edit','Edit posts','blog','2026-07-21 01:21:57'),(17,'blog.delete','Delete posts','blog','2026-07-21 01:21:57'),(18,'blog.publish','Publish posts','blog','2026-07-21 01:21:57'),(19,'comments.moderate','Moderate comments','blog','2026-07-21 01:21:57'),(20,'team.manage','Manage team','content','2026-07-21 01:21:57'),(21,'faqs.manage','Manage FAQs','content','2026-07-21 01:21:57'),(22,'testimonials.manage','Manage testimonials','content','2026-07-21 01:21:57'),(23,'gallery.manage','Manage galleries','content','2026-07-21 01:21:57'),(24,'events.manage','Manage events','content','2026-07-21 01:21:57'),(25,'programs.manage','Manage programs','content','2026-07-21 01:21:57'),(26,'campaigns.manage','Manage campaigns','content','2026-07-21 01:21:57'),(27,'scholarships.manage','Manage scholarships','content','2026-07-21 01:21:57'),(28,'internships.manage','Manage internships','content','2026-07-21 01:21:57'),(29,'jobs.manage','Manage jobs','content','2026-07-21 01:21:57'),(30,'downloads.manage','Manage downloads','content','2026-07-21 01:21:57'),(31,'certificates.manage','Manage certificates','content','2026-07-21 01:21:57'),(32,'forms.manage','Manage forms','forms','2026-07-21 01:21:57'),(33,'submissions.view','View form submissions','forms','2026-07-21 01:21:57'),(34,'submissions.delete','Delete submissions','forms','2026-07-21 01:21:57'),(35,'newsletter.manage','Manage newsletter','forms','2026-07-21 01:21:57'),(36,'seo.manage','Manage SEO','seo','2026-07-21 01:21:57'),(37,'redirects.manage','Manage redirects','seo','2026-07-21 01:21:57'),(38,'settings.manage','Manage settings','settings','2026-07-21 01:21:57'),(39,'users.view','View users','users','2026-07-21 01:21:57'),(40,'users.create','Create users','users','2026-07-21 01:21:57'),(41,'users.edit','Edit users','users','2026-07-21 01:21:57'),(42,'users.delete','Delete users','users','2026-07-21 01:21:57'),(43,'roles.manage','Manage roles','users','2026-07-21 01:21:57'),(44,'audit.view','View audit log','users','2026-07-21 01:21:57');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_postcat_slug` (`slug`),
  KEY `idx_postcat_parent` (`parent_id`),
  CONSTRAINT `fk_postcat_parent` FOREIGN KEY (`parent_id`) REFERENCES `post_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_categories` WRITE;
/*!40000 ALTER TABLE `post_categories` DISABLE KEYS */;
INSERT INTO `post_categories` VALUES (1,'Impact Stories','impact-stories',NULL,NULL,'2026-07-21 04:29:23'),(2,'Announcements','announcements',NULL,NULL,'2026-07-21 04:29:23'),(3,'Education','education',NULL,NULL,'2026-07-21 04:29:23');
/*!40000 ALTER TABLE `post_categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_tag_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_tag_map` (
  `post_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`post_id`,`tag_id`),
  KEY `idx_ptm_tag` (`tag_id`),
  CONSTRAINT `fk_ptm_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ptm_tag` FOREIGN KEY (`tag_id`) REFERENCES `post_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_tag_map` WRITE;
/*!40000 ALTER TABLE `post_tag_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_tag_map` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `post_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `post_tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `post_tags` WRITE;
/*!40000 ALTER TABLE `post_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `post_tags` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `body` longtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','scheduled') NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `is_breaking` tinyint(1) NOT NULL DEFAULT 0,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `views` bigint(20) unsigned NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posts_slug` (`slug`),
  KEY `idx_posts_status` (`status`,`published_at`),
  KEY `idx_posts_category` (`category_id`),
  KEY `fk_posts_author` (`author_id`),
  CONSTRAINT `fk_posts_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_posts_category` FOREIGN KEY (`category_id`) REFERENCES `post_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
INSERT INTO `posts` VALUES (1,1,'Reaching 10,000 children','reaching-10000-children','A milestone year for education access across 27 districts.','<p>This year our learning centres reached more children than ever before, thanks to our donors and volunteers.</p>',NULL,'published',0,0,NULL,0,'2026-07-19 04:29:23',NULL,'2026-07-21 04:29:23','2026-07-21 04:29:23',NULL),(2,2,'New skilling centre opens','new-skilling-centre-opens','Our newest vocational training centre welcomes its first batch.','<p>We are proud to open a new skilling centre offering digital and vocational courses with placement support.</p>',NULL,'published',0,0,NULL,0,'2026-07-13 04:29:23',NULL,'2026-07-21 04:29:23','2026-07-21 04:29:23',NULL),(3,3,'Why scholarships matter','why-scholarships-matter','How a small scholarship changes a family for generations.','<p>For a first-generation learner, a scholarship is often the difference between finishing school and dropping out.</p>',NULL,'published',0,0,NULL,0,'2026-07-06 04:29:23',NULL,'2026-07-21 04:29:23','2026-07-21 04:29:23',NULL);
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `programs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(60) DEFAULT NULL,
  `kind` enum('program','scheme') NOT NULL DEFAULT 'program',
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programs_slug` (`slug`),
  KEY `idx_programs_kind` (`kind`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (2,'Education Access','education-access','Learning centres, digital classrooms and remedial support that keep children in school.',NULL,NULL,'pages','program',1,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL),(3,'Skill Training Programme','skill-training-programme','Job-ready vocational and digital-skills courses with placement support.',NULL,NULL,'programs','program',2,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL),(4,'Scholarship Support','scholarship-support','Merit and need-based scholarships covering fees, materials and mentoring.',NULL,NULL,'campaigns','program',3,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL),(5,'Women Empowerment','women-empowerment','Livelihood training and micro-enterprise support for women.',NULL,NULL,'users','program',4,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL),(6,'Digital Literacy Drive','digital-literacy-drive','Basic computer and internet skills for rural youth.',NULL,NULL,'media','program',5,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL),(7,'Health & Nutrition','health-nutrition','Nutrition and health awareness for schoolchildren and families.',NULL,NULL,'programs','scheme',6,1,'2026-07-21 03:59:23','2026-07-21 03:59:23',NULL);
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `redirects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `redirects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `from_path` varchar(255) NOT NULL,
  `to_path` varchar(255) NOT NULL,
  `status_code` smallint(5) unsigned NOT NULL DEFAULT 301,
  `hits` bigint(20) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_redirect_from` (`from_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `redirects` WRITE;
/*!40000 ALTER TABLE `redirects` DISABLE KEYS */;
/*!40000 ALTER TABLE `redirects` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `remember_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `selector` varchar(32) NOT NULL,
  `validator_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_selector` (`selector`),
  KEY `idx_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `remember_tokens` WRITE;
/*!40000 ALTER TABLE `remember_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `remember_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(1,28),(1,29),(1,30),(1,31),(1,32),(1,33),(1,34),(1,35),(1,36),(1,37),(1,38),(1,39),(1,40),(1,41),(1,42),(1,43),(1,44),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,17),(2,18),(2,19),(2,20),(2,21),(2,22),(2,23),(2,24),(2,25),(2,26),(2,27),(2,28),(2,29),(2,30),(2,31),(2,32),(2,33),(2,34),(2,35),(2,36),(2,37);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Admin','Full system access',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(2,'staff','Staff / Admin','Content and operations, no system settings',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(3,'school','School','Partner school portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(4,'teacher','Teacher','Course and student portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(5,'student','Student','Learner portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(6,'volunteer','Volunteer','Volunteer portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(7,'member','Member','Membership portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57'),(8,'donor','Donor','Donor portal',1,'2026-07-21 01:21:57','2026-07-21 01:21:57');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `scholarships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scholarships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `summary` varchar(500) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `eligibility` text DEFAULT NULL,
  `amount_text` varchar(120) DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scholarships_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `scholarships` WRITE;
/*!40000 ALTER TABLE `scholarships` DISABLE KEYS */;
/*!40000 ALTER TABLE `scholarships` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `seo_meta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seo_meta` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `focus_keyword` varchar(150) DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` varchar(320) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `twitter_card` varchar(30) DEFAULT NULL,
  `noindex` tinyint(1) NOT NULL DEFAULT 0,
  `nofollow` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_seo_entity` (`entity`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `seo_meta` WRITE;
/*!40000 ALTER TABLE `seo_meta` DISABLE KEYS */;
/*!40000 ALTER TABLE `seo_meta` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(150) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `type` enum('string','int','bool','json','text') NOT NULL DEFAULT 'string',
  `group_name` varchar(60) NOT NULL DEFAULT 'general',
  `autoload` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settings_key` (`setting_key`),
  KEY `idx_settings_group` (`group_name`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'site_name','Eduskill India Foundation','string','general',1,'2026-07-21 01:46:00'),(2,'site_tagline','','string','general',1,'2026-07-21 04:34:40'),(3,'brand_color','#1e3a8a','string','theme',1,'2026-07-21 03:56:20'),(4,'accent_color','#f97316','string','theme',1,'2026-07-21 03:56:20'),(5,'search_engines_allowed','0','bool','seo',1,'2026-07-21 01:46:00'),(11,'contact_phone','','string','general',1,'2026-07-21 04:34:40'),(12,'contact_email','','string','general',1,'2026-07-21 04:34:40'),(13,'contact_address','','string','general',1,'2026-07-21 04:34:40'),(14,'whatsapp_number','','string','general',1,'2026-07-21 04:34:40'),(15,'hero_eyebrow','','string','home',1,'2026-07-21 04:34:40'),(16,'hero_title','Education & skills that change lives','string','home',1,'2026-07-21 03:56:20'),(17,'hero_subtitle','','string','home',1,'2026-07-21 04:34:40'),(18,'stat_students','','string','home',1,'2026-07-21 04:34:40'),(19,'stat_schools','','string','home',1,'2026-07-21 04:34:40'),(20,'stat_volunteers','','string','home',1,'2026-07-21 04:34:40'),(21,'stat_districts','','string','home',1,'2026-07-21 04:34:40'),(40,'mail_notify_email','hello@eduskill.org.in','string','mail',1,'2026-07-21 05:16:33'),(41,'mail_driver','log','string','mail',1,'2026-07-21 05:16:33');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `team_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `role_title` varchar(150) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `socials_json` longtext DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_team_active` (`is_active`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `team_members` WRITE;
/*!40000 ALTER TABLE `team_members` DISABLE KEYS */;
INSERT INTO `team_members` VALUES (1,'Ananya Sharma','Founder & Director',NULL,'Leads programme strategy and partnerships.',NULL,1,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL),(2,'Rahul Verma','Head of Programmes',NULL,'Runs the education and skilling initiatives on the ground.',NULL,2,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL),(3,'Priya Nair','Partnerships Lead',NULL,'Builds school and corporate partnerships.',NULL,3,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL),(4,'Imran Qureshi','Field Coordinator',NULL,'Coordinates volunteers and learning centres.',NULL,4,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL);
/*!40000 ALTER TABLE `team_members` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `testimonials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testimonials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `author_name` varchar(150) NOT NULL,
  `author_role` varchar(150) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `quote` text NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_testimonials_active` (`is_active`,`position`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `testimonials` WRITE;
/*!40000 ALTER TABLE `testimonials` DISABLE KEYS */;
INSERT INTO `testimonials` VALUES (1,'Sunita Devi','Parent, Bihar',NULL,'My daughter is the first in our family to finish school. The scholarship made it possible.',5,NULL,1,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL),(2,'Arjun Mehta','Skilling programme graduate',NULL,'The training helped me get my first job in a year. I am now supporting my family.',5,NULL,2,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL),(3,'Kavya Reddy','Volunteer, Hyderabad',NULL,'Volunteering here has been the most meaningful work I have done. The impact is real and visible.',5,NULL,3,1,'2026-07-21 02:27:31','2026-07-21 02:27:31',NULL);
/*!40000 ALTER TABLE `testimonials` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `meta_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`,`created_at`),
  KEY `idx_activity_action` (`action`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_activity_logs` WRITE;
/*!40000 ALTER TABLE `user_activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(190) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `mobile_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive','pending','suspended') NOT NULL DEFAULT 'pending',
  `avatar` varchar(255) DEFAULT NULL,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Prashant Admin','prashantmixadda@gmail.com','2026-07-21 01:21:57',NULL,NULL,'$2y$12$G.U34mS/gtuTGMrgV/SXLu7y/zyMIoqwkIcFNhtR5I/czyhoEhE3S','active',NULL,NULL,NULL,'2026-07-21 05:15:37','::1',NULL,'2026-07-21 01:21:57','2026-07-21 05:15:37');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `verification_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `verification_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `channel` enum('email','mobile') NOT NULL DEFAULT 'email',
  `token_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_verif_user` (`user_id`),
  CONSTRAINT `fk_verif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `verification_tokens` WRITE;
/*!40000 ALTER TABLE `verification_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `verification_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `widgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `widgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `area` varchar(60) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `settings_json` longtext DEFAULT NULL,
  `position` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_widgets_area` (`area`,`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `widgets` WRITE;
/*!40000 ALTER TABLE `widgets` DISABLE KEYS */;
/*!40000 ALTER TABLE `widgets` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

