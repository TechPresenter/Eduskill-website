<?php
/**
 * Admin — Document Hub. Centralised, template-driven document & certificate
 * management: design templates (CKEditor + {{placeholders}}), issue documents
 * (auto-numbered, QR-verifiable), preview, generate, print and manage.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/document_hub.php';

dh_seed_defaults();

$table  = 'document_templates';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* ---- Save template (create/update) ---- */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $errors = validate($_POST, ['name' => 'required|max:191']);
    if ($errors) { set_flash('error', reset($errors)); redirect('/admin/document-hub?action=' . ($editId ? 'edit&id=' . $editId : 'create')); }

    $data = [
        'name'           => clean(post('name')),
        'slug'           => unique_slug($table, post('slug') ?: post('name'), $editId ?: null),
        'category'       => array_key_exists(post('category'), dh_categories()) ? post('category') : 'certificate',
        'doc_type'       => clean(post('doc_type', '')),
        'layout'         => array_key_exists(post('layout'), dh_layouts()) ? post('layout') : 'landscape',
        'theme'          => array_key_exists(post('theme'), dh_themes()) ? post('theme') : 'classic',
        'body'           => post('body', ''),
        'terms_enabled'  => post('terms_enabled') ? 1 : 0,
        'terms'          => post('terms', ''),
        'show_qr'        => post('show_qr') ? 1 : 0,
        'show_seal'      => post('show_seal') ? 1 : 0,
        'show_signature' => post('show_signature') ? 1 : 0,
        'show_logo'      => post('show_logo') ? 1 : 0,
        'show_watermark' => post('show_watermark') ? 1 : 0,
        'watermark_text' => clean(post('watermark_text', '')),
        'number_prefix'  => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) post('number_prefix', 'DOC')) ?: 'DOC'),
        'status'         => in_array(post('status'), ['draft', 'published'], true) ? post('status') : 'draft',
    ];
    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        db_insert('document_template_versions', ['template_id' => $editId, 'body' => $data['body'], 'terms' => $data['terms'], 'saved_by' => current_user_id()]);
    } else {
        $data['created_by'] = current_user_id();
        $editId = db_insert($table, $data);
    }
    log_activity('update', 'document-hub', 'Saved template #' . $editId);
    set_flash('success', 'Template saved.');
    redirect('/admin/document-hub?action=edit&id=' . $editId);
}
if (is_post() && post('_do') === 'duplicate') {
    require_csrf();
    $src = find($table, (int) post('id', 0));
    if ($src) {
        unset($src['id'], $src['created_at'], $src['updated_at']);
        $src['name'] .= ' (copy)';
        $src['slug'] = unique_slug($table, $src['slug'] . '-copy');
        $src['status'] = 'draft';
        $src['number_next'] = 1;
        $src['created_by'] = current_user_id();
        db_insert($table, $src);
        set_flash('success', 'Template duplicated.');
    }
    redirect('/admin/document-hub');
}
if (is_post() && post('_do') === 'toggle') {
    require_csrf();
    $t = find($table, (int) post('id', 0));
    if ($t) db_update($table, ['status' => $t['status'] === 'published' ? 'draft' : 'published'], 'id = :id', [':id' => (int) $t['id']]);
    redirect('/admin/document-hub');
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    db_delete($table, 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Template deleted.');
    redirect('/admin/document-hub');
}

/* ---- Install the standard document catalogue (~80 ready templates) ---- */
if (is_post() && post('_do') === 'install-catalogue') {
    require_csrf();
    $added = dh_seed_catalogue();
    log_activity('create', 'document-hub', 'Installed standard catalogue (+' . $added . ')');
    set_flash($added ? 'success' : 'info', $added ? ($added . ' standard templates installed.') : 'All standard templates are already installed.');
    redirect('/admin/document-hub');
}

/* ---- Import templates from a JSON export ---- */
if (is_post() && post('_do') === 'import') {
    require_csrf();
    $added = 0;
    if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $json = (string) file_get_contents($_FILES['file']['tmp_name']);
        $rows = json_decode($json, true);
        if (is_array($rows)) {
            $allow = ['name', 'category', 'doc_type', 'layout', 'theme', 'body', 'terms_enabled', 'terms', 'show_qr', 'show_seal', 'show_signature', 'show_logo', 'show_watermark', 'watermark_text', 'number_prefix', 'status', 'admin_enabled', 'user_enabled'];
            foreach ($rows as $r) {
                if (empty($r['name'])) { continue; }
                if (db_value('SELECT id FROM document_templates WHERE name = :n LIMIT 1', [':n' => $r['name']]) !== null) { continue; }
                $ins = ['slug' => unique_slug($table, slugify($r['name'])), 'number_next' => 1, 'created_by' => current_user_id()];
                foreach ($allow as $k) { if (array_key_exists($k, $r)) { $ins[$k] = $r[$k]; } }
                db_insert($table, $ins);
                $added++;
            }
        }
    }
    set_flash($added ? 'success' : 'error', $added ? ($added . ' templates imported.') : 'No new templates found in that file.');
    redirect('/admin/document-hub');
}

