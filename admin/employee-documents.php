<?php
/**
 * =============================================================================
 *  Admin — Employee Documents (appointment letters, agreements, ID proofs)
 *  Scoped to one employee via ?employee=<id>.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$empId = (int) request('employee', 0);
$emp   = $empId ? find('employees', $empId) : null;
if (!$emp) { set_flash('error', 'Select an employee first.'); redirect('/admin/employees'); }

$docTypes = employee_doc_labels();
$redir    = '?employee=' . $empId;

/* -------------------------------------------------------------- UPLOAD */
if (is_post() && post('_do') === 'upload') {
    require_csrf();
    $title = clean(post('title'));
    $type  = isset($docTypes[(string) post('doc_type')]) ? post('doc_type') : 'other';
    if (empty($_FILES['file']['name'])) {
        set_flash('error', 'Please choose a file to upload.');
        redirect('/admin/employee-documents' . $redir);
    }
    $up = upload_document($_FILES['file'], 'documents');
    if (!$up['success']) {
        set_flash('error', $up['error']);
        redirect('/admin/employee-documents' . $redir);
    }
    if ($title === '') { $title = $docTypes[$type]; }
    db_insert('employee_documents', [
        'employee_id' => $empId,
        'doc_type'    => $type,
        'title'       => $title,
        'file'        => $up['path'],
    ]);
    log_activity('create', 'employee_documents', 'Uploaded "' . $title . '" for employee #' . $empId);
    set_flash('success', 'Document uploaded.');
    redirect('/admin/employee-documents' . $redir);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $docId = (int) post('id', 0);
    $doc   = find('employee_documents', $docId);
    if ($doc && (int) $doc['employee_id'] === $empId) {
        delete_upload($doc['file']);
        db_delete('employee_documents', 'id = :id', [':id' => $docId]);
        log_activity('delete', 'employee_documents', 'Deleted document #' . $docId . ' for employee #' . $empId);
        set_flash('success', 'Document deleted.');
    }
    redirect('/admin/employee-documents' . $redir);
}

$docs = db_all("SELECT * FROM employee_documents WHERE employee_id = :e ORDER BY uploaded_at DESC", [':e' => $empId]);

$page_title = 'Documents — ' . $emp['name'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Documents</h1><span class="muted"><?= e($emp['employee_code']) ?> · <?= e($emp['name']) ?> · <?= count($docs) ?> file(s)</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('employees?action=edit&id=' . $empId)) ?>">&larr; Back to employee</a>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Upload document</h3>
        <form class="admin-form" method="post" action="<?= e(admin_url('employee-documents')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="upload">
            <input type="hidden" name="employee" value="<?= (int) $empId ?>">
            <div class="form-group"><label class="form-label">Type</label>
                <select class="form-select" name="doc_type">
                    <?php foreach ($docTypes as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?>
                </select></div>
            <div class="form-group"><label class="form-label">Title</label>
                <input class="form-control" name="title" placeholder="Optional — defaults to the type"></div>
            <div class="form-group"><label class="form-label">File <span class="req">*</span></label>
                <input class="form-control" type="file" name="file" required>
                <small class="form-hint">PDF, images or documents.</small></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('upload') ?> Upload</button></div>
        </form>
    </div></div>

    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Stored documents</h3>
        <?php if ($docs): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Document</th><th>Type</th><th>Uploaded</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
                <tr>
                    <td><strong><?= e($d['title']) ?></strong></td>
                    <td><span class="pill pill-blue"><?= e($docTypes[$d['doc_type']] ?? $d['doc_type']) ?></span></td>
                    <td><?= e(format_date($d['uploaded_at'])) ?></td>
                    <td><div class="actions">
                        <a class="icon-btn" href="<?= e(secure_upload_url($d['file'])) ?>" target="_blank" rel="noopener" title="View / download"><?= lucide('download') ?></a>
                        <form method="post" action="<?= e(admin_url('employee-documents')) ?>" data-confirm="Delete this document?" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="employee" value="<?= (int) $empId ?>"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                            <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('folder') ?></div>No documents uploaded yet.</div>
        <?php endif; ?>
    </div></div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
