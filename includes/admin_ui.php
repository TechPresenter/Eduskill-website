<?php
/**
 * =============================================================================
 *  admin_ui.php — the markup half of the admin design system.
 * -----------------------------------------------------------------------------
 *  assets/css/admin-ds.css declares the class API for the components the panel
 *  did not have (empty / error state, skeleton, toast, drawer, breadcrumb, bulk
 *  bar, KPI card + sparkline, timeline, confirm dialog). This file is the ONLY
 *  place that emits that markup, so all 137 admin screens get the same shape,
 *  the same ARIA wiring and the same escaping instead of 137 near-misses.
 *
 *  RULES THIS FILE ENFORCES (from the brand direction, encoded once here):
 *    · Every caller-supplied string goes through e(). The one exception is the
 *      drawer body/footer, whose parameters are named *Html and documented as
 *      "caller must have escaped this" — everything else is escaped for you.
 *    · Status is never colour alone: each state carries a glyph (inline SVG, so
 *      it survives with JavaScript off) AND a text label, sometimes .sr-only.
 *    · Legal radii/spacing/elevation come from the stylesheet — no inline
 *      styles are emitted, and admin_ui_attrs() refuses style= and on*=.
 *    · Interactive elements always have an accessible name; the drawer is a
 *      labelled aria-modal region, the confirm dialog is a real <dialog>.
 *
 *  CONVENTIONS
 *    · Every function RETURNS html; nothing echoes. Use <?= admin_x() ?>.
 *    · Behaviour is NOT here. assets/js/admin-ui.js is the behaviour half of the
 *      same system (window.AdminUI), and admin/partials/head.php loads it on
 *      every admin page. This file emits exactly the hooks that file binds, so
 *      the two halves cannot drift:
 *
 *        drawer   [data-drawer-open="id"] · [data-drawer-close] ·
 *                 [data-drawer-autofocus] · the shared .drawer-scrim and the
 *                 aria-modal/focus/inert handling are the JS's, not ours
 *        confirm  <dialog class="ds-confirm" id> holding [data-confirm-no] and
 *                 [data-confirm-yes]; opened from a trigger that carries
 *                 [data-confirm] + [data-confirm-dialog="id"] — see
 *                 admin_confirm_trigger()
 *        bulk     the posting form carries [data-bulk-scope] (+ data-bulk-name),
 *                 rows carry [data-bulk-item], the header box [data-bulk-all],
 *                 and the bar is .bulk-bar[hidden] with .bulk-count > .n,
 *                 [data-bulk-clear] and a [data-bulk-status] announcer that must
 *                 live OUTSIDE the hidden bar
 *        toast    AdminUI.toast(msg, tone, title) builds the same DOM this file
 *                 renders, into the first .toast-stack on the page; a toast
 *                 rendered HERE is adopted by initDeclarative() on load, which is
 *                 what gives it a working close button and a timer
 *
 *      Nothing here emits a <script>: one behaviour file, bound once, beats a
 *      snippet repeated by 137 screens.
 *
 *  Load with:  require_once BASE_PATH . '/includes/admin_ui.php';
 *  (admin/partials/head.php already does this for every admin page.)
 * =============================================================================
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/bootstrap.php';
}

/* =============================================================================
 |  INTERNALS — shared by the components below. Prefixed admin_ui_ so the public
 |  component API (admin_empty_state, admin_kpi, …) stays obvious.
 |============================================================================*/

/**
 * Render an attribute list safely.
 *
 * Values are escaped and names are ALLOW-listed. A blocklist was tried first and
 * leaked: it stopped on-handlers, style, href, src, action and formaction but let
 * through `xlink:href` (live as a javascript: sink inside inline <svg> in Gecko),
 * plus srcset, poster, data, ping, formmethod and formtarget. An allowlist cannot
 * have that shape of hole — anything not named here is dropped, and a URL-valued
 * attribute is never on the list because URLs must go through admin_ui_href().
 * TRUE renders a boolean attribute, FALSE/NULL renders nothing.
 *
 *   admin_ui_attrs(['data-id' => $row['id'], 'disabled' => true])
 */
function admin_ui_attrs(array $attrs): string
{
    /* data-* and aria-* are open (they are inert by definition); everything else
       must be listed. Add to this list when a component needs an attribute —
       never add one that a browser resolves as a URL. */
    static $allowed = [
        'id', 'class', 'title', 'role', 'type', 'name', 'value', 'for', 'form',
        'target', 'rel', 'download', 'tabindex', 'hidden', 'inert', 'disabled',
        'readonly', 'required', 'checked', 'selected', 'multiple', 'open',
        'autofocus', 'autocomplete', 'placeholder', 'inputmode', 'enterkeyhint',
        'maxlength', 'minlength', 'min', 'max', 'step', 'pattern', 'list',
        'rows', 'cols', 'size', 'wrap', 'colspan', 'rowspan', 'headers', 'scope',
        'alt', 'width', 'height', 'loading', 'decoding', 'lang', 'dir',
        'translate', 'spellcheck', 'draggable', 'accept', 'accept-charset',
        'method', 'enctype', 'novalidate', 'datetime', 'label', 'reversed',
        'start', 'span', 'default', 'controls', 'muted', 'playsinline',
    ];
    $out = '';
    foreach ($attrs as $name => $value) {
        $name = strtolower(trim((string) $name));
        if ($name === '' || !preg_match('/^[a-z][a-z0-9:_.-]*$/', $name)) {
            continue;
        }
        if (!str_starts_with($name, 'data-') && !str_starts_with($name, 'aria-')
            && !in_array($name, $allowed, true)) {
            continue;
        }
        // Belt and braces: an "on…" name can never be reached via the two open
        // prefixes, but a future addition to $allowed must not be able to.
        if (str_starts_with($name, 'on')) {
            continue;
        }
        if ($value === true) {
            $out .= ' ' . $name;
            continue;
        }
        if ($value === false || $value === null) {
            continue;
        }
        $out .= ' ' . $name . '="' . e((string) $value) . '"';
    }
    return $out;
}

/**
 * Normalise a caller-supplied link target. Fragments and query-only links pass
 * through; everything else goes through safe_href(), so javascript: and data:
 * URLs can never reach an href in the admin panel. Returns '' when there is no
 * usable link (the caller then renders a <button> instead of an <a>).
 *
 * PROTOCOL-RELATIVE FORMS ARE REJECTED HERE, not left to safe_href(). That
 * helper guards `//evil.com` with `!str_starts_with($url, '//')`, which misses
 * the backslash spellings — and browsers normalise `/\evil.com`, `/\/evil.com`
 * and `\\evil.com` to `//evil.com`, i.e. an off-site jump out of the admin panel
 * carrying the admin Referer. Any second character that is a slash or backslash
 * (in either order) is therefore refused before safe_href() sees it.
 */
