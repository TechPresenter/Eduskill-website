<?php
/**
 * Reusable CRUD engine for the flat-file admin + REST API.
 *
 * Each content module is a config in resource_config(). Three reusable functions drive everything so
 * a module is just a thin flat file (api/programs.php, admin/programs.php) that calls them — exactly
 * the "flat pages that call reusable functions from includes/" pattern:
 *
 *   api_resource($key)          — full REST handler (GET list/one public, POST/PUT/DELETE auth+CSRF)
 *   admin_resource_table($key)  — echoes the admin list table
 *   admin_resource_form($key)   — echoes the add/edit form + the fetch-submit script
 *
 * Column names come only from these configs (developer-authored), so building SQL from them is safe;
 * every value is a bound parameter and every field is cast/sanitized by its declared type.
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

/** @return array<string,mixed>|null */
function resource_config(string $key): ?array
{
    static $configs = null;
    if ($configs === null) {
        $configs = [
            'programs' => [
                'table' => 'programs', 'singular' => 'Programme', 'plural' => 'Programmes',
                'permission' => 'programs.manage', 'soft_delete' => true, 'slug_from' => 'title',
                'order_by' => 'position ASC, id DESC',
                'columns' => [['title', 'Title'], ['kind', 'Type', 'badge'], ['is_active', 'Active', 'bool']],
                'fields' => [
                    ['title', 'Title', 'text', true], ['summary', 'Short summary', 'textarea'],
                    ['description', 'Full description', 'richtext'], ['image', 'Image', 'image'],
                    ['icon', 'Icon name', 'text'],
                    ['kind', 'Type', 'select', false, ['program' => 'Programme', 'scheme' => 'Scheme']],
                    ['position', 'Sort order', 'number'], ['is_active', 'Active', 'checkbox'],
                ],
            ],
            'events' => [
                'table' => 'events', 'singular' => 'Event', 'plural' => 'Events',
                'permission' => 'events.manage', 'soft_delete' => true, 'slug_from' => 'title',
                'order_by' => 'starts_at DESC, id DESC',
                'columns' => [['title', 'Title'], ['starts_at', 'Starts'], ['status', 'Status', 'badge']],
                'fields' => [
                    ['title', 'Title', 'text', true], ['summary', 'Short summary', 'textarea'],
                    ['description', 'Full description', 'richtext'], ['image', 'Image', 'image'],
                    ['location', 'Location', 'text'], ['starts_at', 'Starts at', 'datetime'],
                    ['ends_at', 'Ends at', 'datetime'],
                    ['status', 'Status', 'select', false, ['draft' => 'Draft', 'published' => 'Published']],
                ],
            ],
            'team' => [
                'table' => 'team_members', 'singular' => 'Team member', 'plural' => 'Team',
                'permission' => 'team.manage', 'soft_delete' => true,
                'order_by' => 'position ASC, id DESC',
                'columns' => [['name', 'Name'], ['role_title', 'Role'], ['is_active', 'Active', 'bool']],
                'fields' => [
                    ['name', 'Name', 'text', true], ['role_title', 'Role / title', 'text'],
                    ['photo', 'Photo', 'image'], ['bio', 'Bio', 'textarea'],
                    ['position', 'Sort order', 'number'], ['is_active', 'Active', 'checkbox'],
                ],
            ],
            'testimonials' => [
                'table' => 'testimonials', 'singular' => 'Testimonial', 'plural' => 'Testimonials',
                'permission' => 'testimonials.manage', 'soft_delete' => true,
                'order_by' => 'position ASC, id DESC',
                'columns' => [['author_name', 'Name'], ['author_role', 'Role'], ['rating', 'Rating'], ['is_active', 'Active', 'bool']],
                'fields' => [
                    ['author_name', 'Name', 'text', true], ['author_role', 'Role / affiliation', 'text'],
                    ['quote', 'Quote', 'textarea', true], ['rating', 'Rating (1-5)', 'number'],
                    ['photo', 'Photo', 'image'], ['video_url', 'Video URL', 'url'],
                    ['position', 'Sort order', 'number'], ['is_active', 'Active', 'checkbox'],
                ],
            ],
            'faqs' => [
                'table' => 'faqs', 'singular' => 'FAQ', 'plural' => 'FAQs',
                'permission' => 'faqs.manage', 'soft_delete' => true,
                'order_by' => 'category ASC, position ASC, id DESC',
                'columns' => [['question', 'Question'], ['category', 'Category'], ['is_active', 'Active', 'bool']],
                'fields' => [
                    ['question', 'Question', 'text', true], ['answer', 'Answer', 'textarea', true],
                    ['category', 'Category', 'text'], ['position', 'Sort order', 'number'],
                    ['is_active', 'Active', 'checkbox'],
                ],
            ],
            'blogs' => [
                'table' => 'posts', 'singular' => 'Post', 'plural' => 'Blog & News',
                'permission' => 'blog.view', 'soft_delete' => true, 'slug_from' => 'title',
                'order_by' => 'COALESCE(published_at, created_at) DESC, id DESC',
                'columns' => [['title', 'Title'], ['status', 'Status', 'badge']],
                'fields' => [
                    ['title', 'Title', 'text', true],
                    ['category_id', 'Category', 'select_dynamic', false, ['table' => 'post_categories', 'value' => 'id', 'label' => 'name']],
                    ['excerpt', 'Excerpt', 'textarea'],
                    ['body', 'Content', 'richtext'],
                    ['featured_image', 'Featured image', 'image'],
                    ['published_at', 'Publish date', 'datetime'],
                    ['status', 'Status', 'select', false, ['draft' => 'Draft', 'published' => 'Published']],
                ],
            ],
            'gallery' => [
                'table' => 'galleries', 'singular' => 'Gallery', 'plural' => 'Galleries',
                'permission' => 'gallery.manage', 'soft_delete' => true, 'slug_from' => 'title',
                'order_by' => 'id DESC',
                'columns' => [['title', 'Title'], ['kind', 'Type', 'badge'], ['is_active', 'Active', 'bool']],
                'fields' => [
                    ['title', 'Title', 'text', true],
                    ['kind', 'Type', 'select', false, ['photo' => 'Photo', 'video' => 'Video']],
                    ['description', 'Description', 'textarea'],
                    ['is_active', 'Active', 'checkbox'],
                ],
            ],
        ];
    }
    return $configs[$key] ?? null;
}

