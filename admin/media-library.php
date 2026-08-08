<?php
/**
 * =============================================================================
 *  Admin — Media Library  (SPECIAL admin module).
 *  Upload images/documents into a single reusable library and copy their
 *  public URLs. No create/edit form beyond the uploader — items are:
 *    upload (upload_image|upload_document -> 'media', insert media_library row)
 *    + a paginated, searchable grid + per-item copy-URL + delete.
 *  Follows the standard admin module pattern (see admin/programs.php):
 *    CSRF on every POST, validation, pagination, upload with file cleanup,
 *    activity logging, flash + redirect.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table = 'media_library';

/* Extensions we treat as images (thumbnail + upload_image path). */
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

/* -------------------------------------------------------------- UPLOAD (create) */
if (is_post() && post('_do') === 'save') {
    require_csrf();

    // A file is mandatory.
    if (empty($_FILES['media']['name'])) {
        set_flash('error', 'Please choose a file to upload.');
        redirect('/admin/media-library');
    }

    $ext = strtolower(pathinfo((string) $_FILES['media']['name'], PATHINFO_EXTENSION));

    // Route to the correct validated uploader, both into the 'media' folder.
    if (in_array($ext, $imageExts, true)) {
        $up = upload_image($_FILES['media'], 'media');
    } else {
        $up = upload_document($_FILES['media'], 'media');
    }

    if (!$up['success']) {
        set_flash('error', $up['error']);
        redirect('/admin/media-library');
    }

    $origName = clean(basename((string) $_FILES['media']['name']));
    $data = [
        'file_name'   => mb_substr($origName, 0, 191),
        'file_path'   => $up['path'],
        'file_type'   => $up['ext'],
        'mime_type'   => mb_substr((string) $up['mime'], 0, 96),
        'file_size'   => (int) $up['size'],
        'alt_text'    => mb_substr(clean(post('alt_text', '')), 0, 255),
        'folder'      => 'media',
        'uploaded_by' => current_user_id(),
    ];

    $newId = db_insert($table, $data);
    log_activity('create', 'media', 'Uploaded media #' . $newId);
    set_flash('success', 'File uploaded to the media library.');
    redirect('/admin/media-library');
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId = (int) post('id', 0);
    $row = find($table, $delId);
    if ($row) {
        if (!empty($row['file_path'])) delete_upload($row['file_path']);
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', 'media', 'Deleted media #' . $delId);
        set_flash('success', 'File deleted from the media library.');
    }
    redirect('/admin/media-library');
}

