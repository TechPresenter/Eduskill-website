<?php
/**
 * =============================================================================
 *  Security Center engine
 * =============================================================================
 *  Builds on the existing security stack (auth.php, auth_security.php, csrf.php,
 *  upload.php, login_attempts, activity_logs, settings). Adds the pieces the
 *  Security Center needs: IP/CIDR allow-listing, a shared password policy,
 *  AES-256-GCM encryption-at-rest, 2FA recovery codes + QR, login-attempt
 *  visibility + unlock, a PHP-level security-header layer, mysqldump backups,
 *  a live feature-status catalogue, and default-OFF admin enforcement.
 *
 *  Guiding rule: every enforcement toggle is OFF by default and can NEVER lock
 *  the admin out of http://localhost (loopback is always allowed; HTTPS/HSTS
 *  never fire on plain HTTP; force-2FA only redirects to enrollment).
 *
 *  Loaded by bootstrap AFTER auth_security.php.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 | Request context helpers
 | ========================================================================== */

/** True when the current request is served over HTTPS. */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Behind a trusted proxy only.
    if ((int) get_setting('trust_proxy', 0) === 1
        && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }
    return false;
}

/**
 * Client IP, hardened. By default trusts ONLY REMOTE_ADDR (proxy headers are
 * spoofable and would defeat the IP allow-list / lockout). Set trust_proxy=1
 * only when a real reverse proxy sits in front.
 */
function sec_client_ip(): string
{
    if ((int) get_setting('trust_proxy', 0) === 1) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', (string) $_SERVER[$h])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/** Loopback / localhost addresses that must never be locked out. */
function sec_is_loopback(string $ip): bool
{
    return $ip === '::1' || $ip === '127.0.0.1' || str_starts_with($ip, '127.');
}

/** Match an IP against a CIDR range (IPv4 or IPv6). Exact IP also accepted. */
function sec_cidr_match(string $ip, string $cidr): bool
{
    $cidr = trim($cidr);
    if ($cidr === '') {
        return false;
    }
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int) $bits;
    $ipBin     = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $bytes = intdiv($bits, 8);
    $rem   = $bits % 8;
    if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) {
        return false;
    }
    if ($rem === 0) {
        return true;
    }
    $mask = chr(0xFF << (8 - $rem) & 0xFF);
    return (ord($ipBin[$bytes]) & ord($mask)) === (ord($subnetBin[$bytes]) & ord($mask));
}

/**
 * Is the current client allowed into the admin area? Supersedes the plain
 * exact-match check with loopback bypass + CIDR. Empty list = allow all.
 */
function sec_ip_allowed(): bool
{
    $list = trim((string) get_setting('admin_ip_whitelist', ''));
    if ($list === '') {
        return true;
    }
    $ip = sec_client_ip();
    if (sec_is_loopback($ip)) {
        return true; // dev-box escape hatch — never self-lock localhost
    }
    foreach (array_filter(array_map('trim', explode(',', $list))) as $entry) {
        if ($entry === $ip || sec_cidr_match($ip, $entry)) {
            return true;
        }
    }
    return false;
}

/* =============================================================================
 | Security-event logging (reuses activity_logs + its new severity column)
 | ========================================================================== */

