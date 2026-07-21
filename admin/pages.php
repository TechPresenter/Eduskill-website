<?php
require __DIR__ . '/includes/auth.php';
require_admin('pages.view');
$admin_title = 'Pages';

// Create a new page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create' && user_can('pages.create')) {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title !== '') {
            $slug = unique_slug('pages', str_slug((string) ($_POST['slug'] ?: $title)));
            $id = db_insert("INSERT INTO pages (slug, title, status, created_by) VALUES (?, ?, 'draft', ?)", [$slug, $title, (int) ($_SESSION['uid'] ?? 0)]);
            flash('success', 'Page created — add some sections and publish.');
            redirect('admin/page-editor.php?id=' . $id);
        }
        flash('error', 'Please enter a title.');
        redirect('admin/pages.php');
    }
    if ($action === 'delete' && user_can('pages.delete')) {
        $p = db_one("SELECT is_system FROM pages WHERE id = ?", [(int) ($_POST['id'] ?? 0)]);
        if ($p && (int) $p['is_system'] === 1) {
            flash('error', 'System pages cannot be deleted.');
        } else {
            db_exec("UPDATE pages SET deleted_at = NOW() WHERE id = ?", [(int) $_POST['id']]);
            flash('success', 'Page moved to trash.');
        }
        redirect('admin/pages.php');
    }
}

$rows = db_all("SELECT id, slug, title, status, is_system, updated_at FROM pages WHERE deleted_at IS NULL ORDER BY is_system DESC, title ASC");
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Pages</h2><p class="page-subtitle">Build website pages from sections.</p></div>
  <?php if (user_can('pages.create')): ?>
    <div class="page-actions"><button type="button" onclick="document.getElementById('new-page').classList.toggle('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><i class="fa-solid fa-plus"></i> New page</button></div>
  <?php endif; ?>
</div>

<div id="new-page" class="mb-5 hidden max-w-xl rounded-2xl border border-edge bg-surface p-5 shadow-card">
  <form method="post" class="flex flex-wrap items-end gap-3"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="flex-1"><label class="block text-sm font-medium text-content">Page title</label><input name="title" required placeholder="e.g. About Us" class="mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"></div>
    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700">Create</button>
  </form>
</div>

<div class="overflow-hidden rounded-2xl border border-edge bg-surface shadow-card">
  <div class="scroll-x"><table class="w-full text-left text-sm">
    <thead class="border-b border-edge bg-surface-sunken text-xs uppercase tracking-wide text-content-muted"><tr><th class="px-4 py-3 font-semibold">Title</th><th class="px-4 py-3 font-semibold">URL</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 text-right font-semibold">Actions</th></tr></thead>
    <tbody class="divide-y divide-edge">
      <?php foreach ($rows as $p): $pub = $p['slug'] === 'home' ? 'index.php' : $p['slug'] . '.php'; ?>
        <tr class="hover:bg-surface-sunken/50">
          <td class="px-4 py-3"><span class="font-medium text-content"><?= e($p['title']) ?></span><?php if ((int) $p['is_system'] === 1): ?><span class="ml-1.5 rounded bg-surface-sunken px-1.5 py-0.5 text-2xs font-semibold text-content-subtle ring-1 ring-inset ring-edge">System</span><?php endif; ?></td>
          <td class="px-4 py-3 text-content-muted">/<?= e($p['slug'] === 'home' ? '' : $p['slug']) ?></td>
          <td class="px-4 py-3"><?= $p['status'] === 'published' ? '<span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-semibold text-success-700">Published</span>' : '<span class="rounded-full bg-surface-sunken px-2 py-0.5 text-xs font-semibold text-content-muted ring-1 ring-inset ring-edge">Draft</span>' ?></td>
          <td class="px-4 py-3"><div class="row-actions">
            <a href="<?= e(url('admin/page-editor.php?id=' . (int) $p['id'])) ?>" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50">Edit</a>
            <a href="<?= e(url($pub)) ?>" target="_blank" class="rounded-lg px-3 py-1.5 text-sm font-medium text-content-muted hover:bg-surface-sunken">View</a>
            <?php if ((int) $p['is_system'] === 0 && user_can('pages.delete')): ?><form method="post" onsubmit="return confirm('Trash this page?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50">Delete</button></form><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
