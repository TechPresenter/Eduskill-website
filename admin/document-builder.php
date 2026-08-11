<?php
/**
 * =============================================================================
 *  Admin — Document Template Builder
 * =============================================================================
 *  A single console for the whole Document Hub identity & content:
 *   1. Signature & Seal   — signatory identity, signature/logo/seal images
 *   2. Document Bodies    — edit any document type's body (CKEditor + placeholders)
 *   3. Placeholder Ref    — the {{placeholder}} catalogue + a live preview
 *   4. Document Access    — enable/disable each document for admins and the public
 *  Backed by the existing Document Hub engine (includes/document_hub.php).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/document_hub.php';

dh_seed_defaults();
$page_title = 'Document Builder';

/* ------------------------------------------------------------------- Actions */
if (is_post()) {
    require_csrf();
    $do = post('_do');

    /* --- Signature, seal, logo & signatory identity --- */
    if ($do === 'save-identity') {
        set_setting('doc_signatory_name', clean(post('doc_signatory_name', '')), 'general', 'text');
        set_setting('doc_signatory_designation', clean(post('doc_signatory_designation', '')), 'general', 'text');
        foreach (['signature' => 'org_signature', 'logo' => 'site_logo', 'seal' => 'org_seal'] as $field => $key) {
            if (!empty($_FILES[$field]['name'])) {
                $up = upload_image($_FILES[$field], 'branding');
                if ($up['success']) {
                    $old = get_setting($key);
                    set_setting($key, $up['path'], 'general', 'image');
                    if ($old && $old !== $up['path']) { delete_upload($old); }
                } else {
                    set_flash('error', ucfirst($field) . ': ' . $up['error']);
                    redirect('/admin/document-builder#identity');
                }
            }
        }
        log_activity('update', 'document-hub', 'Updated document identity (signature/seal)');
        set_flash('success', 'Identity settings saved.');
        redirect('/admin/document-builder#identity');
    }

    /* --- Edit a document body --- */
    if ($do === 'save-body') {
        $tpl = find('document_templates', (int) post('template_id', 0));
        if ($tpl) {
            db_update('document_templates', ['body' => post('body', '')], 'id = :id', [':id' => (int) $tpl['id']]);
            db_insert('document_template_versions', ['template_id' => (int) $tpl['id'], 'body' => post('body', ''), 'terms' => $tpl['terms'], 'saved_by' => current_user_id()]);
            log_activity('update', 'document-hub', 'Edited body of ' . $tpl['name']);
            set_flash('success', 'Document body saved.');
        }
        redirect('/admin/document-builder?tpl=' . (int) post('template_id', 0) . '#bodies');
    }

    /* --- Per-document access toggles --- */
    if ($do === 'save-access') {
        $admin = (array) post('admin_enabled', []);
        $user  = (array) post('user_enabled', []);
        foreach (db_all('SELECT id FROM document_templates') as $r) {
            $id = (int) $r['id'];
            db_update('document_templates', [
                'admin_enabled' => isset($admin[$id]) ? 1 : 0,
                'user_enabled'  => isset($user[$id]) ? 1 : 0,
            ], 'id = :id', [':id' => $id]);
        }
        log_activity('update', 'document-hub', 'Updated document access settings');
        set_flash('success', 'Access settings saved.');
        redirect('/admin/document-builder#access');
    }
}

/* --------------------------------------------------------------- View data   */
$sig       = dh_signatory();
$assets    = dh_org_assets();
$allTpls   = db_all('SELECT * FROM document_templates ORDER BY category, name');
$adminTpls = array_values(array_filter($allTpls, 'dh_admin_enabled'));
$selId     = (int) get('tpl', $adminTpls[0]['id'] ?? 0);
$selTpl    = $selId ? find('document_templates', $selId) : ($adminTpls[0] ?? null);

$phDesc = [
    'organization_name' => 'Your organisation name', 'logo' => 'Organisation logo image', 'seal' => 'Official seal / stamp',
    'signature' => 'Authorised signature image', 'signatory_name' => 'Authorised signatory name', 'signatory_designation' => 'Signatory designation / subtitle',
    'member_name' => 'Recipient / member full name', 'student_name' => 'Student full name', 'photo' => 'Recipient photo',
    'designation' => 'Recipient role / designation', 'blood_group' => 'Blood group (ID cards)', 'emergency_contact' => 'Emergency contact (ID cards)',
    'certificate_no' => 'Auto-generated document / serial number', 'membership_no' => 'Membership number', 'id_no' => 'ID number',
    'roll_no' => 'Roll number', 'registration_no' => 'Registration number', 'course_name' => 'Course / training name',
    'program_name' => 'Programme name', 'event_name' => 'Event name', 'volunteer_hours' => 'Volunteer hours',
    'marks' => 'Marks obtained', 'grade' => 'Grade', 'percentage' => 'Percentage', 'date' => 'Current date',
    'issue_date' => 'Document issue date', 'expiry_date' => 'Valid-until / expiry date', 'qr_code' => 'Verification QR code', 'barcode' => 'Decorative barcode',
];

