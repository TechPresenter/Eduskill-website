-- =============================================================================
--  Schemes — richer project pages + brochure downloads
-- -----------------------------------------------------------------------------
--  The schemes module already carried title / description / eligibility /
--  benefits / documents. A full project page (Kanya Daan is the first) also
--  needs its objectives, an indicative support budget, the selection process,
--  the CSR + transparency commitments, guidelines and an FAQ — and, above all,
--  a downloadable brochure.
--
--  Apply to an existing install with:
--      mysql -u root eduskill < database/migrations/2026_08_22_scheme_sections_and_brochures.sql
--
--  ALTER, not DROP/CREATE: `schemes` already holds live rows.
--
--  Text fields follow the convention the module already uses — ONE ITEM PER
--  LINE, parsed by the $toList() closure in schemes.php. Two exceptions carry
--  a separator, documented on the column:
--    * support_items   "Label | Amount"   (the indicative budget table)
--    * faq             "Question :: Answer"
--  brochures is a JSON array of extra downloads; `brochure` is the primary one.
-- =============================================================================

ALTER TABLE `schemes`
  ADD COLUMN `subtitle` varchar(255) DEFAULT NULL COMMENT 'Tagline under the title, e.g. the Hindi strapline' AFTER `title`,
  ADD COLUMN `donate_url` varchar(255) DEFAULT NULL COMMENT 'Support/Donate CTA, beside Apply' AFTER `apply_url`,
  ADD COLUMN `brochure` varchar(255) DEFAULT NULL COMMENT 'Primary brochure, uploads-relative' AFTER `image`,
  ADD COLUMN `brochures` text DEFAULT NULL COMMENT 'JSON [{label,path,size}] of extra downloads' AFTER `brochure`,
  ADD COLUMN `objectives` text DEFAULT NULL COMMENT 'One per line',
  ADD COLUMN `support_items` text DEFAULT NULL COMMENT 'One per line, "Label | Amount"',
  ADD COLUMN `budget_note` text DEFAULT NULL,
  ADD COLUMN `process_steps` text DEFAULT NULL COMMENT 'One step per line, in order',
  ADD COLUMN `partnership` text DEFAULT NULL COMMENT 'CSR / donor partnership, one per line',
  ADD COLUMN `transparency` text DEFAULT NULL COMMENT 'One per line',
  ADD COLUMN `guidelines` text DEFAULT NULL COMMENT 'Rich text — positioning, safeguards, disclaimers',
  ADD COLUMN `faq` text DEFAULT NULL COMMENT 'One per line, "Question :: Answer"';
