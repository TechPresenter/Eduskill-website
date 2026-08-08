<?php
/**
 * =============================================================================
 *  Global Theme Engine
 * =============================================================================
 *  One centralised token store drives CSS custom properties across EVERY
 *  surface — public site, admin panel, auth pages, email HTML and print/PDF.
 *  Because the whole design system already reads from CSS variables, changing a
 *  token here instantly restyles every component without touching code.
 *
 *    theme_tokens()        the catalogue (groups → tokens → type/default/label)
 *    theme_all()           resolved live values (cached per request)
 *    theme_get/theme_set   read / write a single token (published or draft)
 *    theme_style_tag()     the <style> block injected into each surface
 *    theme_publish()       promote drafts → live (auto-snapshots first)
 *    theme_snapshot/rollback/versions      history + restore
 *    theme_export/import/reset             JSON portability
 *    theme_presets/apply_preset/save_preset
 *    theme_contrast()      WCAG contrast ratio helper
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 | 1. TOKEN CATALOGUE — maps a theme token to the CSS variable it drives.
 |    'var' => the CSS custom property; 'scope' => site|admin|both
 | ========================================================================== */
function theme_tokens(): array
{
    return [
        'brand' => [
            'label' => 'Brand', 'icon' => 'sparkles',
            'tokens' => [
                'brand.name'        => ['label' => 'Company name',   'type' => 'text',  'default' => '', 'hint' => 'Blank = use Website Settings name'],
                'brand.logo'        => ['label' => 'Logo (light)',   'type' => 'image', 'default' => ''],
                'brand.logo_dark'   => ['label' => 'Logo (dark)',    'type' => 'image', 'default' => ''],
                'brand.favicon'     => ['label' => 'Favicon',        'type' => 'image', 'default' => ''],
                'brand.app_icon'    => ['label' => 'App icon (PWA)', 'type' => 'image', 'default' => ''],
                'brand.watermark'   => ['label' => 'Watermark text', 'type' => 'text',  'default' => ''],
                'brand.footer_note' => ['label' => 'Footer branding','type' => 'text',  'default' => ''],
            ],
        ],
        'colors' => [
            'label' => 'Colors', 'icon' => 'palette',
            'tokens' => [
                'color.primary'   => ['label' => 'Primary',    'type' => 'color', 'default' => '#063566', 'var' => '--primary,--brand,--brand-600'],
                'color.secondary' => ['label' => 'Secondary',  'type' => 'color', 'default' => '#084881', 'var' => '--secondary,--brand-700,--brand-2,--info'],
                'color.accent'    => ['label' => 'Accent',     'type' => 'color', 'default' => '#E67B1D', 'var' => '--accent,--orange,--warning'],
                'color.success'   => ['label' => 'Success',    'type' => 'color', 'default' => '#58A42F', 'var' => '--success,--green'],
                'color.success_dark' => ['label' => 'Success (dark)', 'type' => 'color', 'default' => '#308629', 'var' => '--success-dark,--green-dark'],
                'color.danger'    => ['label' => 'Error',      'type' => 'color', 'default' => '#DC2626', 'var' => '--danger'],
                'color.bg'        => ['label' => 'Background', 'type' => 'color', 'default' => '#FFFFFF', 'var' => '--bg'],
                'color.bg_alt'    => ['label' => 'Alt section','type' => 'color', 'default' => '#F1F6FB', 'var' => '--bg-alt'],
                'color.surface'   => ['label' => 'Surface / card', 'type' => 'color', 'default' => '#FDFEFE', 'var' => '--surface'],
                'color.surface_2' => ['label' => 'Surface (muted)','type' => 'color', 'default' => '#F4F8FC', 'var' => '--surface-2'],
                'color.text'      => ['label' => 'Heading text','type' => 'color', 'default' => '#063566', 'var' => '--text'],
                'color.text_soft' => ['label' => 'Body text',  'type' => 'color', 'default' => '#374151', 'var' => '--text-soft'],
                'color.muted'     => ['label' => 'Muted text', 'type' => 'color', 'default' => '#6B7280', 'var' => '--muted'],
                'color.border'    => ['label' => 'Border',     'type' => 'color', 'default' => '#E5E7EB', 'var' => '--border'],
                'color.sidebar'   => ['label' => 'Admin sidebar', 'type' => 'color', 'default' => '#063566', 'scope' => 'admin', 'var' => '--sb-bg'],
                'color.sidebar_2' => ['label' => 'Sidebar gradient end', 'type' => 'color', 'default' => '#042A52', 'scope' => 'admin', 'var' => '--sb-bg-2'],
                'color.sidebar_active' => ['label' => 'Sidebar active', 'type' => 'color', 'default' => '#084881', 'scope' => 'admin', 'var' => '--sb-active'],
                'color.footer'    => ['label' => 'Footer base', 'type' => 'color', 'default' => '#063566', 'scope' => 'site', 'var' => '--footer-bg'],
            ],
        ],
        'gradients' => [
            'label' => 'Gradients & Glass', 'icon' => 'blend',
            'tokens' => [
                'grad.angle'     => ['label' => 'Gradient angle', 'type' => 'number', 'default' => '135', 'min' => 0, 'max' => 360, 'unit' => 'deg'],
                'grad.primary_a' => ['label' => 'Primary from', 'type' => 'color', 'default' => '#063566'],
                'grad.primary_b' => ['label' => 'Primary to',   'type' => 'color', 'default' => '#084881'],
                'grad.cta_a'     => ['label' => 'CTA from',     'type' => 'color', 'default' => '#E67B1D'],
                'grad.cta_b'     => ['label' => 'CTA to',       'type' => 'color', 'default' => '#F59E0B'],
                'glass.blur'     => ['label' => 'Glass blur',   'type' => 'number', 'default' => '12', 'min' => 0, 'max' => 40, 'unit' => 'px', 'var' => '--glass-blur'],
                'glass.alpha'    => ['label' => 'Glass opacity','type' => 'number', 'default' => '12', 'min' => 0, 'max' => 100, 'unit' => '%'],
            ],
        ],
        'typography' => [
            'label' => 'Typography', 'icon' => 'type',
            'tokens' => [
                'font.body'     => ['label' => 'Body font',   'type' => 'font',   'default' => 'Plus Jakarta Sans'],
                'font.heading'  => ['label' => 'Heading font','type' => 'font',   'default' => 'Plus Jakarta Sans'],
                'font.base'     => ['label' => 'Base size',   'type' => 'number', 'default' => '16', 'min' => 12, 'max' => 22, 'unit' => 'px', 'var' => '--font-base'],
                'font.scale'    => ['label' => 'Heading scale','type' => 'number','default' => '1.25', 'min' => 1.05, 'max' => 1.6, 'step' => '.01', 'var' => '--font-scale'],
                'font.line'     => ['label' => 'Line height', 'type' => 'number', 'default' => '1.65', 'min' => 1.2, 'max' => 2.2, 'step' => '.05', 'var' => '--line-height'],
                'font.tracking' => ['label' => 'Letter spacing','type' => 'number','default' => '0', 'min' => -3, 'max' => 5, 'step' => '.1', 'unit' => 'px', 'var' => '--tracking'],
                'font.weight_body' => ['label' => 'Body weight',   'type' => 'select', 'default' => '400', 'options' => ['300','400','500','600'], 'var' => '--fw-body'],
                'font.weight_head' => ['label' => 'Heading weight','type' => 'select', 'default' => '800', 'options' => ['600','700','800','900'], 'var' => '--fw-head'],
            ],
        ],
        'layout' => [
            'label' => 'Layout', 'icon' => 'layout-grid',
            'tokens' => [
                'layout.width'      => ['label' => 'Content width', 'type' => 'select', 'default' => 'boxed', 'options' => ['boxed','full']],
                'layout.container'  => ['label' => 'Container size','type' => 'number', 'default' => '1200', 'min' => 960, 'max' => 1600, 'unit' => 'px', 'var' => '--container'],
                'layout.header_h'   => ['label' => 'Navbar height', 'type' => 'number', 'default' => '74', 'min' => 56, 'max' => 110, 'unit' => 'px', 'var' => '--header-h'],
                'layout.sidebar_w'  => ['label' => 'Sidebar width', 'type' => 'number', 'default' => '268', 'min' => 220, 'max' => 340, 'unit' => 'px', 'scope' => 'admin', 'var' => '--sb-w'],
                'layout.sticky_header' => ['label' => 'Sticky header', 'type' => 'bool', 'default' => '1'],
                'layout.dir'        => ['label' => 'Text direction','type' => 'select', 'default' => 'ltr', 'options' => ['ltr','rtl']],
            ],
        ],
        'effects' => [
            'label' => 'UI Effects', 'icon' => 'wand-sparkles',
            'tokens' => [
                'ui.radius'      => ['label' => 'Border radius', 'type' => 'number', 'default' => '14', 'min' => 0, 'max' => 30, 'unit' => 'px', 'var' => '--radius'],
                'ui.radius_sm'   => ['label' => 'Radius (small)','type' => 'number', 'default' => '10', 'min' => 0, 'max' => 24, 'unit' => 'px', 'var' => '--radius-sm'],
                'ui.radius_lg'   => ['label' => 'Radius (large)','type' => 'number', 'default' => '22', 'min' => 0, 'max' => 40, 'unit' => 'px', 'var' => '--radius-lg'],
                'ui.shadow'      => ['label' => 'Shadow depth',  'type' => 'select', 'default' => 'medium', 'options' => ['none','soft','medium','strong']],
                'ui.transition'  => ['label' => 'Transition speed','type' => 'number','default' => '250', 'min' => 0, 'max' => 600, 'unit' => 'ms'],
                'ui.hover_lift'  => ['label' => 'Card hover lift','type' => 'number', 'default' => '4', 'min' => 0, 'max' => 14, 'unit' => 'px', 'var' => '--hover-lift'],
                'ui.animations'  => ['label' => 'Animations',    'type' => 'bool',   'default' => '1'],
            ],
        ],
        'auth' => [
            'label' => 'Login Page', 'icon' => 'log-in',
            'tokens' => [
                'auth.bg_image'  => ['label' => 'Background image', 'type' => 'image', 'default' => ''],
                'auth.card'      => ['label' => 'Card style', 'type' => 'select', 'default' => 'glass', 'options' => ['glass','solid','bordered']],
                'auth.welcome'   => ['label' => 'Welcome text', 'type' => 'text', 'default' => ''],
                'auth.animation' => ['label' => 'Entrance animation', 'type' => 'bool', 'default' => '1'],
            ],
        ],
        'email' => [
            'label' => 'Email & PDF', 'icon' => 'mail',
            'tokens' => [
                'email.header_bg' => ['label' => 'Email header colour', 'type' => 'color', 'default' => '#063566'],
                'email.btn_bg'    => ['label' => 'Email button colour', 'type' => 'color', 'default' => '#E67B1D'],
                'email.font'      => ['label' => 'Email font', 'type' => 'text', 'default' => 'Arial, Helvetica, sans-serif'],
                'email.signature' => ['label' => 'Email signature', 'type' => 'textarea', 'default' => ''],
                'pdf.accent'      => ['label' => 'PDF/document accent', 'type' => 'color', 'default' => '#063566'],
            ],
        ],
        'advanced' => [
            'label' => 'Advanced', 'icon' => 'code',
            'tokens' => [
                'adv.theme_color' => ['label' => 'Browser theme colour', 'type' => 'color', 'default' => '#063566'],
                'adv.mode'        => ['label' => 'Default mode', 'type' => 'select', 'default' => 'light', 'options' => ['light','dark','auto']],
                'adv.css'         => ['label' => 'Custom CSS', 'type' => 'code', 'default' => ''],
                'adv.js'          => ['label' => 'Custom JavaScript', 'type' => 'code', 'default' => ''],
            ],
        ],
    ];
}

