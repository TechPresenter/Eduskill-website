<?php
/**
 * Presentation helpers — the runtime brand palette (OKLCh) and inline SVG icons.
 * Kept separate from functions.php so the maths stays out of the way of everyday helpers.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

/**
 * Emit a <style> block that overrides the brand/accent CSS variables from the owner's chosen hex
 * colours, with a full OKLCh lightness ramp so every shade reads as the same hue. --on-brand is set
 * to black or white by measured contrast, so a pale brand colour still yields readable button text.
 * The compiled Tailwind only ever references rgb(var(--brand-500) / <alpha>); this feeds it.
 */
function palette_style(?string $brandHex = null, ?string $accentHex = null): string
{
    $brandHex = $brandHex ?: (string) setting('brand_color', '#4f46e5');
    $accentHex = $accentHex ?: (string) setting('accent_color', '#10b981');

    $vars = '';
    foreach (pal_ramp($brandHex) as $step => $rgb) {
        $vars .= "--brand-{$step}:{$rgb};";
    }
    foreach (pal_ramp($accentHex) as $step => $rgb) {
        $vars .= "--accent-{$step}:{$rgb};";
    }
    $vars .= '--on-brand:' . pal_on_color($brandHex) . ';';
    return '<style id="esk-palette">:root{' . $vars . '}</style>';
}

/** @return array<int,string> step => "r g b" */
function pal_ramp(string $hex): array
{
    static $lightness = [50 => 0.971, 100 => 0.936, 200 => 0.885, 300 => 0.808, 400 => 0.704, 500 => 0.637, 600 => 0.577, 700 => 0.505, 800 => 0.443, 900 => 0.396, 950 => 0.284];
    [$l0, $c, $h] = pal_hex_to_oklch($hex);
    $out = [];
    foreach ($lightness as $step => $l) {
        $chroma = max(0.0, $c * (1 - 0.55 * abs($l - $l0)));
        $out[$step] = pal_oklch_to_channels($l, $chroma, $h);
    }
    return $out;
}

function pal_on_color(string $brandHex): string
{
    $ramp = pal_ramp($brandHex);
    [$r, $g, $b] = array_map('intval', explode(' ', $ramp[600]));
    $lin = fn (int $c): float => ($c / 255) <= 0.04045 ? ($c / 255) / 12.92 : ((($c / 255) + 0.055) / 1.055) ** 2.4;
    $bg = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    return (1.05 / ($bg + 0.05)) >= (($bg + 0.05) / 0.05) ? '255 255 255' : '0 0 0';
}

/** @return array{0:float,1:float,2:float} */
function pal_hex_to_oklch(string $hex): array
{
    [$r, $g, $b] = pal_hex_to_rgb($hex);
    $tl = fn (float $c): float => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    $rl = $tl($r / 255);
    $gl = $tl($g / 255);
    $bl = $tl($b / 255);
    $cbrt = fn (float $x): float => $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    $l = $cbrt(0.4122214708 * $rl + 0.5363325363 * $gl + 0.0514459929 * $bl);
    $m = $cbrt(0.2119034982 * $rl + 0.6806995451 * $gl + 0.1073969566 * $bl);
    $s = $cbrt(0.0883024619 * $rl + 0.2817188376 * $gl + 0.6299787005 * $bl);
    $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
    $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
    $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
    return [$L, sqrt($a * $a + $bb * $bb), atan2($bb, $a)];
}

function pal_oklch_to_channels(float $L, float $C, float $h): string
{
    $a = $C * cos($h);
    $b = $C * sin($h);
    $l = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
    $m = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
    $s = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;
    $tb = function (float $lin): int {
        $v = $lin <= 0.0031308 ? 12.92 * $lin : 1.055 * ($lin ** (1 / 2.4)) - 0.055;
        return (int) max(0, min(255, round($v * 255)));
    };
    $r = $tb(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s);
    $g = $tb(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s);
    $bl = $tb(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s);
    return "{$r} {$g} {$bl}";
}

/** @return array{0:int,1:int,2:int} */
function pal_hex_to_rgb(string $hex): array
{
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        $hex = '4f46e5';
    }
    return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
}

/* ---------------------------------------------------------------- icons */

/** Inline SVG icon (Heroicons-style). No Font Awesome dependency to vendor. */
function icon(string $name, string $class = 'h-5 w-5'): string
{
    static $paths = [
        'dashboard' => '<path d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10"/>',
        'pages' => '<path d="M9 12h6m-6 4h6m-6-8h6M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>',
        'campaigns' => '<path d="M3 11l18-5v12L3 14v-3zM11.6 16.8A3 3 0 017 15"/>',
        'programs' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'events' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'blog' => '<path d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h9l7 7v7a2 2 0 01-2 2z"/><path d="M8 11h8M8 15h5"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0112 0M16 6a3 3 0 010 6M21 20a6 6 0 00-4-5.6"/>',
        'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 16l5-5 4 4 3-3 6 6"/><circle cx="9" cy="9" r="1.5"/>',
        'contact' => '<path d="M4 4h16v12H5.2L4 17.2z"/>',
        'donate' => '<path d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.6l-1-1a5.5 5.5 0 00-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 000-7.8z"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 00.3 1.9l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-2.9 1.2V21a2 2 0 11-4 0v-.1A1.7 1.7 0 007 19.7l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00-1.2-2.9H3a2 2 0 110-4h.1A1.7 1.7 0 004.3 7l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 002.9-1.2V3a2 2 0 114 0v.1A1.7 1.7 0 0017 4.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 001.2 2.9H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13l3.5 7v6a2 2 0 01-2 2H4a2 2 0 01-2-2v-6z"/>',
        'star' => '<path d="M12 2l2.9 6.3 6.9.8-5 4.7 1.3 6.8L12 17.6 5.9 20.6 7.2 13.8l-5-4.7 6.9-.8z"/>',
        'seo' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'external' => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/>',
        'bell' => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
    ];
    $inner = $paths[$name] ?? '<circle cx="12" cy="12" r="9"/>';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}
