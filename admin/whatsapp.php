<?php
/**
 * Admin — WhatsApp Business (Meta Cloud API): config, test send, delivery log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post() && post('_do') === 'save') {
    require_csrf();
    set_setting('whatsapp_enabled', post('whatsapp_enabled') ? '1' : '0', 'messaging', 'boolean');
    set_setting('whatsapp_token', trim((string) post('whatsapp_token', '')), 'messaging', 'text');
    set_setting('whatsapp_phone_id', trim((string) post('whatsapp_phone_id', '')), 'messaging', 'text');
    log_activity('update', 'settings', 'Updated WhatsApp settings');
    set_flash('success', 'WhatsApp settings saved.');
    redirect('/admin/whatsapp');
}
if (is_post() && post('_do') === 'test') {
    require_csrf();
    $to = trim((string) post('test_to', ''));
    $tpl = trim((string) post('test_template', ''));
    if ($to === '') { set_flash('error', 'Enter a recipient number.'); redirect('/admin/whatsapp'); }
    $r = whatsapp_send($to, (string) post('test_msg', ''), $tpl !== '' ? ['template' => $tpl] : []);
    set_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'WhatsApp message sent.' : ('Failed: ' . ($r['error'] ?? 'unknown')));
    redirect('/admin/whatsapp');
}

$enabled = (bool) get_setting('whatsapp_enabled', '0');
$token   = (string) get_setting('whatsapp_token', '');
$phoneId = (string) get_setting('whatsapp_phone_id', '');
$log = db_all("SELECT * FROM messaging_log WHERE channel='whatsapp' ORDER BY id DESC LIMIT 15");

$page_title = 'WhatsApp';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>WhatsApp Business</h1><span class="muted">Messaging &amp; Push / Meta Cloud API</span></div>
    <span class="pill <?= $enabled ? 'pill-green' : 'pill-gray' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
</div>
<div class="grid grid-2" style="align-items:start;gap:1.5rem;">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('settings') ?> Configuration</h3></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('whatsapp')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save">
            <div class="alert alert-info"><?= lucide('info') ?> Uses the Meta WhatsApp Cloud API. Create an app at developers.facebook.com, add WhatsApp, and copy the permanent token + phone-number ID.</div>
            <label class="checkbox"><input type="checkbox" name="whatsapp_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> Enable WhatsApp sending</label>
            <div class="form-group"><label class="form-label">Permanent Access Token</label><input class="form-control" name="whatsapp_token" value="<?= e($token) ?>" autocomplete="off"></div>
            <div class="form-group"><label class="form-label">Phone Number ID</label><input class="form-control" name="whatsapp_phone_id" value="<?= e($phoneId) ?>"></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div>
    <div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('send') ?> Send test</h3></div>
            <form class="admin-form panel-body" method="post" action="<?= e(admin_url('whatsapp')) ?>">
                <?= csrf_field() ?><input type="hidden" name="_do" value="test">
                <div class="form-group"><label class="form-label">To (country code, no +)</label><input class="form-control" name="test_to" placeholder="9199..." required></div>
                <div class="form-group"><label class="form-label">Template name (optional)</label><input class="form-control" name="test_template" placeholder="hello_world"><span class="form-hint">Outside the 24h window WhatsApp requires an approved template. Leave blank to send free-form text.</span></div>
                <div class="form-group"><label class="form-label">Message (free-form)</label><textarea class="form-textarea" name="test_msg" style="min-height:70px;">Hello from <?= e(get_setting('site_name', SITE_NAME)) ?></textarea></div>
                <div class="form-actions"><button class="btn btn-secondary" type="submit"><?= lucide('send') ?> Send test</button></div>
            </form>
        </div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent</h3></div>
            <div class="panel-body">
                <?php if ($log): ?>
                <div class="table-wrap"><table class="admin-table"><thead><tr><th>To</th><th>Status</th><th>When</th></tr></thead><tbody>
                <?php foreach ($log as $r): ?>
                    <tr><td><?= e($r['recipient']) ?></td><td><span class="pill <?= $r['status'] === 'sent' ? 'pill-green' : ($r['status'] === 'failed' ? 'pill-red' : 'pill-amber') ?>"><?= e($r['status']) ?></span></td><td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('message-circle') ?></div>No messages yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/foot.php'; ?>
