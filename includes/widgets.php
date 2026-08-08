<?php
/**
 * =============================================================================
 *  Widget manager — reusable content blocks rendered into named positions
 *  (e.g. 'footer', 'sidebar', 'homepage'). Managed from admin/widgets.php.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Return active widgets assigned to a position, ordered by sort_order.
 */
function get_widgets(string $position): array
{
    try {
        return db_all(
            "SELECT * FROM widgets WHERE status = 1 AND position = :p ORDER BY sort_order ASC, id ASC",
            [':p' => $position]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Render a single widget's inner HTML based on its type.
 * 'html' content is admin-authored and trusted (output raw); other types escape.
 */
function render_widget(array $w): string
{
    $content = (string) ($w['content'] ?? '');
    switch ($w['type'] ?? 'html') {
        case 'text':
            return '<p>' . nl2br(e($content)) . '</p>';

        case 'links':
            // One "Label | url" per line.
            $out = '<ul class="widget-links">';
            foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                [$label, $href] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '#');
                $href = preg_match('#^https?://#i', $href) ? $href : url($href);
                $out .= '<li><a href="' . e($href) . '">' . e($label) . '</a></li>';
            }
            return $out . '</ul>';

        case 'contact':
            return '<ul class="widget-contact">'
                . '<li>' . lucide('map-pin') . ' ' . e(get_setting('contact_address', SITE_ADDRESS)) . '</li>'
                . '<li>' . lucide('mail') . ' <a href="mailto:' . e(get_setting('contact_email', SITE_EMAIL)) . '">' . e(get_setting('contact_email', SITE_EMAIL)) . '</a></li>'
                . '<li>' . lucide('phone') . ' <a href="tel:' . e(preg_replace('/\s+/', '', get_setting('contact_phone', SITE_PHONE))) . '">' . e(get_setting('contact_phone', SITE_PHONE)) . '</a></li>'
                . '</ul>';

        case 'newsletter':
            return '<form class="newsletter-form" data-ajax-form data-endpoint="' . e(url('forms/newsletter')) . '">'
                . csrf_field()
                . '<input type="email" name="email" placeholder="Your email" required aria-label="Email">'
                . '<button class="btn btn-primary" type="submit">' . lucide('arrow-right') . '</button></form>';

        case 'html':
        default:
            return $content; // trusted admin HTML
    }
}

/**
 * Echo all active widgets for a position, each wrapped in a titled block.
 * Returns true if anything was rendered.
 */
function render_widgets(string $position): bool
{
    $widgets = get_widgets($position);
    if (!$widgets) {
        return false;
    }
    foreach ($widgets as $w) {
        echo '<div class="footer-widget widget-' . e($w['type']) . '">';
        if (!empty($w['title'])) {
            echo '<h4>' . e($w['title']) . '</h4>';
        }
        echo render_widget($w);
        echo '</div>';
    }
    return true;
}
