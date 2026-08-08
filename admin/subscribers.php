<?php
/**
 * Admin — Subscribers. Manage the newsletter list: search/segment, edit tags,
 * CSV import + export, unsubscribe/resubscribe. Feeds email campaign segments.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'newsletter_subscribers';

/* ---- CSV export (segment-aware) ---- */
if (get('export') === 'csv') {
    $where = '1=1'; $params = [];
    if (in_array(get('status'), ['subscribed', 'unsubscribed'], true)) { $where .= ' AND status = :st'; $params[':st'] = get('status'); }
    if (($tag = trim((string) get('tag', ''))) !== '') { $where .= ' AND tags LIKE :tg'; $params[':tg'] = '%' . $tag . '%'; }
    $rows = db_all("SELECT name, email, tags, status, created_at FROM $table WHERE $where ORDER BY id DESC", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'email', 'tags', 'status', 'created_at']);
    foreach ($rows as $r) { fputcsv($out, [$r['name'], $r['email'], $r['tags'], $r['status'], $r['created_at']]); }
    fclose($out);
    exit;
}

/* ---- CSV import ---- */
if (is_post() && post('_do') === 'import') {
    require_csrf();
    $added = 0; $updated = 0;
    if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $tags = clean(post('import_tags', ''));
        $fh = fopen($_FILES['csv']['tmp_name'], 'r');
        $header = fgetcsv($fh);
        $cols = array_map(static fn ($h) => strtolower(trim((string) $h)), $header ?: []);
        $iEmail = array_search('email', $cols, true);
        $iName  = array_search('name', $cols, true);
        $iTags  = array_search('tags', $cols, true);
        if ($iEmail === false) { $iEmail = 0; } // assume first column is email
        while (($r = fgetcsv($fh)) !== false) {
            $email = strtolower(trim((string) ($r[$iEmail] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
            $name = $iName !== false ? trim((string) ($r[$iName] ?? '')) : '';
            $rowTags = $iTags !== false ? trim((string) ($r[$iTags] ?? '')) : '';
            $rowTags = trim($rowTags . ($rowTags && $tags ? ',' : '') . $tags, ',');
            $existing = find_by($table, 'email', $email);
            if ($existing) {
                db_update($table, ['name' => $name ?: $existing['name'], 'tags' => $rowTags ?: $existing['tags']], 'id = :id', [':id' => (int) $existing['id']]);
                $updated++;
            } else {
                db_insert($table, ['name' => $name ?: null, 'email' => $email, 'tags' => $rowTags ?: null, 'token' => bin2hex(random_bytes(16)), 'status' => 'subscribed']);
                $added++;
            }
        }
        fclose($fh);
    }
    log_activity('import', 'subscribers', "Imported subscribers (+$added, ~$updated)");
    set_flash('success', "Import complete: $added added, $updated updated.");
    redirect('/admin/subscribers');
}

/* ---- Row actions ---- */
if (is_post() && post('_do') === 'tags') {
    require_csrf();
    db_update($table, ['tags' => clean(post('tags', '')) ?: null], 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Tags updated.');
    redirect('/admin/subscribers');
}
if (is_post() && post('_do') === 'toggle') {
    require_csrf();
    $row = find($table, (int) post('id', 0));
    if ($row) { db_update($table, ['status' => $row['status'] === 'subscribed' ? 'unsubscribed' : 'subscribed'], 'id = :id', [':id' => (int) $row['id']]); }
    redirect('/admin/subscribers');
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    db_delete($table, 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Subscriber deleted.');
    redirect('/admin/subscribers');
}

/* ---- List ---- */
$page_title = 'Subscribers';
$status = in_array(get('status'), ['subscribed', 'unsubscribed'], true) ? get('status') : 'all';
$tag    = trim((string) get('tag', ''));
$q      = trim((string) get('q', ''));
$where  = '1=1'; $params = [];
if ($status !== 'all') { $where .= ' AND status = :st'; $params[':st'] = $status; }
if ($tag !== '')       { $where .= ' AND tags LIKE :tg'; $params[':tg'] = '%' . $tag . '%'; }
// Distinct placeholder per slot — PDO EMULATE_PREPARES=false binds each name once.
if ($q !== '')         { $where .= ' AND (email LIKE :q1 OR name LIKE :q2)'; $params[':q1'] = $params[':q2'] = '%' . $q . '%'; }

$counts = [
    'all'          => (int) db_value("SELECT COUNT(*) FROM $table"),
    'subscribed'   => (int) db_value("SELECT COUNT(*) FROM $table WHERE status='subscribed'"),
    'unsubscribed' => (int) db_value("SELECT COUNT(*) FROM $table WHERE status='unsubscribed'"),
];
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 20);
$expQs = http_build_query(array_filter(['export' => 'csv', 'status' => $status !== 'all' ? $status : null, 'tag' => $tag ?: null]));

include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Subscribers</h1><span class="muted"><?= number_format($counts['subscribed']) ?> subscribed · <?= number_format($counts['unsubscribed']) ?> unsubscribed</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('subscribers?' . $expQs)) ?>"><?= lucide('download') ?> Export CSV</a>
</div>

<div class="panel"><div class="panel-body">
    <form class="admin-form" method="post" action="<?= e(admin_url('subscribers')) ?>" enctype="multipart/form-data" style="display:flex;gap:.6rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1rem;">
        <?= csrf_field() ?><input type="hidden" name="_do" value="import">
        <div class="form-group" style="margin:0;"><label class="form-label">Import CSV (email,name,tags)</label><input class="form-control" type="file" name="csv" accept=".csv" required></div>
        <div class="form-group" style="margin:0;"><label class="form-label">Add tags to all</label><input class="form-control" name="import_tags" placeholder="donor, event-2026"></div>
        <button class="btn btn-primary" type="submit"><?= lucide('upload') ?> Import</button>
    </form>

    <div class="data-toolbar">
        <form class="search" method="get" action="<?= e(admin_url('subscribers')) ?>" style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search email or name…">
            <input class="form-control" name="tag" value="<?= e($tag) ?>" placeholder="tag" style="max-width:140px;">
            <select class="form-select" name="status" style="max-width:150px;">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= $counts['all'] ?>)</option>
                <option value="subscribed" <?= $status === 'subscribed' ? 'selected' : '' ?>>Subscribed</option>
                <option value="unsubscribed" <?= $status === 'unsubscribed' ? 'selected' : '' ?>>Unsubscribed</option>
            </select>
            <button class="btn btn-secondary" type="submit"><?= lucide('search') ?></button>
        </form>
    </div>

    <?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>Name</th><th>Tags</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($p['items'] as $r): ?>
        <tr>
            <td><strong><?= e($r['email']) ?></strong></td>
            <td><?= e($r['name'] ?: '—') ?></td>
            <td>
                <form method="post" action="<?= e(admin_url('subscribers')) ?>" style="display:flex;gap:.3rem;">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="tags"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <input class="form-control" name="tags" value="<?= e($r['tags'] ?? '') ?>" style="max-width:180px;font-size:.8rem;padding:.3rem .5rem;" placeholder="add tags">
                    <button class="icon-btn" type="submit" title="Save tags"><?= lucide('check') ?></button>
                </form>
            </td>
            <td><span class="pill <?= $r['status'] === 'subscribed' ? 'pill-green' : 'pill-gray' ?>"><?= e($r['status']) ?></span></td>
            <td><div class="actions">
                <form method="post" action="<?= e(admin_url('subscribers')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="icon-btn" type="submit" title="Toggle subscription"><?= lucide($r['status'] === 'subscribed' ? 'user-minus' : 'user-plus') ?></button></form>
                <form method="post" action="<?= e(admin_url('subscribers')) ?>" data-confirm="Delete this subscriber?" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= $p['links'] ?>
    <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('users') ?></div>No subscribers match.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
