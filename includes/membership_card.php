<?php
/**
 * =============================================================================
 *  Membership card renderer
 * =============================================================================
 *  Produces the digital / printable membership ID card in three forms:
 *    - card_html()  : the on-screen credential (HTML/CSS + inline SVG), 2 faces
 *    - card_png()   : GD-rendered raster (download / preview), >= 1600px wide
 *    - card_pdf()   : the raster wrapped in a minimal, dependency-free PDF at
 *                     ID-1 (CR80) page dimensions
 *
 *  No Composer, no FPDF: the card is composited with GD (JPEG/PNG/FreeType, all
 *  confirmed available) and the QR is drawn straight from the PWF_QR module
 *  matrix, so nothing is fetched from the network.
 *
 *  EVERY value on the card comes from the database or from settings — member
 *  row, membership_plans, social_links, settings. Nothing is invented: a field
 *  with no value has its whole row removed rather than printing a label above a
 *  blank space or a placeholder dash.
 *
 *  Templates ('classic' | 'modern' | 'dark') are a token swap, applied to BOTH
 *  faces and to the raster, so the on-screen card and the download match.
 * =============================================================================
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/qrcode.php';

/** Card pixel size — CR80 landscape (3.375in x 2.125in) at ~300 DPI. */
const CARD_W = 1012;
const CARD_H = 638;

/** Available template ids => human labels. */
function card_templates(): array
{
    return [
        'classic' => 'Classic — ivory body, curved green header',
        'premium' => 'Premium — deep charcoal body, gold accents',
        'modern'  => 'Modern — soft body, wide accent rail',
        'minimal' => 'Minimal — white body, flat header, no ornament',
    ];
}

/**
 * Normalise a template id. 'dark' was the pre-4-template id for the charcoal
 * card and may still be sitting in the settings row of an existing install, so
 * it resolves to its successor rather than silently falling back to classic.
 */
function card_template_id(?string $template): string
{
    $template = strtolower(trim((string) $template));
    if ($template === 'dark') {
        return 'premium';
    }
    return array_key_exists($template, card_templates()) ? $template : 'classic';
}

/** The admin-configured default template, legacy ids resolved. */
function card_default_template(): string
{
    return card_template_id((string) get_setting('membership_card_template', 'classic'));
}

/* =============================================================================
 |  BRAND PALETTE + TEMPLATE TOKENS
 |  The card is restricted to the ten brand colours. A plan's own `color` is
 |  honoured only when it is one of them; anything else would break the audited
 |  contrast pairs, so it falls back to the primary green.
 |============================================================================*/

function card_palette(): array
{
    return [
        'primary'   => '#0B4E3D',
        'dark'      => '#0F4537',
        'secondary' => '#174D3D',
        'yellow'    => '#FFE987',
        'gold'      => '#E8C52E',
        'orange'    => '#F15A24',
        'white'     => '#FFFFFF',
        'soft'      => '#F8FCF8',
        'ivory'     => '#FEFEF1',
        'ink'       => '#151818',
    ];
}

/** Snap an arbitrary (admin-entered) hex to the palette, or to the primary. */
function card_palette_snap(?string $hex): string
{
    $hex = strtolower(trim((string) $hex));
    foreach (card_palette() as $value) {
        if ($hex === strtolower($value)) {
            return $value;
        }
    }
    return card_palette()['primary'];
}

/* =============================================================================
 |  CONTRAST
 |  Admins choose backgrounds; the card DERIVES every foreground from the chosen
 |  background instead of letting it be configured. That is the only way a
 |  colour picker cannot produce an illegible card: there is no combination of
 |  admin choices that yields a text pair below WCAG AA, because the ink is
 |  computed from the measured ratio rather than stored.
 |============================================================================*/

/** WCAG relative luminance of a #rgb / #rrggbb colour. */
function card_luminance(string $hex): float
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return 0.0;
    }
    $lin = [];
    foreach ([0, 2, 4] as $i) {
        $c = hexdec(substr($hex, $i, 2)) / 255;
        $lin[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $lin[0] + 0.7152 * $lin[1] + 0.0722 * $lin[2];
}

/** WCAG contrast ratio between two colours (1.0 .. 21.0). */
function card_contrast(string $a, string $b): float
{
    $la = card_luminance($a);
    $lb = card_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * The first candidate that clears $min against $bg, else the highest-contrast
 * candidate. Candidates are always brand palette colours, so the card can never
 * leave the palette however it is configured.
 */
function card_ink_for(string $bg, array $candidates, float $min = 4.5): string
{
    $best = '';
    $bestRatio = -1.0;
    foreach ($candidates as $candidate) {
        $ratio = card_contrast($bg, $candidate);
        if ($ratio >= $min) {
            return $candidate;
        }
        if ($ratio > $bestRatio) {
            $bestRatio = $ratio;
            $best = $candidate;
        }
    }
    return $best !== '' ? $best : card_palette()['ink'];
}

/** As card_ink_for(), but the candidate must clear $min against EVERY background. */
function card_ink_for_multi(array $backgrounds, array $candidates, float $min = 4.5): string
{
    $best = '';
    $bestRatio = -1.0;
    foreach ($candidates as $candidate) {
        $worst = 21.0;
        foreach ($backgrounds as $bg) {
            $worst = min($worst, card_contrast($bg, $candidate));
        }
        if ($worst >= $min) {
            return $candidate;
        }
        if ($worst > $bestRatio) {
            $bestRatio = $worst;
            $best = $candidate;
        }
    }
    return $best !== '' ? $best : card_palette()['ink'];
}

/** Is this background dark enough to want light type on it? */
function card_is_dark(string $hex): bool
{
    return card_luminance($hex) < 0.30;
}

/**
 * Brand inks to try against a background, best first.
 *
 * The opposite family is always appended rather than omitted. Orange (#F15A24,
 * luminance .26) is the case that forces this: it classifies as "dark", yet no
 * light brand colour clears AA on it — #FFFFFF is 3.37:1 — and its only legible
 * ink is #151818 at 5.30:1. Truncating to one family silently shipped that pair.
 *
 * $role picks the head of the list so the derived value matches the hand-audited
 * default for the same surface: 'strong' surfaces (header, footer, plaque) lead
 * with white, body surfaces lead with ivory, 'quiet' leads with the label tones.
 */
function card_ink_candidates(string $bg, string $role = 'strong', string $accent = ''): array
{
    $p = card_palette();
    $light = [
        'strong' => [$p['white'], $p['ivory'], $p['yellow'], $p['gold']],
        'body'   => [$p['ivory'], $p['white'], $p['yellow'], $p['gold']],
        'quiet'  => [$p['yellow'], $p['gold'], $p['ivory'], $p['white']],
    ];
    $dark = [
        'strong' => [$p['ink'], $p['dark'], $p['primary'], $p['secondary']],
        'body'   => [$p['ink'], $p['dark'], $p['primary'], $p['secondary']],
        'quiet'  => [$p['secondary'], $p['dark'], $p['primary'], $p['ink']],
    ];
    $role = isset($light[$role]) ? $role : 'strong';
    $out  = card_is_dark($bg)
        ? array_merge($light[$role], $dark[$role])
        : array_merge($dark[$role], $light[$role]);
    if ($accent !== '') {
        array_unshift($out, $accent);   // honour the admin accent when it clears AA
    }
    return $out;
}

/** A palette colour as an rgba() string, for hairlines and decoration. */
function card_rgba(string $hex, float $alpha): string
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return 'rgba(11,78,61,' . $alpha . ')';
    }
    return 'rgba(' . hexdec(substr($hex, 0, 2)) . ',' . hexdec(substr($hex, 2, 2)) . ','
        . hexdec(substr($hex, 4, 2)) . ',' . $alpha . ')';
}

/** Primary body ink for a background: ivory/white on dark, near-black on light. */
function card_body_ink_for(string $bg): string
{
    return card_ink_for($bg, card_ink_candidates($bg, 'body'));
}

/** Secondary / label ink for a background — quieter than the body ink, still AA. */
function card_label_ink_for(string $bg, string $accent = ''): string
{
    return card_ink_for($bg, card_ink_candidates($bg, 'quiet', $accent));
}

/* =============================================================================
 |  ADMIN CARD CONFIGURATION
 |  Everything the admin screen (admin/membership-settings.php) can set, resolved
 |  once per request with a sane default for every key, so a fresh install with
 |  no settings rows at all renders exactly the audited default card.
 |============================================================================*/

/** setting key (without the membership_card_ prefix) => default value. */
function card_setting_defaults(): array
{
    return [
        'template'        => 'classic',
        'logo'            => '',   // '' = fall back to the site / theme brand logo
        'org_name'        => '',   // '' = site_name
        'tagline'         => '',   // '' = site_tagline
        'color_primary'   => '',   // '' = template default (header / footer / plaque base)
        'color_secondary' => '',   // '' = template default (header gradient end + labels)
        'color_accent'    => '',   // '' = template default (tier chip + eyebrow type)
        'bg'              => '',   // '' = template default (card body)
        'photo_pos'       => 'left',
        'qr_pos'          => 'right',
        'hidden_fields'   => '',   // CSV of field keys to HIDE; '' = show everything
        'benefits_source' => 'plan',
        'benefits_custom' => '',
        'footer_text'     => '',   // '' = the default two-line property notice
        'socials'         => '',   // CSV of platforms; '' = every active social link
        'signatory'       => '',
        'signatory_role'  => '',
        'signature'       => '',
        'terms'           => '',
        'helpline'        => '',
    ];
}

/**
 * Which palette colours may be used in which slot. Backgrounds that carry type
 * are limited to colours that have a brand ink clearing AA; the derived-ink
 * machinery above then picks that ink.
 */
function card_color_options(string $slot): array
{
    $p = card_palette();
    switch ($slot) {
        case 'bg':
            // The body field. Must be a calm surface, or the deliberate charcoal.
            return [
                $p['ivory'] => 'Ivory',
                $p['soft']  => 'Soft green-white',
                $p['white'] => 'White',
                $p['ink']   => 'Charcoal (dark card)',
            ];
        case 'accent':
            // Chip fills and eyebrow type. Light values read on a dark header.
            return [
                $p['yellow'] => 'Yellow',
                $p['gold']   => 'Gold',
                $p['orange'] => 'Orange',
                $p['white']  => 'White',
                $p['soft']   => 'Soft green-white',
            ];
        default:
            // Header / footer / plaque bases.
            return [
                $p['primary']   => 'Primary green',
                $p['dark']      => 'Deep green',
                $p['secondary'] => 'Secondary green',
                $p['ink']       => 'Charcoal',
                $p['gold']      => 'Gold',
                $p['orange']    => 'Orange',
            ];
    }
}

/** Snap a colour to the allowed set for a slot; '' (= template default) is kept. */
function card_color_for_slot(string $slot, ?string $hex): string
{
    $hex = strtolower(trim((string) $hex));
    if ($hex === '') {
        return '';
    }
    foreach (card_color_options($slot) as $value => $_label) {
        if ($hex === strtolower($value)) {
            return $value;
        }
    }
    return '';
}

/**
 * Every togglable field: key => [label, hint, face].
 *
 * Deliberately NOT here, so the admin screen carries no control that cannot do
 * anything: the tier chip and the status badge (the spec requires status to be
 * shown, and with a glyph, not colour alone) and the QR block, which is the
 * card's whole purpose. Everything listed below takes effect immediately,
 * because a hidden field is blanked and the renderers already drop a value-less
 * row.
 */
function card_field_keys(): array
{
    return [
        'logo'       => ['Logo',                 'Header, footer and the faint watermark.',       'both'],
        'tagline'    => ['Tagline',              'Small caps line under the organisation name.',  'front'],
        'photo'      => ['Photograph',           'Off = the branded monogram mark instead.',      'front'],
        'code'       => ['Member ID plaque',     'The member code block under the photo.',        'front'],
        'joined'     => ['Member since',         'From the join date.',                           'front'],
        'type'       => ['Membership type',      'Member type plus the plan billing period.',     'front'],
        'valid'      => ['Valid thru',           'From the expiry date.',                         'front'],
        'phone'      => ['Mobile',               'The member’s own number.',                     'front'],
        'email'      => ['Email',                'The member’s own address.',                    'front'],
        'address'    => ['Address',              'Address, city, state and pincode combined.',    'front'],
        'verify_url' => ['Verification address', 'The typed verification link.',                  'both'],
        'benefits'   => ['Benefits',             'Front strip and the back list.',                'both'],
        'signatory'  => ['Authorised signatory', 'Signature image, name and designation.',        'both'],
        'socials'    => ['Social links',         '“Follow us” marks in the front footer.',        'front'],
        'terms'      => ['Terms of use',         'Back face paragraph.',                          'back'],
        'contact'    => ['Organisation contact', 'Org email, phone and website on the back.',     'back'],
        'helpline'   => ['Helpline',             'Emergency / helpline number on the back.',      'back'],
        'cin'        => ['Issued-by line',       'The “Issued by … · CIN …” footer on the back.',  'back'],
    ];
}

/**
 * The resolved card configuration. Static-cached: card_theme() and card_data()
 * both read it, and a page can render many cards.
 */
function card_settings(bool $fresh = false): array
{
    static $cfg = null;
    if ($cfg !== null && !$fresh) {
        return $cfg;
    }

    $raw = [];
    foreach (card_setting_defaults() as $key => $default) {
        $raw[$key] = trim((string) get_setting('membership_card_' . $key, $default));
    }

    $raw['template'] = card_template_id($raw['template']);

    foreach (['color_primary' => 'base', 'color_secondary' => 'base', 'color_accent' => 'accent', 'bg' => 'bg'] as $key => $slot) {
        $raw[$key] = card_color_for_slot($slot, $raw[$key]);
    }

    // Photo and QR cannot occupy the same column; the QR yields.
    $raw['photo_pos'] = in_array($raw['photo_pos'], ['left', 'right'], true) ? $raw['photo_pos'] : 'left';
    $raw['qr_pos']    = in_array($raw['qr_pos'], ['left', 'right'], true) ? $raw['qr_pos'] : 'right';
    if ($raw['qr_pos'] === $raw['photo_pos']) {
        $raw['qr_pos'] = $raw['photo_pos'] === 'left' ? 'right' : 'left';
    }

    if (!in_array($raw['benefits_source'], ['plan', 'custom', 'plan_or_custom', 'none'], true)) {
        $raw['benefits_source'] = 'plan';
    }

    /* Hidden — not visible — fields. Storing the hidden set rather than the
       visible one matters: get_setting() returns the default for an empty
       string, so a "visible fields" CSV emptied by unticking everything would
       spring back to the default. An empty hidden set means "show everything",
       which is also the correct default for a field added in a later release. */
    $hidden = [];
    foreach (preg_split('/[,\s]+/', strtolower($raw['hidden_fields'])) ?: [] as $key) {
        if ($key !== '' && array_key_exists($key, card_field_keys())) {
            $hidden[$key] = true;
        }
    }
    $raw['hidden'] = $hidden;

    /* '' means "follow Social Links", so a platform added there later appears on
       the card by itself. That leaves no way to say "print none", which is what
       picking a narrowed set and then choosing nothing means — hence the explicit
       'none' sentinel rather than an empty list that reads as "all". */
    $raw['social_none'] = strtolower($raw['socials']) === 'none';
    $socials = [];
    if (!$raw['social_none']) {
        foreach (preg_split('/[,\s]+/', strtolower($raw['socials'])) ?: [] as $s) {
            if ($s !== '') {
                $socials[] = $s;
            }
        }
    }
    $raw['social_list'] = $socials;

    return $cfg = $raw;
}

/** Is a card field switched on? Unknown keys are visible. */
function card_field_on(string $key): bool
{
    return empty(card_settings()['hidden'][$key]);
}

/**
 * Per-template GEOMETRY, kept apart from the colour tokens so a template is a
 * real structural variant rather than a repaint. Consumed by the renderers.
 */
function card_layout(string $template): array
{
    $cfg = card_settings();
    $template = card_template_id($template);

    // classic
    $l = [
        'variant'    => 'classic',
        'radius'     => '1.15em',   // face corner radius
        'headStyle'  => 'wave',     // wave | arch | rail | flat
        'headGrad'   => true,       // gradient vs one flat tone
        'headSheen'  => true,       // the radial highlight in the header
        'rail'       => 0.0,        // width of a left vertical accent rail, in em
        'contours'   => true,       // the fine bezier contour lines
        'rings'      => true,       // the two concentric rings behind the QR
        'watermark'  => 0.04,       // logo watermark opacity (0 = off)
        'wedge'      => true,       // orange corner wedge
        'hairline'   => true,       // gold hairline along the top edge
        'photoShape' => 'rect',     // rect | arch | circle
        'photoRatio' => '4/5',
        'strip'      => 'bar',      // bar | chips | none  (benefits strip)
        'shadow'     => '0 22px 50px -22px rgba(4,26,18,.6)',
        'caps'       => '.14em',    // eyebrow letter-spacing
    ];

    if ($template === 'premium') {
        $l['variant']    = 'premium';
        $l['radius']     = '0.85em';   // tighter, more formal corner
        $l['headStyle']  = 'arch';     // a single wide arch instead of the wave
        $l['rings']      = false;
        $l['contours']   = true;
        $l['watermark']  = 0.07;       // reads on the charcoal field
        $l['wedge']      = false;      // no orange on a black-tie card
        $l['photoShape'] = 'arch';
        $l['strip']      = 'chips';    // pill-per-benefit rather than one bar
        $l['shadow']     = '0 26px 60px -24px rgba(0,0,0,.75)';
        $l['caps']       = '.2em';
    } elseif ($template === 'modern') {
        $l['variant']    = 'modern';
        $l['radius']     = '1.4em';
        $l['headStyle']  = 'rail';     // header sits beside a vertical accent rail
        $l['rail']       = 0.55;
        $l['rings']      = true;
        $l['contours']   = false;
        $l['watermark']  = 0.05;
        $l['photoShape'] = 'circle';
        $l['photoRatio'] = '1/1';
        $l['strip']      = 'bar';
        $l['caps']       = '.12em';
    } elseif ($template === 'minimal') {
        $l['variant']    = 'minimal';
        $l['radius']     = '0.45em';
        $l['headStyle']  = 'flat';     // a straight edge, no curve at all
        $l['headGrad']   = false;
        $l['headSheen']  = false;
        $l['contours']   = false;
        $l['rings']      = false;
        $l['watermark']  = 0.0;
        $l['wedge']      = false;
        $l['hairline']   = false;
        $l['photoShape'] = 'rect';
        $l['strip']      = 'none';     // benefits live on the back only
        $l['shadow']     = '0 10px 26px -18px rgba(4,26,18,.45)';
        $l['caps']       = '.16em';
    }

    // Admin overrides that are geometry rather than colour.
    $l['photoPos'] = $cfg['photo_pos'];
    $l['qrPos']    = $cfg['qr_pos'];

    return $l;
}

