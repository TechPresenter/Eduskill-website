<?php
/**
 * =============================================================================
 *  Premium FAQ section — reusable renderer
 * =============================================================================
 *  Usage anywhere on the public site:
 *
 *      echo faq_section();                                  // all active FAQs
 *      echo faq_section(['category' => 'donations']);       // one category
 *      echo faq_section(['faqs' => $rows, 'title' => '…']); // supply your own
 *
 *  Appearance is entirely settings-driven (Admin → Website Settings → FAQ
 *  Section). Each option becomes a data-attribute the stylesheet keys off, so
 *  the admin can recombine themes, animations, hover and icon styles without a
 *  code change.
 *
 *  Markup stays <details>/<summary>, so it is keyboard-operable, screen-reader
 *  friendly and works with JavaScript disabled — the script only adds the
 *  single-open behaviour, search and stagger.
 * =============================================================================
 */

declare(strict_types=1);

/** Whitelists — an unknown/stale setting falls back to the first entry. */
function faq_options(): array
{
    return [
        'theme'      => ['ngo', 'blue-purple', 'orange-pink', 'green-cyan', 'indigo-violet',
                         'red-orange', 'teal-emerald', 'rainbow', 'corporate', 'minimal'],
        'animation'  => ['slide', 'fade-slide', 'scale', 'bounce', 'elastic', 'flip', 'rotate',
                         'zoom', 'fold', 'curtain', 'morph', 'liquid', 'spring', 'ripple'],
        'hover'      => ['lift', 'gradient-border', 'neon', 'gradient-bg', 'float', 'glass', 'shine',
                         'magnetic', 'border-draw', 'pulse', 'shadow', 'color', 'tilt', 'ring'],
        'icon'       => ['plus-minus', 'arrow', 'chevron', 'pulse', 'bounce', 'wiggle',
                         'morph', 'circular', 'glow', 'gradient'],
        'border'     => ['gradient', 'rainbow', 'neon', 'dashed', 'pulse', 'shimmer', 'glow', 'liquid'],
        'background' => ['mesh', 'none', 'aurora', 'blobs', 'particles', 'waves', 'rays', 'sparkles'],
    ];
}

/** Read a setting, clamped to its whitelist. */
function faq_opt(string $key): string
{
    $all   = faq_options();
    $list  = $all[$key] ?? [];
    $value = (string) get_setting('faq_' . ($key === 'background' ? 'background' : $key), '');
    return in_array($value, $list, true) ? $value : ($list[0] ?? '');
}

/** Icon markup for the chosen icon style. */
function faq_icon_svg(string $style): string
{
    $wrap = static fn(string $inner): string =>
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';

    return match ($style) {
        'arrow'    => $wrap('<line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline>'),
        'chevron'  => $wrap('<polyline points="6 9 12 15 18 9"></polyline>'),
        'circular' => $wrap('<polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>'),
        default    => $wrap('<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>'),
    };
}

/**
 * Render the section. Returns HTML (nothing is echoed directly).
 */