/** Flat map: token => meta (with group injected). */
function theme_token_map(): array
{
    static $flat = null;
    if ($flat !== null) { return $flat; }
    $flat = [];
    foreach (theme_tokens() as $g => $grp) {
        foreach ($grp['tokens'] as $t => $meta) {
            $meta['group'] = $g;
            $flat[$t] = $meta;
        }
    }
    return $flat;
}

/** Defaults only. */
function theme_defaults(): array
{
    $out = [];
    foreach (theme_token_map() as $t => $m) { $out[$t] = (string) ($m['default'] ?? ''); }
    return $out;
}

/* =============================================================================
 | 2. READ / WRITE
 | ========================================================================== */

/**
 * All resolved token values. $withDrafts=true returns draft-over-live (used by
 * the admin preview); otherwise the published values the public site sees.
 */
function theme_all(bool $withDrafts = false): array
{
    static $cache = [];
    $key = $withDrafts ? 'draft' : 'live';
    if (isset($cache[$key])) { return $cache[$key]; }

    $values = theme_defaults();
    try {
        foreach (db_all('SELECT token, value, draft FROM theme_settings') as $r) {
            $t = $r['token'];
            if (!array_key_exists($t, $values)) { continue; }
            if ($withDrafts && $r['draft'] !== null && $r['draft'] !== '') {
                $values[$t] = (string) $r['draft'];
            } elseif ($r['value'] !== null && $r['value'] !== '') {
                $values[$t] = (string) $r['value'];
            }
        }
    } catch (Throwable $e) {
        // table not migrated yet — defaults keep the site rendering
    }
    return $cache[$key] = $values;
}