/**
 * Colour tokens for a template. Every foreground/background pairing here has
 * been measured against WCAG AA (see the module notes in the redesign report).
 * Admin colour choices are applied on top, and every ink is re-derived from the
 * chosen background so no configuration can drop a pair below AA.
 */
function card_theme(string $template, ?array $cfgOverride = null): array
{
    $p = card_palette();

    // classic
    $t = [
        'head1'       => $p['primary'],   // header gradient start
        'head2'       => $p['dark'],      // header gradient end
        'headInk'     => $p['white'],     // 9.67:1 on #0B4E3D
        'headSub'     => $p['yellow'],    // 7.95:1 on #0B4E3D
        'body'        => $p['ivory'],     // body field (also the wave fill)
        'ink'         => $p['ink'],       // 17.57:1 on #FEFEF1
        'label'       => $p['secondary'], //  9.54:1 on #FEFEF1
        'panel'       => $p['soft'],      // benefits strip
        'panelInk'    => $p['secondary'], //  9.36:1 on #F8FCF8
        'foot'        => $p['dark'],
        'footInk'     => $p['ivory'],     // 10.72:1 on #0F4537
        'footSub'     => $p['yellow'],    //  8.96:1 on #0F4537
        'plaque'      => $p['primary'],
        'plaqueInk'   => $p['white'],     //  9.67:1 on #0B4E3D
        'plaqueLabel' => $p['yellow'],    //  7.95:1 on #0B4E3D
        'chipBg'      => $p['yellow'],
        'chipInk'     => $p['primary'],   //  7.95:1 on #FFE987
        'frame'       => $p['primary'],   // photo / QR plate border
        'chipBorder'  => '',              // outline chips (minimal) — '' = filled
        'rule'        => 'rgba(11,78,61,.16)',
        'deco'        => 'rgba(11,78,61,.10)',
        'decoInk'     => $p['primary'],   // opaque decoration tone (the raster path)
        'qrPlate'     => $p['white'],
        'qrDark'      => $p['primary'],   //  9.67:1 against the white plate
    ];

    $template = card_template_id($template);

    if ($template === 'premium') {
        // Black-tie: charcoal field, gold furniture. (Supersedes the old 'dark'.)
        $t['head1']      = $p['dark'];
        $t['head2']      = $p['ink'];
        $t['body']       = $p['ink'];       // #151818
        $t['ink']        = $p['ivory'];     // 17.57:1 on #151818
        $t['label']      = $p['yellow'];    // 14.68:1 on #151818
        $t['panel']      = $p['dark'];
        $t['panelInk']   = $p['ivory'];     // 10.72:1 on #0F4537
        $t['foot']       = $p['dark'];
        $t['chipBg']     = $p['gold'];
        $t['chipInk']    = $p['ink'];       // 10.59:1 on #E8C52E
        $t['frame']      = $p['gold'];
        $t['rule']       = 'rgba(254,254,241,.20)';
        $t['deco']       = 'rgba(232,197,46,.12)';
        $t['decoInk']    = $p['gold'];
    } elseif ($template === 'modern') {
        $t['head1']      = $p['secondary']; // #174D3D
        $t['head2']      = $p['primary'];
        $t['body']       = $p['soft'];      // #F8FCF8
        $t['panel']      = $p['white'];
        $t['panelInk']   = $p['secondary']; //  9.70:1 on #FFFFFF
        $t['foot']       = $p['primary'];
        $t['footInk']    = $p['white'];     //  9.67:1 on #0B4E3D
        $t['footSub']    = $p['yellow'];    //  7.95:1 on #0B4E3D
    } elseif ($template === 'minimal') {
        // Flat, unornamented, print-cheap: one header tone, outline chips, rules
        // instead of fills.
        $t['head1']      = $p['secondary'];
        $t['head2']      = $p['secondary']; // no gradient
        $t['headInk']    = $p['white'];     //  9.70:1 on #174D3D
        $t['headSub']    = $p['yellow'];    //  7.97:1 on #174D3D
        $t['body']       = $p['white'];
        $t['ink']        = $p['ink'];       // 17.86:1 on #FFFFFF
        $t['label']      = $p['secondary']; //  9.70:1 on #FFFFFF
        $t['panel']      = $p['soft'];
        $t['panelInk']   = $p['secondary']; //  9.36:1 on #F8FCF8
        $t['foot']       = $p['secondary'];
        $t['footInk']    = $p['white'];     //  9.70:1 on #174D3D
        $t['footSub']    = $p['yellow'];    //  7.97:1 on #174D3D
        $t['plaque']     = $p['secondary'];
        $t['plaqueInk']  = $p['white'];     //  9.70:1 on #174D3D
        $t['plaqueLabel'] = $p['yellow'];   //  7.97:1 on #174D3D
        $t['chipBg']     = $p['soft'];
        $t['chipInk']    = $p['secondary']; //  9.36:1 on #F8FCF8
        $t['chipBorder'] = 'rgba(23,77,61,.30)';
        $t['frame']      = $p['secondary'];
        $t['rule']       = 'rgba(23,77,61,.14)';
        $t['deco']       = 'transparent';   // no contour lines at all
        $t['decoInk']    = $p['secondary'];
        $t['qrDark']     = $p['secondary']; //  9.70:1 against the white plate
    }

    return card_theme_apply_admin($t, $cfgOverride);
}

/**
 * Fold the admin's colour choices into a template's tokens.
 *
 * Only the BACKGROUNDS are configurable. Every ink is re-derived from the
 * background actually chosen, which is what makes the picker safe: there is no
 * combination of admin choices that can produce a text pair below WCAG AA,
 * because no foreground is ever stored.
 *
 * $cfg is explicit so the whole matrix of admin choices can be exercised without
 * writing to the settings table; it defaults to the saved configuration.
 */
function card_theme_apply_admin(array $t, ?array $cfg = null): array
{
    $cfg = $cfg ?? card_settings();
    $p   = card_palette();

    $primary   = card_color_for_slot('base',   $cfg['color_primary']   ?? '');
    $secondary = card_color_for_slot('base',   $cfg['color_secondary'] ?? '');
    $accent    = card_color_for_slot('accent', $cfg['color_accent']    ?? '');
    $bg        = card_color_for_slot('bg',     $cfg['bg']              ?? '');

    if ($primary === '' && $secondary === '' && $accent === '' && $bg === '') {
        return $t;   // untouched default — the audited card, byte for byte
    }

    /* One step deeper in the palette, for the gradient end when only the base
       colour was chosen. Staying inside the palette rules out card_darken(). */
    $deeper = [
        $p['primary']   => $p['dark'],
        $p['secondary'] => $p['primary'],
        $p['dark']      => $p['ink'],
        $p['ink']       => $p['dark'],
        $p['gold']      => $p['orange'],
        $p['orange']    => $p['ink'],
    ];

    /** The quiet/eyebrow ink for a set of backgrounds: the accent when it clears AA. */
    $sub = static function (array $backgrounds) use ($accent): string {
        return card_ink_for_multi($backgrounds, card_ink_candidates($backgrounds[0], 'quiet', $accent));
    };
    /** The strong ink for a set of backgrounds. */
    $strong = static function (array $backgrounds): string {
        return card_ink_for_multi($backgrounds, card_ink_candidates($backgrounds[0], 'strong'));
    };

    /* ---------------------------------------------------------- header base */
    if ($primary !== '') {
        $t['head1']  = $primary;
        $t['head2']  = $secondary !== '' ? $secondary : ($deeper[$primary] ?? $primary);
        $t['foot']   = $primary;
        $t['plaque'] = $primary;
        $t['frame']  = $primary;
    } elseif ($secondary !== '') {
        $t['head2'] = $secondary;
    }

    /* A gradient that runs from a light tone to a dark one has NO single ink that
       clears AA at both ends — gold -> deep green leaves white at 1.9:1 over the
       gold end. There is no ink to derive, so the gradient itself is what gives
       way: the header falls back to one flat tone. */
    $heads = [$t['head1'], $t['head2']];
    $probe = $strong($heads);
    if (min(card_contrast($t['head1'], $probe), card_contrast($t['head2'], $probe)) < 4.5) {
        $t['head2'] = $t['head1'];
        $heads = [$t['head1'], $t['head1']];
    }
    $t['headInk'] = $strong($heads);
    $t['headSub'] = $sub($heads);
    $t['footInk']     = $strong([$t['foot']]);
    $t['footSub']     = $sub([$t['foot']]);
    $t['plaqueInk']   = $strong([$t['plaque']]);
    $t['plaqueLabel'] = $sub([$t['plaque']]);

    /* --------------------------------------------------------------- accent */
    if ($accent !== '') {
        $t['chipBg']  = $accent;
        $t['chipInk'] = card_ink_for($accent, card_ink_candidates($accent, 'quiet'));
        if ($t['chipBorder'] !== '') {
            $t['chipBorder'] = card_rgba($t['chipInk'], 0.30);
        }
    }

    /* ----------------------------------------------------------------- body */
    if ($bg !== '') {
        $t['body']  = $bg;
        $t['ink']   = card_body_ink_for($bg);
        $t['label'] = ($secondary !== '' && card_contrast($bg, $secondary) >= 4.5)
            ? $secondary
            : card_label_ink_for($bg, $accent);
        $t['panel']    = card_is_dark($bg) ? $p['dark'] : ($bg === $p['soft'] ? $p['white'] : $p['soft']);
        $t['panelInk'] = card_is_dark($t['panel']) ? $p['ivory'] : card_label_ink_for($t['panel'], $accent);
        $t['rule']     = card_rgba($t['ink'], card_is_dark($bg) ? 0.20 : 0.16);
        if ($t['deco'] !== 'transparent') {
            $t['deco'] = card_rgba($t['label'], card_is_dark($bg) ? 0.12 : 0.10);
        }
        $t['decoInk'] = $t['label'];
    } elseif ($secondary !== '' && card_contrast($t['body'], $secondary) >= 4.5) {
        $t['label'] = $secondary;
    }

    /* ------------------------------------------------------------------- QR
       The modules must stay high-contrast on the plate or the code stops
       scanning, so this one is derived with a 7:1 floor whatever else changed. */
    $t['qrDark'] = card_ink_for($t['qrPlate'], [
        $t['frame'], $p['primary'], $p['dark'], $p['secondary'], $p['ink'],
    ], 7.0);

    return $t;
}

/* =============================================================================
 |  FONT RESOLUTION  (bundled -> system -> GD bitmap fallback)
 |============================================================================*/

/**
 * Resolve a usable TrueType font path for the given weight, or null when none
 * is found (callers then fall back to GD's built-in bitmap font).
 */
function card_font(string $weight = 'regular'): ?string
{
    static $cache = [];
    if (array_key_exists($weight, $cache)) {
        return $cache[$weight];
    }
    $bundled = BASE_PATH . '/assets/fonts';
    $candidates = [
        'bold' => [
            $bundled . '/Inter-Bold.ttf', $bundled . '/bold.ttf',
            'C:/Windows/Fonts/arialbd.ttf', 'C:/Windows/Fonts/segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial Bold.ttf',
        ],
        'semibold' => [
            $bundled . '/Inter-SemiBold.ttf', $bundled . '/semibold.ttf',
            'C:/Windows/Fonts/seguisb.ttf', 'C:/Windows/Fonts/arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ],
        'regular' => [
            $bundled . '/Inter-Regular.ttf', $bundled . '/regular.ttf',
            'C:/Windows/Fonts/arial.ttf', 'C:/Windows/Fonts/segoeui.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
        ],
    ];
    foreach ($candidates[$weight] ?? $candidates['regular'] as $path) {
        if (is_file($path)) {
            return $cache[$weight] = $path;
        }
    }
    return $cache[$weight] = null;
}

/* =============================================================================
 |  LOW-LEVEL GD HELPERS
 |============================================================================*/

/** Allocate a colour from a #rrggbb hex string. */
function card_color($img, string $hex, int $alpha = 0)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return $alpha > 0
        ? imagecolorallocatealpha($img, $r, $g, $b, $alpha)
        : imagecolorallocate($img, $r, $g, $b);
}

/** Draw text with a TTF when available, else GD's bitmap font (scaled). */
function card_text($img, int $size, int $x, int $y, $color, string $text, string $weight = 'regular'): void
{
    $font = card_font($weight);
    if ($font !== null) {
        // imagettftext y is the baseline; approximate with +size for top-left origin.
        imagettftext($img, $size, 0, $x, $y + $size, $color, $font, $text);
        return;
    }
    // Bitmap fallback: pick the largest built-in font and scale up if needed.
    $gdFont = 5;
    imagestring($img, $gdFont, $x, $y, $text, $color);
}

/** Measure TTF text width (0 when falling back to bitmap font). */
function card_text_width(int $size, string $text, string $weight = 'regular'): int
{
    $font = card_font($weight);
    if ($font === null) {
        return strlen($text) * 9;
    }
    $box = imagettfbbox($size, 0, $font, $text);
    return (int) abs($box[2] - $box[0]);
}

/** Draw text centred on $cx. */
function card_text_center($img, int $size, int $cx, int $y, $color, string $text, string $weight = 'regular'): void
{
    $w = card_text_width($size, $text, $weight);
    card_text($img, $size, (int) round($cx - $w / 2), $y, $color, $text, $weight);
}

/** Draw text whose right edge sits on $rx. */
function card_text_right($img, int $size, int $rx, int $y, $color, string $text, string $weight = 'regular'): void
{
    $w = card_text_width($size, $text, $weight);
    card_text($img, $size, $rx - $w, $y, $color, $text, $weight);
}

/**
 * Greedy word-wrap to a pixel width, capped at $maxLines (last one ellipsized).
 *
 * The cap USED TO SWALLOW THE REMAINDER IN SILENCE. On reaching $maxLines the old
 * loop broke, discarding the word in hand and every word after it, and then
 * ellipsized the last COMPLETED line — which by construction already fitted, so
 * no ellipsis was added. A three-word surname wrapped to two lines came out as two
 * lines with the third word simply gone and nothing to say so:
 * "Dr. Rajyalakshmi / Venkatanarasimharajuvaripeta" for
 * "…Venkatanarasimharajuvaripeta Balasubramaniam". On an identity document that is
 * worse than a visible truncation. The last permitted line now absorbs everything
 * that is left and is ellipsized, so text that does not fit always LOOKS like text
 * that did not fit.
 */
function card_wrap_lines(string $text, string $weight, int $size, int $maxW, int $maxLines): array
{
    $words = preg_split('/\s+/', trim($text)) ?: [];
    $lines = [];
    $cur   = '';
    $count = count($words);
    for ($i = 0; $i < $count; $i++) {
        $word = $words[$i];
        $try  = $cur === '' ? $word : $cur . ' ' . $word;
        if ($cur === '' || card_text_width($size, $try, $weight) <= $maxW) {
            $cur = $try;
            continue;
        }
        if (count($lines) + 1 === $maxLines) {
            $rest = implode(' ', array_slice($words, $i));
            $lines[] = card_fit_text($cur . ' ' . $rest, $weight, $size, $maxW);
            return $lines;
        }
        $lines[] = $cur;
        $cur = $word;
    }
    if ($cur !== '') {
        // A single unbreakable token wider than the box is ellipsized too.
        $lines[] = card_fit_text($cur, $weight, $size, $maxW);
    }
    return $lines;
}

/** Break a long unspaced string (a URL) into chunks that fit $maxW. */
function card_break_lines(string $text, string $weight, int $size, int $maxW, int $maxLines): array
{
    $lines = [];
    $cur   = '';
    $len   = mb_strlen($text);
    for ($i = 0; $i < $len; $i++) {
        $ch  = mb_substr($text, $i, 1);
        $try = $cur . $ch;
        if (card_text_width($size, $try, $weight) > $maxW && $cur !== '') {
            $lines[] = $cur;
            $cur = $ch;
            if (count($lines) === $maxLines) {
                return $lines;
            }
        } else {
            $cur = $try;
        }
    }
    if ($cur !== '') {
        $lines[] = $cur;
    }
    return $lines;
}

/** Filled rounded rectangle. */
function card_rounded_rect($img, int $x1, int $y1, int $x2, int $y2, int $r, $color): void
{
    $r = max(0, min($r, (int) (($x2 - $x1) / 2), (int) (($y2 - $y1) / 2)));
    imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as [$cx, $cy]) {
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
    }
}

