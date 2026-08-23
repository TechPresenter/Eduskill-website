<?php
/**
 * =============================================================================
 *  Admin — Schemes & Initiatives CRUD.
 *  Standard admin module: list + create + edit + delete, CSRF, validation,
 *  pagination, image upload (with old-file cleanup), slug generation,
 *  activity logging. Mirrors admin/programs.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'schemes';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* -------------------------------------------------------------- SAVE (create/update) */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);

    $errors = validate($_POST, ['title' => 'required|max:191']);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }

    $deadline = trim((string) post('deadline', ''));

    $data = [
        'title'              => clean(post('title')),
        'slug'               => unique_slug($table, post('slug') ?: post('title'), $editId ?: null),
        'category'           => clean(post('category', '')),
        'department'         => clean(post('department', '')),
        'short_description'  => clean(post('short_description', '')),
        'description'        => post('description', ''), // rich text allowed
        'subtitle'           => clean(post('subtitle', '')),
        'eligibility'        => clean(post('eligibility', '')),
        'benefits'           => clean(post('benefits', '')),
        'documents_required' => clean(post('documents_required', '')),
        /* Line-based list fields, same convention as eligibility/benefits —
           schemes.php splits them on newlines. */
        'objectives'         => clean(post('objectives', '')),
        'support_items'      => clean(post('support_items', '')),
        'budget_note'        => clean(post('budget_note', '')),
        'process_steps'      => clean(post('process_steps', '')),
        'partnership'        => clean(post('partnership', '')),
        'transparency'       => clean(post('transparency', '')),
        'faq'                => clean(post('faq', '')),
        'guidelines'         => post('guidelines', ''),   // rich text allowed
        'apply_url'          => clean(post('apply_url', '')),
        'donate_url'         => clean(post('donate_url', '')),
        'deadline'           => $deadline !== '' ? $deadline : null,
        'is_featured'        => post('is_featured') ? 1 : 0,
        'sort_order'         => (int) post('sort_order', 0),
        'status'             => in_array(post('status'), ['active', 'closed'], true) ? post('status') : 'active',
    ];

    // Optional image upload (replaces + deletes the old one).
    if (!empty($_FILES['image']['name'])) {
        $up = upload_image($_FILES['image'], 'images');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['image'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['image'])) delete_upload($old['image']);
        }
    }

    /* Primary brochure. Brochures are meant to be downloaded by the public, so
       they go in uploads/brochures — a normally-served folder — NOT in
       uploads/documents, which carries a "Require all denied" .htaccess for
       applicant PII and would 403 every visitor. */
    if (!empty($_FILES['brochure']['name'])) {
        $up = upload_file($_FILES['brochure'], SCHEME_BROCHURE_DIR, ['allowed' => 'pdf,doc,docx,jpg,jpeg,png']);
        if (!$up['success']) {
            set_flash('error', 'Brochure: ' . $up['error']);
            redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
        }
        $data['brochure'] = $up['path'];
        if ($editId) {
            $old = find($table, $editId);
            if ($old && !empty($old['brochure'])) delete_upload($old['brochure']);
        }
    }

    /* Additional downloads. The file input is multiple, so $_FILES arrives
       column-wise (name[], tmp_name[], …) and has to be transposed back into
       one array per file before upload_file() can read it. */
    $extra = $editId ? scheme_brochures(find($table, $editId)) : [];
    if (!empty($_FILES['brochures']['name'][0])) {
        $labels = $_POST['brochure_labels'] ?? [];
        foreach ($_FILES['brochures']['name'] as $i => $name) {
            if ($name === '' || count($extra) >= 12) {
                continue;
            }
            $one = [
                'name'     => $name,
                'type'     => $_FILES['brochures']['type'][$i] ?? '',
                'tmp_name' => $_FILES['brochures']['tmp_name'][$i] ?? '',
                'error'    => $_FILES['brochures']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $_FILES['brochures']['size'][$i] ?? 0,
            ];
            $up = upload_file($one, SCHEME_BROCHURE_DIR, ['allowed' => 'pdf,doc,docx,xls,xlsx,jpg,jpeg,png']);
            if (!$up['success']) {
                set_flash('error', $name . ': ' . $up['error']);
                redirect('/admin/schemes?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
            }
            $extra[] = [
                'label' => clean($labels[$i] ?? '') ?: pathinfo($name, PATHINFO_FILENAME),
                'path'  => $up['path'],
                'size'  => (int) ($up['size'] ?? 0),
            ];
        }
    }
    $data['brochures'] = $extra ? json_encode(array_values($extra), JSON_UNESCAPED_UNICODE) : null;

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'schemes', 'Updated scheme #' . $editId);
        set_flash('success', 'Scheme updated successfully.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'schemes', 'Created scheme #' . $newId);
        set_flash('success', 'Scheme created successfully.');
    }
    redirect('/admin/schemes');
}