function faq_section(array $o = []): string
{
    $faqs = $o['faqs'] ?? null;
    if ($faqs === null) {
        try {
            if (!empty($o['category'])) {
                $faqs = db_all(
                    "SELECT * FROM faqs WHERE status = 1 AND category = :c ORDER BY sort_order ASC, id ASC",
                    [':c' => $o['category']]
                );
            } else {
                $faqs = db_all("SELECT * FROM faqs WHERE status = 1 ORDER BY sort_order ASC, id ASC");
            }
        } catch (Throwable $e) {
            $faqs = [];
        }
    }
    if (!$faqs) {
        return '';   // nothing to show — render nothing rather than an empty shell
    }

    // Flag it so includes/footer.php emits the behaviour script.
    $GLOBALS['__pwf_faq_used'] = true;

    $eyebrow = (string) ($o['eyebrow'] ?? 'FAQ');
    $title   = (string) ($o['title']   ?? 'Questions, answered');
    $hl      = (string) ($o['highlight'] ?? '');
    $sub     = (string) ($o['subtitle'] ?? 'Everything people usually ask us. Still stuck? Get in touch and a real person will reply.');

    $iconStyle = faq_opt('icon');
    $showSearch = (string) get_setting('faq_show_search', '1') === '1' && count($faqs) > 3;
    $single     = (string) get_setting('faq_single_open', '1') === '1';

    // Numeric tunables, clamped so a bad settings value cannot break the layout.
    $clamp = static fn($v, $lo, $hi, $def) => is_numeric($v) ? max($lo, min($hi, (int) $v)) : $def;
    $vars = sprintf(
        '--pf-r:%dpx;--pf-dur:%dms;--pf-gap:%dpx;--pf-fs:%dpx;--pf-shadow:%d;--pf-glow:%d;',
        $clamp(get_setting('faq_radius', 20), 0, 40, 20),
        $clamp(get_setting('faq_duration', 380), 80, 1200, 380),
        $clamp(get_setting('faq_spacing', 12), 0, 40, 12),
        $clamp(get_setting('faq_font_size', 16), 12, 24, 16),
        $clamp(get_setting('faq_shadow', 3), 0, 5, 3),
        $clamp(get_setting('faq_glow', 2), 0, 5, 2)
    );

    // The stylesheet ships with the first section on the page rather than from
    // the footer: a <link> after the markup would repaint the accordion mid-load.
    // A <link> in <body> is valid HTML5 and blocks render for this subtree only.
    static $cssOut = false;
    $cssLink = '';
    if (!$cssOut) {
        $cssOut  = true;
        $cssLink = '<link rel="stylesheet" href="' . e(asset('css/faq-premium.css')) . '">';
    }

    /* 'bare' drops the <section> chrome — heading, subtitle and animated
       backdrop — and emits only the themed accordion. Used where a page
       already has its own FAQ heading (contact, volunteer, internship,
       scholarship) so the two do not compete. All theming still applies,
       because it rides on the data attributes, not the wrapper. */
    $bare = !empty($o['bare']);

    ob_start();
    echo $cssLink; ?>
<<?= $bare ? 'div' : 'section' ?> class="pfaq<?= $bare ? ' pfaq-bare' : '' ?>"
         data-theme-pf="<?= e(faq_opt('theme')) ?>"
         data-anim="<?= e(faq_opt('animation')) ?>"
         data-hover="<?= e(faq_opt('hover')) ?>"
         data-icon="<?= e($iconStyle) ?>"
         data-border="<?= e(faq_opt('border')) ?>"
         data-bg="<?= e(faq_opt('background')) ?>"
         data-single="<?= $single ? '1' : '0' ?>"
         style="<?= e($vars) ?>">

    <?php if (!$bare): ?>
    <div class="pfaq-bg" aria-hidden="true">
        <span class="pfaq-mesh"></span>
        <span class="pfaq-aurora"></span>
        <span class="pfaq-blob"></span><span class="pfaq-blob"></span><span class="pfaq-blob"></span>
        <span class="pfaq-wave"></span>
        <span class="pfaq-ray"></span>
        <?php for ($i = 0; $i < 9; $i++): ?>
            <span class="pfaq-dot" style="left:<?= 6 + $i * 10 ?>%;bottom:<?= 4 + ($i % 4) * 9 ?>%;animation-duration:<?= 7 + ($i % 5) ?>s;animation-delay:<?= $i * .7 ?>s"></span>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <div class="<?= $bare ? 'pfaq-inner' : 'container' ?>">
        <?php if (!$bare): ?>
        <header class="pfaq-head">
            <span class="pfaq-eyebrow"><?= e($eyebrow) ?></span>
            <h2 class="pfaq-title">
                <?= e($title) ?><?= $hl !== '' ? ' <span class="hl">' . e($hl) . '</span>' : '' ?>
            </h2>
            <p class="pfaq-sub"><?= e($sub) ?></p>

            <?php if ($showSearch): ?>
                <div class="pfaq-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input type="search" placeholder="Search questions…" aria-label="Search frequently asked questions" data-pfaq-search>
                </div>
            <?php endif; ?>
        </header>
        <?php endif; ?>

        <div class="pfaq-list" data-pfaq-list>
            <?php foreach ($faqs as $i => $f):
                $q = (string) ($f['question'] ?? '');
                $a = (string) ($f['answer'] ?? '');
                if ($q === '') { continue; } ?>
                <details class="pfaq-item" data-pfaq-item
                         data-search="<?= e(mb_strtolower($q . ' ' . strip_tags($a))) ?>"
                         style="transition-delay:<?= min($i * 55, 440) ?>ms">
                    <summary class="pfaq-q">
                        <span class="pfaq-q-txt"><?= e($q) ?></span>
                        <span class="pfaq-ico"><?= faq_icon_svg($iconStyle) ?></span>
                    </summary>
                    <div class="pfaq-wrap"><div>
                        <div class="pfaq-a"><?= function_exists('rich_text') ? rich_text($a) : nl2br(e($a)) ?></div>
                    </div></div>
                </details>
            <?php endforeach; ?>
        </div>

        <p class="pfaq-empty" data-pfaq-empty>No questions match that search.</p>
    </div>
</<?= $bare ? 'div' : 'section' ?>>
<?php
    $html = (string) ob_get_clean();

    // Admin escape hatches. CSS is scoped by convention; JS is emitted verbatim
    // and is an admin-only capability, same as the site-wide custom_js setting.
    $css = trim((string) get_setting('faq_custom_css', ''));
    $js  = trim((string) get_setting('faq_custom_js', ''));
    if ($css !== '') { $html .= '<style id="pfaq-custom-css">' . $css . '</style>'; }
    if ($js  !== '') { $html .= '<script id="pfaq-custom-js">' . $js . '</script>'; }

    return $html;
}

/**
 * Behaviour script for faq_section(), emitted once by includes/footer.php.
 * The stylesheet ships inline with the first section (see above) to avoid a
 * flash of unstyled accordion.
 */
function faq_section_assets(): string
{
    static $done = false;
    if ($done || empty($GLOBALS['__pwf_faq_used'])) {
        return '';
    }
    $done = true;
    return '<script src="' . e(asset('js/faq-premium.js')) . '" defer></script>';
}
