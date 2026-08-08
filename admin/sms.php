<?php
/**
 * Admin — SMS Gateway (MSG91 / Twilio): configuration, test send, delivery log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$keys = ['sms_enabled', 'sms_provider', 'msg91_authkey', 'msg91_sender', 'msg91_route', 'msg91_dlt_template', 'twilio_sid', 'twilio_token', 'twilio_from'];

if (is_post() && post('_do') === 'save') {
    require_csrf();
    set_setting('sms_enabled', post('sms_enabled') ? '1' : '0', 'messaging', 'boolean');
    set_setting('sms_provider', in_array(post('sms_provider'), ['msg91', 'twilio'], true) ? post('sms_provider') : 'msg91', 'messaging', 'text');
    foreach (['msg91_authkey', 'msg91_sender', 'msg91_route', 'msg91_dlt_template', 'twilio_sid', 'twilio_token', 'twilio_from'] as $k) {
        set_setting($k, trim((string) post($k, '')), 'messaging', 'text');
    }
    log_activity('update', 'settings', 'Updated SMS settings');
    set_flash('success', 'SMS settings saved.');
    redirect('/admin/sms');
}
if (is_post() && post('_do') === 'test') {
    require_csrf();
    $to = trim((string) post('test_to', ''));
    $msg = trim((string) post('test_msg', 'Test SMS from ' . get_setting('site_name', SITE_NAME)));
    if ($to === '') { set_flash('error', 'Enter a recipient number.'); redirect('/admin/sms'); }
    $r = sms_send($to, $msg);
    set_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Test SMS sent.' : ('SMS failed: ' . ($r['error'] ?? 'unknown')));
    redirect('/admin/sms');
}

$v = [];
foreach ($keys as $k) { $v[$k] = (string) get_setting($k, ''); }
$log = db_all("SELECT * FROM messaging_log WHERE channel='sms' ORDER BY id DESC LIMIT 15");

$page_title = 'SMS Gateway';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>SMS Gateway</h1><span class="muted">Messaging &amp; Push / MSG91 &amp; Twilio</span></div>
    <span class="pill <?= $v['sms_enabled'] ? 'pill-green' : 'pill-gray' ?>"><?= $v['sms_enabled'] ? 'Enabled' : 'Disabled' ?></span>
</div>

<div class="grid grid-2" style="align-items:start;gap:1.5rem;">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('settings') ?> Configuration</h3></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('sms')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save">
            <label class="checkbox"><input type="checkbox" name="sms_enabled" value="1" <?= $v['sms_enabled'] ? 'checked' : '' ?>> Enable SMS sending</label>
            <div class="form-group"><label class="form-label">Provider</label>
                <select class="form-select" name="sms_provider">
                    <option value="msg91" <?= $v['sms_provider'] !== 'twilio' ? 'selected' : '' ?>>MSG91 (India / DLT)</option>
                    <option value="twilio" <?= $v['sms_provider'] === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                </select>
            </div>
            <fieldset style="border:1px solid var(--border);border-radius:10px;padding:.8rem 1rem;margin:.5rem 0;">
                <legend style="font-weight:700;font-size:.85rem;">MSG91</legend>
                <div class="form-group"><label class="form-label">Auth Key</label><input class="form-control" name="msg91_authkey" value="<?= e($v['msg91_authkey']) ?>" autocomplete="off"></div>
                <div class="grid-2">
                    <div class="form-group"><label class="form-label">Sender ID</label><input class="form-control" name="msg91_sender" value="<?= e($v['msg91_sender']) ?>" placeholder="PWFNGO"></div>
                    <div class="form-group"><label class="form-label">Route</label><input class="form-control" name="msg91_route" value="<?= e($v['msg91_route']) ?>" placeholder="4"></div>
                </div>
                <div class="form-group"><label class="form-label">DLT Template ID</label><input class="form-control" name="msg91_dlt_template" value="<?= e($v['msg91_dlt_template']) ?>"><span class="form-hint">Required by MSG91's v5 Flow API. The message is passed as the <code>message</code> variable.</span></div>
            </fieldset>
            <fieldset style="border:1px solid var(--border);border-radius:10px;padding:.8rem 1rem;margin:.5rem 0;">
                <legend style="font-weight:700;font-size:.85rem;">Twilio</legend>
                <div class="form-group"><label class="form-label">Account SID</label><input class="form-control" name="twilio_sid" value="<?= e($v['twilio_sid']) ?>" autocomplete="off"></div>
                <div class="form-group"><label class="form-label">Auth Token</label><input class="form-control" name="twilio_token" value="<?= e($v['twilio_token']) ?>" autocomplete="off"></div>
                <div class="form-group"><label class="form-label">From Number</label><input class="form-control" name="twilio_from" value="<?= e($v['twilio_from']) ?>" placeholder="+1..."></div>
            </fieldset>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div>

    <div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('send') ?> Send test</h3></div>
            <form class="admin-form panel-body" method="post" action="<?= e(admin_url('sms')) ?>">
                <?= csrf_field() ?><input type="hidden" name="_do" value="test">
                <div class="form-group"><label class="form-label">To (with country code)</label><input class="form-control" name="test_to" placeholder="+9199..." required></div>
                <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" name="test_msg" style="min-height:70px;">Test SMS from <?= e(get_setting('site_name', SITE_NAME)) ?></textarea></div>
                <div class="form-actions"><button class="btn btn-secondary" type="submit"><?= lucide('send') ?> Send test SMS</button></div>
            </form>
        </div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent SMS</h3></div>
            <div class="panel-body">
                <?php if ($log): ?>
                <div class="table-wrap"><table class="admin-table"><thead><tr><th>To</th><th>Status</th><th>When</th></tr></thead><tbody>
                <?php foreach ($log as $r): ?>
                    <tr><td><?= e($r['recipient']) ?></td>
                        <td><span class="pill <?= $r['status'] === 'sent' || $r['status'] === 'delivered' ? 'pill-green' : ($r['status'] === 'failed' ? 'pill-red' : 'pill-amber') ?>"><?= e($r['status']) ?></span><?php if (!empty($r['error'])): ?><br><small class="text-muted" title="<?= e($r['error']) ?>"><?= e(excerpt($r['error'], 8)) ?></small><?php endif; ?></td>
                        <td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('message-square') ?></div>No SMS sent yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/foot.php'; ?>