function sec_log(string $action, string $description, string $severity = 'notice'): void
{
    if (!in_array($severity, ['info', 'notice', 'warning', 'critical'], true)) {
        $severity = 'info';
    }
    try {
        db_insert('activity_logs', [
            'user_id'     => current_user_id(),
            'action'      => $action,
            'module'      => 'security',
            'description' => mb_substr($description, 0, 500),
            'severity'    => $severity,
            'ip_address'  => sec_client_ip(),
            'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('sec_log failed: ' . $e->getMessage());
    }
}

/* =============================================================================
 | AES-256-GCM encryption-at-rest
 | ========================================================================== */

/** Decoded 32-byte key from APP_KEY, or null when unset/invalid. */
function sec_app_key(): ?string
{
    if (!defined('APP_KEY') || !is_string(APP_KEY) || APP_KEY === '') {
        return null;
    }
    $raw = APP_KEY;
    if (str_starts_with($raw, 'base64:')) {
        $raw = base64_decode(substr($raw, 7), true) ?: '';
    }
    return strlen($raw) === 32 ? $raw : null;
}

function sec_has_encryption(): bool
{
    return sec_app_key() !== null && in_array('aes-256-gcm', openssl_get_cipher_methods(), true);
}

/** Encrypt a string. Returns base64(iv|tag|cipher) or null if unavailable. */
function sec_encrypt(string $plain): ?string
{
    $key = sec_app_key();
    if ($key === null) {
        return null;
    }
    $iv  = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        return null;
    }
    return 'enc:' . base64_encode($iv . $tag . $cipher);
}

/** Decrypt a value produced by sec_encrypt(). Returns null on tamper/failure. */
function sec_decrypt(string $value): ?string
{
    $key = sec_app_key();
    if ($key === null || !str_starts_with($value, 'enc:')) {
        return null;
    }
    $bin = base64_decode(substr($value, 4), true);
    if ($bin === false || strlen($bin) < 29) {
        return null;
    }
    $iv     = substr($bin, 0, 12);
    $tag    = substr($bin, 12, 16);
    $cipher = substr($bin, 28);
    $plain  = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}

/** True if a stored value is one of our ciphertexts. */
function sec_is_encrypted(?string $value): bool
{
    return is_string($value) && str_starts_with($value, 'enc:');
}

/* =============================================================================
 | Password policy — one validator for every password write point
 | ========================================================================== */

function sec_password_policy(): array
{
    return [
        'min_len'        => max(6, (int) get_setting('pw_min_len', 8)),
        'require_upper'  => (int) get_setting('pw_require_upper', 0) === 1,
        'require_lower'  => (int) get_setting('pw_require_lower', 0) === 1,
        'require_number' => (int) get_setting('pw_require_number', 0) === 1,
        'require_symbol' => (int) get_setting('pw_require_symbol', 0) === 1,
        'expiry_days'    => max(0, (int) get_setting('pw_expiry_days', 0)),
        'history_count'  => max(0, (int) get_setting('pw_history_count', 0)),
    ];
}

/** Validate a candidate password against the policy. */
function sec_password_validate(string $pw): array
{
    $p = sec_password_policy();
    $errors = [];
    if (mb_strlen($pw) < $p['min_len']) {
        $errors[] = 'Password must be at least ' . $p['min_len'] . ' characters.';
    }
    if ($p['require_upper'] && !preg_match('/[A-Z]/', $pw)) {
        $errors[] = 'Password must contain an uppercase letter.';
    }
    if ($p['require_lower'] && !preg_match('/[a-z]/', $pw)) {
        $errors[] = 'Password must contain a lowercase letter.';
    }
    if ($p['require_number'] && !preg_match('/[0-9]/', $pw)) {
        $errors[] = 'Password must contain a number.';
    }
    if ($p['require_symbol'] && !preg_match('/[^A-Za-z0-9]/', $pw)) {
        $errors[] = 'Password must contain a special character.';
    }
    return ['ok' => $errors === [], 'errors' => $errors];
}

/** False when the password reuses one of the last N hashes for this user. */
function sec_password_check_history(int $userId, string $pw): bool
{
    $n = sec_password_policy()['history_count'];
    if ($n <= 0) {
        return true;
    }
    $rows = db_all(
        'SELECT password FROM password_history WHERE user_id = :u ORDER BY created_at DESC LIMIT ' . (int) $n,
        [':u' => $userId]
    );
    foreach ($rows as $r) {
        if (password_verify($pw, $r['password'])) {
            return false;
        }
    }
    return true;
}

/** Record a new password hash into history and set password_changed_at. */
function sec_password_record(int $userId, string $hash): void
{
    try {
        db_insert('password_history', ['user_id' => $userId, 'password' => $hash]);
        db_update('users', ['password_changed_at' => date('Y-m-d H:i:s'), 'must_change_password' => 0], 'id = :id', [':id' => $userId]);
        $keep = max(1, sec_password_policy()['history_count'] ?: 5);
        // Trim history beyond what we need.
        $ids = db_all('SELECT id FROM password_history WHERE user_id = :u ORDER BY created_at DESC LIMIT 100', [':u' => $userId]);
        foreach (array_slice($ids, $keep) as $old) {
            db_delete('password_history', 'id = :id', [':id' => (int) $old['id']]);
        }
    } catch (Throwable $e) {
        error_log('sec_password_record failed: ' . $e->getMessage());
    }
}

/** Has the user's password expired under the policy? */
function sec_password_expired(array $user): bool
{
    $days = sec_password_policy()['expiry_days'];
    if ($days <= 0) {
        return false;
    }
    $changed = $user['password_changed_at'] ?? null;
    if (empty($changed)) {
        return false; // never stamped → don't force-expire legacy accounts
    }
    return strtotime((string) $changed) < (time() - $days * 86400);
}

/* =============================================================================
 | 2FA — QR rendering + one-time recovery codes (wrap existing totp_* helpers)
 | ========================================================================== */

/** Inline SVG QR for an otpauth:// URI (uses the dependency-free PWF_QR lib). */
function sec_2fa_qr_svg(string $otpauthUri): string
{
    if (!class_exists('PWF_QR')) {
        @require_once __DIR__ . '/lib/qrcode.php';
    }
    try {
        return PWF_QR::encode($otpauthUri, 'M')->svg(2, 4);
    } catch (Throwable $e) {
        return '';
    }
}

/** Generate N fresh recovery codes, replacing any existing ones. Returns plaintext ONCE. */
function sec_recovery_generate(int $userId, int $n = 10): array
{
    db_delete('user_recovery_codes', 'user_id = :u', [':u' => $userId]);
    $codes = [];
    for ($i = 0; $i < $n; $i++) {
        $code = strtoupper(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        $codes[] = $code;
        db_insert('user_recovery_codes', ['user_id' => $userId, 'code_hash' => password_hash($code, PASSWORD_DEFAULT)]);
    }
    sec_log('2fa_recovery', 'Generated ' . $n . ' recovery codes', 'notice');
    return $codes;
}

/** Verify + consume a recovery code. */
function sec_recovery_verify(int $userId, string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return false;
    }
    $rows = db_all('SELECT id, code_hash FROM user_recovery_codes WHERE user_id = :u AND used_at IS NULL', [':u' => $userId]);
    foreach ($rows as $r) {
        if (password_verify($code, $r['code_hash'])) {
            db_update('user_recovery_codes', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => (int) $r['id']]);
            sec_log('2fa_recovery_used', 'A 2FA recovery code was used for user #' . $userId, 'warning');
            return true;
        }
    }
    return false;
}

