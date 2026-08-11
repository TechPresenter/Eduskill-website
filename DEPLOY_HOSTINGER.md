# Deploying EDUSKILL INDIA FOUNDATION to Hostinger

A step-by-step guide to move this app from local XAMPP (`http://localhost/pwf/`)
to a live Hostinger domain. Grounded in this codebase's actual configuration.

> **The good news:** URL/path generation is centralized (`url()`, `asset()`,
> `abs_url()`, `upload_url()` all derive from two constants). The entire
> **code** change is **2 lines in `config.php`, 3 lines in `.htaccess`, 1 line
> in `robots.txt`**. Everything else is hosting configuration.
>
> **The two things that will trip you up:**
> 1. Every `.sql` file hardcodes the database name `pwf` — Hostinger names your
>    DB something like `u1234567_pwf`, so a raw import fails. See **Step 6**.
> 2. Hostinger runs **LiteSpeed + LSAPI PHP** (not Apache+mod_php). `.htaccess`
>    is honored, but `mysqldump`-based backups and server-level `TraceEnable`
>    are not available. See **Step 12** and the security notes.

Hostinger plan assumed: **Premium / Business shared hosting** (hPanel, LiteSpeed,
free Let's Encrypt SSL, MySQL/MariaDB, cron, SSH on most plans).

---

## Prerequisites

- A Hostinger hosting plan with hPanel access.
- Your domain added to the account (or a subdomain — see the last section).
- The `pwf` project folder from XAMPP (this whole tree).
- Your local database exported, **or** the `database/*.sql` files (both paths covered in Step 6).

---

## Step 1 — Point the domain and pick the document root

1. hPanel → **Websites → Add Website** (or **Manage** an existing one). Attach your domain.
2. Note the **document root**. For a primary domain it is
   `/home/uXXXXXXXX/domains/yourdomain.org/public_html`. **The app must live at the
   root of `public_html`**, served at `https://yourdomain.org/` — *not* in a
   `public_html/pwf/` subfolder (that's what the `/pwf/` cleanup below removes).

---

## Step 2 — Set PHP 8.2 (or 8.3)

hPanel → **Advanced → PHP Configuration**:

- **PHP version:** select **8.2** or **8.3**. Do **not** go below 8.2 (every file
  uses `declare(strict_types=1)` and PHP 8.2 syntax).
- All required extensions are **on by default** at Hostinger, but confirm on the
  **PHP Extensions** tab: `pdo_mysql`, `mbstring`, `curl`, `openssl`, `gd`
  (with WebP), `fileinfo`, `dom`/`xml`, `zip`, `json`, `zlib`.
- On **PHP Options**, set **`display_errors = Off`** (belt-and-suspenders; the app
  also forces this once `APP_ENV=production`).

---

## Step 3 — Create the MySQL database and user

hPanel → **Databases → MySQL Databases**:

1. Create a database — Hostinger prefixes it, e.g. **`u1234567_pwf`**.
2. Create a database user — also prefixed, e.g. **`u1234567_admin`** — with a
   **strong password**. Save these three values.
3. Grant the user **All Privileges** on that database.
4. The connection host stays **`localhost`**.

> The user can only touch this one prefixed database. It **cannot** `CREATE
> DATABASE` or `USE` a database literally named `pwf` — which is exactly why the
> SQL files need the tweak in Step 6.

---

## Step 4 — Edit the code (the only source changes)

### 4a. `config.php`

Make these edits (line numbers are approximate — search by constant name):

| Line | Constant | From | To |
|------|----------|------|-----|
| 26 | `APP_ENV` | `'development'` | `'production'` |
| 65 | `DB_NAME` | `'pwf'` | `'u1234567_pwf'` (your DB) |
| 66 | `DB_USER` | `'root'` | `'u1234567_admin'` (your user) |
| 67 | `DB_PASS` | `''` | `'your-strong-db-password'` |
| 79 | `BASE_URI` | `'/pwf'` | `''` (empty — domain root) |
| 80 | `APP_URL` | `'http://localhost/pwf'` | `'https://yourdomain.org'` (https, **no** trailing slash) |
| 135 | `BACKUP_MYSQLDUMP` | `'C:/xampp/mysql/bin/mysqldump.exe'` | `''` (auto-detect; see Step 12) |

Leave these **unchanged**:

- `DB_HOST` = `'localhost'`, `DB_PORT` = `'3306'`, `DB_CHARSET` = `'utf8mb4'`.
- `BASE_PATH`, `UPLOAD_PATH`, `UPLOAD_URI`, `ASSET_URI` — all **derived** from
  the constants above; they self-correct once `BASE_URI`/`APP_URL` are right.
- **`APP_KEY`** — this is your AES-256 encryption-at-rest key. **Do not change or
  blank it.** Rotating it makes any already-encrypted data unreadable. Just make
  sure it keeps its existing `base64:...` value. (Only generate a new one if you
  are certain no encrypted data exists yet and you want a fresh key — then keep it
  secret and never rotate again.)
- `ASSET_VERSION` — optional: bump it (e.g. `5.3.3` → `5.3.4`) on each deploy so
  browsers/CDN don't serve stale CSS/JS.

> **Secrets note:** live SMTP and payment-gateway keys are **not** in `config.php`
> — they live in the `settings` DB table and are entered via the admin panel
> (Step 11). The `SMTP_*` constants are just empty fallbacks.

### 4b. `.htaccess` (root)

Three edits for a domain-root install:

```apache
# 1) Line ~41 — RewriteBase
#    FROM:
    RewriteBase /pwf/
#    TO:
    RewriteBase /

# 2) Lines ~97-98 — ErrorDocument (these are absolute, they do NOT follow RewriteBase)
#    FROM:
ErrorDocument 404 /pwf/404.php
ErrorDocument 403 /pwf/404.php
#    TO:
ErrorDocument 404 /404.php
ErrorDocument 403 /404.php

# 3) Lines ~55-56 — uncomment the HTTPS redirect AFTER SSL is active (Step 9)
#    FROM:
    # RewriteCond %{HTTPS} off
    # RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
#    TO:
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Everything else in `.htaccess` is path-agnostic and needs no change (the
extensionless-URL rewrite self-corrects via `%{THE_REQUEST}`, HSTS auto-activates
over HTTPS, the security headers and folder blocks are portable). `uploads/.htaccess`
and the other per-folder deny files transfer verbatim — no edits.

### 4c. `robots.txt`

```
# FROM:
Sitemap: http://localhost/pwf/sitemap.xml
# TO:
Sitemap: https://yourdomain.org/sitemap.xml
```

That is the **complete** set of code edits.

---

## Step 5 — Upload the files

Pick one:

- **File Manager (simplest):** zip the project locally, hPanel → **Files → File
  Manager**, upload the zip into `public_html`, extract, and move the contents so
  that `index.php`, `config.php`, `admin/`, `includes/`, etc. sit **directly** in
  `public_html` (not in `public_html/pwf/`).
- **FTP:** create an FTP account in hPanel → **Files → FTP Accounts**, connect with
  FileZilla, upload the tree into `public_html`.
- **Git (if enabled):** hPanel → **Advanced → Git**, deploy from your repo.

**Do not upload** these local-only folders: `scratchpad/`, `storage/rebrand-backup/`
(stale config copies), and `.git/` if present. Uploading `SECURITY_VAPT_REPORT.md`
and this file is harmless (`.md` is blocked from the web by `.htaccess`).

---

## Step 6 — Import the database

This used to be the gotcha: the old per-version SQL files each began with
`USE \`pwf\`;` and the base file ran `CREATE DATABASE IF NOT EXISTS \`pwf\``.
Your Hostinger database is not named `pwf` and your DB user cannot create one,
so an as-is import failed with *"Unknown database 'pwf'"* / *"Access denied"*.

**That is fixed.** `database/eduskill.sql` contains no `CREATE DATABASE` and no
`USE`, so it imports into whatever database you have selected, whatever it is
called. The one thing you must still do is **click into your database in the
left sidebar first** — otherwise phpMyAdmin has nothing selected and the import
fails with *"No database selected"*.

Two ways to populate it.

### Option A — Migrate your existing local data (recommended)

1. In **local** XAMPP phpMyAdmin, select the `pwf` database → **Export** →
   *Custom* → SQL, **structure + data**, and under "Object creation options"
   **untick** "Add CREATE DATABASE / USE statement". Save the single `.sql` file.
2. In **Hostinger** phpMyAdmin (hPanel → Databases → phpMyAdmin), click into your
   `u1234567_pwf` database → **Import** → choose that file → Go.
3. This carries all your content, settings, and the admin account in one shot and
   sidesteps the per-file `USE` problem. Done — skip to Step 7.

### Option B — Import the database fresh

There is **one** SQL file: `database/eduskill.sql`. It has no `CREATE DATABASE`
and no `USE`, so there is nothing to strip and no database name to rewrite — it
applies to whichever database you have selected.

1. phpMyAdmin → **click into** your database in the left sidebar.
2. **Import** tab → `database/eduskill.sql` → **Go**.
3. A complete import is **130 tables** (InnoDB, utf8mb4).

The file has two sections. On an empty database run the whole thing — Section 2
is idempotent and applies cleanly on top. On a database that already holds real
data, run **Section 2 only**; Section 1 begins every table with
`DROP TABLE IF EXISTS`.

> **SSH alternative** (hPanel → Advanced → SSH Access), from the project dir:
> ```bash
> DB=u1234567_pwf; U=u1234567_admin
> mysql -h localhost -u $U -p $DB < database/eduskill.sql
> ```

**Default admin login:** `/admin/login` · `admin@eduskillindia.org` ·
**`Admin@123`**. The shipped file sets `must_change_password = 1`, so the site
sends you to a password form before any other admin page will load. That is
deliberate: this password is published in the public repo.

---

## Step 7 — (removed)

The two one-time PHP seeders (`seed_v6.php`, `seed_email.php`) no longer exist.
Everything they produced — the membership and Email Center templates, the default
signature and mail account — is already inside `database/eduskill.sql`, so
importing that file is all you need.

One thing they used to generate is deliberately NOT seeded: the membership cron
token ships **empty**, so no two installations share a trigger token. Generate
yours in **Admin → Membership Settings**.


## Step 8 — Set writable permissions

The app writes to uploads and logs. In File Manager (or via SSH `chmod`):

- `uploads/` and every subfolder → **755** (dirs) / **644** (files)
- `logs/` and `logs/throttle/` → **755**
- Everything else (`includes/`, `config.php`, `database/`, `admin/`) stays **755/644**, read-only.

**Do not use 777.** Hostinger runs PHP as your own account user, so 755/644 is
correct and sufficient. Missing upload subfolders and `logs/throttle/` are
auto-created by the app, so the important thing is that the parent `uploads/` and
`logs/` are writable.

---

## Step 9 — Enable SSL and force HTTPS

1. hPanel → **Security → SSL** → select the domain → install the free
   **Let's Encrypt** certificate → wait for **Active**.
2. Enable the **Force HTTPS** toggle in the same panel.
3. Also uncomment the `.htaccess` HTTPS redirect (Step 4b, edit 3) as the app's own
   belt-and-suspenders.
4. Verify: `http://yourdomain.org` 301-redirects to `https://`, and the response
   now includes the `Strict-Transport-Security` (HSTS) header (it auto-activates
   over TLS).

> This is the single highest-impact step — it activates the app's already-shipped
> `Secure` cookies and HSTS, which are inert until TLS is live (per the VAPT report).

---

## Step 10 — First login and lockdown

1. Go to `https://yourdomain.org/admin/login`.
2. Sign in with `admin@eduskillindia.org` / `Admin@123`.
3. **Immediately change the admin email and password** (Admin → Profile). Consider
   enabling 2FA (Admin → Security).

---

## Step 11 — Configure services via the admin panel

These live in the DB `settings` table (not files), so set them in-app:

- **SMTP (email):** Admin → Settings → email/SMTP. Enter your Hostinger/Titan
  mailbox host (`smtp.hostinger.com`), port `587` (TLS), the full mailbox address
  as the username, and its password. Send a test. (Shared-host `mail()` is often
  spam-filtered, so real SMTP is strongly recommended.)
- **Payment gateways:** Admin → Payment Settings / Membership Settings. Switch each
  gateway you use from **sandbox/test to production/live** and enter the **live**
  keys (Cashfree `cashfree_env=production`, Razorpay live keys, Stripe live +
  webhook signing secret, PayPal `paypal_env=live`). The gateway **webhook URLs**
  derive from `APP_URL`, so that must be correct first (Step 4a). Register each
  webhook in the gateway's dashboard pointing at
  `https://yourdomain.org/api/v1/<gateway>-webhook`.
- **API CORS (security):** there's no admin UI for this key — set it in phpMyAdmin
  on the `settings` table so the public API stops advertising `Access-Control-Allow-Origin: *`:
  ```sql
  INSERT INTO settings (key_name, value, group_name, type)
  VALUES ('api_cors_origin', 'https://yourdomain.org', 'api', 'text')
  ON DUPLICATE KEY UPDATE value = 'https://yourdomain.org';
  ```
- **RBAC enforcement (security):** Admin → Roles → enable enforcement
  (`rbac_enforce=1`), then assign each admin the narrowest role. Verify with a
  *non-super-admin* test account that forbidden modules are actually blocked.

---

## Step 12 — Cron jobs

Two scheduled scripts. Register them in hPanel → **Advanced → Cron Jobs** using the
**CLI (command) form** — CLI runs skip the token and avoid HTTP timeouts:

```
# Membership expiry reminders — daily 06:00   (schedule: 0 6 * * *)
php /home/uXXXXXXXX/domains/yourdomain.org/public_html/cron/membership-reminders.php

# School-fee reminders — weekly Mon 07:00      (schedule: 0 7 * * 1)
php /home/uXXXXXXXX/domains/yourdomain.org/public_html/cron/fee-reminders.php
```

(If you must trigger over HTTP instead, set `membership_cron_token` in
Admin → Membership Settings and use
`wget -qO- "https://yourdomain.org/cron/membership-reminders.php?token=THE_TOKEN"`.)

**Database backups:** the in-app "backup" button shells out to `mysqldump`, which is
typically **disabled on shared hosting** (`exec()` in `disable_functions`, no
`mysqldump` on PATH). It fails gracefully (the panel just says "unavailable"). Use
**hPanel → Files → Backups** (Hostinger's own automatic backups) instead, and
**download + test-restore one** before go-live.

---

## Step 13 — Email deliverability (SPF / DKIM / DMARC)

hPanel → **Emails** (create the mailbox), then **Advanced → DNS Zone Editor** add TXT records:

- **SPF:** `v=spf1 include:_spf.mail.hostinger.com ~all` (or your external relay's SPF host).
- **DKIM:** enable DKIM for the mailbox in the Email panel and publish the CNAME/TXT it generates.
- **DMARC:** at `_dmarc.yourdomain.org` → `v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.org; pct=100`.

Validate with a mail-tester before launch, so receipts/OTP/verification emails don't land in spam.

---

## Step 14 — Post-deploy security checklist

From `SECURITY_VAPT_REPORT.md` §15 — the deploy-time items:

- [x] `APP_ENV=production` (Step 4a) — errors logged, not shown
- [ ] SSL + Force HTTPS active (Step 9)
- [ ] `api_cors_origin` set to your origin (Step 11)
- [ ] `rbac_enforce=1` + least-privilege roles (Step 11)
- [ ] `display_errors=Off` at the PHP layer too (Step 2)
- [ ] Admin password changed; dev credentials rotated (Step 10); live gateway/SMTP keys in (Step 11)
- [ ] hPanel backups enabled + one restore tested (Step 12)
- [ ] SPF/DKIM/DMARC configured (Step 13)
- [ ] *(hygiene)* upgrade CDN `xlsx` 0.18.5 → ≥ 0.20.2 and add SRI hashes to CDN `<script>` tags

**Not achievable on shared hosting (accepted residual, Low):**

- **`TraceEnable off`** is a server-level directive you can't set on shared hosting.
  The `.htaccess` already blocks `TRACK`/`DEBUG`, and once HTTPS + HttpOnly cookies
  are on, TRACE/XST is effectively non-exploitable. If you want it gone entirely,
  front the site with **Cloudflare (free)**, which rejects TRACE at the edge, or ask
  Hostinger support to confirm their managed server already disables it.
- **Nonce/hash CSP** (removing `script-src 'unsafe-inline'`) is a code refactor, not
  a deploy toggle — defer to post-launch; the DOM sanitizer + output escaping cover
  the interim.

---

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Import error *"Unknown database 'pwf'"* | The `USE \`pwf\``/`CREATE DATABASE` lines — do **Step 6** (Option A export without CREATE/USE, or strip those lines). |
| All links/assets 404 with `/pwf/...` | `BASE_URI` still `'/pwf'` — set it to `''` (Step 4a). |
| Emails/receipts/webhooks point at localhost | `APP_URL` still localhost — set the https origin (Step 4a). |
| Blank white page / HTTP 500 | Check `logs/php-error.log`. Usually DB creds wrong (Step 3/4a) or `logs/` not writable (Step 8). |
| Clean URLs 404 (e.g. `/about`) | `RewriteBase` not `/` (Step 4b), or mod_rewrite/`.htaccess` not applied. |
| Uploads fail | `uploads/` not writable (Step 8), or `fileinfo`/`gd` extension off (Step 2). |
| "Encryption disabled" in Security Center | `APP_KEY` was blanked — restore its `base64:...` value (Step 4a). |
| Rich text renders as escaped tags | `dom`/`xml` extension off — enable it (Step 2). |
| Payment webhook returns 401 | Gateway's webhook signing secret not set in Admin → Payment Settings, or webhook URL wrong (must be `https://yourdomain.org/api/v1/<gw>-webhook`). |

---

## Subdomain or subdirectory deployments

- **Subdomain** (`https://app.yourdomain.org/`): a subdomain has its own document
  root, so from the URL's perspective it's a **root install — identical to the
  domain-root steps above**. Set `BASE_URI=''` and `APP_URL='https://app.yourdomain.org'`,
  `RewriteBase /`, `ErrorDocument /404.php`.
- **Subdirectory** (`https://yourdomain.org/app/`, files in `public_html/app/`):
  keep a non-empty base. Set `BASE_URI='/app'`, `APP_URL='https://yourdomain.org/app'`,
  `RewriteBase /app/`, `ErrorDocument 404 /app/404.php`. (If the folder is literally
  named `pwf`, the shipped `.htaccess` values are already correct — only uncomment
  the HTTPS redirect.)

---
*Generated 2026-08-08 · deployment audit of the actual codebase (config, .htaccess, paths, DB, runtime, security).*
