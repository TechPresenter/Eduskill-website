<div align="center">

# 🎓 Eduskill India Foundation

### Enterprise-grade NGO website & content management platform

*Empowering underserved communities across India through education, skills, and opportunity.*

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-utf8mb4-4479A1?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.4-06B6D4?logo=tailwindcss&logoColor=white)
![No Framework](https://img.shields.io/badge/framework-none-success)
![No Composer](https://img.shields.io/badge/composer-not%20required-success)
![License](https://img.shields.io/badge/license-Proprietary-lightgrey)

</div>

---

## 📖 Overview

**Eduskill India Foundation** is a complete, self-hosted website + admin CMS for a modern NGO. It is
built in **Core PHP 8 + MySQL** with a **flat-file architecture** and a **REST API** — deliberately
**no Node.js, Composer, React, or build step at runtime**. Everything ships ready to upload to any
PHP 8 shared host (Hostinger, cPanel, etc.).

A non-technical owner manages the **entire site** — pages, campaigns, programmes, events, blog,
gallery, team, testimonials, enquiries and more — through a polished admin panel. No code editing.

> This repository is the full source: public website (30 pages), admin panel, REST API, database
> schema + seed data, the compiled design system, and complete documentation.

## ✨ Highlights

- **30-page public website** — dynamic, database-driven, SEO-friendly, fully responsive
- **Full admin CMS** — page builder (drag-and-drop sections), content modules, media library, forms inbox, users & roles
- **REST API** — clean JSON endpoints powering the frontend and admin (AJAX + Fetch)
- **RBAC security** — 8 roles, granular permissions, bcrypt, CSRF, login throttling, HTML sanitizer
- **Premium UI/UX** — glassmorphism header, mega menu, gradient/ripple buttons, scroll animations, dark mode
- **Zero runtime dependencies** — precompiled Tailwind CSS, inline SVG icons, CDN-optional libraries
- **Deploy by upload** — no SSH, no npm, no composer on the server

## 🧱 Tech stack

| Layer | Technology |
|---|---|
| Language | Core PHP 8.2 (no framework) |
| Database | MySQL 8 / MariaDB 10.4+ (utf8mb4_unicode_ci) |
| Styling | Tailwind CSS 3.4 (precompiled → committed) + custom premium theme layer |
| Frontend JS | Vanilla ES6, Fetch API |
| Libraries (CDN, optional) | SweetAlert2, Chart.js, AOS, Font Awesome |
| Fonts | Plus Jakarta Sans (display), Inter (body) |
| Architecture | Flat-file pages + `includes/` engine + `api/` REST + MySQL |

## 📂 Project structure

```
eduskill/
├── index.php, about.php, programs.php … (30 public pages)
├── includes/            → engine: config, db, functions, auth, session, header, footer, navbar
│   ├── sections/        → CMS section renderers (hero, counters, campaign_list, …)
│   ├── *-premium.php     → premium redesign partials (mega-menu navbar, rich footer)
├── admin/               → admin panel (dashboard, CRUD modules, page editor, users)
│   └── includes/        → admin shell (header, sidebar, footer, auth guard)
├── api/                 → REST endpoints (contact, newsletter, campaigns, …)
├── assets/
│   ├── css/             → compiled app.css, admin.css + premium.css, premium-admin.css
│   ├── js/              → app.js, admin.js, premium.js, premium-admin.js
│   ├── images/, uploads/
├── config/              → per-install secrets (git-ignored)
├── database/            → schema.sql, seed.sql, eduskill_full.sql, migrations/
├── src-build/           → Tailwind source + config (dev-only; compiled CSS is committed)
├── storage/             → logs & cache (runtime)
└── docs/                → PRD, TRD, features, setup guide, branding, workflow
```

## 🚀 Quick start (local — XAMPP)

```bash
# 1. Place the project in your web root
#    C:\xampp\htdocs\eduskill  (or a vhost)

# 2. Create the database and import the schema + seed data
mysql -u root -e "CREATE DATABASE eduskill_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root eduskill_dev < database/eduskill_full.sql

# 3. Point your browser at the site
#    http://localhost/eduskill
```

Local DB credentials are the XAMPP defaults (`root` / no password) in `includes/config.php`.
See **[docs/SETUP-GUIDE.md](docs/SETUP-GUIDE.md)** for production (Hostinger) deployment.

### 🔑 Admin panel

```
URL:      http://localhost/eduskill/admin
Email:    prashantmixadda@gmail.com
Password: EduskillDemo2026!
```

*(Development credentials — change immediately on any real deployment.)*

## 🎨 Rebuilding the CSS

The compiled CSS is committed, so the owner never needs a build step. Developers editing the design:

```bash
cd src-build
npm install                       # first time only
npx tailwindcss -i ./src/app.css   -o ../assets/css/app.css   --minify
npx tailwindcss -i ./src/admin.css -o ../assets/css/admin.css --minify
```

## 📚 Documentation

| Document | Purpose |
|---|---|
| [PRD](docs/PRD.md) | Product requirements — vision, users, scope, roadmap |
| [TRD](docs/TRD.md) | Technical requirements — architecture, schema, API, security |
| [Features](docs/FEATURES.md) | Complete feature inventory (public + admin) |
| [Setup Guide](docs/SETUP-GUIDE.md) | Local + production installation, step by step |
| [Branding](docs/BRANDING.md) | Brand identity — colours, typography, logo, voice |
| [Workflow](docs/WORKFLOW.md) | Dev workflow + content-management workflow |

## 🗺️ Roadmap

- **Phase 1 (this repo)** — Public website + admin CMS + REST API ✅
- **Phase 2** — Email deliverability (SMTP + SPF/DKIM), two-factor auth, newsletter double opt-in
- **Phase 3** — Online donations (Razorpay), 80G receipts, donor portal
- **Phase 4+** — School / teacher / student / volunteer portals

## 📄 License

Copyright © 2026 Eduskill India Foundation. All rights reserved. See [LICENSE](LICENSE).

---

<div align="center"><sub>Built with care in India 🇮🇳</sub></div>
