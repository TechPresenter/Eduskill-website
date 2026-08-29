<?php
/**
 * =============================================================================
 *  Admin — Community Coordinator Applications  (INBOX module).
 *  list + view + status change + office-use record + delete. NO create form:
 *  rows arrive from the public form at /coordinator-apply.
 *
 *  Follows the standard admin pattern: CSRF-guarded POST handlers keyed on
 *  `_do`, activity logging, flash + redirect, a paginated list with status /
 *  position filters and search, per-status stat cards, and a read-only detail
 *  view — plus the one thing the other application inboxes do not have, the
 *  "FOR OFFICE USE ONLY" block from the paper form, which is the only editable
 *  part of a record.
 *
 *  Documents are linked with secure_upload_url(), never upload_url(): they are
 *  identity and bank documents in a directory Apache refuses to serve, so
 *  admin/secure-file.php is the only route to them.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'coordinator_applications';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$STATUSES  = coord_statuses();
$POSITIONS = coord_positions();
$DOCS      = coord_documents();

/**
 * Sanitise a caller-supplied return path so we only ever redirect back inside
 * this module (guards against open redirects).
 */
$safe_return = static function (string $ret): string {
    return strncmp($ret, 'coordinator-applications', 24) === 0 ? $ret : 'coordinator-applications';
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $return = $safe_return((string) post('_return', 'coordinator-applications'));

    if (!isset($STATUSES[$status])) {
        set_flash('error', 'Invalid status value.');
        redirect('/admin/' . $return);
    }
    if (find($table, $sid)) {
        db_update($table, [
            'status'      => $status,
            'reviewed_by' => (int) (current_user()['id'] ?? 0) ?: null,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', [':id' => $sid]);
        log_activity('update', $table, 'Set coordinator application #' . $sid . ' to ' . $status);
        set_flash('success', 'Application status updated to "' . $STATUSES[$status]['label'] . '".');
    } else {
        set_flash('error', 'Application not found.');
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- OFFICE USE */
if (is_post() && post('_do') === 'office') {
    require_csrf();
    $oid = (int) post('id', 0);
    $row = find($table, $oid);
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/coordinator-applications');
    }

    $status = (string) post('status', $row['status']);
    if (!isset($STATUSES[$status])) {
        $status = $row['status'];
    }
    $level = (string) post('coordinator_level', '');
    if (!isset($POSITIONS[$level])) {
        $level = '';
    }
    $verification = post('field_verification') === 'completed' ? 'completed' : 'pending';
    $outcome = (string) post('interview_outcome', '');
    if (!in_array($outcome, ['', 'recommended', 'not_recommended'], true)) {
        $outcome = '';
    }

    /** Optional date field — stored only when it is a real Y-m-d. */
    $date = static function (string $key): ?string {
        $v = trim((string) post($key, ''));
        $d = $v === '' ? false : DateTime::createFromFormat('Y-m-d', $v);
        return ($d && $d->format('Y-m-d') === $v) ? $v : null;
    };

    $honorarium = trim((string) post('honorarium', ''));

    db_update($table, [
        'status'               => $status,
        'docs_verified'        => (int) post('docs_verified', 0) === 1 ? 1 : 0,
        'field_verification'   => $verification,
        'interview_outcome'    => $outcome,
        'approved_position'    => clean(post('approved_position', '')) ?: null,
        'assigned_area'        => clean(post('assigned_area', '')) ?: null,
        'joining_date'         => $date('joining_date'),
        'coordinator_level'    => $level,
        'honorarium'           => is_numeric($honorarium) ? round((float) $honorarium, 2) : null,
        'approved_by'          => clean(post('approved_by', '')) ?: null,
        'approver_designation' => clean(post('approver_designation', '')) ?: null,
        'office_notes'         => clean(post('office_notes', '')) ?: null,
        'reviewed_by'          => (int) (current_user()['id'] ?? 0) ?: null,
        'reviewed_at'          => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $oid]);

    log_activity('update', $table, 'Updated office record for coordinator application #' . $oid);
    set_flash('success', 'Office record saved.');
    redirect('/admin/coordinator-applications?action=view&id=' . $oid);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId  = (int) post('id', 0);
    $return = $safe_return((string) post('_return', 'coordinator-applications'));
    $row = find($table, $delId);
    if ($row) {
        // The uploads outlive the row unless they are removed with it.
        foreach (coord_json($row['documents']) as $path) {
            delete_upload(is_string($path) ? $path : null);
        }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', $table, 'Deleted coordinator application #' . $delId);
        set_flash('success', 'Application deleted.');
    }
    // After deleting from a detail view there is nothing to return to.
    if (strncmp($return, 'coordinator-applications?action=view', 36) === 0) {
        $return = 'coordinator-applications';
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- VIEW (detail) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/coordinator-applications');
    }

    $education = coord_json($row['education']);
    $files     = coord_json($row['documents']);
    $idNumber  = coord_reveal_id($row['id_proof_no']);
    $reviewer  = !empty($row['reviewed_by']) ? db_value('SELECT name FROM users WHERE id = :i', [':i' => (int) $row['reviewed_by']]) : null;

    /** One label/value row, skipped entirely when there is nothing to show. */
    $line = static function (string $label, ?string $value, bool $pre = false): void {
        if ($value === null || trim($value) === '') {
            return;
        }
        echo '<tr><th class="ca-w-label">' . e($label) . '</th><td'
           . ($pre ? ' class="ca-pre"' : '') . '>' . e($value) . '</td></tr>';
    };

    $page_title = 'Coordinator Application';
    include __DIR__ . '/partials/head.php';
    ?>
    <style>
        /* Layout only — everything else is the shared admin design system. */
        .ca-w-label { width: 210px; }
        .ca-pre { white-space: pre-wrap; line-height: 1.6; }
        .ca-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
        .ca-docs { display: grid; gap: .5rem; }
        .ca-doc {
            display: flex; align-items: center; gap: .7rem;
            padding: .6rem .75rem; border: 1px solid var(--line, #e5e7eb); border-radius: 10px;
        }
        .ca-doc-name { flex: 1 1 auto; min-width: 0; font-size: .88rem; }
        .ca-doc-missing { opacity: .55; }
        .ca-sep { margin: var(--sp-5) 0; }
        .ca-office .form-group { margin-bottom: var(--sp-3); }
    </style>

    <div class="admin-page-head">
        <div>
            <h1><?= e($row['name']) ?></h1>
            <span class="muted">
                Coordinator Applications / <?= e($row['application_no'] ?: ('#' . (int) $row['id'])) ?>
            </span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('coordinator-applications')) ?>">&larr; Back to list</a>
    </div>

    <div class="grid grid-2 cols-top">
        <div>
            <!-- ------------------------------------------------- APPLICANT -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Applicant Details</h2></div>
                <div class="panel-body">
                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <tr>
                                <th class="ca-w-label">Status</th>
                                <td><span class="pill <?= e($STATUSES[$row['status']]['pill'] ?? 'pill-gray') ?>">
                                    <?= e($STATUSES[$row['status']]['label'] ?? ucfirst($row['status'])) ?></span></td>
                            </tr>
                            <tr>
                                <th>Position applied for</th>
                                <td><strong><?= e(coord_position_label($row['position'])) ?></strong></td>
                            </tr>
                            <?php
                            $line('Reference', $row['application_no']);
                            $line("Father's / Mother's / Spouse's name", $row['guardian_name']);
                            $line('Date of birth', $row['dob'] ? date('d M Y', strtotime($row['dob'])) : null);
                            $line('Gender', $row['gender'] ? ucfirst($row['gender']) : null);
                            ?>
                            <tr><th>Mobile</th><td>
                                <a href="tel:<?= e(($row['country_dial'] ? '+' . $row['country_dial'] : '') . $row['phone']) ?>">
                                    <?= e(($row['country_dial'] ? '+' . $row['country_dial'] . ' ' : '') . $row['phone']) ?></a>
                            </td></tr>
                            <?php $line('WhatsApp', $row['whatsapp']); ?>
                            <tr><th>Email</th><td><a href="mailto:<?= e($row['email']) ?>"><?= e($row['email']) ?></a></td></tr>
                            <?php if ($row['id_proof_no']): ?>
                                <tr><th>Aadhaar / ID number</th><td>
                                    <?php if ($idNumber !== null): ?>
                                        <code><?= e($idNumber) ?></code>
                                        <?php if (sec_is_encrypted($row['id_proof_no'])): ?>
                                            <span class="pill pill-green"><?= lucide('lock') ?> Encrypted at rest</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            Stored encrypted — cannot be decrypted with the current APP_KEY.
                                            <?php if (!empty($row['id_proof_last4'])): ?>Ends in <?= e($row['id_proof_last4']) ?>.<?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td></tr>
                            <?php endif; ?>
                            <?php
                            $line('Current address', $row['current_address'], true);
                            $line('Permanent address', $row['permanent_address'], true);
                            $place = array_filter([$row['village'], $row['panchayat'], $row['block'], $row['district'], $row['state']]);
                            $line('Location', $place ? implode(' · ', $place) : null);
                            $line('Preferred Panchayat', $row['preferred_panchayat']);
                            $line('Village / Ward coverage', $row['village_coverage']);
                            $line('Preferred Block', $row['preferred_block']);
                            $line('Block district', $row['block_district']);
                            $line('Preferred District', $row['preferred_district']);
                            $line('District state', $row['district_state']);
                            ?>
                            <tr><th>Submitted</th><td>
                                <?= e(date('d M Y, g:i a', strtotime($row['created_at']))) ?>
                                <small class="text-muted">(<?= e(time_ago($row['created_at'])) ?>)</small>
                            </td></tr>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------------- EDUCATION -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Education &amp; Skills</h2></div>
                <div class="panel-body">
                    <?php if ($education): ?>
                        <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Qualification</th><th>Board / University</th><th>Year</th><th>%&nbsp;/&nbsp;Grade</th></tr></thead>
                            <tbody>
                                <?php foreach ($education as $ed): ?>
                                    <tr>
                                        <td><strong><?= e((string) ($ed['level'] ?? '—')) ?></strong></td>
                                        <td><?= e((string) ($ed['board'] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
                                        <td><?= e((string) ($ed['year'] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
                                        <td><?= e((string) ($ed['grade'] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No qualifications were entered.</p>
                    <?php endif; ?>

                    <?php if (!empty($row['computer_skills'])): ?>
                        <div class="divider ca-sep"></div>
                        <strong>Computer knowledge</strong>
                        <div class="ca-tags" style="margin-top:.5rem;">
                            <?php foreach (array_filter(array_map('trim', explode(',', $row['computer_skills']))) as $skill): ?>
                                <span class="pill pill-cyan"><?= e($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ------------------------------------------------ EXPERIENCE -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Experience &amp; Community Work</h2></div>
                <div class="panel-body">
                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <tr><th class="ca-w-label">Total experience</th><td>
                                <?= (int) $row['experience_years'] ?> years <?= (int) $row['experience_months'] ?> months
                            </td></tr>
                            <tr><th>NGO / social work</th><td>
                                <?= (int) $row['ngo_experience'] === 1
                                    ? '<span class="pill pill-green">Yes</span>'
                                    : '<span class="pill pill-gray">No</span>' ?>
                            </td></tr>
                            <?php $line('NGO experience details', $row['ngo_details'], true); ?>
                            <tr><th>Worked with rural / community groups</th><td>
                                <?= (int) $row['community_experience'] === 1
                                    ? '<span class="pill pill-green">Yes</span>'
                                    : '<span class="pill pill-gray">No</span>' ?>
                            </td></tr>
                            <?php
                            $line('Community work', $row['community_note'], true);
                            $line('Languages', $row['languages']);
                            ?>
                        </tbody>
                    </table>
                    </div>

                    <?php if (!empty($row['focus_areas'])): ?>
                        <div class="divider ca-sep"></div>
                        <strong>Experience areas</strong>
                        <div class="ca-tags" style="margin-top:.5rem;">
                            <?php foreach (array_filter(array_map('trim', explode(',', $row['focus_areas']))) as $area): ?>
                                <span class="pill pill-violet"><?= e($area) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- ---------------------------------- AVAILABILITY & MOBILITY -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Availability &amp; Field Mobility</h2></div>
                <div class="panel-body">
                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <?php foreach (coord_availability() as $key => $q): ?>
                                <tr>
                                    <th class="ca-w-label"><?= e($q['label']) ?></th>
                                    <td><?= (int) $row[$key] === 1
                                        ? '<span class="pill pill-green">Yes</span>'
                                        : '<span class="pill pill-gray">No</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php
                            $line('Preferred work mode', $row['work_mode']);
                            $line('Expected honorarium', $row['expected_honorarium'] !== null
                                ? '₹' . number_format((float) $row['expected_honorarium'], 2)
                                : null);
                            $line('Earliest joining date', $row['available_from']
                                ? date('d M Y', strtotime($row['available_from']))
                                : null);
                            ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>

            <!-- ------------------------------------------- REFERENCE DETAILS -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Reference Details</h2></div>
                <div class="panel-body">
                    <?php if (trim(implode('', array_map(static fn($k) => (string) $row[$k], array_keys(coord_reference_fields())))) !== ''): ?>
                        <div class="table-wrap">
                        <table class="admin-table">
                            <tbody>
                                <?php foreach (coord_reference_fields() as $key => $meta): ?>
                                    <?php $val = trim((string) $row[$key]); ?>
                                    <tr>
                                        <th class="ca-w-label"><?= e($meta['label']) ?></th>
                                        <td>
                                            <?php if ($val === ''): ?>
                                                <span class="text-muted">—</span>
                                            <?php elseif ($key === 'ref_mobile'): ?>
                                                <a href="tel:<?= e($val) ?>"><?= e($val) ?></a>
                                            <?php else: ?>
                                                <?= e($val) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No reference was provided.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================================================= RIGHT COLUMN -->
        <div>
            <!-- --------------------------------------------- DOCUMENTS -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Documents</h2></div>
                <div class="panel-body">
                    <div class="ca-docs">
                        <?php foreach ($DOCS as $slot => $doc): ?>
                            <?php $path = $files[$slot] ?? null; ?>
                            <div class="ca-doc<?= $path ? '' : ' ca-doc-missing' ?>">
                                <?= lucide($path ? 'file-check' : 'file-x') ?>
                                <span class="ca-doc-name">
                                    <?= e($doc['label']) ?>
                                    <?php if (!empty($doc['required']) && !$path): ?>
                                        <span class="pill pill-red">Missing</span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($path): ?>
                                    <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($path)) ?>"
                                       target="_blank" rel="noopener"><?= lucide('download') ?> Open</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-hint" style="margin-top:.75rem;">
                        Documents download through the authenticated file gate — the URLs are useless without an admin session.
                    </p>
                </div>
            </div>

            <!-- ------------------------------------------- OFFICE USE ONLY -->
            <div class="panel ca-office">
                <div class="panel-head"><h2 class="panel-title">For Office Use Only</h2></div>
                <div class="panel-body">
                    <form method="post" action="<?= e(admin_url('coordinator-applications')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="office">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                        <div class="form-group">
                            <label class="form-label" for="of-status">Application status</label>
                            <select class="form-select" id="of-status" name="status">
                                <?php foreach ($STATUSES as $key => $meta): ?>
                                    <option value="<?= e($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-docs">
                                <input type="checkbox" id="of-docs" name="docs_verified" value="1" <?= (int) $row['docs_verified'] === 1 ? 'checked' : '' ?>>
                                Documents verified
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-field">Field / background verification</label>
                            <select class="form-select" id="of-field" name="field_verification">
                                <option value="pending"   <?= $row['field_verification'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $row['field_verification'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-interview">Interview</label>
                            <select class="form-select" id="of-interview" name="interview_outcome">
                                <option value=""                 <?= $row['interview_outcome'] === '' ? 'selected' : '' ?>>Not held yet</option>
                                <option value="recommended"      <?= $row['interview_outcome'] === 'recommended' ? 'selected' : '' ?>>Recommended</option>
                                <option value="not_recommended"  <?= $row['interview_outcome'] === 'not_recommended' ? 'selected' : '' ?>>Not recommended</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-position">Position approved</label>
                            <input class="form-control" id="of-position" name="approved_position" maxlength="128"
                                   value="<?= e((string) $row['approved_position']) ?>" placeholder="e.g. Block Coordinator">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-level">Coordinator level</label>
                            <select class="form-select" id="of-level" name="coordinator_level">
                                <option value="">Not assigned</option>
                                <?php foreach ($POSITIONS as $key => $pos): ?>
                                    <option value="<?= e($key) ?>" <?= $row['coordinator_level'] === $key ? 'selected' : '' ?>><?= e($pos['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-area">Assigned area</label>
                            <input class="form-control" id="of-area" name="assigned_area" maxlength="191"
                                   value="<?= e((string) $row['assigned_area']) ?>" placeholder="Panchayat / Block / District">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-join">Joining date</label>
                            <input class="form-control" id="of-join" name="joining_date" type="date"
                                   value="<?= e((string) $row['joining_date']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-hon">Honorarium / salary (₹)</label>
                            <input class="form-control" id="of-hon" name="honorarium" type="number" step="0.01" min="0"
                                   value="<?= $row['honorarium'] !== null ? e((string) $row['honorarium']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-by">Approved by</label>
                            <input class="form-control" id="of-by" name="approved_by" maxlength="128"
                                   value="<?= e((string) $row['approved_by']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-desig">Designation</label>
                            <input class="form-control" id="of-desig" name="approver_designation" maxlength="128"
                                   value="<?= e((string) $row['approver_designation']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="of-notes">Internal notes</label>
                            <textarea class="form-control" id="of-notes" name="office_notes" rows="3"
                                      placeholder="Verification findings, interview notes…"><?= e((string) $row['office_notes']) ?></textarea>
                            <small class="form-hint">Never shown to the applicant.</small>
                        </div>

                        <button class="btn btn-primary btn-block" type="submit"><?= lucide('save') ?> Save office record</button>
                    </form>

                    <?php if (!empty($row['reviewed_at'])): ?>
                        <p class="form-hint" style="margin-top:.75rem;">
                            Last updated <?= e(time_ago($row['reviewed_at'])) ?><?= $reviewer ? ' by ' . e((string) $reviewer) : '' ?>.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ------------------------------------------------- ACTIONS -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Actions</h2></div>
                <div class="panel-body">
                    <a class="btn btn-outline btn-block" href="mailto:<?= e($row['email']) ?>?subject=<?= e(rawurlencode('Your ' . coord_position_label($row['position']) . ' application ' . ($row['application_no'] ?: ''))) ?>">
                        <?= lucide('mail') ?> Email Applicant
                    </a>
                    <div class="divider ca-sep"></div>
                    <form method="post" action="<?= e(admin_url('coordinator-applications')) ?>"
                          data-confirm="Delete this application and its uploaded documents permanently?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="_return" value="coordinator-applications">
                        <button class="btn btn-danger btn-block" type="submit"><?= lucide('trash-2') ?> Delete Application</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    include __DIR__ . '/partials/foot.php';
    exit;
}

/* -------------------------------------------------------------- LIST */
$page_title = 'Coordinator Applications';

$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
$posFilter    = (string) get('position', '');
if (!isset($STATUSES[$statusFilter]))  $statusFilter = '';
if (!isset($POSITIONS[$posFilter]))    $posFilter    = '';

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($posFilter !== '') {
    $where .= ' AND position = :position';
    $params[':position'] = $posFilter;
}
if ($search !== '') {
    /* CONCAT_WS, not "a LIKE :q OR b LIKE :q" — the connection runs with
       emulated prepares off, where reusing one named placeholder is an
       HY093 error. See the note in includes/database.php. */
    $where .= " AND CONCAT_WS(' ', application_no, name, email, phone, whatsapp, state, district, block, panchayat, village) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 15);

/* Per-status counts for the stat cards (unfiltered totals). */
$counts = ['all' => (int) db_value("SELECT COUNT(*) FROM $table")];
foreach (array_keys($STATUSES) as $st) {
    $counts[$st] = (int) db_value("SELECT COUNT(*) FROM $table WHERE status = :s", [':s' => $st]);
}

/* Path used by row forms to return to the current filtered/paged list. */
$listReturn = 'coordinator-applications';
$listQs = http_build_query(array_filter([
    'status'   => $statusFilter,
    'position' => $posFilter,
    'q'        => $search,
    'page'     => (int) $p['current'] > 1 ? (int) $p['current'] : '',
], static fn($v) => $v !== '' && $v !== null));
if ($listQs !== '') $listReturn .= '?' . $listQs;

$statCards = [
    ['key' => '',             'label' => 'Total',        'value' => $counts['all'],          'icon' => 'inbox',        'bg' => 'bg-blue'],
    ['key' => 'new',          'label' => 'New',          'value' => $counts['new'],          'icon' => 'sparkles',     'bg' => 'bg-cyan'],
    ['key' => 'under_review', 'label' => 'Under Review', 'value' => $counts['under_review'], 'icon' => 'search-check', 'bg' => 'bg-violet'],
    ['key' => 'shortlisted',  'label' => 'Shortlisted',  'value' => $counts['shortlisted'],  'icon' => 'star',         'bg' => 'bg-amber'],
    ['key' => 'approved',     'label' => 'Approved',     'value' => $counts['approved'],     'icon' => 'circle-check', 'bg' => 'bg-green'],
    ['key' => 'rejected',     'label' => 'Rejected',     'value' => $counts['rejected'],     'icon' => 'ban',          'bg' => 'bg-rose'],
];

include __DIR__ . '/partials/head.php';
?>
<style>
    /* Layout only, plus the compact in-row status select. */
    .ca-w-status { width: 170px; }
    .admin-content .ca-status-select { padding: var(--sp-1) var(--sp-2); }
    .ca-docs-count { white-space: nowrap; }
    .ca-when { display: block; font-size: .86rem; font-weight: 500; white-space: nowrap; }
</style>

<div class="admin-page-head">
    <div>
        <h1>Coordinator Applications</h1>
        <span class="muted"><?= (int) $p['total'] ?> matching · <?= (int) $counts['all'] ?> total</span>
    </div>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <a class="stat-card"<?= $statusFilter === $c['key'] ? ' aria-current="page"' : '' ?>
           href="<?= e(admin_url('coordinator-applications' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>">
            <div class="stat-icon <?= e($c['bg']) ?>"><?= lucide($c['icon']) ?></div>
            <div>
                <div class="stat-value"><?= e(number_format($c['value'])) ?></div>
                <div class="stat-label"><?= e($c['label']) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('coordinator-applications')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <?php if ($posFilter !== ''): ?><input type="hidden" name="position" value="<?= e($posFilter) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>"
                       placeholder="Search reference, name, email, phone, district…">
            </form>
            <form method="get" action="<?= e(admin_url('coordinator-applications')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <select class="form-select" name="position" onchange="this.form.submit()">
                    <option value="">All positions</option>
                    <?php foreach ($POSITIONS as $key => $pos): ?>
                        <option value="<?= e($key) ?>" <?= $posFilter === $key ? 'selected' : '' ?>><?= e($pos['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="get" action="<?= e(admin_url('coordinator-applications')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <?php if ($posFilter !== ''): ?><input type="hidden" name="position" value="<?= e($posFilter) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($STATUSES as $key => $meta): ?>
                        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($statusFilter !== '' || $posFilter !== '' || $search !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('coordinator-applications')) ?>">Clear</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Applicant</th><th>Position</th><th>Area</th><th>Docs</th><th>Submitted</th>
                    <th class="ca-w-status">Status</th><th class="num">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                    $docCount = count(coord_json($r['documents']));
                    $area = array_filter([$r['village'], $r['panchayat'], $r['block'], $r['district'], $r['state']]);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($r['name']) ?></strong><br>
                            <small class="text-muted">
                                <?= e($r['application_no'] ?: '#' . (int) $r['id']) ?> · <?= e($r['email']) ?>
                            </small>
                        </td>
                        <td>
                            <?= e(coord_position_label($r['position'])) ?>
                            <?php if (!empty($r['work_mode'])): ?><br><small class="text-muted"><?= e($r['work_mode']) ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($area): ?>
                                <small><?= e(implode(', ', array_slice($area, 0, 3))) ?></small>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td class="ca-docs-count">
                            <?php if ($docCount > 0): ?>
                                <span class="pill pill-cyan"><?= lucide('paperclip') ?> <?= (int) $docCount ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <?php /* The actual date and time leads; "3 hours ago" is the
                                 supporting detail. A reviewer working a queue needs to
                                 know WHEN an application arrived, not only how long
                                 ago — and a relative label alone hid the timezone bug
                                 that made every row read 5h30m early. */ ?>
                        <td>
                            <span class="ca-when"><?= e(format_date($r['created_at'], 'd M Y')) ?></span>
                            <small class="text-muted"><?= e(format_date($r['created_at'], 'g:i a')) ?>
                                · <?= e(time_ago($r['created_at'])) ?></small>
                        </td>
                        <td>
                            <form method="post" action="<?= e(admin_url('coordinator-applications')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                <select class="form-select ca-status-select" name="status" onchange="this.form.submit()">
                                    <?php foreach ($STATUSES as $key => $meta): ?>
                                        <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('coordinator-applications?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('coordinator-applications')) ?>"
                                      data-confirm="Delete this application and its uploaded documents permanently?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="_do" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= $p['links'] ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon"><?= lucide('users-round') ?></div>
                <?php if ($statusFilter !== '' || $posFilter !== '' || $search !== ''): ?>
                    No applications match your filters. <a href="<?= e(admin_url('coordinator-applications')) ?>">Clear filters</a>.
                <?php else: ?>
                    No coordinator applications have been received yet.
                    The public form lives at <a href="<?= e(url('coordinator-apply')) ?>" target="_blank" rel="noopener">/coordinator-apply</a>.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
