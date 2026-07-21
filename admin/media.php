<?php
require __DIR__ . '/includes/auth.php';
require_admin('media.view');
$admin_title = 'Media Library';

// Delete a media file (row + file on disk).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null) && ($_POST['action'] ?? '') === 'delete') {
    $m = db_one("SELECT path FROM media WHERE id = ?", [(int) ($_POST['id'] ?? 0)]);
    if ($m) {
        @unlink(BASE_PATH . '/assets/' . $m['path']);
        db_exec("DELETE FROM media WHERE id = ?", [(int) $_POST['id']]);
        flash('success', 'File deleted.');
    }
    redirect('admin/media.php');
}

$rows = db_all("SELECT * FROM media ORDER BY id DESC LIMIT 200");
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Media Library</h2><p class="page-subtitle"><?= count($rows) ?> file<?= count($rows) === 1 ? '' : 's' ?></p></div>
</div>

<!-- Upload dropzone -->
<div class="mb-6 rounded-2xl border-2 border-dashed border-edge bg-surface p-8 text-center">
  <i class="fa-solid fa-cloud-arrow-up mb-2 text-3xl text-brand-500"></i>
  <p class="text-sm text-content">Drag an image here, or
    <label class="cursor-pointer font-semibold text-brand-600 hover:underline">browse<input id="media-upload" type="file" accept="image/jpeg,image/png,image/webp" class="hidden"></label>
  </p>
  <p class="mt-1 text-xs text-content-muted">JPG, PNG or WebP · up to 5 MB · auto-optimised</p>
</div>

<?php if ($rows === []): ?>
  <div class="rounded-2xl border border-dashed border-edge bg-surface p-10 text-center text-content-muted">No files yet.</div>
<?php else: ?>
  <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
    <?php foreach ($rows as $m): ?>
      <div class="group relative overflow-hidden rounded-xl border border-edge bg-surface shadow-card">
        <div class="aspect-square bg-surface-sunken">
          <?php if (str_starts_with((string) $m['mime'], 'image/')): ?><img src="<?= e(asset((string) $m['path'])) ?>" alt="" class="h-full w-full object-cover" loading="lazy"><?php else: ?><div class="grid h-full place-items-center text-content-subtle"><i class="fa-solid fa-file text-3xl"></i></div><?php endif; ?>
        </div>
        <div class="flex items-center justify-between gap-1 p-2">
          <button type="button" onclick="navigator.clipboard.writeText('<?= e((string) $m['path']) ?>');Swal.fire({icon:'success',title:'Path copied',timer:900,showConfirmButton:false})" class="truncate text-2xs text-content-muted hover:text-brand-600" title="Copy path"><i class="fa-regular fa-copy"></i> copy path</button>
          <form method="post" onsubmit="return confirm('Delete this file?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="action" value="delete"><button class="text-2xs text-danger-600 hover:underline"><i class="fa-solid fa-trash"></i></button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php
$admin_scripts = <<<'JS'
<script>
(function () {
  var input = document.getElementById('media-upload');
  if (!input) return;
  input.addEventListener('change', function () {
    if (!input.files || !input.files[0]) return;
    var fd = new FormData(); fd.append('file', input.files[0]);
    Swal.fire({ title: 'Uploading…', didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });
    window.eskApi('upload.php', 'POST', fd).then(function (d) {
      if (d.ok) { Swal.fire({ icon: 'success', title: 'Uploaded', timer: 900, showConfirmButton: false }).then(function () { location.reload(); }); }
      else { Swal.fire({ icon: 'error', title: 'Failed', text: d.message || '' }); }
    });
  });
})();
</script>
JS;
require __DIR__ . '/includes/footer.php';