/** Rounded rectangle outline of $w px, drawn as two nested filled shapes. */
function card_rounded_outline($img, int $x1, int $y1, int $x2, int $y2, int $r, int $w, $stroke, $fill = null): void
{
    card_rounded_rect($img, $x1, $y1, $x2, $y2, $r, $stroke);
    if ($fill !== null) {
        card_rounded_rect($img, $x1 + $w, $y1 + $w, $x2 - $w, $y2 - $w, max(0, $r - $w), $fill);
    }
}

/** Vertical gradient fill between two hex colours. */
function card_gradient($img, int $x1, int $y1, int $x2, int $y2, string $from, string $to): void
{
    $from = ltrim($from, '#');
    $to   = ltrim($to, '#');
    $fr = hexdec(substr($from, 0, 2)); $fg = hexdec(substr($from, 2, 2)); $fb = hexdec(substr($from, 4, 2));
    $tr = hexdec(substr($to, 0, 2));   $tg = hexdec(substr($to, 2, 2));   $tb = hexdec(substr($to, 4, 2));
    $h = max(1, $y2 - $y1);
    for ($i = 0; $i <= $h; $i++) {
        $t = $i / $h;
        $c = imagecolorallocate($img,
            (int) round($fr + ($tr - $fr) * $t),
            (int) round($fg + ($tg - $fg) * $t),
            (int) round($fb + ($tb - $fb) * $t));
        imagefilledrectangle($img, $x1, $y1 + $i, $x2, $y1 + $i, $c);
    }
}

/** Mix $a over $b at $ratio (0..1) and return the flat hex result. */
function card_mix(string $a, string $b, float $ratio): string
{
    $a = ltrim($a, '#');
    $b = ltrim($b, '#');
    $out = '';
    for ($i = 0; $i < 3; $i++) {
        $ca = hexdec(substr($a, $i * 2, 2));
        $cb = hexdec(substr($b, $i * 2, 2));
        $out .= sprintf('%02x', (int) round($cb + ($ca - $cb) * max(0.0, min(1.0, $ratio))));
    }
    return '#' . $out;
}

/** Darken a hex colour by a factor (0..1). */
function card_darken(string $hex, float $factor = 0.75): string
{
    $hex = ltrim($hex, '#');
    $r = (int) (hexdec(substr($hex, 0, 2)) * $factor);
    $g = (int) (hexdec(substr($hex, 2, 2)) * $factor);
    $b = (int) (hexdec(substr($hex, 4, 2)) * $factor);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/**
 * Fill the area under an organic wave with $fill, optionally laying a band of
 * $band immediately above it — the raster stand-in for the HTML card's curved
 * header/body transition and its gold accent line.
 */
function card_wave($img, int $x1, int $x2, int $baseY, int $bottomY, int $amp, string $fill, ?string $band = null, int $bandH = 0): void
{
    $span   = max(1, $x2 - $x1);
    $sample = [];
    for ($x = $x1; $x <= $x2; $x += 6) {
        $t = ($x - $x1) / $span;
        $y = $baseY + (int) round($amp * sin($t * M_PI * 1.7 + 0.45));
        $sample[] = [$x, $y];
    }
    $sample[] = [$x2, $baseY + (int) round($amp * sin(M_PI * 1.7 + 0.45))];

    if ($band !== null && $bandH > 0) {
        $pts = [];
        foreach ($sample as [$x, $y]) { $pts[] = $x; $pts[] = $y - $bandH; }
        $pts[] = $x2; $pts[] = $bottomY;
        $pts[] = $x1; $pts[] = $bottomY;
        imagefilledpolygon($img, $pts, card_color($img, $band));
    }
    $pts = [];
    foreach ($sample as [$x, $y]) { $pts[] = $x; $pts[] = $y; }
    $pts[] = $x2; $pts[] = $bottomY;
    $pts[] = $x1; $pts[] = $bottomY;
    imagefilledpolygon($img, $pts, card_color($img, $fill));
}

/* =============================================================================
 |  QR + IMAGES
 |============================================================================*/

/**
 * Draw a QR (from its module matrix) onto $img inside a plate.
 * Extra arguments are optional so existing callers (student_card.php) are
 * unaffected.
 */
function card_draw_qr($img, string $payload, int $x, int $y, int $box, string $dark = '#111827', ?string $border = null, int $radius = 16): void
{
    $white = imagecolorallocate($img, 255, 255, 255);
    if ($border !== null) {
        card_rounded_outline($img, $x, $y, $x + $box, $y + $box, $radius, max(2, (int) round($box / 55)), card_color($img, $border), $white);
    } else {
        card_rounded_rect($img, $x, $y, $x + $box, $y + $box, $radius, $white);
    }

    $ink   = card_color($img, $dark);
    $qr    = PWF_QR::encode($payload, 'M');
    $m     = $qr->matrix();
    $n     = $qr->size;
    /* MODULE SIZE IS THE WHOLE POINT OF THIS ARITHMETIC. It floors to whole
       pixels, so every pixel of padding that does not have to be here costs a
       full step of module size on the printed card. Measured on the PDF at
       600 DPI with the production payload (~87 chars -> 41-45 modules): at
       quiet=3 and 10% padding the module came out 5-6px = 0.212-0.254mm, and
       green-on-white below ~0.25mm after inkjet dot gain is where phone cameras
       start failing. quiet=2 with 4% padding yields 6-7px = 0.254-0.296mm.
       The white plate itself supplies the rest of the quiet zone. */
    $quiet = 2;
    $grid  = $n + 2 * $quiet;
    $inner = $box - (int) round($box * 0.04);   // padding inside the plate
    $mod   = (int) floor($inner / $grid);
    if ($mod < 1) { $mod = 1; }
    $span   = $mod * $grid;
    $ox     = $x + (int) (($box - $span) / 2);
    $oy     = $y + (int) (($box - $span) / 2);
    for ($row = 0; $row < $n; $row++) {
        for ($col = 0; $col < $n; $col++) {
            if ($m[$row][$col]) {
                $px = $ox + ($col + $quiet) * $mod;
                $py = $oy + ($row + $quiet) * $mod;
                imagefilledrectangle($img, $px, $py, $px + $mod - 1, $py + $mod - 1, $ink);
            }
        }
    }
}

/** Load an image stored under UPLOAD_PATH as a GD image, or null. */
function card_load_photo(?string $avatar)
{
    if (empty($avatar)) {
        return null;
    }
    $path = UPLOAD_PATH . '/' . ltrim($avatar, '/');
    if (!is_file($path)) {
        return null;
    }
    $data = @file_get_contents($path);
    if ($data === false) {
        return null;
    }
    $src = @imagecreatefromstring($data); // auto-detects jpeg/png/gif/webp
    return $src ?: null;
}

/**
 * The configured brand logo as a GD image (uploaded logo first, then the
 * bundled asset). Never fetches over the network.
 */
function card_load_logo()
{
    // The card may carry its own mark (a mono/print version of the brand logo).
    $token = card_settings()['logo'];
    if ($token !== '' && card_field_on('logo')) {
        $img = card_load_photo($token);
        if ($img) {
            return $img;
        }
    }
    if (!card_field_on('logo')) {
        return null;
    }
    $token = '';
    if (function_exists('theme_get')) {
        $token = trim((string) theme_get('brand.logo'));
    }
    if ($token === '') {
        $token = trim((string) get_setting('site_logo', ''));
    }
    if ($token !== '') {
        $img = card_load_photo($token);
        if ($img) {
            return $img;
        }
    }
    foreach (['/assets/images/logo-256.webp', '/assets/images/logo-128.webp', '/assets/images/logo.jpg'] as $rel) {
        $path = BASE_PATH . $rel;
        if (is_file($path)) {
            $data = @file_get_contents($path);
            $img  = $data !== false ? @imagecreatefromstring($data) : false;
            if ($img) {
                return $img;
            }
        }
    }
    return null;
}

/** Centre-crop $src into ($dx,$dy,$dw,$dh) of $dst. */
function card_cover($dst, $src, int $dx, int $dy, int $dw, int $dh, float $focusY = 0.32): void
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $target = $dw / max(1, $dh);
    $cropW = $sw;
    $cropH = (int) round($sw / $target);
    if ($cropH > $sh) {
        $cropH = $sh;
        $cropW = (int) round($sh * $target);
    }
    $sx = (int) round(($sw - $cropW) / 2);
    $sy = (int) round(($sh - $cropH) * $focusY);   // bias upward so a face stays in frame
    imagecopyresampled($dst, $src, $dx, $dy, $sx, $sy, $dw, $dh, $cropW, $cropH);
}

/* =============================================================================
 |  STATUS
 |  Colour is never the only signal: every badge carries its label, and the HTML
 |  card adds an outline glyph.
 |============================================================================*/

/**
 * The state shown on the card. member_effective_status() covers the membership
 * (none|active|expired|cancelled); an account hold outranks it, because a
 * suspended or unverified account cannot be presented as an active member.
 */
function card_status_key(array $member): string
{
    $status  = member_effective_status($member);
    $account = strtolower(trim((string) ($member['status'] ?? 'active')));
    if ($account === 'suspended') {
        return 'suspended';
    }
    if ($account === 'pending' && !in_array($status, ['expired', 'cancelled'], true)) {
        return 'pending';
    }
    return $status;
}

/** [label, background, ink, icon name] for a status key. Ink is AA on the fill. */
function card_status_meta(string $key): array
{
    $p = card_palette();
    $map = [
        'active'    => [$p['primary'], $p['yellow'],    'check'],  // 7.95:1
        'pending'   => [$p['yellow'],  $p['ink'],       'clock'],  // 14.68:1
        'expired'   => [$p['orange'],  $p['ink'],       'alert'],  // 5.30:1
        'suspended' => [$p['ink'],     $p['ivory'],     'ban'],    // 17.57:1
        'cancelled' => [$p['ink'],     $p['ivory'],     'close'],  // 17.57:1
        'none'      => [$p['soft'],    $p['secondary'], 'user'],   // 9.36:1
    ];
    [$bg, $ink, $icon] = $map[$key] ?? $map['none'];
    return [
        'label' => membership_status_label($key),
        'bg'    => $bg,
        'ink'   => $ink,
        'icon'  => $icon,
    ];
}

/**
 * The status pill's own edge colour, or '' when the pill needs none.
 *
 * card_status_meta() returns FIXED palette fills, deliberately — the state of a
 * credential is not a thing an admin should be able to restyle. But a fixed fill
 * is the one component outside the derive-from-background machinery, and it was
 * measured disappearing completely:
 *
 *   premium (body #151818): suspended 1.00:1, cancelled 1.00:1, active 1.85:1
 *   — the pill shape vanishes into the card and only the glyph and text survive.
 *   Same on `classic` the moment an admin picks "Charcoal (dark card)", which is
 *   an offered option in card_color_options('bg').
 *
 * The ink-on-fill pair always passed; nobody was checking FILL vs CARD. A second
 * failure mode is adjacency: in the `pending` state the fill is #FFE987, the
 * same yellow as the tier chip beside it, so the two pills merge into one shape.
 *
 * So: whenever the fill is within 3:1 of either the card body or the neighbouring
 * tier chip, the pill takes a 1px inset edge that clears 3:1 against BOTH the
 * fill and the body. Nothing is drawn when the fill already stands on its own.
 */
function card_status_edge(array $sm, string $body, string $chipBg = ''): string
{
    /* Only the surfaces the edge actually has to separate the pill FROM are
       constraints. Asking for 3:1 against the card body when the fill already
       clears 9:1 against it just narrows the candidate list for no reason, and
       card_ink_for_multi() then returns its least-bad option — an edge invisible
       against the very surface that needed the separation. */
    $against = [$sm['bg']];
    if (card_contrast($sm['bg'], $body) < 3.0) {
        $against[] = $body;
    }
    if ($chipBg !== '' && card_contrast($sm['bg'], $chipBg) < 3.0) {
        $against[] = $chipBg;
    }
    if (count($against) === 1) {
        return '';                      // the fill stands on its own
    }
    $p = card_palette();
    return card_ink_for_multi($against, [
        $sm['ink'], $p['ink'], $p['ivory'], $p['primary'], $p['secondary'], $p['white'],
    ], 3.0);
}

/* =============================================================================
 |  ICONS  (self-contained outline SVG — lucide() needs JS, which the standalone
 |  admin print page and the raster path do not have)
 |============================================================================*/

function card_icon(string $name, string $size = '1em', float $stroke = 1.9): string
{
    $paths = [
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'ban'      => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
        'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'users'    => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 21c0-3.9 2.9-6.4 6.5-6.4s6.5 2.5 6.5 6.4"/><path d="M16.5 4.9a3.4 3.4 0 0 1 0 6.4"/><path d="M18 14.9c2.2.7 3.5 2.6 3.5 5.1"/>',
        'award'    => '<circle cx="12" cy="9" r="6"/><path d="M8.6 14.2 7 22l5-3 5 3-1.6-7.8"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'file'     => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
        'book'     => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v18H6.5A2.5 2.5 0 0 0 4 22Z"/><path d="M8 7h8M8 11h6"/>',
        'heart'    => '<path d="M12 20s-7-4.4-7-9.4A4.1 4.1 0 0 1 12 7.6 4.1 4.1 0 0 1 19 10.6c0 5-7 9.4-7 9.4Z"/>',
        'shield'   => '<path d="M12 2.6 4.5 5.6v6c0 5 3.2 8.2 7.5 9.8 4.3-1.6 7.5-4.8 7.5-9.8v-6Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'star'     => '<path d="m12 3 2.7 5.6 6.1.8-4.5 4.3 1.1 6.1L12 17l-5.4 2.8 1.1-6.1L3.2 9.4l6.1-.8Z"/>',
        'ticket'   => '<path d="M3 9.5V6.5A1.5 1.5 0 0 1 4.5 5h15A1.5 1.5 0 0 1 21 6.5v3a2.5 2.5 0 0 0 0 5v3a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-3a2.5 2.5 0 0 0 0-5Z"/><path d="M12 8v8"/>',
        'mail'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="m3.5 7 8.5 6 8.5-6"/>',
        'phone'    => '<path d="M6 3h3l2 5-2.4 1.6a12 12 0 0 0 5.8 5.8L16 13l5 2v3a2 2 0 0 1-2.2 2A17 17 0 0 1 4 6.2 2 2 0 0 1 6 3Z"/>',
        'pin'      => '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
        'scan'     => '<path d="M4 8V6a2 2 0 0 1 2-2h2"/><path d="M16 4h2a2 2 0 0 1 2 2v2"/><path d="M20 16v2a2 2 0 0 1-2 2h-2"/><path d="M8 20H6a2 2 0 0 1-2-2v-2"/><path d="M4 12h16"/>',
        'pen'      => '<path d="M3 20.5 8 19l11-11a2.5 2.5 0 0 0-3.5-3.5L4.5 15.5Z"/><path d="M14.5 5.5 18.5 9.5"/>',
        'buoy'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.6"/><path d="m5.6 5.6 3.8 3.8M14.6 14.6l3.8 3.8M18.4 5.6l-3.8 3.8M9.4 14.6l-3.8 3.8"/>',
        'info'     => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
        'card'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19"/><path d="M6 14.5h4"/>',
    ];
    $body = $paths[$name] ?? $paths['check'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $stroke . '" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" '
        . 'style="width:' . $size . ';height:' . $size . ';display:block;flex:none;">' . $body . '</svg>';
}

/** Pick an icon that suits a benefit line (presentation only, never content). */
function card_benefit_icon(string $text): string
{
    $t = strtolower($text);
    $map = [
        'certificat' => 'award',   'certif' => 'award',    'award' => 'award',
        'event'      => 'calendar', 'invit' => 'calendar', 'agm' => 'calendar', 'meet' => 'calendar',
        'report'     => 'file',    'newsletter' => 'file', 'update' => 'file',  'brief' => 'file',
        'train'      => 'book',    'course' => 'book',     'skill' => 'book',   'learn' => 'book', 'workshop' => 'book',
        'health'     => 'heart',   'medical' => 'heart',   'care' => 'heart',   'donat' => 'heart',
        'vote'       => 'shield',  'right' => 'shield',    'protect' => 'shield',
        'recogni'    => 'star',    'website' => 'star',    'featur' => 'star',
        'discount'   => 'ticket',  'priority' => 'ticket', 'fee' => 'ticket',   'access' => 'ticket',
        'communit'   => 'users',   'network' => 'users',   'volunteer' => 'users', 'member' => 'users',
    ];
    foreach ($map as $needle => $icon) {
        if (str_contains($t, $needle)) {
            return $icon;
        }
    }
    return 'check';
}

/* =============================================================================
 |  DATA  — one contract, shared by the HTML card and the raster
 |  Values are RAW here; card_html() escapes, GD draws.
 |============================================================================*/

/** Benefit lines for a plan, in order, cleaned. Empty when the plan has none. */
function card_benefit_lines(?array $plan, int $limit = 8): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) ($plan['benefits'] ?? '')) as $line) {
        // Strip list bullets multibyte-safely; a byte-wise trim() charlist can
        // shear a UTF-8 sequence and leave a stray byte behind.
        $line = (string) preg_replace('/^[\s\x{00A0}\x{2022}\x{00B7}\x{2013}\x{2014}\-*]+/u', '', strip_tags($line));
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));
        if ($line !== '') {
            $out[] = $line;
        }
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/**
 * Social links for the card footer, request-cached. Never fatal when the table
 * is absent. The admin may narrow the set to a chosen few platforms; an empty
 * choice means "every active link", so the card follows Social Links by default.
 */
