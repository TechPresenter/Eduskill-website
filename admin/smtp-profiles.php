<?php
/**
 * Admin — SMTP Profiles (SendGrid / Mailgun / Amazon SES / custom).
 * Multiple sending profiles; campaigns pick one. Standard CRUD.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'email_smtp_profiles';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$presets = [
    'sendgrid' => ['smtp.sendgrid.net', 587, 'tls'],
    'mailgun'  => ['smtp.mailgun.org', 587, 'tls'],
    'ses'      => ['email-smtp.us-east-1.amazonaws.com', 587, 'tls'],
    'custom'   => ['', 587, 'tls'],
];

if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $errors = validate($_POST, ['name' => 'required|max:128', 'host' => 'required|max:191', 'from_email' => 'required|email']);
    if ($errors) { set_flash('error', reset($errors)); redirect('/admin/smtp-profiles?action=' . ($editId ? 'edit&id=' . $editId : 'create')); }

    $data = [
        'name'       => clean(post('name')),
        'provider'   => in_array(post('provider'), array_keys($presets), true) ? post('provider') : 'custom',
        'host'       => clean(post('host')),
        'port'       => (int) post('port', 587),
        'secure'     => in_array(post('secure'), ['none', 'tls', 'ssl'], true) ? post('secure') : 'tls',
        'username'   => clean(post('username', '')),
        'from_email' => strtolower(clean(post('from_email'))),
        'from_name'  => clean(post('from_name', '')),
        'status'     => post('status') ? 1 : 0,
        'is_default' => post('is_default') ? 1 : 0,
    ];
    // Only overwrite password when a new one is typed.
    $pass = (string) post('password', '');
    if ($pass !== '') { $data['password'] = $pass; }

    if ($data['is_default']) { db_query("UPDATE $table SET is_default = 0"); }

    if ($editId) {
        db_update($table, $data, 'id = :id', [':id' => $editId]);
        set_flash('success', 'SMTP profile updated.');
    } else {
        db_insert($table, $data);
        set_flash('success', 'SMTP profile created.');
    }
    log_activity('update', 'smtp-profiles', 'Saved SMTP profile');
    redirect('/admin/smtp-profiles');
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    db_delete($table, 'id = :id', [':id' => (int) post('id', 0)]);
    set_flash('success', 'Profile deleted.');
    redirect('/admin/smtp-profiles');
}

if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Not found.'); redirect('/admin/smtp-profiles'); }
    $page_title = $action === 'edit' ? 'Edit SMTP Profile' : 'Add SMTP Profile';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($page_title) ?></h1><span class="muted">Email Marketing / SMTP Profiles</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('smtp-profiles')) ?>">← Back</a></div>
    <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url('smtp-profiles')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Name <span class="req">*</span></label><input class="form-control" name="name" required value="<?= e($row['name'] ?? '') ?>" placeholder="SendGrid – transactional"></div>
            <div class="form-group"><label class="form-label">Provider</label><select class="form-select" name="provider">
                <?php foreach (['sendgrid' => 'SendGrid', 'mailgun' => 'Mailgun', 'ses' => 'Amazon SES', 'custom' => 'Custom'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= ($row['provider'] ?? 'custom') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Host <span class="req">*</span></label><input class="form-control" name="host" required value="<?= e($row['host'] ?? '') ?>" placeholder="smtp.sendgrid.net"></div>
            <div class="form-group"><label class="form-label">Port</label><input class="form-control" type="number" name="port" value="<?= (int) ($row['port'] ?? 587) ?>"></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Encryption</label><select class="form-select" name="secure">
                <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= ($row['secure'] ?? 'tls') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="form-group"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($row['username'] ?? '') ?>" autocomplete="off"></div>
        </div>
        <div class="form-group"><label class="form-label">Password / API key</label><input class="form-control" type="password" name="password" autocomplete="new-password" placeholder="<?= !empty($row['password']) ? '•••••• (unchanged)' : '' ?>"></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">From email <span class="req">*</span></label><input class="form-control" type="email" name="from_email" required value="<?= e($row['from_email'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">From name</label><input class="form-control" name="from_name" value="<?= e($row['from_name'] ?? '') ?>"></div>
        </div>
        <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!array_key_exists('status', (array) $row) || !empty($row['status'])) ? 'checked' : '' ?>> Active</label>
        <label class="checkbox"><input type="checkbox" name="is_default" value="1" <?= !empty($row['is_default']) ? 'checked' : '' ?>> Default profile</label>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Save Profile</button><a class="btn btn-ghost" href="<?= e(admin_url('smtp-profiles')) ?>">Cancel</a></div>
    </form></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

$page_title = 'SMTP Profiles';
$rows = db_all("SELECT * FROM $table ORDER BY is_default DESC, id DESC");
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>SMTP Profiles</h1><span class="muted"><?= count($rows) ?> profiles</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('smtp-profiles?action=create')) ?>">+ Add Profile</a></div>
<div class="panel"><div class="panel-body">
<?php if ($rows): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Name</th><th>Host</th><th>From</th><th>Default</th><th>Status</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><strong><?= e($r['name']) ?></strong><br><small class="text-muted"><?= e(ucfirst($r['provider'])) ?></small></td>
            <td><?= e($r['host']) ?>:<?= (int) $r['port'] ?> <small class="text-muted">(<?= e($r['secure']) ?>)</small></td>
            <td><small><?= e($r['from_name']) ?> &lt;<?= e($r['from_email']) ?>&gt;</small></td>
            <td><?= !empty($r['is_default']) ? lucide('star') : '—' ?></td>
            <td><span class="pill <?= !empty($r['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($r['status']) ? 'Active' : 'Off' ?></span></td>
            <td><div class="actions">
                <a class="icon-btn" href="<?= e(admin_url('smtp-profiles?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                <form method="post" action="<?= e(admin_url('smtp-profiles')) ?>" data-confirm="Delete this profile?" style="display:inline;">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                </form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
<?php else: ?><div class="empty-state"><div class="icon"><?= lucide('server') ?></div>No SMTP profiles yet. The site mailer settings are used until you add one.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
