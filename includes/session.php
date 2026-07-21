<?php
/**
 * Hardened session start. HttpOnly + SameSite=Lax always; Secure only under HTTPS (so local http
 * still works). Started once, here, for every page.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('esk_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => BASE_URL . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Periodic id rotation to shrink the fixation window.
    if (!isset($_SESSION['_born'])) {
        $_SESSION['_born'] = time();
    } elseif (time() - (int) $_SESSION['_born'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_born'] = time();
    }
}
