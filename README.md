# EDUSKILL INDIA FOUNDATION — NGO Management System

A complete, production-ready NGO website + admin panel built in **Core PHP 8.2+**
with **PDO/MySQL**, a hand-written static **CSS design system**, and **vanilla JS**.
No framework, no Composer, no Node — runs on any Apache shared host.

> Empowering Communities • Spreading Hope • Creating Change
> Patna, Bihar 840007 · info@eduskillindia.org · +91-9955446477
> CIN U88900BR2025NPL079155 · Directors: Shanti Devi & Prashant Kumar

---

## Highlights

- **60+ pages** — a premium public website + a custom admin panel (no template).
- **Fully DB-driven** — every piece of content is editable from the admin panel.
- **Security first** — CSRF on every form, PDO prepared statements everywhere,
  output escaping, `password_hash`, hardened sessions, throttled login, strict
  file-upload validation, security headers.
- **Premium UI** — Outfit/Jakarta typography, blue→purple→orange→green gradient
  system, 3D & glass buttons, mega menu, animated footer, page loader, scroll
  reveal, counters, tilt, marquee, lightbox, timeline, tabs, accordions, FAB,
  toasts, skeletons — all light/dark aware and fully responsive.
- **Member accounts** — signup, login, email + OTP verification, password reset.
- **REST API** at `/api/v1` returning JSON with pagination & filters (+ JWT scaffold).
- **SEO** — dynamic `sitemap.xml`, per-page meta overrides, Open Graph, Twitter
  cards, JSON-LD (Organization / Breadcrumb / Article).

## Tech stack

| Layer     | Choice                                             |
|-----------|----------------------------------------------------|
| Language  | PHP 8.2+ (no framework)                            |
| Database  | MySQL / MariaDB 10.4+ (InnoDB, utf8mb4)            |
| DB access | PDO, prepared statements only                      |
| Styling   | Static hand-written CSS (`tailwind.css`, `premium.css`, `admin.css`) |
| JS        | Vanilla ES6 (`main.js`, `premium.js`, `forms.js`, `admin.js`) |
| CDN (opt) | SweetAlert2 (forms) · Chart.js (admin dashboard) · Google Fonts |

## Folder map

```
config.php                  All environment constants (DB, URLs, mail, security)
.htaccess                   Clean URLs, HTTPS-ready, security headers, gzip, cache
robots.txt / sitemap.php    SEO (sitemap.xml served dynamically via rewrite)

includes/                   The "spine" (shared, reused everywhere)
  bootstrap.php               Single entry point every page requires first
  database.php                PDO singleton (getPDO)
  functions.php               DB helpers, settings, escaping, dates, validation…
  csrf.php  auth.php  member_auth.php   Security + admin + member auth
  upload.php  pagination.php  helper.php  seo.php  mailer.php
  header.php  navbar.php  footer.php  sidebar.php    Layout partials

assets/
  css/{tailwind,premium,admin}.css     Design system
  js/{main,premium,forms,admin}.js     Behaviour
  images/                              Logo + SVG placeholders

<public pages>.php          index, about, our-story, programs, causes, schemes,
                            scholarship, campaigns, events, calendar, gallery,
                            media, news-media, blogs, blog-details, testimonials,
                            success-stories, team, leadership-team, management-body,
                            ngo-details, achievements, certificates, verify-certificate,
                            resources, volunteer, internship, membership, career,
                            become-partner, donate, contact, feedback, faqs,
                            privacy-policy, terms, refund-policy, disclaimer,
                            cookie-policy, sitemap-page, 404
<auth pages>.php            login, signup, forgot-password, reset-password,
                            verify-email, verify-otp, account, logout

forms/                      AJAX POST handlers → JSON (contact, newsletter,
                            volunteer, internship, donate, feedback, comment,
                            event-register, partner, scholarship, career-apply,
                            membership, download)

api/v1/                     REST endpoints (blogs, events, gallery, team,
                            testimonials, programs, contact, volunteer, donate)

admin/                      Custom admin panel (login, dashboard, ~50 modules)
  partials/head.php foot.php   Admin layout

database/                   eduskill.sql (the single schema + seed file)
docs/                       DEV_CONTRACT.md, DEV_CONTRACT_V2.md (developer reference)
uploads/                    User uploads (script execution disabled)
logs/                       PHP error log
```

## Quick start (local XAMPP)

1. Place this folder at `C:\xampp\htdocs\pwf` (already there).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Create the database and import the single SQL file (phpMyAdmin, or shell):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE pwf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:\xampp\mysql\bin\mysql.exe -u root pwf < database\eduskill.sql
   ```
   On a database that already has real data, run **Section 2 only** — see the
   banner inside the file.
4. Visit **http://localhost/pwf/**
5. Admin: **http://localhost/pwf/admin/login**
   - Email: `admin@eduskillindia.org`
   - Password: `Admin@123`  → **change it immediately** (Admin → My Profile).

See **INSTALL.md** for shared-hosting deployment and **TESTING.md** for the
full manual test checklist.

## Configuration

All settings live in `config.php`:
- `APP_ENV` — `development` (show errors) or `production` (hide + log errors).
- `DB_*` — database credentials.
- `BASE_URI` / `APP_URL` — where the site is installed (`/pwf` locally; `''` at a domain root).
- `ASSET_VERSION` — bump to bust CSS/JS caches after edits.
- Mail/SMTP — set `USE_SMTP` and `SMTP_*` (or override via Admin → Settings).

## License

Proprietary — © 2026 EDUSKILL INDIA FOUNDATION. All rights reserved.