function card_socials(): array
{
    static $rows = null;
    if ($rows === null) {
        $rows = [];
        try {
            $rows = db_all('SELECT platform, url FROM social_links WHERE status = 1 ORDER BY sort_order ASC, id ASC');
        } catch (Throwable $e) {
            $rows = [];
        }
    }
    $cfg = card_settings();
    if (!card_field_on('socials') || $cfg['social_none']) {
        return [];
    }
    $only = $cfg['social_list'];
    if (!$only) {
        return $rows;
    }
    $out = [];
    foreach ($rows as $row) {
        if (in_array(strtolower(trim((string) ($row['platform'] ?? ''))), $only, true)) {
            $out[] = $row;
        }
    }
    return $out;
}

/** The platforms an admin may put on the card, from the live Social Links table. */
function card_social_platforms(): array
{
    $out = [];
    try {
        foreach (db_all('SELECT platform FROM social_links WHERE status = 1 ORDER BY sort_order ASC, id ASC') as $row) {
            $slug = strtolower(trim((string) ($row['platform'] ?? '')));
            if ($slug !== '' && !in_array($slug, $out, true)) {
                $out[] = $slug;
            }
        }
    } catch (Throwable $e) {
        return [];
    }
    return $out;
}

/** Authorised-signatory block, entirely from settings. All parts optional. */
function card_signatory(): array
{
    if (!card_field_on('signatory')) {
        return ['image' => '', 'name' => '', 'role' => ''];
    }
    $cfg = card_settings();
    return [
        'image' => $cfg['signature'],
        'name'  => $cfg['signatory'],
        'role'  => $cfg['signatory_role'],
    ];
}

/**
 * The benefit lines the card should print, honouring the admin's chosen source:
 * the member's plan, an organisation-wide list, the plan with the list as a
 * fallback, or nothing at all.
 */
function card_benefits_source(?array $plan, int $limit = 8): array
{
    if (!card_field_on('benefits')) {
        return [];
    }
    $cfg    = card_settings();
    $source = $cfg['benefits_source'];
    if ($source === 'none') {
        return [];
    }
    $custom = card_benefit_lines(['benefits' => $cfg['benefits_custom']], $limit);
    if ($source === 'custom') {
        return $custom;
    }
    $fromPlan = card_benefit_lines($plan, $limit);
    if ($source === 'plan_or_custom') {
        return $fromPlan !== [] ? $fromPlan : $custom;
    }
    return $fromPlan;
}

/**
 * The card footer notice. Admin-configurable, one line per line, with {org},
 * {email}, {phone} and {site} placeholders. Falls back to the default property
 * notice so a fresh install still prints a complete card.
 */
function card_footer_lines(array $tokens): array
{
    $text = card_settings()['footer_text'];
    if ($text === '') {
        $text = "This card is the property of {org}.\nIf found, please return to the nearest office.";
    }
    $map = [];
    foreach ($tokens as $key => $value) {
        $map['{' . $key . '}'] = (string) $value;
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim((string) preg_replace('/\s+/u', ' ', strip_tags(strtr($line, $map))));
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return array_slice($out, 0, 3);
}

/**
 * Everything the two renderers need, resolved once from the DB + settings.
 */
function card_data(array $member): array
{
    $member = member_ensure_identity($member);
    $plan   = membership_plan((int) ($member['plan_id'] ?? 0));
    $cfg    = card_settings();

    /* Field visibility. A hidden field is BLANKED rather than special-cased,
       because the renderers already drop a row whose value is empty — so one
       rule ("no value, no row") covers both "the member has not filled it in"
       and "the admin switched it off". The handful of blocks that cannot be
       expressed as an empty string (tier chip, status badge, QR) are published
       on ['show'] instead. */
    $on = static fn (string $key): bool => card_field_on($key);

    $statusKey  = card_status_key($member);
    $effective  = member_effective_status($member);
    $enrolled   = in_array($effective, ['active', 'expired'], true);

    /* A card whose membership has been WITHDRAWN must not keep asserting the
       things a live card asserts. $valid used to read expiry_date whenever it was
       non-empty, independent of the status, and the benefits came straight off the
       plan with no status gate — so a cancelled member's card printed
       "VALID THRU 31 Mar 2027", the full three-item benefits strip and the back's
       "MEMBERSHIP INCLUDES" list, while verify-member.php told the person checking
       it "This membership has been cancelled." The credential contradicted its own
       verifier. Suspended behaved identically. */
    $revoked = in_array($statusKey, ['suspended', 'cancelled'], true);

    $code = trim((string) ($member['member_code'] ?? ''));
    $name = trim((string) ($member['name'] ?? ''));

    /* Honest states. "Lifetime" used to print whenever expiry_date was null,
       which is also true of someone who never enrolled — so a card could read
       "VALID THRU Lifetime" next to "Not enrolled". Only an actual membership
       gets a validity; otherwise the row is dropped. */
    $joined = !empty($member['join_date']) ? format_date($member['join_date'], 'M Y') : '';
    $valid  = '';
    if (!$revoked) {
        $valid = !empty($member['expiry_date'])
            ? format_date($member['expiry_date'], 'd M Y')
            : ($enrolled ? 'Lifetime' : '');
    }

    /* One explanatory line, so a card with rows missing reads as deliberate
       rather than broken. Empty for the ordinary active card. */
    $note = '';
    if ($revoked) {
        $note = $statusKey === 'suspended'
            ? 'This membership is suspended and is not valid for verification.'
            : 'This membership has been cancelled and is not valid for verification.';
    } elseif (!$enrolled && $joined === '' && $valid === '') {
        $note = 'Membership activates once a plan is confirmed.';
    }

    // MEMBERSHIP TYPE: the member's own type plus the plan's billing duration.
    $typeBits = [];
    $mType = trim((string) ($member['type'] ?? ''));
    if ($mType !== '') {
        $typeBits[] = ucwords(str_replace(['_', '-'], ' ', $mType));
    }
    if (!empty($plan['duration'])) {
        $typeBits[] = trim((string) $plan['duration']);
    }

    // ADDRESS: composed from whatever exists; omitted entirely when nothing does.
    $line = array_values(array_filter([
        trim((string) ($member['address'] ?? '')),
        trim((string) ($member['city'] ?? '')),
        trim((string) ($member['state'] ?? '')),
    ], static fn ($v) => $v !== ''));
    $address = implode(', ', $line);
    $pin = trim((string) ($member['pincode'] ?? ''));
    if ($pin !== '') {
        $address = $address === '' ? $pin : $address . ' ' . $pin;
    }

    /* Photo. `avatar` is the uploaded file; `avatar_url` is a remote OAuth
       picture, which neither renderer used to read at all. */
    $avatar   = trim((string) ($member['avatar'] ?? ''));
    $remote   = trim((string) ($member['avatar_url'] ?? ''));
    $photoSrc = '';
    if ($avatar !== '') {
        $photoSrc = upload_url($avatar);
    } elseif ($remote !== '' && preg_match('#^https?://#i', $remote)) {
        $photoSrc = $remote;
    }

    $initials = '';
    foreach (preg_split('/\s+/', $name) ?: [] as $part) {
        if ($part !== '' && preg_match('/^\p{L}/u', $part)) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($initials) === 2) {
            break;
        }
    }

    /* QR PAYLOAD — deliberately the opaque per-member token, not the printed
       code. member_code is sequential (EIF-2026-00007), so a code URL can be
       walked 00001..99999 to harvest the register; the 32-hex token cannot.
       The human-readable /verify/member/{code} route is printed as text for
       anyone typing it by hand, and is throttled server-side. */
    $verifyQr    = member_verify_url($member);
    $verifyHuman = $code !== '' ? abs_url('verify/member/' . rawurlencode($code)) : $verifyQr;

    $sig = card_signatory();

    /* Organisation identity. The card may override the site's own name, tagline
       and logo — a print-safe mono mark and a shorter legal name are the usual
       reasons — and falls back to the site values when it does not. */
    $siteName = trim((string) get_setting('site_name', SITE_NAME));
    $org      = $cfg['org_name'] !== '' ? $cfg['org_name'] : $siteName;
    $tagline  = $cfg['tagline']  !== '' ? $cfg['tagline']  : trim((string) get_setting('site_tagline', ''));
    $logo     = $cfg['logo'] !== '' ? upload_url($cfg['logo']) : brand_logo_url();

    $orgEmail = trim((string) get_setting('contact_email', SITE_EMAIL));
    $orgPhone = trim((string) get_setting('contact_phone', SITE_PHONE));
    $orgSite  = (string) preg_replace('#^https?://#i', '', rtrim(abs_url(''), '/'));

    $verifyText = (string) preg_replace('#^https?://#i', '', $verifyHuman);

    /* TIER. member_tier_label() answers `$plan['name'] ?? 'Member'`, so a member
       with plan_id = NULL — which is every member in this database, including the
       only active one — got a tier chip reading the invented word "Member". On a
       row whose own name is empty the front then read "Member" beside "Member".
       No plan, no tier: the chip is dropped like any other value-less row. */
    $tier = trim((string) ($plan['name'] ?? ''));

    /* TIER ACCENT. This key used to be published and never read by either
       renderer — computed, snapped and discarded. It is now honoured, but only
       when the plan's stored colour IS one of the ten brand colours: a plan
       coloured #b45309 must not repaint the chip, and card_palette_snap()'s
       fallback to the primary green would otherwise turn every plan without a
       colour into a green-on-green chip (1.3:1 — the banned pair). Everything
       else keeps the audited template chip. */
    $accent = '';
    $planColor = strtolower(trim((string) ($plan['color'] ?? '')));
    if ($planColor !== '' && strtolower(card_palette_snap($planColor)) === $planColor) {
        $accent = card_palette_snap($planColor);
    }

    return [
        'member'      => $member,
        'plan'        => $plan,
        'org'         => $org,
        'tagline'     => $on('tagline') ? $tagline : '',
        'logo'        => $on('logo') ? $logo : '',
        'name'        => $name,
        /* The MEMBER's initials, or nothing. This used to fall back to the first
           letter of the ORGANISATION, which on a photo-less card with an empty
           name printed the foundation's initial in the position that reads as the
           member's monogram. No name, no monogram — the silhouette mark alone is
           the branded fallback. */
        'initials'    => $initials,
        // The org's own initial, for the logo disc when no logo image is configured.
        'orgInitial'  => mb_strtoupper(mb_substr($org !== '' ? $org : $siteName, 0, 1)),
        'code'        => $on('code') ? $code : '',
        'memberCode'  => $code,                       // always present (filenames, audit)
        'tier'        => $tier,
        'accent'      => $accent,
        'status'      => $statusKey,
        'enrolled'    => $enrolled,
        'revoked'     => $revoked,
        'note'        => $note,
        'joined'      => $on('joined') ? $joined : '',
        'valid'       => $on('valid') ? $valid : '',
        'type'        => $on('type') ? implode(' · ', $typeBits) : '',
        'email'       => $on('email') ? trim((string) ($member['email'] ?? '')) : '',
        'phone'       => $on('phone') ? trim((string) ($member['phone'] ?? '')) : '',
        'address'     => $on('address') ? $address : '',
        'photo'       => $on('photo') ? $photoSrc : '',
        'photoFile'   => $on('photo') ? $avatar : '',
        'verifyQr'    => $verifyQr,
        'verifyHuman' => $verifyHuman,
        'verifyText'  => $on('verify_url') ? $verifyText : '',
        'verifyHumanText' => $verifyText,   // never blanked: the QR's text equivalent
        // A withdrawn membership grants nothing, so it lists nothing.
        'benefits'    => $revoked ? [] : card_benefits_source($plan, 8),
        'terms'       => $on('terms') ? $cfg['terms'] : '',
        'helpline'    => $on('helpline') ? $cfg['helpline'] : '',
        'orgEmail'    => $on('contact') ? $orgEmail : '',
        'orgPhone'    => $on('contact') ? $orgPhone : '',
        'orgSite'     => $on('contact') ? $orgSite : '',
        'cin'         => $on('cin') ? trim((string) get_setting('cin', SITE_CIN)) : '',
        'footer'      => card_footer_lines([
            'org'   => $org,
            'site'  => $orgSite,
            'email' => $orgEmail,
            'phone' => $orgPhone,
        ]),
        // Geometry is NOT resolved here: the template actually being rendered is
        // the renderer's argument (a ?template= preview may override the default),
        // so a renderer calls card_layout($template) beside card_theme($template).
        'show'        => [
            'logo'      => $on('logo'),
            'photo'     => $on('photo'),   // false = draw the branded mark, not the photograph
            'benefits'  => $on('benefits'),
            'signatory' => $on('signatory'),
            'socials'   => $on('socials'),
            'cin'       => $on('cin'),
        ],
        'signature'   => $sig['image'],
        'signatory'   => $sig['name'],
        'signRole'    => $sig['role'],
        'socials'     => card_socials(),
    ];
}

/* =============================================================================
 |  ON-SCREEN HTML CARD  (front + back, inline styles, self-contained)
 |============================================================================*/

/** Force any SVG we drop into the card to obey its box. */
function card_svg_fit(string $svg, string $extra = 'width:100%;height:auto;display:block'): string
{
    return preg_replace('/<svg\b/', '<svg style="' . $extra . '"', $svg, 1) ?? $svg;
}

/**
 * A self-contained HTML/CSS membership credential — front and back faces.
 *
 * Styles are inline so the markup drops into any layout unchanged (member page,
 * admin member detail, standalone admin print page). The one shared <style>
 * block — the flip and the ID-1 ratio floor, neither of which can be expressed
 * inline — is emitted once per request no matter how many cards render.
 */
