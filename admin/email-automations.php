<?php
/**
 * Admin — Email Automations. Toggle & edit the trigger emails:
 * welcome, donation receipt, birthday, membership expiry, inactivity.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'email_automations';
$action = get('action', 'list');
$id     = (int) get('id', 0);

if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    db_update($table, [
        'subject'     => clean(post('subject')),
        'body'        => post('body', ''),
        'enabled'     => post('enabled') ? 1 : 0,
        'offset_days' => (int) post('offset_days', 0),
    ], 'id = :id', [':id' => $editId]);
    log_activity('update', 'email-automations', 'Updated automation #' . $editId);
    set_flash('success', 'Automation saved.');
    redirect('/admin/email-automations');
}
if (is_post() && post('_do') === 'toggle') {
    require_csrf();
    $row = find($table, (int) post('id', 0));
    if ($row) { db_update($table, ['enabled' => $row['enabled'] ? 0 : 1], 'id = :id', [':id' => (int) $row['id']]); }
    redirect('/admin/email-automations');
}

if ($action === 'edit') {
    $row = find($table, $id);
    if (!$row) { set_flash('error', 'Not found.'); redirect('/admin/email-automations'); }
    $hasOffset = in_array($row['trigger_key'], ['membership_expiry', 'inactivity'], true);
    $page_title = 'Edit Automation';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($row['name']) ?></h1><span class="muted">Email Marketing / Automations · <code><?= e($row['trigger_key']) ?></code></span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('email-automations')) ?>">← Back</a></div>
    <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url('email-automations')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
        <label class="checkbox"><input type="checkbox" name="enabled" value="1" <?= !empty($row['enabled']) ? 'checked' : '' ?>> Enabled</label>
        <div class="form-group"><label class="form-label">Subject</label><input class="form-control" name="subject" value="<?= e($row['subject']) ?>"></div>
        <?php if ($hasOffset): ?>
        <div class="form-group"><label class="form-label">Trigger offset (days)</label><input class="form-control" type="number" name="offset_days" value="<?= (int) $row['offset_days'] ?>">
            <span class="form-hint"><?= $row['trigger_key'] === 'membership_expiry' ? 'Days BEFORE expiry to send the reminder.' : 'Days of inactivity before the re-engagement email.' ?></span></div>
        <?php endif; ?>
        <div class="form-group"><label class="form-label">Body (HTML)</label>
            <textarea class="form-textarea" name="body" data-wysiwyg style="min-height:240px;"><?= e($row['body']) ?></textarea>
            <span class="form-hint">Merge tags: <code>{{name}}</code>, <code>{{site_name}}</code>, and trigger-specific ones like <code>{{amount}}</code>, <code>{{expiry_date}}</code>.</span></div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
    </form></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

$page_title = 'Email Automations';
$rows = db_all("SELECT * FROM $table ORDER BY id ASC");
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Email Automations</h1><span class="muted">Trigger-based emails</span></div></div>
<div class="panel"><div class="panel-body">
    <div class="alert alert-info"><?= lucide('info') ?> <strong>Welcome</strong> and <strong>Donation receipt</strong> fire instantly on the action. <strong>Birthday</strong>, <strong>Expiry</strong> and <strong>Inactivity</strong> run from the daily cron (<code>admin/cron-email.php</code>).</div>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Automation</th><th>Trigger</th><th>When</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e(excerpt($r['subject'], 10)) ?></small></td>
            <td><code><?= e($r['trigger_key']) ?></code></td>
            <td><small class="text-muted"><?= in_array($r['trigger_key'], ['membership_expiry', 'inactivity'], true) ? (int) $r['offset_days'] . ' days' : 'Immediate' ?></small></td>
            <td>
                <form method="post" action="<?= e(admin_url('email-automations')) ?>" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="pill <?= $r['enabled'] ? 'pill-green' : 'pill-gray' ?>" type="submit" style="border:0;cursor:pointer;"><?= $r['enabled'] ? 'On' : 'Off' ?></button></form>
            </td>
            <td style="text-align:right;"><a class="icon-btn" href="<?= e(admin_url('email-automations?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
