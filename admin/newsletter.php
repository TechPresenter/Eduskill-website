<?php
require __DIR__ . '/includes/auth.php';
require_admin('newsletter.manage');
$admin_title = 'Newsletter';

// CSV export.
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Name', 'Status', 'Subscribed at']);
    foreach (db_all("SELECT email, name, status, created_at FROM newsletter_subscribers ORDER BY id DESC") as $r) {
        fputcsv($out, [$r['email'], $r['name'], $r['status'], $r['created_at']]);
    }
    fclose($out);
    exit;
}

// Delete a subscriber.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null) && ($_POST['action'] ?? '') === 'delete') {
    db_exec("DELETE FROM newsletter_subscribers WHERE id = ?", [(int) ($_POST['id'] ?? 0)]);
    flash('success', 'Subscriber removed.');
    redirect('admin/newsletter.php');
}

$rows = db_all("SELECT * FROM newsletter_subscribers ORDER BY id DESC");
$total = count($rows);
$subscribed = (int) db_val("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed'");
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Newsletter</h2><p class="page-subtitle"><?= $subscribed ?> subscribed · <?= $total ?> total</p></div>
  <div class="page-actions"><a href="<?= e(url('admin/newsletter.php?export=1')) ?>" class="inline-flex items-center gap-2 rounded-lg border border-edge bg-surface px-4 py-2 text-sm font-medium text-content hover:bg-surface-sunken"><i class="fa-solid fa-file-csv text-emerald-500"></i> Export CSV</a></div>
</div>
<div class="overflow-hidden rounded-2xl border border-edge bg-surface shadow-card">
  <?php if ($rows === []): ?>
    <div class="p-12 text-center text-content-muted"><i class="fa-solid fa-paper-plane mb-2 text-3xl opacity-40"></i><p>No subscribers yet.</p></div>
  <?php else: ?>
    <div class="scroll-x"><table class="w-full text-left text-sm">
      <thead class="border-b border-edge bg-surface-sunken text-xs uppercase tracking-wide text-content-muted"><tr><th class="px-4 py-3 font-semibold">Email</th><th class="px-4 py-3 font-semibold">Name</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Joined</th><th class="px-4 py-3 text-right font-semibold">Actions</th></tr></thead>
      <tbody class="divide-y divide-edge">
        <?php foreach ($rows as $r): ?>
          <tr class="hover:bg-surface-sunken/50">
            <td class="px-4 py-3 font-medium text-content"><?= e($r['email']) ?></td>
            <td class="px-4 py-3 text-content-muted"><?= e($r['name'] ?: '—') ?></td>
            <td class="px-4 py-3"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700"><?= e($r['status']) ?></span></td>
            <td class="px-4 py-3 text-content-muted"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></td>
            <td class="px-4 py-3"><form method="post" class="text-right" onsubmit="return confirm('Remove this subscriber?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50">Delete</button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
