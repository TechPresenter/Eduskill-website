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
