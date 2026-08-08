<?php
/**
 * Admin — In-app Notification Centre. Lists the current admin's notifications
 * (targeted + broadcast), marks read/all, and can broadcast a new one.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$uid = (int) current_user_id();

if (is_post() && post('_do') === 'read') {
    require_csrf();
    notif_mark_read($uid, (int) post('id', 0) ?: null);
    redirect('/admin/notifications');
}
if (is_post() && post('_do') === 'broadcast') {
    require_csrf();
    $title = clean(post('title', ''));
    if ($title === '') { set_flash('error', 'Title is required.'); redirect('/admin/notifications'); }
    notify($title, [
        'body' => clean(post('body', '')),
        'url'  => trim((string) post('url', '')) ?: null,
        'icon' => 'megaphone',
        'type' => 'info',
        // user_id omitted => broadcast to all admins
    ]);
    log_activity('create', 'notifications', 'Broadcast notification: ' . $title);
    set_flash('success', 'Notification broadcast to all admins.');
    redirect('/admin/notifications');
}

$items  = notif_recent($uid, 40);
$unread = notif_unread_count($uid);

$page_title = 'Notifications';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Notifications</h1><span class="muted"><?= (int) $unread ?> unread</span></div>
    <?php if ($unread > 0): ?>
    <form method="post" action="<?= e(admin_url('notifications')) ?>" style="display:inline;">
        <?= csrf_field() ?><input type="hidden" name="_do" value="read">
        <button class="btn btn-secondary" type="submit"><?= lucide('check-check') ?> Mark all read</button>
    </form>
    <?php endif; ?>
</div>

<div class="grid grid-2" style="grid-template-columns:2fr 1fr;align-items:start;gap:1.5rem;">
    <div class="panel">
        <div class="panel-body">
            <?php if ($items): ?>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                <?php foreach ($items as $n): $isUnread = empty($n['read_at']); ?>
                    <div class="flex items-center gap-2" style="padding:.7rem .8rem;border:1px solid var(--border);border-radius:10px;<?= $isUnread ? 'background:rgba(59,130,246,.06);border-color:rgba(59,130,246,.3);' : '' ?>">
                        <span class="stat-icon bg-blue" style="width:38px;height:38px;flex:0 0 auto;"><?= lucide($n['icon'] ?: 'bell') ?></span>
                        <div style="min-width:0;flex:1;">
                            <strong><?= e($n['title']) ?></strong><?php if ($isUnread): ?> <span class="pill pill-blue" style="font-size:.6rem;">new</span><?php endif; ?>
                            <?php if (!empty($n['body'])): ?><br><small class="text-muted"><?= e($n['body']) ?></small><?php endif; ?>
                            <br><small class="text-muted"><?= e(time_ago($n['created_at'])) ?><?= $n['user_id'] === null ? ' · broadcast' : '' ?></small>
                        </div>
                        <?php if (!empty($n['url'])): ?><a class="btn btn-ghost btn-sm" href="<?= e($n['url']) ?>"><?= lucide('arrow-right') ?></a><?php endif; ?>
                        <?php if ($isUnread): ?>
                        <form method="post" action="<?= e(admin_url('notifications')) ?>" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="read"><input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                            <button class="icon-btn" type="submit" title="Mark read"><?= lucide('check') ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('bell-off') ?></div>No notifications yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('megaphone') ?> Broadcast</h3></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('notifications')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="broadcast">
            <div class="form-group"><label class="form-label">Title <span class="req">*</span></label><input class="form-control" name="title" required maxlength="191"></div>
            <div class="form-group"><label class="form-label">Message</label><textarea class="form-textarea" name="body" style="min-height:80px;" maxlength="500"></textarea></div>
            <div class="form-group"><label class="form-label">Link URL</label><input class="form-control" name="url" placeholder="<?= e(admin_url('dashboard')) ?>"></div>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('send') ?> Send to all admins</button></div>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partials/foot.php'; ?>
