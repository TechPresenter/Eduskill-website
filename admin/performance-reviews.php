<?php
/**
 * =============================================================================
 *  Admin — Performance Reviews (quarterly / annual: goals, achievements, rating)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table   = 'performance_reviews';
$action  = get('action', 'list');
$id      = (int) get('id', 0);
$periods = review_period_labels();
$empOpts = employee_options('');

/* -------------------------------------------------------------- SAVE */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    if ($editId && !find($table, $editId)) { set_flash('error', 'Review not found.'); redirect('/admin/performance-reviews'); }

    $empId = (int) post('employee_id', 0);
    $label = clean(post('period_label'));
    if (!isset($empOpts[$empId]) || $label === '') {
        flash_old($_POST);
        set_flash('error', 'Employee and a period label are required.');
        redirect('/admin/performance-reviews?action=' . ($editId ? 'edit&id=' . $editId : 'create'));
    }
    $rating = (int) post('rating', 3);
    $rating = max(1, min(5, $rating));

    $data = [
        'employee_id'  => $empId,
        'period_type'  => isset($periods[(string) post('period_type')]) ? post('period_type') : 'quarterly',
        'period_label' => $label,
        'review_date'  => clean(post('review_date')) ?: null,
        'rating'       => $rating,
        'goals'        => clean(post('goals')) ?: null,
        'achievements' => clean(post('achievements')) ?: null,
        'feedback'     => clean(post('feedback')) ?: null,
        'reviewer'     => clean(post('reviewer')) ?: null,
        'status'       => post('status') === 'draft' ? 'draft' : 'final',
    ];
    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        log_activity('update', 'performance_reviews', 'Updated review #' . $editId);
        set_flash('success', 'Review updated.');
    } else {
        $newId = db_insert($table, $data);
        log_activity('create', 'performance_reviews', 'Created review #' . $newId . ' for employee #' . $empId);
        set_flash('success', 'Performance review saved.');
    }
    redirect('/admin/performance-reviews');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $rid = (int) post('id', 0);
    if (find($table, $rid)) {
        db_delete($table, 'id = :id', [':id' => $rid]);
        log_activity('delete', 'performance_reviews', 'Deleted review #' . $rid);
        set_flash('success', 'Review deleted.');
    }
    redirect('/admin/performance-reviews');
}

