<?php
require __DIR__ . '/includes/auth.php';
$id = (int) ($_GET['id'] ?? 0);
require_admin($id > 0 ? 'users.edit' : 'users.create');
$u = $id > 0 ? db_one('SELECT id, name, email, status FROM users WHERE id = ?', [$id]) : null;
if ($id > 0 && $u === null) {
    redirect('admin/users.php');
}
$currentRole = $id > 0 ? (string) db_val('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? LIMIT 1', [$id]) : 'staff';
$roles = db_all('SELECT name, label FROM roles ORDER BY id');
$admin_title = $id ? 'Edit user' : 'New user';
$in = 'mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title"><?= $id ? 'Edit' : 'New' ?> user</h2></div>
  <div class="page-actions"><a href="<?= e(url('admin/users.php')) ?>" class="rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm font-medium text-content hover:bg-surface-sunken">Back</a></div>
</div>
<form id="resource-form" data-resource="users" class="max-w-2xl">
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="rounded-2xl border border-edge bg-surface p-6 shadow-card">
    <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
      <div><label class="block text-sm font-medium text-content">Name *</label><input name="name" required value="<?= e((string) ($u['name'] ?? '')) ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Email *</label><input type="email" name="email" required value="<?= e((string) ($u['email'] ?? '')) ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Password <?= $id ? '<span class="font-normal text-content-subtle">(leave blank to keep)</span>' : '*' ?></label><input type="password" name="password" autocomplete="new-password" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Role</label><select name="role" class="<?= $in ?>"><?php foreach ($roles as $r): ?><option value="<?= e($r['name']) ?>" <?= $currentRole === $r['name'] ? 'selected' : '' ?>><?= e($r['label']) ?></option><?php endforeach; ?></select></div>
      <div><label class="block text-sm font-medium text-content">Status</label><select name="status" class="<?= $in ?>"><?php foreach (['active' => 'Active', 'inactive' => 'Inactive', 'suspended' => 'Suspended'] as $v => $l): ?><option value="<?= $v ?>" <?= ($u['status'] ?? 'active') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="form-actions">
      <a href="<?= e(url('admin/users.php')) ?>" class="text-sm font-medium text-content-muted hover:text-content">Cancel</a>
      <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><?= $id ? 'Save changes' : 'Create user' ?></button>
    </div>
  </div>
</form>
<script>if (window.eskResourceForm) eskResourceForm(document.getElementById('resource-form'));</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
