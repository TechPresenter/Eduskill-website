<?php
/**
 * Core functions — escaping, URLs, settings, CSRF, validation, and the HTML sanitizer.
 * These are the vocabulary every page and API endpoint leans on.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

/* ---------------------------------------------------------------- output + urls */

/** Escape for safe HTML output. THE XSS boundary for dynamic plain text — use on every echoed value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Absolute URL from the domain root for an app path: url('about.php') => "/eduskill/about.php". */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL to a static asset under /assets. */
function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    $to = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

function current_url_is(string $file): bool
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === $file;
}

/* ---------------------------------------------------------------- settings */

/** Read a site setting (cached for the request). Safe if the table is missing (returns default). */
function setting(string $key, mixed $default = null): mixed
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT setting_key, setting_value, type FROM settings') as $row) {
                $cache[$row['setting_key']] = match ($row['type']) {
                    'bool' => (bool) $row['setting_value'],
                    'int' => (int) $row['setting_value'],
                    'json' => json_decode((string) $row['setting_value'], true),
                    default => $row['setting_value'],
                };
            }
        } catch (Throwable) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

/* ---------------------------------------------------------------- csrf */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    $known = $_SESSION['_csrf'] ?? '';
    return $known !== '' && is_string($token) && $token !== '' && hash_equals($known, $token);
}

/* ---------------------------------------------------------------- REST API helpers */

/** Send a JSON response and stop. Used by every api/*.php endpoint. */
function json_out(array $data, int $code = 200): never
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data = [], string $message = ''): never
{
    json_out(array_merge(['ok' => true, 'message' => $message], $data));
}

function json_error(string $message, int $code = 400, array $extra = []): never
{
    json_out(array_merge(['ok' => false, 'message' => $message], $extra), $code);
}

/** Request body as an array — supports both JSON and form-encoded/multipart bodies. */
function api_body(): array
{
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($ct, 'application/json')) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

function request_method(): string
{
    $m = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    // Allow ?_method= override for clients that can't send PUT/DELETE.
    if ($m === 'POST' && isset($_REQUEST['_method'])) {
        $o = strtoupper((string) $_REQUEST['_method']);
        if (in_array($o, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $o;
        }
    }
    return $m;
}

/** Enforce API auth + permission; returns JSON 401/403 and stops if not allowed. */
function api_require(string $permission): void
{
    if (!is_logged_in()) {
        json_error('Authentication required.', 401);
    }
    if (!user_can($permission)) {
        json_error('You do not have permission to do that.', 403);
    }
}

/** Reusable image upload widget (hidden path input + preview + upload/remove). JS in admin.js wires it. */
function upload_widget(string $name, string $value = ''): string
{
    ob_start(); ?>
    <div class="esk-upload mt-1">
      <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
      <div class="flex items-center gap-3">
        <div class="esk-upload-preview grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-lg border border-edge bg-surface-sunken">
          <?php if ($value !== ''): ?><img src="<?= e(asset($value)) ?>" class="h-full w-full object-cover"><?php else: ?><i class="fa-regular fa-image text-content-subtle"></i><?php endif; ?>
        </div>
        <div class="flex items-center gap-2">
          <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-edge bg-surface px-3 py-2 text-sm font-medium text-content hover:bg-surface-sunken"><i class="fa-solid fa-upload"></i> Upload<input type="file" class="esk-upload-input hidden" accept="image/jpeg,image/png,image/webp"></label>
          <button type="button" class="esk-upload-clear text-sm font-medium text-danger-600 hover:underline">Remove</button>
        </div>
      </div>
    </div>
    <?php return (string) ob_get_clean();
}

/* ---------------------------------------------------------------- misc helpers */

function str_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

/** Unique slug within a table (optionally excluding one id). */
function unique_slug(string $table, string $base, ?int $exceptId = null): string
{
    $base = $base !== '' ? $base : 'item';
    $slug = $base;
    $n = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = ?";
        $params = [$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $exceptId;
        }
        if ((int) db_val($sql, $params) === 0) {
            return $slug;
        }
        $slug = $base . '-' . (++$n);
    }
}

/** Indian digit grouping for whole rupees: 123456 => "1,23,456". */
function inr(int $paise): string
{
    $n = (int) floor($paise / 100);
    $s = (string) abs($n);
    if (strlen($s) > 3) {
        $last3 = substr($s, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($s, 0, -3));
        $s = $rest . ',' . $last3;
    }
    return ($n < 0 ? '-' : '') . $s;
}

/* ---------------------------------------------------------------- flash messages */

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

/* ---------------------------------------------------------------- html sanitizer (XSS boundary) */

/**
 * Clean rich text (CKEditor output) against a strict allowlist, on SAVE. Parses to a DOM and rebuilds
 * from an allowlist so malformed-markup tricks that fool regex sanitizers don't get through.
 */
function clean_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }
    static $allowed = [
        'p' => [], 'br' => [], 'hr' => [], 'span' => [], 'div' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 'small' => [], 'sub' => [], 'sup' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'ul' => [], 'ol' => [], 'li' => [],
        'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'blockquote' => [], 'figure' => [], 'figcaption' => [],
        'img' => ['src', 'alt', 'width', 'height'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'],
    ];
    static $drop = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'noscript'];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return '';
    }
    _clean_node($body, $allowed, $drop);

    $out = '';
    foreach (iterator_to_array($body->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

/** @param array<string,array<int,string>> $allowed @param array<int,string> $drop */
function _clean_node(DOMNode $node, array $allowed, array $drop): void
{
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);
            _clean_node($child, $allowed, $drop);
            if (!isset($allowed[$tag])) {
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($child);
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, $allowed[$tag], true)) {
                    $child->removeAttribute($attr->name);
                } elseif (($name === 'href' || $name === 'src')
                    && preg_match('#^\s*(javascript|data|vbscript):#i', (string) $attr->value)) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        } elseif (!($child instanceof DOMText)) {
            $node->removeChild($child);
        }
    }
}
