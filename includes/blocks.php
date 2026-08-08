<?php
/**
 * =============================================================================
 *  Page builder — block registry, sanitiser and renderer
 * =============================================================================
 *  ONE definition drives all three consumers:
 *    - admin/page-builder.php  : builds the editor UI from `fields`
 *    - blocks_sanitize()       : whitelists a posted JSON payload
 *    - blocks_render()         : emits the public markup
 *
 *  Adding a widget therefore means adding one entry here — the editor, the
 *  save path and the front end all pick it up automatically. Previously each
 *  of the three had its own hand-written switch, so a new block had to be
 *  added in three places and they drifted.
 *
 *  SECURITY: every field is cast and escaped on the way out according to its
 *  declared type. Only 'richtext' emits HTML, and it goes through the same
 *  rich_text() sanitiser the rest of the site uses. A block type or field the
 *  registry does not declare is dropped on save, so a tampered payload cannot
 *  introduce new keys.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * The registry.
 *
 * Field types: text | textarea | richtext | url | image | color | select |
 *              number | repeater
 */
function blocks_registry(): array
{
    static $r = null;
    if ($r !== null) {
        return $r;
    }

    $r = [
        'heading' => [
            'label' => 'Heading', 'icon' => 'heading', 'group' => 'Basic',
            'fields' => [
                'text'  => ['type' => 'text',   'label' => 'Heading text'],
                'level' => ['type' => 'select', 'label' => 'Level', 'default' => 'h2',
                            'options' => ['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6']],
                'align' => ['type' => 'select', 'label' => 'Align', 'default' => 'left',
                            'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ],
        ],
        'text' => [
            'label' => 'Paragraph', 'icon' => 'align-left', 'group' => 'Basic',
            'fields' => ['body' => ['type' => 'textarea', 'label' => 'Text']],
        ],
        'richtext' => [
            'label' => 'Rich Text (HTML)', 'icon' => 'file-code', 'group' => 'Basic',
            'fields' => ['body' => ['type' => 'richtext', 'label' => 'Content']],
        ],
        'button' => [
            'label' => 'Button', 'icon' => 'mouse-pointer-click', 'group' => 'Basic',
            'fields' => [
                'text'    => ['type' => 'text',   'label' => 'Label'],
                'url'     => ['type' => 'url',    'label' => 'Link'],
                // NOT named 'style': blocks_sanitize() reserves that key for the
                // shared style drawer, and a field of the same name would be
                // overwritten by it on every save.
                'variant' => ['type' => 'select', 'label' => 'Style', 'default' => 'primary',
                              'options' => ['primary' => 'Primary', 'outline' => 'Outline', 'secondary' => 'Secondary']],
                'align'   => ['type' => 'select', 'label' => 'Align', 'default' => 'left',
                              'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right']],
            ],
        ],
        'image' => [
            'label' => 'Image', 'icon' => 'image', 'group' => 'Media',
            'fields' => [
                'url'     => ['type' => 'image', 'label' => 'Image'],
                'caption' => ['type' => 'text',  'label' => 'Caption'],
                'alt'     => ['type' => 'text',  'label' => 'Alt text (accessibility)'],
            ],
        ],
        'gallery' => [
            'label' => 'Image Gallery', 'icon' => 'images', 'group' => 'Media',
            'fields' => [
                'items' => ['type' => 'repeater', 'label' => 'Images', 'max' => 12,
                            'sub' => ['url' => ['type' => 'image', 'label' => 'Image'],
                                      'alt' => ['type' => 'text',  'label' => 'Alt text']]],
            ],
        ],
        'video' => [
            'label' => 'Video', 'icon' => 'video', 'group' => 'Media',
            'fields' => [
                'url'     => ['type' => 'url',  'label' => 'YouTube / Vimeo URL'],
                'caption' => ['type' => 'text', 'label' => 'Caption'],
            ],
        ],
        'hero' => [
            'label' => 'Hero Banner', 'icon' => 'panel-top', 'group' => 'Sections',
            'fields' => [
                'heading'     => ['type' => 'text',  'label' => 'Heading'],
                'subheading'  => ['type' => 'textarea', 'label' => 'Subheading'],
                'button_text' => ['type' => 'text',  'label' => 'Button label'],
                'button_url'  => ['type' => 'url',   'label' => 'Button link'],
                'bg_color'    => ['type' => 'color', 'label' => 'Background colour'],
            ],
        ],
        'cards' => [
            'label' => 'Cards', 'icon' => 'layout-grid', 'group' => 'Sections',
            'fields' => [
                'items' => ['type' => 'repeater', 'label' => 'Cards', 'max' => 9,
                            'sub' => ['icon'  => ['type' => 'text', 'label' => 'Lucide icon name'],
                                      'title' => ['type' => 'text', 'label' => 'Title'],
                                      'text'  => ['type' => 'textarea', 'label' => 'Description']]],
            ],
        ],
        'columns' => [
            'label' => 'Columns', 'icon' => 'columns-3', 'group' => 'Layout',
            'fields' => [
                'cols' => ['type' => 'repeater', 'label' => 'Columns', 'max' => 4,
                           'sub' => ['text' => ['type' => 'textarea', 'label' => 'Column content']]],
            ],
        ],
        'accordion' => [
            'label' => 'Accordion / FAQ', 'icon' => 'chevrons-up-down', 'group' => 'Sections',
            'fields' => [
                'items' => ['type' => 'repeater', 'label' => 'Items', 'max' => 20,
                            'sub' => ['q' => ['type' => 'text',     'label' => 'Question'],
                                      'a' => ['type' => 'textarea', 'label' => 'Answer']]],
            ],
        ],
        'cta' => [
            'label' => 'Call to Action', 'icon' => 'megaphone', 'group' => 'Sections',
            'fields' => [
                'heading'     => ['type' => 'text',     'label' => 'Heading'],
                'text'        => ['type' => 'textarea', 'label' => 'Supporting text'],
                'button_text' => ['type' => 'text',     'label' => 'Button label'],
                'button_url'  => ['type' => 'url',      'label' => 'Button link'],
            ],
        ],
        'divider' => [
            'label' => 'Divider', 'icon' => 'minus', 'group' => 'Layout',
            'fields' => [],
        ],
        'spacer' => [
            'label' => 'Spacer', 'icon' => 'move-vertical', 'group' => 'Layout',
            'fields' => ['height' => ['type' => 'number', 'label' => 'Height (px)', 'default' => '40']],
        ],
        'embed' => [
            'label' => 'Embed / Custom HTML', 'icon' => 'code', 'group' => 'Advanced',
            'fields' => ['body' => ['type' => 'richtext', 'label' => 'HTML']],
        ],
    ];
    return $r;
}

/**
 * Style options every block shares. Kept separate from `fields` so the editor
 * can render them in a collapsed "Style" drawer rather than inline.
 */
function blocks_style_fields(): array
{
    return [
        'pad'     => ['type' => 'select', 'label' => 'Vertical padding', 'default' => 'md',
                      'options' => ['none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large']],
        'bg'      => ['type' => 'select', 'label' => 'Background', 'default' => 'none',
                      'options' => ['none' => 'None', 'soft' => 'Soft tint', 'brand' => 'Brand', 'dark' => 'Dark']],
        'width'   => ['type' => 'select', 'label' => 'Width', 'default' => 'container',
                      'options' => ['container' => 'Container', 'narrow' => 'Narrow', 'full' => 'Full width']],
        'hide_on' => ['type' => 'select', 'label' => 'Hide on', 'default' => '',
                      'options' => ['' => 'Always visible', 'mobile' => 'Mobile', 'tablet' => 'Tablet', 'desktop' => 'Desktop']],
        'css'     => ['type' => 'text', 'label' => 'Extra CSS classes'],
    ];
}

/** Cast one value according to its declared field type. */
function blocks_cast(array $spec, $value): string
{
    $type = $spec['type'] ?? 'text';
    $v    = is_scalar($value) ? (string) $value : '';

    return match ($type) {
        'number' => (string) (int) $v,
        'url'    => $v === '' ? '' : mb_substr($v, 0, 500),
        'select' => array_key_exists($v, $spec['options'] ?? []) ? $v : (string) ($spec['default'] ?? ''),
        'color'  => preg_match('/^#[0-9a-f]{3,8}$/i', $v) ? $v : '',
        default  => mb_substr($v, 0, $type === 'text' ? 500 : 20000),
    };
}

/**
 * Whitelist a posted blocks payload against the registry.
 * Unknown types, unknown fields and stray keys are dropped.
 */
function blocks_sanitize(string $json): array
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return [];
    }
    $reg    = blocks_registry();
    $styles = blocks_style_fields();
    $clean  = [];

    foreach ($data as $b) {
        if (!is_array($b) || empty($b['type'])) {
            continue;
        }
        $type = (string) $b['type'];
        if (!isset($reg[$type])) {
            continue;                       // unknown block — drop entirely
        }
        $out = ['type' => $type];

        foreach ($reg[$type]['fields'] as $key => $spec) {
            if (($spec['type'] ?? '') === 'repeater') {
                $rows = [];
                foreach ((array) ($b[$key] ?? []) as $row) {
                    if (!is_array($row)) { continue; }
                    $r = [];
                    foreach ($spec['sub'] as $sk => $sspec) {
                        $r[$sk] = blocks_cast($sspec, $row[$sk] ?? '');
                    }
                    $rows[] = $r;
                }
                $out[$key] = array_slice($rows, 0, (int) ($spec['max'] ?? 12));
                continue;
            }
            $out[$key] = blocks_cast($spec, $b[$key] ?? ($spec['default'] ?? ''));
        }

        // Shared style options.
        $st = [];
        foreach ($styles as $key => $spec) {
            $val = blocks_cast($spec, ($b['style'][$key] ?? ($spec['default'] ?? '')));
            if ($val !== '') { $st[$key] = $val; }
        }
        if ($st) { $out['style'] = $st; }

        $clean[] = $out;
    }
    return $clean;
}

