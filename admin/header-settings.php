<?php
/**
 * =============================================================================
 *  Admin — Header Designer.
 *  Consolidated configuration for the site header: logo, top utility bar,
 *  header CTA button, and links to the Menu Builder + Announcement Bar.
 *  Values are stored in `settings` (group 'header') and read live by
 *  includes/navbar.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title = 'Header Designer';

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    // Logo upload (optional) — stored path goes into the site_logo setting.
    if (!empty($_FILES['logo']['name'])) {
        $up = upload_image($_FILES['logo'], 'images');
        if ($up['success']) {
            set_setting('site_logo', $up['path'], 'general', 'image');
        } else {
            set_flash('error', $up['error']);
        }
    }
    // Remove logo?
    if (post('remove_logo')) {
        delete_upload(get_setting('site_logo'));
        set_setting('site_logo', '', 'general', 'image');
    }

    set_setting('topbar_enabled',   post('topbar_enabled') ? '1' : '0', 'header', 'boolean');
    set_setting('topbar_show_lang', post('topbar_show_lang') ? '1' : '0', 'header', 'boolean');
    set_setting('header_cta_text',  clean(post('header_cta_text', 'Donate')), 'header', 'text');
    set_setting('header_cta_url',   clean(post('header_cta_url', 'donate')), 'header', 'text');
    set_setting('header_apply_enabled', post('header_apply_enabled') ? '1' : '0', 'header', 'boolean');
    set_setting('header_apply_text', clean(post('header_apply_text', 'Apply Now')), 'header', 'text');
    set_setting('header_apply_url',  clean(post('header_apply_url', 'coordinator-apply')), 'header', 'text');
    set_setting('header_sticky',    post('header_sticky') ? '1' : '0', 'header', 'boolean');

    log_activity('update', 'settings', 'Updated header settings');
    set_flash('success', 'Header settings saved.');
    redirect('/admin/header-settings');
}

/* ----------------------------------------------------------- CURRENT */
$logo         = get_setting('site_logo');
$topbar       = get_setting('topbar_enabled', '1') === '1';
$showLang     = get_setting('topbar_show_lang', '1') === '1';
$ctaText      = get_setting('header_cta_text', 'Donate');
$ctaUrl       = get_setting('header_cta_url', 'donate');
$applyOn      = get_setting('header_apply_enabled', '1') === '1';
$applyText    = get_setting('header_apply_text', 'Apply Now');
$applyUrl     = get_setting('header_apply_url', 'coordinator-apply');
$sticky       = get_setting('header_sticky', '1') === '1';