function card_html(array $member, string $template = 'classic'): string
{
    /* card_template_id(), not array_key_exists(): the pre-4-template id 'dark'
       is not a key of card_templates(), so a stored 'dark' fell through to
       classic HERE while both card pages resolved it to premium through
       card_template_id() — the caller and the renderer disagreed about which
       card was being drawn. */
    $tplId = card_template_id($template);
    $d = card_data($member);
    $t = card_theme($tplId);
    /* GEOMETRY. card_layout() was published, documented on the admin screen
       ("each template is a different structure — header shape, photo shape,
       benefits treatment and corner radius — not just a repaint"), rendered in
       the admin's four template thumbnails … and never read by the renderer, so
       every real card drew the classic geometry in a different palette. */
    $l = card_layout($tplId);

    $p = card_palette();
    $head1 = $t['head1']; $head2 = $t['head2']; $headInk = $t['headInk']; $headSub = $t['headSub'];
    $body  = $t['body'];  $ink   = $t['ink'];   $label   = $t['label'];
    $panel = $t['panel']; $panelInk = $t['panelInk'];
    $foot  = $t['foot'];  $footInk = $t['footInk']; $footSub = $t['footSub'];
    $plaque = $t['plaque']; $plaqueInk = $t['plaqueInk']; $plaqueLabel = $t['plaqueLabel'];
    $chipBg = $t['chipBg']; $chipInk = $t['chipInk'];
    $frame = $t['frame']; $rule = $t['rule']; $deco = $t['deco'];
    $gold  = $p['gold']; $yellow = $p['yellow']; $orange = $p['orange'];

    $org      = e($d['org']);
    $tagline  = e($d['tagline']);
    $logo     = e($d['logo']);
    /* An empty name used to print the word "Member" — an invented name on an
       identity document, and (before the tier chip started dropping) the front
       read "Member" directly above "Member". Empty means the line is not drawn;
       the MEMBER ID plaque and the chips are real data and still identify the
       card. */
    $nameHtml = $d['name'] === '' ? '' :
        '<div style="font-size:1.85em;font-weight:800;line-height:1.05;letter-spacing:-.02em;color:' . $ink
        . ';text-wrap:balance;overflow-wrap:anywhere;">' . e($d['name']) . '</div>';
    $code     = e($d['code']);
    $tier     = e($d['tier']);
    $verifyTx = e($d['verifyText']);
    $caps     = $l['caps'];

    /* ---------------------------------------------------------------- QR --
       PWF_QR emits fixed intrinsic width/height, which overflowed the column
       and pushed content past the card edge. Every SVG is forced to its box.

       quiet=3 (not 2): the QR spec asks for a 4-module quiet zone. The white
       plate around the code supplies roughly one more module of margin, so 3 in
       the SVG plus the plate lands on spec without shrinking the modules as far
       as a full 4 would.

       The label is the DESTINATION, not "QR code" — but the destination is the
       opaque token URL, and putting a bearer token into an accessible name puts
       it into the a11y tree, the DOM text and any screen-reader log. So the
       graphic is aria-hidden and the human-readable verification address is
       exposed as real text below it (see $qrAlt): the sighted and the
       non-sighted route both end at the same place, and neither carries the
       token. */
    $qrSvg = PWF_QR::encode($d['verifyQr'], 'M')->svg(3, 4, $t['qrDark'], $t['qrPlate']);
    $qrSvg = str_replace('role="img" aria-label="QR code"', 'aria-hidden="true" focusable="false"', $qrSvg);
    $qrSvg = card_svg_fit($qrSvg);

    /* The text equivalent of the QR. $d['verifyText'] can be switched off by the
       admin's "Verification address" field toggle, which used to leave the
       destination inside an image called "QR code" and nothing else — a 1.1.1
       failure reachable through supported configuration. When the visible line is
       off, the same address ships as screen-reader-only text. */
    $srOnly = 'position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;'
        . 'clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0;';
    $qrAlt = $d['verifyText'] !== '' ? '' :
        '<span style="' . $srOnly . '">Verify this membership at ' . e($d['verifyHumanText']) . '</span>';

    /* --------------------------------------------------------- status chip */
    $sm       = card_status_meta($d['status']);
    $chipBgEff = $d['accent'] !== '' ? $d['accent'] : $chipBg;
    $chipInkEff = $d['accent'] !== ''
        ? card_ink_for($d['accent'], card_ink_candidates($d['accent'], 'quiet'))
        : $chipInk;
    $chipEdge = card_status_edge($sm, $body, $tier !== '' ? $chipBgEff : '');
    $statusChip = '<span style="display:inline-flex;align-items:center;gap:.3em;flex:none;'
        . 'padding:.28em .6em;border-radius:999px;font-size:.64em;font-weight:800;letter-spacing:.1em;'
        . 'text-transform:uppercase;background:' . $sm['bg'] . ';color:' . $sm['ink'] . ';'
        . ($chipEdge !== '' ? 'box-shadow:inset 0 0 0 1px ' . $chipEdge . ';' : '')
        . '">' . card_icon($sm['icon'], '1.15em', 2.4) . '<span>' . e($sm['label']) . '</span></span>';

    /* The tier chip is a value like any other: no plan, no chip. */
    $tierChip = $tier === '' ? '' :
        '<span style="flex:none;padding:.28em .6em;border-radius:999px;background:' . $chipBgEff . ';'
        . 'color:' . $chipInkEff . ';font-size:.64em;font-weight:800;letter-spacing:.1em;'
        . 'text-transform:uppercase;'
        . ($t['chipBorder'] !== '' ? 'box-shadow:inset 0 0 0 1px ' . $t['chipBorder'] . ';' : '')
        . '">' . $tier . '</span>';

    /* ------------------------------------------------------ decoration ---
       Organic curves, contour lines and a watermark, all behind the content.
       Which of them appear is the template's geometry, not a constant. */
    $watermark = ($logo !== '' && $l['watermark'] > 0)
        ? '<img src="' . $logo . '" alt="" aria-hidden="true" '
          . 'style="position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:44%;max-width:230px;'
          . 'opacity:' . $l['watermark'] . ';pointer-events:none;">'
        : '';
    $decoShapes = '';
    if ($l['contours']) {
        $decoShapes .= '<path d="M-40 300 C170 236 268 356 452 316 C636 276 742 150 1052 196"/>'
            . '<path d="M-40 352 C176 288 276 408 460 368 C644 328 750 202 1052 248"/>'
            . '<path d="M-40 404 C182 340 284 460 468 420 C652 380 758 254 1052 300"/>'
            . '<path d="M-30 620 C150 560 300 640 470 596 C640 552 820 596 1042 548"/>';
    }
    if ($l['rings']) {
        $decoShapes .= '<circle cx="906" cy="212" r="158"/><circle cx="906" cy="212" r="112"/>';
    }
    $decoSvg = $decoShapes === '' ? '' : <<<DSVG
<svg viewBox="0 0 1012 638" preserveAspectRatio="none" focusable="false"
     style="position:absolute;inset:0;width:100%;height:100%;display:block;">
  <g fill="none" stroke="{$deco}" stroke-width="1.6">{$decoShapes}</g>
</svg>
DSVG;
    $decoLayer = <<<DECO
<span aria-hidden="true" style="position:absolute;inset:0;overflow:hidden;pointer-events:none;z-index:0;">
  {$decoSvg}
  {$watermark}
</span>
DECO;

    /* The wedge has to sit ABOVE the header (the header's own background covers the
       corner, so a z-index:0 wedge would simply not exist), which means its size is
       a collision risk rather than a cosmetic choice. At clamp(26px,7.5%,56px) it
       measured 42px at a 560px card, extending 30.9px along its 225 degree axis
       while the right-aligned eyebrow's nearest corner sat at 30.5px — 0.6px INSIDE
       the orange, with the glyph tops clearing by ~0.8px, i.e. the entire margin
       was the current font's ascent. A theme-engine font change or another
       browser's rounding would have put #FFE987 on #F15A24 (2.77:1). Halved, it
       reaches 18.5px and clears by ~12px. */
    $wedge = $l['wedge']
        ? '<span aria-hidden="true" style="position:absolute;top:0;right:0;width:clamp(18px,4.5%,30px);'
          . 'aspect-ratio:1;pointer-events:none;z-index:2;'
          . 'background:linear-gradient(225deg,' . $orange . ' 0%,' . $orange . ' 52%,transparent 52%);"></span>'
        : '';

    $hairline = $l['hairline']
        ? '<span aria-hidden="true" style="position:absolute;top:0;left:0;right:0;height:3px;z-index:3;'
          . 'pointer-events:none;background:linear-gradient(90deg,' . $gold . ',' . $yellow . ' 55%,' . $gold . ');"></span>'
        : '';
    $railBar = $l['rail'] > 0
        ? '<span aria-hidden="true" style="position:absolute;top:0;bottom:0;left:0;width:' . $l['rail'] . 'em;'
          . 'z-index:3;pointer-events:none;background:' . $chipBgEff . ';"></span>'
        : '';

    /* ---------------------------------------------- header bottom edge ---
       'wave'  the organic curve that eats into the header, gold band riding it
       'arch'  one wide formal arch
       'rail'  a straight gold band (the accent is the vertical rail instead)
       'flat'  nothing at all */
    if ($l['headStyle'] === 'wave' || $l['headStyle'] === 'arch') {
        [$pGold, $pBody] = $l['headStyle'] === 'wave'
            ? ['M0,22 C168,58 336,-8 512,12 C688,32 852,52 1012,16 L1012,64 L0,64 Z',
               'M0,30 C168,66 336,0 512,20 C688,40 852,60 1012,24 L1012,64 L0,64 Z']
            : ['M0,4 C258,66 754,66 1012,4 L1012,64 L0,64 Z',
               'M0,13 C258,68 754,68 1012,13 L1012,64 L0,64 Z'];
        $waveSvg = <<<WAVE
<svg viewBox="0 0 1012 64" preserveAspectRatio="none" aria-hidden="true" focusable="false"
     style="position:absolute;left:0;right:0;bottom:-1px;width:100%;height:clamp(13px,3.2vw,24px);display:block;">
  <path d="{$pGold}" fill="{$gold}"/>
  <path d="{$pBody}" fill="{$body}"/>
</svg>
WAVE;
    } elseif ($l['headStyle'] === 'rail') {
        $waveSvg = '<span aria-hidden="true" style="position:absolute;left:0;right:0;bottom:0;height:3px;'
            . 'background:' . $gold . ';"></span>';
    } else {
        $waveSvg = '';
    }
    /* A curve eats into the header, so the padding under it has to be bigger than
       for a flat edge or the eyebrow row sits inside the curve. */
    $headPadB = $l['headStyle'] === 'wave' || $l['headStyle'] === 'arch'
        ? 'clamp(17px,4.4%,30px)' : 'clamp(9px,2.4%,16px)';
    $headBg = ($l['headSheen'] ? 'radial-gradient(120% 140% at 90% -10%, rgba(255,233,135,.18), transparent 60%),' : '')
        . ($l['headGrad']
            ? 'linear-gradient(150deg,' . $head1 . ' 0%,' . $head2 . ' 100%)'
            : $head1);

    /* ----------------------------------------------------------- header --- */
    $taglineHtml = $tagline === '' ? '' :
        '<span style="display:block;font-size:.58em;font-weight:700;letter-spacing:.13em;'
        . 'text-transform:uppercase;color:' . $headSub . ';margin-top:.15em;">' . $tagline . '</span>';
    $logoHtml = $logo === '' ? '' :
        '<img src="' . $logo . '" alt="" width="44" height="44" style="width:clamp(26px,7.4%,44px);height:auto;'
        . 'aspect-ratio:1;border-radius:50%;background:' . $p['white'] . ';padding:2px;box-sizing:border-box;'
        . 'object-fit:contain;flex:none;box-shadow:0 0 0 1.5px ' . $gold . ';">';

    /* Icons are built before the heredocs: a heredoc interpolates every {$var}
       it contains, so a placeholder-and-str_replace dance would print nothing. */
    $usersIcon = card_icon('users', '1.15em', 1.9);
    $cardIcon  = card_icon('card', '1.1em', 1.9);

    $headerHtml = <<<HEAD
<div style="position:relative;padding:clamp(9px,2.2%,15px) clamp(12px,3%,20px) {$headPadB};
     background:{$headBg};">
  <div style="position:relative;z-index:1;display:flex;align-items:center;gap:.6em;">
    {$logoHtml}
    <div style="min-width:0;line-height:1.1;">
      <strong style="display:block;font-size:.92em;font-weight:800;letter-spacing:.02em;
              text-transform:uppercase;color:{$headInk};overflow-wrap:anywhere;">{$org}</strong>
      {$taglineHtml}
    </div>
    <div style="margin-left:auto;flex:none;display:flex;align-items:center;gap:.35em;color:{$headSub};">
      <span style="display:block;width:1.15em;">
        {$usersIcon}
      </span>
      <span style="font-size:.62em;font-weight:800;letter-spacing:{$caps};">MEMBERSHIP CARD</span>
    </div>
  </div>
  {$waveSvg}
</div>
HEAD;

    /* ------------------------------------------------------------- photo ---
       Real photograph when one exists; otherwise a deliberate branded mark —
       a silhouette on a brand tint under a monogram chip, never a bare initial.
       align-self is load-bearing: in a row with align-items:stretch the frame
       would be pulled to full height and aspect-ratio ignored. */
    if ($d['photo'] !== '') {
        $photoInner = '<img src="' . e($d['photo']) . '" alt="" '
            . 'style="width:100%;height:100%;object-fit:cover;object-position:50% 28%;display:block;">';
    } else {
        /* aria-hidden on the monogram: it is a decorative stand-in for a missing
           photograph, and as live text a photo-less member's card announced "AC"
           immediately before "Ananya Chatterjee". */
        $initials = $d['initials'] === '' ? '' : e($d['initials']);
        $monogram = $initials === '' ? '' :
            '<span style="position:absolute;left:50%;bottom:7%;transform:translateX(-50%);background:' . $plaque
            . ';color:' . $plaqueInk . ';font-size:.62em;font-weight:800;letter-spacing:.08em;'
            . 'padding:.18em .5em;border-radius:999px;white-space:nowrap;">' . $initials . '</span>';
        $photoInner = <<<PH
<span aria-hidden="true" style="position:relative;display:block;width:100%;height:100%;
      background:linear-gradient(165deg,{$panel} 0%,{$body} 100%);">
  <svg viewBox="0 0 80 100" aria-hidden="true" focusable="false" style="width:100%;height:auto;display:block;">
    <g fill="{$frame}" opacity=".17">
      <circle cx="40" cy="36" r="15"/>
      <path d="M9 100c0-24 14-36 31-36s31 12 31 36Z"/>
    </g>
    <g fill="none" stroke="{$frame}" stroke-opacity=".22" stroke-width="1.3">
      <path d="M0 84c17-8 29 5 49-4 13-6 22 1 31-3"/>
      <path d="M0 92c17-8 29 5 49-4 13-6 22 1 31-3"/>
    </g>
  </svg>
  {$monogram}
</span>
PH;
    }

    /* Photo frame geometry is the template's, not a constant. */
    $photoRadius = '.7em';
    if ($l['photoShape'] === 'circle') {
        $photoRadius = '50%';
    } elseif ($l['photoShape'] === 'arch') {
        $photoRadius = '46% 46% .55em .55em / 34% 34% .55em .55em';
    }
    $photoRatio = $l['photoRatio'];

    $idPlaque = $code === '' ? '' : <<<IDP
<span style="display:block;margin-top:.4em;background:{$plaque};border-radius:.6em;
      padding:.34em .3em;text-align:center;">
  <span style="display:block;font-size:.56em;font-weight:800;letter-spacing:.14em;color:{$plaqueLabel};">MEMBER ID</span>
  <strong style="display:block;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
          font-size:.72em;font-weight:700;letter-spacing:.02em;color:{$plaqueInk};
          overflow-wrap:anywhere;">{$code}</strong>
</span>
IDP;

    /* ------------------------------------------------------- labelled rows --
       A label above an empty value reads as a rendering fault, so a row with no
       value is not emitted at all. */
    $row = static function (string $lbl, string $value, bool $wide = false, string $size = '.9em') use ($label, $ink): string {
        if (trim($value) === '') {
            return '';
        }
        return '<div style="min-width:0;' . ($wide ? 'grid-column:1/-1;' : '') . '">'
            . '<div style="font-size:.58em;font-weight:800;letter-spacing:.14em;text-transform:uppercase;'
            . 'color:' . $label . ';line-height:1.3;">' . e($lbl) . '</div>'
            . '<div style="font-size:' . $size . ';font-weight:700;color:' . $ink . ';line-height:1.3;'
            . 'overflow-wrap:anywhere;">' . e($value) . '</div></div>';
    };
    /* EMAIL spans both columns: in a half-column an address like
       ananya.chatterjee@example.org broke mid-word across two lines. */
    $rows = $row('Member since', $d['joined'])
        . $row('Membership type', $d['type'])
        . $row('Valid thru', $d['valid'])
        . $row('Mobile', $d['phone'], false, '.82em')
        . $row('Email', $d['email'], true, '.78em')
        . $row('Address', $d['address'], true, '.76em');

    /* Not-enrolled, suspended and cancelled cards say so in words, so the missing
       rows read as deliberate. Never a label above blank space, never a dash. */
    if ($d['note'] !== '') {
        $rows .= '<div style="grid-column:1/-1;font-size:.68em;line-height:1.45;color:' . $label . ';">'
            . e($d['note']) . '</div>';
    }

    /* ------------------------------------------------------- signatory ---- */
    $signBlock = '';
    $signParts = '';
    if ($d['signature'] !== '') {
        $signParts .= '<img src="' . e(upload_url($d['signature'])) . '" alt="" '
            . 'style="display:block;width:100%;max-width:100%;height:auto;max-height:2.6em;'
            . 'object-fit:contain;object-position:center bottom;margin:0 auto .15em;">';
    }
    if ($d['signatory'] !== '') {
        $signParts .= '<strong style="display:block;font-size:.66em;font-weight:800;color:' . $ink . ';'
            . 'line-height:1.25;overflow-wrap:anywhere;">' . e($d['signatory']) . '</strong>';
    }
    if ($d['signRole'] !== '') {
        $signParts .= '<span style="display:block;font-size:.58em;font-weight:700;letter-spacing:.06em;'
            . 'text-transform:uppercase;color:' . $label . ';">' . e($d['signRole']) . '</span>';
    }
    if ($signParts !== '') {
        $signBlock = '<span style="display:block;margin-top:auto;padding-top:.45em;text-align:center;'
            . 'border-top:1px solid ' . $rule . ';">' . $signParts
            . '<span style="display:block;font-size:.56em;font-weight:700;letter-spacing:.1em;'
            . 'text-transform:uppercase;color:' . $label . ';margin-top:.15em;">Authorised signatory</span>'
            . '</span>';
    }

    /* --------------------------------------------------------- benefits ---
       Straight from the plan, and nothing at all for a withdrawn membership. No
       plan benefits, no strip — the card never invents an entitlement.
       'bar'   one panel with dividers   (classic / modern)
       'chips' a pill per benefit        (premium)
       'none'  back face only            (minimal) */
    $stripItems = '';
    $stripCount = 0;
    $stripStyle = $l['strip'];
    if ($stripStyle !== 'none') {
        foreach (array_slice($d['benefits'], 0, 5) as $i => $b) {
            $stripCount++;
            if ($stripStyle === 'chips') {
                $stripItems .= '<span style="display:inline-flex;align-items:center;gap:.3em;min-width:0;'
                    . 'padding:.16em .55em;border-radius:999px;color:' . $panelInk . ';'
                    . 'box-shadow:inset 0 0 0 1px ' . $rule . ';">'
                    . card_icon(card_benefit_icon($b), '.9em', 1.9)
                    . '<span style="font-size:.58em;font-weight:700;line-height:1.3;overflow:hidden;'
                    . 'text-overflow:ellipsis;white-space:nowrap;">' . e($b) . '</span></span>';
            } else {
                $stripItems .= '<span style="flex:1 1 0;min-width:0;display:flex;align-items:center;gap:.3em;'
                    . 'padding:0 .5em;color:' . $panelInk . ';'
                    . ($i > 0 ? 'border-left:1px solid ' . $rule . ';' : '') . '">'
                    . card_icon(card_benefit_icon($b), '.95em', 1.9)
                    . '<span style="font-size:.6em;font-weight:700;line-height:1.25;overflow:hidden;'
                    . 'text-overflow:ellipsis;white-space:nowrap;">' . e($b) . '</span></span>';
            }
        }
    }
    if ($stripCount === 0) {
        $benefitStrip = '';
    } elseif ($stripStyle === 'chips') {
        $benefitStrip = '<div style="display:flex;flex-wrap:wrap;gap:.3em;background:' . $panel . ';'
            . 'border-top:1px solid ' . $rule . ';border-bottom:1px solid ' . $rule . ';'
            . 'padding:.42em clamp(12px,3%,20px);">' . $stripItems . '</div>';
    } else {
        $benefitStrip = '<div style="display:flex;align-items:stretch;background:' . $panel . ';'
            . 'border-top:1px solid ' . $rule . ';border-bottom:1px solid ' . $rule . ';'
            . 'padding:.42em .5em;">' . $stripItems . '</div>';
    }

    /* ----------------------------------------------------------- socials --- */
    $socialHtml = '';
    foreach ($d['socials'] as $s) {
        $platform = trim((string) ($s['platform'] ?? ''));
        $href     = trim((string) ($s['url'] ?? ''));
        if ($platform === '' || $href === '') {
            continue;
        }
        $svg = function_exists('social_svg') ? social_svg($platform, 'pwf-card-soc') : '';
        if (!str_contains($svg, '<svg')) {
            $svg = card_icon('globe', '100%', 2.0);   // unknown platform: neutral mark
        }
        $svg = card_svg_fit($svg, 'width:100%;height:auto;display:block;fill:currentColor');
        /* The GLYPH stays 1.05em; the LINK is padded out to a 24x24 CSS-px hit
           area. As bare 1.05em anchors these measured 10x10 at a 414px viewport,
           failing SC 2.5.8 (Target Size, Minimum) — and they are standalone icon
           links, so the inline exception does not apply. */
        $socialHtml .= '<a href="' . e($href) . '" target="_blank" rel="noopener noreferrer nofollow" '
            . 'title="' . e(ucfirst($platform)) . '" aria-label="' . e(ucfirst($platform)) . '" '
            . 'style="display:inline-flex;align-items:center;justify-content:center;min-width:24px;'
            . 'min-height:24px;color:' . $footSub . ';">'
            . '<span style="display:block;width:1.05em;">' . $svg . '</span></a>';
    }
    $socialBlock = $socialHtml === '' ? '' :
        '<div style="flex:none;text-align:right;">'
        . '<span style="display:block;font-size:.56em;font-weight:800;letter-spacing:.16em;color:' . $footSub . ';">FOLLOW US</span>'
        . '<span style="display:flex;gap:.1em;justify-content:flex-end;margin-top:.1em;margin-right:-4px;">'
        . $socialHtml . '</span></div>';

    $footLogo = $logo === '' ? '' : '<img src="' . $logo . '" alt="" width="30" height="30" '
        . 'style="width:1.75em;height:auto;aspect-ratio:1;border-radius:50%;background:' . $p['white'] . ';'
        . 'padding:1.5px;box-sizing:border-box;object-fit:contain;flex:none;">';

    /* ============================ BACK-FACE BLOCKS ======================== */
    $sectionTitle = static function (string $text, string $icon) use ($label): string {
        return '<div style="display:flex;align-items:center;gap:.3em;font-size:.58em;font-weight:800;'
            . 'letter-spacing:.14em;text-transform:uppercase;color:' . $label . ';margin-bottom:.35em;">'
            . card_icon($icon, '1.1em', 2.1) . '<span>' . e($text) . '</span></div>';
    };

    $backBenefits = '';
    if ($d['benefits']) {
        $li = '';
        foreach ($d['benefits'] as $b) {
            $li .= '<li style="display:flex;align-items:flex-start;gap:.35em;margin:0 0 .22em;color:' . $ink . ';">'
                . card_icon(card_benefit_icon($b), '.9em', 2.0)
                . '<span style="font-size:.66em;line-height:1.4;">' . e($b) . '</span></li>';
        }
        $backBenefits = $sectionTitle('Membership includes', 'award')
            . '<ul style="list-style:none;margin:0 0 .7em;padding:0;">' . $li . '</ul>';
    }

    $backTerms = $d['terms'] === '' ? '' :
        $sectionTitle('Terms of use', 'info')
        . '<p style="margin:0 0 .7em;font-size:.62em;line-height:1.45;color:' . $ink . ';">' . e($d['terms']) . '</p>';

    $backVerify = $sectionTitle('How to verify', 'scan')
        . '<p style="margin:0 0 .25em;font-size:.64em;line-height:1.45;color:' . $ink . ';">'
        . ($verifyTx !== ''
            ? 'Scan the code on the front with any phone camera, or open the address below in a browser.'
            : 'Scan the code on the front with any phone camera.')
        . '</p>'
        . ($verifyTx !== ''
            ? '<strong style="display:block;font-size:.62em;font-weight:800;color:' . $label . ';overflow-wrap:anywhere;'
              . 'margin-bottom:.7em;">' . $verifyTx . '</strong>'
            : '<span style="' . $srOnly . '">Verify at ' . e($d['verifyHumanText']) . '</span>');

    /* $kind is a REAL text label, not just the icon. The icons are correctly
       aria-hidden, which left a screen reader hearing an address, a phone number,
       a bare domain and — when configured — a second bare phone number, with
       nothing saying which was the helpline (1.3.3 / 1.1.1). The label is
       screen-reader-only for the three obvious rows and VISIBLE for the helpline,
       because "which of these two numbers is the emergency one" is a question a
       sighted reader also cannot answer from an icon. */
    $contactRow = static function (string $icon, string $kind, string $value, string $href = '', bool $showKind = false) use ($ink, $label, $srOnly): string {
        if (trim($value) === '') {
            return '';
        }
        $text = $href === '' ? e($value)
            : '<a href="' . e($href) . '" style="color:inherit;text-decoration:none;">' . e($value) . '</a>';
        $kindHtml = $showKind
            ? '<span style="font-weight:800;color:' . $label . ';">' . e($kind) . ' </span>'
            : '<span style="' . $srOnly . '">' . e($kind) . ' </span>';
        return '<span style="display:flex;align-items:center;gap:.35em;color:' . $ink . ';margin-bottom:.16em;">'
            . '<span style="color:' . $label . ';display:block;">' . card_icon($icon, '.92em', 2.0) . '</span>'
            . '<span style="font-size:.64em;overflow-wrap:anywhere;">' . $kindHtml . $text . '</span></span>';
    };
    $contactBits = $contactRow('mail', 'Email:', $d['orgEmail'], 'mailto:' . $d['orgEmail'])
        . $contactRow('phone', 'Telephone:', $d['orgPhone'], 'tel:' . preg_replace('/[^0-9+]/', '', $d['orgPhone']))
        . $contactRow('globe', 'Website:', $d['orgSite'])
        . $contactRow('buoy', 'Helpline:', $d['helpline'] !== '' ? $d['helpline'] : '', '', true);
    $backContact = $contactBits === '' ? '' : $sectionTitle('Contact', 'card') . $contactBits;

    $backSign = '';
    if ($signParts !== '') {
        // margin-top:auto — the back is stretched to the front's height, so the
        // signatory sits on the bottom edge instead of floating in the middle.
        $backSign = '<div style="margin-top:auto;padding-top:.45em;border-top:1px solid ' . $rule . ';text-align:center;">'
            . $signParts
            . '<span style="display:block;font-size:.56em;font-weight:700;letter-spacing:.1em;'
            . 'text-transform:uppercase;color:' . $label . ';margin-top:.15em;">Authorised signatory</span></div>';
    }

    $issuedBits = [];
    if ($d['org'] !== '')  { $issuedBits[] = 'Issued by ' . $d['org']; }
    if ($d['cin'] !== '')  { $issuedBits[] = 'CIN ' . $d['cin']; }
    $issuedLine = e(implode(' · ', $issuedBits));

    /* The back's left column carries benefits and terms. With no plan and no terms
       configured — the live state for every member in this database — it rendered
       as an empty half at the front's full height. It is now dropped entirely, so
       the verification and contact column uses the width, and a member whose state
       needs explaining gets that explanation on both faces. No filler copy: when
       there is nothing to say, nothing is said. */
    $backNote = $d['note'] === '' ? '' :
        $sectionTitle('Membership status', 'info')
        . '<p style="margin:0 0 .7em;font-size:.64em;line-height:1.45;color:' . $ink . ';">' . e($d['note']) . '</p>';
    $backLeftCol = ($backBenefits === '' && $backTerms === '' && $backNote === '') ? '' :
        '<div style="flex:1.15;min-width:0;">' . $backBenefits . $backTerms . $backNote . '</div>';

    /* ------------------------------------------------- shared shell CSS --
       The flip, the ratio floor and the type scale cannot be inlined, so they go
       into one <style> emitted once per request however many cards a page renders.

       WHY THE COLUMN GEOMETRY IS HERE AND NOT INLINE: an inline style beats any
       non-!important rule, so every structural value the container query has to
       override must live in this sheet. The stacking rule shipped for the card's
       whole life and had never once executed, because the two-column
       grid-template-columns was set inline on the same element. Colour, spacing
       and everything template-specific stays inline as before. */
    static $shellPrinted = false;
    $shell = '';
    if (!$shellPrinted) {
        $shellPrinted = true;
        $shell = <<<'CSS'
<style>
/* width:100% is load-bearing, not decoration. max-width alone leaves the set
   shrink-to-fit, and inside any flex/grid/table parent it collapses toward
   min-content — which rendered the card as a one-character-wide strip. */
.pwf-cardset{width:100%;max-width:560px;margin-inline:auto;}
.pwf-flip{position:relative;perspective:1900px;}
.pwf-flip-inner{position:relative;display:grid;transform-style:preserve-3d;
    transition:transform .7s cubic-bezier(.22,1,.36,1);}
.pwf-flip-inner>.pwf-face{grid-area:1/1;}
.pwf-flip.is-back .pwf-flip-inner{transform:rotateY(180deg);}
.pwf-face{backface-visibility:hidden;-webkit-backface-visibility:hidden;}
.pwf-face.back{transform:rotateY(180deg);}
/* The hidden face is taken out of the a11y tree AND the tab order by the flip
   script (inert + aria-hidden). backface-visibility is paint-only: without this
   a screen reader read the whole card twice and keyboard focus landed on
   invisible mailto:/tel:/social links — 2.4.3 and 2.4.7. */
.pwf-face[inert]{pointer-events:none;}
/* An ID-1 ratio FLOOR, not a ratio box. `aspect-ratio` is a hard height: a long
   name, a wrapped address or an extra line was simply cut off at the edge. The
   pseudo-element shares the card's single grid cell, so the face is at least
   1012:638 and grows when the content needs more room — it can never crop.
   Both faces are grid items of one row, so they are always the same box. */
/* container-type:inline-size applies INLINE-SIZE CONTAINMENT: the box sizes as
   though it had no contents, so without an explicit width it resolves to zero
   and every child wraps to one character per line. The width and the single
   minmax(0,1fr) column give the grid a definite basis to lay out against. */
.pwf-card{display:grid;grid-template-columns:minmax(0,1fr);width:100%;container-type:inline-size;}
.pwf-card::before{content:"";grid-area:1/1;width:0;min-width:0;padding-top:63.05%;}
/* THE TYPE SCALE IS A FUNCTION OF THE CARD, NOT THE WINDOW.
   This was clamp(9.5px,2.05vw,14px) on the face, and 2.05vw is the VIEWPORT: at
   320/375/414px it pinned to the 9.5px floor (so "SCAN TO VERIFY" rendered at
   4.75px), while the three desktop columns stayed intact and the ID-1 ratio only
   came out right at exactly one card width — 0.921 at a 340px card, i.e. an "ID
   card" taller than it is wide. In print, vw resolves against the PAGE, so a
   14px base landed in an 86mm box and the card printed 86x97.5mm to 86x170mm
   instead of 54mm.
   cqw fixes all three at once: 2.5cqw of the 560px design width is 14px, and it
   holds in a narrow admin column and on a print sheet alike. It goes on the BODY,
   not the face: the face IS the query container, and an element cannot size
   itself from its own container units.
   The rem floor (not px) is what lets a user's larger default font size and
   text-only zoom enlarge the card at all — 1.4.4. */
.pwf-card>.pwf-card-body{grid-area:1/1;min-width:0;font-size:clamp(.62rem,2.5cqw,.95rem);}
.pwf-card svg{max-width:100%;}
.pwf-card a{text-decoration:none;}
.pwf-card .pwf-front-row{flex:1;display:flex;align-items:flex-start;}
.pwf-card .pwf-photo-col{flex:none;width:clamp(60px,19%,104px);align-self:flex-start;}
.pwf-card .pwf-mid-col{flex:1;min-width:0;display:flex;flex-direction:column;gap:.45em;}
.pwf-card .pwf-qr-col{flex:none;width:clamp(74px,24%,124px);display:flex;flex-direction:column;
    align-self:stretch;text-align:center;}
.pwf-card .pwf-qr-plate{flex:none;}
.pwf-card .pwf-qr-meta{min-width:0;display:flex;flex-direction:column;}
.pwf-card .pwf-facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));}
/* Below 360px of CARD width (not viewport — the card also sits in a narrow admin
   panel) the desktop three-column front stops being readable however well it
   scales, so it becomes two rows: photo + identity, then the QR block across the
   full width. The type floor is raised at the same time, which trades some of
   the ID-1 ratio for legibility — deliberately: at 288px you can have an ID-1
   rectangle or type above 8px, not both.
   The breakpoint is 360 rather than 430 on measurement: at a 420px card the two
   fact columns are 135px each and perfectly readable, and reflowing there turned
   a 1.36-ratio card into a 0.82-ratio column — which is what admin/members.php
   and the settings preview rail would have rendered. */
