<?php
/**
 * =============================================================================
 *  Core helper functions — the reusable spine used by every page.
 * =============================================================================
 *  Contains:
 *    - Generic PDO CRUD helpers (db_all, db_row, db_value, db_insert, ...)
 *    - Settings accessors (get_setting / set_setting)
 *    - Output escaping (e) and input sanitising (clean)
 *    - Slug helpers (slugify / unique_slug)
 *    - Request helpers (post / get / is_post / json_response)
 *    - Redirect + flash message system
 *    - Date / money / text formatting
 *    - Activity logging
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

/* =============================================================================
 |  GENERIC DATABASE HELPERS
 |  All use prepared statements. $params is an associative or positional array.
 |============================================================================*/

/**
 * Run a prepared statement and return the PDOStatement.
 */
function db_query(string $sql, array $params = []): PDOStatement
{
    $stmt = getPDO()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** Fetch all rows for a query. */
function db_all(string $sql, array $params = []): array
{
    return db_query($sql, $params)->fetchAll();
}

/** Fetch a single row (or null). */
function db_row(string $sql, array $params = []): ?array
{
    $row = db_query($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Fetch a single scalar value (first column of first row) or null. */
function db_value(string $sql, array $params = [])
{
    $value = db_query($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

/**
 * Insert an associative array into a table. Returns the new row id.
 */
function db_insert(string $table, array $data): int
{
    $columns      = array_keys($data);
    $placeholders = array_map(static fn($c) => ':' . $c, $columns);
    $sql = sprintf(
        'INSERT INTO `%s` (`%s`) VALUES (%s)',
        $table,
        implode('`, `', $columns),
        implode(', ', $placeholders)
    );
    $params = [];
    foreach ($data as $key => $value) {
        $params[':' . $key] = $value;
    }
    db_query($sql, $params);
    return (int) getPDO()->lastInsertId();
}

/**
 * Update rows. $where is a raw condition string (use placeholders), e.g.
 *   db_update('users', ['name' => 'X'], 'id = :id', [':id' => 5]);
 * Returns number of affected rows.
 */
function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = "`$key` = :set_$key";
        $params[":set_$key"] = $value;
    }
    $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
    $stmt = db_query($sql, array_merge($params, $whereParams));
    return $stmt->rowCount();
}

/**
 * Delete rows. Returns number of affected rows.
 *   db_delete('users', 'id = :id', [':id' => 5]);
 */
function db_delete(string $table, string $where, array $whereParams = []): int
{
    $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
    return db_query($sql, $whereParams)->rowCount();
}

/** Find a single row by primary key id. */
function find(string $table, int $id): ?array
{
    return db_row("SELECT * FROM `$table` WHERE id = :id LIMIT 1", [':id' => $id]);
}

/** Find a single row by an arbitrary column = value. */
function find_by(string $table, string $column, $value): ?array
{
    return db_row("SELECT * FROM `$table` WHERE `$column` = :v LIMIT 1", [':v' => $value]);
}

/**
 * Count rows matching an optional raw WHERE clause.
 *   db_count('donations', 'status = :s', [':s' => 'completed']);
 */
function db_count(string $table, string $where = '', array $params = []): int
{
    $sql = "SELECT COUNT(*) FROM `$table`";
    if ($where !== '') {
        $sql .= " WHERE $where";
    }
    return (int) db_value($sql, $params);
}

/**
 * Convenience list fetch. Conditions is an assoc array of column => value
 * combined with AND (equality only). For anything richer, use db_all().
 */
function get_all(string $table, array $conditions = [], string $orderBy = 'id DESC', ?int $limit = null): array
{
    $sql = "SELECT * FROM `$table`";
    $params = [];
    if ($conditions) {
        $clauses = [];
        foreach ($conditions as $col => $val) {
            $clauses[] = "`$col` = :$col";
            $params[":$col"] = $val;
        }
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }
    if ($orderBy !== '') {
        $sql .= " ORDER BY $orderBy";
    }
    if ($limit !== null) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db_all($sql, $params);
}

/* =============================================================================
 |  SETTINGS
 |============================================================================*/

/**
 * Return all settings as an associative key => value map (cached per request).
 */
function all_settings(bool $fresh = false): array
{
    static $cache = null;
    if ($cache === null || $fresh) {
        $cache = [];
        try {
            foreach (db_all('SELECT key_name, value FROM settings') as $row) {
                $cache[$row['key_name']] = $row['value'];
            }
        } catch (Throwable $e) {
            $cache = []; // settings table may not exist yet during install
        }
    }
    return $cache;
}

/**
 * Get a single setting value with a fallback default.
 */
function get_setting(string $key, $default = null)
{
    $settings = all_settings();
    return array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== ''
        ? $settings[$key]
        : $default;
}

/**
 * Is the public site currently switched into maintenance mode?
 *
 * Fails OPEN: if settings cannot be read (e.g. the database is unreachable) we
 * return false so the visitor gets the real error rather than a misleading
 * "scheduled maintenance" page for an unplanned outage.
 */
function maintenance_mode_enabled(): bool
{
    try {
        return (string) get_setting('maintenance_mode', '0') === '1';
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Should this request be allowed through while maintenance mode is on?
 *
 * Returns true when the request must proceed as normal — either because
 * maintenance mode is off, or because this particular request is exempt.
 * Called from includes/bootstrap.php.
 */
function maintenance_bypasses_request(): bool
{
    if (!maintenance_mode_enabled()) {
        return true;
    }

    // Signed-in admins browse the site normally so they can verify it before
    // switching maintenance back off.
    if (function_exists('is_logged_in') && is_logged_in()) {
        return true;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    // The whole admin area stays reachable — otherwise enabling maintenance
    // mode would lock the operator out of the switch that disables it.
    if (str_contains($script, '/admin/')) {
        return true;
    }

    // Endpoints that must keep working through the window: the error/maintenance
    // documents themselves (including them again would recurse), and the payment
    // return + webhook routes, so a gateway callback for a payment taken just
    // before the window can still settle instead of being lost.
    $exempt = [
        'maintenance.php', '500.php', '404.php',
        'donation-return.php', 'membership-payment-return.php', 'pay-return.php',
        'donation-verify.php', 'email-track.php',
    ];

    return in_array(basename($script), $exempt, true);
}

/**
 * Create or update a setting.
 */
function set_setting(string $key, $value, string $group = 'general', string $type = 'text'): void
{
    $exists = find_by('settings', 'key_name', $key);
    if ($exists) {
        db_update('settings', ['value' => $value], 'key_name = :k', [':k' => $key]);
    } else {
        db_insert('settings', [
            'key_name'   => $key,
            'value'      => $value,
            'group_name' => $group,
            'type'       => $type,
        ]);
    }
    all_settings(true); // refresh cache
}

/* =============================================================================
 |  OUTPUT ESCAPING & SANITISING
 |============================================================================*/

/**
 * Escape a value for safe HTML output. Use EVERYWHERE untrusted data is echoed.
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Trim and strip tags from an input string. */
function clean($value): string
{
    return trim(strip_tags((string) ($value ?? '')));
}

/**
 * Build a word-limited excerpt from HTML/text content.
 */
function excerpt(?string $text, int $words = 30, string $suffix = '…'): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if ($text === '') {
        return '';
    }
    $parts = explode(' ', $text);
    if (count($parts) <= $words) {
        return $text;
    }
    return implode(' ', array_slice($parts, 0, $words)) . $suffix;
}

/* =============================================================================
 |  SLUGS
 |============================================================================*/

/** Convert a string to a URL-safe slug. */
function slugify(string $text): string
{
    $text = trim($text);
    // Transliterate accented characters where possible.
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'n-a';
}

/**
 * Generate a slug unique within a table (appends -2, -3, ... on collision).
 * Pass $ignoreId to exclude the current row when editing.
 */
function unique_slug(string $table, string $text, ?int $ignoreId = null, string $column = 'slug'): string
{
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :slug";
        $params = [':slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }
        if ((int) db_value($sql, $params) === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

/* =============================================================================
 |  REQUEST HELPERS
 |============================================================================*/

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Read a GET parameter. */
function get(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/** Read a POST parameter. */
function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/** Read from POST then GET. */
function request(string $key, $default = null)
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/** Best-effort client IP address. */
function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** Detect an AJAX / fetch request. */
function is_ajax(): bool
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

/**
 * Emit a JSON response and stop. Used by AJAX handlers and the API.
 */
function json_response(array $data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Standard success envelope for AJAX/API. */
function json_success(string $message = 'OK', array $extra = [], int $status = 200): void
{
    json_response(array_merge(['success' => true, 'message' => $message], $extra), $status);
}

/** Standard error envelope for AJAX/API. */
function json_error(string $message = 'Something went wrong', array $extra = [], int $status = 422): void
{
    json_response(array_merge(['success' => false, 'message' => $message], $extra), $status);
}

/** Generate a cryptographically-secure random string. */
function str_random(int $length = 32): string
{
    return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
}

/* =============================================================================
 |  REDIRECT + FLASH MESSAGES
 |============================================================================*/

/**
 * Redirect to a path (relative to BASE_URI) or absolute URL, then stop.
 */
function redirect(string $to, int $code = 302): void
{
    // Absolute URL? use as-is. Otherwise resolve against BASE_URI.
    if (!preg_match('#^https?://#i', $to)) {
        $base = rtrim(BASE_URI, '/');
        // If the path already begins with the base URI (e.g. it came from
        // $_SERVER['REQUEST_URI']), keep it as-is; otherwise prepend the base.
        if ($base !== '' && ($to === $base || str_starts_with($to, $base . '/'))) {
            // already root-absolute and based — leave it
        } else {
            $to = $base . '/' . ltrim($to, '/');
        }
        $to = preg_replace('#(?<!:)//+#', '/', $to);
    }
    if (!headers_sent()) {
        header('Location: ' . $to, true, $code);
    }
    exit;
}

/** Redirect back to the referring page (or a fallback). */
function back(string $fallback = '/'): void
{
    redirect($_SERVER['HTTP_REFERER'] ?? $fallback);
}

/**
 * Store a flash message. Types: success | error | warning | info.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type][] = $message;
}

/** Retrieve and clear all flash messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $flashes;
}

/** Are there any flash messages waiting? */
function has_flashes(): bool
{
    return !empty($_SESSION['_flash']);
}

/**
 * Store old input so a form can be re-populated after a validation failure.
 */
function flash_old(array $input): void
{
    // Never flash sensitive fields.
    unset($input['password'], $input['password_confirmation'], $input[CSRF_TOKEN_NAME]);
    $_SESSION['_old'] = $input;
}

/** Retrieve an old input value (and keep it for the current request only). */
function old(string $key, $default = '')
{
    return $_SESSION['_old'][$key] ?? $default;
}

/** Clear old input (call at the end of a successful page render). */
function clear_old(): void
{
    unset($_SESSION['_old']);
}

/* =============================================================================
 |  DATE / TIME / MONEY FORMATTING
 |============================================================================*/

function format_date($date, string $format = 'd M Y'): string
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        return (new DateTime((string) $date))->format($format);
    } catch (Throwable $e) {
        return '';
    }
}

function format_datetime($date, string $format = 'd M Y, h:i A'): string
{
    return format_date($date, $format);
}

/** Human "3 hours ago" style string. */
function time_ago($date): string
{
    if (empty($date)) {
        return '';
    }
    try {
        $ts = (new DateTime((string) $date))->getTimestamp();
    } catch (Throwable $e) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff / 60) . ' min ago';
    if ($diff < 86400)   return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800)  return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    return format_date($date, 'd M Y');
}

/** Format a number as Indian-rupee currency by default. */
function money($amount, string $symbol = '₹', int $decimals = 0): string
{
    return $symbol . number_format((float) $amount, $decimals);
}

/** Format large numbers compactly (1.2K, 3.4M). */
function number_short($n): string
{
    $n = (float) $n;
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000)    return round($n / 1000, 1) . 'K';
    return (string) (int) $n;
}

/* =============================================================================
 |  ACTIVITY LOG
 |============================================================================*/

/**
 * Record an admin/user action in activity_logs (best-effort, never throws).
 */
function log_activity(string $action, ?string $module = null, ?string $description = null): void
{
    try {
        db_insert('activity_logs', [
            'user_id'     => $_SESSION['user']['id'] ?? null,
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'ip_address'  => client_ip(),
            'user_agent'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('[activity_log] ' . $e->getMessage());
    }
}

/* =============================================================================
 |  VALIDATION HELPERS
 |============================================================================*/

/** Validate an email address. */
function is_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Lightweight validator. $rules example:
 *   ['name' => 'required', 'email' => 'required|email', 'msg' => 'required|min:5']
 * Returns an array of field => error message (empty when valid).
 */
function validate(array $data, array $rules): array
{
    $errors = [];
    foreach ($rules as $field => $ruleset) {
        $value = trim((string) ($data[$field] ?? ''));
        foreach (explode('|', $ruleset) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $label = ucwords(str_replace('_', ' ', $field));
            if ($name === 'required' && $value === '') {
                $errors[$field] = "$label is required.";
            } elseif ($name === 'email' && $value !== '' && !is_email($value)) {
                $errors[$field] = "Please enter a valid email address.";
            } elseif ($name === 'min' && $value !== '' && mb_strlen($value) < (int) $param) {
                $errors[$field] = "$label must be at least $param characters.";
            } elseif ($name === 'max' && mb_strlen($value) > (int) $param) {
                $errors[$field] = "$label must not exceed $param characters.";
            } elseif ($name === 'numeric' && $value !== '' && !is_numeric($value)) {
                $errors[$field] = "$label must be a number.";
            }
            if (isset($errors[$field])) {
                break; // one error per field
            }
        }
    }
    return $errors;
}
