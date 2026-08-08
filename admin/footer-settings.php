<?php
/**
 * =============================================================================
 *  Admin — Footer Builder.
 *  Configuration for the site footer: about text, copyright line, newsletter
 *  toggle, and links to the Widget Manager / Social Links / Menu Builder that
 *  supply the footer columns. Values are stored in `settings` (group 'footer')
 *  and read live by includes/footer.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$page_title = 'Footer Builder';

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    set_setting('footer_about',      clean(post('footer_about', '')), 'footer', 'textarea');
    set_setting('footer_copyright',  clean(post('footer_copyright', '')), 'footer', 'text');
    set_setting('footer_show_newsletter', post('footer_show_newsletter') ? '1' : '0', 'footer', 'boolean');
    set_setting('footer_show_trust', post('footer_show_trust') ? '1' : '0', 'footer', 'boolean');

    log_activity('update', 'settings', 'Updated footer settings');
    set_flash('success', 'Footer settings saved.');
    redirect('/admin/footer-settings');
}

/* ----------------------------------------------------------- CURRENT */
$about       = get_setting('footer_about', 'EDUSKILL INDIA FOUNDATION works across Bihar to empower communities through education, healthcare, skill development and relief — spreading hope and creating lasting change.');
$copyright   = get_setting('footer_copyright', '');
$showNews    = get_setting('footer_show_newsletter', '1') === '1';
$showTrust   = get_setting('footer_show_trust', '1') === '1';

include __DIR__ . '/partials/head.php';
?>
<form id="footerForm" class="settings-page admin-form" method="post" action="<?= e(admin_url('footer-settings')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="_do" value="save">

    <header class="settings-topbar">
        <div class="settings-head-txt">
            <nav class="settings-crumb" aria-label="Breadcrumb">
                <a href="<?= e(admin_url('dashboard')) ?>"><?= lucide('home') ?> Admin</a>
                <?= lucide('chevron-right') ?><span>Footer Builder</span>
            </nav>
            <h1>Footer Builder</h1>
            <p>About text, copyright line and which footer sections appear on the public site.</p>
        </div>
        <div class="settings-actions">
            <a class="btn btn-outline btn-sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><?= lucide('external-link') ?> View Site</a>
            <button class="btn btn-primary btn-sm" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </header>

    <section class="settings-card">
        <header class="settings-card-head">
            <span class="sch-ico"><?= lucide('panel-bottom') ?></span>
            <div><h2>Footer Content</h2><p>About, copyright &amp; visible sections</p></div>
        </header>
        <div class="settings-card-body">
            <div class="settings-grid">
                <div class="settings-field span-2">
                    <div class="ff">
                        <textarea class="ff-input" id="f_footer_about" name="footer_about" rows="4" maxlength="600" placeholder=" "><?= e($about) ?></textarea>
                        <label class="ff-label" for="f_footer_about">Footer About Text</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> Short description shown in the first footer column.</small>
                </div>
                <div class="settings-field span-2">
                    <div class="ff">
                        <input class="ff-input" id="f_footer_copyright" name="footer_copyright" value="<?= e($copyright) ?>" placeholder=" ">
                        <label class="ff-label" for="f_footer_copyright">Copyright Line</label>
                    </div>
                    <small class="settings-hint"><?= lucide('info') ?> Use <code>{year}</code> and <code>{site}</code> tokens, e.g. <code>© {year} {site}. All rights reserved.</code> — leave blank for the default.</small>
                </div>
            </div>

            <h3 class="settings-sub"><?= lucide('layout-grid') ?> Sections</h3>
            <label class="switch">
                <input type="checkbox" name="footer_show_newsletter" value="1" <?= $showNews ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Newsletter signup block</strong><small>A subscribe form in the footer.</small></span>
            </label>
            <label class="switch">
                <input type="checkbox" name="footer_show_trust" value="1" <?= $showTrust ? 'checked' : '' ?>>
                <span class="switch-track"><span class="switch-thumb"></span></span>
                <span class="switch-text"><strong>Trust strip</strong><small>Registered NGO · 80G · Secure · CIN.</small></span>
            </label>

            <div class="alert alert-info" style="margin-top:1.3rem;font-size:.84rem;">
                <?= lucide('info') ?> Footer columns &amp; content are managed in
                <a href="<?= e(admin_url('menus')) ?>">Menu Builder</a> (link columns) ·
                <a href="<?= e(admin_url('widgets')) ?>">Widget Manager</a> (footer widgets) ·
                <a href="<?= e(admin_url('social-links')) ?>">Social Links</a>.
            </div>
        </div>
    </section>

    <div class="settings-footbar">
        <span class="sf-note"><?= lucide('shield-check') ?> Changes appear in the site footer immediately after saving.</span>
        <div class="sf-actions">
            <a class="btn btn-ghost" href="<?= e(admin_url('footer-settings')) ?>"><?= lucide('rotate-ccw') ?> Reset</a>
            <a class="btn btn-secondary" href="<?= e(admin_url('dashboard')) ?>">Cancel</a>
            <button class="btn btn-primary" type="submit" data-save><?= lucide('save') ?> Save Changes</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/foot.php'; ?>
