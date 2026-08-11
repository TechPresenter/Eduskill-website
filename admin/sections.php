<?php
/**
 * =============================================================================
 *  Admin — Homepage Sections (SPECIAL settings editor).
 * =============================================================================
 *  Shows, hides and re-titles the sections on the public homepage. Everything is
 *  persisted to the `settings` table under the `homepage` group via
 *  set_setting():
 *      show_<section>      → boolean '1'/'0' (section visible?)
 *      <section>_heading   → text     (section title)
 *      <section>_subtitle  → textarea (section subtitle)
 *
 *  The list of sections is NOT written here: it comes from home_sections() in
 *  includes/homepage.php, the same registry index.php reads. This page used to
 *  keep its own list of seven sections and index.php ignored every value it
 *  saved — the headings were edited and nothing changed on the page, and seven
 *  newer sections had no entry at all. One registry, both sides, no drift.
 *
 *  A section only offers a heading / subtitle box where the page has somewhere
 *  to put it. Sections whose words come from a record (the hero, the featured
 *  story, the video band) say so and link to the screen that owns them.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/homepage.php';
require_admin();

$sections = home_sections();

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    foreach ($sections as $key => $meta) {
        // Visibility toggle → strict '1' / '0'. Sections that cannot be hidden
        // (the hero) post no checkbox and must not be written to '0'.
        if (($meta['toggle'] ?? true) !== false) {
            set_setting('show_' . $key, post('show_' . $key) ? '1' : '0', 'homepage', 'boolean');
        }
        // Heading / subtitle only where the section declares one. Blank is kept
        // blank on purpose: get_setting() treats '' as absent, so clearing a box
        // restores the built-in default rather than emptying the page.
        // home_text() rather than clean(): clean() is trim(strip_tags(...)), and
        // strip_tags() throws away everything after an unclosed `<`, so a heading
        // of "Under <18 outreach" was silently stored as "Under". It also guards
        // the non-scalar case that a crafted POST (`counters_heading[]=x`) puts
        // through here, which clean() turned into the literal "Array" plus a
        // warning. See includes/homepage.php §0.
        if (array_key_exists('heading', $meta)) {
            set_setting($key . '_heading', home_text(post($key . '_heading', '')), 'homepage', 'text');
        }
        if (array_key_exists('subtitle', $meta)) {
            set_setting($key . '_subtitle', home_text(post($key . '_subtitle', '')), 'homepage', 'textarea');
        }
    }

    log_activity('update', 'sections', 'Updated homepage section settings');
    set_flash('success', 'Homepage sections updated successfully.');
    redirect('/admin/sections');
}

/* -------------------------------------------------------------- LOAD current values */
$vals = [];
foreach ($sections as $key => $meta) {
    $vals[$key] = [
        'show'     => home_section_visible($key),
        'heading'  => (string) get_setting($key . '_heading', ''),
        'subtitle' => (string) get_setting($key . '_subtitle', ''),
    ];
}
$hiddenCount = count(array_filter($vals, static fn(array $v): bool => !$v['show']));

/* -------------------------------------------------------------- VIEW */
$page_title = 'Homepage Sections';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Homepage Sections</h1>
        <span class="muted">
            The <?= count($sections) ?> sections your homepage renders, in page order.
            Show, hide and re-title them here.
            <?php if ($hiddenCount): ?>
                <strong><?= $hiddenCount ?></strong> <?= $hiddenCount === 1 ? 'is' : 'are' ?> currently hidden.
            <?php endif; ?>
        </span>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= lucide('globe') ?> View Homepage</a>
        <button class="btn btn-primary" type="submit" form="sectionsForm">Save Changes</button>
    </div>
</div>

