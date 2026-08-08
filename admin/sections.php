<?php
/**
 * =============================================================================
 *  Admin — Homepage Sections (SPECIAL settings editor).
 *  Toggles the visibility of each homepage section and edits its heading /
 *  subtitle text. Everything is persisted to the `settings` table under the
 *  `homepage` group via set_setting():
 *      show_<section>      → boolean '1'/'0' (section visible?)
 *      <section>_heading   → text  (section title)
 *      <section>_subtitle  → text  (section subtitle)
 *  Single form, CSRF-guarded, set_setting loop, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/*
 * The toggleable homepage sections. Order here is the display order.
 * Each entry supplies a friendly label, an icon and the default heading /
 * subtitle used both as the form placeholder default and the stored fallback.
 */
$sections = [
    'counters'     => ['label' => 'Impact Counters',     'icon' => 'bar-chart-3',    'heading' => 'Our Impact',              'subtitle' => 'Numbers that reflect the change we create together.'],
    'programs'     => ['label' => 'Programs',             'icon' => 'target',         'heading' => 'Our Programs',            'subtitle' => 'Focused initiatives designed to create real, measurable change in the communities we serve.'],
    'events'       => ['label' => 'Events',               'icon' => 'calendar-days',  'heading' => 'Upcoming Events',         'subtitle' => 'Join us at our upcoming events and be a part of the change.'],
    'blogs'        => ['label' => 'Blog & Stories',       'icon' => 'clipboard-pen',  'heading' => 'Latest Stories & News',   'subtitle' => 'Updates, stories and insights straight from the field.'],
    'testimonials' => ['label' => 'Testimonials',         'icon' => 'message-square', 'heading' => 'What People Say',          'subtitle' => 'Voices of the people and partners we work alongside.'],
    'gallery'      => ['label' => 'Gallery',              'icon' => 'image',          'heading' => 'Gallery',                 'subtitle' => 'Moments captured from our work across communities.'],
    'partners'     => ['label' => 'Partners & Sponsors',  'icon' => 'handshake',      'heading' => 'Our Partners & Sponsors',  'subtitle' => 'The organisations that make our mission possible.'],
];

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    foreach ($sections as $key => $meta) {
        // Visibility toggle → strict '1' / '0' boolean setting.
        set_setting('show_' . $key, post('show_' . $key) ? '1' : '0', 'homepage', 'boolean');

        // Heading / subtitle text (plain text — tags stripped, trimmed).
        set_setting($key . '_heading',  clean(post($key . '_heading', '')),  'homepage', 'text');
        set_setting($key . '_subtitle', clean(post($key . '_subtitle', '')), 'homepage', 'textarea');
    }

    log_activity('update', 'sections', 'Updated homepage section settings');
    set_flash('success', 'Homepage sections updated successfully.');
    redirect('/admin/sections');
}

/* -------------------------------------------------------------- LOAD current values */
$vals = [];
foreach ($sections as $key => $meta) {
    $vals[$key] = [
        'show'     => get_setting('show_' . $key, '1'), // sections default to visible
        'heading'  => get_setting($key . '_heading',  $meta['heading']),
        'subtitle' => get_setting($key . '_subtitle', $meta['subtitle']),
    ];
}

/* -------------------------------------------------------------- VIEW */
$page_title = 'Homepage Sections';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1>Homepage Sections</h1>
        <span class="muted">Show, hide and re-title the sections on your public homepage.</span>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('globe') ?> View Homepage</a>
        <button class="btn btn-primary" type="submit" form="sectionsForm">Save Changes</button>
    </div>
</div>

<form id="sectionsForm" class="admin-form" method="post" action="<?= e(admin_url('sections')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <?php foreach ($sections as $key => $meta): $v = $vals[$key]; ?>
    <div class="panel" style="margin-bottom:1.25rem;">
        <div class="panel-head">
            <h3><?= lucide($meta['icon']) ?> <?= e($meta['label']) ?></h3>
            <label class="checkbox">
                <input type="checkbox" name="show_<?= e($key) ?>" value="1" <?= !empty($v['show']) ? 'checked' : '' ?>>
                Visible on homepage
            </label>
        </div>
        <div class="panel-body">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Heading</label>
                    <input class="form-control" type="text" name="<?= e($key) ?>_heading" maxlength="191" value="<?= e($v['heading']) ?>" placeholder="<?= e($meta['heading']) ?>">
                    <span class="form-hint">Leave blank to use the built-in default.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Subtitle</label>
                    <textarea class="form-textarea" name="<?= e($key) ?>_subtitle" maxlength="320" style="min-height:80px;" placeholder="<?= e($meta['subtitle']) ?>"><?= e($v['subtitle']) ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="form-actions">
        <button class="btn btn-primary" type="submit">Save Changes</button>
        <a class="btn btn-ghost" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
    </div>
</form>

<?php include __DIR__ . '/partials/foot.php'; ?>
