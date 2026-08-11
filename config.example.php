<?php
/**
 * =============================================================================
 *  EDUSKILL INDIA FOUNDATION — Central Configuration (EXAMPLE / TEMPLATE)
 * =============================================================================
 *  HOW TO USE
 *  ----------
 *   1. Copy this file to `config.php` in the same directory:
 *          cp config.example.php config.php        (Linux / macOS)
 *          copy config.example.php config.php      (Windows)
 *   2. Fill in the values marked  <-- CHANGE ME  below.
 *   3. Generate an APP_KEY (see the "Encryption-at-rest key" block).
 *   4. Import the SQL files from database/ (see README.md -> Quick Start).
 *
 *  `config.php` is git-ignored on purpose — it holds live credentials and the
 *  encryption key. Never commit it. Keep this template in sync when you add a
 *  new constant.
 *
 *  This file defines every environment constant the application relies on:
 *  database credentials, URLs, filesystem paths, and the dev/prod error toggle.
 *
 *  Requires PHP 8.2+ (runs on 8.2 and 8.3). No framework, no Composer.
 * =============================================================================
 */

declare(strict_types=1);

/* -----------------------------------------------------------------------------
 | Environment
 | -----------------------------------------------------------------------------
 | 'development' -> full error display (local XAMPP).
 | 'production'  -> errors logged, never shown to visitors (shared hosting).
 */
define('APP_ENV', 'development');            // <-- CHANGE ME on a live server

/* -----------------------------------------------------------------------------
 | Site identity
 | -------------------------------------------------------------------------- */
define('SITE_NAME',     'EDUSKILL INDIA FOUNDATION');
define('SITE_TAGLINE',  'Empowering Communities • Spreading Hope • Creating Change');
define('SITE_EMAIL',    'info@eduskillindia.org');
define('SITE_PHONE',    '+91 00000 00000');                 // <-- CHANGE ME
define('SITE_ADDRESS',  'Your registered operating address'); // <-- CHANGE ME
define('SITE_CIN',      'UXXXXXXXXXXXXXXXXXXXXX');           // <-- CHANGE ME

/* -----------------------------------------------------------------------------
 | Statutory / registration details (Government certificates)
 | -----------------------------------------------------------------------------
 | Displayed across the footer, About, Contact and Legal/Compliance pages.
 | Each is also mirrored into the `settings` table so it stays admin-editable.
 */
define('SITE_LEGAL_STATUS',  'Section 8 Company (Non-Profit Organization)');
define('SITE_INCORP_DATE',   'DD Month YYYY');   // <-- CHANGE ME
define('SITE_PAN',           'XXXXXXXXXX');      // <-- CHANGE ME
define('SITE_TAN',           'XXXXXXXXXX');      // <-- CHANGE ME
define('SITE_DARPAN_ID',     'XX/YYYY/XXXXXXX'); // <-- CHANGE ME
// Registered office address — may be left blank. Pages that reference it
// degrade to "not shown" instead of fataling on an undefined constant.
// Set a value here, or in Settings -> registered_office, to show the block.
define('SITE_REG_OFFICE',    '');

/* -----------------------------------------------------------------------------
 | DATABASE — edit these for your environment            <-- CHANGE ME (all)
 | -----------------------------------------------------------------------------
 | Local XAMPP defaults: host=localhost, user=root, empty password.
 | On shared hosting your host will give you a db name + user + password.
 | NOTE: cPanel/Hostinger prefix DB and user names (e.g. u1234567_pwf).
 */
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'pwf');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/* -----------------------------------------------------------------------------
 | URLs                                                  <-- CHANGE ME (both)
 | -----------------------------------------------------------------------------
 | BASE_URI : root-relative path the site lives under, no trailing slash.
 |            Local subfolder install  -> '/pwf'
 |            Deployed at a domain root -> ''  (empty string)
 | APP_URL  : absolute URL, used for canonical links, Open Graph, emails,
 |            and the XML sitemap. No trailing slash.
 */
define('BASE_URI', '/pwf');
define('APP_URL',  'http://localhost/pwf');

/* -----------------------------------------------------------------------------
 | Filesystem paths (absolute, no trailing slash) — normally leave as-is
 | -------------------------------------------------------------------------- */