/* -------------------------------------------------------------- REMOVE ONE FILE */
if (is_post() && post('_do') === 'drop_file') {
    require_csrf();
    $fid  = (int) post('id', 0);
    $what = (string) post('what', '');
    $row  = find($table, $fid);
    if ($row) {
        if ($what === 'brochure' && !empty($row['brochure'])) {
            delete_upload($row['brochure']);
            db_update($table, ['brochure' => null], 'id = :id', [':id' => $fid]);
            set_flash('success', 'Brochure removed.');
        } elseif ($what === 'extra') {
            $idx  = (int) post('index', -1);
            $list = scheme_brochures($row);
            if (isset($list[$idx])) {
                delete_upload($list[$idx]['path'] ?? null);
                array_splice($list, $idx, 1);
                db_update($table, ['brochures' => $list ? json_encode(array_values($list), JSON_UNESCAPED_UNICODE) : null],
                    'id = :id', [':id' => $fid]);
                set_flash('success', 'Download removed.');
            }
        } elseif ($what === 'image' && !empty($row['image'])) {
            delete_upload($row['image']);
            db_update($table, ['image' => null], 'id = :id', [':id' => $fid]);
            set_flash('success', 'Image removed.');
        }
        log_activity('update', 'schemes', 'Removed ' . $what . ' from scheme #' . $fid);
    }
    redirect('/admin/schemes?action=edit&id=' . $fid);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['image'])) delete_upload($row['image']);
        // Uploaded files outlive the row unless they are removed with it.
        if (!empty($row['brochure'])) delete_upload($row['brochure']);
        foreach (scheme_brochures($row) as $b) {
            delete_upload($b['path'] ?? null);
        }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'schemes', 'Deleted scheme #' . $delId);
        set_flash('success', 'Scheme deleted.');
    }
    redirect('/admin/schemes');
}