function admin_ui_href(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    // Strip the C0 controls used to smuggle a scheme past a regex
    // ("java\tscript:", "java\nscript:"). Browsers strip these too, so removing
    // them here means we validate the string the browser will actually resolve.
    // Spaces and non-ASCII are left alone: they can be legitimate query text.
    $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url) ?? '';
    if ($url === '') {
        return '';
    }
    if (preg_match('#^[/\\\\][/\\\\]#', $url)) {
        return '';                     // //host, /\host, \/host, \\host
    }
    if ($url[0] === '#' || $url[0] === '?') {
        return $url;
    }
    if ($url[0] === '\\') {
        return '';
    }
    if (preg_match('#^(mailto|tel):[^\s"<>]+$#i', $url)) {
        return $url;
    }
    $safe = function_exists('safe_href') ? safe_href($url) : '';
    if ($safe !== '') {
        return $safe;
    }
    // Relative admin paths ("members?status=new") are legitimate and safe_href()
    // rejects them, so allow a conservative relative form and nothing else.
    return preg_match('#^[A-Za-z0-9][A-Za-z0-9._/-]*(\?[^"<>\s]*)?$#', $url) ? $url : '';
}

/**
 * Sanitise an id so it is safe in an attribute, in aria-labelledby, and in the
 * id selectors admin-ui.js resolves. Falls back to a unique id so aria wiring is
 * never left dangling.
 *
 * STRIPPING ALONE IS MANY-TO-ONE, and that is a correctness problem rather than
 * a cosmetic one: 'a b' and 'ab' both became 'ab', so a page deriving ids from
 * data ("archive 1", "archive1") could point a confirm TRIGGER at a different
 * dialog than the one whose consequence it renders — the admin reads one
 * statement and a different gesture is replayed on "yes". Two distinct inputs
 * therefore never resolve to the same id: the second one to ask gets a `-2`
 * suffix. Asking twice for the SAME raw string still returns the same id, so a
 * dialog and its trigger continue to agree.
 */
function admin_ui_id(string $raw, string $prefix = 'ds'): string
{
    static $seq = 0;
    static $issued = [];          // normalised id => the raw string that owns it

    $id = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';
    if ($id === '' || !preg_match('/^[A-Za-z]/', $id)) {
        $id = $prefix . '-' . ($id !== '' ? $id : (string) (++$seq));
    }

    $owner = $prefix . "\0" . $raw;
    if (($issued[$id] ?? $owner) !== $owner) {
        $n = 2;
        while (isset($issued[$id . '-' . $n]) && $issued[$id . '-' . $n] !== $owner) {
            $n++;
        }
        $id .= '-' . $n;
    }
    $issued[$id] = $owner;
    return $id;
}

/**
 * Canonical tone vocabulary. Callers say 'success' / 'danger' / 'pending' /
 * whatever their table column holds; the components only ever see ok | warn |
 * error | info, which is what the stylesheet defines.
 */
function admin_ui_tone(string $tone): string
{
    $t = strtolower(trim($tone));
    $map = [
        'ok' => 'ok', 'success' => 'ok', 'good' => 'ok', 'done' => 'ok',
        'approved' => 'ok', 'active' => 'ok', 'paid' => 'ok', 'green' => 'ok',
        'warn' => 'warn', 'warning' => 'warn', 'pending' => 'warn',
        'review' => 'warn', 'amber' => 'warn', 'orange' => 'warn', 'due' => 'warn',
        'error' => 'error', 'err' => 'error', 'danger' => 'error', 'fail' => 'error',
        'failed' => 'error', 'rejected' => 'error', 'red' => 'error', 'destructive' => 'error',
    ];
    return $map[$t] ?? 'info';
}

/**
 * The text half of "never colour alone" — the word that accompanies the glyph.
 * Used as .sr-only text where the design has no room for a visible label.
 */
function admin_ui_tone_label(string $tone): string
{
    return [
        'ok'    => 'Success',
        'warn'  => 'Warning',
        'error' => 'Error',
        'info'  => 'Note',
    ][admin_ui_tone($tone)];
}

/**
 * Inline status/utility glyph as an SVG, in the lucide drawing style but with
 * no JavaScript dependency — a status icon that only appears once lucide.js has
 * run is a status conveyed by colour alone until then.
 *
 *   admin_ui_glyph('warn', 't-ico')
 */
function admin_ui_glyph(string $name, string $class = '', int $size = 0): string
{
    $paths = [
        'ok'      => '<circle cx="12" cy="12" r="9"/><path d="m8.4 12.3 2.5 2.5 4.7-5.1"/>',
        'warn'    => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9.5v4"/><path d="M12 17.2h.01"/>',
        'error'   => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.8h.01"/>',
        'up'      => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'down'    => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'flat'    => '<path d="M5 12h14"/>',
        'close'   => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
    ];
    $key = isset($paths[$name]) ? $name : admin_ui_tone($name);
    $dim = $size > 0 ? ' width="' . $size . '" height="' . $size . '"' : '';
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor"'
         . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $dim
         . ' aria-hidden="true" focusable="false">' . $paths[$key] . '</svg>';
}

/**
 * Build the data-confirm-* attribute map admin-ui.js reads from a trigger.
 * Shared by admin_confirm_trigger() (which renders it) and admin_ui_actions()
 * (which merges it into a button), so the attribute names live in one place.
 */
function admin_ui_confirm_map(array $o): array
{
    $dialog = trim((string) ($o['dialog'] ?? ''));
    $text   = trim((string) ($o['text'] ?? ''));

    // [data-confirm] is the hook itself; "1" means "ask, using the dialog/defaults".
    $attrs = ['data-confirm' => $text !== '' ? $text : '1'];
    if ($dialog !== '') {
        $attrs['data-confirm-dialog'] = admin_ui_id($dialog, 'confirm');
    }
    foreach (['title' => 'data-confirm-title', 'affected' => 'data-confirm-affected',
              'ok' => 'data-confirm-ok', 'cancel' => 'data-confirm-cancel',
              'tone' => 'data-confirm-tone'] as $key => $attr) {
        $v = trim((string) ($o[$key] ?? ''));
        if ($v !== '') {
            $attrs[$attr] = $v;
        }
    }
    return $attrs;
}

