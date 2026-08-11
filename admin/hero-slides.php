<?php
/**
 * =============================================================================
 *  Admin — Hero Slider (premium, fully customisable homepage hero).
 *  Card gallery + a tabbed editor (Content / Design / Buttons / Effects) for
 *  every creative control: badge, highlighted/typing title, background style
 *  (gradient/mesh/image/solid/video), overlay, accent, layout, CTAs, trust
 *  signals, rating, shape divider and animation. Preserves the standard CRUD
 *  contract (CSRF, uploads, activity log, flash + redirect).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/hero.php';

$table  = 'hero_slides';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/** Curated icon set for badge/CTA pickers. */
$ICONS = ['', 'heart', 'hand-heart', 'shield-check', 'sparkles', 'star', 'award', 'gift', 'graduation-cap',
    'book-open', 'users', 'globe', 'target', 'rocket', 'trending-up', 'badge-check', 'flame', 'zap',
    'arrow-right', 'chevron-right', 'phone', 'mail', 'calendar-days', 'megaphone', 'gem', 'crown', 'leaf', 'sun'];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/hero-slides?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }
    $enum = static fn ($v, array $opts, $def) => in_array($v, $opts, true) ? $v : $def;

    $data = [
        'title'        => clean(post('title')),
        'badge_text'   => clean(post('badge_text', '')),
        'badge_icon'   => clean(post('badge_icon', '')),
        'subtitle'     => clean(post('subtitle', '')),
        'highlight'    => clean(post('highlight', '')),
        'typing_words' => clean(post('typing_words', '')),
        'description'  => clean(post('description', '')),
        'accent'       => clean(post('accent', '')) ?: null,
        'bg_type'      => $enum(post('bg_type'), ['gradient', 'mesh', 'image', 'solid', 'video'], 'gradient'),
        'bg_from'      => clean(post('bg_from', '')) ?: null,
        'bg_to'        => clean(post('bg_to', '')) ?: null,
        'bg_angle'     => max(0, min(360, (int) post('bg_angle', 135))),
        'bg_video'     => clean(post('bg_video', '')) ?: null,
        'overlay'      => max(0, min(100, (int) post('overlay', 45))),
        'button_text'  => clean(post('button_text', '')),
        'button_url'   => clean(post('button_url', '')),
        'btn_style'    => $enum(post('btn_style'), ['gradient', 'glass', 'outline', 'solid'], 'gradient'),
        'btn_icon'     => clean(post('btn_icon', '')),
        'button2_text' => clean(post('button2_text', '')),
        'button2_url'  => clean(post('button2_url', '')),
        'btn2_style'   => $enum(post('btn2_style'), ['gradient', 'glass', 'outline', 'solid'], 'glass'),
        'btn2_icon'    => clean(post('btn2_icon', '')),
        'trust_text'   => clean(post('trust_text', '')),
        'rating'       => post('rating') !== '' ? max(0, min(5, (float) post('rating'))) : null,
        'rating_count' => post('rating_count') !== '' ? (int) post('rating_count') : null,
        'text_align'   => $enum(post('text_align'), ['left', 'center', 'right'], 'left'),
        'layout'       => $enum(post('layout'), ['center', 'split'], 'center'),
        'height'       => $enum(post('height'), ['auto', 'tall', 'full'], 'tall'),
        'divider'      => $enum(post('divider'), ['none', 'wave', 'curve', 'tilt', 'triangle'], 'none'),
        'animate'      => post('animate') ? 1 : 0,
        'sort_order'   => (int) post('sort_order', 0),
        'status'       => post('status') ? 1 : 0,
    ];

    foreach (['image' => 'image', 'hero_image' => 'hero_image'] as $field => $col) {
        if (!empty($_FILES[$field]['name'])) {
            $up = upload_image($_FILES[$field], 'slides');
            if (!$up['success']) {
                set_flash('error', $up['error']);
                redirect('/admin/hero-slides?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
            }
            $old = $editId ? find($table, $editId) : null;
            $data[$col] = $up['path'];
            if ($old && !empty($old[$col])) { delete_upload($old[$col]); }
        }
    }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'hero_slides', 'Updated hero slide #' . $editId);
        set_flash('success', 'Hero slide updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'hero_slides', 'Created hero slide #' . $newId);
        set_flash('success', 'Hero slide created.');
    }
    redirect('/admin/hero-slides');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        foreach (['image', 'hero_image'] as $c) { if (!empty($row[$c])) delete_upload($row[$c]); }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'hero_slides', 'Deleted hero slide #' . $delId);
        set_flash('success', 'Hero slide deleted.');
    }
    redirect('/admin/hero-slides');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Hero slide not found.'); redirect('/admin/hero-slides'); }
    $v  = static fn ($k, $d = '') => e((string) ($row[$k] ?? $d));
    $sel = static fn ($k, $val, $d = '') => (($row[$k] ?? $d) === $val) ? 'selected' : '';
    $chk = static fn ($k, $d = 0) => (!array_key_exists($k, (array) $row) ? $d : !empty($row[$k])) ? 'checked' : '';
    $iconSelect = static function (string $name, string $current) use ($ICONS) {
        $h = '<select class="form-select" name="' . e($name) . '">';
        foreach ($ICONS as $ic) { $h .= '<option value="' . e($ic) . '" ' . ($ic === $current ? 'selected' : '') . '>' . ($ic === '' ? '— none —' : e($ic)) . '</option>'; }
        return $h . '</select>';
    };
    $page_title = $action === 'edit' ? 'Edit Hero Slide' : 'New Hero Slide';
    $prev = hero_slide((array) $row);
    include __DIR__ . '/partials/head.php';
    ?>
    <style>
    /* Screen-local layout ONLY. The tab strip is the shared `.tabs`/`.tab`
       component from admin-pro.css — this file carried a third copy of it
       (`.hs-tab*`, after security.php's and document-builder.php's). The badge
       lost its backdrop-filter (glassmorphism) for a flat ink scrim, and the
       overlay/scrim tints are mixed from brand ink, not a blue-black. */
    .hs-panel{display:none}
    .hs-panel.is-active{display:block}
    /* Mirrors the shared layers' own specificity (`body.admin .panel`,
       `.admin-content .panel`) so a nested panel can flatten without !important. */
    .admin-content .panel.hs-sub{box-shadow:none;margin-bottom:var(--sp-4)}
    .admin-content .panel.hs-sub:last-child{margin-bottom:0}
    .hs-prev{position:relative;isolation:isolate;display:flex;align-items:center;min-height:220px;padding:var(--sp-6);border-radius:var(--r-lg);overflow:hidden;color:#fff}
    .hs-prev .ov{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(21,24,24,.3),rgba(21,24,24,.7))}
    .hs-prev-body{width:100%}
    .hs-prev-badge{display:inline-flex;align-items:center;gap:var(--sp-1);margin-bottom:var(--sp-3);padding:var(--sp-1) var(--sp-3);border:1px solid rgba(255,255,255,.24);border-radius:var(--r-pill);background:rgba(21,24,24,.55);font-size:.75rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
    .hs-prev-title{margin:0 0 var(--sp-2);font-size:1.7rem;font-weight:800;line-height:1.1}
    .hs-prev-sub{max-width:46ch;font-size:.9rem;opacity:.9}
    /* Mirrors the public hero's .hx-hl so the preview tells the truth. The accent
       fallback is the brand green hero_accent() itself falls back to — it used to
       be a purple that is nowhere in the palette. */
    .hs-prev .hx-hl{background:linear-gradient(120deg,var(--acc,#174D3D),#fff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
    .swatch{display:flex;align-items:center;gap:var(--sp-2)}
    .swatch input[type=color]{width:44px;height:44px;padding:2px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface);cursor:pointer}
    .hs-desc{min-height:90px}
    .hs-thumb{max-height:90px}
    .hs-thumb-lg{max-height:110px}
    .hs-check-gap{margin-top:var(--sp-2)}
    </style>

    <div class="admin-page-head">
        <div><h1><?= lucide('image') ?> <?= e($page_title) ?></h1><span class="muted">Hero Slider / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('hero-slides')) ?>">← Back</a>
    </div>

    <!-- live-ish preview (reflects saved state) -->
    <div class="panel"><div class="panel-body">
        <div class="hs-prev" style="<?= hero_bg_style($prev) ?>--acc:<?= e(hero_accent($prev)) ?>;text-align:<?= e($prev['text_align']) ?>">
            <?php if ((int) ($prev['overlay'] ?? 0) > 0 && in_array($prev['bg_type'], ['image', 'video'], true)): ?><span class="ov" style="opacity:<?= (int) $prev['overlay'] / 100 ?>"></span><?php endif; ?>
            <div class="hs-prev-body">
                <?php if (!empty($prev['badge_text'])): ?><span class="hs-prev-badge"><?= !empty($prev['badge_icon']) ? lucide($prev['badge_icon']) : '' ?><?= e($prev['badge_text']) ?></span><br><?php endif; ?>
                <?php /* hx-hl, not hero-hl: the highlight class the public hero and this
                         screen's own `.hs-prev .hx-hl` rule both use. The old name matched no
                         rule anywhere, so the empty-form preview showed plain text where a
                         live slide shows the accent gradient. */ ?>
                <div class="hs-prev-title"><?= $row ? hero_title_html($prev) . hero_typing_html($prev) : 'Your headline with a <span class="hx-hl">highlight</span>' ?></div>
                <div class="hs-prev-sub"><?= e($prev['description'] ?: $prev['subtitle'] ?: 'Subtitle / supporting copy appears here.') ?></div>
            </div>
        </div>
        <p class="form-hint">Preview reflects the last saved state. Save to refresh.</p>
    </div></div>

    <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= e(admin_url('hero-slides')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="panel"><div class="panel-body">
            <div class="tabs" role="tablist">
                <button type="button" class="tab is-active" data-tab="content"><?= lucide('type') ?> Content</button>
                <button type="button" class="tab" data-tab="design"><?= lucide('palette') ?> Design</button>
                <button type="button" class="tab" data-tab="buttons"><?= lucide('mouse-pointer-click') ?> Buttons</button>
                <button type="button" class="tab" data-tab="effects"><?= lucide('sparkles') ?> Effects</button>
            </div>

            <!-- CONTENT -->
            <section class="hs-panel is-active" id="hs-content">
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Badge text</label><input class="form-control" name="badge_text" maxlength="120" value="<?= $v('badge_text') ?>" placeholder="Admissions open for 2026"></div>
                    <div class="form-group"><label class="form-label">Badge icon</label><?= $iconSelect('badge_icon', (string) ($row['badge_icon'] ?? 'sparkles')) ?></div>
                </div>
                <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" required maxlength="191" value="<?= $v('title') ?>" placeholder="Build Your Career in {Digital Marketing}">
                    <span class="form-hint">Wrap words in <code>{curly braces}</code> to gradient-highlight them.</span></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Highlight words</label><input class="form-control" name="highlight" maxlength="191" value="<?= $v('highlight') ?>" placeholder="comma,separated (alt to braces)"></div>
                    <div class="form-group"><label class="form-label">Typing words (rotating)</label><input class="form-control" name="typing_words" maxlength="255" value="<?= $v('typing_words') ?>" placeholder="Digital Marketing, Web Design, Data Science"></div>
                </div>
                <div class="form-group"><label class="form-label">Subtitle</label><input class="form-control" name="subtitle" maxlength="255" value="<?= $v('subtitle') ?>"></div>
                <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea hs-desc" name="description"><?= $v('description') ?></textarea></div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Trust signal text</label><input class="form-control" name="trust_text" maxlength="191" value="<?= $v('trust_text') ?>" placeholder="Trusted by 12,000+ members"></div>
                    <div class="form-group"><label class="form-label">Rating (0–5)</label><input class="form-control" type="number" step="0.1" min="0" max="5" name="rating" value="<?= $v('rating') ?>" placeholder="4.9"></div>
                    <div class="form-group"><label class="form-label">Rating count</label><input class="form-control" type="number" min="0" name="rating_count" value="<?= $v('rating_count') ?>" placeholder="1200"></div>
                </div>
            </section>

            <!-- DESIGN -->
            <section class="hs-panel" id="hs-design">
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Background type</label>
                        <select class="form-select" name="bg_type"><?php foreach (['gradient' => 'Gradient', 'mesh' => 'Mesh gradient', 'image' => 'Image', 'solid' => 'Solid colour', 'video' => 'Video'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel('bg_type', $k, 'gradient') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <?php /* NOTE: this default is left exactly as shipped. Changing it changes
                             the value a NEW slide submits when the admin never opens the picker,
                             which is a data change, not a presentation one — it needs its own
                             product decision (and a migration for existing rows) rather than
                             riding along in a CSS pass. hero_accent() falls back to #174D3D. */ ?>
                    <div class="form-group"><label class="form-label">Accent colour</label><div class="swatch"><input type="color" name="accent" value="<?= $v('accent', '#a855f7') ?>"><input class="form-control" value="<?= $v('accent', '#a855f7') ?>" readonly></div></div>
                    <div class="form-group"><label class="form-label">Overlay darkness (%)</label><input class="form-control" type="number" min="0" max="100" name="overlay" value="<?= $v('overlay', '45') ?>"></div>
                </div>
                <div class="grid-3">
                    <?php /* Same reasoning as the accent field above: submitted-value defaults
                             are data, so they stay at the shipped values. */ ?>
                    <div class="form-group"><label class="form-label">Gradient from</label><div class="swatch"><input type="color" name="bg_from" value="<?= $v('bg_from', '#084881') ?>"><input class="form-control" value="<?= $v('bg_from', '#084881') ?>" readonly></div></div>
                    <div class="form-group"><label class="form-label">Gradient to</label><div class="swatch"><input type="color" name="bg_to" value="<?= $v('bg_to', '#084881') ?>"><input class="form-control" value="<?= $v('bg_to', '#084881') ?>" readonly></div></div>
                    <div class="form-group"><label class="form-label">Gradient angle (°)</label><input class="form-control" type="number" min="0" max="360" name="bg_angle" value="<?= $v('bg_angle', '135') ?>"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Background image (for “Image” type)</label>
                        <input class="form-control" type="file" name="image" accept="image/*" data-preview="#bgPrev">
                        <img id="bgPrev" class="img-preview hs-thumb" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" style="<?= empty($row['image']) ? 'display:none;' : '' ?>"></div>
                    <div class="form-group"><label class="form-label">Background video URL (for “Video” type)</label><input class="form-control" name="bg_video" value="<?= $v('bg_video') ?>" placeholder="/uploads/... or https://…"></div>
                </div>
                <div class="grid-3">
                    <div class="form-group"><label class="form-label">Layout</label><select class="form-select" name="layout"><option value="center" <?= $sel('layout', 'center', 'center') ?>>Centered</option><option value="split" <?= $sel('layout', 'split', 'center') ?>>Split (with image)</option></select></div>
                    <div class="form-group"><label class="form-label">Section height</label><select class="form-select" name="height"><?php foreach (['tall' => 'Tall', 'full' => 'Full screen', 'auto' => 'Compact'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel('height', $k, 'tall') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Text alignment</label><select class="form-select" name="text_align"><?php foreach (['left', 'center', 'right'] as $a): ?><option value="<?= $a ?>" <?= $sel('text_align', $a, 'left') ?>><?= ucfirst($a) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label class="form-label">Foreground / side image (for “Split” layout)</label>
                    <input class="form-control" type="file" name="hero_image" accept="image/*" data-preview="#fgPrev">
                    <img id="fgPrev" class="img-preview hs-thumb-lg" src="<?= e(!empty($row['hero_image']) ? upload_url($row['hero_image']) : asset('images/placeholder.svg')) ?>" style="<?= empty($row['hero_image']) ? 'display:none;' : '' ?>"></div>
            </section>

            <!-- BUTTONS -->
            <section class="hs-panel" id="hs-buttons">
                <div class="panel hs-sub"><div class="panel-head"><h2 class="panel-title"><?= lucide('mouse-pointer-click') ?> Primary button</h2></div><div class="panel-body">
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Text</label><input class="form-control" name="button_text" maxlength="64" value="<?= $v('button_text') ?>" placeholder="Get Started"></div>
                        <div class="form-group"><label class="form-label">URL</label><input class="form-control" name="button_url" maxlength="255" value="<?= $v('button_url') ?>" placeholder="/donate"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Style</label><select class="form-select" name="btn_style"><?php foreach (['gradient' => 'Gradient (accent)', 'solid' => 'Solid white', 'glass' => 'Glass', 'outline' => 'Outline'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel('btn_style', $k, 'gradient') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label class="form-label">Icon</label><?= $iconSelect('btn_icon', (string) ($row['btn_icon'] ?? 'arrow-right')) ?></div>
                    </div>
                </div></div>
                <div class="panel hs-sub"><div class="panel-head"><h2 class="panel-title"><?= lucide('mouse-pointer-click') ?> Secondary button</h2></div><div class="panel-body">
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Text</label><input class="form-control" name="button2_text" maxlength="64" value="<?= $v('button2_text') ?>" placeholder="Learn More"></div>
                        <div class="form-group"><label class="form-label">URL</label><input class="form-control" name="button2_url" maxlength="255" value="<?= $v('button2_url') ?>" placeholder="/about"></div>
                    </div>
                    <div class="grid-2">
                        <div class="form-group"><label class="form-label">Style</label><select class="form-select" name="btn2_style"><?php foreach (['glass' => 'Glass', 'outline' => 'Outline', 'solid' => 'Solid white', 'gradient' => 'Gradient (accent)'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel('btn2_style', $k, 'glass') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label class="form-label">Icon</label><?= $iconSelect('btn2_icon', (string) ($row['btn2_icon'] ?? '')) ?></div>
                    </div>
                </div></div>
            </section>

            <!-- EFFECTS -->
            <section class="hs-panel" id="hs-effects">
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Shape divider (bottom)</label><select class="form-select" name="divider"><?php foreach (['none' => 'None', 'wave' => 'Wave', 'curve' => 'Curve', 'tilt' => 'Tilt', 'triangle' => 'Triangle'] as $k => $l): ?><option value="<?= $k ?>" <?= $sel('divider', $k, 'none') ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Sort order</label><input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>"></div>
                </div>
                <label class="checkbox"><input type="checkbox" name="animate" value="1" <?= $chk('animate', 1) ?>> Entrance animations &amp; floating effects</label><br>
                <label class="checkbox hs-check-gap"><input type="checkbox" name="status" value="1" <?= $chk('status', 1) ?>> Active (show on homepage)</label>
            </section>
        </div></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> <?= $action === 'edit' ? 'Update' : 'Create' ?> Slide</button><a class="btn btn-ghost" href="<?= e(admin_url('hero-slides')) ?>">Cancel</a></div>
    </form>

    <script>
    (function () {
        // Shared .tabs component (admin-pro.css) — the local .hs-tab copy is gone.
        var tabs = document.querySelectorAll('.tabs .tab'), panels = document.querySelectorAll('.hs-panel');
        tabs.forEach(function (t) { t.addEventListener('click', function () {
            tabs.forEach(function (x) { x.classList.toggle('is-active', x === t); });
            panels.forEach(function (p) { p.classList.toggle('is-active', p.id === 'hs-' + t.dataset.tab); });
        }); });
        // color swatch → mirror hex into readonly text field
        document.querySelectorAll('.swatch input[type=color]').forEach(function (c) {
            var txt = c.nextElementSibling;
            c.addEventListener('input', function () { if (txt) txt.value = c.value; });
        });
    })();
    </script>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* -------------------------------------------------------------- LIST (card gallery) */
$page_title = 'Hero Slider';
$rows = db_all("SELECT * FROM $table ORDER BY sort_order ASC, id DESC");
include __DIR__ . '/partials/head.php';
?>
<style>
/* Screen-local layout ONLY. Each card is now a `.panel`, so its radius (--r-lg,
   was 18px), border, elevation and corner clipping all come from the shared
   surface — including the no-lift-on-hover rule. Gone with the old block: the
   translateY hover, the 0 2px 10px black text-shadow, the backdrop-filter glass
   badge, 0.68rem type, and the purple accent fallback. */
.hero-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:var(--sp-5)}
.admin-content .panel.hero-card{margin-bottom:0}
.hero-card-prev{position:relative;isolation:isolate;display:flex;align-items:flex-end;min-height:170px;padding:var(--sp-4);color:#fff}
.hero-card-prev .ov{position:absolute;inset:0;z-index:-1;background:linear-gradient(180deg,rgba(21,24,24,.2),rgba(21,24,24,.7))}
.hero-card-badge{position:absolute;top:var(--sp-3);left:var(--sp-3);display:inline-flex;align-items:center;gap:var(--sp-1);padding:var(--sp-1) var(--sp-2);border:1px solid rgba(255,255,255,.24);border-radius:var(--r-pill);background:rgba(21,24,24,.55);font-size:.75rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.hero-card-status{position:absolute;top:var(--sp-3);right:var(--sp-3)}
.hero-card-title{font-size:1.15rem;font-weight:800;line-height:1.15}
/* Mirrors the public hero's .hx-hl; fallback is hero_accent()'s own brand default. */
.hero-card-title .hx-hl{background:linear-gradient(120deg,var(--acc,#174D3D),#fff);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
.hero-card-foot{display:flex;align-items:center;justify-content:space-between;gap:var(--sp-2);padding:var(--sp-3) var(--sp-4)}
.hero-card-meta{font-size:.8rem;color:var(--muted)}
.hs-inline{display:inline}
</style>
<div class="admin-page-head">
    <div><h1><?= lucide('image') ?> Hero Slider</h1><span class="muted"><?= count($rows) ?> slide<?= count($rows) === 1 ? '' : 's' ?> · the rotating banners at the top of your homepage</span></div>
    <div class="flex gap-2"><a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('external-link') ?> View site</a>
        <a class="btn btn-primary" href="<?= e(admin_url('hero-slides?action=create')) ?>">+ New Slide</a></div>
</div>

<?php if ($rows): ?>
<div class="hero-grid">
    <?php foreach ($rows as $i => $r): $s = hero_slide($r); ?>
        <div class="panel hero-card">
            <div class="hero-card-prev" style="<?= hero_bg_style($s) ?>--acc:<?= e(hero_accent($s)) ?>;text-align:<?= e($s['text_align']) ?>">
                <?php if (in_array($s['bg_type'], ['image', 'video'], true)): ?><span class="ov"></span><?php endif; ?>
                <?php if (!empty($s['badge_text'])): ?><span class="hero-card-badge"><?= !empty($s['badge_icon']) ? lucide($s['badge_icon']) : '' ?><?= e($s['badge_text']) ?></span><?php endif; ?>
                <span class="hero-card-status"><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Inactive' ?></span></span>
                <div class="hero-card-title"><?= hero_title_html($s) ?></div>
            </div>
            <div class="hero-card-foot">
                <span class="hero-card-meta">#<?= (int) $r['sort_order'] ?> · <?= e(ucfirst($s['bg_type'])) ?> · <?= e(ucfirst($s['layout'])) ?></span>
                <div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('hero-slides?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('hero-slides')) ?>" data-confirm="Delete this slide permanently?" class="hs-inline"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
    <div class="panel"><div class="panel-body">
        <div class="empty-state"><div class="icon"><?= lucide('image') ?></div>
            <p class="es-title">No hero slides yet</p>
            <p class="es-text">The homepage falls back to its built-in banner until you add one. A slide can be a gradient, a mesh, an image or a video.</p>
            <div class="es-actions"><a class="btn btn-primary btn-sm" href="<?= e(admin_url('hero-slides?action=create')) ?>"><?= lucide('plus') ?> Create your first slide</a></div>
        </div>
    </div></div>
<?php endif; ?>

<?php include __DIR__ . '/partials/foot.php'; ?>