@container (max-width:360px){
  .pwf-card .pwf-facts{grid-template-columns:minmax(0,1fr);}
  .pwf-card .pwf-front-row{flex-wrap:wrap;}
  .pwf-card .pwf-qr-col{width:100%;flex-direction:row;align-items:center;gap:.7em;text-align:left;
      align-self:auto;margin-top:.5em;}
  .pwf-card .pwf-qr-plate{width:34%;}
  .pwf-card .pwf-qr-meta{flex:1;}
  .pwf-card>.pwf-card-body{font-size:max(2.95cqw,.62rem);}
}
.pwf-flip-btn{margin:14px auto 0;display:inline-flex;align-items:center;gap:.5rem;
    padding:.6rem 1.1rem;border-radius:999px;cursor:pointer;font:inherit;font-weight:700;
    font-size:.9rem;border:1.5px solid #174D3D;background:transparent;color:#0B4E3D;}
.pwf-flip-btn:hover{background:#0B4E3D;color:#FFFFFF;border-color:#0B4E3D;}
.pwf-flip-btn:focus-visible{outline:2px solid #0B4E3D;outline-offset:3px;}
@media (prefers-reduced-motion:reduce){.pwf-flip-inner{transition:none;}}
/* PRINT. Two things are wrong by default on every browser and both are fatal
   here. (1) Chrome ships "Background graphics" OFF, which drops background-color
   and background-image but KEEPS color — so the header, the MEMBER ID plaque,
   the status chip, the SCAN plaque, the monogram chip and the whole footer
   printed as white paper with #FFE987 and #FFFFFF text on it, and the premium
   template printed a blank sheet. (2) A card split across two sheets is waste
   paper. */
@media print{
  .pwf-cardset,.pwf-cardset *{-webkit-print-color-adjust:exact !important;print-color-adjust:exact !important;}
  .pwf-cardset{max-width:85.6mm;break-inside:avoid;page-break-inside:avoid;}
  .pwf-face{box-shadow:none !important;}
  .pwf-flip-btn{display:none !important;}
  /* The back is behind a 3D transform that print rasterises as a blank face. */
  .pwf-face.back{display:none !important;}
  /* ON PAPER THE FLOOR HAS TO GO. 85.6mm is 323 CSS px, where 2.5cqw is 8.1px —
     below the 0.62rem screen floor, which exists so a user's larger default font
     can enlarge the card. Left in place it would win the clamp and print a 9.9px
     base in a 54mm-tall box: the card grows to ~86x108mm and the 360px reflow
     fires, stacking the QR block on a printed ID card. In mm the floor is
     meaningful and the arithmetic lands on the design scale exactly:
     2.5cqw of 85.6mm = 2.14mm, which is 14px scaled to an 85.6mm-wide card. */
  .pwf-card>.pwf-card-body{font-size:max(1.9mm,2.5cqw);}
  .pwf-card .pwf-facts{grid-template-columns:repeat(2,minmax(0,1fr));}
  .pwf-card .pwf-front-row{flex-wrap:nowrap;}
  .pwf-card .pwf-qr-col{width:clamp(74px,24%,124px);flex-direction:column;align-items:stretch;
      text-align:center;align-self:stretch;margin-top:0;}
  .pwf-card .pwf-qr-plate{width:auto;}
}
</style>
CSS;
    }

    /* The face keeps a viewport-keyed font-size ONLY so its em corner radius
       still tracks the card; every piece of content sizes from .pwf-card-body's
       cqw scale above. */
    $faceBase = 'position:relative;overflow:hidden;border-radius:' . $l['radius'] . ';'
        . 'font-size:clamp(9.5px,2.05vw,14px);font-family:var(--font-head,var(--font-sans,system-ui,sans-serif));'
        . 'box-shadow:' . $l['shadow'] . ';';

    /* FOOTER NOTICE. card_footer_lines() ran on every render and card_data()
       published the result, and the front footer then hard-coded the same two
       sentences anyway — so everything typed into the admin's "Footer notice"
       field was computed, interpolated, sliced to three lines and thrown away. */
    $footerHtml = '';
    foreach ($d['footer'] as $fLine) {
        $footerHtml .= '<span style="display:block;font-size:.58em;">' . e($fLine) . '</span>';
    }

    /* Column order from the admin's photo/QR position settings — two more stored
       choices the renderer never read. */
    $orderPhoto = $l['photoPos'] === 'left' ? 1 : 3;
    $orderQr    = $l['qrPos'] === 'left' ? 1 : 3;

    return $shell . <<<HTML
<div class="pwf-cardset">
  <div class="pwf-flip" data-pwf-flip>
    <div class="pwf-flip-inner">

      <!-- ============================== FRONT ============================== -->
      <section class="pwf-face pwf-card front" aria-label="Membership card, front"
               style="{$faceBase}background:{$body};color:{$ink};">
        {$decoLayer}
        {$wedge}
        {$hairline}
        {$railBar}

        <div class="pwf-card-body" style="position:relative;z-index:1;display:flex;flex-direction:column;">
          {$headerHtml}

          <div class="pwf-front-row" style="gap:clamp(8px,2.2%,15px);
               padding:clamp(8px,2%,14px) clamp(12px,3%,20px) clamp(9px,2.2%,15px);">

            <!-- photograph + member id plaque -->
            <div class="pwf-photo-col" style="order:{$orderPhoto};">
              <span style="display:block;width:100%;aspect-ratio:{$photoRatio};overflow:hidden;
                    border-radius:{$photoRadius};border:1.5px solid {$frame};box-sizing:border-box;
                    background:{$panel};">{$photoInner}</span>
              {$idPlaque}
            </div>

            <!-- identity + labelled facts -->
            <div class="pwf-mid-col" style="order:2;">
              {$nameHtml}
              <div style="display:flex;flex-wrap:wrap;gap:.35em;align-items:center;">
                {$tierChip}
                {$statusChip}
              </div>
              <div class="pwf-facts" style="gap:.5em clamp(8px,2.5%,18px);margin-top:.1em;">{$rows}</div>
            </div>

            <!-- QR, verification address, authorised signatory -->
            <div class="pwf-qr-col" style="order:{$orderQr};">
              <span class="pwf-qr-plate" style="display:block;background:{$p['white']};border:1.5px solid {$frame};
                    border-radius:.7em;padding:5%;box-sizing:border-box;">{$qrSvg}{$qrAlt}</span>
              <span class="pwf-qr-meta">
                <span style="display:block;margin-top:.35em;background:{$plaque};border-radius:.45em;
                      padding:.24em .3em;font-size:.56em;font-weight:800;letter-spacing:.13em;
                      color:{$plaqueLabel};">SCAN TO VERIFY</span>
                <span style="display:block;margin-top:.3em;font-size:.58em;line-height:1.35;
                      color:{$label};overflow-wrap:anywhere;">{$verifyTx}</span>
                {$signBlock}
              </span>
            </div>
          </div>

          {$benefitStrip}

          <!-- FOOTER -->
          <div style="background:{$foot};color:{$footInk};padding:.5em clamp(12px,3%,20px);
               display:flex;align-items:center;gap:.55em;">
            {$footLogo}
            <div style="min-width:0;line-height:1.35;">
              <strong style="display:block;font-size:.62em;font-weight:800;letter-spacing:.1em;
                      text-transform:uppercase;overflow-wrap:anywhere;">{$org}</strong>
              {$footerHtml}
            </div>
            {$socialBlock}
          </div>
        </div>
      </section>

      <!-- ============================== BACK =============================== -->
      <!-- No aria-hidden/inert in the static markup: the flip script applies the
           correct state to BOTH faces on load. With JS off the flip cannot happen
           at all, so a permanently inert back face would make the terms and
           contact block unreachable rather than merely unflippable. -->
      <section class="pwf-face pwf-card back" aria-label="Membership card, back"
               style="{$faceBase}background:{$body};color:{$ink};
               font-family:var(--font-sans,system-ui,sans-serif);">
        {$decoLayer}
        {$hairline}
        {$railBar}

        <div class="pwf-card-body" style="position:relative;z-index:1;display:flex;flex-direction:column;">
          <div style="position:relative;padding:clamp(7px,1.8%,12px) clamp(12px,3%,20px) {$headPadB};
               background:{$headBg};">
            <div style="position:relative;z-index:1;display:flex;align-items:center;gap:.5em;">
              <span style="display:block;width:1.1em;color:{$headSub};flex:none;">{$cardIcon}</span>
              <strong style="min-width:0;overflow-wrap:anywhere;font-size:.68em;font-weight:800;
                      letter-spacing:{$caps};text-transform:uppercase;color:{$headInk};">{$org}</strong>
              <span style="margin-left:auto;flex:none;font-size:.58em;font-weight:800;letter-spacing:{$caps};
                    color:{$headSub};">BENEFITS &amp; TERMS</span>
            </div>
            {$waveSvg}
          </div>

          <div style="flex:1;display:flex;gap:clamp(10px,3%,22px);
               padding:clamp(8px,2%,14px) clamp(12px,3%,20px) clamp(8px,2%,14px);">
            {$backLeftCol}
            <div style="flex:1;min-width:0;display:flex;flex-direction:column;">
              {$backVerify}
              {$backContact}
              {$backSign}
            </div>
          </div>

          <div style="background:{$foot};color:{$footInk};padding:.45em clamp(12px,3%,20px);
               font-size:.58em;line-height:1.4;">
            {$issuedLine}
          </div>
        </div>
      </section>

    </div>
  </div>
  <div style="text-align:center;">
    <!-- The visible text is STATIC and aria-pressed carries the state. It used to
         change to "Show front" while ALSO setting aria-pressed="true", so with the
         back showing a screen reader announced "Show front, toggle button,
         pressed" — the name described the action to undo while the state said
         "on". A changing name would also have to match the accessible name
         (2.5.3), so the state moves entirely into aria-pressed plus the polite
         status line below. -->
    <button class="pwf-flip-btn" type="button" data-pwf-flip-btn aria-pressed="false">
      <span>Flip card</span>
    </button>
    <span data-pwf-flip-state aria-live="polite" style="{$srOnly}">Showing the front of the card</span>
  </div>
</div>
HTML;
}

/**
 * Behaviour for the flip. Emitted once, after the last card on the page — so a
 * page rendering several cards (the admin member list) does not repeat it.
 */
function card_html_assets(): string
{
    static $done = false;
    if ($done) { return ''; }
    $done = true;
    return <<<'JS'
<script>
(function () {
    document.querySelectorAll('[data-pwf-flip-btn]').forEach(function (btn) {
        var set = btn.closest('.pwf-cardset');
        var flip = set && set.querySelector('[data-pwf-flip]');
        if (!flip) return;
        var faces = flip.querySelectorAll('.pwf-face');
        var state = set.querySelector('[data-pwf-flip-state]');
        function apply(back) {
            flip.classList.toggle('is-back', back);
            btn.setAttribute('aria-pressed', back ? 'true' : 'false');
            /* backface-visibility is PAINT ONLY. Without this the hidden face
               stayed in the accessibility tree and the tab order: a screen reader
               read the whole card twice with no way to tell which side was
               showing, and keyboard focus landed on invisible mailto:/tel: links
               while the front showed, and on invisible social links while the back
               showed (2.4.3 Focus Order, 2.4.7 Focus Visible). */
            Array.prototype.forEach.call(faces, function (face) {
                var hidden = face.classList.contains('back') ? !back : back;
                if (hidden) {
                    face.setAttribute('aria-hidden', 'true');
                    face.setAttribute('inert', '');
                } else {
                    face.removeAttribute('aria-hidden');
                    face.removeAttribute('inert');
                }
            });
            if (state) {
                state.textContent = back ? 'Showing the back of the card' : 'Showing the front of the card';
            }
        }
        apply(flip.classList.contains('is-back'));   // seal the hidden face on load
        btn.addEventListener('click', function () { apply(!flip.classList.contains('is-back')); });
        /* Swipe on touch, as the card is a physical object in the hand. */
        var x0 = null;
        flip.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        flip.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 45) apply(dx < 0);
            x0 = null;
        }, { passive: true });
    });
})();
</script>
JS;
}

