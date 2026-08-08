<?php
/**
 * =============================================================================
 *  CSRF protection
 * =============================================================================
 *  Every form must include csrf_field(). Every state-changing POST handler
 *  must call require_csrf() (or verify_csrf()) before touching the database.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Return the session CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Hidden form field carrying the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * Meta tag so JavaScript (fetch) can read the token for AJAX requests.
 */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . csrf_token() . '">';
}

/**
 * Verify a submitted token against the session token (timing-safe).
 * Falls back to reading POST / the X-CSRF-Token header.
 */
function verify_csrf(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME]
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
    }
    $stored = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    return $stored !== '' && is_string($token) && hash_equals($stored, $token);
}

/**
 * Enforce CSRF. On failure: JSON error for AJAX, otherwise flash + redirect back.
 */
function require_csrf(): void
{
    if (verify_csrf()) {
        return;
    }
    if (is_ajax()) {
        json_error('Your session has expired. Please refresh the page and try again.', [], 419);
    }
    set_flash('error', 'Security check failed. Please try again.');
    back();
}
