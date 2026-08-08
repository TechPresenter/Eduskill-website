<?php
/**
 * =============================================================================
 *  Member authentication — public member accounts (separate from admin `users`).
 * =============================================================================
 *  Session key: $_SESSION['member']  (admin uses $_SESSION['user']).
 *
 *  Registration & recovery flows are backed by the member_tokens table:
 *    - verify_email   : emailed link  /verify-email?email=&token=
 *    - reset_password : emailed link  /reset-password?email=&token=
 *    - otp            : 6-digit code emailed for /verify-otp
 *
 *  Page usage (each auth page handles its own POST):
 *    $r = member_register($_POST);         // signup
 *    $r = member_login($email, $password); // login
 *    request_password_reset($email);       // forgot
 *    reset_member_password($email,$token,$pass);
 *    verify_member_email($email,$token);
 *    verify_member_otp($email,$code);
 * =============================================================================
 */

declare(strict_types=1);

/* -------------------------------------------------------------- SESSION STATE */

function current_member(): ?array
{
    return $_SESSION['member'] ?? null;
}

function is_member_logged_in(): bool
{
    return !empty($_SESSION['member']['id']);
}

function require_member(string $redirect = '/login'): void
{
    if (!is_member_logged_in()) {
        $_SESSION['_member_intended'] = $_SERVER['REQUEST_URI'] ?? null;
        set_flash('error', 'Please sign in to continue.');
        redirect($redirect);
    }
}

function member_set_session(array $member): void
{
    session_regenerate_id(true);
    $_SESSION['member'] = [
        'id'     => (int) $member['id'],
        'name'   => $member['name'],
        'email'  => $member['email'],
        'avatar' => $member['avatar'] ?? null,
    ];
}

/* -------------------------------------------------------------- REGISTER/LOGIN */

/**
 * Register a new member. Returns [success, message, member_id, needs_verification].
 */
function member_register(array $data): array
{
    $name  = clean($data['name'] ?? '');
    $email = strtolower(clean($data['email'] ?? ''));
    $pass  = (string) ($data['password'] ?? '');
    $phone = clean($data['phone'] ?? '');

    $errors = validate($data, [
        'name'     => 'required|max:128',
        'email'    => 'required|email',
        'password' => 'required|min:8',
    ]);
    if ($errors) {
        return ['success' => false, 'message' => reset($errors), 'errors' => $errors];
    }
    if (find_by('members', 'email', $email)) {
        return ['success' => false, 'message' => 'An account with this email already exists.'];
    }

    $allowedTypes = ['member', 'donor', 'volunteer', 'intern', 'partner', 'supporter'];
    $type = in_array($data['type'] ?? '', $allowedTypes, true) ? $data['type'] : 'member';

    $id = db_insert('members', [
        'name'     => $name,
        'email'    => $email,
        'password' => password_hash($pass, PASSWORD_DEFAULT),
        'phone'    => $phone,
        'type'     => $type,
        'document' => $data['document'] ?? null,
        'status'   => 'pending',
    ]);

    // Send verification email (best effort).
    send_member_verification($email, $id, $name);

    // Attribute the signup to a captured referral code, if any.
    if (function_exists('ref_record')) {
        ref_record('signup', ['name' => $name, 'email' => $email]);
    }

    log_activity('member_register', 'members', 'New member: ' . $email);

    return [
        'success'             => true,
        'message'             => 'Account created! Please check your email to verify your address.',
        'member_id'           => $id,
        'needs_verification'  => true,
    ];
}

/**
 * Authenticate a member. Returns [success, message].
 */
function member_login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (is_locked_out($email)) {
        return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
    }

    $member = db_row('SELECT * FROM members WHERE email = :e LIMIT 1', [':e' => $email]);
    if (!$member || !password_verify($password, $member['password'])) {
        record_login_attempt($email, false);
        return ['success' => false, 'message' => 'Invalid email or password.'];
    }
    if ($member['status'] === 'suspended') {
        return ['success' => false, 'message' => 'Your account has been suspended.'];
    }
    if (empty($member['email_verified_at'])) {
        return ['success' => false, 'message' => 'Please verify your email before signing in.', 'unverified' => true, 'email' => $email];
    }

    member_set_session($member);
    db_update('members',
        ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()],
        'id = :id', [':id' => $member['id']]);
    record_login_attempt($email, true);

    return ['success' => true, 'message' => 'Welcome back, ' . $member['name'] . '!', 'member_id' => (int) $member['id']];
}

function member_logout(): void
{
    if (function_exists('remember_forget') && is_member_logged_in()) {
        remember_forget('members', 'pwf_member_remember', (int) ($_SESSION['member']['id'] ?? 0));
    }
    unset($_SESSION['member']);
}

/* -------------------------------------------------------------- TOKENS */

/**
 * Create a member token. Returns the RAW value to email (token string, or a
 * 6-digit code for OTP). Only its hash is stored.
 */
function create_member_token(string $email, string $type, ?int $memberId = null, int $ttlMinutes = 60): string
{
    // Invalidate previous unused tokens of the same type.
    db_query('UPDATE member_tokens SET used_at = NOW() WHERE email = :e AND type = :t AND used_at IS NULL',
        [':e' => $email, ':t' => $type]);

    $raw = ($type === 'otp')
        ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
        : bin2hex(random_bytes(32));

    db_insert('member_tokens', [
        'member_id'  => $memberId,
        'email'      => $email,
        'type'       => $type,
        'token'      => hash('sha256', $raw),
        'expires_at' => date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
    ]);
    return $raw;
}

