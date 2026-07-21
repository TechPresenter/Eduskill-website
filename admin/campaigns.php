<?php
require __DIR__ . '/includes/auth.php';
require_admin('campaigns.manage');
$admin_title = 'Campaigns';
$rows = db_all("SELECT * FROM campaigns WHERE deleted_at IS NULL ORDER BY is_featured DESC, id DESC");
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Campaigns</h2><p class="page-subtitle"><?= count($rows) ?> campaign<?= count($rows) === 1 ? '' : 's' ?></p></div>
  <div class="page-actions">
    <a href="<?= e(url('admin/campaign-form.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><?= icon('campaigns', 'h-4 w-4') ?> New campaign</a>
  </div>
</div>

<div class="overflow-hidden rounded-card border border-edge bg-surface shadow-card">
  <?php if ($rows === []): ?>
    <div class="p-10 text-center text-sm text-content-muted">No campaigns yet. Create your first one.</div>
  <?php else: ?>
    <div class="scroll-x"><table class="w-full text-left text-sm">
      <thead class="border-b border-edge bg-surface-sunken text-xs uppercase tracking-wide text-content-muted"><tr>
        <th class="px-4 py-3 font-semibold">Title</th><th class="px-4 py-3 font-semibold">Goal</th>
        <th class="px-4 py-3 font-semibold">Raised</th><th class="px-4 py-3 font-semibold">Status</th>
        <th class="px-4 py-3 text-right font-semibold">Actions</th></tr></thead>
      <tbody class="divide-y divide-edge">
        <?php foreach ($rows as $c): ?>
          <tr class="hover:bg-surface-sunken/50">
            <td class="px-4 py-3"><span class="font-medium text-content"><?= e($c['title']) ?></span><?php if ((int) $c['is_featured'] === 1): ?><span class="ml-1.5 rounded bg-brand-50 px-1.5 py-0.5 text-2xs font-semibold text-brand-700">Featured</span><?php endif; ?></td>
            <td class="px-4 py-3 text-content-muted">₹<?= e(inr((int) $c['goal_amount'])) ?></td>
            <td class="px-4 py-3 text-content-muted">₹<?= e(inr((int) $c['raised_amount'])) ?></td>
            <td class="px-4 py-3"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700"><?= e($c['status']) ?></span></td>
            <td class="px-4 py-3"><div class="row-actions">
              <a href="<?= e(url('admin/campaign-form.php?id=' . (int) $c['id'])) ?>" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50">Edit</a>
              <a href="<?= e(url('campaign-details.php?slug=' . urlencode((string) $c['slug']))) ?>" target="_blank" class="rounded-lg px-3 py-1.5 text-sm font-medium text-content-muted hover:bg-surface-sunken">View</a>
              <button type="button" onclick="eskDelete('campaigns', <?= (int) $c['id'] ?>, '<?= e(addslashes((string) $c['title'])) ?>')" class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50">Delete</button>
            </div></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
