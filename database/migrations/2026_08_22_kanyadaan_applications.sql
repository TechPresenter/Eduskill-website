-- =============================================================================
--  Kanya Daan Project — beneficiary applications
-- -----------------------------------------------------------------------------
--  Backs the public form at /kanyadaan-apply and the admin workflow at
--  /admin/kanyadaan-applications (verification -> committee approval ->
--  distribution -> acknowledgement).
--
--  Apply to an existing install with:
--      mysql -u root eduskill < database/migrations/2026_08_22_kanyadaan_applications.sql
--
--  Design notes
--   * The repeating family table is JSON in a TEXT column, as the coordinator
--     module does for education rows. Everything searched, filtered or counted
--     — names, contact, place, income, status — is a real column.
--   * Aadhaar/ID and bank account numbers are written through sec_encrypt()
--     (AES-256-GCM) whenever APP_KEY is set, so those columns normally hold
--     "enc:…". Only the last four digits are kept in the clear, which is all the
--     admin list needs to match a person to their paperwork.
--   * `legally_permissible`, `bride_age` and `groom_age` exist because this
--     project must be able to show, per case, that it did not assist a marriage
--     below the legal age. The handler enforces 18/21 before it will store a row.
-- =============================================================================

DROP TABLE IF EXISTS `kanyadaan_applications`;
CREATE TABLE `kanyadaan_applications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_no` varchar(32) DEFAULT NULL,

  -- ---- A. Applicant details -------------------------------------------------
  `applicant_name` varchar(128) NOT NULL,
  `relationship` enum('bride','father','mother','guardian','other') NOT NULL DEFAULT 'bride',
  `relationship_other` varchar(96) DEFAULT NULL,
  `phone` varchar(32) NOT NULL,
  `country_name` varchar(64) DEFAULT NULL,
  `country_iso` char(2) DEFAULT NULL,
  `country_dial` varchar(6) DEFAULT NULL,
  `whatsapp` varchar(32) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,

  -- ---- Location -------------------------------------------------------------
  `state` varchar(96) DEFAULT NULL,
  `district` varchar(96) DEFAULT NULL,
  `block` varchar(96) DEFAULT NULL,
  `panchayat` varchar(96) DEFAULT NULL,
  `village` varchar(128) DEFAULT NULL,

  -- ---- B. Bride details -----------------------------------------------------
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

  -- ---- C. Groom details -----------------------------------------------------
  `groom_name` varchar(128) DEFAULT NULL,
  `groom_dob` date DEFAULT NULL,
  `groom_age` tinyint(3) unsigned DEFAULT NULL,
  `groom_occupation` varchar(128) DEFAULT NULL,
  `groom_address` varchar(500) DEFAULT NULL,

  -- ---- D. Marriage details --------------------------------------------------
  `marriage_date` date DEFAULT NULL,
  `marriage_location` varchar(191) DEFAULT NULL,
  `marriage_type` varchar(128) DEFAULT NULL,
  `legally_permissible` tinyint(1) NOT NULL DEFAULT 0,

  -- ---- 10. Family details ---------------------------------------------------
  `family_members` text DEFAULT NULL COMMENT 'JSON [{name,age,relationship,occupation,income}]',
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `annual_income` decimal(12,2) DEFAULT NULL,
  `house_type` enum('','kutcha','semi_pucca','pucca') NOT NULL DEFAULT '',
  `family_size` tinyint(3) unsigned DEFAULT NULL,
  `earning_members` tinyint(3) unsigned DEFAULT NULL,

  -- ---- 11. Economic condition -----------------------------------------------
  `financial_hardship` tinyint(1) NOT NULL DEFAULT 0,
  `hardship_reason` text DEFAULT NULL,
  `existing_debts` text DEFAULT NULL,
  `govt_assistance` tinyint(1) NOT NULL DEFAULT 0,
  `govt_assistance_details` varchar(500) DEFAULT NULL,

  -- ---- 12. Support requested ------------------------------------------------
  `support_items` varchar(500) DEFAULT NULL COMMENT 'CSV of whitelisted labels',
  `support_justification` text DEFAULT NULL,

  -- ---- 13. Document checklist -----------------------------------------------
  `documents` text DEFAULT NULL COMMENT 'JSON {slot: uploads-relative path}',

  -- ---- 14. Declaration ------------------------------------------------------
  `declared_place` varchar(128) DEFAULT NULL,
  `declared_on` date DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `dowry_declaration` tinyint(1) NOT NULL DEFAULT 0,

  -- ---- Office workflow ------------------------------------------------------
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