/** Wrapper classes from a block's style options. */
function blocks_wrap_class(array $b): string
{
    $s   = $b['style'] ?? [];
    $cls = ['pb-block', 'pb-' . ($b['type'] ?? 'x')];
    $cls[] = 'pb-pad-' . ($s['pad'] ?? 'md');
    if (!empty($s['bg']) && $s['bg'] !== 'none') { $cls[] = 'pb-bg-' . $s['bg']; }
    if (!empty($s['hide_on'])) { $cls[] = 'pb-hide-' . $s['hide_on']; }
    if (!empty($s['css'])) {
        // Only class-name-shaped tokens; never let arbitrary text into the attribute.
        foreach (preg_split('/\s+/', (string) $s['css']) as $t) {
            if (preg_match('/^[A-Za-z][\w-]{0,40}$/', $t)) { $cls[] = $t; }
        }
    }
    return implode(' ', $cls);
}

/** Inner container class from the width option. */
function blocks_inner_class(array $b): string
{
    return match ($b['style']['width'] ?? 'container') {
        'narrow' => 'container container-narrow',
        'full'   => 'pb-full',
        default  => 'container',
    };
}

/** Turn a YouTube/Vimeo URL into an embed URL, or '' when unrecognised. */
function blocks_embed_url(string $url): string
{
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return '';
}