/**
 * Verify (and consume) a member token. Returns the token row or null.
 */
function verify_member_token(string $email, string $type, string $rawValue): ?array
{
    $row = db_row(
        'SELECT * FROM member_tokens
         WHERE email = :e AND type = :t AND token = :tok AND used_at IS NULL AND expires_at >= NOW()
         ORDER BY id DESC LIMIT 1',
        [':e' => strtolower(trim($email)), ':t' => $type, ':tok' => hash('sha256', trim($rawValue))]
    );
    if (!$row) {
        return null;
    }
    db_update('member_tokens', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $row['id']]);
    return $row;
}

/* -------------------------------------------------------------- EMAIL SENDERS */

function send_member_verification(string $email, ?int $memberId = null, string $name = ''): void
{
    $token = create_member_token($email, 'verify_email', $memberId, 1440);
    $link  = abs_url('verify-email?email=' . urlencode($email) . '&token=' . $token);
    try {
        send_mail($email, 'Verify your email address',
            '<p>Hi ' . e($name) . ',</p><p>Welcome to ' . e(get_setting('site_name', SITE_NAME)) . '! '
            . 'Please confirm your email address by clicking the button below.</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;padding:12px 22px;background:#063566;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Verify Email</a></p>'
            . '<p>Or copy this link:<br>' . e($link) . '</p>');
    } catch (Throwable $e) { error_log('[member verify mail] ' . $e->getMessage()); }
}

function send_member_otp(string $email): string
{
    $code = create_member_token($email, 'otp', null, 15);
    try {
        send_mail($email, 'Your verification code',
            '<p>Your one-time verification code is:</p>'
            . '<p style="font-size:32px;font-weight:800;letter-spacing:8px;color:#063566;">' . e($code) . '</p>'
            . '<p>This code expires in 15 minutes.</p>');
    } catch (Throwable $e) { error_log('[member otp mail] ' . $e->getMessage()); }
    return $code;
}

function request_password_reset(string $email): bool
{
    $email  = strtolower(trim($email));
    $member = find_by('members', 'email', $email);
    if (!$member) {
        return false; // caller shows a generic message either way (no user enumeration)
    }
    $token = create_member_token($email, 'reset_password', (int) $member['id'], 30);
    $link  = abs_url('reset-password?email=' . urlencode($email) . '&token=' . $token);
    try {
        send_mail($email, 'Reset your password',
            '<p>We received a request to reset your password.</p>'
            . '<p><a href="' . e($link) . '" style="display:inline-block;padding:12px 22px;background:#063566;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;">Reset Password</a></p>'
            . '<p>If you did not request this, you can safely ignore this email. The link expires in 30 minutes.</p>');
    } catch (Throwable $e) { error_log('[member reset mail] ' . $e->getMessage()); }
    return true;
}

/* -------------------------------------------------------------- VERIFY/RESET */

function verify_member_email(string $email, string $token): array
{
    if (!verify_member_token($email, 'verify_email', $token)) {
        return ['success' => false, 'message' => 'This verification link is invalid or has expired.'];
    }
    db_update('members', ['email_verified_at' => date('Y-m-d H:i:s'), 'status' => 'active'],
        'email = :e', [':e' => strtolower(trim($email))]);
    return ['success' => true, 'message' => 'Your email has been verified. You can now sign in.'];
}

function verify_member_otp(string $email, string $code): array
{
    // A 6-digit OTP is only 10^6 wide, so unlimited guessing is a brute force.
    // Cap wrong attempts per email+IP within the code's lifetime; once the cap is
    // hit, burn every outstanding code for this email so the attacker must
    // trigger a fresh send (itself throttled to 3/10min) and start over.
    $key = 'otp-verify-' . strtolower(trim($email));
    if (function_exists('pwf_throttle') && !pwf_throttle($key, 5, 900)) {
        db_query('UPDATE member_tokens SET used_at = NOW()
                  WHERE email = :e AND type = :t AND used_at IS NULL',
            [':e' => strtolower(trim($email)), ':t' => 'otp']);
        return ['success' => false, 'message' => 'Too many incorrect codes. Please request a new one.'];
    }

    if (!verify_member_token($email, 'otp', $code)) {
        return ['success' => false, 'message' => 'The code is incorrect or has expired.'];
    }
    db_update('members', ['email_verified_at' => date('Y-m-d H:i:s'), 'status' => 'active'],
        'email = :e', [':e' => strtolower(trim($email))]);
    return ['success' => true, 'message' => 'Verified successfully!'];
}

function reset_member_password(string $email, string $token, string $newPassword): array
{
    if (mb_strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
    }
    if (!verify_member_token($email, 'reset_password', $token)) {
        return ['success' => false, 'message' => 'This reset link is invalid or has expired.'];
    }
    db_update('members', ['password' => password_hash($newPassword, PASSWORD_DEFAULT)],
        'email = :e', [':e' => strtolower(trim($email))]);
    return ['success' => true, 'message' => 'Your password has been reset. Please sign in.'];
}