include __DIR__ . '/partials/head.php';
?>
<form id="headerForm" class="settings-page admin-form" method="post" action="<?= e(admin_url('header-settings')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <header class="settings-topbar">
        <div class="settings-head-txt">
            <nav class="settings-crumb" aria-label="Breadcrumb">
                <a href="<?= e(admin_url('dashboard')) ?>"><?= lucide('home') ?> Admin</a>
                <?= lucide('chevron-right') ?><span>Header Designer</span>
            </nav>
            <h1>Header Designer</h1>
            <p>Logo, the primary call-to-action button, top utility bar and navigation behaviour.</p>
        </div>
        <div class="settings-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View Site</a>
            <button class="btn btn-primary btn-sm" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </header>

    <section class="settings-card">
        <header class="settings-card-head">
            <span class="sch-ico"><?= lucide('panel-top') ?></span>
            <div><h2>Header &amp; Navigation</h2><p>Branding, CTA button and top bar</p></div>
        </header>
        <div class="settings-card-body">
            <h3 class="settings-sub"><?= lucide('image') ?> Branding</h3>
            <div class="settings-field span-2">
                <label class="settings-label">Site Logo</label>
                <div class="settings-upload">
                    <img id="logoPreview" class="su-preview" src="<?= e(!empty($logo) ? upload_url($logo) : asset('images/logo-128.webp')) ?>" alt="Site logo preview">
                    <div class="su-body">
                        <input class="su-file" type="file" id="logoFile" name="logo" accept="image/*" data-preview="#logoPreview">
                        <label class="btn btn-outline btn-sm" for="logoFile"><?= lucide('upload') ?> Choose logo</label>
                        <?php if (!empty($logo)): ?>
                            <label class="checkbox" style="font-size:.8rem;margin-top:.2rem;"><input type="checkbox" name="remove_logo" value="1"> Remove current logo (revert to default)</label>
                        <?php endif; ?>
                        <small class="su-note">PNG / SVG / WebP. Shown in the navbar &amp; footer. Leave blank to keep the current logo.</small>
                    </div>
                </div>
            </div>

            <h3 class="settings-sub"><?= lucide('mouse-pointer-click') ?> Call-to-action button</h3>
            <div class="settings-grid">
                <div class="settings-field">
                    <div class="ff">
                        <input class="ff-input" id="f_cta_text" name="header_cta_text" value="<?= e($ctaText) ?>" maxlength="32" placeholder=" ">
                        <label class="ff-label" for="f_cta_text">Button Label</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> e.g. <code>Donate</code>.</small>
                </div>
                <div class="settings-field">
                    <div class="ff">
                        <input class="ff-input" id="f_cta_url" name="header_cta_url" value="<?= e($ctaUrl) ?>" maxlength="191" placeholder=" ">
                        <label class="ff-label" for="f_cta_url">Button Link</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> A page slug like <code>donate</code> or a full URL. Colours come from <a href="<?= e(admin_url('theme')) ?>">Theme Settings</a>.</small>
                </div>
            </div>

            <h3 class="settings-sub"><?= lucide('clipboard-pen') ?> Secondary button (recruitment)</h3>
            <label class="switch">
                <input type="checkbox" name="header_apply_enabled" value="1" <?= $applyOn ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Show the second button</strong><small>An outline button beside the main call to action, for applications and recruitment.</small></span>
            </label>
            <div class="settings-grid">
                <div class="settings-field">
                    <div class="ff">
                        <input class="ff-input" id="f_apply_text" name="header_apply_text" value="<?= e($applyText) ?>" maxlength="32" placeholder=" ">
                        <label class="ff-label" for="f_apply_text">Button Label</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> e.g. <code>Apply Now</code>.</small>
                </div>
                <div class="settings-field">
                    <div class="ff">
                        <input class="ff-input" id="f_apply_url" name="header_apply_url" value="<?= e($applyUrl) ?>" maxlength="191" placeholder=" ">
                        <label class="ff-label" for="f_apply_url">Button Link</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> Defaults to <code>coordinator-apply</code>, the Community Coordinator application form.</small>
                </div>
            </div>

            <h3 class="settings-sub"><?= lucide('settings-2') ?> Options</h3>
            <label class="switch">
                <input type="checkbox" name="topbar_enabled" value="1" <?= $topbar ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Top utility bar</strong><small>Email, phone and social icons above the navbar.</small></span>
            </label>
            <label class="switch">
                <input type="checkbox" name="topbar_show_lang" value="1" <?= $showLang ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Language switcher</strong><small>Shown in the top utility bar.</small></span>
            </label>
            <label class="switch">
                <input type="checkbox" name="header_sticky" value="1" <?= $sticky ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Sticky navbar</strong><small>Stays visible as visitors scroll the page.</small></span>
            </label>

            <div class="alert alert-info" style="margin-top:1.3rem;font-size:.84rem;">
                <?= lucide('info') ?> Related managers:
                <a href="<?= e(admin_url('menus')) ?>">Menu Builder</a> (nav links) ·
                <a href="<?= e(admin_url('announcements')) ?>">Announcement Bar</a> ·
                <a href="<?= e(admin_url('social-links')) ?>">Social Links</a> ·
                <a href="<?= e(admin_url('theme')) ?>">Theme &amp; Colours</a>.
            </div>
        </div>
    </section>

    <div class="settings-footbar">
        <span class="sf-note"><?= lucide('shield-check') ?> Header changes apply site-wide immediately after saving.</span>
        <div class="sf-actions">
            <a class="btn btn-ghost" href="<?= e(admin_url('header-settings')) ?>"><?= lucide('rotate-ccw') ?> Reset</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/foot.php'; ?>
