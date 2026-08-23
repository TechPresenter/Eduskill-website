-- =============================================================================
--  Community Coordinator applications  (Panchayat / Block / District)
-- -----------------------------------------------------------------------------
--  Backs the public application form at /coordinator-apply and the admin inbox
--  at /admin/coordinator-applications.
--
--  Apply to an existing install with:
--      mysql -u root eduskill < database/migrations/2026_08_22_coordinator_applications.sql
--  New installs get the table from database/eduskill.sql, which carries the
--  same definition.
--
--  Design notes
--   * The repeating sections of the paper form (the education rows and the
--     document checklist) are stored as JSON in TEXT columns, the same
--     way team_members.socials and the press_coverage setting already do. The
--     fields that are searched, filtered or reported on — name, contact, place,
--     position, status — are real columns.
--   * `id_proof_no` holds an Aadhaar / government ID number. It is written
--     through sec_encrypt() (AES-256-GCM) whenever APP_KEY is configured, so the
--     column normally contains "enc:…" rather than the number. `id_proof_last4`
--     is the only plaintext kept, purely so the admin list can show a masked
--     hint without decrypting every row.
-- =============================================================================

DROP TABLE IF EXISTS `coordinator_applications`;
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