/**
 * Format a KPI-ish number without mangling strings the caller pre-formatted.
 *
 * !! RETURNS AN UNESCAPED STRING. It is the one internal that hands a caller
 * !! value straight back, so every call site must wrap it in e() — all of them
 * !! in this file do. It is not named *_raw only because it is internal; treat it
 * !! as if it were.
 *
 * Non-scalars are dropped rather than cast: number_format() on an array raised
 * "Array to string conversion" and printed the literal word "Array" into
 * .kpi-value, and a stray object would have thrown.
 */
function admin_ui_num($value): string
{
    if (is_int($value)) {
        return number_format($value);
    }
    if (is_float($value)) {
        if (!is_finite($value)) {
            return '';
        }
        $s = number_format($value, 2);
        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }
    if ($value === null || is_bool($value) || !is_scalar($value)) {
        return '';
    }
    return (string) $value;
}

/**
 * Render a row of action buttons/links from a spec array. Two shapes accepted:
 *
 *   ['Add member' => admin_url('members/create')]                  // label => url
 *   [['label' => 'Retry', 'icon' => 'refresh-cw', 'variant' => 'secondary',
 *     'url' => null, 'attrs' => ['data-drawer-open' => 'd1']]]
 *
 * A spec with a url renders <a>; without one it renders <button type="button">
 * (or type=submit when 'submit' => true / 'form' => 'formId' is given), because
 * an <a href="#"> that only works with JS has no accessible meaning.
 *
 * Other keys: destructive (danger fill), small (btn-sm), new_tab, disabled,
 * name/value (posted by a submit), class (overrides the variant), and
 * confirm (string or admin_confirm_trigger() options — the action then asks
 * before it runs).
 */
function admin_ui_actions(array $actions, string $wrapClass = 'es-actions', string $defaultVariant = 'primary'): string
{
    $html = '';
    $first = true;
    foreach ($actions as $key => $spec) {
        if (is_string($spec) || is_numeric($spec)) {
            $spec = ['label' => (string) $key, 'url' => (string) $spec];
        }
        if (!is_array($spec)) {
            continue;
        }
        $label = trim((string) ($spec['label'] ?? $spec['text'] ?? (is_string($key) ? $key : '')));
        if ($label === '') {
            continue;
        }
        $variant = (string) ($spec['variant'] ?? ($first ? $defaultVariant : 'secondary'));
        if (!empty($spec['destructive'])) {
            $variant = 'danger';
        }
        $cls = trim((string) ($spec['class'] ?? ('btn btn-' . preg_replace('/[^a-z-]/', '', strtolower($variant)))));
        if (!empty($spec['small'])) {
            $cls .= ' btn-sm';
        }
        $ico  = (string) ($spec['icon'] ?? '');
        $icon = $ico !== '' ? lucide($ico) . ' ' : '';
        $attrs = is_array($spec['attrs'] ?? null) ? $spec['attrs'] : [];

        /* 'confirm' => 'Delete {count} rows?'  or the full admin_confirm_trigger()
           option array. Rendered as attributes so admin-ui.js can intercept the
           click; the action itself is unchanged. */
        $confirm = $spec['confirm'] ?? null;
        if ($confirm !== null && $confirm !== false && $confirm !== '') {
            $co = is_array($confirm) ? $confirm : ['text' => (string) $confirm];
            if (!isset($co['tone']) && empty($spec['destructive'])) {
                $co['tone'] = 'neutral';
            }
            $attrs = array_merge($attrs, admin_ui_confirm_map($co));
        }

        $href = admin_ui_href((string) ($spec['url'] ?? $spec['href'] ?? ''));
        if ($href !== '') {
            $extra = '';
            if (!empty($spec['new_tab'])) {
                $extra = ' target="_blank" rel="noopener noreferrer"';
            }
            $html .= '<a class="' . e($cls) . '" href="' . e($href) . '"' . $extra
                   . admin_ui_attrs($attrs) . '>' . $icon . e($label) . '</a>';
        } else {
            $type = (!empty($spec['submit']) || !empty($spec['form'])) ? 'submit' : 'button';
            $more = [];
            if (!empty($spec['form']))  { $more['form']  = (string) $spec['form']; }
            if (isset($spec['name']))   { $more['name']  = (string) $spec['name']; }
            if (isset($spec['value']))  { $more['value'] = (string) $spec['value']; }
            if (!empty($spec['disabled'])) { $more['disabled'] = true; }
            $html .= '<button type="' . $type . '" class="' . e($cls) . '"'
                   . admin_ui_attrs($more + $attrs) . '>' . $icon . e($label) . '</button>';
        }
        $first = false;
    }
    if ($html === '') {
        return '';
    }
    return $wrapClass === ''
        ? $html
        : '<div class="' . e($wrapClass) . '">' . $html . '</div>';
}

/* =============================================================================
 |  1. EMPTY STATE
 |============================================================================*/

/**
 * "There is nothing here yet" — the friendly end of a list, with the action
 * that would create the first row. Use it instead of an empty <tbody>.
 *
 * $actions accepts either ['Label' => url] or the full admin_ui_actions() spec.
 *
 *   echo admin_empty_state('No members yet', 'Approved members appear here.',
 *        ['Add member' => admin_url('members-create')], 'users');
 */
function admin_empty_state(string $title, string $text = '', array $actions = [], string $icon = 'inbox'): string
{
    $html  = '<div class="empty-state">';
    $html .= '<div class="icon" aria-hidden="true">' . lucide($icon !== '' ? $icon : 'inbox') . '</div>';
    $html .= '<p class="es-title">' . e($title !== '' ? $title : 'Nothing here yet') . '</p>';
    if ($text !== '') {
        $html .= '<p class="es-text">' . e($text) . '</p>';
    }
    $html .= admin_ui_actions($actions);
    return $html . '</div>';
}

/* =============================================================================
 |  2. ERROR STATE
 |============================================================================*/

/**
 * A panel-sized failure: the query threw, the API is down, the report could not
 * be built. Announced with role="alert", and carries glyph + "Error" text so the
 * red is a third cue rather than the only one.
 *
 *   echo admin_error_state('Could not load donations',
 *        'The payment gateway did not respond.', ['Try again' => admin_url('donations')]);
 */
function admin_error_state(string $title, string $text = '', array $actions = []): string
{
    $html  = '<div class="error-state" role="alert">';
    $html .= admin_ui_glyph('error', 'icon');
    $html .= '<p class="es-title"><span class="sr-only">Error: </span>'
           . e($title !== '' ? $title : 'Something went wrong') . '</p>';
    if ($text !== '') {
        $html .= '<p class="es-text">' . e($text) . '</p>';
    }
    $html .= admin_ui_actions($actions, 'es-actions', 'secondary');
    return $html . '</div>';
}