function sec_recovery_remaining(int $userId): int
{
    return (int) db_value('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = :u AND used_at IS NULL', [':u' => $userId]);
}

/* =============================================================================
 | Login protection — tunable thresholds, viewer, unlock
 | ========================================================================== */

function sec_lockout_max(): int
{
    return max(3, (int) get_setting('login_max_attempts', LOGIN_MAX_ATTEMPTS));
}
function sec_lockout_mins(): int
{
    return max(1, (int) get_setting('login_lockout_mins', LOGIN_LOCKOUT_MINS));
}
function sec_captcha_after(): int
{
    return max(1, (int) get_setting('captcha_after', 3));
}

/** Recent login attempts for the viewer. */
function sec_recent_attempts(int $limit = 50): array
{
    $limit = max(1, min(500, $limit));
    return db_all('SELECT * FROM login_attempts ORDER BY id DESC LIMIT ' . $limit);
}

/** Emails/IPs currently at or over the lockout threshold within the window. */
function sec_locked_list(): array
{
    $since = date('Y-m-d H:i:s', time() - sec_lockout_mins() * 60);
    return db_all(
        "SELECT email, ip_address, COUNT(*) AS fails, MAX(created_at) AS last_at
         FROM login_attempts
         WHERE success = 0 AND created_at >= :since
         GROUP BY email, ip_address
         HAVING fails >= :max
         ORDER BY last_at DESC",
        [':since' => $since, ':max' => sec_lockout_max()]
    );
}

/** Clear recent failed attempts for an email or IP (manual unlock). */
function sec_unlock(string $key): int
{
    $key = trim($key);
    if ($key === '') {
        return 0;
    }
    $since = date('Y-m-d H:i:s', time() - sec_lockout_mins() * 60);
    $affected = db_delete(
        'login_attempts',
        // Distinct placeholder per slot — PDO EMULATE_PREPARES=false binds each
        // name once, so the reused :k made the manual unlock button always 500.
        'success = 0 AND created_at >= :since AND (email = :k1 OR ip_address = :k2)',
        [':since' => $since, ':k1' => $key, ':k2' => $key]
    );
    sec_log('unlock', 'Cleared lockout for ' . $key, 'notice');
    return (int) $affected;
}

