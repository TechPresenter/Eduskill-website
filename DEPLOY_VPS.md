# Deploying to a Hostinger VPS with GitHub

Target: **Ubuntu 24.04 LTS**, root access, domain already pointed at the server.
Result: `https://eduskillindia.org` served from a git clone you can update with `git pull`.

The other deploy guides in this repo cover Hostinger **shared** hosting. This one is for a
VPS, where you own the whole stack.

---

## Before you start — three things specific to this project

**1. Apache, not nginx.** This project depends on `.htaccess` in two ways nginx ignores
completely:

- the root `.htaccess` provides every extensionless URL on the site (`/donate`, `/schemes`)
- `uploads/resumes`, `uploads/documents`, `uploads/admissions`, `uploads/student-documents`,
  `uploads/coordinator-docs` and `uploads/kanyadaan-docs` each carry a `Require all denied`
  guard, because they hold applicant Aadhaar scans, bank documents and income proofs

On nginx those guards do nothing and that PII becomes publicly downloadable by URL. Use
Apache, and make sure `AllowOverride All` is set (Step 3) — without it Apache ignores
`.htaccess` too, with exactly the same consequence.

**2. `config.php` does not come from git.** It is gitignored on purpose: it holds the
database password and `APP_KEY`. You create it on the server by hand (Step 6). If you skip
it, every page answers `503` with a maintenance message and the real reason goes to the
Apache error log — that is `includes/bootstrap.php` telling you the file is missing.

**3. `APP_KEY` encrypts real PII.** Aadhaar and bank numbers on coordinator and Kanya Daan
applications are stored AES-256-GCM encrypted under this key. Generate it once, back it up,
and never change it on a live site — a changed key makes existing records unreadable.

---

## Step 1 — Code on GitHub  ✅ already done

The site lives at **https://github.com/TechPresenter/Eduskill-website**, branch `main`,
currently at commit `d32f29f`. The repository is **public**, so the server can clone it
over plain HTTPS with no deploy key.

To ship a change later: commit locally, then

```bash
cd /c/xampp/htdocs/pwf
git push website HEAD:main
```

---

## Step 2 — Base packages on the VPS

```bash
ssh root@187.127.188.140

apt update && apt upgrade -y

apt install -y apache2 mariadb-server git unzip curl ca-certificates \
  php php-cli php-mysql php-mbstring php-curl php-gd php-zip \
  php-xml php-imap php-intl php-bcmath libapache2-mod-php
```

Ubuntu 24.04 ships PHP 8.3; this project needs **8.2 or newer**. Confirm:

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|curl|gd|zip|xml|imap|openssl|fileinfo'
```

Every one of those is used: `gd` for ID cards and image validation, `openssl` for the
AES encryption, `fileinfo` for upload MIME checks, `imap` for the Email Center mailbox,
`curl` for the payment gateways.

---

## Step 3 — Apache: modules, vhost, and `AllowOverride All`

```bash
a2enmod rewrite headers expires deflate ssl
```

Create the site config:

```bash
nano /etc/apache2/sites-available/eduskillindia.conf
```

```apache
<VirtualHost *:80>
    ServerName eduskillindia.org
    ServerAlias www.eduskillindia.org
    DocumentRoot /var/www/eduskillindia.org

    <Directory /var/www/eduskillindia.org>
        # REQUIRED. Without this Apache ignores every .htaccess in the tree:
        # the pretty URLs break AND the uploads PII guards stop working.
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/eduskillindia-error.log
    CustomLog ${APACHE_LOG_DIR}/eduskillindia-access.log combined
</VirtualHost>
```

Enable it and drop the Apache default:

```bash
a2ensite eduskillindia
a2dissite 000-default
apache2ctl configtest      # must say "Syntax OK"
systemctl reload apache2
```

---

## Step 4 — Database

```bash
mysql_secure_installation      # set a root password, answer Y to the rest
```

Create the database and its own user (never let the app use root):

```bash
mysql -u root -p
```

```sql
CREATE DATABASE eduskill CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eduskill'@'localhost' IDENTIFIED BY 'PUT-A-LONG-RANDOM-PASSWORD-HERE';
GRANT ALL PRIVILEGES ON eduskill.* TO 'eduskill'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Step 5 — Clone the repository

If the GitHub repo is **private**, give the server a read-only deploy key first:

```bash
ssh-keygen -t ed25519 -C "eduskill-vps" -f /root/.ssh/id_ed25519 -N ""
cat /root/.ssh/id_ed25519.pub
```

Copy that key into GitHub → your repo → **Settings → Deploy keys → Add deploy key**
(leave "Allow write access" unchecked). Then clone over SSH:

```bash
rm -rf /var/www/html
git clone git@github.com:TechPresenter/Eduskill-website.git /var/www/eduskillindia.org
```

For a **public** repo, HTTPS is fine and needs no key:

```bash
git clone https://github.com/TechPresenter/Eduskill-website.git /var/www/eduskillindia.org
```

```bash
cd /var/www/eduskillindia.org
git branch --show-current   # main
```

---

## Step 6 — Create `config.php` (the step people skip)

```bash
cd /var/www/eduskillindia.org
cp config.example.php config.php

# generate a fresh 32-byte key and keep a copy somewhere safe
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"

nano config.php
```

Set at least these:

