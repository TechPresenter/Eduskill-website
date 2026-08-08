<?php
/**
 * =============================================================================
 *  Admin — Campaign Gallery (per-campaign media: images + videos)
 * =============================================================================
 *  Scoped to a single campaign via ?campaign=<id> (required — the page flashes
 *  and redirects to the campaigns list when it is missing/unknown, and keeps the
 *  context through every link and post-save redirect). Add an uploaded image
 *  (raster only) or an external video URL, list the gallery as a grid of
 *  thumbnails ordered by sort_order, and delete items (removing the file for
 *  images). Standard admin pattern: CSRF-guarded POST handlers keyed on _do,
 *  prepared statements, stat cards, activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'campaign_media';

/* -------------------------------------------------------------- ADD */
if (is_post() && post('_do') === 'add') {
    require_csrf();
    $campaignId = (int) post('campaign', 0);
    $campaign   = $campaignId ? find('campaigns', $campaignId) : null;
    if (!$campaign) {
        set_flash('error', 'Campaign not found.');
        redirect('/admin/campaigns');
    }
    $backUrl = '/admin/campaign-gallery?campaign=' . $campaignId;

    $type = post('type') === 'video' ? 'video' : 'image';
    $path = null;
    $url  = null;

    if ($type === 'image') {
        if (empty($_FILES['file']['name'])) {
            set_flash('error', 'Please choose an image to upload.');
            redirect($backUrl);
        }
        // Raster formats only — exclude SVG (which upload.php would otherwise
        // accept unsanitised, enabling stored XSS from a crafted .svg).
        $up = upload_image($_FILES['file'], 'campaigns', ['allowed' => 'jpg,jpeg,png,gif,webp']);
        if (!$up['success']) {
            set_flash('error', 'Image: ' . $up['error']);
            redirect($backUrl);
        }
        $path = $up['path'];
    } else {
        $url = clean(post('url'));
        if ($url === '') {
            set_flash('error', 'Please enter a video URL.');
            redirect($backUrl);
        }
    }

    $newId = db_insert($table, [
        'campaign_id' => $campaignId,
        'type'        => $type,
        'path'        => $path,
        'url'         => $url,
        'caption'     => clean(post('caption')) ?: null,
        'sort_order'  => (int) post('sort_order', 0),
    ]);
    log_activity('create', 'campaigns', 'Added ' . $type . ' #' . $newId . ' to campaign gallery #' . $campaignId);
    set_flash('success', 'Media added to the gallery.');
    redirect($backUrl);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $campaignId = (int) post('campaign', 0);
    $delId      = (int) post('id', 0);
    $row        = find($table, $delId);
    if ($row) {
        if ($row['type'] === 'image' && !empty($row['path'])) {
            delete_upload($row['path']);
        }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'campaigns', 'Deleted campaign media #' . $delId);
        set_flash('success', 'Media removed.');
    }
    redirect('/admin/campaign-gallery?campaign=' . $campaignId);
}

/* -------------------------------------------------------------- LIST (campaign required) */
$campaignId = (int) get('campaign', 0);
$campaign   = $campaignId ? find('campaigns', $campaignId) : null;
if (!$campaign) {
    set_flash('error', 'Select a campaign to manage its gallery.');
    redirect('/admin/campaigns');
}

$items = db_all(
    "SELECT * FROM $table WHERE campaign_id = :c ORDER BY sort_order ASC, id ASC",
    [':c' => $campaignId]
);

$scope  = 'campaign_id = :c';
$sp     = [':c' => $campaignId];
$counts = [
    'all'    => db_count($table, $scope, $sp),
    'images' => db_count($table, $scope . " AND type = 'image'", $sp),
    'videos' => db_count($table, $scope . " AND type = 'video'", $sp),
];

