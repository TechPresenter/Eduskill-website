# Technical Requirements Document (TRD)
### Eduskill India Foundation — Website & CMS Platform

**Version:** 1.0 · **Stack:** Core PHP 8.2 + MySQL/MariaDB + Tailwind CSS

---

## 1. Architecture

A **flat-file PHP** application: each public page is a real `.php` file that boots a shared engine
in `includes/`, renders its content (mostly from the database), and is served directly by Apache. A
lightweight **REST API** under `api/` serves JSON for AJAX interactions. There is **no framework and
no Composer**; the design system is **precompiled Tailwind** committed to `assets/`.

```
Browser ──▶ Apache ──▶ page.php ──▶ includes/config.php (boot)
                                   ├─ db.php        (PDO)
                                   ├─ functions.php (helpers, CSRF, settings, sanitizer)
                                   ├─ helpers.php   (palette, icons)
                                   ├─ content.php   (section renderer, campaign cards)
                                   ├─ session.php   (hardened session)
                                   └─ auth.php      (RBAC)
                          ──▶ header.php / navbar.php ──▶ page body ──▶ footer.php

Browser (fetch) ──▶ api/*.php ──▶ includes/config.php ──▶ JSON {ok, data|error, message}
```

### Request flow highlights
- **Base-path detection** — the project root is measured against the web docroot so URLs work
  unchanged under `/eduskill` locally and at a domain root in production (Windows-safe: normalise
  backslashes *before* trimming).
- **CMS rendering** — `render_sections($slug)` loads a published page's ordered `page_sections`
  rows and includes the matching allow-listed partial from `includes/sections/`.
- **Fault isolation** — each section render is wrapped in try/catch so one bad section never
  breaks the page.

## 2. Technology & versions

| Component | Version / note |
|---|---|
| PHP | 8.2 (PDO, DOMDocument, mbstring, GD) |
| Database | MySQL 8 / MariaDB 10.4+, engine **InnoDB**, charset **utf8mb4_unicode_ci** (never `_0900_`) |
| CSS | Tailwind 3.4 (+ `@tailwindcss/forms`, `typography`) → `assets/css/{app,admin}.css` |
| Premium theme | Hand-written `assets/css/premium.css` + `premium-admin.css` layered on top |
| JS | Vanilla ES6 (`app.js`, `admin.js`, `premium.js`, `premium-admin.js`) |
| CDN libs (optional) | SweetAlert2, Chart.js, AOS, Font Awesome |

## 3. Directory layout

See [README](../README.md#-project-structure). Key server-side dirs are guarded with an `.htaccess`
`Require all denied` + `index.php` 403 stub (`includes/`, `config/`, `database/`, `storage/`, `lib/`).

## 4. Database design

- **~48 tables**, all InnoDB / utf8mb4_unicode_ci, with primary keys, foreign keys, and indexes.
- **Money is stored as `BIGINT` paise** (₹1 = 100) — never floats.
- **Soft deletes** (`deleted_at`) on content tables; **auth tables are not soft-deleted** (a ghost
  row would break `UNIQUE(email)`), deactivation is a status change.
- Timestamps: `created_at` / `updated_at`; connection pinned to `time_zone = '+05:30'` and
  `STRICT_TRANS_TABLES` (MariaDB ships non-strict by default → silent truncation risk).

### Table groups
| Group | Tables |
|---|---|
| Auth / RBAC | `users`, `roles`, `permissions`, `role_permissions`, `user_roles`, `password_resets`, `remember_tokens`, `verification_tokens`, `login_attempts`, `user_activity_logs`, `audit_logs` |
| Settings / SEO | `settings`, `seo_meta`, `redirects` |
| CMS | `pages`, `page_sections`, `page_revisions`, `menus`, `menu_items`, `banners`, `widgets`, `announcements` |
| Media | `media`, `media_folders` |
| Blog | `posts`, `post_categories`, `post_tags`, `post_tag_map`, `comments` |
| Content modules | `campaigns`, `programs`, `events`, `team_members`, `testimonials`, `faqs`, `galleries`, `gallery_items`, `scholarships`, `internships`, `jobs`, `downloads`, `certificates` |
| Forms / comms | `forms`, `form_fields`, `form_submissions`, `contacts`, `newsletter_subscribers`, `email_log` |
| System | `migrations` |

Schema + seed live in `database/` (`schema.sql`, `seed.sql`, `eduskill_full.sql`, and idempotent
`migrations/*.sql` runnable via `tools/migrate.php`).

## 5. REST API

JSON envelope: `{ "ok": true, "data": … , "message": … }` or `{ "ok": false, "error": … }`.

| Method & path | Purpose |
|---|---|
| `POST /api/contact.php` | Contact form intake → `contacts` |
| `POST /api/newsletter.php` | Newsletter subscribe → `newsletter_subscribers` |
| `GET  /api/campaigns.php` | Public campaign list |
| `GET  /api/programs.php`, `events.php`, `team.php`, `testimonials.php`, `faqs.php` | Module reads |
| Admin resource endpoints | CRUD (create/update/delete) with CSRF-header auth via `eskApi()` |

## 6. Security

| Control | Implementation |
|---|---|
| Passwords | **bcrypt** (cost 12) via `password_hash`/`password_verify` |
| CSRF | Per-session synchroniser token; `csrf_field()` + `verify_csrf()` (hash_equals); rotated on login |
| RBAC | `user_can($perm)`; `super_admin` short-circuits; permission-gated admin routes |
| Login throttle | `login_attempts` keyed on **(identifier, ip)** + per-IP cap → no username-only lockout DoS |
| XSS boundary | Server-side **DOMDocument allow-list sanitiser** (`clean_html`) on save |
| Sessions | HttpOnly + SameSite=Lax always; Secure only on HTTPS; periodic id rotation |
| Spam | Hidden **honeypot** field on public forms; DB-first capture |
| Headers | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` via `.htaccess` |
| Secrets | Per-install `config/config.php` is git-ignored & excluded from update ZIPs |
| Audit | `user_activity_logs` + `audit_logs` (before/after snapshots) |

## 7. Design system

- **Colours are runtime CSS variables.** PHP (`palette_style()`) computes an OKLCh lightness ramp
  from the owner's chosen brand/accent hex and emits `--brand-*` / `--accent-*` channel triplets;
  Tailwind only ever compiles `rgb(var(--brand-500) / <alpha>)`. The DB never stores a class name.
- `--on-brand` is auto-selected (black/white) by measured WCAG contrast, so a light brand colour
  still yields readable buttons.
- **Dark mode** re-points the same semantic surface/content/edge tokens — views need no `dark:`.
- **Premium layer** (`premium.css`) adds glassmorphism, gradients, ripple, scroll-reveal and a
  mega-menu/rich-footer, by overriding the shared component classes.

## 8. Build & deploy

- **Compiled CSS is committed.** Rebuild only when the design changes (`src-build/`, see README).
  Content globs must scan `../*.php`, `../includes/**`, `../admin/**`, `../api/**` — otherwise admin
  utility classes get purged.
- **Deploy = upload.** No shell required on the host; an installer writes `config/config.php` and an
  `installed.lock`. See [SETUP-GUIDE.md](SETUP-GUIDE.md).

## 9. Coding conventions

- Every include starts with `defined('ESK') || exit('No direct access.');`
- Output escaping with `e()` on **every** dynamic value; prepared statements for **all** SQL.
- `LIMIT`/`OFFSET` are cast to `(int)` and interpolated (MariaDB rejects string-bound LIMIT when
  emulation is off).
- Helpers: `e() url() asset() redirect() setting() csrf_field() inr() db_all/db_one/db_val`.
