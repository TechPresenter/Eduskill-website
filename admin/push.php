<?php
/**
 * Admin — Push Notifications (OneSignal): config, broadcast send, delivery log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post() && post('_do') === 'save') {
    require_csrf();
    set_setting('onesignal_enabled', post('onesignal_enabled') ? '1' : '0', 'messaging', 'boolean');
    set_setting('onesignal_app_id', trim((string) post('onesignal_app_id', '')), 'messaging', 'text');
    set_setting('onesignal_api_key', trim((string) post('onesignal_api_key', '')), 'messaging', 'text');
    log_activity('update', 'settings', 'Updated OneSignal settings');
    set_flash('success', 'Push settings saved.');
    redirect('/admin/push');
}
if (is_post() && post('_do') === 'send') {
    require_csrf();
    $msg = trim((string) post('msg', ''));
    if ($msg === '') { set_flash('error', 'Enter a message.'); redirect('/admin/push'); }
    $opts = [];
    if (trim((string) post('url', '')) !== '') $opts['url'] = trim((string) post('url'));
    if (trim((string) post('heading', '')) !== '') $opts['heading'] = trim((string) post('heading'));
    $r = push_send($msg, $opts);
    set_flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Push notification sent.' : ('Failed: ' . ($r['error'] ?? 'unknown')));
    redirect('/admin/push');
}

$enabled = (bool) get_setting('onesignal_enabled', '0');
$appId   = (string) get_setting('onesignal_app_id', '');
$apiKey  = (string) get_setting('onesignal_api_key', '');
$log = db_all("SELECT * FROM messaging_log WHERE channel='push' ORDER BY id DESC LIMIT 15");

$page_title = 'Push (OneSignal)';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Web Push — OneSignal</h1><span class="muted">Messaging &amp; Push / OneSignal</span></div>
    <span class="pill <?= $enabled ? 'pill-green' : 'pill-gray' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
</div>
<div class="grid grid-2" style="align-items:start;gap:1.5rem;">
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('settings') ?> Configuration</h3></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('push')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save">
            <div class="alert alert-info"><?= lucide('info') ?> Create an app in OneSignal, add the Web platform, then paste the App ID + REST API Key. Server-side broadcasts work as soon as these are set. To let site visitors <em>subscribe</em>, add OneSignal's Web SDK snippet via <a href="<?= e(admin_url('custom-code')) ?>">Custom Code</a> (it needs its service-worker file at the site root).</div>
            <label class="checkbox"><input type="checkbox" name="onesignal_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> Enable OneSignal push</label>
            <div class="form-group"><label class="form-label">App ID</label><input class="form-control" name="onesignal_app_id" value="<?= e($appId) ?>"></div>
            <div class="form-group"><label class="form-label">REST API Key</label><input class="form-control" name="onesignal_api_key" value="<?= e($apiKey) ?>" autocomplete="off"></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save</button></div>
        </form>
    </div>
    <div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('bell-ring') ?> Send broadcast</h3></div>
            <form class="admin-form panel-body" method="post" action="<?= e(admin_url('push')) ?>">
                <?= csrf_field() ?><input type="hidden" name="_do" value="send">
                <div class="form-group"><label class="form-label">Heading</label><input class="form-control" name="heading" placeholder="<?= e(get_setting('site_name', SITE_NAME)) ?>"></div>
                <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" name="msg" style="min-height:70px;" required></textarea></div>
                <div class="form-group"><label class="form-label">Link URL (optional)</label><input class="form-control" type="url" name="url" placeholder="https://"></div>
                <div class="form-actions"><button class="btn btn-secondary" type="submit"><?= lucide('send') ?> Send to all subscribers</button></div>
            </form>
        </div>
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent</h3></div>
            <div class="panel-body">
                <?php if ($log): ?>
                <div class="table-wrap"><table class="admin-table"><thead><tr><th>Message</th><th>Status</th><th>When</th></tr></thead><tbody>
                <?php foreach ($log as $r): ?>
                    <tr><td><?= e(excerpt($r['body'] ?? '', 8)) ?></td><td><span class="pill <?= $r['status'] === 'sent' ? 'pill-green' : ($r['status'] === 'failed' ? 'pill-red' : 'pill-amber') ?>"><?= e($r['status']) ?></span></td><td><small class="text-muted"><?= e(time_ago($r['created_at'])) ?></small></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
                <?php else: ?><div class="empty-state"><div class="icon"><?= lucide('bell-ring') ?></div>No push sent yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/partials/foot.php'; ?>
