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
.db-wrap{max-width:1180px}
.db-tabs{display:flex;flex-wrap:wrap;gap:.35rem;border-bottom:1px solid var(--border);margin-bottom:1.4rem;padding-bottom:.1rem}
.db-tab{border:0;background:transparent;color:var(--muted);font:inherit;font-weight:600;font-size:.92rem;padding:.65rem 1rem;border-radius:9px 9px 0 0;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;display:inline-flex;align-items:center;gap:.45rem}
.db-tab:hover{color:var(--text);background:var(--surface-2)}
.db-tab.is-active{color:var(--brand-600);border-bottom-color:var(--brand-600)}
.db-tab svg{width:1.05em;height:1.05em}
.db-panel{display:none}.db-panel.is-active{display:block;animation:dbfade .2s ease}
@keyframes dbfade{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
.db-ph-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:.7rem}
.db-ph{display:flex;gap:.7rem;align-items:flex-start;border:1px solid var(--border);border-radius:11px;padding:.7rem .85rem;background:var(--surface)}
.db-ph code{background:var(--surface-2);border:1px solid var(--border);border-radius:7px;padding:.28rem .5rem;font-weight:700;color:var(--brand-600);white-space:nowrap;cursor:pointer;font-size:.82rem}
.db-ph b{font-size:.9rem}.db-ph small{color:var(--muted);display:block;font-size:.76rem}
.db-thumb{width:84px;height:84px;border-radius:12px;border:1px solid var(--border);object-fit:contain;background:var(--surface-2);padding:6px}
.db-tips{background:rgba(230,123,29,.08);border:1px solid rgba(230,123,29,.35);border-radius:12px;padding:1rem 1.2rem;margin-top:1.2rem}
.db-tips li{color:#92600a;font-size:.86rem;margin:.2rem 0}
.db-toggle{display:inline-flex;padding:0;border:0;background:transparent;gap:0}
</style>

<div class="admin-content db-wrap">
<div class="admin-page-head">
    <div><h1><?= lucide('file-cog') ?> Document Template Builder</h1><span class="muted">Customise signatures, seals, bodies and access for all documents</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('document-hub')) ?>"><?= lucide('layout-grid') ?> Document Hub</a>
</div>

<div class="db-tabs" role="tablist">
    <button class="db-tab is-active" data-tab="identity"><?= lucide('pen-tool') ?> Signature &amp; Seal</button>
    <button class="db-tab" data-tab="bodies"><?= lucide('file-text') ?> Document Bodies</button>
    <button class="db-tab" data-tab="placeholders"><?= lucide('code') ?> Placeholder Reference</button>
    <button class="db-tab" data-tab="access"><?= lucide('toggle-right') ?> Document Access</button>
</div>

