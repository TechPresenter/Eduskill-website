# Setup & Deployment Guide — Eduskill India Foundation

Two paths: **local development (XAMPP)** and **production (shared hosting / Hostinger)**.

---

## A. Local development (XAMPP / WAMP / MAMP)

### Prerequisites
- PHP **8.2+** (with `pdo_mysql`, `mbstring`, `gd`, `dom`)
- MySQL 8 or MariaDB 10.4+
- Apache with `mod_rewrite` enabled

### Steps

1. **Get the code into your web root**
   ```
   C:\xampp\htdocs\eduskill
   ```

2. **Create the database & import data**
   ```bash
   mysql -u root -e "CREATE DATABASE eduskill_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root eduskill_dev < database/eduskill_full.sql
   ```
   *(Or use phpMyAdmin: create `eduskill_dev`, then Import → `database/eduskill_full.sql`.)*

3. **Check the DB credentials** in `includes/config.php` — the defaults are XAMPP's
   (`127.0.0.1` / `root` / empty password / `eduskill_dev`). Adjust if yours differ.

4. **Open the site**
   ```
   http://localhost/eduskill
   ```

5. **Log in to the admin**
   ```
   http://localhost/eduskill/admin
   Email:    prashantmixadda@gmail.com
   Password: EduskillDemo2026!
   ```
   > ⚠️ Change this password before any real deployment.

### Troubleshooting
| Symptom | Fix |
|---|---|
| Blank page / 500 | Check `storage/logs/php-error.log`; ensure PHP 8.2 & extensions |
| “Database connection failed” | Verify DB name/user/pass in `includes/config.php`; is MySQL running? |
| Broken styling | The compiled CSS is committed — hard-refresh (Ctrl-F5). To rebuild, see [WORKFLOW.md](WORKFLOW.md) |
| 404 on clean URLs | Enable `mod_rewrite`; confirm `.htaccess` is allowed (`AllowOverride All`) |
| Icons missing | Font Awesome loads from CDN — check internet, or vendor it locally |

---

## B. Production deployment (Hostinger / cPanel shared hosting)

No SSH, npm, or Composer required — **deploy by upload**.

### Steps

1. **Create the database** in your hosting panel (MySQL Databases). Note the **full DB name**
   (hosts usually prefix it, e.g. `u123456789_eduskill`), the DB user and password.
   > 🔑 The #1 deployment mistake is forgetting the host's DB-name/user prefix.

2. **Import the schema + seed** via phpMyAdmin → Import → `database/eduskill_full.sql`
   (or `schema.sql` then `seed.sql`). If your host uses a strict SQL mode, the dumps are
   MariaDB-safe (utf8mb4_unicode_ci).

3. **Upload the files** into `public_html` (or a subfolder). You can ZIP the project, upload via the
   File Manager, and Extract. Exclude `src-build/node_modules/` and `storage/logs/*`.

4. **Set your credentials.** Edit `includes/config.php` (or `config/config.php` if using the
   installer flow) with your production DB host/name/user/password, and set:
   - `env` → `production` (hides errors, logs them instead)
   - `app_url` → your domain (optional; auto-detected otherwise)
   - a fresh random `app_key`

5. **Point your domain** at `public_html` and enable **HTTPS** (free Let's Encrypt on most hosts).
   The app auto-detects HTTPS and secures cookies accordingly.

6. **Verify**
   - Home page loads and is styled.
   - `/admin` login works; change the admin password immediately (Admin → Profile).
   - Submit the contact form → check it lands in the admin inbox.
   - Set your brand colour, contact details and social links in **Admin → Settings**.

### Security checklist (production)
- [ ] Admin password changed from the dev default
- [ ] `env = production`, `debug = false`
- [ ] Fresh `app_key`
- [ ] HTTPS enabled & forced
- [ ] `config/`, `includes/`, `database/`, `storage/` return **403** (guards ship in the repo)
- [ ] Search-engine indexing enabled in Settings when you're ready to go live

### Break-glass recovery
Locked out? `recover.php` resets one super-admin's password — but only after you create a file named
`config/RECOVERY_ENABLED` via the File Manager (proving filesystem control). It self-disarms after use.
