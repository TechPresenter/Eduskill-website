# Deploying to Hostinger Business (shared) via GitHub

Repo: `https://github.com/TechPresenter/Eduskill-india-Foundation` (branch `main`)
Plan: **Hostinger Business shared hosting** — hPanel, LiteSpeed, free SSL, SSH, Git deploy.

This is the Git-based companion to `DEPLOY_HOSTINGER.md`. The manual-upload guide's
Steps 1–3 and 9–14 still apply verbatim; this file replaces the *file transfer* and
*configuration* parts with the Git workflow.

---

## Why this repo is already correct for Git deploy

Verified against the actual repo state:

| Check | Status |
|---|---|
| `config.php` tracked? | ❌ **No** — gitignored. Your DB password and `APP_KEY` are **not** on GitHub. ✅ |
| `config.example.php` shipped? | ✅ Yes, with empty `APP_KEY` and `CHANGE ME` markers |
| `database/*.sql` tracked? | ✅ One file: `database/eduskill.sql` |
| `uploads/` tree clones? | ✅ 24 `.gitkeep` files recreate every subfolder |
| Per-folder `.htaccess` guards clone? | ✅ `uploads/`, `includes/`, `database/`, `logs/`, `storage/` |
| `.git/` blocked from web? | ✅ **Fixed in commit `d5151b1`** (see note below) |

> **The `.git/` exposure fix.** Hostinger's Git feature clones *into your webroot*, so
> `.git/` ends up publicly reachable. The pre-existing dotfile guard
> `<FilesMatch "^\.">` did **not** cover it — `.git/config` and `.git/HEAD` have
> basenames with no leading dot, so only the directory entry was hidden and
> `https://yourdomain.org/.git/config` would have disclosed the repo URL. A
> `RewriteRule (^|/)\.(git|github)(/|$) - [F,L]` now denies it at any depth.
> **Push this commit before deploying.**

---

## ⚠️ Your repo is PUBLIC — read this first

An unauthenticated GitHub API request returns `200`, so anyone can read the repo.
That is a valid choice, but it has one hard consequence:

**`database/eduskill.sql` ships the seed admin login, and this repo is public:**
`admin@eduskillindia.org` / **`Admin@123`**

Anyone who finds your live site can try it. So:

1. **Change the admin password the moment the DB is imported** (Step 7 below) —
   before you announce or link the site anywhere.
2. Consider changing the admin **email** too, so the public seed value isn't a valid username.
3. Enable **2FA** on the admin account (Admin → Security).

If you'd rather not publish the source, make the repo **private** — the deploy flow
below covers both cases (private just needs a deploy key, Step 3b).

---

## Step 1 — Push the pending commit

The `.git` protection fix is committed locally but not yet on GitHub:

```bash
cd /c/xampp/htdocs/pwf
git push origin main
```

Confirm `https://github.com/TechPresenter/Eduskill-india-Foundation` shows the
commit **"Block .git/ from web access for Git-based deployment"**.

---

## Step 2 — Prepare the host (same as the manual guide)

Do these in hPanel first — they're unchanged from `DEPLOY_HOSTINGER.md`:

- **Step 1** — attach the domain; note the docroot
  `/home/uXXXXXXXX/domains/yourdomain.org/public_html`
- **Step 2** — set **PHP 8.2 or 8.3**; set `display_errors = Off` in PHP Options
- **Step 3** — create the MySQL **database + user** (prefixed, e.g. `u1234567_pwf` /
  `u1234567_admin`) and save the password

---

## Step 3 — Connect the repo in hPanel

hPanel → **Websites → Manage → Advanced → GIT**.

### 3a. Public repo (your current setup — simplest)

- **Repository address:** `https://github.com/TechPresenter/Eduskill-india-Foundation.git`
- **Branch:** `main`
- **Directory:** leave **blank** (deploys into `public_html` root)
- Click **Create**.

### 3b. If you make the repo private

Hostinger shows an **SSH deploy key** on the GIT page. Copy it, then in GitHub go to
**Repo → Settings → Deploy keys → Add deploy key**, paste it, leave *Allow write
access* **unchecked**, save. Back in hPanel use the **SSH** URL instead:
`git@github.com:TechPresenter/Eduskill-india-Foundation.git`