function theme_get(string $token, bool $withDrafts = false): string
{
    $all = theme_all($withDrafts);
    return (string) ($all[$token] ?? '');
}

/** Write a token. $asDraft keeps the published value untouched. */
function theme_set(string $token, string $value, bool $asDraft = true): void
{
    $map = theme_token_map();
    if (!isset($map[$token])) { return; }
    $group = $map[$token]['group'];
    $exists = db_value('SELECT id FROM theme_settings WHERE token = :t LIMIT 1', [':t' => $token]);
    if ($exists === null) {
        db_insert('theme_settings', [
            'token' => $token, 'group_name' => $group,
            'value' => $asDraft ? null : $value, 'draft' => $asDraft ? $value : null,
        ]);
    } else {
        db_update('theme_settings', $asDraft ? ['draft' => $value] : ['value' => $value, 'draft' => null],
            'id = :id', [':id' => (int) $exists]);
    }
}

function theme_has_draft(): bool
{
    try { return (int) db_value("SELECT COUNT(*) FROM theme_settings WHERE draft IS NOT NULL AND draft <> ''") > 0; }
    catch (Throwable $e) { return false; }
}

/** Promote every draft to live (snapshotting the current live state first). */
function theme_publish(?string $label = null): void
{
    theme_snapshot($label ?: 'Auto-snapshot before publish', true);
    db_query("UPDATE theme_settings SET value = draft, draft = NULL WHERE draft IS NOT NULL AND draft <> ''");
    log_activity('update', 'theme', 'Published theme changes');
}