define('BASE_PATH',     __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('UPLOAD_PATH',   BASE_PATH . '/uploads');
define('UPLOAD_URI',    BASE_URI . '/uploads');   // web path to uploads
define('ASSET_URI',     BASE_URI . '/assets');    // web path to assets

/* -----------------------------------------------------------------------------
 | Asset cache-busting version. Bump this when you change CSS/JS so shared
 | hosting caches don't serve stale files (used as assets/main.js?v=ASSET_VERSION).
 | -------------------------------------------------------------------------- */
define('ASSET_VERSION', '5.8.7');

/* -----------------------------------------------------------------------------
 | Uploads
 | -------------------------------------------------------------------------- */
define('UPLOAD_MAX_BYTES',  5 * 1024 * 1024); // 5 MB per file
define('ALLOWED_IMAGE_EXT', 'jpg,jpeg,png,gif,webp,svg');
define('ALLOWED_DOC_EXT',   'pdf,doc,docx,xls,xlsx,ppt,pptx,txt');

/* -----------------------------------------------------------------------------
 | Security / session
 | -------------------------------------------------------------------------- */
define('SESSION_NAME',      'PWF_SESSION');
define('LOGIN_MAX_ATTEMPTS', 5);          // failed logins before lockout (fallback; tunable in Security Center)
define('LOGIN_LOCKOUT_MINS', 15);         // lockout window in minutes   (fallback; tunable in Security Center)
define('TWOFA_HANDSHAKE_SECS', 600);      // how long a pending 2FA login stays valid (10 min)
define('CSRF_TOKEN_NAME',   'csrf_token');

/* -----------------------------------------------------------------------------
 | Encryption-at-rest key (AES-256-GCM via includes/security.php)
 | -----------------------------------------------------------------------------
 | 32 random bytes, base64-encoded, prefixed "base64:". Generate ONCE and never
 | change it in place — rotating the key makes previously-encrypted values
 | unreadable. If blank/removed, sec_encrypt() fails closed and the Security
 | Center reports encryption as disabled.
 |
 | Generate one with PHP:
 |     php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
 | ...or with OpenSSL:
 |     openssl rand -base64 32
 |
 | <-- CHANGE ME. Paste the generated value below, keeping the "base64:" prefix.
 */
define('APP_KEY', '');

/* -----------------------------------------------------------------------------
 | Database backups (Security Center → Backups)
 | -----------------------------------------------------------------------------
 | BACKUP_DIR is placed ABOVE the app folder (dirname(BASE_PATH)) so a gzipped
 | SQL dump — which contains password hashes and PII — is not sitting inside the
 | served tree. On XAMPP (DocumentRoot = htdocs) point it above htdocs entirely;
 | override for your host if needed. The directory also carries its own deny
 | .htaccess as belt-and-suspenders. BACKUP_MYSQLDUMP can point at mysqldump.exe
 | if it is not on PATH; blank = auto-detect / degrade gracefully.
 | NOTE: LiteSpeed shared hosts (Hostinger) usually have no mysqldump — leave
 | BACKUP_MYSQLDUMP blank there and use the host's own backup tooling.
 */
define('BACKUP_DIR',       dirname(dirname(BASE_PATH)) . '/pwf-storage/backups');
define('BACKUP_MYSQLDUMP', '');   // e.g. 'C:/xampp/mysql/bin/mysqldump.exe' on XAMPP

/* -----------------------------------------------------------------------------
 | Mail (SMTP). Leave USE_SMTP false to fall back to PHP mail().
 | These can be overridden by rows in the `settings` table at runtime.
 | -------------------------------------------------------------------------- */
define('MAIL_FROM_EMAIL', SITE_EMAIL);
define('MAIL_FROM_NAME',  SITE_NAME);
define('USE_SMTP',        false);        // <-- set true and fill the block below
define('SMTP_HOST',       '');
define('SMTP_PORT',       587);
define('SMTP_USER',       '');
define('SMTP_PASS',       '');           // <-- never commit a real password
define('SMTP_SECURE',     'tls');        // 'tls' | 'ssl' | ''

/* -----------------------------------------------------------------------------
 | Timezone
 | -------------------------------------------------------------------------- */
date_default_timezone_set('Asia/Kolkata');

/* -----------------------------------------------------------------------------
 | Error reporting toggle (driven by APP_ENV)
 | -------------------------------------------------------------------------- */
if (APP_ENV === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php-error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php-error.log');
}

/* -----------------------------------------------------------------------------
 | Last-resort exception handler — even in development an uncaught exception
 | must never dump a raw stack trace (paths, SQL) into an API/JSON response;
 | in production the visitor gets a clean 500 page while the log gets the trace.
 | -------------------------------------------------------------------------- */
set_exception_handler(static function (Throwable $e): void {
    error_log('[uncaught] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        . "\n" . $e->getTraceAsString());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Uncaught: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (APP_ENV !== 'production') {
        // Developers still get the full picture, deliberately re-rendered.
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Uncaught ' . get_class($e) . ': ' . $e->getMessage() . "\n"
            . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString();
        exit;
    }
    http_response_code(500);
    $wantsJson = isset($_SERVER['REQUEST_URI']) && str_contains($_SERVER['REQUEST_URI'], '/api/');
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
            . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:12vh auto;text-align:center;">'
            . '<h1 style="font-size:1.6rem;">Something went wrong</h1>'
            . '<p style="color:#6b7280;">We hit an unexpected error. Please try again in a moment.</p>'
            . '<a href="' . htmlspecialchars(BASE_URI ?: '/', ENT_QUOTES) . '/" style="color:#0B4E3D;font-weight:700;">Back to home</a></div>';
    }
    exit;
});
