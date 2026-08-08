<?php
/**
 * =============================================================================
 *  Auth security helpers — remember-me, CAPTCHA, admin IP whitelist,
 *  configurable session timeout, and TOTP (Google Authenticator) 2FA.
 * =============================================================================
 *  Loaded by bootstrap after auth.php + member_auth.php.
 * =============================================================================
 */

declare(strict_types=1);

/* -----------------------------------------------------------------------------
 | Configurable idle-session timeout
 | -------------------------------------------------------------------------- */
function enforce_session_timeout(): void
{
    $minutes = (int) get_setting('session_timeout_minutes', 30);
    if ($minutes <= 0) {
        return;
    }
    $now = time();
    if (!empty($_SESSION['_last_activity']) && ($now - $_SESSION['_last_activity']) > $minutes * 60) {
        // Idle too long — end any privileged session.
        if (is_logged_in()) { logout(); }
        if (function_exists('member_logout')) { member_logout(); }
        pwf_session_start();
        set_flash('info', 'You were signed out due to inactivity.');
    }
    $_SESSION['_last_activity'] = $now;
}

/* -----------------------------------------------------------------------------
 | Admin IP whitelist (setting `admin_ip_whitelist`, comma-separated; empty = all)
 | -------------------------------------------------------------------------- */
function admin_ip_allowed(): bool
{
    // Prefer the hardened engine check (loopback bypass + CIDR) when available.
    if (function_exists('sec_ip_allowed')) {
        return sec_ip_allowed();
    }
    $list = trim((string) get_setting('admin_ip_whitelist', ''));
    if ($list === '') {
        return true; // no restriction configured
    }
    $ip = client_ip();
    // Never lock out loopback / localhost — prevents self-lockout on the dev box.
    if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.')) {
        return true;
    }
    foreach (array_filter(array_map('trim', explode(',', $list))) as $allowed) {
        if ($allowed === $ip) {
            return true;
        }
    }
    return false;
}

/* -----------------------------------------------------------------------------
 | Simple math CAPTCHA (shown after 3 failed logins)
 | -------------------------------------------------------------------------- */
function login_needs_captcha(string $email): bool
{
    $after = function_exists('sec_captcha_after') ? sec_captcha_after() : 3;
    return failed_login_count($email) >= $after;
}

function captcha_new(): array
{
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $_SESSION['_captcha'] = $a + $b;
    return ['a' => $a, 'b' => $b];
}

function captcha_verify(): bool
{
    $answer = (int) post('captcha', -1);
    $ok = isset($_SESSION['_captcha']) && $answer === (int) $_SESSION['_captcha'];
    unset($_SESSION['_captcha']);
    return $ok;
}

/* -----------------------------------------------------------------------------
 | Remember-me (persistent login) for a users/members row
 | -------------------------------------------------------------------------- */
function remember_issue(string $table, int $id, string $cookie): void
{
    $token = str_random(64);
    db_update($table, ['remember_token' => hash('sha256', $token)], 'id = :id', [':id' => $id]);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie($cookie, $id . ':' . $token, [
        'expires'  => time() + 60 * 60 * 24 * 30, // 30 days
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_forget(string $table, string $cookie, ?int $id = null): void
{
    if ($id) {
        db_update($table, ['remember_token' => null], 'id = :id', [':id' => $id]);
    }
    setcookie($cookie, '', time() - 3600, '/');
}

/**
 * On each request, if not logged in but a valid remember cookie exists, restore
 * the session. Called from bootstrap.
 */
function remember_attempt(): void
{
    // Admin
    if (!is_logged_in() && !empty($_COOKIE['pwf_remember'])) {
        [$id, $token] = array_pad(explode(':', $_COOKIE['pwf_remember'], 2), 2, '');
        $user = $id ? find('users', (int) $id) : null;
        if ($user && !empty($user['remember_token']) && $token !== ''
            && hash_equals($user['remember_token'], hash('sha256', $token))
            && $user['status'] === 'active' && empty($user['deleted_at'])
            && (int) $user['totp_enabled'] !== 1) { // 2FA users must re-verify
            $_SESSION['user'] = [
                'id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email'],
                'role_id' => $user['role_id'] !== null ? (int) $user['role_id'] : null, 'avatar' => $user['avatar'],
            ];
            $_SESSION['_created'] = time();
        } else {
            remember_forget('users', 'pwf_remember');
        }
    }
    // Member
    if (function_exists('member_set_session') && !is_member_logged_in() && !empty($_COOKIE['pwf_member_remember'])) {
        [$id, $token] = array_pad(explode(':', $_COOKIE['pwf_member_remember'], 2), 2, '');
        $m = $id ? find('members', (int) $id) : null;
        if ($m && !empty($m['remember_token']) && $token !== ''
            && hash_equals($m['remember_token'], hash('sha256', $token))
            && $m['status'] === 'active' && !empty($m['email_verified_at'])) {
            member_set_session($m);
        } else {
            remember_forget('members', 'pwf_member_remember');
        }
    }
}

/* -----------------------------------------------------------------------------
 | TOTP (RFC 6238) — Google Authenticator compatible
 | -------------------------------------------------------------------------- */
function totp_base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

/** Generate a new random base32 secret (16 chars). */
function totp_secret_new(int $length = 16): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = '';
    for ($i = 0; $i < $length; $i++) {
        $s .= $alphabet[random_int(0, 31)];
    }
    return $s;
}

/** Compute the TOTP code for a secret at a given time step. */
function totp_code(string $secret, ?int $timeSlice = null): string
{
    $timeSlice = $timeSlice ?? (int) floor(time() / 30);
    $key = totp_base32_decode($secret);
    $bin = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $part = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    );
    return str_pad((string) ($part % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Verify a user-entered TOTP code within a +/- window (default 1 step). */
function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) {
        return false;
    }
    $current = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $current + $i), $code)) {
            return true;
        }
    }
    return false;
}

/** otpauth:// URI for QR generation / manual entry. */
function totp_uri(string $secret, string $account, ?string $issuer = null): string
{
    $issuer = $issuer ?: get_setting('site_name', SITE_NAME);
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