/* =============================================================================
 |  3. SKELETONS
 |============================================================================*/

/**
 * Table placeholder for a list that is still loading (or being fetched into by
 * JS). Sized in rows x columns so it occupies roughly the height the real table
 * will, which is what stops the layout jumping.
 *
 * Announced once as "Loading" — the bars themselves are aria-hidden, so a screen
 * reader is not read 40 meaningless cells.
 *
 *   echo admin_skeleton_rows(8, 5);
 */
function admin_skeleton_rows(int $rows, int $cols): string
{
    $rows = max(1, min(50, $rows));
    $cols = max(1, min(24, $cols));
    $row  = '<div class="sk-row" aria-hidden="true">' . str_repeat('<span class="skeleton sk-line"></span>', $cols) . '</div>';
    return '<div data-ds-skeleton="table" role="status" aria-busy="true" aria-label="Loading rows">'
         . str_repeat($row, $rows)
         . '</div>';
}

/**
 * One or more standalone placeholders.
 *   $kind: 'line' | 'short' | 'mid' | 'block' | 'circle'  (unknown → 'line')
 *
 *   echo admin_skeleton('block');            // a card-sized box
 *   echo admin_skeleton('line', 3);          // three text lines
 */
function admin_skeleton(string $kind, int $count = 1): string
{
    $count = max(1, min(40, $count));
    $map = [
        'line'   => 'skeleton sk-line',
        'short'  => 'skeleton sk-line is-short',
        'mid'    => 'skeleton sk-line is-mid',
        'block'  => 'skeleton sk-block',
        'circle' => 'skeleton sk-circle',
    ];
    $cls = $map[strtolower(trim($kind))] ?? $map['line'];
    return '<div data-ds-skeleton="' . e(strtolower(trim($kind)) ?: 'line') . '" role="status" aria-busy="true" aria-label="Loading">'
         . str_repeat('<div class="' . $cls . '" aria-hidden="true"></div>', $count)
         . '</div>';
}

/* =============================================================================
 |  4. TOAST
 |============================================================================*/

/**
 * A single non-blocking confirmation, rendered SERVER-side. $tone is
 * ok | warn | error (info also accepted) — anything a status column holds
 * ('success', 'failed', 'pending') is normalised. The tone shows as a coloured
 * rule, a glyph shape AND a text label, so it is never colour alone.
 *
 * ok/info announce politely (role=status); warn/error assertively (role=alert).
 *
 * This emits exactly the DOM that AdminUI.toast() builds at runtime, so the two
 * are indistinguishable on screen. Prefer the runtime call for anything that
 * happens after load; use this when the message must exist in the HTML (a
 * redirect-and-flash that has to survive with JS off). A server-rendered toast
 * has no timer of its own — it must live inside a .toast-stack, so pass it
 * through admin_toast_stack().
 *
 *   echo admin_toast('Member approved.', 'ok', 'Saved');
 */
function admin_toast(string $message, string $tone = 'ok', string $title = ''): string
{
    $t     = admin_ui_tone($tone);
    $mod   = $t === 'info' ? '' : ' is-' . $t;
    $alert = ($t === 'error');

    $html  = '<div class="toast' . $mod . '" data-ds-toast'
           . ' role="' . ($alert ? 'alert' : 'status') . '" aria-live="' . ($alert ? 'assertive' : 'polite') . '"'
           . ' aria-atomic="true">';
    $html .= '<span class="t-ico">' . admin_ui_glyph($t) . '</span>';
    $html .= '<div class="t-body">';
    if ($title !== '') {
        $html .= '<strong class="t-title">' . e($title) . '</strong>';
    } else {
        // No visible title: the tone still has to reach assistive tech as a word.
        $html .= '<span class="sr-only">' . e(admin_ui_tone_label($t)) . ': </span>';
    }
    $html .= e($message) . '</div>';
    $html .= '<button type="button" class="t-close" aria-label="Dismiss notification">'
           . admin_ui_glyph('close', '', 14) . '</button>';

    return $html . '</div>';
}

/**
 * The positioned container for one or more server-rendered toasts. Accepts
 * every shape a caller is likely to already have:
 *
 *   [['message' => 'Saved.', 'tone' => 'ok', 'title' => 'Member #12']]
 *   [['Saved.', 'ok', 'Member #12']]                    // positional
 *   ['success' => 'Saved.']                             // tone => message
 *   ['success' => ['Saved.', 'Email queued.']]           // get_flashes() as-is
 *
 * data-ds-live is load-bearing: admin-ui.js keeps [data-ds-live] out of the
 * inert-ed background, so a toast stays announceable while a drawer or confirm
 * dialog is open. admin-ui.js also reuses the FIRST .toast-stack it finds, so a
 * page must emit at most one — the admin shell already mounts an empty one in
 * admin/partials/foot.php, and this container simply takes its place higher up
 * the document when a page has flashes to render.
 *
 * THE CONTAINER IS NOT A LIVE REGION. Each toast already is one (role=alert or
 * role=status with a matching aria-live), and nesting an assertive region inside
 * a polite one is undefined: in practice either the message is announced twice
 * or the ancestor's politeness wins and every error is silently downgraded to
 * polite. One owner — the toast — so the stack carries only role="region".
 *
 *   echo admin_toast_stack([['message' => 'Saved.', 'tone' => 'ok']]);
 */
/**
 * Has a .toast-stack already been printed into this document?
 *
 * admin/partials/foot.php mounts an empty stack for AdminUI.toast() to fill, and
 * admin-ui.js reuses the FIRST stack it finds — so two stacks means runtime
 * toasts land in one and the shell's mount stays empty forever, with two
 * identically-labelled "Notifications" landmarks to boot. foot.php asks this
 * before mounting, so exactly one stack exists whether or not the page had
 * flashes to render.
 */
function admin_toast_stack_rendered(bool $markRendered = false): bool
{
    static $rendered = false;
    if ($markRendered) {
        $rendered = true;
    }
    return $rendered;
}