/* ---- Export all templates as JSON ---- */
if ($action === 'export') {
    $rows = db_all("SELECT name, category, doc_type, layout, theme, body, terms_enabled, terms, show_qr, show_seal, show_signature, show_logo, show_watermark, watermark_text, number_prefix, status, admin_enabled, user_enabled FROM $table ORDER BY category, name");
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="document-templates-' . date('Ymd') . '.json"');
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---- Bulk print / bulk ZIP of selected issued documents ---- */
if ($action === 'bulk-print' || $action === 'bulk-zip') {
    $ids = array_filter(array_map('intval', explode(',', (string) get('ids', ''))));
    if (!$ids) { set_flash('error', 'No documents selected.'); redirect('/admin/document-hub?action=issued'); }
    $in = implode(',', $ids);
    $docs = db_all("SELECT di.*, dt.body, dt.theme, dt.layout, dt.show_qr, dt.show_seal, dt.show_signature, dt.show_logo, dt.show_watermark, dt.watermark_text, dt.terms_enabled, dt.terms
                    FROM document_issued di JOIN document_templates dt ON dt.id = di.template_id WHERE di.id IN ($in)");

    if ($action === 'bulk-zip') {
        $zipPath = tempnam(sys_get_temp_dir(), 'dhz') . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $head = '<!DOCTYPE html><meta charset="utf-8"><style>' . dh_styles() . 'body{margin:0;padding:20px;background:#fff}</style>';
        foreach ($docs as $d) {
            $html = dh_render($d, json_decode((string) $d['data'], true) ?: [], $d);
            $zip->addFromString($d['doc_no'] . '.html', $head . $html);
        }
        $zip->close();
        log_activity('export', 'document-hub', 'Bulk ZIP of ' . count($docs) . ' documents');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="documents-' . date('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        @unlink($zipPath);
        exit;
    }

    // bulk-print: one page, all documents, page-break between each.
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Bulk print (<?= count($docs) ?>)</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style><?= dh_styles() ?> body{margin:0;padding:4rem 1rem 2rem;background:#e5e7eb} .doc{margin:0 auto 2rem} .pv-bar{position:fixed;top:0;left:0;right:0;background:#0f172a;color:#fff;padding:.7rem;text-align:center;z-index:10} .pv-bar button{background:#063566;color:#fff;border:0;padding:.55rem 1rem;border-radius:9px;cursor:pointer;font:inherit;font-weight:600}</style>
    </head><body><div class="pv-bar no-print"><button onclick="window.print()">Print / Save all as PDF</button></div>
    <?php foreach ($docs as $d) { echo dh_render($d, json_decode((string) $d['data'], true) ?: [], $d); } ?>
    </body></html><?php
    exit;
}

/* ---- Generate (issue a document) ---- */
if (is_post() && post('_do') === 'generate') {
    require_csrf();
    $tpl = find($table, (int) post('id', 0));
    if (!$tpl) { set_flash('error', 'Template not found.'); redirect('/admin/document-hub'); }
    if (!dh_admin_enabled($tpl)) { set_flash('error', 'This document type is disabled.'); redirect('/admin/document-hub'); }
    $data = [];
    foreach ($_POST as $k => $v) {
        if (preg_match('/^field_([a-z0-9_]+)$/', $k, $m)) { $data[$m[1]] = clean((string) $v); }
    }
    if (!empty($_FILES['photo']['name'])) {
        // 'media', not 'documents': this photo is embedded as <img> in the
        // issued document, which is rendered on the PUBLIC document.php view.
        // uploads/documents is Apache-denied (PII), so a photo stored there
        // would render as a broken image for every public viewer.
        $up = upload_image($_FILES['photo'], 'media');
        if ($up['success']) { $data['photo'] = upload_url($up['path']); }
    }
    $data['recipient_email'] = clean(post('recipient_email', ''));
    $issued = dh_issue($tpl, $data);
    // Optional email to recipient.
    if (post('send_email') && !empty($data['recipient_email']) && filter_var($data['recipient_email'], FILTER_VALIDATE_EMAIL)) {
        $link = rtrim(APP_URL, '/') . '/document?t=' . $issued['qr_token'];
        try { send_mail($data['recipient_email'], $tpl['doc_type'] . ' — ' . $issued['doc_no'], '<p>Your ' . e($tpl['doc_type']) . ' is ready.</p><p><a href="' . e($link) . '">View / download &rarr;</a></p>'); } catch (Throwable $e) {}
    }
    redirect('/document?t=' . $issued['qr_token']);
}

/* ---- Preview (standalone) ---- */
if ($action === 'preview') {
    $tpl = find($table, $id);
    if (!$tpl) { redirect('/admin/document-hub'); }
    $html = dh_render($tpl, dh_sample_data(), null);
    ?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Preview · <?= e($tpl['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style><?= dh_styles() ?>
    body{margin:0;padding:5rem 1.5rem 2rem;background:#e5e7eb;} .pv-bar{position:fixed;top:0;left:0;right:0;background:#0f172a;color:#fff;padding:.7rem 1rem;display:flex;gap:.6rem;justify-content:center;align-items:center;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.3)} .pv-bar b{margin-right:auto;padding-left:.5rem} .pv-bar button,.pv-bar a{background:#063566;color:#fff;border:0;padding:.55rem 1rem;border-radius:9px;cursor:pointer;text-decoration:none;font:inherit;font-weight:600}</style>
    </head><body>
    <div class="pv-bar no-print"><b>Preview — sample data</b><button onclick="window.print()">Print / Save as PDF</button><a href="<?= e(admin_url('document-hub?action=generate&id=' . $id)) ?>">Generate real</a><a href="<?= e(admin_url('document-hub?action=edit&id=' . $id)) ?>">Edit</a><a href="<?= e(admin_url('document-hub')) ?>">Close</a></div>
    <?= $html ?>
    </body></html><?php
    exit;
}

/* ---- Generate form ---- */
if ($action === 'generate') {
    $tpl = find($table, $id);
    if (!$tpl) { set_flash('error', 'Template not found.'); redirect('/admin/document-hub'); }
    // Discover user-input placeholders in the body (exclude auto/special ones).
    $auto = ['logo', 'qr_code', 'seal', 'signature', 'barcode', 'photo', 'organization_name', 'date', 'issue_date', 'certificate_no', 'doc_no'];
    preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', (string) $tpl['body'], $mm);
    $fields = array_values(array_unique(array_map('strtolower', $mm[1] ?? [])));
    $fields = array_diff($fields, $auto);
    $hasPhoto = strpos((string) $tpl['body'], '{{photo}}') !== false;
    $page_title = 'Generate — ' . $tpl['name'];
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= lucide('file-plus') ?> Generate: <?= e($tpl['name']) ?></h1><span class="muted">Document Hub / Issue a document</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('document-hub')) ?>">← Back</a></div>
    <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url('document-hub')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?><input type="hidden" name="_do" value="generate"><input type="hidden" name="id" value="<?= (int) $tpl['id'] ?>">
        <div class="grid-2">
            <?php foreach ($fields as $f): ?>
                <div class="form-group"><label class="form-label"><?= e(ucwords(str_replace('_', ' ', $f))) ?></label><input class="form-control" name="field_<?= e($f) ?>"></div>
            <?php endforeach; ?>
            <?php if ($hasPhoto): ?><div class="form-group"><label class="form-label">Photo</label><input class="form-control" type="file" name="photo" accept="image/*"></div><?php endif; ?>
            <div class="form-group"><label class="form-label">Recipient email (optional)</label><input class="form-control" type="email" name="recipient_email"></div>
        </div>
        <label class="checkbox"><input type="checkbox" name="send_email" value="1"> Email the document link to the recipient</label>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('file-check') ?> Generate &amp; open</button><a class="btn btn-ghost" href="<?= e(admin_url('document-hub')) ?>">Cancel</a></div>
    </form></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- Issued documents list ---- */
if ($action === 'issued') {
    $p = paginate("SELECT di.*, dt.name AS tpl_name FROM document_issued di LEFT JOIN document_templates dt ON dt.id = di.template_id ORDER BY di.id DESC", [], 20);
    $page_title = 'Issued Documents';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= lucide('files') ?> Issued Documents</h1><span class="muted"><?= (int) $p['total'] ?> issued</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('document-hub')) ?>">← Templates</a></div>
    <div class="panel"><div class="panel-body">
    <?php if ($p['items']): ?>
        <div class="flex gap-2" style="margin-bottom:1rem;flex-wrap:wrap;align-items:center;">
            <button type="button" class="btn btn-sm btn-secondary" id="dh-bulk-print" disabled><?= lucide('printer') ?> Bulk print</button>
            <button type="button" class="btn btn-sm btn-secondary" id="dh-bulk-zip" disabled><?= lucide('file-archive') ?> Download ZIP</button>
            <span class="text-muted" style="font-size:.82rem;" id="dh-bulk-count">Select documents to print or download together.</span>
        </div>
        <div class="table-wrap"><table class="admin-table"><thead><tr><th style="width:34px;"><input type="checkbox" id="dh-check-all"></th><th>Doc No</th><th>Type</th><th>Recipient</th><th>Issued</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr><td><input type="checkbox" class="dh-check" value="<?= (int) $r['id'] ?>"></td><td><code><?= e($r['doc_no']) ?></code></td><td><?= e($r['doc_type']) ?></td><td><?= e($r['recipient_name'] ?: '—') ?><br><small class="text-muted"><?= e($r['recipient_email'] ?: '') ?></small></td>
                <td><small class="text-muted"><?= e(format_date($r['created_at'], 'd M Y')) ?></small></td>
                <td style="text-align:right;"><div class="actions">
                    <a class="icon-btn" href="<?= e(url('document?t=' . $r['qr_token'])) ?>" target="_blank" title="View / print"><?= lucide('printer') ?></a>
                    <a class="icon-btn" href="<?= e(url('verify-document?t=' . $r['qr_token'])) ?>" target="_blank" title="Verify"><?= lucide('shield-check') ?></a>
                </div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div><?= $p['links'] ?>
        <script>
        (function () {
            var all = document.getElementById('dh-check-all'), boxes = document.querySelectorAll('.dh-check');
            var bp = document.getElementById('dh-bulk-print'), bz = document.getElementById('dh-bulk-zip'), cnt = document.getElementById('dh-bulk-count');
            var base = <?= json_encode(admin_url('document-hub')) ?>;
            function selected() { return Array.from(boxes).filter(function (b) { return b.checked; }).map(function (b) { return b.value; }); }
            function sync() { var s = selected(); bp.disabled = bz.disabled = !s.length; cnt.textContent = s.length ? (s.length + ' selected') : 'Select documents to print or download together.'; }
            if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
            boxes.forEach(function (b) { b.addEventListener('change', sync); });
            bp.addEventListener('click', function () { var s = selected(); if (s.length) window.open(base + '?action=bulk-print&ids=' + s.join(','), '_blank'); });
            bz.addEventListener('click', function () { var s = selected(); if (s.length) window.location = base + '?action=bulk-zip&ids=' + s.join(','); });
        })();
        </script>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('files') ?></div>No documents issued yet.</div><?php endif; ?>
    </div></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- Create / edit builder ---- */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Not found.'); redirect('/admin/document-hub'); }
    $chk = static fn ($k, $d = 1) => (!array_key_exists($k, (array) $row) ? $d : !empty($row[$k])) ? 'checked' : '';
    $page_title = $action === 'edit' ? 'Edit Template' : 'New Template';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= lucide('file-pen-line') ?> <?= e($page_title) ?></h1><span class="muted">Document Hub / Template builder</span></div>
        <div class="flex gap-2"><?php if ($action === 'edit'): ?><a class="btn btn-secondary" href="<?= e(admin_url('document-hub?action=preview&id=' . $id)) ?>" target="_blank"><?= lucide('eye') ?> Preview</a><?php endif; ?><a class="btn btn-secondary" href="<?= e(admin_url('document-hub')) ?>">← Back</a></div></div>

    <form class="admin-form" method="post" action="<?= e(admin_url('document-hub')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="grid-2" style="align-items:start;gap:1.5rem;">
            <div class="panel"><div class="panel-body">
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Template name <span class="req">*</span></label><input class="form-control" name="name" required value="<?= e($row['name'] ?? '') ?>" data-slug-source></div>
                    <div class="form-group"><label class="form-label">Document type</label><input class="form-control" name="doc_type" value="<?= e($row['doc_type'] ?? '') ?>" placeholder="e.g. Membership Certificate"></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach (dh_categories() as $k => $l): ?><option value="<?= $k ?>" <?= ($row['category'] ?? 'certificate') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Layout</label><select class="form-select" name="layout"><?php foreach (dh_layouts() as $k => $l): ?><option value="<?= $k ?>" <?= ($row['layout'] ?? 'landscape') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Theme</label><select class="form-select" name="theme"><?php foreach (dh_themes() as $k => $l): ?><option value="<?= $k ?>" <?= ($row['theme'] ?? 'classic') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Number prefix</label><input class="form-control" name="number_prefix" value="<?= e($row['number_prefix'] ?? 'DOC') ?>" style="text-transform:uppercase;"></div>
                </div>
                <div class="form-group"><label class="form-label">Template body (HTML + placeholders)</label>
                    <textarea class="form-textarea" name="body" data-wysiwyg style="min-height:280px;"><?= e($row['body'] ?? '') ?></textarea>
                </div>
                <label class="checkbox"><input type="checkbox" name="terms_enabled" value="1" <?= !empty($row['terms_enabled']) ? 'checked' : '' ?>> Enable Terms &amp; Conditions</label>
                <div class="form-group" style="margin-top:.6rem;"><label class="form-label">Terms &amp; Conditions</label>
                    <textarea class="form-textarea" name="terms" data-wysiwyg style="min-height:150px;"><?= e($row['terms'] ?? '') ?></textarea>
                </div>
            </div></div>

            <div>
                <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('sliders-horizontal') ?> Options</h3></div><div class="panel-body">
                    <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status"><option value="draft" <?= ($row['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option><option value="published" <?= ($row['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option></select></div>
                    <label class="checkbox"><input type="checkbox" name="show_logo" value="1" <?= $chk('show_logo') ?>> Show logo</label><br>
                    <label class="checkbox"><input type="checkbox" name="show_qr" value="1" <?= $chk('show_qr') ?>> Show QR code</label><br>
                    <label class="checkbox"><input type="checkbox" name="show_seal" value="1" <?= $chk('show_seal') ?>> Show seal</label><br>
                    <label class="checkbox"><input type="checkbox" name="show_signature" value="1" <?= $chk('show_signature') ?>> Show signature</label><br>
                    <label class="checkbox"><input type="checkbox" name="show_watermark" value="1" <?= $chk('show_watermark', 0) ?>> Watermark</label>
                    <div class="form-group" style="margin-top:.6rem;"><input class="form-control" name="watermark_text" value="<?= e($row['watermark_text'] ?? '') ?>" placeholder="Watermark text"></div>
                </div></div>
                <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('braces') ?> Placeholders</h3></div><div class="panel-body" style="max-height:340px;overflow:auto;">
                    <p class="text-muted" style="font-size:.8rem;">Click to copy, then paste into the body.</p>
                    <?php foreach (dh_placeholders() as $grp => $items): ?>
                        <div style="font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:.6rem 0 .3rem;"><?= e($grp) ?></div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach ($items as $k => $l): ?><button type="button" class="pill pill-blue" style="border:0;cursor:pointer;font-family:monospace;" onclick="navigator.clipboard&&navigator.clipboard.writeText('{{<?= e($k) ?>}}');this.textContent='copied!';var s=this;setTimeout(function(){s.textContent='{{<?= e($k) ?>}}'},900);">{{<?= e($k) ?>}}</button><?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div></div>
            </div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save template</button><a class="btn btn-ghost" href="<?= e(admin_url('document-hub')) ?>">Cancel</a></div>
    </form>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- List (templates) ---- */
$page_title = 'Document Hub';
$catF = array_key_exists(get('category', ''), dh_categories()) ? get('category') : '';
$q = trim((string) get('q', ''));
$where = 'admin_enabled = 1'; $params = [];
if ($catF !== '') { $where .= ' AND category = :c'; $params[':c'] = $catF; }
// Distinct placeholder per slot: PDO runs with EMULATE_PREPARES=false, where a
// named placeholder may be bound only once — reusing :q threw HY093 on every search.
if ($q !== '')    { $where .= ' AND (name LIKE :q1 OR doc_type LIKE :q2)'; $params[':q1'] = $params[':q2'] = '%' . $q . '%'; }
$rows = db_all("SELECT * FROM $table WHERE $where ORDER BY category, name", $params);
$issuedCount = (int) db_value("SELECT COUNT(*) FROM document_issued");
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1><?= lucide('stamp') ?> Document Hub</h1><span class="muted"><?= count($rows) ?> templates · <?= $issuedCount ?> issued</span></div>
    <div class="flex gap-2" style="flex-wrap:wrap;">
        <a class="btn btn-secondary" href="<?= e(admin_url('document-builder')) ?>"><?= lucide('file-cog') ?> Builder</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('document-hub?action=issued')) ?>"><?= lucide('files') ?> Issued</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('document-hub?action=export')) ?>"><?= lucide('download') ?> Export</a>
        <a class="btn btn-primary" href="<?= e(admin_url('document-hub?action=create')) ?>">+ New Template</a></div></div>

<?php $catalogueCount = count(dh_catalogue()); ?>
<div class="panel" style="margin-bottom:1rem;"><div class="panel-body" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:.7rem;">
        <span style="width:44px;height:44px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(225,29,72,.14),rgba(8,72,129,.14));color:#e11d48;"><?= lucide('library-big') ?></span>
        <div><strong>Standard template library</strong><br><small class="text-muted"><?= $catalogueCount ?> professional NGO, educational, exam &amp; ID-card templates ready to install.</small></div>
    </div>
    <div class="flex gap-2" style="flex-wrap:wrap;">
        <form method="post" action="<?= e(admin_url('document-hub')) ?>" enctype="multipart/form-data" style="display:flex;gap:.5rem;align-items:center;">
            <?= csrf_field() ?><input type="hidden" name="_do" value="import">
            <input class="form-control" type="file" name="file" accept="application/json,.json" style="max-width:200px;">
            <button class="btn btn-secondary" type="submit"><?= lucide('upload') ?> Import</button>
        </form>
        <form method="post" action="<?= e(admin_url('document-hub')) ?>"><?= csrf_field() ?><input type="hidden" name="_do" value="install-catalogue">
            <button class="btn btn-primary" type="submit"><?= lucide('package-plus') ?> Install standard templates</button></form>
    </div>
</div></div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('document-hub')) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search templates…">
            <select class="form-select" name="category" style="max-width:170px;"><option value="">All categories</option><?php foreach (dh_categories() as $k => $l): ?><option value="<?= $k ?>" <?= $catF === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <button class="btn btn-secondary" type="submit"><?= lucide('search') ?></button>
        </form>
    </div>
    <?php if ($rows): ?>
    <div class="grid grid-3" style="gap:1rem;">
        <?php foreach ($rows as $r): ?>
            <div class="card-3d" style="padding:1.1rem;">
                <div class="flex items-center justify-between mb-1">
                    <span class="pill pill-blue"><?= e(dh_categories()[$r['category']] ?? $r['category']) ?></span>
                    <span class="pill <?= $r['status'] === 'published' ? 'pill-green' : 'pill-gray' ?>"><?= e($r['status']) ?></span>
                </div>
                <h3 style="margin:.3rem 0 .1rem;font-size:1.02rem;"><?= e($r['name']) ?></h3>
                <p class="text-muted" style="font-size:.8rem;margin:0 0 .7rem;"><?= e($r['doc_type'] ?: ucfirst($r['category'])) ?> · <?= e(dh_themes()[$r['theme']] ?? $r['theme']) ?> · <?= e(dh_layouts()[$r['layout']] ?? $r['layout']) ?></p>
                <div class="flex flex-wrap gap-1">
                    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('document-hub?action=preview&id=' . $r['id'])) ?>" target="_blank"><?= lucide('eye') ?> Preview</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(admin_url('document-hub?action=generate&id=' . $r['id'])) ?>"><?= lucide('file-plus') ?> Generate</a>
                    <a class="btn btn-secondary btn-sm" href="<?= e(admin_url('document-hub?action=edit&id=' . $r['id'])) ?>"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('document-hub')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="duplicate"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn" type="submit" title="Duplicate"><?= lucide('copy') ?></button></form>
                    <form method="post" action="<?= e(admin_url('document-hub')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn" type="submit" title="Toggle status"><?= lucide($r['status'] === 'published' ? 'toggle-right' : 'toggle-left') ?></button></form>
                    <form method="post" action="<?= e(admin_url('document-hub')) ?>" data-confirm="Delete this template?" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('stamp') ?></div>No templates match. <a href="<?= e(admin_url('document-hub?action=create')) ?>">Create one</a>.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
