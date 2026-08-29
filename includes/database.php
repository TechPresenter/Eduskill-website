<?php
/**
 * =============================================================================
 *  Database layer — PDO singleton
 * =============================================================================
 *  Provides a single shared PDO connection configured for:
 *   - UTF-8 (utf8mb4)
 *   - Exceptions on error (ERRMODE_EXCEPTION)
 *   - Real prepared statements (emulation OFF)
 *   - Associative fetches by default
 *
 *  Usage anywhere:  $pdo = getPDO();
 * =============================================================================
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Return the shared PDO instance, creating it on first call.
 *
 * @return PDO
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        /* Pin the connection's time zone to PHP's, so a column defaulting to
           current_timestamp() is written in the same zone that date() prints.

           Without this the two disagree on any host whose system clock is not
           already IST — the production VPS runs UTC, so every submission was
           stored 5h30m behind and the admin list showed an application made
           moments ago as "5 hr ago". The offset is derived from PHP rather than
           hardcoded, so it stays correct across DST changes and if the site is
           ever configured for another zone. */
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            . ", time_zone = '" . (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P') . "'",
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Never leak credentials or SQL to the browser.
        if (APP_ENV === 'production') {
            error_log('[DB] Connection failed: ' . $e->getMessage());
            http_response_code(503);
            exit('Service temporarily unavailable. Please try again shortly.');
        }
        // Development: show a helpful message.
        http_response_code(503);
        exit(
            '<h1>Database connection failed</h1>' .
            '<p>Check your credentials in <code>config.php</code> and make sure ' .
            'MySQL is running in the XAMPP Control Panel.</p>' .
            '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>'
        );
    }

    return $pdo;
}