function admin_toast_stack(array $toasts): string
{
    $inner  = '';
    foreach ($toasts as $key => $item) {
        if (is_string($item)) {                       // ['ok' => 'Saved.'] or ['Saved.']
            $inner .= admin_toast($item, is_string($key) ? $key : 'ok');
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        /* A flash bag: 'success' => ['msg', 'msg']. A string outer key names the
           tone, so a plain list under it is a list of MESSAGES, not [msg, tone]. */
        if (is_string($key) && $item !== [] && array_is_list($item)
            && count(array_filter($item, 'is_string')) === count($item)) {
            foreach ($item as $msg) {
                $inner .= admin_toast((string) $msg, $key);
            }
            continue;
        }
        $inner .= admin_toast(
            (string) ($item['message'] ?? $item[0] ?? ''),
            (string) ($item['tone'] ?? $item[1] ?? 'ok'),
            (string) ($item['title'] ?? $item[2] ?? '')
        );
    }
    if (trim($inner) === '') {
        return '';
    }
    admin_toast_stack_rendered(true);
    return '<div class="toast-stack" id="toastStack" role="region" aria-label="Notifications"'
         . ' data-ds-live>' . $inner . '</div>';
}

/* =============================================================================
 |  5. DRAWER
 |============================================================================*/

/**
 * Right-hand detail panel (a bottom sheet under 640px) for a record preview,
 * an activity log, a transaction — anything that should not cost a page load.
 *
 * Ships closed and sealed: a .ds-drawer is only translated off-screen, so
 * aria-hidden + inert keep its controls out of the tab order until
 * [data-drawer-open="id"] opens it. admin-ui.js owns the rest — the shared
 * .drawer-scrim (it creates one per page), scroll lock, focus trap, Escape, and
 * re-sealing after the transition — which is why no scrim is emitted here.
 *
 * !! $bodyHtml and $footHtml are MARKUP. They are printed verbatim — the CALLER
 * !! must have escaped every value inside them (use e(), or rich_text() for a
 * !! CMS field). Every other parameter of every function in this file is escaped
 * !! for you; these two are the deliberate exception, which is why they are
 * !! named *Html.
 *
 *   echo admin_drawer('memberDrawer', 'Member details',
 *        '<p>' . e($m['name']) . '</p>',
 *        '<button class="btn btn-secondary" data-drawer-close>Close</button>');
 *   // opener:  <button class="btn btn-sm" data-drawer-open="memberDrawer">View</button>
 */
function admin_drawer(string $id, string $title, string $bodyHtml, string $footHtml = ''): string
{
    $id    = admin_ui_id($id, 'drawer');
    $label = $id . '-title';

    $html  = '<aside class="ds-drawer" id="' . e($id) . '" role="dialog" aria-modal="true"'
           . ' aria-labelledby="' . e($label) . '" aria-hidden="true" inert tabindex="-1">';
    $html .= '<div class="ds-drawer-head">';
    $html .= '<h2 class="ds-drawer-title" id="' . e($label) . '">' . e($title !== '' ? $title : 'Details') . '</h2>';
    $html .= '<button type="button" class="icon-btn" data-drawer-close aria-label="Close panel">'
           . admin_ui_glyph('close', '', 18) . '</button>';
    $html .= '</div>';
    $html .= '<div class="ds-drawer-body">' . $bodyHtml . '</div>';
    if (trim($footHtml) !== '') {
        $html .= '<div class="ds-drawer-foot">' . $footHtml . '</div>';
    }
    return $html . '</aside>';
}

/* =============================================================================
 |  6. BREADCRUMBS
 |============================================================================*/

/**
 * Where am I. The last entry is always the current page: rendered without a
 * link and marked aria-current="page".
 *
 * $trail is ['Label' => url, …, 'Current page' => ''] (an entry with an empty
 * url is text), or a list of ['label' => …, 'url' => …].
 *
 *   echo admin_crumbs(['Dashboard' => admin_url('dashboard'),
 *                      'Members'   => admin_url('members'),
 *                      $member['name'] => '']);
 */
function admin_crumbs(array $trail): string
{
    $items = [];
    foreach ($trail as $key => $value) {
        if (is_array($value)) {
            $label = trim((string) ($value['label'] ?? (is_string($key) ? $key : '')));
            $url   = (string) ($value['url'] ?? '');
        } else {
            $label = is_string($key) ? trim($key) : trim((string) $value);
            $url   = is_string($key) ? (string) $value : '';
        }
        if ($label !== '') {
            $items[] = [$label, admin_ui_href($url)];
        }
    }
    if (!$items) {
        return '';
    }

    $last = count($items) - 1;
    $html = '<nav aria-label="Breadcrumb"><ol class="ds-crumbs">';
    foreach ($items as $i => [$label, $url]) {
        $current = ($i === $last);
        $html .= '<li' . ($current ? ' aria-current="page"' : '') . '>';
        $html .= ($url !== '' && !$current)
            ? '<a href="' . e($url) . '">' . e($label) . '</a>'
            : '<span>' . e($label) . '</span>';
        $html .= '</li>';
    }
    return $html . '</ol></nav>';
}

/* =============================================================================
 |  7. BULK ACTION BAR
 |============================================================================*/

/**
 * The sticky bar that appears once rows are ticked. Hidden until then; the count
 * is written into .bulk-count > .n and announced politely as it changes.
 *
 * The table it serves supplies the other half of admin-ui.js's contract:
 *   posting form     <form method="post" data-bulk-scope data-bulk-name="ids[]">
 *   header checkbox  <input type="checkbox" data-bulk-all aria-label="Select all rows">
 *   row checkbox     <input type="checkbox" name="ids[]" value="7" data-bulk-item
 *                           aria-label="Select Anita Rao">
 * With that in place the JS handles select-all, shift-range, the count, the
 * hidden attribute and mirroring ids into the form on submit.
 *
 * GIVE EVERY ROW BOX A NAME. A bare checkbox in a table cell announces as
 * "checkbox, unchecked" with no indication of which record — 137 screens copying
 * the markup above would have shipped a column of those. admin-ui.js derives a
 * fallback name from the row's first text cell, but the row knows its own subject
 * and the explicit label is always better.
 *
 * CLEARING: the bar renders a "Clear selection" button ([data-bulk-clear]).
 * Unticking the header box is NOT a way to clear a partial selection — that box
 * is `indeterminate`, and activating an indeterminate checkbox sets checked=true
 * in every engine, so the gesture selects everything and has to be repeated.
 *
 * $formId makes the buttons submit that form by id, for a bar rendered outside
 * it. An action may carry 'confirm' (see admin_confirm_trigger()); inside
 * .bulk-actions admin-ui.js substitutes {count} with the number selected.
 *
 *   echo admin_bulk_bar([
 *       ['label' => 'Approve', 'name' => 'bulk', 'value' => 'approve', 'variant' => 'success'],
 *       ['label' => 'Delete',  'name' => 'bulk', 'value' => 'delete', 'destructive' => true,
 *        'confirm' => ['text' => 'Delete {count} selected members?', 'ok' => 'Delete']],
 *   ], 'membersForm');
 */
function admin_bulk_bar(array $actions, string $formId = ''): string
{
    $form = $formId !== '' ? admin_ui_id($formId, 'form') : '';

    // Every action submits the owning form unless it says otherwise.
    $specs = [];
    foreach ($actions as $key => $spec) {
        if (is_string($spec) || is_numeric($spec)) {
            $spec = ['label' => (string) $key, 'url' => (string) $spec];
        }
        if (!is_array($spec)) {
            continue;
        }
        $spec['small'] = $spec['small'] ?? true;
        if (empty($spec['url']) && empty($spec['href'])) {
            // A bulk action acts on the selection, so it posts: type=submit,
            // whether the bar sits inside the form or targets it by id.
            $spec['submit'] = $spec['submit'] ?? true;
            if ($form !== '' && empty($spec['form'])) {
                $spec['form'] = $form;
            }
        }
        $specs[] = $spec;
    }

    /* The announcer sits OUTSIDE the bar, and that is the whole point. The bar is
       [hidden] until something is ticked, and a live-region mutation inside a
       display:none subtree is not announced — so writing the count first and
       un-hiding second lost the 0 → 1 transition entirely (1 → 2 and 2 → 3
       announced fine). A permanent region outside the toggled element cannot have
       that bug, on this bar or on a page that renders its own. */
    $html  = '<span class="sr-only" data-bulk-status role="status" aria-live="polite"></span>';
    $html .= '<div class="bulk-bar" hidden role="region" aria-label="Bulk actions">';
    /* aria-atomic: only .n changes, so without it the announcement is the bare
       number "3" with no noun. */
    $html .= '<span class="bulk-count" aria-live="polite" aria-atomic="true">'
           . '<span class="n">0</span> selected</span>';
    $html .= '<button type="button" class="btn btn-ghost btn-sm" data-bulk-clear>Clear selection</button>';
    $html .= admin_ui_actions($specs, 'bulk-actions', 'secondary');
    return $html . '</div>';
}

/* =============================================================================
 |  8. KPI CARD  (+ 9. SPARKLINE)
 |============================================================================*/

/**
 * Dashboard figure with an optional trend. Keys:
 *   label   string   what it counts                      (required in practice)
 *   value   mixed    int/float are formatted, strings printed as given
 *   icon    string   lucide slug                         (default 'activity')
 *   delta   mixed    12.4 → "+12.4%", or a ready string  (optional)
 *   dir     string   up|down|flat — overrides the sign, for "lower is better"
 *   unit    string   suffix for a numeric delta           (default '%')
 *   since   string   "vs last month"
 *   spark   array    numbers, oldest first
 *   state   string   ''|'loading'|'error'
 *   error   string   message shown in the error state
 *
 * .is-up is green and .is-down is red, so for a metric where a fall is good
 * (refunds, bounce rate) pass dir explicitly.
 *
 *   echo admin_kpi(['label' => 'Donations', 'value' => 428500, 'icon' => 'coins',
 *                   'delta' => 12.4, 'since' => 'vs last month',
 *                   'spark' => [4,9,7,12,11,18]]);
 */
function admin_kpi(array $kpi): string
{
    $state   = strtolower(trim((string) ($kpi['state'] ?? '')));
    // 'loading' wins over 'error': a card cannot be both, and a half-arrived
    // figure must not be reported as a failure.
    $loading = ($state === 'loading' || !empty($kpi['loading']));
    $failed  = !$loading
        && ($state === 'error' || $state === 'err' || $state === 'failed' || !empty($kpi['error']));

    $cls = 'kpi' . ($loading ? ' is-loading' : '') . ($failed ? ' is-error' : '');
    $html  = '<div class="' . $cls . '"' . ($loading ? ' aria-busy="true"' : '') . '>';

    /* top row — icon + label */
    $html .= '<div class="kpi-top">';
    $html .= '<span class="kpi-ico" aria-hidden="true">' . lucide((string) ($kpi['icon'] ?? 'activity')) . '</span>';
    $html .= '<span class="kpi-label">' . e((string) ($kpi['label'] ?? 'Metric')) . '</span>';
    $html .= '</div>';

    /* value — or the state that replaced it */
    if ($loading) {
        // .is-loading hides .kpi-value visually (and from the a11y tree), so the
        // waiting state has to be stated separately.
        $html .= '<div class="kpi-value">&mdash;</div>';
        $html .= '<span class="sr-only">Loading this figure&hellip;</span>';
    } elseif ($failed) {
        // 'error' => true is a flag, not a message — only a string is shown.
        $msg = is_string($kpi['error'] ?? null) ? trim($kpi['error']) : '';
        $html .= '<div class="kpi-value">' . admin_ui_glyph('error', '', 16)
               . ' <span class="sr-only">Error: </span>'
               . e($msg !== '' ? $msg : 'Unavailable') . '</div>';
    } else {
        $html .= '<div class="kpi-value">' . e(admin_ui_num($kpi['value'] ?? 0)) . '</div>';
    }

    /* delta + period */
    $delta = $kpi['delta'] ?? null;
    $since = trim((string) ($kpi['since'] ?? ''));
    if (!$loading && !$failed && ($delta !== null && $delta !== '' || $since !== '')) {
        $row = '';
        if ($delta !== null && $delta !== '') {
            $dir = strtolower(trim((string) ($kpi['dir'] ?? '')));
            if (!in_array($dir, ['up', 'down', 'flat'], true)) {
                if (is_int($delta) || is_float($delta)) {
                    $dir = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
                } else {
                    $s   = ltrim((string) $delta);
                    $dir = str_starts_with($s, '-') ? 'down' : (str_starts_with($s, '+') ? 'up' : 'flat');
                }
            }
            if (is_int($delta) || is_float($delta)) {
                $unit  = (string) ($kpi['unit'] ?? '%');
                $shown = ($delta > 0 ? '+' : '') . admin_ui_num($delta) . $unit;
            } else {
                $shown = (string) $delta;
            }
            $word = ['up' => 'increase', 'down' => 'decrease', 'flat' => 'no change'][$dir];
            $row .= '<span class="kpi-delta is-' . $dir . '">' . admin_ui_glyph($dir)
                  . '<span class="sr-only">' . $word . ' </span>' . e($shown) . '</span>';
        }
        if ($since !== '') {
            $row .= ' <span class="kpi-since">' . e($since) . '</span>';
        }
        $html .= '<div>' . $row . '</div>';
    }

    /* sparkline — an empty <svg> while loading keeps the card the same height */
    $spark = is_array($kpi['spark'] ?? null) ? $kpi['spark'] : [];
    if ($loading) {
        $html .= '<svg class="kpi-spark" aria-hidden="true" focusable="false"></svg>';
    } elseif (!$failed && $spark) {
        $html .= admin_sparkline($spark);
    }

    return $html . '</div>';
}

/**
 * Wrap KPI cards in the responsive grid so the container lives in one place.
 *
 *   echo admin_kpi_grid([['label' => 'Members', 'value' => 1280], …]);
 */
function admin_kpi_grid(array $kpis): string
{
    $inner = '';
    foreach ($kpis as $k) {
        if (is_array($k)) {
            $inner .= admin_kpi($k);
        }
    }
    return $inner === '' ? '' : '<div class="kpi-grid">' . $inner . '</div>';
}

/**
 * Trend line as a self-contained <svg> — the path is computed here, in PHP, so
 * a KPI card needs no client-side charting library and no second request.
 *
 * Degenerate series are handled rather than avoided:
 *   []            → '' (the caller simply renders no chart)
 *   [7]           → a flat rule at mid height (one point has no trend)
 *   [5,5,5]       → a flat rule at mid height, and no division by zero
 *   [-4,0,9]      → min/max come from the data, so negatives sit correctly
 *   [0, '1e400']  → the infinity is dropped, not plotted (see below)
 * Non-numeric entries are dropped; very long series keep their most recent 240
 * points so the path cannot bloat the page.
 *
 * NON-FINITE VALUES ARE DROPPED, not merely tolerated. is_numeric() accepts
 * '1e400', which casts to INF; `$max - $min` was then NAN, `NAN <= 0` is false
 * so the flat-series guard did not catch it, and `d="M2.00,32.00 L118.00,NaN"`
 * reached the SVG — invalid path data, so the renderer discards the path from
 * the error onward and the chart silently vanishes. (It could never be an
 * injection: sprintf('%.2F') can only emit [0-9.-] plus the words NaN/INF, so
 * nothing can leave the quoted d= attribute.) A VARCHAR column summed by MySQL
 * is enough to produce this, so it is filtered rather than documented.
 *
 * The filled .area is only drawn when the series actually varies. aria-hidden,
 * because the figure and its delta already say this in text.
 *
 *   echo admin_sparkline([12, 18, 9, 24, 30]);
 */
function admin_sparkline(array $values, int $w = 120, int $h = 34): string
{
    $w = max(20, min(1000, $w));
    $h = max(10, min(400, $h));

    $vals = [];
    foreach ($values as $v) {
        if (is_bool($v) || !is_numeric($v)) {
            continue;
        }
        $f = (float) $v;
        if (!is_finite($f)) {                 // INF / -INF / NAN — see the note above
            continue;
        }
        $vals[] = $f;
    }
    if (!$vals) {
        return '';
    }
    if (count($vals) > 240) {
        $vals = array_slice($vals, -240);
    }

    $pad  = 2.0;                       // keeps the 2px stroke inside the box
    $n    = count($vals);
    $min  = min($vals);
    $max  = max($vals);
    $span = $max - $min;
    $innerW = $w - 2 * $pad;
    $innerH = $h - 2 * $pad;
    $mid    = $h / 2;

    $pt = static fn (float $x, float $y): string => sprintf('%.2F,%.2F', $x, $y);

    // $span can still overflow to INF from two finite extremes (PHP_FLOAT_MAX and
    // -PHP_FLOAT_MAX), which would divide every point to 0 or NAN.
    if ($n === 1 || !is_finite($span) || $span <= 0.0) {
        // One point, or a perfectly flat series: draw the rule, not a divide.
        $line = 'M' . $pt($pad, $mid) . ' L' . $pt($w - $pad, $mid);
        $area = '';
    } else {
        $step = $innerW / ($n - 1);
        $segs = [];
        foreach ($vals as $i => $v) {
            $x = $pad + $step * $i;
            $y = $pad + $innerH - (($v - $min) / $span) * $innerH;
            $segs[] = $pt($x, $y);
        }
        $line = 'M' . implode(' L', $segs);
        $area = $line . ' L' . $pt($w - $pad, (float) $h) . ' L' . $pt($pad, (float) $h) . ' Z';
    }

    return '<svg class="kpi-spark" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none"'
         . ' aria-hidden="true" focusable="false">'
         . ($area !== '' ? '<path class="area" d="' . $area . '"/>' : '')
         . '<path d="' . $line . '" vector-effect="non-scaling-stroke"/>'
         . '</svg>';
}

/* =============================================================================
 |  10. TIMELINE
 |============================================================================*/

/**
 * Recent activity: who did what, when. Item keys:
 *   who   string  actor            what  string  the action
 *   when  string|int  label, or a timestamp / 'Y-m-d H:i:s' (→ <time datetime>)
 *   tone  string  ''|ok|warn|error  url   string  makes "what" a link
 *
 * The stylesheet only colours the rail node per tone, so each toned entry also
 * gets a glyph and an .sr-only word — colour is never the only signal. An empty
 * list falls back to an empty state rather than an empty <ol>.
 *
 *   echo admin_timeline([
 *       ['who' => 'Priya', 'what' => 'approved membership #1042',
 *        'when' => '2026-08-10 09:15:00', 'tone' => 'ok'],
 *   ]);
 */
function admin_timeline(array $items): string
{
    $rows = '';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $who  = trim((string) ($item['who'] ?? ''));
        $what = trim((string) ($item['what'] ?? ''));
        if ($who === '' && $what === '') {
            continue;
        }
        $rawTone = trim((string) ($item['tone'] ?? ''));
        $tone    = $rawTone === '' ? '' : admin_ui_tone($rawTone);
        $cls     = ['ok' => ' class="is-ok"', 'warn' => ' class="is-warn"', 'error' => ' class="is-err"'][$tone] ?? '';

        $rows .= '<li' . $cls . '><div class="tl-row">';
        if ($tone !== '' && $tone !== 'info') {
            $rows .= admin_ui_glyph($tone, '', 14)
                   . '<span class="sr-only">' . e(admin_ui_tone_label($tone)) . ': </span>';
        }
        if ($who !== '') {
            $rows .= '<span class="tl-who">' . e($who) . '</span>';
        }
        if ($what !== '') {
            $href  = admin_ui_href((string) ($item['url'] ?? ''));
            $inner = $href !== '' ? '<a href="' . e($href) . '">' . e($what) . '</a>' : e($what);
            $rows .= '<span class="tl-what">' . $inner . '</span>';
        }

        $when = $item['when'] ?? '';
        if ($when !== '' && $when !== null) {
            $iso = (string) ($item['when_iso'] ?? '');
            if ($iso === '' && is_numeric($when)) {
                $iso = date(DATE_ATOM, (int) $when);
                $when = date('j M Y, g:i a', (int) $when);
            } elseif ($iso === '' && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2})?/', (string) $when)) {
                $ts  = strtotime((string) $when);
                $iso = $ts ? date(DATE_ATOM, $ts) : '';
            }
            $rows .= $iso !== ''
                ? '<time class="tl-when" datetime="' . e($iso) . '">' . e((string) $when) . '</time>'
                : '<span class="tl-when">' . e((string) $when) . '</span>';
        }
        $rows .= '</div></li>';
    }

    if ($rows === '') {
        return admin_empty_state('No activity yet', 'Actions taken in this module will be listed here.', [], 'history');
    }
    return '<ol class="ds-timeline">' . $rows . '</ol>';
}