function sec_prune_login_attempts(int $days = 90): int
{
    $cut = date('Y-m-d H:i:s', time() - max(1, $days) * 86400);
    return (int) db_delete('login_attempts', 'created_at < :cut', [':cut' => $cut]);
}

/* =============================================================================
 | Security-header layer (PHP fallback for hosts without mod_headers)
 | ========================================================================== */

/**
 * Emit baseline security headers from PHP. HSTS only fires on real HTTPS in
 * production with the toggle on — never on the HTTP dev box (HSTS pins persist).
 * Skips the campaign-embed route so its permissive framing is preserved.
 */
function sec_emit_headers(): void
{
    if (headers_sent()) {
        return;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($uri, 'campaign-embed')) {
        return; // keep the embed widget's frame-ancestors * exemption
    }
    // Only set headers Apache's .htaccess may not have (idempotent-ish; header() overwrites).
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if (is_https() && APP_ENV === 'production' && (int) get_setting('hsts_enabled', 0) === 1) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/** Self-check: which security headers are actually present on responses. */
function sec_headers_status(): array
{
    $want = [
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'Referrer-Policy'         => 'present',
        'Content-Security-Policy' => 'present',
        'Permissions-Policy'      => 'present',
    ];
    $sent = [];
    foreach (headers_list() as $h) {
        $parts = explode(':', $h, 2);
        $sent[strtolower(trim($parts[0]))] = trim($parts[1] ?? '');
    }
    $out = [];
    foreach ($want as $name => $_) {
        $out[$name] = isset($sent[strtolower($name)]);
    }
    return $out;
}

function sec_https_status(): array
{
    $https = is_https();
    return [
        'is_https'       => $https,
        'force_enabled'  => (int) get_setting('force_https', 0) === 1,
        'hsts_enabled'   => (int) get_setting('hsts_enabled', 0) === 1,
        'safe_to_enable' => $https && APP_ENV === 'production',
    ];
}

/* =============================================================================
 | Database backups (mysqldump → gzip in BACKUP_DIR, outside the webroot)
 | ========================================================================== */

/** Is exec() usable in this PHP build? */
function sec_exec_available(): bool
{
    if (!function_exists('exec')) {
        return false;
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array('exec', $disabled, true);
}

/** Locate the mysqldump binary, or null when unavailable. */
function sec_mysqldump_path(): ?string
{
    if (defined('BACKUP_MYSQLDUMP') && BACKUP_MYSQLDUMP !== '' && is_file(BACKUP_MYSQLDUMP)) {
        return BACKUP_MYSQLDUMP;
    }
    foreach (['C:/xampp/mysql/bin/mysqldump.exe', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    return null;
}

/** Backup directory (placed outside the web document root by config). */
function sec_backup_dir(): string
{
    $dir = defined('BACKUP_DIR') ? BACKUP_DIR : (dirname(dirname(BASE_PATH)) . '/pwf-storage/backups');
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    // Belt-and-suspenders: if the dir ever resolves inside a served tree, an
    // Apache deny still blocks direct fetches.
    $ht = $dir . '/.htaccess';
    if (is_dir($dir) && !is_file($ht)) {
        @file_put_contents($ht, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\nOptions -Indexes\n");
    }
    return $dir;
}

/**
 * Run a database backup. Returns ['success'=>bool,'message'=>string,'id'=>int].
 * Degrades gracefully (never fatals the panel) when exec/mysqldump is missing.
 */
function sec_backup_run(string $destination = 'local'): array
{
    $dir = sec_backup_dir();
    if (!is_writable($dir)) {
        return ['success' => false, 'message' => 'Backup directory is not writable: ' . $dir];
    }
    if (!sec_exec_available()) {
        return ['success' => false, 'message' => 'PHP exec() is disabled on this server; automated backups are unavailable.'];
    }
    $bin = sec_mysqldump_path();
    if ($bin === null) {
        return ['success' => false, 'message' => 'mysqldump was not found. Set BACKUP_MYSQLDUMP in config.php.'];
    }

    $stamp    = date('Ymd-His');
    $base     = 'pwf-' . $stamp . '.sql';
    $sqlPath  = $dir . '/' . $base;
    $gzPath   = $sqlPath . '.gz';
    $errPath  = $dir . '/.dump-err.log';

    $rowId = (int) db_insert('backups', [
        'filename' => basename($gzPath), 'destination' => in_array($destination, ['local', 'ftp', 's3'], true) ? $destination : 'local',
        'status' => 'running', 'created_by' => current_user_id(),
    ]);

    $cmd = escapeshellarg($bin)
        . ' --host=' . escapeshellarg(DB_HOST) . ' --port=' . escapeshellarg((string) DB_PORT)
        . ' --user=' . escapeshellarg(DB_USER)
        . (DB_PASS !== '' ? ' --password=' . escapeshellarg(DB_PASS) : '')
        . ' --single-transaction --skip-lock-tables --routines --events --default-character-set=utf8mb4 '
        . escapeshellarg(DB_NAME)
        . ' > ' . escapeshellarg($sqlPath) . ' 2> ' . escapeshellarg($errPath);

    $out = []; $code = 0;
    @exec($cmd, $out, $code);

    if ($code !== 0 || !is_file($sqlPath) || filesize($sqlPath) === 0) {
        $err = is_file($errPath) ? trim((string) file_get_contents($errPath)) : '';
        @unlink($sqlPath);
        db_update('backups', ['status' => 'failed', 'message' => mb_substr('mysqldump exit ' . $code . ': ' . $err, 0, 500)], 'id = :id', [':id' => $rowId]);
        sec_log('backup_failed', 'Database backup failed (exit ' . $code . ')', 'warning');
        return ['success' => false, 'message' => 'Backup failed. ' . ($err !== '' ? $err : 'mysqldump returned ' . $code . '.'), 'id' => $rowId];
    }

    // Gzip the dump, then drop the plaintext .sql.
    $raw = (string) file_get_contents($sqlPath);
    @file_put_contents($gzPath, gzencode($raw, 6));
    @unlink($sqlPath);
    @unlink($errPath);

    $size = is_file($gzPath) ? (int) filesize($gzPath) : 0;
    $sum  = is_file($gzPath) ? hash_file('sha256', $gzPath) : null;
    db_update('backups', ['status' => 'success', 'size_bytes' => $size, 'checksum' => $sum, 'message' => 'OK'], 'id = :id', [':id' => $rowId]);
    sec_log('backup', 'Database backup created: ' . basename($gzPath) . ' (' . human_filesize($size) . ')', 'notice');

    // Optional off-site copy.
    if ($destination === 'ftp') {
        $ok = sec_backup_upload_ftp($gzPath);
        db_update('backups', ['message' => $ok ? 'OK · uploaded to FTP' : 'Saved locally · FTP upload failed'], 'id = :id', [':id' => $rowId]);
    }

    return ['success' => true, 'message' => 'Backup created: ' . basename($gzPath), 'id' => $rowId];
}

/** Off-site copy to FTP (self-contained; credentials from encrypted settings). */
function sec_backup_upload_ftp(string $file): bool
{
    $host = (string) get_setting('backup_ftp_host', '');
    if ($host === '' || !function_exists('ftp_connect') || !is_file($file)) {
        return false;
    }
    $user = (string) get_setting('backup_ftp_user', '');
    $pass = sec_setting_secret('backup_ftp_pass');
    $path = trim((string) get_setting('backup_ftp_path', ''), '/');
    try {
        $conn = @ftp_connect($host, 21, 15);
        if (!$conn || !@ftp_login($conn, $user, $pass)) {
            return false;
        }
        @ftp_pasv($conn, true);
        $remote = ($path !== '' ? $path . '/' : '') . basename($file);
        $ok = @ftp_put($conn, $remote, $file, FTP_BINARY);
        @ftp_close($conn);
        return (bool) $ok;
    } catch (Throwable $e) {
        return false;
    }
}

function sec_backup_list(int $limit = 50): array
{
    return db_all('SELECT * FROM backups ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
}

/** Absolute path to a backup file, contained to BACKUP_DIR (no traversal). */
function sec_backup_file_path(string $filename): ?string
{
    $filename = basename($filename); // strip any path
    $dir  = sec_backup_dir();
    $full = $dir . '/' . $filename;
    $real = realpath($full);
    $rdir = realpath($dir);
    if ($real === false || $rdir === false) {
        return null;
    }
    // Containment: exact dir, or a child under it (trailing separator prevents a
    // sibling like ".../backups-archive" from passing a bare prefix test).
    if ($real !== $rdir && !str_starts_with($real, $rdir . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $real;
}

function sec_backup_delete(int $id): bool
{
    $row = find('backups', $id);
    if (!$row) {
        return false;
    }
    $path = sec_backup_file_path((string) $row['filename']);
    if ($path !== null && is_file($path)) {
        @unlink($path);
    }
    db_delete('backups', 'id = :id', [':id' => $id]);
    sec_log('backup_delete', 'Deleted backup #' . $id, 'notice');
    return true;
}

/** Keep the newest $keep successful backups; delete older files + rows. */
function sec_backup_prune(int $keep = 7): int
{
    $rows = db_all("SELECT * FROM backups WHERE status = 'success' ORDER BY id DESC");
    $removed = 0;
    foreach (array_slice($rows, max(0, $keep)) as $old) {
        if (sec_backup_delete((int) $old['id'])) {
            $removed++;
        }
    }
    return $removed;
}

/* =============================================================================
 | Encrypted-secret settings helpers (blank-preserve + optional encryption)
 | ========================================================================== */

/** Read a possibly-encrypted secret setting as plaintext. */
function sec_setting_secret(string $key): string
{
    $val = (string) get_setting($key, '');
    if (sec_is_encrypted($val)) {
        return (string) (sec_decrypt($val) ?? '');
    }
    return $val;
}

/** Store a secret setting, encrypting at rest when a key is available. */
function sec_setting_secret_set(string $key, string $plain, string $group = 'security'): void
{
    if ($plain === '') {
        return; // blank-preserve: keep the existing stored value
    }
    $store = sec_has_encryption() ? (sec_encrypt($plain) ?? $plain) : $plain;
    set_setting($key, $store, $group, 'text');
}

/* =============================================================================
 | Default-OFF admin enforcement (called from require_admin, AFTER auth)
 | ========================================================================== */

/**
 * Applies must-change-password / password-expiry / force-2FA enforcement.
 * All are OFF by default and only ever REDIRECT (never 403). Target routes are
 * whitelisted so there is no redirect loop and no lockout.
 */
function sec_enforce_admin(): void
{
    if (!is_logged_in()) {
        return;
    }
    $path = function_exists('current_path') ? current_path() : ($_SERVER['REQUEST_URI'] ?? '');
    // Routes that must always stay reachable so the user can resolve the gate.
    $exempt = str_contains($path, 'admin/profile')
        || str_contains($path, 'admin/security')
        || str_contains($path, 'admin/logout')
        || str_contains($path, 'admin/login');
    if ($exempt) {
        return;
    }

    $uid  = current_user_id();
    $user = $uid ? find('users', $uid) : null;
    if (!$user) {
        return;
    }

    // 1) Forced password change / expiry → send to profile.
    if ((int) ($user['must_change_password'] ?? 0) === 1) {
        set_flash('warning', 'Please set a new password to continue.');
        redirect('/admin/profile#password');
    }
    if (sec_password_expired($user)) {
        set_flash('warning', 'Your password has expired. Please set a new one.');
        redirect('/admin/profile#password');
    }

    // 2) Org-wide 2FA requirement → send unenrolled admins to enroll (never blocks).
    if ((int) get_setting('force_2fa', 0) === 1 && (int) ($user['totp_enabled'] ?? 0) !== 1) {
        set_flash('warning', 'Two-factor authentication is required. Please set it up to continue.');
        redirect('/admin/security#twofa');
    }
}

/* =============================================================================
 | Feature-status catalogue (drives the Overview dashboard)
 | ========================================================================== */

/** One row per OWASP feature: label, state (ok|warn|off), detail, deep-link anchor. */
function sec_feature_status(): array
{
    $ipList   = trim((string) get_setting('admin_ip_whitelist', ''));
    $twofaTot = (int) db_value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL");
    $twofaOn  = (int) db_value("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND totp_enabled = 1");
    $lastBk   = db_row("SELECT * FROM backups WHERE status = 'success' ORDER BY id DESC LIMIT 1");
    $https    = sec_https_status();
    $pol      = sec_password_policy();
    $complexity = $pol['require_upper'] || $pol['require_lower'] || $pol['require_number'] || $pol['require_symbol'];

    return [
        ['key' => 'csrf', 'label' => 'CSRF Protection', 'state' => 'ok',
         'detail' => 'Per-session token, timing-safe verify on every POST + AJAX.', 'href' => ''],
        ['key' => 'xss', 'label' => 'XSS Prevention', 'state' => 'ok',
         'detail' => 'Output escaped with e(); rich text sanitised; safe_href() on links.', 'href' => ''],
        ['key' => 'sqli', 'label' => 'SQL Injection', 'state' => 'ok',
         'detail' => 'PDO prepared statements everywhere; emulation disabled.', 'href' => ''],
        ['key' => 'session', 'label' => 'Session Security', 'state' => 'ok',
         'detail' => 'HttpOnly + SameSite=Lax, id rotation, regenerate on login.', 'href' => 'access'],
        ['key' => 'ip', 'label' => 'Admin IP Restriction', 'state' => $ipList === '' ? 'off' : 'ok',
         'detail' => $ipList === '' ? 'Open to all IPs (loopback always allowed).' : 'Whitelist active.', 'href' => 'access'],
        ['key' => '2fa', 'label' => 'Two-Factor Auth', 'state' => $twofaOn > 0 ? 'ok' : 'off',
         'detail' => $twofaOn . ' of ' . $twofaTot . ' admin account(s) protected.' . ((int) get_setting('force_2fa', 0) === 1 ? ' Required org-wide.' : ''), 'href' => 'twofa'],
        ['key' => 'lockout', 'label' => 'Login Attempt Limits', 'state' => 'ok',
         'detail' => sec_lockout_max() . ' attempts / ' . sec_lockout_mins() . ' min; CAPTCHA after ' . sec_captcha_after() . '.', 'href' => 'login'],
        ['key' => 'password', 'label' => 'Password Policy', 'state' => ($pol['min_len'] >= 8 && $complexity) ? 'ok' : 'warn',
         'detail' => 'Min ' . $pol['min_len'] . ($complexity ? ', complexity on' : ', complexity off') . ($pol['expiry_days'] ? ', expires ' . $pol['expiry_days'] . 'd' : '') . '.', 'href' => 'password'],
        ['key' => 'upload', 'label' => 'Upload Protection', 'state' => (int) get_setting('upload_block_svg', 0) === 1 ? 'ok' : 'warn',
         'detail' => 'MIME + extension whitelist, random names. ' . ((int) get_setting('upload_block_svg', 0) === 1 ? 'SVG blocked.' : 'SVG allowed (sanitised).'), 'href' => 'uploads'],
        ['key' => 'headers', 'label' => 'Security Headers', 'state' => 'ok',
         'detail' => 'nosniff, X-Frame SAMEORIGIN, Referrer-Policy, CSP, Permissions-Policy.', 'href' => 'headers'],
        ['key' => 'https', 'label' => 'HTTPS / HSTS', 'state' => $https['is_https'] ? ($https['hsts_enabled'] ? 'ok' : 'warn') : 'off',
         'detail' => $https['is_https'] ? ('HTTPS active' . ($https['hsts_enabled'] ? ' with HSTS.' : ', HSTS off.')) : 'Development runs over HTTP.', 'href' => 'headers'],
        ['key' => 'encryption', 'label' => 'Data Encryption', 'state' => sec_has_encryption() ? 'ok' : 'off',
         'detail' => sec_has_encryption() ? 'AES-256-GCM key configured. Passwords bcrypt-hashed.' : 'APP_KEY not set — passwords still bcrypt-hashed.', 'href' => 'encryption'],
        ['key' => 'backup', 'label' => 'Database Backups', 'state' => $lastBk ? 'ok' : 'off',
         'detail' => $lastBk ? ('Last: ' . format_datetime($lastBk['created_at']) . ' (' . human_filesize((int) $lastBk['size_bytes']) . ').') : 'No backups yet.', 'href' => 'backups'],
        ['key' => 'audit', 'label' => 'Audit Logs', 'state' => 'ok',
         'detail' => 'Every admin action logged with user, IP and timestamp.', 'href' => 'audit'],
        ['key' => 'ratelimit', 'label' => 'Rate Limiting', 'state' => 'ok',
         'detail' => 'Login throttle + per-IP form throttle (pwf_throttle).', 'href' => 'login'],
    ];
}
