<?php
/**
 * =============================================================================
 *  Bootstrap — the single include every page pulls in first.
 * =============================================================================
 *  Put this at the very top of every public page, admin page, API and handler:
 *
 *      require_once __DIR__ . '/includes/bootstrap.php';        // public root
 *      require_once __DIR__ . '/../includes/bootstrap.php';     // admin/, api/v1/
 *
 *  It loads config, the database layer, every helper in the spine, and starts
 *  a hardened session so csrf_token(), current_user(), flash messages, etc. are
 *  ready to use.
 * =============================================================================
 */

declare(strict_types=1);

/* -----------------------------------------------------------------------------
 |  config.php must exist before anything else can run — and it is the ONE file
 |  a deploy is most likely to be missing, because it is gitignored on purpose
 |  (it holds the DB password and APP_KEY, which must never reach a public repo).
 |
 |  A Git-based deploy therefore never delivers it, and `require_once` on a
 |  missing file is a fatal with ZERO output once display_errors is off: the
 |  server answers 500 with Content-Length: 0 on every single page. Nothing in
 |  the response says what is wrong, so the site looks comprehensively broken
 |  when exactly one file is absent. That is what took eduskillindia.org down.
 |
 |  Say so instead. No paths, no versions, no stack trace — the visitor gets a
 |  neutral 503 maintenance message; the specific cause goes to the error log.
 |
 |  Note WHICH log: config.php is what points errors at logs/php-error.log, and
 |  it has not run, so this line lands in the SERVER error log instead
 |  (Apache's error.log locally; hPanel -> Files -> Error Log on Hostinger).
 |  That is the right place anyway — it is the log an operator reaches for when
 |  the app itself will not start.
 |
 |  503 rather than 500 is deliberate: this is a configuration gap, not a crash,
 |  and Retry-After tells crawlers to come back instead of dropping the pages
 |  from the index.
 * -------------------------------------------------------------------------- */
if (!is_file(__DIR__ . '/../config.php')) {
    error_log('[bootstrap] config.php is missing. Copy config.example.php to config.php and fill in the database credentials. It is gitignored, so a Git deploy will not create it for you.');

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "config.php is missing. Copy config.example.php to config.php and set the database credentials.\n");
        exit(1);
    }

    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Temporarily unavailable</title>'
       . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#0B4E3D;color:#fff;padding:2rem}'
       . 'div{max-width:32rem;text-align:center}h1{font-size:1.5rem;margin:0 0 .75rem;font-weight:650}'
       . 'p{margin:0;opacity:.85;line-height:1.6}</style></head><body><div>'
       . '<h1>This site is temporarily unavailable</h1>'
       . '<p>We are carrying out maintenance and will be back shortly. '
       . 'Please try again in a little while.</p>'
       . '</div></body></html>';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/upload.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/tracking.php';
require_once __DIR__ . '/messaging.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/marketing.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/member_auth.php';
require_once __DIR__ . '/membership.php';
require_once __DIR__ . '/school.php';
require_once __DIR__ . '/student.php';
require_once __DIR__ . '/lms.php';
require_once __DIR__ . '/campaign.php';
require_once __DIR__ . '/donation.php';
require_once __DIR__ . '/employee.php';
require_once __DIR__ . '/auth_security.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/rbac.php';
require_once __DIR__ . '/auth_router.php';   // role vocabulary + post-login routing
require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/widgets.php';
require_once __DIR__ . '/registrations.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/faq-section.php';
require_once __DIR__ . '/blocks.php';

// Start the hardened session (defined in auth.php).
pwf_session_start();

// Emit PHP-level security headers (fallback for hosts without mod_headers).
if (function_exists('sec_emit_headers')) {
    sec_emit_headers();
}

// Restore persistent "remember me" logins, then enforce the idle timeout.
remember_attempt();
enforce_session_timeout();

// Record the page view (public GET pages only; skips admin/api/bots/logged-in).
track_pageview();

/* -----------------------------------------------------------------------------
 | Maintenance mode
 | -----------------------------------------------------------------------------
 | When `maintenance_mode` is enabled in Settings the public site answers 503
 | with maintenance.php. Deliberately NOT applied to:
 |   - the CLI (cron jobs must keep running through a maintenance window),
 |   - admin/ and the admin login (so you can switch the site back on),
 |   - maintenance.php / 500.php themselves (which would recurse),
 |   - the payment return + webhook endpoints, because a gateway callback that
 |     arrives mid-window must still be able to settle an in-flight payment.
 | Signed-in admins always bypass it so the site can be verified before release.
 */
if (PHP_SAPI !== 'cli' && !maintenance_bypasses_request()) {
    require __DIR__ . '/../maintenance.php';
    exit;
}