include __DIR__ . '/partials/head.php';
?>
<style>
/* Screen-local layout ONLY. The tab strip is the shared `.tabs`/`.tab`
   component from admin-pro.css — this file used to carry a byte-identical
   `.db-tabs`/`.db-tab` copy of it (and of security.php's `.sc-tab`). */
.db-wrap{max-width:1180px}
.db-panel{display:none}
.db-panel.is-active{display:block}
.db-cols{gap:var(--sp-6);align-items:start}
.db-ph-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:var(--sp-3)}
.db-ph{display:flex;align-items:flex-start;gap:var(--sp-3);padding:var(--sp-3);border:1px solid var(--border);border-radius:var(--r-md);background:var(--surface)}
.db-ph code{padding:var(--sp-1) var(--sp-2);border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface-2);font-size:.82rem;font-weight:700;color:var(--brand-600);white-space:nowrap;cursor:pointer}
.db-ph b{font-size:.9rem}
.db-ph small{display:block;font-size:.78rem;color:var(--muted)}
.db-group{margin:var(--sp-4) 0 var(--sp-2);font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.db-thumb{width:84px;height:84px;padding:var(--sp-1);border:1px solid var(--border);border-radius:var(--r-md);background:var(--surface-2);object-fit:contain}
/* Tinted from --st-warn, the design system's one warning hue. This box used to
   mix a brand-orange tint with an off-palette dark-amber body text. */
.db-tips{margin-top:var(--sp-5);padding:var(--sp-4);border:1px solid color-mix(in srgb,var(--st-warn) 35%,transparent);border-radius:var(--r-md);background:color-mix(in srgb,var(--st-warn) 6%,transparent)}
.db-tips-title{color:var(--st-warn)}
.db-tips ul{margin:var(--sp-2) 0 0;padding-left:var(--sp-5)}
.db-tips li{margin:var(--sp-1) 0;font-size:.86rem;color:var(--text-soft)}
/* Strips .switch's card chrome so the toggle can sit bare in a table cell. */
.db-toggle{display:inline-flex;gap:0;padding:0;border:0;background:transparent}
.db-picker{display:flex;flex-wrap:wrap;align-items:center;gap:var(--sp-3);margin-bottom:var(--sp-4)}
.db-picker .form-label{margin:0}
.db-picker .form-select{max-width:340px}
.db-lede{margin:0 0 var(--sp-4)}
.db-2col{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:var(--sp-4)}
.db-inline-form{display:flex;flex-wrap:wrap;align-items:flex-end;gap:var(--sp-3)}
.db-inline-form .form-group{flex:1;margin:0}
.db-body-area{min-height:340px}
.db-upload{margin-top:var(--sp-2)}
.db-ta-c{text-align:center}
</style>

<div class="admin-content db-wrap">
<div class="admin-page-head">
    <div><h1><?= lucide('file-cog') ?> Document Template Builder</h1><span class="muted">Customise signatures, seals, bodies and access for all documents</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('document-hub')) ?>"><?= lucide('layout-grid') ?> Document Hub</a>
</div>

<div class="tabs" role="tablist">
    <button type="button" class="tab is-active" data-tab="identity"><?= lucide('pen-tool') ?> Signature &amp; Seal</button>
    <button type="button" class="tab" data-tab="bodies"><?= lucide('file-text') ?> Document Bodies</button>
    <button type="button" class="tab" data-tab="placeholders"><?= lucide('code') ?> Placeholder Reference</button>
    <button type="button" class="tab" data-tab="access"><?= lucide('toggle-right') ?> Document Access</button>
</div>