/** Cast + sanitize one posted value per its field type. */
function crud_cast(string $type, mixed $value, array $options = []): mixed
{
    return match ($type) {
        'number' => (int) $value,
        'money' => (int) round((float) $value * 100),
        'checkbox' => !empty($value) ? 1 : 0,
        'richtext' => clean_html((string) ($value ?? '')),
        'date', 'datetime' => trim((string) ($value ?? '')) !== '' ? str_replace('T', ' ', trim((string) $value)) : null,
        'select_dynamic' => trim((string) ($value ?? '')) !== '' ? (int) $value : null,
        'select' => array_key_exists((string) $value, $options) ? (string) $value : (string) array_key_first($options),
        default => trim((string) ($value ?? '')),
    };
}

/** Build the clean column=>value set for insert/update from posted input. */
function crud_build(array $config, array $in): array
{
    $data = [];
    foreach ($config['fields'] as $f) {
        [$name, , $type] = $f;
        $data[$name] = crud_cast($type, $in[$name] ?? null, $f[4] ?? []);
    }
    return $data;
}

/* ---------------------------------------------------------------- REST handler */

function api_resource(string $key): never
{
    $cfg = resource_config($key);
    if ($cfg === null) {
        json_error('Unknown resource.', 404);
    }
    $table = $cfg['table'];
    $soft = !empty($cfg['soft_delete']);
    $method = request_method();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $where = $soft ? 'WHERE deleted_at IS NULL' : '';

    if ($method === 'GET') {
        if ($id > 0) {
            $row = db_one("SELECT * FROM {$table} WHERE id = ? " . ($soft ? 'AND deleted_at IS NULL' : ''), [$id]);
            $row === null ? json_error('Not found.', 404) : json_ok(['data' => $row]);
        }
        json_ok(['data' => db_all("SELECT * FROM {$table} {$where} ORDER BY " . $cfg['order_by'])]);
    }

    api_require($cfg['permission']);
    $in = api_body();
    if (!verify_csrf($in['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null))) {
        json_error('Invalid or expired security token.', 400);
    }

    if ($method === 'DELETE') {
        if ($id <= 0) {
            json_error('Missing id.', 400);
        }
        $soft
            ? db_exec("UPDATE {$table} SET deleted_at = NOW() WHERE id = ?", [$id])
            : db_exec("DELETE FROM {$table} WHERE id = ?", [$id]);
        activity_log($key . '.deleted', (int) ($_SESSION['uid'] ?? 0));
        json_ok([], $cfg['singular'] . ' deleted.');
    }

    // POST (create) / PUT (update)
    $data = crud_build($cfg, $in);
    foreach ($cfg['fields'] as $f) {
        if (!empty($f[3]) && ($data[$f[0]] === '' || $data[$f[0]] === null)) {
            json_error($f[1] . ' is required.', 422);
        }
    }
    if (!empty($cfg['slug_from'])) {
        $data['slug'] = unique_slug($table, str_slug((string) ($data[$cfg['slug_from']] ?? '')), $method === 'PUT' ? $id : null);
    }

    if ($method === 'POST') {
        $cols = array_keys($data);
        $bind = [];
        foreach ($data as $k => $v) {
            $bind[":{$k}"] = $v;
        }
        $newId = db_insert("INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $bind);
        activity_log($key . '.created', (int) ($_SESSION['uid'] ?? 0));
        json_ok(['id' => $newId], $cfg['singular'] . ' created.');
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        if ($id <= 0) {
            json_error('Missing id.', 400);
        }
        $set = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $bind = [':id' => $id];
        foreach ($data as $k => $v) {
            $bind[":{$k}"] = $v;
        }
        db_exec("UPDATE {$table} SET {$set} WHERE id = :id" . ($soft ? ' AND deleted_at IS NULL' : ''), $bind);
        activity_log($key . '.updated', (int) ($_SESSION['uid'] ?? 0));
        json_ok([], $cfg['singular'] . ' updated.');
    }

    json_error('Method not allowed.', 405);
}

/* ---------------------------------------------------------------- admin list */

function admin_resource_table(string $key): void
{
    $cfg = resource_config($key);
    if ($cfg === null) {
        echo '<p class="text-danger-600">Unknown resource.</p>';
        return;
    }
    $where = !empty($cfg['soft_delete']) ? 'WHERE deleted_at IS NULL' : '';
    $rows = db_all("SELECT * FROM {$cfg['table']} {$where} ORDER BY " . $cfg['order_by']);
    ?>
    <div class="page-header">
      <div><h2 class="page-title"><?= e($cfg['plural']) ?></h2><p class="page-subtitle"><?= count($rows) ?> item<?= count($rows) === 1 ? '' : 's' ?></p></div>
      <div class="page-actions"><a href="<?= e(url('admin/' . $key . '-form.php')) ?>" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700">+ New <?= e(strtolower((string) $cfg['singular'])) ?></a></div>
    </div>
    <div class="overflow-hidden rounded-card border border-edge bg-surface shadow-card">
      <?php if ($rows === []): ?>
        <div class="p-10 text-center text-sm text-content-muted">Nothing here yet. Create your first <?= e(strtolower((string) $cfg['singular'])) ?>.</div>
      <?php else: ?>
        <div class="scroll-x"><table class="w-full text-left text-sm">
          <thead class="border-b border-edge bg-surface-sunken text-xs uppercase tracking-wide text-content-muted"><tr>
            <?php foreach ($cfg['columns'] as $col): ?><th class="px-4 py-3 font-semibold"><?= e($col[1]) ?></th><?php endforeach; ?>
            <th class="px-4 py-3 text-right font-semibold">Actions</th></tr></thead>
          <tbody class="divide-y divide-edge">
            <?php foreach ($rows as $row): ?>
              <tr class="hover:bg-surface-sunken/50">
                <?php foreach ($cfg['columns'] as $col): $val = $row[$col[0]] ?? ''; $type = $col[2] ?? 'text'; ?>
                  <td class="px-4 py-3 text-content">
                    <?php if ($type === 'bool'): ?>
                      <?= (int) $val === 1 ? '<span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-semibold text-success-700">Yes</span>' : '<span class="rounded-full bg-surface-sunken px-2 py-0.5 text-xs font-semibold text-content-muted ring-1 ring-inset ring-edge">No</span>' ?>
                    <?php elseif ($type === 'badge'): ?>
                      <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700"><?= e((string) $val) ?></span>
                    <?php else: ?>
                      <?= e(mb_strlen((string) $val) > 60 ? mb_substr((string) $val, 0, 60) . '…' : (string) $val) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td class="px-4 py-3"><div class="row-actions">
                  <a href="<?= e(url('admin/' . $key . '-form.php?id=' . (int) $row['id'])) ?>" class="rounded-lg px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50">Edit</a>
                  <button type="button" onclick="eskDelete('<?= e($key) ?>', <?= (int) $row['id'] ?>, 'this item')" class="rounded-lg px-3 py-1.5 text-sm font-medium text-danger-600 hover:bg-danger-50">Delete</button>
                </div></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
    <?php
}

/* ---------------------------------------------------------------- admin form */

function admin_resource_form(string $key): void
{
    $cfg = resource_config($key);
    if ($cfg === null) {
        echo '<p class="text-danger-600">Unknown resource.</p>';
        return;
    }
    $id = (int) ($_GET['id'] ?? 0);
    $soft = !empty($cfg['soft_delete']);
    $row = $id > 0 ? db_one("SELECT * FROM {$cfg['table']} WHERE id = ? " . ($soft ? 'AND deleted_at IS NULL' : ''), [$id]) : null;
    if ($id > 0 && $row === null) {
        redirect('admin/' . $key . '.php');
    }
    $in = 'mt-1 w-full rounded-lg border border-edge bg-surface px-3 py-2 text-sm text-content focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20';
    ?>
    <div class="page-header">
      <div><h2 class="page-title"><?= $id ? 'Edit' : 'New' ?> <?= e(strtolower((string) $cfg['singular'])) ?></h2></div>
      <div class="page-actions"><a href="<?= e(url('admin/' . $key . '.php')) ?>" class="rounded-lg border border-edge bg-surface px-3.5 py-2 text-sm font-medium text-content hover:bg-surface-sunken">Back</a></div>
    </div>
    <form id="resource-form" data-resource="<?= e($key) ?>" class="max-w-3xl">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <div class="rounded-card border border-edge bg-surface p-6 shadow-card">
        <div class="form-grid">
          <?php foreach ($cfg['fields'] as $f): [$name, $label, $type] = $f; $required = !empty($f[3]); $opts = $f[4] ?? [];
            $raw = $row[$name] ?? ''; $wide = in_array($type, ['textarea', 'richtext'], true); ?>
            <div class="<?= $wide ? 'form-full' : '' ?>">
              <?php if ($type === 'checkbox'): ?>
                <label class="flex items-center gap-2.5 py-2"><input type="checkbox" name="<?= e($name) ?>" value="1" <?= (int) $raw === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-edge text-brand-600"><span class="text-sm font-medium text-content"><?= e($label) ?></span></label>
              <?php else: ?>
                <label class="block text-sm font-medium text-content"><?= e($label) ?><?= $required ? ' <span class="text-danger-500">*</span>' : '' ?></label>
                <?php if ($type === 'textarea'): ?>
                  <textarea name="<?= e($name) ?>" rows="3" class="<?= $in ?>"><?= e((string) $raw) ?></textarea>
                <?php elseif ($type === 'richtext'): ?>
                  <textarea name="<?= e($name) ?>" rows="6" class="<?= $in ?> font-mono text-xs"><?= e((string) $raw) ?></textarea><span class="mt-1 block text-2xs text-content-subtle">Basic HTML allowed; cleaned on save.</span>
                <?php elseif ($type === 'select'): ?>
                  <select name="<?= e($name) ?>" class="<?= $in ?>"><?php foreach ($opts as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= (string) $raw === (string) $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?></select>
                <?php elseif ($type === 'select_dynamic'): $src = $opts;
                    $choices = db_all("SELECT {$src['value']} AS v, {$src['label']} AS l FROM {$src['table']} ORDER BY l ASC"); ?>
                  <select name="<?= e($name) ?>" class="<?= $in ?>"><option value="">— none —</option><?php foreach ($choices as $ch): ?><option value="<?= e((string) $ch['v']) ?>" <?= (string) $raw === (string) $ch['v'] ? 'selected' : '' ?>><?= e((string) $ch['l']) ?></option><?php endforeach; ?></select>
                <?php elseif ($type === 'image'): ?>
                  <?= upload_widget($name, (string) $raw) ?>
                <?php elseif ($type === 'number'): ?>
                  <input type="number" name="<?= e($name) ?>" value="<?= e((string) $raw) ?>" class="<?= $in ?>">
                <?php elseif ($type === 'date'): ?>
                  <input type="date" name="<?= e($name) ?>" value="<?= e((string) $raw) ?>" class="<?= $in ?>">
                <?php elseif ($type === 'datetime'): ?>
                  <input type="datetime-local" name="<?= e($name) ?>" value="<?= e(str_replace(' ', 'T', (string) $raw)) ?>" class="<?= $in ?>">
                <?php else: ?>
                  <input type="text" name="<?= e($name) ?>" value="<?= e((string) $raw) ?>" class="<?= $in ?>">
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="form-actions">
          <a href="<?= e(url('admin/' . $key . '.php')) ?>" class="text-sm font-medium text-content-muted hover:text-content">Cancel</a>
          <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-on-brand hover:bg-brand-700"><?= $id ? 'Save changes' : 'Create' ?></button>
        </div>
      </div>
    </form>
    <script>if (window.eskResourceForm) eskResourceForm(document.getElementById('resource-form'));</script>
    <?php
}