<!-- ============ SIGNATURE & SEAL ============ -->
<section class="db-panel is-active" id="db-identity">
    <form method="post" action="<?= e(admin_url('document-builder')) ?>" enctype="multipart/form-data" class="admin-form">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save-identity">
        <div class="grid-2" style="gap:1.5rem;align-items:start;">
            <div class="panel"><div class="panel-head"><h3><?= lucide('pen-tool') ?> Signatory Information</h3></div><div class="panel-body">
                <div class="form-group"><label class="form-label">Authorised signatory name</label>
                    <input class="form-control" name="doc_signatory_name" value="<?= e($sig['name']) ?>" placeholder="Director / Secretary">
                    <span class="form-hint">Available as <code>{{signatory_name}}</code> in document bodies.</span></div>
                <div class="form-group"><label class="form-label">Signatory designation</label>
                    <input class="form-control" name="doc_signatory_designation" value="<?= e($sig['designation']) ?>" placeholder="<?= e(get_setting('site_name', SITE_NAME)) ?>">
                    <span class="form-hint">Subtitle under the name — <code>{{signatory_designation}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h3><?= lucide('signature') ?> Signature Image</h3></div><div class="panel-body">
                <?php if ($assets['signature']): ?><img class="db-thumb" src="<?= e($assets['signature']) ?>" alt="signature"><?php endif; ?>
                <div class="form-group" style="margin-top:.6rem;"><label class="form-label">Upload signature</label>
                    <input class="form-control" type="file" name="signature" accept="image/png,image/jpeg,image/webp">
                    <span class="form-hint">PNG with transparent background recommended. Renders where a body uses <code>{{signature}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h3><?= lucide('image') ?> Organisation Logo</h3></div><div class="panel-body">
                <?php if ($assets['logo']): ?><img class="db-thumb" src="<?= e($assets['logo']) ?>" alt="logo"><?php endif; ?>
                <div class="form-group" style="margin-top:.6rem;"><label class="form-label">Upload logo</label>
                    <input class="form-control" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                    <span class="form-hint">Appears where a body uses <code>{{logo}}</code>.</span></div>
            </div></div>

            <div class="panel"><div class="panel-head"><h3><?= lucide('stamp') ?> Official Seal / Stamp</h3></div><div class="panel-body">
                <?php if ($assets['seal']): ?><img class="db-thumb" src="<?= e($assets['seal']) ?>" alt="seal"><?php endif; ?>
                <div class="form-group" style="margin-top:.6rem;"><label class="form-label">Upload seal</label>
                    <input class="form-control" type="file" name="seal" accept="image/png,image/jpeg,image/webp">
                    <span class="form-hint">Circular PNG recommended. Renders where a body uses <code>{{seal}}</code>. Without one, a generated seal is shown.</span></div>
            </div></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Identity Settings</button></div>
    </form>
</section>

<!-- ============ DOCUMENT BODIES ============ -->
<section class="db-panel" id="db-bodies">
    <div class="panel"><div class="panel-head"><h3><?= lucide('file-text') ?> Edit Document Body</h3></div><div class="panel-body">
        <?php if ($adminTpls): ?>
        <form method="get" action="<?= e(admin_url('document-builder')) ?>" style="margin-bottom:1rem;display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;">
            <label class="form-label" style="margin:0;">Document</label>
            <select class="form-select" name="tpl" onchange="this.form.submit()" style="max-width:340px;">
                <?php foreach ($adminTpls as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $selTpl && (int) $selTpl['id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?> — <?= e(dh_categories()[$t['category']] ?? $t['category']) ?></option><?php endforeach; ?>
            </select>
            <input type="hidden" name="_tab" value="bodies">
            <?php if ($selTpl): ?><a class="btn btn-outline btn-sm" href="<?= e(admin_url('document-hub?action=preview&id=' . $selTpl['id'])) ?>" target="_blank"><?= lucide('eye') ?> Preview</a><?php endif; ?>
        </form>
        <?php if ($selTpl): ?>
        <form method="post" action="<?= e(admin_url('document-builder')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-body"><input type="hidden" name="template_id" value="<?= (int) $selTpl['id'] ?>">
            <textarea class="form-textarea" name="body" data-wysiwyg style="min-height:340px;"><?= e($selTpl['body']) ?></textarea>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Body</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('document-hub?action=edit&id=' . $selTpl['id'])) ?>">Advanced settings →</a></div>
        </form>
        <?php endif; ?>
        <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('file-text') ?></div>No documents yet. <a href="<?= e(admin_url('document-hub?action=create')) ?>">Create one</a>.</div><?php endif; ?>
    </div></div>
</section>

<!-- ============ PLACEHOLDER REFERENCE ============ -->
<section class="db-panel" id="db-placeholders">
    <div class="panel"><div class="panel-head"><h3><?= lucide('code') ?> Available Template Placeholders</h3></div><div class="panel-body">
        <p class="text-muted" style="margin-bottom:1rem;">Use these in any document body. They are replaced with real data when a document is generated. Click a placeholder to copy it.</p>
        <?php foreach (dh_placeholders() as $group => $items): ?>
            <div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:1rem 0 .5rem;"><?= e($group) ?></div>
            <div class="db-ph-grid">
                <?php foreach ($items as $k => $label): ?>
                    <div class="db-ph"><code data-copy="{{<?= e($k) ?>}}" title="Copy">{{<?= e($k) ?>}}</code><div><b><?= e($label) ?></b><small><?= e($phDesc[$k] ?? 'Placeholder') ?></small></div></div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <div class="db-tips"><strong style="color:#92600a;"><?= lucide('lightbulb') ?> Tips</strong>
            <ul style="margin:.5rem 0 0;padding-left:1.1rem;">
                <li>Placeholders are case-insensitive; unrecognised ones are removed from the output.</li>
                <li>Use plain text, line breaks and HTML in bodies (authored via the rich editor).</li>
                <li><code>{{signature}}</code>, <code>{{logo}}</code>, <code>{{seal}}</code> and <code>{{qr_code}}</code> render from the Signature &amp; Seal tab and settings.</li>
                <li>Preview any document after saving to test your changes.</li>
            </ul>
        </div>
    </div></div>

    <div class="panel"><div class="panel-head"><h3><?= lucide('eye') ?> Quick Document Preview</h3></div><div class="panel-body">
        <p class="text-muted" style="margin-bottom:.8rem;">Open any document with sample data to see your template changes, or look up an issued document number.</p>
        <div class="grid-2" style="gap:1rem;">
            <form action="<?= e(admin_url('document-hub')) ?>" method="get" target="_blank" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
                <input type="hidden" name="action" value="preview">
                <div class="form-group" style="flex:1;margin:0;"><label class="form-label">Preview a template (sample data)</label>
                    <select class="form-select" name="id"><?php foreach ($adminTpls as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></div>
                <button class="btn btn-primary" type="submit"><?= lucide('eye') ?> Preview</button>
            </form>
            <form action="<?= e(url('verify-document')) ?>" method="get" target="_blank" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;margin:0;"><label class="form-label">Look up an issued document</label>
                    <input class="form-control" name="no" placeholder="e.g. MEMC-2026-0001"></div>
                <button class="btn btn-secondary" type="submit"><?= lucide('search') ?> Verify</button>
            </form>
        </div>
    </div></div>
</section>

<!-- ============ DOCUMENT ACCESS ============ -->
<section class="db-panel" id="db-access">
    <div class="panel"><div class="panel-head"><h3><?= lucide('toggle-right') ?> Document Enable / Disable</h3></div><div class="panel-body">
        <div class="alert alert-info" style="margin-bottom:1rem;">
            <strong>Admin Access:</strong> if off, the document is hidden from the Document Hub and cannot be generated.<br>
            <strong>Public Access:</strong> if off, only signed-in admins can open/download it — recipients with the link cannot.
        </div>
        <form method="post" action="<?= e(admin_url('document-builder')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save-access">
            <div class="table-wrap"><table class="admin-table"><thead><tr><th>Document Type</th><th style="text-align:center;">Admin Access</th><th style="text-align:center;">Public Access</th></tr></thead><tbody>
                <?php foreach ($allTpls as $t): ?>
                    <tr>
                        <td><strong><?= e($t['name']) ?></strong><br><small class="text-muted"><?= e(dh_categories()[$t['category']] ?? $t['category']) ?></small></td>
                        <td style="text-align:center;"><label class="switch db-toggle"><input type="checkbox" name="admin_enabled[<?= (int) $t['id'] ?>]" value="1" <?= dh_admin_enabled($t) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></label></td>
                        <td style="text-align:center;"><label class="switch db-toggle"><input type="checkbox" name="user_enabled[<?= (int) $t['id'] ?>]" value="1" <?= dh_user_enabled($t) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></label></td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save Access Settings</button></div>
        </form>
    </div></div>
</section>
</div>

<script>
(function () {
    var tabs = document.querySelectorAll('.db-tab'), panels = document.querySelectorAll('.db-panel');
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