> **Directory must be empty.** If `public_html` already has files (Hostinger's default
> `index.html` / `default.php`), delete them in File Manager first or the clone fails.

---

## Step 4 — Deploy

On the GIT page, click **Deploy**. Hostinger clones the repo into `public_html`.

Verify in **File Manager** that `index.php`, `admin/`, `includes/`, `assets/`,
`database/` sit **directly** in `public_html` — not nested in a subfolder.

**Auto-deploy (optional but recommended):** the GIT page shows a **webhook URL**.
Copy it → GitHub **Repo → Settings → Webhooks → Add webhook** → paste as *Payload
URL*, content type `application/json`, event: *Just the push event*. Now every
`git push origin main` redeploys automatically.

---

## Step 5 — Create `config.php` on the server 🔑

**This is the step Git can't do for you** — `config.php` is gitignored (correctly), so
it is **not** in the clone. You create it once, by hand, on the server.

File Manager → `public_html` → copy `config.example.php` → rename the copy to
`config.php` → **Edit**, and set:

```php
define('APP_ENV', 'production');                    // line ~34

define('DB_HOST',    'localhost');                  // line ~69
define('DB_NAME',    'u1234567_pwf');               // your Hostinger DB
define('DB_USER',    'u1234567_admin');             // your Hostinger user
define('DB_PASS',    'your-strong-db-password');

define('BASE_URI', '');                             // line ~85 — empty at domain root
define('APP_URL',  'https://yourdomain.org');       // line ~86 — https, NO trailing slash

define('APP_KEY',  'base64:PASTE_A_NEW_32_BYTE_KEY');  // line ~134 — see below
define('BACKUP_MYSQLDUMP', '');                     // line ~149 — leave empty on shared
```

### Generating `APP_KEY` (AES-256 encryption-at-rest key)

`config.example.php` ships it **empty**, and empty means encryption fails closed. Over SSH:

```bash
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Paste the output as the `APP_KEY` value.

> **Two rules:** (1) Generate a **fresh** key for production — never reuse the local
> one, and never commit it. (2) **Never rotate it after go-live** — changing it makes
> all previously encrypted data unreadable.
>
> **Migrating existing local data?** If your local DB already holds encrypted values
> and you're importing that data, you must copy the **local** `APP_KEY` from your
> XAMPP `config.php` instead of generating a new one, or those values won't decrypt.

**Because `config.php` is untracked, redeploys never overwrite it.** Set it once; it
survives every future `git push`. Same for `uploads/` and `logs/` (gitignored).

---

## Step 6 — `.htaccess` production edits

The repo ships the XAMPP `/pwf/` values, so edit these on the server (File Manager →
`public_html/.htaccess`):

```apache
# line ~41
RewriteBase /pwf/        →   RewriteBase /

# lines ~97-98
ErrorDocument 404 /pwf/404.php   →   ErrorDocument 404 /404.php
ErrorDocument 403 /pwf/404.php   →   ErrorDocument 403 /404.php

# lines ~55-56 — uncomment AFTER SSL is active (Step 9)
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

Also `robots.txt`: `Sitemap: http://localhost/pwf/sitemap.xml` →
`https://yourdomain.org/sitemap.xml`.

> ⚠️ **These are tracked files** — a future `git push` from your local machine will
> overwrite them with the `/pwf/` values again. See **Keeping prod edits safe** below.

---

## Step 7 — Import the database

Identical to `DEPLOY_HOSTINGER.md` **Step 6**, and the same trap applies: every `.sql`
file hardcodes `` USE `pwf` ``, which your prefixed DB user cannot execute.

**Fastest path (SSH — Business plan includes it):**

```bash
cd ~/domains/yourdomain.org/public_html
DB=u1234567_pwf; U=u1234567_admin

# strip the CREATE DATABASE / USE lines, then import in order
sed -i '/CREATE DATABASE/d; /^USE /d' database/*.sql

mysql -h localhost -u $U -p $DB < database/eduskill.sql
```

> The `sed` edits tracked files — see **Keeping prod edits safe**. Alternatively export
> your local `pwf` DB from phpMyAdmin *without* "Add CREATE DATABASE / USE", and import
> that single file into `u1234567_pwf` — it carries your real content and avoids the
> problem entirely.