/* -------------------------------------------------------------- LIST (grid) */
$page_title = 'Media Library';
$search = trim((string) get('q', ''));
$where  = '1=1';
$params = [];
if ($search !== '') {
    $where .= " AND CONCAT_WS(' ', file_name, file_type) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 24);

/** Lucide icon name for a non-image file, keyed by extension. */
$fileIcon = static function (string $ext): string {
    return match (strtolower($ext)) {
        'pdf'                 => 'file-text',
        'doc', 'docx'         => 'file-text',
        'xls', 'xlsx'         => 'file-spreadsheet',
        'ppt', 'pptx'         => 'presentation',
        'txt'                 => 'file-text',
        default               => 'file',
    };
};

include __DIR__ . '/partials/head.php';
?>
<style>
/* Media Library — responsive grid (scoped to this page). */
.media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.1rem; }
.media-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; display: flex; flex-direction: column; box-shadow: var(--shadow-sm); transition: transform .2s, box-shadow .2s; }
.media-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
.media-thumb { position: relative; aspect-ratio: 4 / 3; background: var(--surface-2); display: grid; place-items: center; overflow: hidden; }
.media-thumb img { width: 100%; height: 100%; object-fit: cover; }
.media-thumb .file-glyph { font-size: 2.6rem; }
.media-thumb .type-tag { position: absolute; top: .5rem; left: .5rem; }
.media-body { padding: .8rem .85rem; display: flex; flex-direction: column; gap: .55rem; }
.media-name { font-weight: 600; font-size: .85rem; word-break: break-word; line-height: 1.3; }
.media-meta { color: var(--muted); font-size: .74rem; display: flex; gap: .4rem; flex-wrap: wrap; align-items: center; }
.media-copy { display: flex; gap: .35rem; }
.media-copy .form-control { font-size: .74rem; padding: .35rem .5rem; }
.copy-btn { flex-shrink: 0; padding: 0 .6rem; font-size: .74rem; white-space: nowrap; }
.copy-btn.is-copied { background: rgba(88,164,47,.14); color: #308629; border-color: transparent; }
.media-foot { display: flex; align-items: center; justify-content: space-between; padding: 0 .85rem .8rem; }
</style>

<div class="admin-page-head">
    <div><h1>Media Library</h1><span class="muted"><?= (int) $p['total'] ?> files</span></div>
</div>

<div class="panel">
    <div class="panel-body">
        <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= e(admin_url('media-library')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="save">
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Choose a file <span class="req">*</span></label>
                    <input class="form-control" type="file" name="media" required
                           accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                           data-preview="#imgPreview">
                    <p class="form-hint">Images (jpg, png, gif, webp, svg) or documents (pdf, doc, xls, ppt, txt). Max <?= e(human_filesize(UPLOAD_MAX_BYTES)) ?>.</p>
                    <img id="imgPreview" class="img-preview" src="" alt="preview" style="display:none;">
                </div>
                <div class="form-group">
                    <label class="form-label">Alt text (optional)</label>
                    <input class="form-control" name="alt_text" maxlength="255" placeholder="Describe the image for accessibility">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= lucide('upload') ?> Upload to Library</button>
            </div>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('media-library')) ?>">
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>" placeholder="Search files…">
            </form>
        </div>

        <?php if ($p['items']): ?>
        <div class="media-grid">
            <?php foreach ($p['items'] as $m):
                $isImage = str_starts_with((string) ($m['mime_type'] ?? ''), 'image/');
                $publicUrl = upload_url($m['file_path']);
                $inputId = 'mediaUrl' . (int) $m['id'];
            ?>
            <div class="media-card">
                <div class="media-thumb">
                    <?php if ($isImage): ?>
                        <img src="<?= e($publicUrl) ?>" alt="<?= e($m['alt_text'] ?? $m['file_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="file-glyph"><?= lucide($fileIcon((string) ($m['file_type'] ?? ''))) ?></span>
                    <?php endif; ?>
                    <span class="pill pill-blue type-tag"><?= e(strtoupper((string) ($m['file_type'] ?? 'file'))) ?></span>
                </div>
                <div class="media-body">
                    <div class="media-name" title="<?= e($m['file_name']) ?>"><?= e($m['file_name']) ?></div>
                    <div class="media-meta">
                        <span><?= e(human_filesize((int) ($m['file_size'] ?? 0))) ?></span>
                        <span>•</span>
                        <span><?= e(format_date($m['created_at'], 'd M Y')) ?></span>
                    </div>
                    <div class="media-copy">
                        <input class="form-control" id="<?= e($inputId) ?>" type="text" value="<?= e($publicUrl) ?>" readonly onfocus="this.select();">
                        <button type="button" class="btn btn-secondary btn-sm copy-btn" data-copy="#<?= e($inputId) ?>">Copy</button>
                    </div>
                </div>
                <div class="media-foot">
                    <a class="btn btn-ghost btn-sm" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Open ↗</a>
                    <form method="post" action="<?= e(admin_url('media-library')) ?>" data-confirm="Delete this file permanently?" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('image') ?></div>No media yet. Upload your first file above.</div>
        <?php endif; ?>
    </div>
</div>

<script>
// Copy a media item's public URL to the clipboard, with visual feedback.
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.querySelector(btn.getAttribute('data-copy'));
        if (!input) return;
        input.focus();
        input.select();
        input.setSelectionRange(0, 99999);
        var done = function () {
            var label = btn.textContent;
            btn.textContent = 'Copied!';
            btn.classList.add('is-copied');
            setTimeout(function () { btn.textContent = label; btn.classList.remove('is-copied'); }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done, function () {
                try { document.execCommand('copy'); } catch (e) {}
                done();
            });
        } else {
            try { document.execCommand('copy'); } catch (e) {}
            done();
        }
    });
});
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