function theme_discard_draft(): void
{
    db_query('UPDATE theme_settings SET draft = NULL');
    log_activity('update', 'theme', 'Discarded theme draft');
}

/* =============================================================================
 | 3. VERSIONS / IMPORT / EXPORT / PRESETS
 | ========================================================================== */

function theme_snapshot(?string $label = null, bool $auto = false): int
{
    $payload = json_encode(theme_all(false), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return (int) db_insert('theme_versions', [
        'label' => $label ?: ('Snapshot ' . date('d M Y H:i')),
        'payload' => $payload, 'is_auto' => $auto ? 1 : 0,
        'created_by' => function_exists('current_user_id') ? current_user_id() : null,
    ]);
}

function theme_versions(int $limit = 25): array
{
    try { return db_all('SELECT id,label,note,is_auto,created_at FROM theme_versions ORDER BY id DESC LIMIT ' . max(1, min(100, $limit))); }
    catch (Throwable $e) { return []; }
}

/** Restore a snapshot straight to live (snapshots the current state first). */
function theme_rollback(int $versionId): bool
{
    $v = find('theme_versions', $versionId);
    if (!$v) { return false; }
    $data = json_decode((string) $v['payload'], true);
    if (!is_array($data)) { return false; }
    theme_snapshot('Auto-snapshot before rollback', true);
    foreach ($data as $t => $val) { theme_set((string) $t, (string) $val, false); }
    log_activity('update', 'theme', 'Rolled back theme to version #' . $versionId);
    return true;
}

function theme_export(): string
{
    return (string) json_encode([
        'app' => 'eduskill-theme', 'version' => 1, 'exported_at' => date('c'),
        'tokens' => theme_all(false),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/** Import a JSON theme (as draft by default so it can be previewed first). */
function theme_import(string $json, bool $asDraft = true): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) { return ['ok' => false, 'message' => 'That file is not valid JSON.']; }
    $tokens = $data['tokens'] ?? $data;
    if (!is_array($tokens)) { return ['ok' => false, 'message' => 'No theme tokens found in that file.']; }
    $known = theme_token_map();
    $n = 0;
    foreach ($tokens as $t => $val) {
        if (isset($known[$t]) && is_scalar($val)) { theme_set((string) $t, (string) $val, $asDraft); $n++; }
    }
    log_activity('update', 'theme', 'Imported theme (' . $n . ' tokens)');
    return ['ok' => true, 'message' => $n . ' tokens imported' . ($asDraft ? ' as a draft — preview then publish.' : '.')];
}

function theme_reset(): void
{
    theme_snapshot('Auto-snapshot before reset', true);
    db_query('DELETE FROM theme_settings');
    log_activity('update', 'theme', 'Reset theme to defaults');
}

function theme_presets(): array
{
    try { return db_all('SELECT * FROM theme_presets ORDER BY is_builtin DESC, name ASC'); }
    catch (Throwable $e) { return []; }
}

function theme_save_preset(string $name): int
{
    $slug = unique_slug('theme_presets', $name);
    return (int) db_insert('theme_presets', [
        'name' => clean($name), 'slug' => $slug,
        'payload' => json_encode(theme_all(true), JSON_UNESCAPED_SLASHES),
        'created_by' => function_exists('current_user_id') ? current_user_id() : null,
    ]);
}

/** Apply a preset as a draft so it can be previewed before publishing. */
function theme_apply_preset(int $id, bool $asDraft = true): bool
{
    $p = find('theme_presets', $id);
    if (!$p) { return false; }
    $data = json_decode((string) $p['payload'], true);
    if (!is_array($data)) { return false; }
    foreach ($data as $t => $val) { if (is_scalar($val)) { theme_set((string) $t, (string) $val, $asDraft); } }
    db_query('UPDATE theme_presets SET is_active = 0');
    db_update('theme_presets', ['is_active' => 1], 'id = :id', [':id' => $id]);
    log_activity('update', 'theme', 'Applied theme preset: ' . $p['name']);
    return true;
}

/** Ship a few built-in themes the first time the page is opened. */
function theme_seed_presets(): void
{
    try { if ((int) db_value('SELECT COUNT(*) FROM theme_presets') > 0) { return; } } catch (Throwable $e) { return; }
    $mk = static fn (array $o): string => json_encode(array_merge(theme_defaults(), $o), JSON_UNESCAPED_SLASHES);
    $builtin = [
        ['EduSkill Default', 'eduskill-default', []],
        ['Midnight',   'midnight',   ['color.primary' => '#0F172A', 'color.secondary' => '#1E293B', 'color.accent' => '#38BDF8', 'color.sidebar' => '#0F172A', 'color.sidebar_2' => '#020617', 'grad.primary_a' => '#0F172A', 'grad.primary_b' => '#1E293B']],
        ['Forest',     'forest',     ['color.primary' => '#14532D', 'color.secondary' => '#166534', 'color.accent' => '#F59E0B', 'color.success' => '#22C55E', 'color.sidebar' => '#14532D', 'color.sidebar_2' => '#052E16', 'grad.primary_a' => '#14532D', 'grad.primary_b' => '#166534']],
        ['Royal Plum', 'royal-plum', ['color.primary' => '#4C1D95', 'color.secondary' => '#6D28D9', 'color.accent' => '#F472B6', 'color.sidebar' => '#4C1D95', 'color.sidebar_2' => '#2E1065', 'grad.primary_a' => '#4C1D95', 'grad.primary_b' => '#6D28D9']],
        ['Sunrise',    'sunrise',    ['color.primary' => '#9A3412', 'color.secondary' => '#C2410C', 'color.accent' => '#F59E0B', 'color.sidebar' => '#7C2D12', 'color.sidebar_2' => '#431407', 'grad.primary_a' => '#9A3412', 'grad.primary_b' => '#EA580C']],
    ];
    foreach ($builtin as [$name, $slug, $over]) {
        db_insert('theme_presets', ['name' => $name, 'slug' => $slug, 'payload' => $mk($over), 'is_builtin' => 1]);
    }
}

/* =============================================================================
 | 4. CSS EMISSION — the part that actually restyles the platform
 | ========================================================================== */

/** Shadow presets by depth name. */
function theme_shadow_set(string $depth): array
{
    return match ($depth) {
        'none'   => ['0 0 0 rgba(0,0,0,0)', '0 0 0 rgba(0,0,0,0)', '0 0 0 rgba(0,0,0,0)'],
        'soft'   => ['0 1px 2px rgba(6,53,102,.04)', '0 2px 8px rgba(6,53,102,.06)', '0 8px 20px rgba(6,53,102,.10)'],
        'strong' => ['0 2px 4px rgba(6,53,102,.10)', '0 8px 26px rgba(6,53,102,.16)', '0 28px 60px rgba(6,53,102,.26)'],
        default  => ['0 1px 2px rgba(6,53,102,.07)', '0 4px 16px rgba(6,53,102,.10)', '0 18px 40px rgba(6,53,102,.16)'],
    };
}

/** hex → "r,g,b" (for rgba() composition). */
function theme_rgb(string $hex): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) { return '6,53,102'; }
    return hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ',' . hexdec(substr($hex, 4, 2));
}

