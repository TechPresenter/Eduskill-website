<?php
/**
 * =============================================================================
 *  REST API v1 — shared bootstrap
 * =============================================================================
 *  - Loads the app spine (config, DB, helpers)
 *  - Sets JSON + CORS headers, handles OPTIONS preflight
 *  - Provides a consistent success/error envelope and helpers:
 *        api_method(), api_require_method(), api_input(),
 *        api_ok($data,$meta), api_fail($msg,$code), api_page_params()
 *  - Optional JWT auth scaffold (HS256) — DOCUMENTED and OFF by default.
 *
 *  Envelope:
 *      success list:   { "success": true, "data": [...], "meta": {...} }
 *      success single: { "success": true, "data": {...} }
 *      error:          { "success": false, "error": { "code": 404, "message": "" } }
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

/* ------------------------------------------------------------------ CORS */
// Restrict in production by setting the `api_cors_origin` setting.
$allowedOrigin = get_setting('api_cors_origin', '*');
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');
header('Vary: Origin');
header('Content-Type: application/json; charset=utf-8');

// Pre-flight.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ---------------------------------------------------------------- HELPERS */

/** Current HTTP method. */
function api_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/** Enforce an allowed method (string or array). */
function api_require_method($allowed): void
{
    $allowed = (array) $allowed;
    if (!in_array(api_method(), $allowed, true)) {
        api_fail('Method not allowed.', 405, ['Allow' => implode(', ', $allowed)]);
    }
}

/** Merge query params with a decoded JSON or form body. */
function api_input(): array
{
    $body = [];
    $raw  = file_get_contents('php://input');
    if ($raw !== '' && $raw !== false) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $body = $json;
        }
    }
    return array_merge($_GET, $_POST, $body);
}

/** Success envelope. */
function api_ok($data, array $meta = [], int $status = 200): void
{
    http_response_code($status);
    $payload = ['success' => true, 'data' => $data];
    if ($meta) {
        $payload['meta'] = $meta;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Error envelope. */
function api_fail(string $message, int $code = 400, array $headers = []): void
{
    foreach ($headers as $k => $v) {
        header($k . ': ' . $v);
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error'   => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Per-IP rate limit for write endpoints, returning a 429 with Retry-After.
 *
 * The public API accepts submissions without authentication by design — it is
 * the machine-readable face of the site's own contact/volunteer/donate forms.
 * Those forms are throttled by pwf_throttle(); the API must not be a way around
 * that, so it shares the same file-backed, IP-keyed budget. Keys are namespaced
 * 'api-*' so an API flood cannot lock a visitor out of the HTML form.
 */
function api_throttle(string $action, int $max = 5, int $windowSec = 300): void
{
    // No loopback exemption: the HTML forms are throttled on localhost too, and
    // exempting it here would leave exactly the bypass this closes.
    if (!pwf_throttle('api-' . $action, $max, $windowSec)) {
        api_fail('Too many requests. Please try again later.', 429, [
            'Retry-After' => (string) $windowSec,
        ]);
    }
}

/**
 * Parse pagination params. Returns [page, perPage, offset].
 */
function api_page_params(int $defaultPer = 12, int $maxPer = 50): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per  = (int) ($_GET['per_page'] ?? $defaultPer);
    $per  = max(1, min($maxPer, $per));
    return [$page, $per, ($page - 1) * $per];
}

/**
 * Run a paginated read query and emit the standard list envelope.
 * $sql must be a SELECT WITHOUT a LIMIT clause.
 */
function api_paginated(string $sql, array $params, int $defaultPer = 12): void
{
    [$page, $per, $offset] = api_page_params($defaultPer);
    $total = (int) db_value("SELECT COUNT(*) FROM ($sql) AS _c", $params);
    $rows  = db_all($sql . " LIMIT $per OFFSET $offset", $params);
    api_ok($rows, [
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $per,
        'last_page' => max(1, (int) ceil($total / $per)),
    ]);
}

/* -------------------------------------------------------------- OPTIONAL AUTH
 | JWT (HS256) scaffold. Disabled by default. To require a bearer token on an
 | endpoint, set the `api_jwt_enabled` setting to '1' and call api_require_auth().
 | Issue tokens elsewhere with jwt_encode(['sub'=>userId], get_setting('api_jwt_secret')).
 |----------------------------------------------------------------------------*/

function jwt_b64(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function jwt_b64_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

/** Encode a JWT (HS256). */
function jwt_encode(array $claims, string $secret, int $ttl = 3600): string
{
    $header  = jwt_b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $claims += ['iat' => time(), 'exp' => time() + $ttl];
    $payload = jwt_b64(json_encode($claims));
    $sig     = jwt_b64(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$sig";
}

/** Verify + decode a JWT. Returns claims array or null. */
function jwt_decode(string $token, string $secret): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;
    $expected = jwt_b64(hash_hmac('sha256', "$h.$p", $secret, true));
    if (!hash_equals($expected, $s)) {
        return null;
    }
    $claims = json_decode(jwt_b64_decode($p), true);
    if (!is_array($claims) || (isset($claims['exp']) && $claims['exp'] < time())) {
        return null;
    }
    return $claims;
}

/** Require a valid bearer token when JWT auth is enabled. */
function api_require_auth(): array
{
    if ((string) get_setting('api_jwt_enabled', '0') !== '1') {
        return []; // auth disabled — allow through
    }
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        api_fail('Authorization token required.', 401);
    }
    $claims = jwt_decode(trim($m[1]), get_setting('api_jwt_secret', 'change-me'));
    if ($claims === null) {
        api_fail('Invalid or expired token.', 401);
    }
    return $claims;
}
