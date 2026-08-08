# Installation & Deployment Guide

## A. Local (XAMPP / LAMP)

1. **Copy** the project to your web root (`C:\xampp\htdocs\pwf` on XAMPP).
2. **Start** Apache + MySQL (XAMPP Control Panel). Ensure `mod_rewrite`,
   `mod_headers`, `mod_deflate`, `mod_expires` are enabled (default on XAMPP).
3. **Create + import the database.** The schema is built up as a numbered
   migration chain and **every file must be applied, in order** — `schema.sql`
   alone is only the original core and leaves ~100 later tables missing.

   Order: `schema.sql` → `schema_v2.sql` … `schema_v24.sql` → `sample_data.sql`,
   then the two PHP seeders. Every file is idempotent, so re-running is safe.

   Via shell (this loops the whole chain in the correct numeric order):
   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   for /L %i in (2,1,24) do C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v%i.sql
   C:\xampp\mysql\bin\mysql.exe -u root pwf < database\sample_data.sql
   C:\xampp\php\php.exe database\seed_v6.php
   C:\xampp\php\php.exe database\seed_email.php
   ```
   (In PowerShell use `2..24 | % { cmd /c "C:\xampp\mysql\bin\mysql.exe -u root pwf < database\schema_v$_.sql" }`.)

   In phpMyAdmin, import the same files in the same order — `schema.sql` first,
   then `schema_v2` through `schema_v24` in ascending numeric order (note that
   `schema_v10` comes after `schema_v9`, not after `schema_v1`), then
   `sample_data.sql`.

   **Verify** before moving on — you should get 130 tables:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='pwf';"
   ```
4. **Configure** `config.php` — for local XAMPP the defaults already work
   (`DB_USER=root`, empty password, `BASE_URI=/pwf`).
5. Open **http://localhost/pwf/** and sign in to the admin at `/admin/login`.

## B. Shared hosting (cPanel etc.)

1. **Upload** all files to `public_html/` (or a subfolder). Keep the folder
   structure intact.
2. **Create a MySQL database + user** in cPanel and grant all privileges.
3. **Import** the full migration chain (phpMyAdmin → Import), in the same order
   as step A.3 — `schema.sql`, then `schema_v2` … `schema_v24` in ascending
   numeric order, then `sample_data.sql`. Skipping any file leaves tables
   missing and pages will fatal on first use. Omit `sample_data.sql` if you do
   not want the demo content. Then run the two PHP seeders once (from SSH, or by
   temporarily browsing to them and deleting them afterwards).
4. **Edit `config.php`:**
   ```php
   define('APP_ENV', 'production');           // hide errors, log them
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourdb');
   define('DB_USER', 'youruser');
   define('DB_PASS', 'yourpassword');
   define('BASE_URI', '');                      // '' if at the domain root, else '/subfolder'
   define('APP_URL',  'https://yourdomain.org'); // no trailing slash
   ```
5. **Update `.htaccess`** — set `RewriteBase /` if installed at the domain root
   (it ships as `/pwf/`). Uncomment the HTTPS redirect block once SSL is active.
6. **Folder permissions** — make these writable by the web server (755 dirs / 644 files;
   use 775 if your host runs PHP as a different user):
   ```
   uploads/  (and all subfolders)   → writable (755/775)
   logs/                            → writable (755/775)
   ```
   Everything else can stay read-only (644 / 755).
7. **First login** → `/admin/login` with the seeded credentials, then:
   - Admin → **My Profile**: change the password and email.
   - Admin → **Website Settings**: set your real contact details, logo, socials.
   - Admin → **Users**: create additional accounts / remove the demo admin.

## C. Verify the install

- Home page loads with styling → assets + rewrite OK.
- `/about` (clean URL) loads → `mod_rewrite` OK.
- `/sitemap.xml` returns XML → dynamic sitemap OK.
- Submit the contact form → a success toast + a row in Admin → Contact Messages.
- Try a wrong admin password 6× → lockout message → throttling OK.
- Direct-open `includes/functions.php` in the browser → **403 Forbidden** (protected).

## D. Going live checklist

- [ ] `APP_ENV = production`
- [ ] Strong DB password; DB user limited to this database
- [ ] Admin password changed; demo data cleared or replaced
- [ ] HTTPS enforced (uncomment `.htaccess` redirect; set `Secure` cookies auto-on under HTTPS)
- [ ] `APP_URL` uses `https://`
- [ ] SMTP configured (Admin → Settings) so email actually sends
- [ ] `uploads/` and `logs/` writable; `includes/`, `config.php`, `database/` **not** web-readable
- [ ] Submit `sitemap.xml` to Google Search Console