/* -------------------------------------------------------------- FORM (create/edit) */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) {
        set_flash('error', 'Scheme not found.');
        redirect('/admin/schemes');
    }
    $page_title = $action === 'edit' ? 'Edit Scheme' : 'Add Scheme';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Schemes &amp; Initiatives / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('schemes')) ?>">← Back to list</a>
    </div>

    <div class="panel">
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('schemes')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Title <span class="req">*</span></label>
                    <input class="form-control" name="title" data-slug-source required value="<?= e($row['title'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Slug</label>
                    <input class="form-control" name="slug" data-slug-target value="<?= e($row['slug'] ?? '') ?>" placeholder="auto-generated">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Subtitle / Tagline</label>
                <input class="form-control" name="subtitle" maxlength="255" value="<?= e($row['subtitle'] ?? '') ?>"
                       placeholder="e.g. बेटी के विवाह में सम्मानपूर्वक सहयोग">
                <small class="form-hint">Shown under the title on the scheme page. Hindi is fine.</small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input class="form-control" name="category" value="<?= e($row['category'] ?? '') ?>" placeholder="e.g. Education, Health, Skill">
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input class="form-control" name="department" value="<?= e($row['department'] ?? '') ?>" placeholder="e.g. State Government">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Short Description</label>
                <textarea class="form-textarea" name="short_description" style="min-height:80px;" maxlength="500"><?= e($row['short_description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Full Description</label>
                <textarea class="form-textarea" name="description" style="min-height:180px;"><?= e($row['description'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Eligibility</label>
                    <textarea class="form-textarea" name="eligibility" style="min-height:120px;" placeholder="Who can apply…"><?= e($row['eligibility'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Benefits</label>
                    <textarea class="form-textarea" name="benefits" style="min-height:120px;" placeholder="What applicants get…"><?= e($row['benefits'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Documents Required</label>
                <textarea class="form-textarea" name="documents_required" style="min-height:100px;" placeholder="One document per line…"><?= e($row['documents_required'] ?? '') ?></textarea>
            </div>

            <?php /* ---------------- Project page sections ---------------- */ ?>
            <h3 class="settings-sub" style="margin-top:1.5rem;"><?= lucide('layout-list') ?> Project page sections</h3>
            <p class="form-hint" style="margin-bottom:1rem;">
                Every box below is optional — a section you leave empty simply does not appear on the page.
                Unless noted otherwise, put <strong>one item per line</strong>.
            </p>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Objectives</label>
                    <textarea class="form-textarea" name="objectives" style="min-height:120px;" placeholder="One objective per line…"><?= e($row['objectives'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Selection Process</label>
                    <textarea class="form-textarea" name="process_steps" style="min-height:120px;" placeholder="One step per line, in order…"><?= e($row['process_steps'] ?? '') ?></textarea>
                    <small class="form-hint">Numbered automatically — Step 1, Step 2, …</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Indicative Support Budget</label>
                <textarea class="form-textarea" name="support_items" style="min-height:110px;" placeholder="Household goods | ₹15,000"><?= e($row['support_items'] ?? '') ?></textarea>
                <small class="form-hint">
                    One row per line as <code>Label | Amount</code>. A last row whose label starts with
                    &ldquo;Total&rdquo; or &ldquo;कुल&rdquo; is highlighted as the total.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Budget Note</label>
                <textarea class="form-textarea" name="budget_note" style="min-height:70px;" placeholder="Amounts may vary according to funding…"><?= e($row['budget_note'] ?? '') ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">CSR &amp; Donor Partnership</label>
                    <textarea class="form-textarea" name="partnership" style="min-height:110px;" placeholder="CSR Companies&#10;Individual Donors…"><?= e($row['partnership'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Transparency &amp; Accountability</label>
                    <textarea class="form-textarea" name="transparency" style="min-height:110px;" placeholder="Beneficiary Verification&#10;Utilization Reporting…"><?= e($row['transparency'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Frequently Asked Questions</label>
                <textarea class="form-textarea" name="faq" style="min-height:130px;" placeholder="Who can apply? :: Families found eligible after verification."><?= e($row['faq'] ?? '') ?></textarea>
                <small class="form-hint">One per line as <code>Question :: Answer</code> (two colons).</small>
            </div>

            <div class="form-group">
                <label class="form-label">Important Guidelines &amp; Safeguards</label>
                <textarea class="form-textarea" name="guidelines" style="min-height:130px;" placeholder="Positioning, disclaimers, legal safeguards… HTML allowed."><?= e($row['guidelines'] ?? '') ?></textarea>
                <small class="form-hint">Rich text (HTML) allowed. Shown in a highlighted panel near the bottom of the page.</small>
            </div>

            <?php /* ---------------- Links + dates ---------------- */ ?>
            <h3 class="settings-sub" style="margin-top:1.5rem;"><?= lucide('link') ?> Links &amp; dates</h3>
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Apply URL</label>
                    <input class="form-control" name="apply_url" value="<?= e($row['apply_url'] ?? '') ?>" placeholder="contact  or  https://…">
                    <small class="form-hint">A page slug like <code>contact</code>, or a full URL. Blank sends people to the contact page.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Donate / Support URL</label>
                    <input class="form-control" name="donate_url" value="<?= e($row['donate_url'] ?? '') ?>" placeholder="donate  or  https://…">
                    <small class="form-hint">Adds a second CTA for donors and CSR partners.</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Deadline</label>
                <input class="form-control" type="date" name="deadline" value="<?= e($row['deadline'] ?? '') ?>">
                <small class="form-hint">Leave blank for a rolling, always-open scheme.</small>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Sort Order</label>
                    <input class="form-control" type="number" name="sort_order" value="<?= (int) ($row['sort_order'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?= (($row['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= (($row['status'] ?? '') === 'closed') ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Featured Image</label>
                <input class="form-control" type="file" name="image" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(!empty($row['image']) ? upload_url($row['image']) : asset('images/placeholder.svg')) ?>" alt="preview" style="<?= empty($row['image']) ? 'display:none;' : '' ?>">
            </div>

            <?php /* ---------------- Brochures & downloads ----------------
                     Uploads happen through the main Save, so this block only
                     collects files; removal is a separate POST (_do=drop_file)
                     because a form cannot both save and delete in one submit
                     without turning every checkbox into a destructive action. */ ?>
            <h3 class="settings-sub" style="margin-top:1.5rem;"><?= lucide('file-down') ?> Brochures &amp; downloads</h3>

            <div class="form-group">
                <label class="form-label">Primary Brochure</label>
                <?php if (!empty($row['brochure'])): ?>
                    <div class="sch-file">
                        <?= lucide('file-text') ?>
                        <a href="<?= e(upload_url($row['brochure'])) ?>" target="_blank" rel="noopener"><?= e(basename($row['brochure'])) ?></a>
                        <button class="btn btn-ghost btn-sm sch-file-x" type="submit" form="dropBrochure"
                                data-confirm="Remove the primary brochure?"><?= lucide('trash-2') ?> Remove</button>
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" name="brochure" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                <small class="form-hint">PDF, DOC/DOCX or an image, up to 5&nbsp;MB. Uploading a new file replaces the current one.</small>
            </div>

            <?php $extras = scheme_brochures($row ?: null); ?>
            <div class="form-group">
                <label class="form-label">Additional Downloads</label>
                <?php if ($extras): ?>
                    <div class="sch-files">
                        <?php foreach ($extras as $i => $b): ?>
                            <div class="sch-file">
                                <?= lucide('paperclip') ?>
                                <a href="<?= e(upload_url($b['path'])) ?>" target="_blank" rel="noopener"><?= e($b['label']) ?></a>
                                <?php if ($b['size'] > 0): ?><span class="muted"><?= e(human_filesize($b['size'])) ?></span><?php endif; ?>
                                <button class="btn btn-ghost btn-sm sch-file-x" type="submit" form="dropExtra<?= (int) $i ?>"
                                        data-confirm="Remove this download?"><?= lucide('trash-2') ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <input class="form-control" type="file" name="brochures[]" multiple
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" data-sch-multi>
                <small class="form-hint">
                    Select one or more files — application form, guidelines, reports. Up to 12 in total.
                    Names are taken from the filenames; edit them below after choosing.
                </small>
                <div class="sch-labels" data-sch-labels hidden></div>
            </div>

            <label class="checkbox"><input type="checkbox" name="is_featured" value="1" <?= !empty($row['is_featured']) ? 'checked' : '' ?>> Feature this scheme</label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Create' ?> Scheme</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('schemes')) ?>">Cancel</a>
            </div>
        </form>
    </div>

    <?php /* Removal posts, kept OUTSIDE the editor form — HTML forbids nesting
             forms, so the buttons above reference these by id instead. Each is
             its own CSRF-guarded action, so a delete can never ride along on a
             normal save. Only rendered when editing an existing row. */ ?>
    <?php if ($action === 'edit'): ?>
        <?php if (!empty($row['brochure'])): ?>
            <form id="dropBrochure" method="post" action="<?= e(admin_url('schemes')) ?>" class="hidden-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="drop_file">
                <input type="hidden" name="what" value="brochure">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            </form>
        <?php endif; ?>
        <?php foreach ($extras as $i => $b): ?>
            <form id="dropExtra<?= (int) $i ?>" method="post" action="<?= e(admin_url('schemes')) ?>" class="hidden-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="drop_file">
                <input type="hidden" name="what" value="extra">
                <input type="hidden" name="index" value="<?= (int) $i ?>">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
            </form>
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        .hidden-form { display: none; }
        .sch-files { display: grid; gap: .4rem; margin-bottom: .6rem; }
        .sch-file {
            display: flex; align-items: center; gap: .5rem;
            padding: .5rem .7rem; border: 1px solid var(--line, #e5e7eb); border-radius: 9px;
            font-size: .88rem; margin-bottom: .5rem;
        }
        .sch-file a { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .sch-file-x { flex: 0 0 auto; }
        .sch-labels { display: grid; gap: .4rem; margin-top: .5rem; }
        .sch-labels input { font-size: .88rem; }
    </style>

    <script>
    /* When files are chosen for "Additional Downloads", offer a label box per
       file, pre-filled with the filename. The inputs are created in the same
       order as the file list, so brochure_labels[i] lines up with brochures[i]
       in the handler. */
    (function () {
        var input = document.querySelector('[data-sch-multi]');
        var wrap  = document.querySelector('[data-sch-labels]');
        if (!input || !wrap) { return; }
        input.addEventListener('change', function () {
            wrap.innerHTML = '';
            var files = Array.prototype.slice.call(input.files || []);
            wrap.hidden = files.length === 0;
            files.forEach(function (f, i) {
                var box = document.createElement('input');
                box.type = 'text';
                box.className = 'form-control';
                box.name = 'brochure_labels[]';
                box.maxLength = 120;
                box.value = f.name.replace(/\.[^.]+$/, '');
                box.setAttribute('aria-label', 'Label for ' + f.name);
                wrap.appendChild(box);
            });
        });
    })();
    </script>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Schemes & Initiatives';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', title, category, department) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id DESC", $params, 12);

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Schemes &amp; Initiatives</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('schemes?action=create')) ?>">+ Add Scheme</a>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('schemes')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search schemes…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Deadline</th><th>Status</th><th>Featured</th><th>Order</th><th style="text-align:right;">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <tr>
                        <td><img class="thumb" src="<?= e(!empty($r['image']) ? upload_url($r['image']) : asset('images/placeholder.svg')) ?>" alt=""></td>
                        <td>
                            <strong><?= e($r['title']) ?></strong><br>
                            <small class="text-muted"><?= e(excerpt($r['short_description'] ?? '', 12)) ?></small>
                        </td>
                        <td><?= !empty($r['category']) ? e($r['category']) : '—' ?></td>
                        <td><?= !empty($r['deadline']) ? e(format_date($r['deadline'], 'd M Y')) : '—' ?></td>
                        <td><span class="pill <?= $r['status'] === 'active' ? 'pill-green' : 'pill-red' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                        <td><?= !empty($r['is_featured']) ? lucide('star') : '—' ?></td>
                        <td><?= (int) $r['sort_order'] ?></td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('schemes?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                <form method="post" action="<?= e(admin_url('schemes')) ?>" data-confirm="Delete this scheme permanently?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('clipboard-list') ?></div>No schemes yet. <a href="<?= e(admin_url('schemes?action=create')) ?>">Add your first scheme</a>.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