```php
define('DB_HOST',  'localhost');
define('DB_NAME',  'eduskill');
define('DB_USER',  'eduskill');
define('DB_PASS',  'the password from Step 4');

define('APP_KEY',  'base64:...the key you just generated...');

// The two URL constants. BASE_URI MUST become an empty string.
define('BASE_URI', '');                          // was '/pwf' for the XAMPP subfolder
define('APP_URL',  'https://eduskillindia.org'); // no trailing slash
```

> **`BASE_URI` is the one that catches people.** Locally the site lives in a subfolder
> (`http://localhost/pwf`), so `BASE_URI` is `'/pwf'` and every link, asset and form action is
> built with that prefix. At a domain root it must be `''`, or every URL on the live site comes
> out as `https://eduskillindia.org/pwf/...` and the whole site 404s — CSS included.

Then lock the file down — readable by Apache, by nobody else:

```bash
chown root:www-data config.php
chmod 640 config.php
```

---

## Step 7 — Import the database

**Recommended: bring your local database across**, so the site arrives with the content you
have already set up (the Kanya Daan scheme, bank details, settings, pages).

On Windows:

```bash
"/c/xampp/mysql/bin/mysqldump.exe" -u root --default-character-set=utf8mb4 \
  --single-transaction --routines pwf > eduskill-live.sql
scp eduskill-live.sql root@187.127.188.140:/root/
```

On the VPS:

```bash
mysql -u eduskill -p eduskill < /root/eduskill-live.sql
rm /root/eduskill-live.sql
```

**Alternative: a clean install** from the schema in the repo, which carries the tables plus
demo seed content:

```bash
cd /var/www/eduskillindia.org
mysql -u eduskill -p eduskill < database/eduskill.sql

# then every migration, in date order
for f in database/migrations/*.sql; do
  echo "applying $f"; mysql -u eduskill -p eduskill < "$f"
done
```

If you take the clean-install route you will need to re-enter the Kanya Daan scheme content
and the bank details through the admin panel.

---

## Step 8 — Ownership and writable folders

```bash
cd /var/www/eduskillindia.org

chown -R www-data:www-data /var/www/eduskillindia.org
find /var/www/eduskillindia.org -type d -exec chmod 755 {} \;
find /var/www/eduskillindia.org -type f -exec chmod 644 {} \;

# the three trees the app writes to
chmod -R 775 uploads logs storage

# re-apply the config lockdown, which the sweep above just loosened
chown root:www-data config.php && chmod 640 config.php
```

---

## Step 9 — HTTPS

```bash
apt install -y certbot python3-certbot-apache
certbot --apache -d eduskillindia.org -d www.eduskillindia.org
```

Choose redirect-to-HTTPS when asked. Renewal is automatic; check it with:

```bash
certbot renew --dry-run
```

Firewall:

```bash
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw enable
```

---

## Step 10 — Verify (do not skip the third one)

```bash
# 1. the site answers
curl -I https://eduskillindia.org

# 2. extensionless URLs work -> proves .htaccess rewrites are active
curl -o /dev/null -s -w "%{http_code}\n" https://eduskillindia.org/donate      # 200

# 3. THE IMPORTANT ONE — applicant PII must NOT be public
curl -o /dev/null -s -w "%{http_code}\n" https://eduskillindia.org/uploads/kanyadaan-docs/   # 403
curl -o /dev/null -s -w "%{http_code}\n" https://eduskillindia.org/uploads/resumes/          # 403

# 4. config.php must never be served
curl -o /dev/null -s -w "%{http_code}\n" https://eduskillindia.org/config.php  # 403
```

If check 3 returns **200 or a directory listing, stop and fix `AllowOverride All`** before
you let anyone submit an application — identity documents would be downloadable by anyone.

Then sign in at `https://eduskillindia.org/admin` and change the admin password immediately.

Watch the log while you click around:

```bash
tail -f /var/log/apache2/eduskillindia-error.log
```

---

## Step 11 — Deploying updates afterwards

Locally: commit, push to `main`. On the server:

```bash
cd /var/www/eduskillindia.org
git pull origin main

# apply any migration added since the last deploy
mysql -u eduskill -p eduskill < database/migrations/<the-new-file>.sql

chown -R www-data:www-data uploads logs storage
systemctl reload apache2
```

`config.php` and everything under `uploads/` are gitignored, so a pull never overwrites your
configuration or visitor-submitted files.

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| Every page is a 503 "temporarily unavailable" | `config.php` missing — Step 6. The real reason is in the Apache error log. |
| Home page loads, every other link 404s | `AllowOverride All` missing, so `.htaccess` rewrites are ignored — Step 3. |
| Every link and image points at `/pwf/...` | `BASE_URI` still `'/pwf'`; set it to `''` — Step 6. |
| `uploads/...` directories are browsable | Same cause, and it is a PII exposure. Fix immediately. |
| Blank white page | PHP fatal. `tail -50 /var/log/apache2/eduskillindia-error.log`. |
| Uploads fail over ~2 MB | Raise `upload_max_filesize` and `post_max_size` in `/etc/php/8.3/apache2/php.ini`, then `systemctl reload apache2`. |
| Aadhaar/bank fields show "cannot be decrypted" | `APP_KEY` differs from the one the data was encrypted under. Restore the original key. |