$page_title = 'Gallery · ' . $campaign['title'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Campaign Gallery</h1><span class="muted">Campaigns / <?= e($campaign['title']) ?> · Gallery</span></div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('campaigns?action=edit&id=' . $campaignId)) ?>"><?= lucide('pencil') ?> Edit campaign</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('campaigns')) ?>">← All campaigns</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('images') ?></div><div><div class="stat-value"><?= (int) $counts['all'] ?></div><div class="stat-label">Media items</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('image') ?></div><div><div class="stat-value"><?= (int) $counts['images'] ?></div><div class="stat-label">Images</div></div></div>
    <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('video') ?></div><div><div class="stat-value"><?= (int) $counts['videos'] ?></div><div class="stat-label">Videos</div></div></div>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <!-- Add media -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Add to gallery</h3>
        <form method="post" action="<?= e(admin_url('campaign-gallery')) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="add">
            <input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">

            <div class="form-group"><label class="form-label">Type</label>
                <select class="form-select" name="type" id="cm-type">
                    <option value="image">Image (upload)</option>
                    <option value="video">Video (URL)</option>
                </select></div>

            <div class="form-group" id="cm-image-field"><label class="form-label">Image file</label>
                <input class="form-control" type="file" name="file" accept="image/*">
                <small class="form-hint">JPG, PNG, GIF or WEBP.</small></div>

            <div class="form-group" id="cm-video-field" style="display:none;"><label class="form-label">Video URL</label>
                <input class="form-control" type="url" name="url" placeholder="https://www.youtube.com/watch?v=…">
                <small class="form-hint">Link to a YouTube / Vimeo / hosted video.</small></div>

            <div class="grid-2">
                <div class="form-group"><label class="form-label">Caption</label>
                    <input class="form-control" name="caption" placeholder="Optional caption"></div>
                <div class="form-group"><label class="form-label">Sort order</label>
                    <input class="form-control" type="number" name="sort_order" value="0"></div>
            </div>

            <div class="form-actions mt-3">
                <button class="btn btn-primary" type="submit"><?= lucide('plus') ?> Add media</button>
            </div>
        </form>
    </div></div>

    <!-- Gallery grid -->
    <div class="panel"><div class="panel-body">
        <h3 class="mb-3">Gallery <span class="text-muted">(<?= (int) $counts['all'] ?>)</span></h3>
        <?php if ($items): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
            <?php foreach ($items as $m): ?>
                <div style="border:1px solid var(--border,#e2e8f0);border-radius:12px;overflow:hidden;background:var(--surface,#fff);display:flex;flex-direction:column;">
                    <?php if ($m['type'] === 'image'): ?>
                        <div style="aspect-ratio:4/3;background:#eef2f7;overflow:hidden;">
                            <img src="<?= e(upload_url($m['path'])) ?>" alt="<?= e($m['caption'] ?? '') ?>" style="width:100%;height:100%;object-fit:cover;display:block;">
                        </div>
                    <?php else: ?>
                        <a href="<?= e(safe_href($m['url'])) ?>" target="_blank" rel="noopener noreferrer" style="aspect-ratio:4/3;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;text-decoration:none;gap:.4rem;">
                            <?= lucide('play-circle') ?> <span style="font-size:.8rem;font-weight:600;">Video</span>
                        </a>
                    <?php endif; ?>
                    <div style="padding:.6rem .7rem;display:flex;flex-direction:column;gap:.35rem;">
                        <div style="font-size:.88rem;font-weight:600;word-break:break-word;">
                            <?= $m['caption'] ? e($m['caption']) : '<span class="text-muted">No caption</span>' ?>
                        </div>
                        <?php if ($m['type'] === 'video' && !empty($m['url'])): ?>
                            <a href="<?= e(safe_href($m['url'])) ?>" target="_blank" rel="noopener noreferrer" style="font-size:.75rem;word-break:break-all;"><?= e($m['url']) ?> <?= lucide('external-link') ?></a>
                        <?php endif; ?>
                        <div class="flex items-center justify-between" style="gap:.5rem;">
                            <small class="text-muted"><?= e(ucfirst($m['type'])) ?> · #<?= (int) $m['sort_order'] ?></small>
                            <div class="actions">
                                <form method="post" action="<?= e(admin_url('campaign-gallery')) ?>" data-confirm="Remove this media from the gallery?" style="display:inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <input type="hidden" name="campaign" value="<?= (int) $campaignId ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('images') ?></div>No media in this gallery yet. Add an image or video using the form.</div>
        <?php endif; ?>
    </div></div>
</div>

<script>
(function () {
    var sel = document.getElementById('cm-type');
    if (!sel) return;
    function sync() {
        var isVideo = sel.value === 'video';
        document.getElementById('cm-image-field').style.display = isVideo ? 'none' : '';
        document.getElementById('cm-video-field').style.display = isVideo ? '' : 'none';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