/* =============================================================================
 |  RASTER PATH (GD)  — the same design, composited for download / print
 |============================================================================*/

/** Largest font size (down to $min) whose rendered text fits within $maxW px. */
function card_fit_size(string $text, string $weight, int $max, int $min, int $maxW): int
{
    for ($s = $max; $s > $min; $s--) {
        if (card_text_width($s, $text, $weight) <= $maxW) {
            return $s;
        }
    }
    return $min;
}

/**
 * The largest size (down to $min) at which $text wraps into $maxLines WITHOUT
 * being truncated, plus those lines. Falls back to $min, whose last line is
 * ellipsized by card_wrap_lines().
 *
 * This is what keeps a legal name, an email and a postal address WHOLE on the
 * download: shrink the type until it fits, and only truncate when even the floor
 * size cannot hold it. Returns [size, lines].
 */
function card_fit_wrapped(string $text, string $weight, int $max, int $min, int $maxW, int $maxLines): array
{
    for ($size = $max; $size > $min; $size--) {
        $lines = card_wrap_lines($text, $weight, $size, $maxW, $maxLines);
        if ($lines && !str_ends_with((string) end($lines), '…')) {
            return [$size, $lines];
        }
    }
    return [$min, card_wrap_lines($text, $weight, $min, $maxW, $maxLines)];
}

/** Ellipsize text to fit $maxW px at the given size (no-op if it already fits). */
function card_fit_text(string $text, string $weight, int $size, int $maxW): string
{
    if (card_text_width($size, $text, $weight) <= $maxW) {
        return $text;
    }
    $t = $text;
    while (mb_strlen($t) > 1 && card_text_width($size, $t . '…', $weight) > $maxW) {
        $t = mb_substr($t, 0, -1);
    }
    return $t . '…';
}

/**
 * Build the card front as a GD image at $scale x ID-1 (1012x638) design units.
 * Caller owns the returned resource.
 *
 * This mirrors card_html()'s front face: curved green header with a gold band,
 * photo + MEMBER ID plaque, identity and labelled rows, QR plate with the
 * verification address, benefits strip and the dark green footer.
 */