<!-- ============ SIGNATURE & SEAL ============ -->
<section class="db-panel is-active" id="db-identity">
    <form method="post" action="<?= e(admin_url('document-builder')) ?>" enctype="multipart/form-data" class="admin-form">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save-identity">
        <div class="grid-2 db-cols">
            <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('pen-tool') ?> Signatory Information</h2></div><div class="panel-body">
                <div class="form-group"><label class="form-label">Authorised signatory name</label>
                    <input class="form-control" name="doc_signatory_name" value="<?= e($sig['name']) ?>" placeholder="Director / Secretary">
                    <span class="form-hint">Available as <code>{{signatory_name}}</code> in document bodies.</span></div>
                <div class="form-group"><label class="form-label">Signatory designation</label>
                    <input class="form-control" name="doc_signatory_designation" value="<?= e($sig['designation']) ?>" placeholder="<?= e(get_setting('site_name', SITE_NAME)) ?>">
                    <span class="form-hint">Subtitle under the name — <code>{{signatory_designation}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('signature') ?> Signature Image</h2></div><div class="panel-body">
                <?php if ($assets['signature']): ?><img class="db-thumb" src="<?= e($assets['signature']) ?>" alt="signature"><?php endif; ?>
                <div class="form-group db-upload"><label class="form-label">Upload signature</label>
                    <input class="form-control" type="file" name="signature" accept="image/png,image/jpeg,image/webp">
                    <span class="form-hint">PNG with transparent background recommended. Renders where a body uses <code>{{signature}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('image') ?> Organisation Logo</h2></div><div class="panel-body">
                <?php if ($assets['logo']): ?><img class="db-thumb" src="<?= e($assets['logo']) ?>" alt="logo"><?php endif; ?>
                <div class="form-group db-upload"><label class="form-label">Upload logo</label>
                    <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                    <span class="form-hint">Appears where a body uses <code>{{logo}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('stamp') ?> Official Seal / Stamp</h2></div><div class="panel-body">
                <?php if ($assets['seal']): ?><img class="db-thumb" src="<?= e($assets['seal']) ?>" alt="seal"><?php endif; ?>
                <div class="form-group db-upload"><label class="form-label">Upload seal</label>
                    <input class="form-control" type="file" name="seal" accept="image/png,image/jpeg,image/webp">
                    <span class="form-hint">Circular PNG recommended. Renders where a body uses <code>{{seal}}</code>. Without one, a generated seal is shown.</span></div>
            </div></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Identity Settings</button></div>
    </form>
</section>

<!-- ============ DOCUMENT BODIES ============ -->
<section class="db-panel" id="db-bodies">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('file-text') ?> Edit Document Body</h2></div><div class="panel-body">
        <?php if ($adminTpls): ?>
        <form method="get" action="<?= e(admin_url('document-builder')) ?>" class="db-picker">
            <label class="form-label">Document</label>
            <select class="form-select" name="tpl" onchange="this.form.submit()">
                <?php foreach ($adminTpls as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $selTpl && (int) $selTpl['id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= e(dh_categories()[$t['category']] ?? $t['category']) ?></option><?php endforeach; ?>
            </select>
            <input type="hidden" name="_tab" value="bodies">
            <?php if ($selTpl): ?><a class="btn btn-outline btn-sm" href="<?= e(admin_url('document-hub?action=preview&id=' . $selTpl['id'])) ?>" target="_blank"><?= lucide('eye') ?> Preview</a><?php endif; ?>
        </form>
        <?php if ($selTpl): ?>
        <form method="post" action="<?= e(admin_url('document-builder')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-body"><input type="hidden" name="template_id" value="<?= (int) $selTpl['id'] ?>">
            <textarea class="form-textarea db-body-area" name="body" data-wysiwyg><?= e($selTpl['body']) ?></textarea>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Body</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('document-hub?action=edit&id=' . $selTpl['id'])) ?>">Advanced settings →</a></div>
        </form>
        <?php endif; ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('file-text') ?></div>
                <p class="es-title">No documents to edit yet</p>
                <p class="es-text">Create a template in the Document Hub — or install the standard library — and its body becomes editable here.</p>
                <div class="es-actions">
                    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('document-hub?action=create')) ?>"><?= lucide('plus') ?> New template</a>
                    <a class="btn btn-secondary btn-sm" href="<?= e(admin_url('document-hub')) ?>"><?= lucide('layout-grid') ?> Document Hub</a>
                </div>
            </div>
        <?php endif; ?>
    </div></div>
</section>

<!-- ============ PLACEHOLDER REFERENCE ============ -->
<section class="db-panel" id="db-placeholders">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('code') ?> Available Template Placeholders</h2></div><div class="panel-body">
        <p class="text-muted db-lede">Use these in any document body. They are replaced with real data when a document is generated. Click a placeholder to copy it.</p>
        <?php foreach (dh_placeholders() as $group => $items): ?>
            <div class="db-group"><?= e($group) ?></div>
            <div class="db-ph-grid">
                <?php foreach ($items as $k => $label): ?>
                    <div class="db-ph"><code data-copy="{{<?= e($k) ?>}}" title="Copy">{{<?= e($k) ?>}}</code><div><b><?= e($label) ?></b><small><?= e($phDesc[$k] ?? 'Placeholder') ?></small></div></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="db-tips"><strong class="db-tips-title"><?= lucide('lightbulb') ?> Tips</strong>
            <ul>
                <li>Placeholders are case-insensitive; unrecognised ones are removed from the output.</li>
                <li>Use plain text, line breaks and HTML in bodies (authored via the rich editor).</li>
                <li><code>{{signature}}</code>, <code>{{logo}}</code>, <code>{{seal}}</code> and <code>{{qr_code}}</code> render from the Signature &amp; Seal tab and settings.</li>
                <li>Preview any document after saving to test your changes.</li>
            </ul>
        </div>
    </div></div>

    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('eye') ?> Quick Document Preview</h2></div><div class="panel-body">
        <p class="text-muted db-lede">Open any document with sample data to see your template changes, or look up an issued document number.</p>
        <div class="db-2col">
            <form action="<?= e(admin_url('document-hub')) ?>" method="get" target="_blank" class="db-inline-form">
                <input type="hidden" name="action" value="preview">
                <div class="form-group"><label class="form-label">Preview a template (sample data)</label>
                    <select class="form-select" name="id"><?php foreach ($adminTpls as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-primary" type="submit"><?= lucide('eye') ?> Preview</button>
            </form>
            <form action="<?= e(url('verify-document')) ?>" method="get" target="_blank" class="db-inline-form">
                <div class="form-group"><label class="form-label">Look up an issued document</label>
                    <input class="form-control" name="no" placeholder="e.g. MEMC-2026-0001"></div>
                <button class="btn btn-secondary" type="submit"><?= lucide('search') ?> Verify</button>
            </form>
        </div>
    </div></div>
</section>

<!-- ============ DOCUMENT ACCESS ============ -->
<section class="db-panel" id="db-access">
    <div class="panel"><div class="panel-head"><h2 class="panel-title"><?= lucide('toggle-right') ?> Document Enable / Disable</h2></div><div class="panel-body">
        <?php if ($allTpls): ?>
        <div class="alert alert-info db-lede">
            <strong>Admin Access:</strong> if off, the document is hidden from the Document Hub and cannot be generated.<br>
            <strong>Public Access:</strong> if off, only signed-in admins can open/download it — recipients with the link cannot.
        </div>
        <form method="post" action="<?= e(admin_url('document-builder')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-access">
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Document Type</th><th class="db-ta-c">Admin Access</th><th class="db-ta-c">Public Access</th></tr></thead><tbody>
                <?php foreach ($allTpls as $t): ?>
                    <tr>
                        <td><strong><?= e($t['name']) ?></strong><br><small class="text-muted"><?= e(dh_categories()[$t['category']] ?? $t['category']) ?></small></td>
                        <td class="db-ta-c"><label class="switch db-toggle"><input type="checkbox" name="admin_enabled[<?= (int) $t['id'] ?>]" value="1" <?= dh_admin_enabled($t) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></label></td>
                        <td class="db-ta-c"><label class="switch db-toggle"><input type="checkbox" name="user_enabled[<?= (int) $t['id'] ?>]" value="1" <?= dh_user_enabled($t) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></label></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Access Settings</button></div>
        </form>
        <?php else: ?>
            <?php /* A fresh install has no templates: this used to render a headed table
                     with an empty body and a live Save button that saved nothing. */ ?>
            <div class="empty-state"><div class="icon"><?= lucide('toggle-right') ?></div>
                <p class="es-title">No documents to configure</p>
                <p class="es-text">Access toggles appear once templates exist. Install the standard library or create a template in the Document Hub.</p>
                <div class="es-actions">
                    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('document-hub')) ?>"><?= lucide('package-plus') ?> Install standard templates</a>
                    <a class="btn btn-secondary btn-sm" href="<?= e(admin_url('document-hub?action=create')) ?>"><?= lucide('plus') ?> New template</a>
                </div>
            </div>
        <?php endif; ?>
    </div></div>
</section>
</div>

<script>
(function () {
    // Shared .tabs component (admin-pro.css) — the local .db-tab copy is gone.
    var tabs = document.querySelectorAll('.db-wrap .tabs .tab'), panels = document.querySelectorAll('.db-panel');
    function show(name) {
        var ok = false;
        tabs.forEach(function (t) { var a = t.dataset.tab === name; t.classList.toggle('is-active', a); ok = ok || a; });
        if (!ok) return;
        panels.forEach(function (p) { p.classList.toggle('is-active', p.id === 'db-' + name); });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); history.replaceState(null, '', '#' + t.dataset.tab); }); });
    document.querySelectorAll('.db-ph code[data-copy]').forEach(function (c) {
        c.addEventListener('click', function () { if (navigator.clipboard) { navigator.clipboard.writeText(c.dataset.copy); var o = c.textContent; c.textContent = 'copied!'; setTimeout(function () { c.textContent = o; }, 800); } });
    });
    var h = (location.hash || '').replace('#', '');
    var url = new URLSearchParams(location.search);
    if (url.get('_tab')) h = url.get('_tab');
    if (url.get('tpl')) h = 'bodies';
    if (h) show(h);
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