/* =============================================================================
 |  11. CONFIRM DIALOG
 |============================================================================*/

/**
 * A confirm that states the consequence instead of asking "are you sure?".
 *
 * A real <dialog class="ds-confirm">, which is what admin-ui.js drives: it calls
 * showModal() (so the backdrop, Escape and top-layer stacking are the
 * platform's), resolves on [data-confirm-yes] / [data-confirm-no], and only then
 * replays the gesture that asked. So this dialog holds NO form of its own — the
 * action stays on the trigger, which means one code path, CSRF where it already
 * is, and a page that still works with JS off (the trigger just submits without
 * asking, because a <dialog> with no open attribute is display:none).
 *
 * $affected is the one line naming what will change ("3 members, 1 invoice") —
 * the difference between "Are you sure?" and a decision the admin can make.
 *
 * $opts: destructive (bool, red title + glyph + "Warning" text and a danger
 * button), confirm / ok (button label), cancel (button label), icon (lucide slug
 * on the confirm button).
 *
 *   echo admin_confirm('delMember', 'Delete this member?',
 *        'The profile, ID card and renewal history are removed. This cannot be undone.',
 *        'Member #1042 — Anita Rao', ['destructive' => true, 'ok' => 'Delete member']);
 *   // trigger — the form that actually does the work:
 *   //   <form method="post" action="…" <?= admin_confirm_trigger(['dialog' => 'delMember']) ?>>
 */