function card_build_gd(array $member, string $template = 'classic', float $scale = 1.62)
{
    $tplId = card_template_id($template);       // resolves the legacy 'dark' id
    $d = card_data($member);
    $t = card_theme($tplId);
    $l = card_layout($tplId);
    $p = card_palette();

    $s = max(1.0, $scale);
    $k = static fn (float $v): int => (int) round($v * $s);
    $f = static fn (float $v): int => max(6, (int) round($v * $s));

    $W = $k(CARD_W);
    $H = $k(CARD_H);

    $img = imagecreatetruecolor($W, $H);
    imagefilledrectangle($img, 0, 0, $W, $H, card_color($img, $t['body']));

    $cInk    = card_color($img, $t['ink']);
    $cLabel  = card_color($img, $t['label']);
    $cHeadInk = card_color($img, $t['headInk']);
    $cHeadSub = card_color($img, $t['headSub']);
    $cPlaqueInk = card_color($img, $t['plaqueInk']);
    $cPlaqueLbl = card_color($img, $t['plaqueLabel']);
    $cFootInk   = card_color($img, $t['footInk']);
    $cFootSub   = card_color($img, $t['footSub']);
    $cPanelInk  = card_color($img, $t['panelInk']);

    /* ---- decoration: fine contour arcs behind the body ------------------- */
    /* decoInk is a theme token so the arcs stay visible whatever body colour the
       template or the admin picked — GD has no blend mode to fall back on. */
    $decoCol = card_color($img, $t['decoInk'], 119);
    imagesetthickness($img, max(1, $k(1.4)));
    for ($i = 0; $i < 3; $i++) {
        imagearc($img, $k(906), $k(292 + $i * 24), $k(560 + $i * 86), $k(400 + $i * 58), 0, 360, $decoCol);
    }
    imagesetthickness($img, 1);

    /* ---- header, with the template's own bottom edge --------------------
       GD approximation of card_html()'s geometry: 'wave' and 'arch' both get the
       organic curve (a shallower, wider amplitude for the arch — GD cannot draw
       the HTML face's single elliptical sweep), 'rail'/'flat' get a straight
       edge. See the report for exactly what differs from the HTML card. */
    $headH = 152;
    card_gradient($img, 0, 0, $W, $k($headH), $t['head1'], $l['headGrad'] ? $t['head2'] : $t['head1']);
    if ($l['headStyle'] === 'wave') {
        card_wave($img, 0, $W, $k($headH - 24), $k($headH), $k(11), $t['body'], $p['gold'], $k(6));
    } elseif ($l['headStyle'] === 'arch') {
        card_wave($img, 0, $W, $k($headH - 18), $k($headH), $k(5), $t['body'], $p['gold'], $k(5));
    } else {
        imagefilledrectangle($img, 0, $k($headH - 4), $W, $k($headH), card_color($img, $p['gold']));
    }
    // gold accent hairline along the top edge
    if ($l['hairline']) {
        imagefilledrectangle($img, 0, 0, $W, $k(3), card_color($img, $p['gold']));
    }
    // vertical accent rail (modern)
    if ($l['rail'] > 0) {
        imagefilledrectangle($img, 0, 0, $k(10), $H, card_color($img, $d['accent'] !== '' ? $d['accent'] : $t['chipBg']));
    }

    // logo disc
    $logoImg = card_load_logo();
    $lcx = $k(80); $lcy = $k(62); $lr = $k(34);
    imagefilledellipse($img, $lcx, $lcy, $lr * 2, $lr * 2, card_color($img, $p['white']));
    if ($logoImg) {
        $box = (int) round($lr * 1.42);
        card_cover($img, $logoImg, $lcx - (int) ($box / 2), $lcy - (int) ($box / 2), $box, $box, 0.5);
        imagedestroy($logoImg);
    } else {
        // The ORGANISATION's initial in the logo slot — not the member's.
        card_text_center($img, $f(26), $lcx, $lcy - $k(20), card_color($img, $t['head1']), $d['orgInitial'], 'bold');
    }

    // header type
    $rightLabel = 'MEMBERSHIP CARD';
    $rightW = card_text_width($f(15), $rightLabel, 'bold');
    $orgMaxW = $W - $k(130) - $rightW - $k(80);
    $orgSize = card_fit_size(strtoupper($d['org']), 'bold', $f(28), $f(15), $orgMaxW);
    card_text($img, $orgSize, $k(130), $k(30), $cHeadInk, card_fit_text(strtoupper($d['org']), 'bold', $orgSize, $orgMaxW), 'bold');
    if ($d['tagline'] !== '') {
        card_text($img, $f(13), $k(130), $k(76), $cHeadSub,
            card_fit_text(strtoupper($d['tagline']), 'semibold', $f(13), $orgMaxW), 'semibold');
    }
    card_text_right($img, $f(15), $W - $k(40), $k(38), $cHeadSub, $rightLabel, 'bold');
    // small community mark left of the label
    $mx = $W - $k(40) - $rightW - $k(34);
    imagesetthickness($img, max(1, $k(2)));
    imageellipse($img, $mx, $k(42), $k(13), $k(13), $cHeadSub);
    imageellipse($img, $mx + $k(15), $k(46), $k(10), $k(10), $cHeadSub);
    imagearc($img, $mx + $k(6), $k(60), $k(34), $k(22), 190, 350, $cHeadSub);
    imagesetthickness($img, 1);

    /* ---- geometry --------------------------------------------------------
       $qrW went 166 -> 186 to buy the printed QR a whole module-size step (see
       card_draw_qr). The right column is no longer laid out against hard-coded
       y values — everything below the plate is measured, and the signatory block
       is bottom-anchored — because with a bigger plate the old fixed signY=438
       collided with the URL lines, and at the old size a member with a signature
       image AND a name AND a role already overran the benefits strip at y=508. */
    $bodyTop = 172;
    $qrW     = 186;
    $qrX     = CARD_W - 40 - $qrW;          // 786
    $leftX   = 44;
    $leftW   = 206;
    $midX    = $leftX + $leftW + 30;        // 280
    $midW    = $qrX - 24 - $midX;           // 482

    /* ---- LEFT: photo + MEMBER ID plaque --------------------------------- */
    // 1/1 for the templates whose HTML face uses a square/circular photo.
    $ph = $l['photoRatio'] === '1/1' ? $leftW : 258;
    $bw = 3;
    card_rounded_rect($img, $k($leftX - $bw), $k($bodyTop - $bw), $k($leftX + $leftW + $bw), $k($bodyTop + $ph + $bw), $k(12), card_color($img, $t['frame']));
    $photo = card_load_photo($d['photoFile']);
    if ($photo) {
        card_cover($img, $photo, $k($leftX), $k($bodyTop), $k($leftW), $k($ph), 0.28);
        imagedestroy($photo);
    } else {
        /* Branded fallback: a silhouette mark on a brand tint under a monogram
           chip — never a bare initial. GD has neither a clipping region nor
           grouped opacity, so the mark is composited in an off-screen tile the
           exact size of the frame: anything outside is discarded (that is the
           clip), and imagecopymerge applies one opacity to the whole mark
           instead of letting the head and shoulders double up where they meet. */
        $tileW = $k($leftW); $tileH = $k($ph);
        $tile  = imagecreatetruecolor($tileW, $tileH);
        // The tile's own corners carry the border colour, so copying it in keeps
        // the frame's rounded corners intact.
        imagefilledrectangle($tile, 0, 0, $tileW, $tileH, card_color($tile, $t['frame']));
        card_rounded_rect($tile, 0, 0, $tileW, $tileH, $k(10), card_color($tile, $t['panel']));
        // A pre-mixed tint rather than an alpha fill: overlapping shapes would
        // otherwise double up and show a seam where head meets shoulders.
        $silCol = card_color($tile, card_mix($t['frame'], $t['panel'], 0.17));
        imagefilledellipse($tile, (int) ($tileW / 2), $k(92), $k(78), $k(78), $silCol);
        imagefilledellipse($tile, (int) ($tileW / 2), $k(258), $k(192), $k(214), $silCol);
        imagecopy($img, $tile, $k($leftX), $k($bodyTop), 0, 0, $tileW, $tileH);
        imagedestroy($tile);
        // No name, no monogram: the silhouette mark stands alone rather than
        // presenting the organisation's initial as the member's.
        if ($d['initials'] !== '') {
            $cx = $k($leftX + (int) ($leftW / 2));
            $chipW = card_text_width($f(15), $d['initials'], 'bold') + $k(26);
            card_rounded_rect($img, $cx - (int) ($chipW / 2), $k($bodyTop + $ph - 54), $cx + (int) ($chipW / 2), $k($bodyTop + $ph - 20), $k(17), card_color($img, $t['plaque']));
            card_text_center($img, $f(15), $cx, $k($bodyTop + $ph - 49), $cPlaqueInk, $d['initials'], 'bold');
        }
    }
    if ($d['code'] !== '') {
        $py = $bodyTop + $ph + 12;
        card_rounded_rect($img, $k($leftX), $k($py), $k($leftX + $leftW), $k($py + 56), $k(10), card_color($img, $t['plaque']));
        card_text_center($img, $f(11), $k($leftX + (int) ($leftW / 2)), $k($py + 8), $cPlaqueLbl, 'MEMBER ID', 'bold');
        $codeSize = card_fit_size($d['code'], 'bold', $f(18), $f(11), $k($leftW - 16));
        card_text_center($img, $codeSize, $k($leftX + (int) ($leftW / 2)), $k($py + 26), $cPlaqueInk, $d['code'], 'bold');
    }

    /* ---- MIDDLE: name, chips, labelled rows -----------------------------
       WRAPPED, NOT ELLIPSIZED. The screen and the download disagreed: the HTML
       front wraps a long name, email and address in full, while the raster —
       the artefact somebody actually carries and shows — printed
       "Dr. Ananyashree Balasubramaniam-Venk…", an address cut mid-word and an
       email ending in "…@subdomain.example-organ…". A truncated legal name on an
       identity document is a defect in its own right, so name/email/address now
       wrap to two lines and the row cursor absorbs the extra height. */
    // An empty name draws no name — the raster does not invent "Member" either.
    $y = $bodyTop - 4;                       // design-unit cursor for this column
    if ($d['name'] !== '') {
        [$nameSize, $nameLines] = card_fit_wrapped($d['name'], 'bold', $f(34), $f(19), $k($midW), 2);
        $nameLHd = (int) round(($nameSize / max(0.001, $s)) * 1.16);   // design units
        foreach ($nameLines as $i => $nLine) {
            card_text($img, $nameSize, $k($midX), $k($y + $i * $nameLHd), $cInk, $nLine, 'bold');
        }
        $y += count($nameLines) * $nameLHd + 12;
    }

    // tier + status chips
    $chipY = $y;
    $chipH = 36;
    $chipX = $k($midX);
    $chipFill = $d['accent'] !== '' ? $d['accent'] : $t['chipBg'];
    $chipTextCol = $d['accent'] !== ''
        ? card_ink_for($d['accent'], card_ink_candidates($d['accent'], 'quiet'))
        : $t['chipInk'];
    // No plan, no tier chip — the raster never prints an invented tier either.
    if ($d['tier'] !== '') {
        $tierLabel = strtoupper($d['tier']);
        $tw = card_text_width($f(14), $tierLabel, 'bold') + $k(30);
        card_rounded_rect($img, $chipX, $k($chipY), $chipX + $tw, $k($chipY + $chipH), $k(18), card_color($img, $chipFill));
        card_text($img, $f(14), $chipX + $k(15), $k($chipY + 9), card_color($img, $chipTextCol), $tierLabel, 'bold');
        $chipX += $tw + $k(10);
    }

    $sm = card_status_meta($d['status']);
    $sLabel = strtoupper($sm['label']);
    $sEdge  = card_status_edge($sm, $t['body'], $d['tier'] !== '' ? $chipFill : '');
    $sw = card_text_width($f(14), $sLabel, 'bold') + $k(44);
    if ($sEdge !== '') {
        card_rounded_outline($img, $chipX, $k($chipY), $chipX + $sw, $k($chipY + $chipH), $k(18), max(1, $k(1.5)), card_color($img, $sEdge), card_color($img, $sm['bg']));
    } else {
        card_rounded_rect($img, $chipX, $k($chipY), $chipX + $sw, $k($chipY + $chipH), $k(18), card_color($img, $sm['bg']));
    }
    // marker dot + the label itself: never colour alone
    imagefilledellipse($img, $chipX + $k(16), $k($chipY + $chipH / 2), $k(11), $k(11), card_color($img, $sm['ink']));
    card_text($img, $f(14), $chipX + $k(28), $k($chipY + 9), card_color($img, $sm['ink']), $sLabel, 'bold');
    $y += $chipH + 14;

    // rows: two columns, email/address full width. Empty values are never emitted.
    $pairs = [];
    foreach ([
        ['MEMBER SINCE',    $d['joined'],  false],
        ['MEMBERSHIP TYPE', $d['type'],    false],
        ['VALID THRU',      $d['valid'],   false],
        ['MOBILE',          $d['phone'],   false],
        ['EMAIL',           $d['email'],   true],
        ['ADDRESS',         $d['address'], true],
    ] as [$lbl, $val, $wide]) {
        if (trim((string) $val) !== '') {
            $pairs[] = [$lbl, (string) $val, $wide];
        }
    }
    $colW    = (int) (($midW - 22) / 2);
    $rowBase = 28;                            // label + one value line
    $lineH   = 20;
    /* f(14), not f(15), for the two full-width values. Four design units of type
       size is the difference between a 67-character email fitting on one line and
       needing two — and with a two-line name, a two-line email AND a two-line
       address, the column overruns the benefits strip. */
    $wideSize = $f(14);
    /* The old loop had `if ($rowY + $rowH > 506) { break; }` — it silently DROPPED
       a whole field rather than making room, with 30 units of headroom left and no
       trace in the output. Instead: try two lines for the wide values, and if that
       overruns the budget, retry with one. A row is never dropped. */
    $rowsBottom = ($l['strip'] !== 'none' && $d['benefits']) ? 500 : 552;

    /* Resolve each full-width value ONCE — size and lines — then measure the whole
       column against the budget. Two lines are the goal; one line (still shrunk to
       fit, still visibly ellipsized if it truly cannot) is the fallback. */
    $wideFit = static function (int $maxLines) use ($pairs, $f, $k, $midW, $wideSize): array {
        $out = [];
        foreach ($pairs as $idx => [$lbl, $val, $wide]) {
            if ($wide) {
                $out[$idx] = card_fit_wrapped($val, 'bold', $wideSize, $f(10), $k($midW), $maxLines);
            }
        }
        return $out;
    };
    $measure = static function (array $fit) use ($pairs, $y, $rowBase, $lineH): int {
        $py = $y; $pc = 0;
        foreach ($pairs as $idx => [$lbl, $val, $wide]) {
            if ($wide) {
                if ($pc === 1) { $py += $rowBase + $lineH; $pc = 0; }
                $py += $rowBase + (count($fit[$idx][1]) - 1) * $lineH + $lineH;
            } elseif ($pc === 0) {
                $pc = 1;
            } else {
                $pc = 0; $py += $rowBase + $lineH;
            }
        }
        return $pc === 1 ? $py + $rowBase + $lineH : $py;
    };
    $fit = $wideFit(2);
    if ($measure($fit) > $rowsBottom) {
        $fit = $wideFit(1);
    }

    $rowY = $y;
    $col  = 0;
    foreach ($pairs as $idx => [$lbl, $val, $wide]) {
        if ($wide && $col === 1) { $rowY += $rowBase + $lineH; $col = 0; }
        $x = $midX + ($col === 1 ? $colW + 22 : 0);
        $w = $wide ? $midW : $colW;
        card_text($img, $f(11), $k($x), $k($rowY), $cLabel, $lbl, 'semibold');
        if ($wide) {
            [$vSize, $lines] = $fit[$idx];
            foreach ($lines as $i => $vLine) {
                card_text($img, $vSize, $k($x), $k($rowY + 18 + $i * $lineH), $cInk, $vLine, 'bold');
            }
            $rowY += $rowBase + (count($lines) - 1) * $lineH + $lineH;
            $col = 0;
        } else {
            $vSize = card_fit_size($val, 'bold', $f(18), $f(11), $k($w));
            card_text($img, $vSize, $k($x), $k($rowY + 18), $cInk, card_fit_text($val, 'bold', $vSize, $k($w)), 'bold');
            if ($col === 0) { $col = 1; }
            else { $col = 0; $rowY += $rowBase + $lineH; }
        }
    }
    if ($col === 1) { $rowY += $rowBase + $lineH; }

    /* The state note the HTML card carries, so the download explains its own
       missing rows instead of just having fewer of them. */
    if ($d['note'] !== '') {
        foreach (card_wrap_lines($d['note'], 'semibold', $f(12), $k($midW), 2) as $i => $nLine) {
            card_text($img, $f(12), $k($midX), $k($rowY + 2) + $i * $k(17), $cLabel, $nLine, 'semibold');
        }
    }

    /* ---- RIGHT: QR plate, scan plaque, address, signatory --------------- */
    card_draw_qr($img, $d['verifyQr'], $k($qrX), $k($bodyTop), $k($qrW), $t['qrDark'], $t['frame'], $k(12));
    $scanY = $bodyTop + $qrW + 10;
    card_rounded_rect($img, $k($qrX), $k($scanY), $k($qrX + $qrW), $k($scanY + 32), $k(8), card_color($img, $t['plaque']));
    card_text_center($img, $f(11), $k($qrX + (int) ($qrW / 2)), $k($scanY + 7), $cPlaqueLbl, 'SCAN TO VERIFY', 'bold');

    $qrCx  = $qrX + (int) ($qrW / 2);
    $urlY  = $scanY + 40;
    $urlLines = $d['verifyText'] !== ''
        ? card_break_lines($d['verifyText'], 'regular', $f(10), $k($qrW), 3)
        : [];
    foreach ($urlLines as $i => $line) {
        card_text_center($img, $f(10), $k($qrCx), $k($urlY + $i * 14), $cLabel, $line);
    }
    $urlBottom = $urlLines ? $urlY + count($urlLines) * 14 : $scanY + 34;

    /* SIGNATORY — measured and BOTTOM-ANCHORED. It used to start at a hard-coded
       y=438, which (a) collided with the verification address once the QR plate
       grew and (b) already overran the benefits strip at y=508 whenever all three
       parts — signature image, name and role — were present. Now the block's
       height is computed first and it is placed against the bottom of whatever
       space the right column actually has; if there is not enough room for it, it
       is not drawn half-way over the strip. */
    $hasSign = $d['signature'] !== '' || $d['signatory'] !== '' || $d['signRole'] !== '';
    $items   = ($l['strip'] === 'none') ? [] : array_slice($d['benefits'], 0, 5);
    if ($hasSign) {
        $sigImg = $d['signature'] !== '' ? card_load_photo($d['signature']) : null;
        $sigH   = 6;
        if ($sigImg) { $sigH += 38; }
        if ($d['signatory'] !== '') { $sigH += 20; }
        if ($d['signRole'] !== '')  { $sigH += 16; }
        $sigH += 16;                                   // "AUTHORISED SIGNATORY"
        $colBottom = $items ? 502 : 552;
        $signY     = $colBottom - $sigH;
        if ($signY >= $urlBottom + 6) {
            imagefilledrectangle($img, $k($qrX), $k($signY), $k($qrX + $qrW), $k($signY) + max(1, $k(1)), card_color($img, $t['label'], 88));
            $sy = $signY + 6;
            if ($sigImg) {
                $sw2 = imagesx($sigImg); $sh2 = imagesy($sigImg);
                $ratio = min($qrW / max(1, $sw2), 36 / max(1, $sh2));
                $dw = (int) round($sw2 * $ratio); $dh = (int) round($sh2 * $ratio);
                imagecopyresampled($img, $sigImg, $k($qrX + (int) (($qrW - $dw) / 2)), $k($sy), 0, 0, $k($dw), $k($dh), $sw2, $sh2);
                $sy += 38;
            }
            if ($d['signatory'] !== '') {
                card_text_center($img, $f(13), $k($qrCx), $k($sy), $cInk,
                    card_fit_text($d['signatory'], 'bold', $f(13), $k($qrW)), 'bold');
                $sy += 20;
            }
            if ($d['signRole'] !== '') {
                card_text_center($img, $f(10), $k($qrCx), $k($sy), $cLabel,
                    card_fit_text(strtoupper($d['signRole']), 'semibold', $f(10), $k($qrW)), 'semibold');
                $sy += 16;
            }
            card_text_center($img, $f(9), $k($qrCx), $k($sy), $cLabel, 'AUTHORISED SIGNATORY', 'semibold');
        }
        if ($sigImg) { imagedestroy($sigImg); }
    }

    /* ---- benefits strip -------------------------------------------------- */
    $stripTop = 508;
    $stripH   = 52;
    if ($items) {
        imagefilledrectangle($img, 0, $k($stripTop), $W, $k($stripTop + $stripH), card_color($img, $t['panel']));
        imagefilledrectangle($img, 0, $k($stripTop), $W, $k($stripTop) + max(1, $k(1)), card_color($img, $t['label'], 96));
        $n = count($items);
        $cell = (int) ((CARD_W - 80) / $n);
        foreach ($items as $i => $bText) {
            $cx = 40 + $i * $cell;
            if ($i > 0) {
                imagefilledrectangle($img, $k($cx - 6), $k($stripTop + 12), $k($cx - 6) + max(1, $k(1)), $k($stripTop + $stripH - 12), card_color($img, $t['label'], 100));
            }
            /* GD cannot render the HTML card's outline icon set, so each item
               gets a ringed tick — the same "outline mark then label" rhythm. */
            imagesetthickness($img, max(1, $k(2)));
            imageellipse($img, $k($cx + 10), $k($stripTop + 26), $k(17), $k(17), $cPanelInk);
            imageline($img, $k($cx + 6), $k($stripTop + 26), $k($cx + 9), $k($stripTop + 29), $cPanelInk);
            imageline($img, $k($cx + 9), $k($stripTop + 29), $k($cx + 14), $k($stripTop + 22), $cPanelInk);
            imagesetthickness($img, 1);
            card_text($img, $f(11), $k($cx + 24), $k($stripTop + 18), $cPanelInk,
                card_fit_text($bText, 'semibold', $f(11), $k($cell - 34)), 'semibold');
        }
    }

    /* ---- footer ---------------------------------------------------------- */
    $footTop = 562;
    imagefilledrectangle($img, 0, $k($footTop), $W, $H, card_color($img, $t['foot']));
    $fx = 40;
    $logo2 = card_load_logo();
    if ($logo2) {
        $lr2 = $k(21);
        imagefilledellipse($img, $k(62), $k($footTop + 38), $lr2 * 2, $lr2 * 2, card_color($img, $p['white']));
        $box2 = (int) round($lr2 * 1.42);
        card_cover($img, $logo2, $k(62) - (int) ($box2 / 2), $k($footTop + 38) - (int) ($box2 / 2), $box2, $box2, 0.5);
        imagedestroy($logo2);
        $fx = 92;
    }
    card_text($img, $f(13), $k($fx), $k($footTop + 8), $cFootInk,
        card_fit_text(strtoupper($d['org']), 'bold', $f(13), $k(520)), 'bold');
    /* The admin's footer notice, not two hard-coded sentences. This path printed
       "This card is the property of … / If found, please return to the nearest
        office." verbatim, so the setting was inert here too. */
    $fy = $footTop + 27;
    foreach (array_slice($d['footer'], 0, 3) as $fLine) {
        card_text($img, $f(11), $k($fx), $k($fy), $cFootInk,
            card_fit_text($fLine, 'regular', $f(11), $k(560)));
        $fy += 16;
    }

    // FOLLOW US + social chips (letter marks: GD has no brand glyphs)
    $socials = array_slice($d['socials'], 0, 6);
    if ($socials) {
        card_text_right($img, $f(10), $W - $k(40), $k($footTop + 10), $cFootSub, 'FOLLOW US', 'bold');
        $chip = 30; $gap = 8;
        $totalW = count($socials) * $chip + (count($socials) - 1) * $gap;
        $sx2 = CARD_W - 40 - $totalW;
        foreach ($socials as $i => $soc) {
            $plat = trim((string) ($soc['platform'] ?? ''));
            if ($plat === '') { continue; }
            $mark = strtoupper(mb_substr($plat, 0, 1));
            if (stripos($plat, 'linkedin') === 0) { $mark = 'IN'; }
            $x0 = $sx2 + $i * ($chip + $gap);
            card_rounded_rect($img, $k($x0), $k($footTop + 32), $k($x0 + $chip), $k($footTop + 32 + $chip), $k(8), card_color($img, $t['footSub']));
            card_text_center($img, $f(12), $k($x0 + (int) ($chip / 2)), $k($footTop + 38), card_color($img, $t['foot']), $mark, 'bold');
        }
    }

    return $img;
}

/* =============================================================================
 |  OUTPUT: PNG / JPEG / PDF
 |============================================================================*/

/** Render the card as PNG binary (>= 1600px wide by default). */
function card_png(array $member, string $template = 'classic', float $scale = 1.62): string
{
    $img = card_build_gd($member, $template, $scale);
    ob_start();
    imagepng($img);
    $png = (string) ob_get_clean();
    imagedestroy($img);
    return $png;
}

/** Render the card as JPEG binary. */
function card_jpeg(array $member, string $template = 'classic', int $quality = 92, float $scale = 1.62): string
{
    $img = card_build_gd($member, $template, $scale);
    ob_start();
    imagejpeg($img, null, $quality);
    $jpeg = (string) ob_get_clean();
    imagedestroy($img);
    return $jpeg;
}

/**
 * Wrap the card JPEG in a minimal single-page PDF at ID-1 (CR80) page size.
 * Hand-written PDF: catalog + pages + page + image XObject (DCTDecode) +
 * content stream. No external library. Rendered at 2x design units, i.e. ~600
 * DPI on a 3.375in x 2.125in card.
 */
function card_pdf(array $member, string $template = 'classic', float $scale = 2.0): string
{
    $jpeg = card_jpeg($member, $template, 94, $scale);
    $iw   = (int) round(CARD_W * $scale);
    $ih   = (int) round(CARD_H * $scale);
    // Page size in points (72 pt/in): CR80 landscape = 3.375in x 2.125in.
    $pw = 243.0;
    $ph = 153.0;

    $objects = [];
    $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pw $ph] "
        . "/Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>";
    $objects[4] = "<< /Type /XObject /Subtype /Image /Width $iw /Height $ih "
        . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
        . strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream";
    $content = "q\n$pw 0 0 $ph 0 0 cm\n/Im0 Do\nQ";
    $objects[5] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    for ($i = 1; $i <= 5; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF";
    return $pdf;
}
