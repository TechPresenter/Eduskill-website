<?php
/**
 * =============================================================================
 *  Admin — Kanya Daan Project applications.
 * -----------------------------------------------------------------------------
 *  An INBOX module with a case workflow on top: applications arrive from the
 *  public form and move new -> verifying -> verified -> approved -> distributed,
 *  with waitlisted and rejected as off-ladder outcomes.
 *
 *  Screens:
 *    list   dashboard stat cards, district/block breakdown, filters, search
 *    view   the full application, plus the office panel that records document
 *           verification, field verification, need assessment, committee
 *           approval, distribution and acknowledgement
 *
 *  Documents are linked with secure_upload_url(), never upload_url(): they are
 *  identity, age, income and bank records for a vulnerable family, held in a
 *  directory Apache refuses to serve. admin/secure-file.php is the only route.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'kanyadaan_applications';
$action = get('action', 'list');
$id     = (int) get('id', 0);

$STATUSES = kd_statuses();
$DOCS     = kd_documents();

/** Keep redirects inside this module (guards against open redirects). */
$safe_return = static function (string $ret): string {
    return strncmp($ret, 'kanyadaan-applications', 22) === 0 ? $ret : 'kanyadaan-applications';
};

/** Optional date field — stored only when it is a real Y-m-d. */
$dateOf = static function (string $key): ?string {
    $v = trim((string) post($key, ''));
    $d = $v === '' ? false : DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
};

/* -------------------------------------------------------------- STATUS CHANGE */
if (is_post() && post('_do') === 'status') {
    require_csrf();
    $sid    = (int) post('id', 0);
    $status = (string) post('status', '');
    $return = $safe_return((string) post('_return', 'kanyadaan-applications'));

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
        log_activity('update', $table, 'Set Kanya Daan application #' . $sid . ' to ' . $status);
        set_flash('success', 'Status updated to "' . $STATUSES[$status]['label'] . '".');
    } else {
        set_flash('error', 'Application not found.');
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- CASE RECORD */
if (is_post() && post('_do') === 'case') {
    require_csrf();
    $cid = (int) post('id', 0);
    $row = find($table, $cid);
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/kanyadaan-applications');
    }

    $status = (string) post('status', $row['status']);
    if (!isset($STATUSES[$status])) {
        $status = $row['status'];
    }
    $verification = (string) post('field_verification', 'pending');
    if (!array_key_exists($verification, kd_verification_states())) {
        $verification = 'pending';
    }
    $sanctioned = trim((string) post('sanctioned_amount', ''));

    db_update($table, [
        'status'               => $status,
        'docs_verified'        => (int) post('docs_verified', 0) === 1 ? 1 : 0,
        'field_verification'   => $verification,
        'field_verified_by'    => clean(post('field_verified_by', '')) ?: null,
        'field_verified_on'    => $dateOf('field_verified_on'),
        'verification_notes'   => clean(post('verification_notes', '')) ?: null,
        'need_assessment'      => clean(post('need_assessment', '')) ?: null,
        // Clamped: a negative sanction is never a real value.
        'sanctioned_amount'    => is_numeric($sanctioned) ? round(max(0.0, (float) $sanctioned), 2) : null,
        'approved_by'          => clean(post('approved_by', '')) ?: null,
        'approval_date'        => $dateOf('approval_date'),
        'assigned_coordinator' => clean(post('assigned_coordinator', '')) ?: null,
        'distributed_on'       => $dateOf('distributed_on'),
        'distribution_notes'   => clean(post('distribution_notes', '')) ?: null,
        'acknowledged'         => (int) post('acknowledged', 0) === 1 ? 1 : 0,
        'rejection_reason'     => mb_substr(clean(post('rejection_reason', '')), 0, 500) ?: null,
        'office_notes'         => clean(post('office_notes', '')) ?: null,
        'reviewed_by'          => (int) (current_user()['id'] ?? 0) ?: null,
        'reviewed_at'          => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $cid]);

    log_activity('update', $table, 'Updated case record for Kanya Daan application #' . $cid);
    set_flash('success', 'Case record saved.');
    redirect('/admin/kanyadaan-applications?action=view&id=' . $cid);
}

