<?php
/**
 * =============================================================================
 *  Hero Slider — render helpers for the premium homepage hero.
 * =============================================================================
 *  Turns a hero_slides row into background styles, a highlighted/typed title,
 *  badges, trust signals, star ratings and styled CTA buttons. Shared by the
 *  public homepage (index.php) and the admin live preview so both match.
 * =============================================================================
 */

declare(strict_types=1);

/** Sensible per-slide defaults merged over a row (backward compatible). */
function hero_slide(array $s): array
{
    return array_merge([
        'accent' => '#084881', 'bg_type' => 'gradient', 'bg_from' => '#084881', 'bg_to' => '#084881',
        'bg_angle' => 135, 'overlay' => 45, 'layout' => 'center', 'height' => 'tall',
        'btn_style' => 'gradient', 'btn2_style' => 'glass', 'divider' => 'none', 'animate' => 1,
        'text_align' => 'left',
    ], array_filter($s, static fn ($v) => $v !== null && $v !== ''));
}

function hero_accent(array $s): string
{
    return $s['accent'] ?: '#084881';
}

/** Background CSS for a slide (gradient / mesh / image / solid / video base). */
function hero_bg_style(array $s): string
{
    $type = $s['bg_type'] ?? 'gradient';
    $from = $s['bg_from'] ?: '#084881';
    $to   = $s['bg_to'] ?: '#084881';
    $ang  = (int) ($s['bg_angle'] ?? 135);
    return match ($type) {
        'solid' => 'background:' . e($from) . ';',
        'image' => !empty($s['image'])
            ? "background-image:url('" . e(upload_url($s['image'])) . "');background-size:cover;background-position:center;"
            : "background:linear-gradient({$ang}deg," . e($from) . ',' . e($to) . ');',
        'mesh'  => 'background-color:' . e($to) . ';background-image:'
            . 'radial-gradient(at 18% 18%,' . e($from) . ' 0px,transparent 52%),'
            . 'radial-gradient(at 82% 8%,' . e($to) . ' 0px,transparent 48%),'
            . 'radial-gradient(at 62% 92%,' . e($from) . ' 0px,transparent 46%),'
            . 'radial-gradient(at 8% 78%,' . e($to) . ' 0px,transparent 44%);',
        'video' => 'background:#0b1020;',
        default => "background:linear-gradient({$ang}deg," . e($from) . ',' . e($to) . ');',
    };
}

/** Title HTML with gradient-highlighted words. Supports {curly} syntax or the highlight column. */
function hero_title_html(array $s): string
{
    $title = (string) ($s['title'] ?? '');
    $html  = e($title);
    // {word} → highlighted span.
    $html = preg_replace_callback('/\{([^}]+)\}/', static fn ($m) => '<span class="hx-hl">' . $m[1] . '</span>', $html);
    // Otherwise highlight words listed in the highlight column.
    if (strpos($title, '{') === false && !empty($s['highlight'])) {
        foreach (array_filter(array_map('trim', explode(',', (string) $s['highlight']))) as $w) {
            $we = e($w);
            $html = preg_replace('/(' . preg_quote($we, '/') . ')/u', '<span class="hx-hl">$1</span>', $html, 1) ?? $html;
        }
    }
    return nl2br($html);
}

/** The rotating typed word span (empty string when no typing words set). */
function hero_typing_html(array $s): string
{
    $words = array_values(array_filter(array_map('trim', explode(',', (string) ($s['typing_words'] ?? '')))));
    if (!$words) {
        return '';
    }
    return ' <span class="hx-hl hx-type" data-words="' . e(implode('|', $words)) . '">' . e($words[0]) . '</span>';
}

/** Star-rating markup (or '' when no rating). */
function hero_rating_html(array $s): string
{
    $r = (float) ($s['rating'] ?? 0);
    if ($r <= 0) {
        return '';
    }
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<span class="hx-star' . ($i <= round($r) ? ' on' : '') . '">' . lucide('star') . '</span>';
    }
    $count = !empty($s['rating_count']) ? '<b>' . e(number_format((int) $s['rating_count'])) . '</b> reviews' : '';
    return '<div class="hx-rating"><span class="hx-stars">' . $stars . '</span><span class="hx-rate-num">' . e(number_format($r, 1)) . '</span> ' . $count . '</div>';
}

/** One CTA button. $primary uses the accent gradient. */
function hero_cta(string $text, ?string $url, string $style, ?string $icon, bool $primary, string $accent): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $href = $url ? (preg_match('#^https?://#', $url) ? $url : url($url)) : url('donate');
    $ic   = $icon ? lucide($icon) : '';
    $var  = $primary ? ' style="--acc:' . e($accent) . '"' : '';
    return '<a class="hx-btn hx-btn-' . e($style) . ($primary ? ' is-primary' : '') . '" href="' . e($href) . '"' . $var . '>'
        . $ic . '<span>' . e($text) . '</span></a>';
}

/** Shape-divider SVG for the bottom of the hero (or '' for none). */
function hero_divider_svg(string $type): string
{
    $svg = match ($type) {
        'wave'     => '<path d="M0,64 C360,120 1080,8 1440,64 L1440,120 L0,120 Z"></path>',
        'curve'    => '<path d="M0,80 Q720,0 1440,80 L1440,120 L0,120 Z"></path>',
        'tilt'     => '<path d="M0,120 L1440,20 L1440,120 Z"></path>',
        'triangle' => '<path d="M0,120 L720,20 L1440,120 Z"></path>',
        default    => '',
    };
    if ($svg === '') {
        return '';
    }
    return '<div class="hx-divider"><svg viewBox="0 0 1440 120" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">' . $svg . '</svg></div>';
}
