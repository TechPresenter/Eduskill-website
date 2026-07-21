<?php
require __DIR__ . '/includes/auth.php';
require_admin('campaigns.manage');

$id = (int) ($_GET['id'] ?? 0);
$c = $id > 0 ? db_one("SELECT * FROM campaigns WHERE id = ? AND deleted_at IS NULL", [$id]) : null;
if ($id > 0 && $c === null) {
    redirect('admin/campaigns.php');
}
$admin_title = $id ? 'Edit campaign' : 'New campaign';
$v = fn (string $k, $d = '') => e((string) ($c[$k] ?? $d));
$money = fn (string $k) => $c ? number_format((int) ($c[$k] ?? 0) / 100, 2, '.', '') : '';
$in = 'mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
require __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div><h2 class="page-title"><?= $id ? 'Edit' : 'New' ?> campaign</h2></div>
  <div class="page-actions"><a href="<?= e(url('admin/campaigns.php')) ?>" class="rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm font-medium text-content hover:bg-surface-sunken">Back</a></div>
</div>

<form id="campaign-form" class="max-w-3xl">
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <div class="rounded-card border border-edge bg-surface p-6 shadow-card">
    <div class="form-grid">
      <div class="form-full"><label class="block text-sm font-medium text-content">Title *</label><input name="title" required value="<?= $v('title') ?>" class="<?= $in ?>"></div>
      <div class="form-full"><label class="block text-sm font-medium text-content">Short summary</label><textarea name="summary" rows="2" class="<?= $in ?>"><?= $v('summary') ?></textarea></div>
      <div class="form-full"><label class="block text-sm font-medium text-content">Full description</label><textarea name="description" rows="6" class="<?= $in ?> font-mono text-xs"><?= $v('description') ?></textarea><span class="mt-1 block text-2xs text-content-subtle">Basic HTML allowed; cleaned on save.</span></div>
      <div><label class="block text-sm font-medium text-content">Goal amount (₹) *</label><input type="number" step="0.01" name="goal_amount" required value="<?= $money('goal_amount') ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Raised so far (₹)</label><input type="number" step="0.01" name="raised_amount" value="<?= $money('raised_amount') ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Donor count</label><input type="number" name="donor_count" value="<?= $v('donor_count', '0') ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Category</label><input name="category" value="<?= $v('category') ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">Start date</label><input type="date" name="starts_at" value="<?= $v('starts_at') ?>" class="<?= $in ?>"></div>
      <div><label class="block text-sm font-medium text-content">End date</label><input type="date" name="ends_at" value="<?= $v('ends_at') ?>" class="<?= $in ?>"></div>
      <div class="form-full"><label class="block text-sm font-medium text-content">Cover image</label><?= upload_widget('image', (string) ($c['image'] ?? '')) ?></div>
      <div><label class="block text-sm font-medium text-content">Status</label><select name="status" class="<?= $in ?>">
        <?php foreach (['draft' => 'Draft', 'active' => 'Active', 'completed' => 'Completed', 'closed' => 'Closed'] as $val => $lab): ?>
          <option value="<?= $val ?>" <?= ($c['status'] ?? 'draft') === $val ? 'selected' : '' ?>><?= $lab ?></option>
        <?php endforeach; ?>
      </select></div>
      <div class="form-full"><label class="flex items-center gap-2.5"><input type="checkbox" name="is_featured" value="1" <?= (int) ($c['is_featured'] ?? 0) === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-edge text-brand-600"><span class="text-sm font-medium text-content">Feature on homepage</span></label></div>
    </div>
    <div class="form-actions">
      <a href="<?= e(url('admin/campaigns.php')) ?>" class="text-sm font-medium text-content-muted hover:text-content">Cancel</a>
      <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><?= $id ? 'Save changes' : 'Create' ?></button>
    </div>
  </div>
</form>

<?php
$admin_scripts = <<<'JS'
<script>
(function () {
  var f = document.getElementById('campaign-form');
  f.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(f);
    var id = fd.get('id');
    var data = {};
    fd.forEach(function (v, k) { data[k] = v; });
    data.is_featured = f.querySelector('[name=is_featured]').checked ? 1 : 0;
    var path = id && id !== '0' ? 'campaigns.php?id=' + id : 'campaigns.php';
    var method = id && id !== '0' ? 'PUT' : 'POST';
    var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    window.eskApi(path, method, data).then(function (d) {
      btn.disabled = false;
      if (d.ok) {
        Swal.fire({ icon: 'success', title: 'Saved', timer: 1000, showConfirmButton: false })
          .then(function () { location.href = (document.querySelector('meta[name=base-url]').content) + '/admin/campaigns.php'; });
      } else {
        Swal.fire({ icon: 'error', title: 'Could not save', text: d.message || 'Please check the form.' });
      }
    });
  });
})();
</script>
JS;
require __DIR__ . '/includes/footer.php';
