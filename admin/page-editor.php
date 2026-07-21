<?php
require __DIR__ . '/includes/auth.php';
require_admin('pages.edit');

$id = (int) ($_GET['id'] ?? 0);
$page = db_one("SELECT * FROM pages WHERE id = ? AND deleted_at IS NULL", [$id]);
if ($page === null) {
    redirect('admin/pages.php');
}

/* ---- save: rebuild page_sections from posted data ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        flash('error', 'Session expired. Please try again.');
        redirect('admin/page-editor.php?id=' . $id);
    }
    $raw = is_array($_POST['sections'] ?? null) ? $_POST['sections'] : [];
    $sections = [];
    foreach ($raw as $s) {
        if (!is_array($s)) {
            continue;
        }
        $type = (string) ($s['type'] ?? '');
        if (!isset(section_schema()[$type])) {
            continue;
        }
        $sections[] = [
            'type' => $type,
            'position' => (int) ($s['position'] ?? 0),
            'visible' => !empty($s['visible']) ? 1 : 0,
            'settings' => section_build_settings($type, is_array($s['settings'] ?? null) ? $s['settings'] : []),
        ];
    }
    usort($sections, fn ($a, $b) => $a['position'] <=> $b['position']);

    $publish = ($_POST['do'] ?? '') === 'publish';
    db_transaction(function () use ($id, $sections, $publish) {
        db_exec("UPDATE pages SET status = ?, published_at = " . ($publish ? 'NOW()' : 'published_at') . " WHERE id = ?", [$publish ? 'published' : 'draft', $id]);
        db_exec("DELETE FROM page_sections WHERE page_id = ?", [$id]);
        $pos = 1;
        foreach ($sections as $s) {
            db_insert(
                "INSERT INTO page_sections (page_id, type, position, settings_json, is_visible) VALUES (?, ?, ?, ?, ?)",
                [$id, $s['type'], $pos++, json_encode($s['settings'], JSON_UNESCAPED_UNICODE), $s['visible']]
            );
        }
    });
    activity_log($publish ? 'page.published' : 'page.saved', (int) ($_SESSION['uid'] ?? 0));
    flash('success', $publish ? 'Page published — it is now live.' : 'Draft saved.');
    redirect('admin/page-editor.php?id=' . $id);
}

$rows = db_all("SELECT type, position, settings_json, is_visible FROM page_sections WHERE page_id = ? ORDER BY position", [$id]);
$existing = array_map(fn ($r) => ['type' => $r['type'], 'visible' => (bool) $r['is_visible'], 'settings' => json_decode((string) $r['settings_json'], true) ?: []], $rows);
$schema = section_schema();
$admin_title = 'Edit: ' . $page['title'];
$in = 'mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';

/** Render one field (uses aria-label, no id, so cloned templates don't collide). */
$field = function (array $f, string $prefix, mixed $value) use ($in): string {
    [$name, $label, $type] = $f;
    $n = $prefix . '[' . $name . ']';
    $v = is_scalar($value) ? (string) $value : '';
    ob_start(); ?>
    <div>
      <span class="block text-xs font-medium text-content-muted"><?= e($label) ?></span>
      <?php if ($type === 'textarea'): ?>
        <textarea name="<?= e($n) ?>" rows="2" aria-label="<?= e($label) ?>" class="<?= $in ?>"><?= e($v) ?></textarea>
      <?php elseif ($type === 'richtext'): ?>
        <textarea name="<?= e($n) ?>" rows="6" aria-label="<?= e($label) ?>" class="<?= $in ?> font-mono text-xs"><?= e($v) ?></textarea><span class="mt-1 block text-2xs text-content-subtle">Basic HTML allowed; cleaned on save.</span>
      <?php elseif ($type === 'number'): ?>
        <input type="number" name="<?= e($n) ?>" value="<?= e($v) ?>" aria-label="<?= e($label) ?>" class="<?= $in ?>">
      <?php elseif ($type === 'image'): ?>
        <?= upload_widget($n, $v) ?>
      <?php else: ?>
        <input type="text" name="<?= e($n) ?>" value="<?= e($v) ?>" aria-label="<?= e($label) ?>" class="<?= $in ?>">
      <?php endif; ?>
    </div>
    <?php return (string) ob_get_clean();
};
$row = function (string $cardKey, array $rep, string $rowKey, array $values) use ($field): string {
    $prefix = 'sections[' . $cardKey . '][settings][' . $rep['name'] . '][' . $rowKey . ']';
    ob_start(); ?>
    <div class="repeat-row rounded-lg border border-edge bg-surface-sunken p-3">
      <div class="flex items-start gap-3">
        <div class="grid flex-1 gap-2 sm:grid-cols-2"><?php foreach ($rep['fields'] as $rf) {
            echo $field($rf, $prefix, $values[$rf[0]] ?? '');
        } ?></div>
        <button type="button" class="row-remove mt-5 shrink-0 rounded-lg p-1.5 text-danger-600 hover:bg-danger-50" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
      </div>
    </div>
    <?php return (string) ob_get_clean();
};
$card = function (string $key, string $type, array $section, array $def) use ($field, $row): string {
    $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
    $visible = (bool) ($section['visible'] ?? true);
    $prefix = 'sections[' . $key . '][settings]';
    ob_start(); ?>
    <div class="section-item rounded-card border border-edge bg-surface shadow-card" data-key="<?= e($key) ?>" data-type="<?= e($type) ?>">
      <input type="hidden" name="sections[<?= e($key) ?>][type]" value="<?= e($type) ?>">
      <input type="hidden" class="pos-input" name="sections[<?= e($key) ?>][position]" value="0">
      <div class="flex items-center gap-3 border-b border-edge px-4 py-3">
        <div class="flex flex-col text-content-subtle">
          <button type="button" class="section-up hover:text-content" aria-label="Up"><i class="fa-solid fa-chevron-up text-xs"></i></button>
          <button type="button" class="section-down hover:text-content" aria-label="Down"><i class="fa-solid fa-chevron-down text-xs"></i></button>
        </div>
        <span class="font-display text-sm font-semibold text-content"><?= e($def['label']) ?></span>
        <label class="ml-auto flex items-center gap-1.5 text-xs text-content-muted"><input type="checkbox" name="sections[<?= e($key) ?>][visible]" value="1" <?= $visible ? 'checked' : '' ?>> Visible</label>
        <button type="button" class="section-remove rounded-lg p-1.5 text-danger-600 hover:bg-danger-50" aria-label="Delete"><i class="fa-solid fa-trash text-xs"></i></button>
      </div>
      <div class="space-y-3 p-4">
        <?php foreach ($def['fields'] as $f) {
            echo $field($f, $prefix, $settings[$f[0]] ?? '');
        } ?>
        <?php if (isset($def['repeat'])): $rep = $def['repeat']; ?>
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-content-muted"><?= e($rep['label']) ?></p>
            <div class="repeat-rows mt-2 space-y-2"><?php foreach ((is_array($settings[$rep['name']] ?? null) ? $settings[$rep['name']] : []) as $ri => $rv) {
                echo $row($key, $rep, 'r' . $ri, is_array($rv) ? $rv : []);
            } ?></div>
            <button type="button" class="repeat-add mt-2 text-sm font-medium text-brand-600 hover:underline">+ Add item</button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php return (string) ob_get_clean();
};
require __DIR__ . '/includes/header.php';
?>
<form method="post" id="page-editor">
  <?= csrf_field() ?><input type="hidden" name="do" id="do-field" value="draft">
  <div class="page-header">
    <div><h2 class="page-title"><?= e($page['title']) ?></h2><p class="page-subtitle">/<?= e($page['slug'] === 'home' ? '' : $page['slug']) ?> · <a href="<?= e(url($page['slug'] === 'home' ? 'index.php' : $page['slug'] . '.php')) ?>" target="_blank" class="text-brand-600 hover:underline">View</a></p></div>
    <div class="page-actions"><a href="<?= e(url('admin/pages.php')) ?>" class="rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm font-medium text-content hover:bg-surface-sunken">All pages</a></div>
  </div>

  <div id="sections" class="space-y-4">
    <?php foreach ($existing as $i => $s) {
        if (isset($schema[$s['type']])) {
            echo $card('s' . $i, $s['type'], $s, $schema[$s['type']]);
        }
    } ?>
  </div>
  <div id="sections-empty" class="mt-4 rounded-card border border-dashed border-edge p-8 text-center text-sm text-content-muted <?= $existing !== [] ? 'hidden' : '' ?>">No sections yet. Add one below.</div>

  <div class="mt-5 flex flex-wrap items-center gap-2 rounded-card border border-edge bg-surface p-3">
    <label class="text-sm font-medium text-content" for="add-section-type">Add a section:</label>
    <select id="add-section-type" class="rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none"><?php foreach ($schema as $type => $def): ?><option value="<?= e($type) ?>"><?= e($def['label']) ?></option><?php endforeach; ?></select>
    <button type="button" id="add-section-btn" class="rounded-lg bg-brand-600 px-3.5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700">+ Add</button>
  </div>

  <div class="form-actions">
    <button type="submit" onclick="document.getElementById('do-field').value='draft'" class="rounded-lg border border-edge bg-surface px-4 py-2 text-sm font-semibold text-content hover:bg-surface-sunken">Save draft</button>
    <button type="submit" onclick="document.getElementById('do-field').value='publish'" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700">Publish</button>
  </div>
</form>

<?php foreach ($schema as $type => $def): ?>
  <template class="section-template" data-type="<?= e($type) ?>"><?= $card('__KEY__', $type, ['type' => $type, 'visible' => true, 'settings' => []], $def) ?></template>
  <?php if (isset($def['repeat'])): ?><template class="row-template" data-type="<?= e($type) ?>"><?= $row('__KEY__', $def['repeat'], '__ROW__', []) ?></template><?php endif; ?>
<?php endforeach; ?>

<?php $admin_scripts = '<script src="' . e(asset('js/page-editor.js')) . '"></script>'; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