/**
 * Build the CSS custom-property block.
 * @param string $scope site|admin  (admin adds sidebar tokens)
 */
function theme_css_vars(string $scope = 'site', bool $withDrafts = false): string
{
    $v = theme_all($withDrafts);
    $map = theme_token_map();
    $out = [];

    // Direct token → CSS var mappings (a token may drive several vars).
    foreach ($map as $token => $meta) {
        if (empty($meta['var'])) { continue; }
        $tScope = $meta['scope'] ?? 'both';
        if ($tScope !== 'both' && $tScope !== $scope) { continue; }
        $val = trim((string) ($v[$token] ?? ''));
        if ($val === '') { continue; }
        $unit = $meta['unit'] ?? '';
        if ($unit && is_numeric($val)) { $val .= $unit; }
        foreach (explode(',', $meta['var']) as $cssVar) {
            $out[] = trim($cssVar) . ':' . $val;
        }
    }

    // Derived values.
    $ang  = (int) ($v['grad.angle'] ?? 135);
    $pa   = $v['grad.primary_a'] ?: $v['color.primary'];
    $pb   = $v['grad.primary_b'] ?: $v['color.secondary'];
    $ca   = $v['grad.cta_a'] ?: $v['color.accent'];
    $cb   = $v['grad.cta_b'] ?: $v['color.accent'];
    $out[] = "--grad-brand:linear-gradient({$ang}deg,{$pa},{$pb})";
    $out[] = "--grad-primary:linear-gradient({$ang}deg,{$pa},{$pb})";
    $out[] = "--grad-donate:linear-gradient({$ang}deg,{$ca},{$cb})";
    $out[] = "--grad-accent:linear-gradient({$ang}deg,{$ca},{$cb})";
    $out[] = "--grad-green:linear-gradient({$ang}deg," . ($v['color.success'] ?: '#58A42F') . ',' . ($v['color.success_dark'] ?: '#308629') . ')';
    $out[] = "--grad-cta:linear-gradient({$ang}deg,{$pa}," . ($v['color.success'] ?: '#58A42F') . ')';

    [$s1, $s2, $s3] = theme_shadow_set((string) ($v['ui.shadow'] ?? 'medium'));
    $out[] = '--shadow-sm:' . $s1;
    $out[] = '--shadow:' . $s2;
    $out[] = '--shadow-lg:' . $s3;

    $trans = (int) ($v['ui.transition'] ?? 250);
    $out[] = '--transition:' . $trans . 'ms cubic-bezier(.4,0,.2,1)';
    $out[] = '--ring:rgba(' . theme_rgb($v['color.secondary'] ?: '#084881') . ',.35)';
    $out[] = '--glass-bg:rgba(255,255,255,' . (max(0, min(100, (int) ($v['glass.alpha'] ?? 12))) / 100) . ')';

    // Fonts
    $body = trim((string) ($v['font.body'] ?? ''));
    $head = trim((string) ($v['font.heading'] ?? '')) ?: $body;
    if ($body !== '') { $out[] = "--font-sans:'" . str_replace("'", '', $body) . "',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif"; }
    if ($head !== '') { $out[] = "--font-head:'" . str_replace("'", '', $head) . "',system-ui,sans-serif"; }

    // Emitted on :root AND :root[data-theme] so admin-chosen tokens are not
    // silently outranked by the hardcoded `:root[data-theme="dark"]` block in
    // tailwind.css, which has higher specificity (0,2,0) than a bare :root.
    $body = implode(';', $out) . ';';
    return ":root{{$body}}:root[data-theme]{{$body}}";
}

