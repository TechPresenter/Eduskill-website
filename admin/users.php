<?php
require __DIR__ . '/includes/auth.php';
require_admin('users.view');
$admin_title = 'Users & Roles';
$rows = db_all(
    "SELECT u.id, u.name, u.email, u.status, u.last_login_at,
            (SELECT r.label FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=u.id LIMIT 1) AS role
     FROM users u ORDER BY u.id DESC"
);
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title">Users &amp; Roles</h2><p class="page-subtitle"><?= count($rows) ?> user<?= count($rows) === 1 ? '' : 's' ?></p></div>
  <?php if (user_can('users.create')): ?><div class="page-actions"><a href="<?= e(url('admin/user-form.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><i class="fa-solid fa-user-plus"></i> New user</a></div><?php endif; ?>
</div>
<div class="overflow-hidden rounded-2xl border border-edge bg-surface shadow-card">
  <div class="scroll-x"><table class="w-full text-left text-sm">
    <thead class="border-b border-edge bg-surface-sunken text-xs uppercase tracking-wide text-content-muted"><tr>
      <th class="px-4 py-3 font-semibold">User</th><th class="px-4 py-3 font-semibold">Role</th>
      <th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Last login</th>
      <th class="px-4 py-3 text-right font-semibold">Actions</th></tr></thead>
    <tbody class="divide-y divide-edge">
      <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-surface-sunken/50">
          <td class="px-4 py-3"><div class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white"><?= e(mb_strtoupper(mb_substr((string) $r['name'], 0, 1))) ?></span><div><div class="font-medium text-content"><?= e($r['name']) ?></div><div class="text-xs text-content-muted"><?= e($r['email']) ?></div></div></div></td>
          <td class="px-4 py-3"><span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700"><?= e($r['role'] ?: '—') ?></span></td>
          <td class="px-4 py-3"><?= $r['status'] === 'active' ? '<span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-semibold text-success-700">Active</span>' : '<span class="rounded-full bg-surface-sunken px-2 py-0.5 text-xs font-semibold text-content-muted ring-1 ring-inset ring-edge">' . e(ucfirst($r['status'])) . '</span>' ?></td>
          <td class="px-4 py-3 text-content-muted"><?= $r['last_login_at'] ? e(date('d M Y', strtotime((string) $r['last_login_at']))) : 'Never' ?></td>
          <td class="px-4 py-3"><div class="row-actions">
            <?php if (user_can('users.edit')): ?><a href="<?= e(url('admin/user-form.php?id=' . (int) $r['id'])) ?>" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50">Edit</a><?php endif; ?>
            <?php if (user_can('users.delete') && (int) $r['id'] !== (int) ($_SESSION['uid'] ?? 0)): ?><button type="button" onclick="eskDelete('users', <?= (int) $r['id'] ?>, '<?= e(addslashes((string) $r['name'])) ?>')" class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50">Delete</button><?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