/* -------------------------------------------------------------- DELETE */
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $delId  = (int) post('id', 0);
    $return = $safe_return((string) post('_return', 'kanyadaan-applications'));
    $row = find($table, $delId);
    if ($row) {
        foreach (kd_json($row['documents']) as $path) {
            delete_upload(is_string($path) ? $path : null);
        }
        db_delete($table, 'id = :id', [':id' => $delId]);
        log_activity('delete', $table, 'Deleted Kanya Daan application #' . $delId);
        set_flash('success', 'Application deleted.');
    }
    if (strncmp($return, 'kanyadaan-applications?action=view', 34) === 0) {
        $return = 'kanyadaan-applications';
    }
    redirect('/admin/' . $return);
}

/* -------------------------------------------------------------- VIEW (detail) */
if ($action === 'view') {
    $row = find($table, $id);
    if (!$row) {
        set_flash('error', 'Application not found.');
        redirect('/admin/kanyadaan-applications');
    }

    $family   = kd_json($row['family_members']);
    $files    = kd_json($row['documents']);
    $brideId  = kd_reveal($row['bride_id_no']);
    $bankNo   = kd_reveal($row['bank_account']);
    $reviewer = !empty($row['reviewed_by'])
        ? db_value('SELECT name FROM users WHERE id = :i', [':i' => (int) $row['reviewed_by']])
        : null;
    $meta = $STATUSES[$row['status']] ?? ['label' => ucfirst($row['status']), 'pill' => 'pill-gray', 'step' => 0];

    /** One label/value row, skipped entirely when there is nothing to show. */
    $line = static function (string $label, ?string $value, bool $pre = false): void {
        if ($value === null || trim($value) === '') {
            return;
        }
        echo '<tr><th class="kd-w-label">' . e($label) . '</th><td'
           . ($pre ? ' class="kd-pre"' : '') . '>' . e($value) . '</td></tr>';
    };
    /** A yes/no pill. */
    $yn = static function ($v): string {
        return ((int) $v === 1)
            ? '<span class="pill pill-green">Yes</span>'
            : '<span class="pill pill-gray">No</span>';
    };

    $page_title = 'Kanya Daan Application';
    include __DIR__ . '/partials/head.php';
    ?>
    <style>
        /* Layout only — everything else is the shared admin design system. */
        .kd-w-label { width: 210px; }
        .kd-pre { white-space: pre-wrap; line-height: 1.6; }
        .kd-tags { display: flex; flex-wrap: wrap; gap: .35rem; }
        .kd-docs { display: grid; gap: .5rem; }
        .kd-doc {
            display: flex; align-items: center; gap: .7rem;
            padding: .6rem .75rem; border: 1px solid var(--line, #e5e7eb); border-radius: 10px;
        }
        .kd-doc-name { flex: 1 1 auto; min-width: 0; font-size: .88rem; }
        .kd-doc-missing { opacity: .55; }
        .kd-sep { margin: var(--sp-5) 0; }
        .kd-case .form-group { margin-bottom: var(--sp-3); }
        /* Case progress rail */
        .kd-rail { display: flex; align-items: center; gap: .35rem; margin: var(--sp-3) 0 var(--sp-4); flex-wrap: wrap; }
        .kd-rail-step {
            display: flex; align-items: center; gap: .4rem;
            padding: .35rem .7rem; border-radius: 999px;
            border: 1px solid var(--line, #e5e7eb); font-size: .78rem; font-weight: 600;
            color: var(--muted, #6b7280);
        }
        .kd-rail-step.is-done { background: color-mix(in srgb, #16a34a 12%, transparent); border-color: transparent; color: #15803d; }
        .kd-rail-step.is-now  { background: color-mix(in srgb, #2563eb 14%, transparent); border-color: transparent; color: #1d4ed8; }
        .kd-flag { padding: .6rem .8rem; border-radius: 8px; font-size: .85rem;
                   background: color-mix(in srgb, #dc2626 8%, transparent); color: #b91c1c; margin-bottom: var(--sp-3); }
    </style>

    <div class="admin-page-head">
        <div>
            <h1><?= e($row['bride_name']) ?></h1>
            <span class="muted">
                Kanya Daan / <?= e($row['application_no'] ?: ('#' . (int) $row['id'])) ?>
                · applied by <?= e($row['applicant_name']) ?>
            </span>
        </div>
        <a class="btn btn-secondary" href="<?= e(admin_url('kanyadaan-applications')) ?>">&larr; Back to list</a>
    </div>

    <?php /* The case rail makes the workflow position readable at a glance. */ ?>
    <div class="kd-rail">
        <?php foreach ($STATUSES as $key => $st): ?>
            <?php if ($st['step'] === 0) continue; ?>
            <span class="kd-rail-step<?= $meta['step'] > $st['step'] ? ' is-done' : ($meta['step'] === $st['step'] ? ' is-now' : '') ?>">
                <?= lucide($meta['step'] > $st['step'] ? 'circle-check' : 'circle') ?> <?= e($st['label']) ?>
            </span>
        <?php endforeach; ?>
        <?php if ($meta['step'] === 0): ?>
            <span class="pill <?= e($meta['pill']) ?>"><?= e($meta['label']) ?></span>
        <?php endif; ?>
    </div>

    <div class="grid grid-2 cols-top">
        <div>
            <!-- ------------------------------------------- APPLICANT + BRIDE -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Applicant &amp; Bride</h2></div>
                <div class="panel-body">
                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <tr><th class="kd-w-label">Status</th>
                                <td><span class="pill <?= e($meta['pill']) ?>"><?= e($meta['label']) ?></span></td></tr>
                            <?php
                            $line('Reference', $row['application_no']);
                            $line('Applicant', $row['applicant_name']);
                            $rel = kd_relationships()[$row['relationship']] ?? $row['relationship'];
                            $line('Relationship with bride', $rel . ($row['relationship_other'] ? ' — ' . $row['relationship_other'] : ''));
                            ?>
                            <tr><th>Mobile</th><td>
                                <a href="tel:<?= e(($row['country_dial'] ? '+' . $row['country_dial'] : '') . $row['phone']) ?>">
                                    <?= e(($row['country_dial'] ? '+' . $row['country_dial'] . ' ' : '') . $row['phone']) ?></a>
                            </td></tr>
                            <?php
                            $line('WhatsApp', $row['whatsapp']);
                            if (!empty($row['email'])) {
                                echo '<tr><th>Email</th><td><a href="mailto:' . e($row['email']) . '">' . e($row['email']) . '</a></td></tr>';
                            }
                            $place = array_filter([$row['village'], $row['panchayat'], $row['block'], $row['district'], $row['state']]);
                            $line('Location', $place ? implode(' · ', $place) : null);
                            ?>
                            <tr><th colspan="2" style="padding-top:1rem;"><strong>Bride</strong></th></tr>
                            <?php
                            $line('Name', $row['bride_name']);
                            $line('Date of birth', $row['bride_dob'] ? date('d M Y', strtotime($row['bride_dob'])) : null);
                            $line('Age', $row['bride_age'] !== null ? $row['bride_age'] . ' years' : null);
                            $line('Education', $row['bride_education']);
                            $line('Occupation', $row['bride_occupation']);
                            $line('Marital status', $row['marital_status']);
                            ?>
                            <?php if ($row['bride_id_no']): ?>
                                <tr><th>Aadhaar / ID</th><td>
                                    <?php if ($brideId !== null): ?>
                                        <code><?= e($brideId) ?></code>
                                        <?php if (sec_is_encrypted($row['bride_id_no'])): ?>
                                            <span class="pill pill-green"><?= lucide('lock') ?> Encrypted at rest</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Encrypted — not readable with the current APP_KEY.
                                            <?php if ($row['bride_id_last4']): ?>Ends in <?= e($row['bride_id_last4']) ?>.<?php endif; ?></span>
                                    <?php endif; ?>
                                </td></tr>
                            <?php endif; ?>
                            <?php if ($row['bank_account']): ?>
                                <tr><th>Bank account</th><td>
                                    <?php if ($bankNo !== null): ?>
                                        <code><?= e($bankNo) ?></code>
                                        <?php if (sec_is_encrypted($row['bank_account'])): ?>
                                            <span class="pill pill-green"><?= lucide('lock') ?> Encrypted</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Encrypted — not readable.
                                            <?php if ($row['bank_last4']): ?>Ends in <?= e($row['bank_last4']) ?>.<?php endif; ?></span>
                                    <?php endif; ?>
                                </td></tr>
                            <?php endif; ?>
                            <?php
                            $line('Bank & branch', $row['bank_name']);
                            $line('IFSC', $row['bank_ifsc']);
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

            <!-- --------------------------------------------- GROOM + MARRIAGE -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Groom &amp; Marriage</h2></div>
                <div class="panel-body">
                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <?php
                            $line('Groom name', $row['groom_name']);
                            $line('Date of birth', $row['groom_dob'] ? date('d M Y', strtotime($row['groom_dob'])) : null);
                            $line('Age', $row['groom_age'] !== null ? $row['groom_age'] . ' years' : null);
                            $line('Occupation', $row['groom_occupation']);
                            $line('Address', $row['groom_address'], true);
                            $line('Proposed marriage date', $row['marriage_date'] ? date('d M Y', strtotime($row['marriage_date'])) : null);
                            $line('Marriage location', $row['marriage_location']);
                            $line('Type / arrangement', $row['marriage_type']);
                            ?>
                            <tr><th>Declared legally permissible</th><td><?= $yn($row['legally_permissible']) ?></td></tr>
                            <tr><th>Not a dowry request</th><td><?= $yn($row['dowry_declaration']) ?></td></tr>
                        </tbody>
                    </table>
                    </div>
                    <?php
                    /* A visible flag rather than a silent one: the handler refuses
                       under-age cases, so this can only appear if a row was edited
                       outside the form or the law changed. It must not pass quietly. */
                    $ageFlags = [];
                    if ($row['bride_age'] !== null && (int) $row['bride_age'] < kd_min_age('bride')) {
                        $ageFlags[] = 'bride is recorded as ' . (int) $row['bride_age'];
                    }
                    if ($row['groom_age'] !== null && (int) $row['groom_age'] < kd_min_age('groom')) {
                        $ageFlags[] = 'groom is recorded as ' . (int) $row['groom_age'];
                    }
                    if ($ageFlags): ?>
                        <div class="kd-flag" style="margin-top:.75rem;">
                            <?= lucide('triangle-alert') ?>
                            <strong>Below statutory age of marriage:</strong> <?= e(implode('; ', $ageFlags)) ?>.
                            This case must not be approved.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ------------------------------------------------ FAMILY + MEANS -->
            <div class="panel">
                <div class="panel-head"><h2 class="panel-title">Family &amp; Economic Condition</h2></div>
                <div class="panel-body">
                    <?php if ($family): ?>
                        <div class="table-wrap">
                        <table class="admin-table">
                            <thead><tr>
                                <?php foreach (kd_family_fields() as $meta2): ?><th><?= e($meta2['label']) ?></th><?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($family as $m): ?>
                                    <tr>
                                        <?php foreach (array_keys(kd_family_fields()) as $key): ?>
                                            <td><?= e((string) ($m[$key] ?? '')) ?: '<span class="text-muted">—</span>' ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <div class="divider kd-sep"></div>
                    <?php endif; ?>

                    <div class="table-wrap">
                    <table class="admin-table">
                        <tbody>
                            <?php
                            $line('Monthly family income', $row['monthly_income'] !== null ? '₹' . number_format((float) $row['monthly_income'], 2) : null);
                            $line('Annual family income', $row['annual_income'] !== null ? '₹' . number_format((float) $row['annual_income'], 2) : null);
                            $line('House type', kd_house_types()[$row['house_type']] ?? null);
                            $line('Family members', $row['family_size'] !== null ? (string) $row['family_size'] : null);
                            $line('Earning members', $row['earning_members'] !== null ? (string) $row['earning_members'] : null);
                            ?>
                            <tr><th class="kd-w-label">Financial hardship</th><td><?= $yn($row['financial_hardship']) ?></td></tr>
                            <?php $line('Reason for request', $row['hardship_reason'], true); ?>
                            <tr><th>Government assistance</th><td><?= $yn($row['govt_assistance']) ?></td></tr>
                            <?php
                            $line('Assistance details', $row['govt_assistance_details']);
                            $line('Justification', $row['support_justification'], true);
                            ?>
                        </tbody>
                    </table>
                    </div>

                    <?php if (!empty($row['support_items'])): ?>
                        <div class="divider kd-sep"></div>
                        <strong>Support requested</strong>
                        <div class="kd-tags" style="margin-top:.5rem;">
                            <?php foreach (array_filter(array_map('trim', explode(',', $row['support_items']))) as $it): ?>
                                <span class="pill pill-cyan"><?= e($it) ?></span>
                            <?php endforeach; ?>
                        </div>
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
                    <div class="kd-docs">
                        <?php foreach ($DOCS as $slot => $doc): ?>
                            <?php $path = $files[$slot] ?? null; ?>
                            <div class="kd-doc<?= $path ? '' : ' kd-doc-missing' ?>">
                                <?= lucide($path ? 'file-check' : 'file-x') ?>
                                <span class="kd-doc-name">
                                    <?= e($doc['label']) ?>
                                    <?php if (!empty($doc['required']) && !$path): ?>
                                        <span class="pill pill-red">Missing</span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($path): ?>
                                    <a class="btn btn-outline btn-sm" href="<?= e(secure_upload_url($path)) ?>"
                                       target="_blank" rel="noopener"><?= lucide('download') ?> Open</a>
                                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-hint" style="margin-top:.75rem;">
                        Downloads pass through the authenticated file gate — the URLs are useless without an admin session.
                    </p>
                </div>
            </div>

            <!-- ------------------------------------------------ CASE RECORD -->
            <div class="panel kd-case">
                <div class="panel-head"><h2 class="panel-title">Case Record</h2></div>
                <div class="panel-body">
                    <form method="post" action="<?= e(admin_url('kanyadaan-applications')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="case">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

                        <div class="form-group">
                            <label class="form-label" for="kc-status">Case status</label>
                            <select class="form-select" id="kc-status" name="status">
                                <?php foreach ($STATUSES as $key => $st): ?>
                                    <option value="<?= e($key) ?>" <?= $row['status'] === $key ? 'selected' : '' ?>><?= e($st['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <h4 class="form-label" style="margin-top:1rem;"><?= lucide('file-search') ?> Verification</h4>

                        <div class="form-group">
                            <label class="form-label" for="kc-docs">
                                <input type="checkbox" id="kc-docs" name="docs_verified" value="1" <?= (int) $row['docs_verified'] === 1 ? 'checked' : '' ?>>
                                Documents verified
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="kc-field">Field verification</label>
                            <select class="form-select" id="kc-field" name="field_verification">
                                <?php foreach (kd_verification_states() as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= $row['field_verification'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="kc-fby">Verified by</label>
                            <input class="form-control" id="kc-fby" name="field_verified_by" maxlength="128"
                                   value="<?= e((string) $row['field_verified_by']) ?>" placeholder="Coordinator name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-fon">Verified on</label>
                            <input class="form-control" id="kc-fon" name="field_verified_on" type="date"
                                   value="<?= e((string) $row['field_verified_on']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-vnotes">Verification notes</label>
                            <textarea class="form-control" id="kc-vnotes" name="verification_notes" rows="3"><?= e((string) $row['verification_notes']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-need">Need assessment</label>
                            <textarea class="form-control" id="kc-need" name="need_assessment" rows="3"
                                      placeholder="What the family actually needs, and why."><?= e((string) $row['need_assessment']) ?></textarea>
                        </div>

                        <h4 class="form-label" style="margin-top:1rem;"><?= lucide('gavel') ?> Committee approval</h4>

                        <div class="form-group">
                            <label class="form-label" for="kc-amt">Sanctioned amount (₹)</label>
                            <input class="form-control" id="kc-amt" name="sanctioned_amount" type="number" step="0.01" min="0"
                                   value="<?= $row['sanctioned_amount'] !== null ? e((string) $row['sanctioned_amount']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-aby">Approved by</label>
                            <input class="form-control" id="kc-aby" name="approved_by" maxlength="128"
                                   value="<?= e((string) $row['approved_by']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-adate">Approval date</label>
                            <input class="form-control" id="kc-adate" name="approval_date" type="date"
                                   value="<?= e((string) $row['approval_date']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-coord">Assigned coordinator</label>
                            <input class="form-control" id="kc-coord" name="assigned_coordinator" maxlength="128"
                                   value="<?= e((string) $row['assigned_coordinator']) ?>">
                        </div>

                        <h4 class="form-label" style="margin-top:1rem;"><?= lucide('package-check') ?> Distribution</h4>

                        <div class="form-group">
                            <label class="form-label" for="kc-don">Distributed on</label>
                            <input class="form-control" id="kc-don" name="distributed_on" type="date"
                                   value="<?= e((string) $row['distributed_on']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-dnotes">Distribution notes</label>
                            <textarea class="form-control" id="kc-dnotes" name="distribution_notes" rows="3"
                                      placeholder="Items handed over, date, witnesses."><?= e((string) $row['distribution_notes']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-ack">
                                <input type="checkbox" id="kc-ack" name="acknowledged" value="1" <?= (int) $row['acknowledged'] === 1 ? 'checked' : '' ?>>
                                Beneficiary acknowledgement received
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="kc-reject">Rejection reason</label>
                            <input class="form-control" id="kc-reject" name="rejection_reason" maxlength="500"
                                   value="<?= e((string) $row['rejection_reason']) ?>" placeholder="Only if the case is rejected">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="kc-notes">Internal notes</label>
                            <textarea class="form-control" id="kc-notes" name="office_notes" rows="3"><?= e((string) $row['office_notes']) ?></textarea>
                            <small class="form-hint">Never shown to the applicant.</small>
                        </div>

                        <button class="btn btn-primary btn-block" type="submit"><?= lucide('save') ?> Save case record</button>
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
                    <?php if (!empty($row['email'])): ?>
                        <a class="btn btn-outline btn-block" href="mailto:<?= e($row['email']) ?>?subject=<?= e(rawurlencode('Kanya Daan application ' . ($row['application_no'] ?: ''))) ?>">
                            <?= lucide('mail') ?> Email Applicant
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-outline btn-block" href="tel:<?= e(($row['country_dial'] ? '+' . $row['country_dial'] : '') . $row['phone']) ?>">
                        <?= lucide('phone') ?> Call <?= e($row['phone']) ?>
                    </a>
                    <div class="divider kd-sep"></div>
                    <form method="post" action="<?= e(admin_url('kanyadaan-applications')) ?>"
                          data-confirm="Delete this application and its uploaded documents permanently?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_do" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                        <input type="hidden" name="_return" value="kanyadaan-applications">
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
$page_title = 'Kanya Daan Applications';

$search       = trim((string) get('q', ''));
$statusFilter = (string) get('status', '');
$districtFilt = trim((string) get('district', ''));
if (!isset($STATUSES[$statusFilter])) $statusFilter = '';

$where  = '1=1';
$params = [];
if ($statusFilter !== '') {
    $where .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($districtFilt !== '') {
    $where .= ' AND district = :district';
    $params[':district'] = $districtFilt;
}
if ($search !== '') {
    /* CONCAT_WS, not "a LIKE :q OR b LIKE :q" — emulated prepares are off, so
       reusing one named placeholder is an HY093 error. */
    $where .= " AND CONCAT_WS(' ', application_no, applicant_name, bride_name, groom_name, phone, whatsapp,"
            . " email, state, district, block, panchayat, village) LIKE :q";
    $params[':q'] = '%' . $search . '%';
}

$p = paginate("SELECT * FROM $table WHERE $where ORDER BY id DESC", $params, 15);

/* Dashboard figures (unfiltered). */
$counts = ['all' => (int) db_value("SELECT COUNT(*) FROM $table")];
foreach (array_keys($STATUSES) as $st) {
    $counts[$st] = (int) db_value("SELECT COUNT(*) FROM $table WHERE status = :s", [':s' => $st]);
}
$sanctionedTotal  = (float) db_value("SELECT COALESCE(SUM(sanctioned_amount), 0) FROM $table WHERE status IN ('approved','distributed')");
$distributedTotal = (float) db_value("SELECT COALESCE(SUM(sanctioned_amount), 0) FROM $table WHERE status = 'distributed'");
$avgAssistance    = (float) db_value("SELECT COALESCE(AVG(sanctioned_amount), 0) FROM $table WHERE status = 'distributed' AND sanctioned_amount > 0");
$districtsCovered = (int) db_value("SELECT COUNT(DISTINCT district) FROM $table WHERE district IS NOT NULL AND district <> ''");
$ackCount         = (int) db_value("SELECT COUNT(*) FROM $table WHERE acknowledged = 1");

/* District / block breakdown — the brief asks for both. */
$byDistrict = db_all(
    "SELECT district, COUNT(*) AS n,
            SUM(status = 'distributed') AS done,
            SUM(status IN ('new','verifying')) AS pending
       FROM $table
      WHERE district IS NOT NULL AND district <> ''
      GROUP BY district ORDER BY n DESC LIMIT 10"
);
$byBlock = db_all(
    "SELECT district, block, COUNT(*) AS n
       FROM $table
      WHERE block IS NOT NULL AND block <> ''
      GROUP BY district, block ORDER BY n DESC LIMIT 10"
);

/* Path used by row forms to return to the current filtered/paged list. */
$listReturn = 'kanyadaan-applications';
$listQs = http_build_query(array_filter([
    'status'   => $statusFilter,
    'district' => $districtFilt,
    'q'        => $search,
    'page'     => (int) $p['current'] > 1 ? (int) $p['current'] : '',
], static fn($v) => $v !== '' && $v !== null));
if ($listQs !== '') $listReturn .= '?' . $listQs;

$statCards = [
    ['key' => '',            'label' => 'Total',        'value' => $counts['all'],         'icon' => 'inbox',        'bg' => 'bg-blue'],
    ['key' => 'new',         'label' => 'New',          'value' => $counts['new'],         'icon' => 'sparkles',     'bg' => 'bg-cyan'],
    ['key' => 'verifying',   'label' => 'Verifying',    'value' => $counts['verifying'],   'icon' => 'file-search',  'bg' => 'bg-violet'],
    ['key' => 'verified',    'label' => 'Verified',     'value' => $counts['verified'],    'icon' => 'circle-check', 'bg' => 'bg-cyan'],
    ['key' => 'approved',    'label' => 'Approved',     'value' => $counts['approved'],    'icon' => 'gavel',        'bg' => 'bg-amber'],
    ['key' => 'distributed', 'label' => 'Distributed',  'value' => $counts['distributed'], 'icon' => 'package-check','bg' => 'bg-green'],
    ['key' => 'waitlisted',  'label' => 'Waitlisted',   'value' => $counts['waitlisted'],  'icon' => 'clock',        'bg' => 'bg-blue'],
    ['key' => 'rejected',    'label' => 'Rejected',     'value' => $counts['rejected'],    'icon' => 'ban',          'bg' => 'bg-rose'],
];

include __DIR__ . '/partials/head.php';
?>
<style>
    .kd-w-status { width: 175px; }
    .admin-content .kd-status-select { padding: var(--sp-1) var(--sp-2); }
    .kd-money { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: var(--sp-3); margin-bottom: var(--sp-4); }
    .kd-money-card { padding: var(--sp-4); border: 1px solid var(--line, #e5e7eb); border-radius: 12px; }
    .kd-money-card .v { font-size: 1.35rem; font-weight: 800; }
    .kd-money-card .l { font-size: .8rem; color: var(--muted, #6b7280); }
    .kd-split { display: grid; grid-template-columns: 1fr 1fr; gap: var(--sp-4); }
    @media (max-width: 900px) { .kd-split { grid-template-columns: 1fr; } }
</style>

<div class="admin-page-head">
    <div>
        <h1>Kanya Daan Applications</h1>
        <span class="muted"><?= (int) $p['total'] ?> matching · <?= (int) $counts['all'] ?> total</span>
    </div>
    <a class="btn btn-secondary" href="<?= e(url('kanyadaan-apply')) ?>" target="_blank" rel="noopener">
        <?= lucide('external-link') ?> View public form
    </a>
</div>

<div class="stat-grid">
    <?php foreach ($statCards as $c): ?>
        <a class="stat-card"<?= $statusFilter === $c['key'] ? ' aria-current="page"' : '' ?>
           href="<?= e(admin_url('kanyadaan-applications' . ($c['key'] !== '' ? '?status=' . $c['key'] : ''))) ?>">
            <div class="stat-icon <?= e($c['bg']) ?>"><?= lucide($c['icon']) ?></div>
            <div>
                <div class="stat-value"><?= e(number_format($c['value'])) ?></div>
                <div class="stat-label"><?= e($c['label']) ?></div>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php /* Programme figures. Only assistance actually sanctioned is counted, so
         these never overstate what the project has committed. */ ?>
<div class="kd-money">
    <div class="kd-money-card">
        <div class="v">₹<?= e(number_format($sanctionedTotal, 2)) ?></div>
        <div class="l">Total sanctioned (approved + distributed)</div>
    </div>
    <div class="kd-money-card">
        <div class="v">₹<?= e(number_format($distributedTotal, 2)) ?></div>
        <div class="l">Assistance distributed</div>
    </div>
    <div class="kd-money-card">
        <div class="v">₹<?= e(number_format($avgAssistance, 2)) ?></div>
        <div class="l">Average per beneficiary</div>
    </div>
    <div class="kd-money-card">
        <div class="v"><?= (int) $districtsCovered ?></div>
        <div class="l">Districts covered</div>
    </div>
    <div class="kd-money-card">
        <div class="v"><?= (int) $ackCount ?></div>
        <div class="l">Acknowledgements received</div>
    </div>
</div>

<?php if ($byDistrict || $byBlock): ?>
<div class="kd-split">
    <?php if ($byDistrict): ?>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Applications by District</h2></div>
            <div class="panel-body">
                <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>District</th><th class="num">Total</th><th class="num">Pending</th><th class="num">Distributed</th></tr></thead>
                    <tbody>
                        <?php foreach ($byDistrict as $d): ?>
                            <tr>
                                <td><a href="<?= e(admin_url('kanyadaan-applications?district=' . rawurlencode($d['district']))) ?>"><?= e($d['district']) ?></a></td>
                                <td class="num"><?= (int) $d['n'] ?></td>
                                <td class="num"><?= (int) $d['pending'] ?></td>
                                <td class="num"><?= (int) $d['done'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($byBlock): ?>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Applications by Block</h2></div>
            <div class="panel-body">
                <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Block</th><th>District</th><th class="num">Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($byBlock as $b): ?>
                            <tr>
                                <td><?= e($b['block']) ?></td>
                                <td><?= e((string) $b['district']) ?: '<span class="text-muted">—</span>' ?></td>
                                <td class="num"><?= (int) $b['n'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-body">
        <div class="data-toolbar">
            <form class="search" method="get" action="<?= e(admin_url('kanyadaan-applications')) ?>">
                <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
                <?php if ($districtFilt !== ''): ?><input type="hidden" name="district" value="<?= e($districtFilt) ?>"><?php endif; ?>
                <input class="form-control" type="search" name="q" value="<?= e($search) ?>"
                       placeholder="Search reference, bride, applicant, phone, district…">
            </form>
            <form method="get" action="<?= e(admin_url('kanyadaan-applications')) ?>">
                <?php if ($search !== ''): ?><input type="hidden" name="q" value="<?= e($search) ?>"><?php endif; ?>
                <?php if ($districtFilt !== ''): ?><input type="hidden" name="district" value="<?= e($districtFilt) ?>"><?php endif; ?>
                <select class="form-select" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    <?php foreach ($STATUSES as $key => $st): ?>
                        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($st['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($statusFilter !== '' || $search !== '' || $districtFilt !== ''): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('kanyadaan-applications')) ?>">Clear</a>
            <?php endif; ?>
        </div>

        <?php if ($p['items']): ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr>
                    <th>Bride / Applicant</th><th>Area</th><th>Marriage</th><th>Docs</th><th>Sanctioned</th>
                    <th class="kd-w-status">Status</th><th class="num">Actions</th>
                </tr></thead>
                <tbody>
                <?php foreach ($p['items'] as $r): ?>
                    <?php
                    $docCount = count(kd_json($r['documents']));
                    $area = array_filter([$r['village'], $r['block'], $r['district']]);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($r['bride_name']) ?></strong>
                            <?php if ($r['bride_age'] !== null): ?><small class="text-muted"> · <?= (int) $r['bride_age'] ?></small><?php endif; ?>
                            <br>
                            <small class="text-muted">
                                <?= e($r['application_no'] ?: '#' . (int) $r['id']) ?> · <?= e($r['applicant_name']) ?>
                            </small>
                        </td>
                        <td><?php if ($area): ?><small><?= e(implode(', ', $area)) ?></small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                        <td>
                            <?php if (!empty($r['marriage_date'])): ?>
                                <small><?= e(date('d M Y', strtotime($r['marriage_date']))) ?></small>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($docCount > 0): ?>
                                <span class="pill pill-cyan"><?= lucide('paperclip') ?> <?= (int) $docCount ?></span>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['sanctioned_amount'] !== null): ?>
                                ₹<?= e(number_format((float) $r['sanctioned_amount'], 0)) ?>
                            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?= e(admin_url('kanyadaan-applications')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_do" value="status">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <input type="hidden" name="_return" value="<?= e($listReturn) ?>">
                                <select class="form-select kd-status-select" name="status" onchange="this.form.submit()">
                                    <?php foreach ($STATUSES as $key => $st): ?>
                                        <option value="<?= e($key) ?>" <?= $r['status'] === $key ? 'selected' : '' ?>><?= e($st['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="icon-btn" href="<?= e(admin_url('kanyadaan-applications?action=view&id=' . (int) $r['id'])) ?>" title="View"><?= lucide('eye') ?></a>
                                <form method="post" action="<?= e(admin_url('kanyadaan-applications')) ?>"
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
                <div class="icon"><?= lucide('heart-handshake') ?></div>
                <?php if ($statusFilter !== '' || $search !== '' || $districtFilt !== ''): ?>
                    No applications match your filters. <a href="<?= e(admin_url('kanyadaan-applications')) ?>">Clear filters</a>.
                <?php else: ?>
                    No Kanya Daan applications have been received yet.
                    The public form lives at <a href="<?= e(url('kanyadaan-apply')) ?>" target="_blank" rel="noopener">/kanyadaan-apply</a>.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