function admin_confirm(string $id, string $title, string $text, string $affected = '', array $opts = []): string
{
    $id      = admin_ui_id($id, 'confirm');
    $titleId = $id . '-title';
    $textId  = $id . '-text';
    $affId   = $id . '-affected';
    $destructive = !empty($opts['destructive']);

    $okLabel     = trim((string) ($opts['ok'] ?? $opts['confirm'] ?? ($destructive ? 'Yes, delete' : 'Yes, proceed')));
    $cancelLabel = trim((string) ($opts['cancel'] ?? 'Cancel'));
    $icon        = trim((string) ($opts['icon'] ?? ''));

    /* aria-describedby names BOTH the consequence and the affected line. The
       affected line is the whole point of this component — "3 members, 1 invoice"
       is the difference between "are you sure?" and a decision — and focus lands
       on Cancel, so a screen-reader user who is not told it here is never told.
       A `hidden` element referenced by aria-describedby is skipped, which is
       exactly the wanted behaviour for the empty case.
       role="dialog" is NOT set: this is a native <dialog>, which already has that
       role, and hard-coding it shadows the platform's own mapping. aria-modal
       stays, because the no-<dialog> fallback in admin-ui.js opens this same node
       by hand and does back the claim with a real inert-ed background. */
    $html  = '<dialog class="ds-confirm' . ($destructive ? ' is-destructive' : '') . '" id="' . e($id) . '"'
           . ' aria-modal="true" aria-labelledby="' . e($titleId) . '"'
           . ' aria-describedby="' . e($textId . ' ' . $affId) . '">';
    $html .= '<div class="ds-confirm-body">';
    if ($destructive) {
        /* .is-destructive only recolours the title, so the warning also carries a
           glyph and a word. It lives OUTSIDE the <h2>: admin-ui.js writes
           `title.textContent` whenever a trigger supplies data-confirm-title,
           which silently destroyed both cues when they were inside the heading
           and left red text as the only signal. Nothing can reach it here. */
        $html .= '<p class="ds-confirm-warn">' . admin_ui_glyph('warn', '', 18)
               . '<span class="sr-only">Warning: </span>'
               . '<span aria-hidden="true">Destructive action</span></p>';
    }
    $html .= '<h2 class="ds-confirm-title" id="' . e($titleId) . '">'
           . e($title !== '' ? $title : 'Please confirm') . '</h2>';
    $html .= '<p class="ds-confirm-text" id="' . e($textId) . '">'
           . e($text !== '' ? $text : 'This action takes effect immediately.') . '</p>';
    // The slot always exists so the JS can fill it per trigger; hidden until it
    // has something to say.
    $html .= '<p class="ds-confirm-affected" id="' . e($affId) . '"' . (trim($affected) === '' ? ' hidden' : '') . '>'
           . (trim($affected) !== '' ? '<strong>Affects:</strong> ' . e($affected) : '') . '</p>';
    $html .= '</div>';

    $html .= '<div class="ds-confirm-foot">';
    $html .= '<button type="button" class="btn btn-secondary" value="cancel" data-confirm-no>'
           . e($cancelLabel !== '' ? $cancelLabel : 'Cancel') . '</button>';
    $html .= '<button type="button" class="btn btn-' . ($destructive ? 'danger' : 'primary') . '"'
           . ' value="confirm" data-confirm-yes>'
           . ($icon !== '' ? lucide($icon) . ' ' : '')
           . e($okLabel !== '' ? $okLabel : 'Yes, proceed') . '</button>';
    $html .= '</div>';

    return $html . '</dialog>';
}

/**
 * The attributes that turn any button, link or form into a confirmed action.
 * admin-ui.js intercepts the gesture in the capture phase, asks, and replays it
 * on yes — so the element keeps its own href/method/action and needs no JS of
 * its own. Put them on the <form> for a submit, on the control otherwise.
 *
 * Keys (all optional): dialog (id of an admin_confirm() dialog to use — with it,
 * that dialog's own copy is left alone), text, title, affected, ok, cancel,
 * tone ('neutral'/'info' for a non-destructive ask; destructive is the default).
 * {count} in text/title/affected is substituted with the number of rows selected
 * when the control sits in a .bulk-actions bar.
 *
 *   <button class="btn btn-danger btn-sm" <?= admin_confirm_trigger([
 *       'text' => 'Delete this member permanently?', 'ok' => 'Delete']) ?>>Delete</button>
 */
function admin_confirm_trigger(array $o = []): string
{
    return ltrim(admin_ui_attrs(admin_ui_confirm_map($o)));
}
