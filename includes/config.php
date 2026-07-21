<?php
/**
 * Eduskill India Foundation — application bootstrap + configuration.
 *
 * Every page (public and admin) and every API endpoint includes this ONE file. It defines the
 * config constants, then pulls in the rest of the engine (db, functions, helpers, session, auth) so
 * a page only needs:  require __DIR__ . '/includes/config.php';
 *
 * Flat-file, no framework, no Composer — deployable by uploading to any PHP 8 shared host.
 */

declare(strict_types=1);

if (defined('ESK')) {
    return; // already bootstrapped
}
define('ESK', true);

/* ------------------------------------------------------------------ environment */

define('APP_ENV', 'local');            // 'local' | 'production'
define('APP_DEBUG', APP_ENV === 'local');
define('APP_NAME', 'Eduskill India Foundation');

/* ------------------------------------------------------------------ database */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'eduskill_dev');     // import database/eduskill_foundation.sql into this
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ------------------------------------------------------------------ security key */

// 64 hex chars — used for AES-256 / HMAC of sensitive values. Change on a real deployment.
define('APP_KEY', 'dev0000000000000000000000000000000000000000000000000000000000dev');

/* ------------------------------------------------------------------ paths + URLs */

define('BASE_PATH', dirname(__DIR__));                 // filesystem root of the project
define('INCLUDES_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', BASE_PATH . '/assets/uploads');

/**
 * BASE_URL — the app's URL prefix from the domain root ("/eduskill" locally, "" at a domain root).
 * Derived from where the project sits under the web root, so it is correct from ANY page depth
 * (root pages and /admin pages alike). str_replace before rtrim handles Windows backslashes.
 */
$docRoot = rtrim(str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$projRoot = str_replace('\\', '/', BASE_PATH);
$prefix = ($docRoot !== '' && str_starts_with($projRoot, $docRoot)) ? substr($projRoot, strlen($docRoot)) : '';
define('BASE_URL', rtrim($prefix, '/'));

/* ------------------------------------------------------------------ timezone + errors */

date_default_timezone_set('Asia/Kolkata');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

/* ------------------------------------------------------------------ load the engine */

require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/functions.php';
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/content.php';
require_once INCLUDES_PATH . '/crud.php';
require_once INCLUDES_PATH . '/sections_schema.php';
require_once INCLUDES_PATH . '/session.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/mailer.php';