/* -------------------------------------------------------------- FORM */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Review not found.'); redirect('/admin/performance-reviews'); }
    $o = static fn(string $k, $d = '') => e(old($k, $row[$k] ?? $d));
    $preEmp = (int) request('employee', $row['employee_id'] ?? 0);

    $page_title = $action === 'edit' ? 'Edit Review' : 'New Performance Review';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head">
        <div><h1><?= e($page_title) ?></h1><span class="muted">Performance / <?= $action === 'edit' ? 'Edit' : 'Create' ?></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('performance-reviews')) ?>">&larr; Back to list</a>
    </div>
    <form class="admin-form" method="post" action="<?= e(admin_url('performance-reviews')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="panel" style="max-width:820px;"><div class="panel-body">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Employee <span class="req">*</span></label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">Select employee…</option>
                        <?php foreach ($empOpts as $eid => $label): ?><option value="<?= (int) $eid ?>" <?= (int) old('employee_id', $preEmp) === (int) $eid ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Reviewer</label>
                    <input class="form-control" name="reviewer" value="<?= $o('reviewer') ?>" placeholder="e.g. Line manager"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Period type</label>
                    <select class="form-select" name="period_type">
                        <?php foreach ($periods as $k => $l): ?><option value="<?= $k ?>" <?= ($row['period_type'] ?? 'quarterly') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label class="form-label">Period label <span class="req">*</span></label>
                    <input class="form-control" name="period_label" value="<?= $o('period_label') ?>" placeholder="e.g. Q2 2026 / FY 2025-26" required></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Review date</label>
                    <input class="form-control" type="date" name="review_date" value="<?= e(old('review_date', $row['review_date'] ?? date('Y-m-d'))) ?>"></div>
                <div class="form-group"><label class="form-label">Overall rating</label>
                    <select class="form-select" name="rating">
                        <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>" <?= (int) ($row['rating'] ?? 3) === $i ? 'selected' : '' ?>><?= $i ?> / 5 — <?= ['', 'Needs improvement', 'Below expectations', 'Meets expectations', 'Exceeds expectations', 'Outstanding'][$i] ?></option><?php endfor; ?>
                    </select></div>
            </div>
            <div class="form-group"><label class="form-label">Goals</label><textarea class="form-textarea" name="goals" style="min-height:90px;"><?= $o('goals') ?></textarea></div>
            <div class="form-group"><label class="form-label">Achievements</label><textarea class="form-textarea" name="achievements" style="min-height:90px;"><?= $o('achievements') ?></textarea></div>
            <div class="form-group"><label class="form-label">Feedback</label><textarea class="form-textarea" name="feedback" style="min-height:90px;"><?= $o('feedback') ?></textarea></div>
            <div class="form-group"><label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="final" <?= ($row['status'] ?? 'final') === 'final' ? 'selected' : '' ?>>Final</option>
                    <option value="draft" <?= ($row['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                </select></div>
            <div class="form-actions mt-3"><button class="btn btn-primary" type="submit"><?= $action === 'edit' ? 'Update' : 'Save' ?> review</button>
                <a class="btn btn-ghost" href="<?= e(admin_url('performance-reviews')) ?>">Cancel</a></div>
        </div></div>
    </form>
    <?php clear_old(); include __DIR__ . '/partials/foot.php'; exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Performance Reviews';
$search     = trim((string) get('q', ''));
$where = '1=1'; $params = [];
if ($search !== '') { $where .= " AND CONCAT_WS(' ', e.name, e.employee_code, r.period_label) LIKE :q"; $params[':q'] = '%' . $search . '%'; }

$p = paginate(
    "SELECT r.*, e.name AS emp_name, e.employee_code
       FROM performance_reviews r
       JOIN employees e ON e.id = r.employee_id
      WHERE $where
      ORDER BY r.review_date DESC, r.id DESC",
    $params, 20
);
$avg = (float) db_value("SELECT AVG(rating) FROM performance_reviews");
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Performance Reviews</h1><span class="muted"><?= (int) db_count('performance_reviews', '') ?> reviews · avg rating <?= $avg ? number_format($avg, 1) : '—' ?>/5</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('performance-reviews?action=create')) ?>">+ New Review</a>
</div>

<div class="panel"><div class="panel-body">
    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('performance-reviews')) ?>">
            <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search employee or period…"></form>
        <?php if ($search !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('performance-reviews')) ?>">Clear</a><?php endif; ?>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table">
        <thead><tr><th>Employee</th><th>Period</th><th>Rating</th><th>Reviewer</th><th>Date</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($p['items'] as $r): ?>
            <tr>
                <td><strong><?= e($r['emp_name']) ?></strong><br><small class="text-muted"><?= e($r['employee_code']) ?></small></td>
                <td><span class="pill pill-gray"><?= e($periods[$r['period_type']] ?? $r['period_type']) ?></span> <?= e($r['period_label']) ?><?= $r['status'] === 'draft' ? ' <span class="pill pill-amber">Draft</span>' : '' ?></td>
                <td><?= star_rating((int) $r['rating']) ?></td>
                <td><?= $r['reviewer'] ? e($r['reviewer']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= $r['review_date'] ? e(format_date($r['review_date'])) : '<span class="text-muted">—</span>' ?></td>
                <td><div class="actions">
                    <a class="icon-btn" href="<?= e(admin_url('performance-reviews?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                    <form method="post" action="<?= e(admin_url('performance-reviews')) ?>" data-confirm="Delete this review?" style="display:inline;">
                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?>
        <div class="empty-state"><div class="icon"><?= lucide('star') ?></div>No reviews yet. <a href="<?= e(admin_url('performance-reviews?action=create')) ?>">Create one</a>.</div>
    <?php endif; ?>
</div></div>

<?php include __DIR__ . '/partials/foot.php'; ?>
