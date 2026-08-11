<?php
/**
 * =============================================================================
 *  Admin — Contacts (CRM). Three tabs: Contacts · Lists · Segments.
 * =============================================================================
 *  ?tab=contacts|lists|segments  (default contacts)
 *  Contacts: search + status chips + segment/list/dupe filters, pagination,
 *            inline add/edit drawer, bulk actions, CSV import + export,
 *            duplicate detection, per-contact engagement history.
 *  Lists:    contact_lists grid with live member counts + create/delete.
 *  Segments: built-in smart segments with live counts + compose affordance.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

$BASE = 'email-contacts';

/* =============================================================================
 |  CSV EXPORT — must run BEFORE any HTML (before head.php) then exit.
 |============================================================================*/
if (get('export') === 'csv') {
    $rows = db_all("SELECT first_name,last_name,email,phone,company,job_title,country,tags,status,source,last_contacted_at,created_at
                    FROM contacts ORDER BY id ASC");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contacts-' . date('Y-m-d') . '.csv"');
    // Neutralize CSV/formula injection (a cell starting = + - @ TAB CR would run
    // as a formula when opened in Excel/Sheets) — the codebase convention.
    $safe = static fn($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
    $out = fopen('php://output', 'w');
    fputcsv($out, ['first_name', 'last_name', 'email', 'phone', 'company', 'job_title', 'country', 'tags', 'status', 'source', 'last_contacted_at', 'created_at']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($safe, [
            $r['first_name'], $r['last_name'], $r['email'], $r['phone'], $r['company'],
            $r['job_title'], $r['country'], $r['tags'], $r['status'], $r['source'],
            $r['last_contacted_at'], $r['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

/* =============================================================================
 |  POST HANDLERS
 |============================================================================*/
if (is_post()) {
    require_csrf();
    $do  = (string) post('_do');
    $tab = (string) post('tab', 'contacts');
    $ret = '/admin/' . $BASE . '?tab=' . (in_array($tab, ['contacts', 'lists', 'segments'], true) ? $tab : 'contacts');

    /* ---- Save (create / update) a contact — upsert by unique email --------- */
    if ($do === 'save') {
        $editId = (int) post('id', 0);
        $email  = strtolower(trim((string) post('email')));

        if (!is_email($email)) {
            set_flash('error', 'A valid email address is required.');
            redirect($ret . ($editId ? '&edit=' . $editId : '&new=1'));
        }

        $status = (string) post('status', 'active');
        if (!in_array($status, ['active', 'unsubscribed', 'bounced', 'spam'], true)) { $status = 'active'; }

        $data = [
            'first_name' => clean(post('first_name', '')) ?: null,
            'last_name'  => clean(post('last_name', '')) ?: null,
            'email'      => $email,
            'phone'      => clean(post('phone', '')) ?: null,
            'company'    => clean(post('company', '')) ?: null,
            'job_title'  => clean(post('job_title', '')) ?: null,
            'country'    => clean(post('country', '')) ?: null,
            'tags'       => clean(post('tags', '')) ?: null,
            'status'     => $status,
            'notes'      => clean(post('notes', '')) ?: null,
        ];

        $existing = find_by('contacts', 'email', $email);

        if ($editId) {
            if ($existing && (int) $existing['id'] !== $editId) {
                set_flash('error', 'Another contact already uses that email address.');
                redirect($ret . '&edit=' . $editId);
            }
            db_update('contacts', $data, 'id = :id', [':id' => $editId]);
            log_activity('update', 'contacts', 'Updated contact #' . $editId);
            set_flash('success', 'Contact updated.');
        } elseif ($existing) {
            db_update('contacts', $data, 'id = :id', [':id' => $existing['id']]);
            log_activity('update', 'contacts', 'Upserted contact #' . $existing['id'] . ' (' . $email . ')');
            set_flash('info', 'A contact with that email already existed — its details were updated instead.');
        } else {
            $data['source'] = 'manual';
            $newId = db_insert('contacts', $data);
            log_activity('create', 'contacts', 'Created contact #' . $newId);
            set_flash('success', 'Contact added.');
        }
        redirect($ret);
    }

    /* ---- Delete a single contact ------------------------------------------ */
    if ($do === 'delete') {
        $id = (int) post('id', 0);
        if ($id && find('contacts', $id)) {
            db_delete('contacts', 'id = :id', [':id' => $id]);
            log_activity('delete', 'contacts', 'Deleted contact #' . $id);
            set_flash('success', 'Contact deleted.');
        }
        redirect($ret);
    }

    /* ---- Bulk actions: add-to-list / unsubscribe / delete ----------------- */
    if ($do === 'bulk') {
        $ids    = array_values(array_filter(array_map('intval', (array) post('ids', []))));
        $action = (string) post('action');
        if (!$ids) {
            set_flash('warning', 'No contacts were selected.');
            redirect($ret);
        }
        if ($action === 'addlist') {
            $listId = (int) post('list_id', 0);
            if ($listId && find('contact_lists', $listId)) {
                $n = 0;
                foreach ($ids as $cid) {
                    db_query("INSERT IGNORE INTO contact_list_members (list_id, contact_id) VALUES (:l, :c)",
                        [':l' => $listId, ':c' => $cid]);
                    $n++;
                }
                set_flash('success', "$n contact(s) added to the list.");
            } else {
                set_flash('warning', 'Choose a list to add the selected contacts to.');
            }
        } elseif ($action === 'unsubscribe') {
            foreach ($ids as $cid) {
                db_update('contacts', ['status' => 'unsubscribed'], 'id = :id', [':id' => $cid]);
            }
            log_activity('update', 'contacts', 'Bulk unsubscribed ' . count($ids) . ' contact(s)');
            set_flash('success', count($ids) . ' contact(s) marked unsubscribed.');
        } elseif ($action === 'delete') {
            foreach ($ids as $cid) {
                db_delete('contacts', 'id = :id', [':id' => $cid]);
            }
            log_activity('delete', 'contacts', 'Bulk deleted ' . count($ids) . ' contact(s)');
            set_flash('success', count($ids) . ' contact(s) deleted.');
        } else {
            set_flash('warning', 'Unknown bulk action.');
        }
        redirect($ret);
    }

    /* ---- CSV import (tolerant of column order / case) --------------------- */
    if ($do === 'import') {
        $f = $_FILES['csv'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            set_flash('error', 'Please choose a CSV file to import.');
            redirect($ret);
        }
        $fh = fopen($f['tmp_name'], 'r');
        if (!$fh) {
            set_flash('error', 'Could not read the uploaded file.');
            redirect($ret);
        }
        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            set_flash('error', 'The CSV file appears to be empty.');
            redirect($ret);
        }
        // Map header names -> column index (case/space/underscore tolerant).
        $norm = static function (string $s): string {
            return strtolower(trim(str_replace([' ', '-'], '_', $s)));
        };
        $aliases = [
            'first_name' => 'first_name', 'firstname' => 'first_name', 'first' => 'first_name', 'fname' => 'first_name',
            'last_name'  => 'last_name',  'lastname'  => 'last_name',  'last'  => 'last_name',  'lname' => 'last_name', 'surname' => 'last_name',
            'email' => 'email', 'email_address' => 'email', 'e_mail' => 'email',
            'phone' => 'phone', 'phone_number' => 'phone', 'mobile' => 'phone', 'telephone' => 'phone',
            'company' => 'company', 'organisation' => 'company', 'organization' => 'company', 'org' => 'company',
            'job_title' => 'job_title', 'title' => 'job_title', 'role' => 'job_title',
            'country' => 'country',
            'tags' => 'tags', 'tag' => 'tags', 'labels' => 'tags',
        ];
        $col = [];
        foreach ($header as $i => $h) {
            $key = $aliases[$norm((string) $h)] ?? null;
            if ($key !== null && !isset($col[$key])) { $col[$key] = $i; }
        }
        $inserted = 0; $updated = 0; $skipped = 0;
        if (!isset($col['email'])) {
            fclose($fh);
            set_flash('error', 'No "email" column was found in the CSV header. Expected headers include: first_name, last_name, email, phone, company, tags.');
            redirect($ret);
        }
        $val = static function (array $row, array $col, string $k): ?string {
            if (!isset($col[$k])) { return null; }
            $v = trim((string) ($row[$col[$k]] ?? ''));
            return $v === '' ? null : $v;
        };
        while (($row = fgetcsv($fh)) !== false) {
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) { continue; }
            $email = strtolower(trim((string) ($row[$col['email']] ?? '')));
            if (!is_email($email)) { $skipped++; continue; }
            $data = [
                'first_name' => $val($row, $col, 'first_name'),
                'last_name'  => $val($row, $col, 'last_name'),
                'email'      => $email,
                'phone'      => $val($row, $col, 'phone'),
                'company'    => $val($row, $col, 'company'),
                'job_title'  => $val($row, $col, 'job_title'),
                'country'    => $val($row, $col, 'country'),
                'tags'       => $val($row, $col, 'tags'),
            ];
            $data = array_map(static fn ($v) => is_string($v) ? mb_substr($v, 0, 191) : $v, $data);
            $exists = find_by('contacts', 'email', $email);
            if ($exists) {
                // Only overwrite columns that carry a value in the CSV.
                $patch = array_filter($data, static fn ($v) => $v !== null && $v !== '');
                unset($patch['email']);
                if ($patch) { db_update('contacts', $patch, 'id = :id', [':id' => $exists['id']]); }
                $updated++;
            } else {
                $data['status'] = 'active';
                $data['source'] = 'csv-import';
                db_insert('contacts', $data);
                $inserted++;
            }
        }
        fclose($fh);
        log_activity('import', 'contacts', "CSV import: $inserted new, $updated updated, $skipped skipped");
        set_flash('success', "Import finished — $inserted added, $updated updated, $skipped skipped.");
        redirect($ret);
    }

    /* ---- Create / update a contact list ----------------------------------- */
    if ($do === 'save_list') {
        $listId = (int) post('id', 0);
        $name   = clean(post('name', ''));
        if ($name === '') {
            set_flash('error', 'List name is required.');
            redirect('/admin/' . $BASE . '?tab=lists');
        }
        $color = (string) post('color', '#0B4E3D');
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) { $color = '#0B4E3D'; }
        $data = [
            'name'        => mb_substr($name, 0, 128),
            'description' => clean(post('description', '')) ?: null,
            'color'       => $color,
        ];
        if ($listId && find('contact_lists', $listId)) {
            db_update('contact_lists', $data, 'id = :id', [':id' => $listId]);
            set_flash('success', 'List updated.');
        } else {
            db_insert('contact_lists', $data);
            set_flash('success', 'List created.');
        }
        redirect('/admin/' . $BASE . '?tab=lists');
    }

    /* ---- Delete a contact list (members cascade) -------------------------- */
    if ($do === 'delete_list') {
        $listId = (int) post('id', 0);
        if ($listId && find('contact_lists', $listId)) {
            db_delete('contact_lists', 'id = :id', [':id' => $listId]);
            log_activity('delete', 'contact_lists', 'Deleted list #' . $listId);
            set_flash('success', 'List deleted.');
        }
        redirect('/admin/' . $BASE . '?tab=lists');
    }

    redirect($ret);
}

/* =============================================================================
 |  GET — shared state
 |============================================================================*/
$tab = (string) get('tab', 'contacts');
if (!in_array($tab, ['contacts', 'lists', 'segments'], true)) { $tab = 'contacts'; }

$counts = [
    'total'        => (int) db_value("SELECT COUNT(*) FROM contacts"),
    'active'       => (int) db_value("SELECT COUNT(*) FROM contacts WHERE status = 'active'"),
    'unsubscribed' => (int) db_value("SELECT COUNT(*) FROM contacts WHERE status = 'unsubscribed'"),
    'bounced'      => (int) db_value("SELECT COUNT(*) FROM contacts WHERE status = 'bounced'"),
];

// Duplicate detection: contacts that share a (trimmed) phone number.
$dupeCount = (int) db_value(
    "SELECT COUNT(*) FROM contacts c
     JOIN (SELECT phone FROM contacts WHERE phone IS NOT NULL AND TRIM(phone) <> ''
           GROUP BY phone HAVING COUNT(*) > 1) d ON c.phone = d.phone"
);

$statusMeta = [
    'active'       => ['Active', 'pill-green'],
    'unsubscribed' => ['Unsubscribed', 'pill-gray'],
    'bounced'      => ['Bounced', 'pill-amber'],
    'spam'         => ['Spam', 'pill-red'],
];

$allLists = db_all("SELECT * FROM contact_lists ORDER BY name ASC");

/* -------- Contacts-tab query state -------- */
$q      = trim((string) get('q', ''));
$status = (string) get('status', '');
if (!array_key_exists($status, $statusMeta)) { $status = ''; }
$seg        = (string) get('seg', '');
$listFilter = (int) get('list', 0);
$dupes      = (bool) get('dupes', 0);

$editId = (int) get('edit', 0);
$edit   = $editId ? find('contacts', $editId) : null;
$showForm = $edit || get('new');

// Engagement history for the contact being edited.
$histEvents = []; $histMessages = [];
if ($edit) {
    $ce = (string) $edit['email'];
    $histEvents = db_all("SELECT event_type, url, device, browser, country, created_at
                          FROM email_events WHERE recipient = :e
                          ORDER BY created_at DESC LIMIT 12", [':e' => $ce]);
    $histMessages = db_all("SELECT id, subject, folder, direction, sent_at, created_at
                            FROM email_messages
                            WHERE (to_email = :e1 OR from_email = :e2) AND deleted_at IS NULL
                            ORDER BY COALESCE(sent_at, created_at) DESC LIMIT 8",
                            [':e1' => $ce, ':e2' => $ce]);
}

/* -------- Build the contacts list query -------- */
$where  = ['1=1'];
$params = [];
$join   = '';
if ($q !== '') {
    $where[] = "CONCAT_WS(' ', c.first_name, c.last_name, c.email, c.company, c.tags) LIKE :q";
    $params[':q'] = '%' . $q . '%';
}
if ($status !== '') {
    $where[] = "c.status = :st";
    $params[':st'] = $status;
}
if ($seg === 'donors') {
    $where[] = "c.tags LIKE :seg"; $params[':seg'] = '%donor%';
} elseif ($seg === 'volunteers') {
    $where[] = "c.tags LIKE :seg"; $params[':seg'] = '%volunteer%';
} elseif ($seg === 'recent') {
    $where[] = "c.last_contacted_at IS NOT NULL AND c.last_contacted_at >= (NOW() - INTERVAL 30 DAY)";
}
if ($listFilter) {
    $join .= " JOIN contact_list_members clm ON clm.contact_id = c.id AND clm.list_id = :lid";
    $params[':lid'] = $listFilter;
}
if ($dupes) {
    $join .= " JOIN (SELECT phone FROM contacts WHERE phone IS NOT NULL AND TRIM(phone) <> ''
                     GROUP BY phone HAVING COUNT(*) > 1) dup ON c.phone = dup.phone";
}
$listName = $listFilter ? (string) (db_value("SELECT name FROM contact_lists WHERE id = :i", [':i' => $listFilter]) ?? '') : '';

$p = ($tab === 'contacts')
    ? paginate("SELECT c.* FROM contacts c$join WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC, c.id DESC", $params, 15)
    : ['items' => [], 'total' => 0, 'links' => ''];

/* -------- Lists tab data -------- */
$lists = ($tab === 'lists')
    ? db_all("SELECT cl.*, (SELECT COUNT(*) FROM contact_list_members m WHERE m.list_id = cl.id) AS member_count
              FROM contact_lists cl ORDER BY cl.name ASC")
    : [];

/* -------- Segments tab data (live counts) -------- */
$segments = [];
if ($tab === 'segments') {
    $segments = [
        ['key' => 'active',      'label' => 'Active contacts',    'icon' => 'user-check',   'color' => 'pill-green', 'desc' => 'Subscribed and reachable.',                 'count' => $counts['active']],
        ['key' => 'unsubscribed','label' => 'Unsubscribed',       'icon' => 'user-x',       'color' => 'pill-gray',  'desc' => 'Opted out — excluded from sends.',          'count' => $counts['unsubscribed']],
        ['key' => 'donors',      'label' => 'Donors',             'icon' => 'heart-handshake','color' => 'pill-violet','desc' => 'Tagged “donor”.',                          'count' => (int) db_value("SELECT COUNT(*) FROM contacts WHERE tags LIKE '%donor%'")],
        ['key' => 'volunteers',  'label' => 'Volunteers',         'icon' => 'hand-helping', 'color' => 'pill-cyan',  'desc' => 'Tagged “volunteer”.',                      'count' => (int) db_value("SELECT COUNT(*) FROM contacts WHERE tags LIKE '%volunteer%'")],
        ['key' => 'recent',      'label' => 'Recently contacted', 'icon' => 'clock',        'color' => 'pill-blue',  'desc' => 'Contacted in the last 30 days.',           'count' => (int) db_value("SELECT COUNT(*) FROM contacts WHERE last_contacted_at IS NOT NULL AND last_contacted_at >= (NOW() - INTERVAL 30 DAY)")],
    ];
}

$page_title = 'Contacts';
include __DIR__ . '/partials/head.php';

$tabUrl = static function (string $t) use ($BASE): string { return admin_url($BASE . '?tab=' . $t); };
?>
<div class="admin-page-head">
    <div><h1>Contacts</h1><span class="muted"><?= number_format($counts['total']) ?> total contacts across <?= number_format(count($allLists)) ?> list<?= count($allLists) === 1 ? '' : 's' ?></span></div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        <a class="btn btn-outline" href="<?= e(admin_url($BASE . '?export=csv')) ?>"><?= lucide('download') ?> Export CSV</a>
        <?php if ($tab === 'contacts'): ?>
            <a class="btn btn-primary" href="<?= e(admin_url($BASE . '?tab=contacts&new=1')) ?>"><?= lucide('user-plus') ?> Add contact</a>
        <?php elseif ($tab === 'lists'): ?>
            <a class="btn btn-primary" href="#ecx-list-form"><?= lucide('plus') ?> New list</a>
        <?php endif; ?>
    </div>
</div>

<div class="tabs ecx-tabs">
    <a class="tab <?= $tab === 'contacts' ? 'is-active' : '' ?>" href="<?= e($tabUrl('contacts')) ?>"><?= lucide('users') ?> Contacts <span class="ecx-tabnum"><?= number_format($counts['total']) ?></span></a>
    <a class="tab <?= $tab === 'lists' ? 'is-active' : '' ?>" href="<?= e($tabUrl('lists')) ?>"><?= lucide('list') ?> Lists <span class="ecx-tabnum"><?= number_format(count($allLists)) ?></span></a>
    <a class="tab <?= $tab === 'segments' ? 'is-active' : '' ?>" href="<?= e($tabUrl('segments')) ?>"><?= lucide('filter') ?> Segments</a>
</div>

<?php /* ============================================================ CONTACTS TAB */ ?>
<?php if ($tab === 'contacts'): ?>
<div class="ecx adm-rise">

    <?php if ($showForm): ?>
    <div class="panel ecx-form-panel" id="ecx-contact-form">
        <div class="panel-head">
            <h3 class="panel-title"><?= lucide($edit ? 'user-pen' : 'user-plus') ?> <?= $edit ? 'Edit contact' : 'Add a contact' ?></h3>
            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($BASE . '?tab=contacts')) ?>"><?= lucide('x') ?> Close</a>
        </div>
        <div class="panel-body">
            <div class="ecx-form-grid">
                <form method="post" action="<?= e(admin_url($BASE)) ?>" class="ecx-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="save">
                    <input type="hidden" name="tab" value="contacts">
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">First name</label>
                            <input class="form-control" name="first_name" maxlength="96" value="<?= e($edit['first_name'] ?? '') ?>" placeholder="Priya">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last name</label>
                            <input class="form-control" name="last_name" maxlength="96" value="<?= e($edit['last_name'] ?? '') ?>" placeholder="Sharma">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Email <span class="req">*</span></label>
                            <input class="form-control" type="email" name="email" required maxlength="191" value="<?= e($edit['email'] ?? '') ?>" placeholder="name@example.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input class="form-control" name="phone" maxlength="32" value="<?= e($edit['phone'] ?? '') ?>" placeholder="+91-98000 00000">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Company</label>
                            <input class="form-control" name="company" maxlength="128" value="<?= e($edit['company'] ?? '') ?>" placeholder="Organisation">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Job title</label>
                            <input class="form-control" name="job_title" maxlength="128" value="<?= e($edit['job_title'] ?? '') ?>" placeholder="Program Lead">
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input class="form-control" name="country" maxlength="64" value="<?= e($edit['country'] ?? '') ?>" placeholder="India">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <?php foreach ($statusMeta as $sv => [$slabel, $_c]): ?>
                                    <option value="<?= e($sv) ?>" <?= ($edit['status'] ?? 'active') === $sv ? 'selected' : '' ?>><?= e($slabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tags</label>
                        <input class="form-control" name="tags" maxlength="255" value="<?= e($edit['tags'] ?? '') ?>" placeholder="donor, newsletter, volunteer">
                        <small class="form-hint">Comma-separated. Tags power the smart segments (e.g. <code>donor</code>, <code>volunteer</code>).</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-textarea" name="notes" rows="3" placeholder="Internal notes…"><?= e($edit['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= lucide('check') ?> <?= $edit ? 'Save changes' : 'Add contact' ?></button>
                        <a class="btn btn-ghost" href="<?= e(admin_url($BASE . '?tab=contacts')) ?>">Cancel</a>
                        <?php if ($edit): ?>
                            <form method="post" action="<?= e(admin_url($BASE)) ?>" data-confirm="Delete this contact permanently?" style="margin-left:auto;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="delete">
                                <input type="hidden" name="tab" value="contacts">
                                <input type="hidden" name="id" value="<?= (int) $edit['id'] ?>">
                                <button class="btn btn-danger" type="submit"><?= lucide('trash-2') ?> Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($edit): ?>
                <aside class="ecx-history">
                    <div class="ecx-hist-id">
                        <div class="ecx-avatar"><?= e(strtoupper(mb_substr(($edit['first_name'] ?: $edit['email']), 0, 1))) ?></div>
                        <div>
                            <strong><?= e(trim(($edit['first_name'] ?? '') . ' ' . ($edit['last_name'] ?? '')) ?: $edit['email']) ?></strong>
                            <div class="muted" style="font-size:.8rem;"><?= e($edit['email']) ?></div>
                        </div>
                    </div>

                    <div class="ecx-hist-head"><?= lucide('activity') ?> Engagement history</div>
                    <?php if ($histEvents): ?>
                        <ul class="ecx-timeline">
                            <?php foreach ($histEvents as $ev):
                                $etMap = ['open' => 'pill-blue', 'click' => 'pill-violet', 'sent' => 'pill-green', 'delivered' => 'pill-green', 'bounce' => 'pill-amber', 'fail' => 'pill-red', 'spam' => 'pill-red', 'unsubscribe' => 'pill-gray']; ?>
                                <li>
                                    <span class="pill <?= $etMap[$ev['event_type']] ?? 'pill-gray' ?>"><?= e(ucfirst($ev['event_type'])) ?></span>
                                    <span class="ecx-tl-meta">
                                        <?php if (!empty($ev['device'])): ?><?= e($ev['device']) ?> · <?php endif; ?>
                                        <?= e(time_ago($ev['created_at'])) ?>
                                    </span>
                                    <?php if (!empty($ev['url'])): ?><span class="ecx-tl-url"><?= e(excerpt($ev['url'], 8)) ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted ecx-hist-empty">No tracked opens or clicks yet.</p>
                    <?php endif; ?>

                    <div class="ecx-hist-head"><?= lucide('mail') ?> Messages</div>
                    <?php if ($histMessages): ?>
                        <ul class="ecx-msglist">
                            <?php foreach ($histMessages as $m): ?>
                                <li>
                                    <a href="<?= e(admin_url('email-mailbox?folder=' . e($m['folder']) . '&id=' . (int) $m['id'])) ?>">
                                        <span class="ecx-msg-dir"><?= lucide($m['direction'] === 'incoming' ? 'arrow-down-left' : 'arrow-up-right') ?></span>
                                        <span class="ecx-msg-subj"><?= e($m['subject'] ?: '(no subject)') ?></span>
                                    </a>
                                    <span class="muted"><?= e(time_ago($m['sent_at'] ?: $m['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="muted ecx-hist-empty">No emails to or from this contact yet.</p>
                    <?php endif; ?>

                    <a class="btn btn-outline btn-sm btn-block" href="<?= e(admin_url('email-compose?to=' . urlencode($edit['email']))) ?>"><?= lucide('send') ?> Compose to this contact</a>
                </aside>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-body">
            <?php if ($dupeCount > 0 && !$dupes): ?>
                <div class="ecx-dupe-notice">
                    <?= lucide('copy-check') ?>
                    <span><strong><?= (int) $dupeCount ?></strong> possible duplicate contact<?= $dupeCount === 1 ? '' : 's' ?> share a phone number.</span>
                    <a class="btn btn-outline btn-sm" href="<?= e(admin_url($BASE . '?tab=contacts&dupes=1')) ?>">Review duplicates</a>
                </div>
            <?php endif; ?>

            <?php if ($dupes || $seg || $listFilter): ?>
                <div class="ecx-active-filter">
                    <?= lucide('filter') ?>
                    <span>
                        <?php if ($dupes): ?>Showing contacts that share a phone number<?php endif; ?>
                        <?php if ($seg): ?>Segment: <strong><?= e(ucfirst($seg)) ?></strong><?php endif; ?>
                        <?php if ($listFilter): ?>List: <strong><?= e($listName) ?></strong><?php endif; ?>
                    </span>
                    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($BASE . '?tab=contacts')) ?>"><?= lucide('x') ?> Clear</a>
                </div>
            <?php endif; ?>

            <div class="data-toolbar">
                <form class="search" method="get" action="<?= e(admin_url($BASE)) ?>">
                    <input type="hidden" name="tab" value="contacts">
                    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                    <input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, company, tags…">
                </form>
                <div class="ecx-chips">
                    <a class="filter-chip <?= $status === '' && !$seg && !$dupes && !$listFilter ? 'is-active' : '' ?>" href="<?= e(admin_url($BASE . '?tab=contacts')) ?>">All <span class="ecx-chipn"><?= $counts['total'] ?></span></a>
                    <a class="filter-chip <?= $status === 'active' ? 'is-active' : '' ?>" href="<?= e(admin_url($BASE . '?tab=contacts&status=active')) ?>">Active <span class="ecx-chipn"><?= $counts['active'] ?></span></a>
                    <a class="filter-chip <?= $status === 'unsubscribed' ? 'is-active' : '' ?>" href="<?= e(admin_url($BASE . '?tab=contacts&status=unsubscribed')) ?>">Unsubscribed <span class="ecx-chipn"><?= $counts['unsubscribed'] ?></span></a>
                    <a class="filter-chip <?= $status === 'bounced' ? 'is-active' : '' ?>" href="<?= e(admin_url($BASE . '?tab=contacts&status=bounced')) ?>">Bounced <span class="ecx-chipn"><?= $counts['bounced'] ?></span></a>
                </div>
            </div>

            <?php if ($p['items']): ?>
            <form method="post" action="<?= e(admin_url($BASE)) ?>" id="ecxBulkForm">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="bulk">
                <input type="hidden" name="tab" value="contacts">
                <input type="hidden" name="action" id="ecxBulkAction">

                <div class="ecx-bulkbar" id="ecxBulkBar" hidden>
                    <span class="ecx-bulk-count"><strong id="ecxSel">0</strong> selected</span>
                    <div class="ecx-bulk-actions">
                        <select class="form-select form-sm" id="ecxBulkList" name="list_id">
                            <option value="">Choose a list…</option>
                            <?php foreach ($allLists as $l): ?>
                                <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline btn-sm" data-bulk="addlist"><?= lucide('list-plus') ?> Add to list</button>
                        <button type="button" class="btn btn-outline btn-sm" data-bulk="unsubscribe"><?= lucide('user-x') ?> Unsubscribe</button>
                        <button type="button" class="btn btn-danger btn-sm" data-bulk="delete" data-confirm-bulk="Delete the selected contacts permanently?"><?= lucide('trash-2') ?> Delete</button>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="admin-table ecx-table">
                        <thead>
                            <tr>
                                <th style="width:34px;"><label class="checkbox"><input type="checkbox" id="ecxCheckAll"></label></th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Tags</th>
                                <th>Status</th>
                                <th>Last contacted</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($p['items'] as $c):
                            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
                            [$slabel, $spill] = $statusMeta[$c['status']] ?? ['Active', 'pill-green'];
                            $tags = array_filter(array_map('trim', explode(',', (string) $c['tags']))); ?>
                            <tr>
                                <td><label class="checkbox"><input type="checkbox" class="ecx-cb" name="ids[]" value="<?= (int) $c['id'] ?>"></label></td>
                                <td>
                                    <div class="ecx-name">
                                        <span class="ecx-av"><?= e(strtoupper(mb_substr(($c['first_name'] ?: $c['email']), 0, 1))) ?></span>
                                        <span class="ecx-name-txt">
                                            <a href="<?= e(admin_url($BASE . '?tab=contacts&edit=' . (int) $c['id'])) ?>"><strong><?= e($name ?: '—') ?></strong></a>
                                            <span class="muted"><?= e($c['email']) ?></span>
                                        </span>
                                    </div>
                                </td>
                                <td><?= $c['company'] ? e($c['company']) : '<span class="muted">—</span>' ?></td>
                                <td>
                                    <?php if ($tags): ?>
                                        <div class="ecx-tags">
                                            <?php foreach (array_slice($tags, 0, 3) as $t): ?><span class="pill pill-blue"><?= e($t) ?></span><?php endforeach; ?>
                                            <?php if (count($tags) > 3): ?><span class="pill pill-gray">+<?= count($tags) - 3 ?></span><?php endif; ?>
                                        </div>
                                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                                </td>
                                <td><span class="pill <?= $spill ?>"><?= e($slabel) ?></span></td>
                                <td><?= $c['last_contacted_at'] ? '<span title="' . e($c['last_contacted_at']) . '">' . e(time_ago($c['last_contacted_at'])) . '</span>' : '<span class="muted">Never</span>' ?></td>
                                <td>
                                    <div class="actions">
                                        <a class="icon-btn" href="<?= e(admin_url('email-compose?to=' . urlencode($c['email']))) ?>" title="Compose"><?= lucide('send') ?></a>
                                        <a class="icon-btn" href="<?= e(admin_url($BASE . '?tab=contacts&edit=' . (int) $c['id'])) ?>" title="Edit"><?= lucide('pen-line') ?></a>
                                        <button class="icon-btn danger" type="submit" title="Delete"
                                                onclick="if(!confirm('Delete this contact?')){return false;}this.form.querySelector('#ecxBulkAction').value='delete';var h=document.createElement('input');h.type='hidden';h.name='ids[]';h.value='<?= (int) $c['id'] ?>';this.form.appendChild(h);">
                                            <?= lucide('trash-2') ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <div class="ecx-pager">
                <span class="muted"><?= (int) $p['total'] ?> contact<?= (int) $p['total'] === 1 ? '' : 's' ?></span>
                <?= $p['links'] ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon"><?= lucide('users') ?></div>
                    <p><?= $q || $status || $seg || $dupes || $listFilter ? 'No contacts match this view.' : 'No contacts yet.' ?></p>
                    <a class="btn btn-primary btn-sm" href="<?= e(admin_url($BASE . '?tab=contacts&new=1')) ?>"><?= lucide('user-plus') ?> Add your first contact</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel ecx-import">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('upload') ?> Import contacts from CSV</h3></div>
        <div class="panel-body">
            <form method="post" action="<?= e(admin_url($BASE)) ?>" enctype="multipart/form-data" class="ecx-import-form">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="import">
                <input type="hidden" name="tab" value="contacts">
                <div class="ecx-import-row">
                    <input class="form-control" type="file" name="csv" accept=".csv,text/csv" required>
                    <button class="btn btn-primary" type="submit"><?= lucide('file-up') ?> Import</button>
                </div>
                <small class="form-hint">
                    Expected columns (order &amp; case flexible): <code>first_name, last_name, email, phone, company, tags</code>.
                    Rows without a valid email are skipped; existing contacts are matched by email and updated.
                    Need a starting point? <a href="<?= e(admin_url($BASE . '?export=csv')) ?>">Export your current contacts</a> and edit that file.
                </small>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* ============================================================ LISTS TAB */ ?>
<?php if ($tab === 'lists'): ?>
<div class="ecx adm-rise">
    <div class="ecx-lists-grid">
        <?php if ($lists): foreach ($lists as $l): ?>
            <div class="panel ecx-list-card">
                <div class="panel-body">
                    <div class="ecx-list-top">
                        <span class="ecx-swatch" style="background:<?= e($l['color']) ?>;"></span>
                        <div class="ecx-list-meta">
                            <strong><?= e($l['name']) ?></strong>
                            <?php if (!empty($l['description'])): ?><span class="muted"><?= e($l['description']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="ecx-list-foot">
                        <span class="pill pill-gray"><?= lucide('users') ?> <?= (int) $l['member_count'] ?> member<?= (int) $l['member_count'] === 1 ? '' : 's' ?></span>
                        <div class="actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($BASE . '?tab=contacts&list=' . (int) $l['id'])) ?>"><?= lucide('eye') ?> View</a>
                            <button type="button" class="btn btn-ghost btn-sm" data-edit-list
                                    data-id="<?= (int) $l['id'] ?>" data-name="<?= e($l['name']) ?>"
                                    data-desc="<?= e($l['description'] ?? '') ?>" data-color="<?= e($l['color']) ?>"><?= lucide('pen-line') ?></button>
                            <form method="post" action="<?= e(admin_url($BASE)) ?>" data-confirm="Delete this list? Contacts stay, only the grouping is removed." style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="delete_list">
                                <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                <button class="btn btn-ghost btn-sm ecx-del" type="submit"><?= lucide('trash-2') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="empty-state" style="grid-column:1/-1;">
                <div class="icon"><?= lucide('list') ?></div>
                <p>No contact lists yet. Create one to group contacts for targeted sends.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel ecx-list-form" id="ecx-list-form">
        <div class="panel-head"><h3 class="panel-title" id="ecxListFormTitle"><?= lucide('plus') ?> Create a list</h3></div>
        <div class="panel-body">
            <form method="post" action="<?= e(admin_url($BASE)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="save_list">
                <input type="hidden" name="id" id="ecxListId" value="0">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">List name <span class="req">*</span></label>
                        <input class="form-control" name="name" id="ecxListName" maxlength="128" required placeholder="e.g. Monthly Donors">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Colour</label>
                        <input class="form-control ecx-color" type="color" name="color" id="ecxListColor" value="#0B4E3D">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input class="form-control" name="description" id="ecxListDesc" maxlength="255" placeholder="Optional — what this list is for">
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= lucide('check') ?> <span id="ecxListSubmit">Create list</span></button>
                    <button class="btn btn-ghost" type="reset" id="ecxListReset" hidden>Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php /* ============================================================ SEGMENTS TAB */ ?>
<?php if ($tab === 'segments'): ?>
<div class="ecx adm-rise">
    <div class="dash-section-head">
        <h2>Smart segments</h2>
        <span class="muted">Live, filter-based groups computed from your contacts — no setup required.</span>
    </div>
    <div class="ecx-seg-grid">
        <?php foreach ($segments as $s): ?>
            <div class="panel ecx-seg-card">
                <div class="panel-body">
                    <div class="ecx-seg-top">
                        <span class="ecx-seg-ic"><?= lucide($s['icon']) ?></span>
                        <span class="pill <?= $s['color'] ?>"><?= number_format($s['count']) ?></span>
                    </div>
                    <h3 class="ecx-seg-name"><?= e($s['label']) ?></h3>
                    <p class="ecx-seg-desc muted"><?= e($s['desc']) ?></p>
                    <div class="ecx-seg-actions">
                        <?php
                        // Segments that map to the contacts-tab filters get a live "view" link.
                        $viewUrl = match ($s['key']) {
                            'active', 'unsubscribed' => admin_url($BASE . '?tab=contacts&status=' . $s['key']),
                            'donors', 'volunteers', 'recent' => admin_url($BASE . '?tab=contacts&seg=' . $s['key']),
                            default => admin_url($BASE . '?tab=contacts'),
                        };
                        ?>
                        <a class="btn btn-outline btn-sm" href="<?= e($viewUrl) ?>"><?= lucide('users') ?> View contacts</a>
                        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('email-campaigns')) ?>"><?= lucide('send') ?> Compose to segment</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="panel ecx-seg-note">
        <div class="panel-body">
            <?= lucide('info') ?>
            <div>
                <strong>How segments work.</strong>
                <span class="muted">Segments are evaluated live against the current contact base, so their counts always reflect the latest data. Use “View contacts” to open the filtered list, then build a campaign from the <a href="<?= e(admin_url('email-campaigns')) ?>">Campaigns</a> page to reach everyone in the segment.</span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ---- Contacts (CRM) page — scoped to .ecx ------------------------------- */
.ecx-tabs .tab { display:inline-flex; align-items:center; gap:.4rem; }
.ecx-tabs .tab svg { width:16px; height:16px; }
.ecx-tabnum { background:var(--surface-2); border-radius:999px; padding:.05rem .5rem; font-size:.72rem; font-weight:700; color:var(--muted); }
.ecx-tabs .tab.is-active .ecx-tabnum { background:var(--grad-brand-soft); color:var(--brand-600); }
.ecx { display:flex; flex-direction:column; gap:1.1rem; }

.ecx-form-panel { border:1px solid color-mix(in srgb, var(--brand-600) 22%, var(--border)); }
.ecx-form-grid { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(0,1fr); gap:1.4rem; }
.ecx-form .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
.ecx-form .form-actions { display:flex; align-items:center; gap:.6rem; margin-top:.4rem; flex-wrap:wrap; }
.ecx-form .req { color:#dc2626; }

.ecx-history { border-left:1px solid var(--border); padding-left:1.4rem; display:flex; flex-direction:column; gap:.6rem; }
.ecx-hist-id { display:flex; align-items:center; gap:.7rem; padding-bottom:.4rem; }
.ecx-avatar, .ecx-av { display:grid; place-items:center; border-radius:50%; background:var(--grad-brand,#0B4E3D); color:#fff; font-weight:700; }
.ecx-avatar { width:42px; height:42px; font-size:1rem; }
.ecx-av { width:30px; height:30px; font-size:.8rem; flex:0 0 auto; }
.ecx-hist-head { display:flex; align-items:center; gap:.4rem; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; font-weight:700; color:var(--muted); margin-top:.5rem; }
.ecx-hist-head svg { width:14px; height:14px; }
.ecx-hist-empty { font-size:.85rem; margin:.1rem 0 .3rem; }
.ecx-timeline { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.5rem; }
.ecx-timeline li { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; font-size:.82rem; }
.ecx-tl-meta { color:var(--muted); }
.ecx-tl-url { flex-basis:100%; color:var(--text-soft); font-size:.75rem; }
.ecx-msglist { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:.4rem; }
.ecx-msglist li { display:flex; align-items:center; justify-content:space-between; gap:.6rem; font-size:.82rem; }
.ecx-msglist a { display:inline-flex; align-items:center; gap:.4rem; color:var(--text); min-width:0; }
.ecx-msglist a:hover { color:var(--brand-600); }
.ecx-msg-dir svg { width:14px; height:14px; color:var(--muted); }
.ecx-msg-subj { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:190px; }

.ecx-dupe-notice, .ecx-active-filter { display:flex; align-items:center; gap:.6rem; padding:.7rem .9rem; border-radius:var(--radius); margin-bottom:1rem; font-size:.88rem; }
.ecx-dupe-notice { background:rgba(241,90,36,.1); border:1px solid rgba(241,90,36,.25); color:var(--text); }
.ecx-dupe-notice svg { width:18px; height:18px; color:#b45309; flex:0 0 auto; }
.ecx-dupe-notice a { margin-left:auto; }
.ecx-active-filter { background:var(--grad-brand-soft); border:1px solid color-mix(in srgb, var(--brand-600) 25%, transparent); }
.ecx-active-filter svg { width:16px; height:16px; color:var(--brand-600); }
.ecx-active-filter a { margin-left:auto; }

.ecx .data-toolbar { justify-content:space-between; }
.ecx .data-toolbar .search { flex:1 1 240px; max-width:420px; }
.ecx-chips { display:flex; gap:.4rem; flex-wrap:wrap; }
.ecx-chipn { opacity:.7; font-weight:700; }
.ecx-chips .filter-chip.is-active .ecx-chipn { opacity:.95; }

.ecx-bulkbar { display:flex; align-items:center; gap:1rem; padding:.6rem .9rem; margin-bottom:.8rem; border-radius:var(--radius); background:var(--surface-2); border:1px solid var(--border); flex-wrap:wrap; }
.ecx-bulk-count { font-size:.85rem; color:var(--text-soft); }
.ecx-bulk-actions { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
.form-sm { padding:.35rem .55rem; font-size:.82rem; }

.ecx-name { display:flex; align-items:center; gap:.6rem; }
.ecx-name-txt { display:flex; flex-direction:column; line-height:1.3; min-width:0; }
.ecx-name-txt .muted { font-size:.78rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
.ecx-tags { display:flex; gap:.3rem; flex-wrap:wrap; }
.ecx-table td { vertical-align:middle; }
.ecx-pager { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-top:1rem; flex-wrap:wrap; }

.ecx-import-row { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
.ecx-import-row .form-control[type=file] { flex:1 1 280px; }

.ecx-lists-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:1rem; }
.ecx-list-card .panel-body { display:flex; flex-direction:column; gap:.9rem; }
.ecx-list-top { display:flex; align-items:flex-start; gap:.7rem; }
.ecx-swatch { width:14px; height:14px; border-radius:5px; margin-top:.25rem; flex:0 0 auto; box-shadow:inset 0 0 0 1px rgba(0,0,0,.08); }
.ecx-list-meta { display:flex; flex-direction:column; gap:.15rem; min-width:0; }
.ecx-list-meta .muted { font-size:.82rem; }
.ecx-list-foot { display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; margin-top:auto; }
.ecx-list-foot .pill svg { width:13px; height:13px; }
.ecx-del:hover { color:#dc2626; }
.ecx-color { padding:.2rem; height:42px; cursor:pointer; }

.ecx-seg-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:1rem; }
.ecx-seg-card .panel-body { display:flex; flex-direction:column; gap:.5rem; }
.ecx-seg-top { display:flex; align-items:center; justify-content:space-between; }
.ecx-seg-ic { display:grid; place-items:center; width:40px; height:40px; border-radius:12px; background:var(--grad-brand-soft); color:var(--brand-600); }
.ecx-seg-ic svg { width:20px; height:20px; }
.ecx-seg-name { margin:.2rem 0 0; font-size:1.05rem; }
.ecx-seg-desc { font-size:.84rem; margin:0; }
.ecx-seg-actions { display:flex; gap:.4rem; margin-top:.6rem; flex-wrap:wrap; }
.ecx-seg-note .panel-body { display:flex; align-items:flex-start; gap:.7rem; }
.ecx-seg-note svg { width:20px; height:20px; color:var(--brand-600); flex:0 0 auto; }

@media (max-width:900px){
    .ecx-form-grid { grid-template-columns:1fr; }
    .ecx-history { border-left:0; padding-left:0; border-top:1px solid var(--border); padding-top:1rem; }
    .ecx-form .grid-2 { grid-template-columns:1fr; }
}
</style>

<script>
(function () {
    // ---- Bulk selection bar ----
    var boxes = document.querySelectorAll('.ecx-cb');
    var bar   = document.getElementById('ecxBulkBar');
    var sel   = document.getElementById('ecxSel');
    var all   = document.getElementById('ecxCheckAll');
    var form  = document.getElementById('ecxBulkForm');
    function refresh() {
        var n = document.querySelectorAll('.ecx-cb:checked').length;
        if (sel) sel.textContent = n;
        if (bar) bar.hidden = n === 0;
        if (all) all.checked = n > 0 && n === boxes.length;
    }
    boxes.forEach(function (c) { c.addEventListener('change', refresh); });
    if (all) all.addEventListener('change', function () {
        boxes.forEach(function (c) { c.checked = all.checked; });
        refresh();
    });
    document.querySelectorAll('[data-bulk]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!document.querySelectorAll('.ecx-cb:checked').length) return;
            var action = b.getAttribute('data-bulk');
            if (action === 'addlist') {
                var lst = document.getElementById('ecxBulkList');
                if (lst && !lst.value) { lst.focus(); return; }
            }
            var conf = b.getAttribute('data-confirm-bulk');
            if (conf && !window.confirm(conf)) return;
            document.getElementById('ecxBulkAction').value = action;
            form.submit();
        });
    });

    // ---- List edit: prefill the create-list form ----
    document.querySelectorAll('[data-edit-list]').forEach(function (b) {
        b.addEventListener('click', function () {
            var id = b.getAttribute('data-id');
            var set = function (elId, v) { var el = document.getElementById(elId); if (el) el.value = v; };
            set('ecxListId', id);
            set('ecxListName', b.getAttribute('data-name') || '');
            set('ecxListDesc', b.getAttribute('data-desc') || '');
            set('ecxListColor', b.getAttribute('data-color') || '#0B4E3D');
            var title = document.getElementById('ecxListFormTitle');
            if (title) title.textContent = 'Edit list';
            var submit = document.getElementById('ecxListSubmit');
            if (submit) submit.textContent = 'Save changes';
            var reset = document.getElementById('ecxListReset');
            if (reset) reset.hidden = false;
            var form = document.getElementById('ecx-list-form');
            if (form) { form.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
        });
    });
    var resetBtn = document.getElementById('ecxListReset');
    if (resetBtn) resetBtn.addEventListener('click', function () {
        setTimeout(function () {
            document.getElementById('ecxListId').value = '0';
            document.getElementById('ecxListColor').value = '#0B4E3D';
            var t = document.getElementById('ecxListFormTitle'); if (t) t.textContent = 'Create a list';
            var s = document.getElementById('ecxListSubmit'); if (s) s.textContent = 'Create list';
            resetBtn.hidden = true;
        }, 0);
    });
})();
</script>

<?php include __DIR__ . '/partials/foot.php'; ?>