<form id="sectionsForm" class="admin-form hs-form" method="post" action="<?= e(admin_url('sections')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <p class="hs-lede">
        <?= lucide('info') ?>
        Leave a heading or subtitle empty to fall back to the wording the page ships with —
        the placeholder in each box shows you what that is. Hiding a section removes it from
        the public homepage only; its content is left untouched.
    </p>

    <?php $n = 0; foreach ($sections as $key => $meta):
        $v          = $vals[$key];
        $canToggle  = ($meta['toggle'] ?? true) !== false;
        $hasHeading = array_key_exists('heading', $meta);
        $hasSub     = array_key_exists('subtitle', $meta);
        $n++;
    ?>
    <div class="panel hs-panel<?= $canToggle && !$v['show'] ? ' is-off' : '' ?>">
        <div class="panel-head hs-head">
            <h3>
                <span class="hs-num" aria-hidden="true"><?= $n ?></span>
                <?= lucide($meta['icon'] ?? 'square') ?>
                <?= e($meta['label']) ?>
            </h3>
            <?php if ($canToggle): ?>
                <label class="checkbox hs-toggle">
                    <input type="checkbox" name="show_<?= e($key) ?>" value="1" <?= $v['show'] ? 'checked' : '' ?>>
                    Visible on homepage
                </label>
            <?php else: ?>
                <span class="hs-always"><?= lucide('lock') ?> Always shown</span>
            <?php endif; ?>
        </div>
        <div class="panel-body">
            <?php if (!empty($meta['desc'])): ?>
                <p class="hs-desc"><?= e($meta['desc']) ?></p>
            <?php endif; ?>

            <?php if ($hasHeading || $hasSub): ?>
                <div class="grid-2">
                    <?php if ($hasHeading): ?>
                    <div class="form-group">
                        <label class="form-label" for="f_<?= e($key) ?>_heading">Heading</label>
                        <input class="form-control" type="text" id="f_<?= e($key) ?>_heading"
                               name="<?= e($key) ?>_heading" maxlength="191"
                               value="<?= e($v['heading']) ?>"
                               placeholder="<?= e((string) $meta['heading']) ?>">
                        <span class="form-hint">Leave blank for the built-in default.</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($hasSub): ?>
                    <div class="form-group">
                        <label class="form-label" for="f_<?= e($key) ?>_subtitle">Subtitle</label>
                        <textarea class="form-textarea" id="f_<?= e($key) ?>_subtitle"
                                  name="<?= e($key) ?>_subtitle" maxlength="320" style="min-height:80px;"
                                  placeholder="<?= e((string) $meta['subtitle']) ?>"><?= e($v['subtitle']) ?></textarea>
                        <span class="form-hint">Leave blank for the built-in default.</span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="hs-note"><?= lucide('type') ?> This section has no heading of its own to edit — its words come from the content it shows.</p>
            <?php endif; ?>

            <?php if (!empty($meta['hint'])): ?>
                <p class="hs-note"><?= lucide('lightbulb') ?> <?= e($meta['hint']) ?></p>
            <?php endif; ?>

            <?php if (!empty($meta['links'])): ?>
                <div class="hs-links">
                    <?php foreach ($meta['links'] as $route => $label): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(admin_url((string) $route)) ?>"><?= lucide('arrow-up-right') ?> <?= e((string) $label) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a class="btn btn-ghost" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
    </div>
</form>

<style>
/* ===== Homepage Sections (scoped .hs-*) ===================================
   Colour pairs introduced:
     .hs-num     #0B4E3D on a 10% primary tint over white (≈#E9EDEC) → 8.2:1
     .hs-desc    var(--muted) on the card surface                    → 4.5:1+
     .hs-always  #0B4E3D on #FFE987                                  → 7.95:1
   ======================================================================== */
.hs-lede {
    display: flex; align-items: flex-start; gap: .55rem;
    margin: 0 0 1.25rem; padding: .85rem 1rem;
    font-size: .88rem; line-height: 1.6; color: var(--muted, #4B6754);
    background: var(--surface-2, #F8FCF8);
    border: 1px solid var(--border, #C1CCB3); border-radius: 12px;
}
.hs-lede svg, .hs-note svg { width: 15px; height: 15px; flex: 0 0 auto; margin-top: .15rem; color: var(--primary, #0B4E3D); }

.hs-panel { margin-bottom: 1.25rem; transition: opacity .3s cubic-bezier(.22,1,.36,1); }
/* A hidden section stays legible — it is dimmed, not greyed out of readability. */
.hs-panel.is-off { opacity: .72; }
.hs-panel.is-off .hs-head { background: var(--surface-2, #F8FCF8); }

.hs-head h3 { display: flex; align-items: center; gap: .55rem; margin: 0; }
.hs-num {
    display: grid; place-items: center; width: 26px; height: 26px; flex: 0 0 auto;
    border-radius: 999px; font-size: .78rem; font-weight: 800;
    background: color-mix(in srgb, var(--primary, #0B4E3D) 10%, transparent);
    color: var(--primary, #0B4E3D);
}
.hs-always {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .25rem .7rem; border-radius: 999px;
    background: var(--yellow, #FFE987); color: var(--primary, #0B4E3D);
    font-size: .76rem; font-weight: 700;
}
.hs-always svg { width: 14px; height: 14px; }

.hs-desc { margin: 0 0 1rem; font-size: .9rem; line-height: 1.65; color: var(--muted, #4B6754); max-width: 70ch; }
.hs-note {
    display: flex; align-items: flex-start; gap: .45rem;
    margin: .85rem 0 0; font-size: .84rem; line-height: 1.6; color: var(--muted, #4B6754); max-width: 70ch;
}
.hs-links { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }

@media (prefers-reduced-motion: reduce) { .hs-panel { transition: none; } }
</style>

<script>
/* Dim a panel the moment its toggle changes, so the state of the page is
   readable before saving. Progressive: without JS the checkbox still works. */
(function () {
    var form = document.getElementById('sectionsForm');
    if (!form) { return; }
    form.addEventListener('change', function (e) {
        var box = e.target.closest('.hs-toggle input[type="checkbox"]');
        if (!box) { return; }
        var panel = box.closest('.hs-panel');
        if (panel) { panel.classList.toggle('is-off', !box.checked); }
    });
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