/** Extra rules that need real selectors (not just variables). */
function theme_css_rules(string $scope, bool $withDrafts = false): string
{
    $v = theme_all($withDrafts);
    $css = '';

    $head = trim((string) ($v['font.heading'] ?? ''));
    if ($head !== '') {
        $css .= "h1,h2,h3,h4,h5,h6,.section-title,.card-title{font-family:var(--font-head);}";
    }
    $css .= 'body{font-size:' . max(12, (int) ($v['font.base'] ?? 16)) . 'px;line-height:' . (float) ($v['font.line'] ?? 1.65)
          . ';letter-spacing:' . (float) ($v['font.tracking'] ?? 0) . 'px;font-weight:' . (int) ($v['font.weight_body'] ?? 400) . ';}';
    $css .= 'h1,h2,h3,h4,h5,h6{font-weight:' . (int) ($v['font.weight_head'] ?? 800) . ';}';

    if (($v['layout.width'] ?? 'boxed') === 'full') {
        $css .= '.container{max-width:none;}';
    }
    if ((string) ($v['ui.animations'] ?? '1') !== '1') {
        $css .= '*,*::before,*::after{animation:none!important;transition:none!important;}';
    }
    if ((string) ($v['layout.sticky_header'] ?? '1') !== '1' && $scope === 'site') {
        $css .= '.navbar,.site-header{position:relative!important;}';
    }
    if ($scope === 'site' && !empty($v['color.footer'])) {
        $css .= '.footer{--footer-bg:' . $v['color.footer'] . ';}';
    }
    return $css;
}