**Then immediately** (public-repo urgency, per the warning above):

1. Visit `https://yourdomain.org/admin/login`
2. Sign in `admin@eduskillindia.org` / `Admin@123`
3. **Change the password and email right away**, and enable 2FA.

---

## Step 8 — Permissions, SSL, services, cron

Unchanged from `DEPLOY_HOSTINGER.md`:

- **Step 8** — `uploads/` + `logs/` writable at **755** (never 777)
- **Step 9** — Let's Encrypt SSL + Force HTTPS, then uncomment the redirect
- **Step 11** — SMTP, live payment keys, `api_cors_origin`, `rbac_enforce=1`
- **Step 12** — the two cron jobs (CLI form)
- **Step 13** — SPF / DKIM / DMARC
- **Step 14** — the security checklist

---

## Keeping prod edits safe across redeploys

Steps 6 and 7 modify **tracked** files (`.htaccess`, `robots.txt`, `database/*.sql`).
A redeploy resets them. Three ways to handle it, best first:

### Option A — Make the repo deploy-ready (recommended)

Change the values **locally**, commit, and push, so the repo *is* the production
config. Since `BASE_URI`/`APP_URL` live in the untracked `config.php`, the only
tracked prod-specific values are the three `.htaccess` lines and `robots.txt`:

```bash
# locally, one time
# .htaccess: RewriteBase / ; ErrorDocument /404.php ; uncomment HTTPS redirect
# robots.txt: live Sitemap URL
git commit -am "Production: domain-root rewrite base, HTTPS redirect, live sitemap"
git push origin main
```

Your XAMPP install then needs the `/pwf/` values locally — keep them in a
`.htaccess.local` you swap in, or simply accept that local runs at the domain-root
config and use `http://localhost/` via a vhost.

### Option B — Deploy from a `production` branch

Keep `main` for development. Create a `production` branch carrying the prod
`.htaccess`/`robots.txt`, point Hostinger's GIT at **`production`**, and merge `main`
→ `production` for each release. Cleanest separation, one extra merge per deploy.

### Option C — Re-apply after each deploy

Just redo Step 6 in File Manager after every deploy. Fine for rare deploys; error-prone
otherwise. (The `database/*.sql` edits only matter for the *first* import, so those
don't recur.)

---

## Redeploy workflow (after the initial setup)

```bash
# local
git add -A && git commit -m "your change" && git push origin main
```

Then either the webhook fires automatically, or click **Deploy** in hPanel.

**Preserved across redeploys** (untracked/gitignored): `config.php`, `uploads/`,
`logs/`, `storage/`.
**Overwritten** (tracked): all PHP/CSS/JS, `.htaccess`, `robots.txt`, `database/*.sql`.

Bump `ASSET_VERSION` in `config.php` when you change CSS/JS so browsers don't serve
stale assets. *(Note: it's in the untracked `config.php`, so bump it on the server.)*

---

## Git-specific troubleshooting

| Symptom | Fix |
|---|---|
| Clone fails: "directory not empty" | Delete Hostinger's default `index.html`/`default.php` from `public_html` first |
| `Permission denied (publickey)` | Private repo without a deploy key — do Step 3b, and use the `git@github.com:` SSH URL |
| Site loads but 500 error | `config.php` missing — it's gitignored and must be created by hand (Step 5) |
| "Encryption disabled" in Security Center | `APP_KEY` still empty (`config.example.php` ships it blank) — generate one (Step 5) |
| Deploy succeeded but site unchanged | Wrong branch in hPanel, or browser cache — hard-refresh and bump `ASSET_VERSION` |
| `/.git/config` reachable in a browser | The fix commit wasn't pushed/deployed — verify commit `d5151b1` is live |
| Prod `.htaccess` reverted to `/pwf/` | Expected — a redeploy overwrote it. Adopt Option A or B above |
| Files land in `public_html/Eduskill-india-Foundation/` | The **Directory** field wasn't blank in hPanel; clear it and redeploy |

---
*Companion to `DEPLOY_HOSTINGER.md` · verified against repo state 2026-08-08.*