/** Safe internal/external link, reusing the site helper when present. */
function blocks_href(string $url): string
{
    $url = trim($url);
    if ($url === '') { return '#'; }
    if (preg_match('~^(https?://|mailto:|tel:|#|/)~i', $url)) { return $url; }
    return function_exists('url') ? url($url) : $url;
}

/**
 * Render an ordered block array to HTML.
 */
function blocks_render(array $blocks): string
{
    if (!$blocks) {
        return '';
    }
    ob_start();
    foreach ($blocks as $b) {
        if (!is_array($b) || empty($b['type'])) { continue; }
        $type  = (string) $b['type'];
        $wrap  = blocks_wrap_class($b);
        $inner = blocks_inner_class($b);
        ?>
        <section class="<?= e($wrap) ?>">
            <div class="<?= e($inner) ?>">
            <?php switch ($type):

                case 'heading':
                    $lvl = in_array($b['level'] ?? 'h2', ['h1','h2','h3','h4','h5','h6'], true) ? $b['level'] : 'h2'; ?>
                    <<?= $lvl ?> class="pb-heading" style="text-align:<?= e($b['align'] ?? 'left') ?>"><?= e((string) ($b['text'] ?? '')) ?></<?= $lvl ?>>
                <?php break;

                case 'text': ?>
                    <p class="pb-text"><?= nl2br(e((string) ($b['body'] ?? ''))) ?></p>
                <?php break;

                case 'richtext':
                case 'embed': ?>
                    <div class="pb-rich"><?= function_exists('rich_text') ? rich_text((string) ($b['body'] ?? '')) : e((string) ($b['body'] ?? '')) ?></div>
                <?php break;

                case 'button':
                    $variant = in_array($b['variant'] ?? 'primary', ['primary', 'outline', 'secondary'], true)
                        ? $b['variant'] : 'primary'; ?>
                    <div style="text-align:<?= e($b['align'] ?? 'left') ?>">
                        <a class="btn btn-<?= e($variant) ?> btn-lg"
                           href="<?= e(blocks_href((string) ($b['url'] ?? ''))) ?>"><?= e((string) ($b['text'] ?? 'Learn more')) ?></a>
                    </div>
                <?php break;

                case 'image':
                    $src = (string) ($b['url'] ?? ''); if ($src === '') { break; } ?>
                    <figure class="pb-figure">
                        <img src="<?= e(function_exists('upload_url') ? upload_url($src) : $src) ?>"
                             alt="<?= e((string) ($b['alt'] ?? $b['caption'] ?? '')) ?>" loading="lazy" decoding="async">
                        <?php if (!empty($b['caption'])): ?><figcaption><?= e((string) $b['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php break;

                case 'gallery': ?>
                    <div class="pb-gallery">
                        <?php foreach ((array) ($b['items'] ?? []) as $it):
                            if (empty($it['url'])) { continue; } ?>
                            <img src="<?= e(function_exists('upload_url') ? upload_url((string) $it['url']) : (string) $it['url']) ?>"
                                 alt="<?= e((string) ($it['alt'] ?? '')) ?>" loading="lazy" decoding="async">
                        <?php endforeach; ?>
                    </div>
                <?php break;

                case 'video':
                    $emb = blocks_embed_url((string) ($b['url'] ?? '')); if ($emb === '') { break; } ?>
                    <div class="pb-video">
                        <iframe src="<?= e($emb) ?>" title="<?= e((string) ($b['caption'] ?? 'Video')) ?>"
                                loading="lazy" allowfullscreen
                                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                    <?php if (!empty($b['caption'])): ?><p class="pb-cap"><?= e((string) $b['caption']) ?></p><?php endif;
                break;

                case 'hero': ?>
                    <div class="pb-hero" <?= !empty($b['bg_color']) ? 'style="background:' . e((string) $b['bg_color']) . '"' : '' ?>>
                        <h2><?= e((string) ($b['heading'] ?? '')) ?></h2>
                        <?php if (!empty($b['subheading'])): ?><p><?= e((string) $b['subheading']) ?></p><?php endif; ?>
                        <?php if (!empty($b['button_text'])): ?>
                            <a class="btn btn-primary btn-lg" href="<?= e(blocks_href((string) ($b['button_url'] ?? ''))) ?>"><?= e((string) $b['button_text']) ?></a>
                        <?php endif; ?>
                    </div>
                <?php break;

                case 'cards': ?>
                    <div class="pb-cards">
                        <?php foreach ((array) ($b['items'] ?? []) as $it): ?>
                            <article class="pb-card">
                                <?php if (!empty($it['icon']) && function_exists('lucide')): ?>
                                    <span class="pb-card-ico"><?= lucide((string) $it['icon']) ?></span>
                                <?php endif; ?>
                                <h3><?= e((string) ($it['title'] ?? '')) ?></h3>
                                <p><?= nl2br(e((string) ($it['text'] ?? ''))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php break;

                case 'columns': ?>
                    <div class="pb-cols" style="--pb-n:<?= max(1, count((array) ($b['cols'] ?? []))) ?>">
                        <?php foreach ((array) ($b['cols'] ?? []) as $c): ?>
                            <div><?= nl2br(e((string) ($c['text'] ?? ''))) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php break;

                case 'accordion': ?>
                    <div class="pb-acc">
                        <?php foreach ((array) ($b['items'] ?? []) as $it):
                            if (empty($it['q'])) { continue; } ?>
                            <details class="pb-acc-item">
                                <summary><?= e((string) $it['q']) ?></summary>
                                <div><?= nl2br(e((string) ($it['a'] ?? ''))) ?></div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php break;

                case 'cta': ?>
                    <div class="pb-cta">
                        <h2><?= e((string) ($b['heading'] ?? '')) ?></h2>
                        <?php if (!empty($b['text'])): ?><p><?= e((string) $b['text']) ?></p><?php endif; ?>
                        <?php if (!empty($b['button_text'])): ?>
                            <a class="btn btn-primary btn-lg" href="<?= e(blocks_href((string) ($b['button_url'] ?? ''))) ?>"><?= e((string) $b['button_text']) ?></a>
                        <?php endif; ?>
                    </div>
                <?php break;

                case 'divider': ?>
                    <hr class="pb-divider">
                <?php break;

                case 'spacer': ?>
                    <div class="pb-spacer" style="height:<?= max(0, min(400, (int) ($b['height'] ?? 40))) ?>px"></div>
                <?php break;

            endswitch; ?>
            </div>
        </section>
        <?php
    }
    return (string) ob_get_clean();
}