/** Google Fonts <link> for the configured families (skips system defaults). */
function theme_font_link(bool $withDrafts = false): string
{
    $v = theme_all($withDrafts);
    $fams = array_values(array_unique(array_filter([
        trim((string) ($v['font.body'] ?? '')), trim((string) ($v['font.heading'] ?? '')),
    ])));
    if (!$fams) { return ''; }
    $q = [];
    foreach ($fams as $f) { $q[] = 'family=' . rawurlencode($f) . ':wght@300;400;500;600;700;800;900'; }
    return '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?' . implode('&', $q) . '&display=swap">';
}

/**
 * THE injection point. Drop this immediately after the stylesheet links on any
 * surface and every component picks up the theme.
 */
function theme_style_tag(string $scope = 'site', bool $withDrafts = false): string
{
    $out  = theme_font_link($withDrafts);
    $out .= '<style id="pwf-theme-tokens">' . theme_css_vars($scope, $withDrafts)
          . theme_css_rules($scope, $withDrafts) . '</style>';
    $custom = trim((string) theme_get('adv.css', $withDrafts));
    if ($custom !== '') { $out .= '<style id="pwf-theme-custom">' . $custom . '</style>'; }
    return $out;
}

/** Custom JS, emitted near </body>. */
function theme_script_tag(bool $withDrafts = false): string
{
    $js = trim((string) theme_get('adv.js', $withDrafts));
    return $js === '' ? '' : '<script id="pwf-theme-js">' . $js . '</script>';
}

/* =============================================================================
 | 5. WCAG CONTRAST
 | ========================================================================== */

function theme_luminance(string $hex): float
{
    [$r, $g, $b] = array_map('intval', explode(',', theme_rgb($hex)));
    $f = static function (float $c): float {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $f((float) $r) + 0.7152 * $f((float) $g) + 0.0722 * $f((float) $b);
}

/** ['ratio' => 5.31, 'aa' => true, 'aaa' => false, 'grade' => 'AA'] */
function theme_contrast(string $fg, string $bg): array
{
    $l1 = theme_luminance($fg);
    $l2 = theme_luminance($bg);
    $ratio = ($l1 > $l2 ? ($l1 + 0.05) / ($l2 + 0.05) : ($l2 + 0.05) / ($l1 + 0.05));
    $ratio = round($ratio, 2);
    return [
        'ratio' => $ratio,
        'aa'    => $ratio >= 4.5,
        'aaa'   => $ratio >= 7,
        'grade' => $ratio >= 7 ? 'AAA' : ($ratio >= 4.5 ? 'AA' : ($ratio >= 3 ? 'AA Large' : 'Fail')),
    ];
}
