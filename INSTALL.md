# Installation & Deployment Guide

## A. Local (XAMPP / LAMP)

1. **Copy** the project to your web root (`C:\xampp\htdocs\pwf` on XAMPP).
2. **Start** Apache + MySQL (XAMPP Control Panel). Ensure `mod_rewrite`,
   `mod_headers`, `mod_deflate`, `mod_expires` are enabled (default on XAMPP).
3. **Create + import the database.** There is **one** SQL file:
   `database/eduskill.sql`. It replaced the old 34-file migration chain
   (`schema.sql`, `schema_v2`…`schema_v30`, `sample_data.sql` and two PHP
   seeders), which had to be applied in exact numeric order and silently left
   tables missing if you skipped one.

   ```bash
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE pwf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:\xampp\mysql\bin\mysql.exe -u root pwf < database\eduskill.sql
   ```

   In phpMyAdmin: create the database, click **into** it in the left sidebar,
   then Import → `database/eduskill.sql`. (The file has no `USE` statement, so
   it applies to whichever database is selected.)

   The file has two sections. On an empty database run the whole thing —
   Section 2 is idempotent and applies cleanly on top. On a database that
   already holds real data, run **Section 2 only**; Section 1 begins every
   table with `DROP TABLE IF EXISTS`.

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
3. **Import** `database/eduskill.sql` (phpMyAdmin → click into your database →
   Import). One file, nothing to order and nothing to skip.

   On a database that already holds real data, run **Section 2 only** — scroll
   to the `SECTION 2` banner and paste from there into the SQL tab. Section 2
   contains no `DROP`, `TRUNCATE` or `DELETE`.
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
