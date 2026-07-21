# Product Requirements Document (PRD)
### Eduskill India Foundation — Website & CMS Platform

**Version:** 1.0 · **Status:** Phase 1 delivered · **Owner:** Eduskill India Foundation

---

## 1. Vision

Give Eduskill India Foundation a **world-class digital presence** that builds trust, tells its
impact story, and converts visitors into donors, volunteers, and partners — while being **fully
manageable by a non-technical team** with no ongoing developer dependency.

## 2. Mission statement (of the organisation)

Empowering underserved communities across India through **education, skills training, and
opportunity** — delivered with local partners and measured by real outcomes.

## 3. Goals & objectives

| # | Goal | Success measure |
|---|------|-----------------|
| G1 | Establish a credible, professional online presence | Modern, responsive, fast site live on the org's domain |
| G2 | Enable self-service content management | Owner edits every page/module via admin, no code |
| G3 | Drive engagement | Working contact, volunteer, partner, careers & newsletter capture |
| G4 | Be transparent & compliant | Certifications (12A/80G), reports, legal pages, audit trail |
| G5 | Deploy & maintain cheaply | Runs on entry-level shared hosting, deploy-by-upload |
| G6 | Scale in phases | Clear path to donations, portals, and multi-role access |

## 4. Target users / personas

- **Visitor / Beneficiary** — discovers programmes, schemes, scholarships; applies or enquires.
- **Donor** — learns about campaigns, sees progress & tax benefits, intends to give.
- **Volunteer / Partner / Job seeker** — registers interest via forms.
- **Content owner (non-coder)** — the NGO staff who manage all site content daily.
- **Super Admin** — configures settings, users, roles, and the whole system.

## 5. Scope

### In scope (Phase 1 — this release)
- Public website: **30 pages** (see [FEATURES.md](FEATURES.md)) — dynamic, CMS-driven.
- Admin CMS: dashboard, page builder, content modules (campaigns, programmes, events, team,
  testimonials, FAQs, blog), media library, forms/enquiries inbox, newsletter, users & roles, settings.
- REST API for the frontend/admin interactions.
- Security: RBAC, CSRF, hashed passwords, login throttling, HTML sanitisation.
- SEO essentials, responsive design, dark mode, premium UI/UX.
- Offline donation info (bank / UPI) — no online payment yet.

### Out of scope (later phases)
- **Phase 2:** transactional email (SMTP + SPF/DKIM), 2FA, newsletter double opt-in.
- **Phase 3:** online donations (Razorpay), 80G receipts, donor portal.
- **Phase 4+:** school / teacher / student / volunteer self-service portals & dashboards.

## 6. Functional requirements (summary)

- **FR-1** Every public page renders content from the database; the owner can edit copy, sections,
  images and SEO without code.
- **FR-2** A drag-and-drop **page builder** composes pages from allow-listed section types
  (hero, rich text, features, counters, CTA, FAQ, campaign list, team grid, testimonials).
- **FR-3** Content modules (campaigns, programmes, events, team, testimonials, FAQs, blog, gallery,
  scholarships, internships, jobs, downloads, certificates) are full CRUD in the admin.
- **FR-4** Public forms (contact, volunteer, partner, careers, scholarship/internship apply,
  newsletter) capture leads to the database with spam protection.
- **FR-5** Certificate verification lets the public validate a certificate by its number.
- **FR-6** RBAC restricts admin capabilities per role/permission.
- **FR-7** Auto-generated HTML sitemap and SEO metadata per entity.

## 7. Non-functional requirements

| Area | Requirement |
|---|---|
| **Performance** | Fast TTFB on shared hosting; precompiled CSS; lazy images; minimal JS |
| **Responsive** | Flawless on desktop, tablet, mobile (mobile-first) |
| **Accessibility** | Semantic HTML, focus states, reduced-motion support, ARIA where needed |
| **SEO** | Clean URLs, per-page meta/OG tags, sitemap, robots control |
| **Security** | See [TRD.md](TRD.md) §Security — OWASP-aware defaults |
| **Maintainability** | No framework lock-in; conventions documented; deploy-by-upload |
| **Portability** | Runs on PHP 8 + MySQL/MariaDB shared hosting with no shell access |
| **Compliance** | DPDP-aware data handling; 12A/80G/CSR trust signals; audit logging |

## 8. Constraints & assumptions

- **Non-coder owner** — the UX of the admin must be honest and simple; unbuilt items are labelled.
- **No Node/Composer on the server** — all build output is committed; libraries are CDN-optional.
- **Hostinger flat layout** — the app lives in `public_html` with a portable base-path detection.
- **India-first** — INR currency (stored as paise), IST timezone, Indian digit grouping.

## 9. Success metrics

- Owner can publish a new page and a new campaign end-to-end **without assistance**.
- All 30 pages live, responsive, and error-free.
- Zero secrets committed; site deploys to production by upload + installer.
- Lead-capture forms deliver enquiries to the admin inbox reliably.
