<?php
/**
 * =============================================================================
 *  Admin — Campaign Updates (progress updates posted to donors)
 * =============================================================================
 *  ?campaign=<id> required. Post a progress update (title, body, optional image)
 *  for a single campaign and optionally email every donor of that campaign. Lists
 *  existing updates newest-first with delete. Standard admin pattern: CSRF,
 *  prepared statements, activity logging, flash + redirect. The ?campaign context
 *  is preserved across every link and redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table      = 'campaign_updates';
$campaignId = (int) get('campaign', 0);
$campaign   = $campaignId ? find('campaigns', $campaignId) : null;
if (!$campaign) {
    set_flash('error', 'Campaign not found.');
    redirect(admin_url('campaigns'));
}

$selfUrl = admin_url('campaign-updates?campaign=' . $campaignId);

/* -------------------------------------------------------------- SAVE (create) */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    if (clean(post('title')) === '') {
        flash_old($_POST);
        set_flash('error', 'Update title is required.');
        redirect($selfUrl);
    }

    $data = [
        'campaign_id' => $campaignId,
        'title'       => clean(post('title')),
        'body'        => trim((string) post('body')) ?: null,
        'created_by'  => current_user_id(),
    ];

    if (!empty($_FILES['image']['name'])) {
        // Raster formats only — exclude SVG (which upload.php would otherwise
        // accept unsanitised, enabling stored XSS from a crafted .svg).
        $up = upload_image($_FILES['image'], 'campaigns', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            flash_old($_POST);
            set_flash('error', 'Image: ' . $up['error']);
            redirect($selfUrl);
        }
        $data['image'] = $up['path'];
    }

    $newId = db_insert($table, $data);

    $emailed = null;
    if (post('notify')) {
        $emailed = campaign_update_notify($newId); // emails donors + flags notified=1
    }

    log_activity('create', 'campaigns', 'Posted update #' . $newId . ' on campaign #' . $campaignId);

    $msg = 'Update posted.';
    if ($emailed !== null) {
        $msg .= ' ' . $emailed . ' donor' . ($emailed === 1 ? '' : 's') . ' emailed.';
    }
    set_flash('success', $msg);
    redirect($selfUrl);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row   = find($table, $delId);
    if ($row && (int) $row['campaign_id'] === $campaignId) {
        if (!empty($row['image'])) { delete_upload($row['image']); }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'campaigns', 'Deleted update #' . $delId . ' on campaign #' . $campaignId);
        set_flash('success', 'Update deleted.');
    }
    redirect($selfUrl);
}

/* -------------------------------------------------------------- LIST */
$updates = db_all(
    "SELECT * FROM $table WHERE campaign_id = :c ORDER BY created_at DESC, id DESC",
    [':c' => $campaignId]
);

$page_title = 'Updates — ' . $campaign['title'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1><?= e($campaign['title']) ?></h1><span class="muted">Campaigns / Updates · <?= (int) count($updates) ?> posted</span></div>
    <a class="btn btn-secondary" href="<?= e(admin_url('campaigns')) ?>">← Back to campaigns</a>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <!-- Post an update -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Post an update</h3>
        <form class="admin-form" method="post" action="<?= e($selfUrl) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">

            <div class="form-group"><label class="form-label">Title <span class="req">*</span></label>
                <input class="form-control" name="title" required value="<?= e(old('title')) ?>"></div>
            <div class="form-group"><label class="form-label">Body</label>
                <textarea class="form-textarea" name="body" style="min-height:140px;"><?= e(old('body')) ?></textarea></div>
            <div class="form-group"><label class="form-label">Image</label>
                <input class="form-control" type="file" name="image" accept="image/*"></div>
            <label class="checkbox"><input type="checkbox" name="notify" value="1"> Notify donors by email</label>
            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= lucide('send') ?> Post update</button>
            </div>
        </form>
    </div></div>

    <!-- Existing updates -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Posted updates</h3>
        <?php if ($updates): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Update</th><th>Posted</th><th>Notified</th><th style="text-align:right;">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($updates as $u): ?>
                <tr>
                    <td><div class="flex items-center" style="gap:.6rem;">
                        <?php if (!empty($u['image'])): ?>
                            <img src="<?= e(upload_url($u['image'])) ?>" alt="" style="width:34px;height:34px;border-radius:8px;object-fit:cover;flex:none;background:#eef2f7;">
                        <?php endif; ?>
                        <div><strong><?= e($u['title']) ?></strong>
                            <?php if (!empty($u['body'])): ?><br><small class="text-muted"><?= e(excerpt($u['body'], 14)) ?></small><?php endif; ?>
                        </div>
                    </div></td>
                    <td><?= e(format_date($u['created_at'], 'd M Y')) ?></td>
                    <td><?php if ((int) $u['notified'] === 1): ?><span class="pill pill-green">Notified</span><?php else: ?><span class="pill pill-gray">Not sent</span><?php endif; ?></td>
                    <td><div class="actions">
                        <form method="post" action="<?= e($selfUrl) ?>" data-confirm="Delete this update permanently?" style="display:inline;">
                            <?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                        </form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('megaphone') ?></div>No updates posted yet. Share your first progress update with donors.</div>
        <?php endif; ?>
    </div></div>
</div>

<?php
clear_old();
include __DIR__ . '/partials/foot.php';
