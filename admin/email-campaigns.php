<?php
/**
 * Admin — Email Campaigns. Compose (subject + A/B, from, template, segment,
 * schedule), send in batches with open/click tracking, and per-campaign reports.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_marketing.php';

$table  = 'email_campaigns';
$action = get('action', 'list');
$id     = (int) get('id', 0);

/* ---- Save (draft / update) ---- */
if (is_post() && post('_do') === 'save') {
    require_csrf();
    $editId = (int) post('id', 0);
    $errors = validate($_POST, ['name' => 'required|max:191', 'subject' => 'required|max:191']);
    if ($errors) { set_flash('error', reset($errors)); redirect('/admin/email-campaigns?action=' . ($editId ? 'edit&id=' . $editId : 'create')); }

    $body = post('body', '');
    $tplId = (int) post('template_id', 0);
    if (trim(strip_tags($body)) === '' && $tplId) { $tpl = find('email_templates', $tplId); if ($tpl) $body = $tpl['body']; }

    $schedRaw = trim((string) post('scheduled_at', ''));
    $sched = $schedRaw !== '' && strtotime($schedRaw) ? date('Y-m-d H:i:s', strtotime($schedRaw)) : null;

    $data = [
        'name'            => clean(post('name')),
        'subject'         => clean(post('subject')),
        'subject_b'       => clean(post('subject_b', '')) ?: null,
        'ab_enabled'      => post('ab_enabled') ? 1 : 0,
        'from_name'       => clean(post('from_name', '')) ?: null,
        'from_email'      => clean(post('from_email', '')) ?: null,
        'smtp_profile_id' => (int) post('smtp_profile_id', 0) ?: null,
        'template_id'     => $tplId ?: null,
        'body'            => $body,
        'segment_status'  => in_array(post('segment_status'), ['subscribed', 'all'], true) ? post('segment_status') : 'subscribed',
        'segment_tag'     => clean(post('segment_tag', '')) ?: null,
        'scheduled_at'    => $sched,
        'status'          => $sched ? 'scheduled' : 'draft',
    ];
    if ($editId) {
        // Never rewind a campaign that has already started or finished sending.
        // Saving an edit used to force status back to 'draft', and the send
        // handler below calls em_prepare() for a 'draft' campaign — which wipes
        // every email_campaign_recipients row and re-queues the WHOLE list. One
        // typo fix on a sent campaign would therefore re-blast every subscriber.
        $cur = find($table, $editId);
        if ($cur && in_array($cur['status'], ['sending', 'sent', 'paused'], true)) {
            unset($data['status']);
        }
        db_update($table, $data, 'id = :id', [':id' => $editId]);
    }
    else { $data['created_by'] = current_user_id(); $editId = db_insert($table, $data); }
    log_activity('update', 'email-campaigns', 'Saved campaign #' . $editId);
    set_flash('success', 'Campaign saved.');
    redirect('/admin/email-campaigns?action=report&id=' . $editId);
}

/* ---- Send a batch (prepares on first send) ---- */
if (is_post() && post('_do') === 'send') {
    require_csrf();
    $cid = (int) post('id', 0);
    $c = find($table, $cid);
    if ($c) {
        // Build the recipient list ONCE. em_prepare() deletes every existing
        // recipient row before re-inserting, so calling it on a campaign that
        // already has recipients would reset delivered/failed state and re-queue
        // people who were already emailed. Only prepare a genuinely empty one.
        $queued = (int) (db_row(
            'SELECT COUNT(*) AS n FROM email_campaign_recipients WHERE campaign_id = :c',
            [':c' => $cid]
        )['n'] ?? 0);
        if ($queued === 0 && in_array($c['status'], ['draft', 'scheduled'], true)) {
            em_prepare($cid);
        }
        $res = em_send_batch($cid, 50);
        set_flash('success', "Sent {$res['sent']}, failed {$res['failed']}. Remaining: {$res['remaining']}.");
    }
    redirect('/admin/email-campaigns?action=report&id=' . $cid);
}
if (is_post() && post('_do') === 'delete') {
    require_csrf();
    $cid = (int) post('id', 0);
    db_delete('email_campaign_recipients', 'campaign_id = :c', [':c' => $cid]);
    db_delete($table, 'id = :id', [':id' => $cid]);
    set_flash('success', 'Campaign deleted.');
    redirect('/admin/email-campaigns');
}

/* ---- Report ---- */
if ($action === 'report') {
    $c = find($table, $id);
    if (!$c) { set_flash('error', 'Not found.'); redirect('/admin/email-campaigns'); }
    $recent = db_all("SELECT * FROM email_campaign_recipients WHERE campaign_id = :c ORDER BY id DESC LIMIT 25", [':c' => $id]);
    $openRate  = $c['sent_count'] > 0 ? round($c['open_count'] / $c['sent_count'] * 100, 1) : 0;
    $clickRate = $c['sent_count'] > 0 ? round($c['click_count'] / $c['sent_count'] * 100, 1) : 0;
    // A/B open rate per variant
    $ab = [];
    if (!empty($c['ab_enabled'])) {
        foreach (['A', 'B'] as $vv) {
            $t = (int) db_value("SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id=:c AND variant=:v AND status<>'queued'", [':c' => $id, ':v' => $vv]);
            $o = (int) db_value("SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id=:c AND variant=:v AND status IN('opened','clicked')", [':c' => $id, ':v' => $vv]);
            $ab[$vv] = ['sent' => $t, 'open' => $o, 'rate' => $t ? round($o / $t * 100, 1) : 0];
        }
    }
    $remaining = (int) db_value("SELECT COUNT(*) FROM email_campaign_recipients WHERE campaign_id=:c AND status='queued'", [':c' => $id]);
    $page_title = 'Campaign Report';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($c['name']) ?></h1><span class="muted">Email Marketing / Report · <span class="pill pill-blue"><?= e($c['status']) ?></span></span></div>
        <div class="flex gap-2">
            <a class="btn btn-secondary" href="<?= e(admin_url('email-campaigns?action=edit&id=' . $id)) ?>"><?= lucide('pencil') ?> Edit</a>
            <?php if ($c['status'] !== 'sent' || $remaining > 0): ?>
            <form method="post" action="<?= e(admin_url('email-campaigns')) ?>" data-confirm="Send <?= $remaining > 0 ? 'the next batch (up to 50)' : 'this campaign' ?>?" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="_do" value="send"><input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-primary" type="submit"><?= lucide('send') ?> <?= $remaining > 0 ? 'Send next 50 (' . $remaining . ' left)' : 'Send now' ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= number_format($c['total']) ?></div><div class="stat-label">Recipients</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-green"><?= lucide('send') ?></div><div><div class="stat-value"><?= number_format($c['sent_count']) ?></div><div class="stat-label">Sent</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-violet"><?= lucide('mail-open') ?></div><div><div class="stat-value"><?= $openRate ?>%</div><div class="stat-label"><?= number_format($c['open_count']) ?> Opened</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-amber"><?= lucide('mouse-pointer-click') ?></div><div><div class="stat-value"><?= $clickRate ?>%</div><div class="stat-label"><?= number_format($c['click_count']) ?> Clicked</div></div></div>
        <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('triangle-alert') ?></div><div><div class="stat-value"><?= number_format($c['fail_count']) ?></div><div class="stat-label">Failed</div></div></div>
    </div>
    <?php if ($ab): ?>
    <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('split') ?> A/B subject test</h3></div><div class="panel-body">
        <div class="grid grid-2">
            <div><strong>A:</strong> <?= e($c['subject']) ?><br><small class="text-muted"><?= $ab['A']['open'] ?>/<?= $ab['A']['sent'] ?> opened — <strong><?= $ab['A']['rate'] ?>%</strong></small></div>
            <div><strong>B:</strong> <?= e($c['subject_b']) ?><br><small class="text-muted"><?= $ab['B']['open'] ?>/<?= $ab['B']['sent'] ?> opened — <strong><?= $ab['B']['rate'] ?>%</strong></small></div>
        </div>
        <?php if ($ab['A']['sent'] && $ab['B']['sent']): ?><p class="mt-2 mb-0"><span class="pill pill-green">Winner: <?= $ab['A']['rate'] >= $ab['B']['rate'] ? 'A' : 'B' ?></span></p><?php endif; ?>
    </div></div>
    <?php endif; ?>
    <div class="panel"><div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Recent recipients</h3></div><div class="panel-body">
        <?php if ($recent): ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>Email</th><th>Variant</th><th>Status</th><th>Sent</th></tr></thead><tbody>
        <?php foreach ($recent as $r): ?><tr><td><?= e($r['email']) ?></td><td><?= e($r['variant']) ?></td>
            <td><span class="pill <?= in_array($r['status'], ['opened', 'clicked', 'sent'], true) ? 'pill-green' : ($r['status'] === 'failed' ? 'pill-red' : 'pill-gray') ?>"><?= e($r['status']) ?></span></td>
            <td><small class="text-muted"><?= $r['sent_at'] ? e(time_ago($r['sent_at'])) : '—' ?></small></td></tr><?php endforeach; ?>
        </tbody></table></div><?php else: ?><div class="empty-state"><div class="icon"><?= lucide('mail') ?></div>Not sent yet. Click “Send now” to prepare the list and start sending.</div><?php endif; ?>
    </div></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- Compose (create/edit) ---- */
if ($action === 'create' || $action === 'edit') {
    $row = $action === 'edit' ? find($table, $id) : [];
    if ($action === 'edit' && !$row) { set_flash('error', 'Not found.'); redirect('/admin/email-campaigns'); }
    $templates = db_all("SELECT id, name FROM email_templates WHERE status = 1 ORDER BY name");
    $profiles  = db_all("SELECT id, name FROM email_smtp_profiles WHERE status = 1 ORDER BY is_default DESC, name");
    $tags = db_all("SELECT DISTINCT TRIM(tags) t FROM newsletter_subscribers WHERE tags <> '' LIMIT 50");
    $schedVal = !empty($row['scheduled_at']) ? date('Y-m-d\TH:i', strtotime($row['scheduled_at'])) : '';
    $page_title = $action === 'edit' ? 'Edit Campaign' : 'New Campaign';
    include __DIR__ . '/partials/head.php';
    ?>
    <div class="admin-page-head"><div><h1><?= e($page_title) ?></h1><span class="muted">Email Marketing / Campaigns</span></div>
        <a class="btn btn-secondary" href="<?= e(admin_url('email-campaigns')) ?>">← Back</a></div>
    <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url('email-campaigns')) ?>">
        <?= csrf_field() ?><input type="hidden" name="_do" value="save"><input type="hidden" name="id" value="<?= (int) ($row['id'] ?? 0) ?>">
        <div class="grid-2">
            <div class="form-group"><label class="form-label">Campaign name <span class="req">*</span></label><input class="form-control" name="name" required value="<?= e($row['name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Template (optional)</label><select class="form-select" name="template_id"><option value="">— None / custom body —</option>
                <?php foreach ($templates as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($row['template_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
            </select></div>
        </div>
        <div class="form-group"><label class="form-label">Subject <span class="req">*</span></label><input class="form-control" name="subject" required value="<?= e($row['subject'] ?? '') ?>" placeholder="Use {{name}} for personalisation"></div>
        <label class="checkbox"><input type="checkbox" name="ab_enabled" value="1" <?= !empty($row['ab_enabled']) ? 'checked' : '' ?>> Enable A/B subject test</label>
        <div class="form-group"><label class="form-label">Subject B (A/B variant)</label><input class="form-control" name="subject_b" value="<?= e($row['subject_b'] ?? '') ?>"></div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">From name</label><input class="form-control" name="from_name" value="<?= e($row['from_name'] ?? get_setting('mail_from_name', MAIL_FROM_NAME)) ?>"></div>
            <div class="form-group"><label class="form-label">From email</label><input class="form-control" type="email" name="from_email" value="<?= e($row['from_email'] ?? get_setting('mail_from_email', MAIL_FROM_EMAIL)) ?>"></div>
        </div>
        <div class="grid-2">
            <div class="form-group"><label class="form-label">SMTP profile</label><select class="form-select" name="smtp_profile_id"><option value="">Site default mailer</option>
                <?php foreach ($profiles as $pr): ?><option value="<?= (int) $pr['id'] ?>" <?= (int) ($row['smtp_profile_id'] ?? 0) === (int) $pr['id'] ? 'selected' : '' ?>><?= e($pr['name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="form-group"><label class="form-label">Schedule (optional)</label><input class="form-control" type="datetime-local" name="scheduled_at" value="<?= e($schedVal) ?>"><span class="form-hint">Leave blank to send manually.</span></div>
        </div>
        <fieldset style="border:1px solid var(--border);border-radius:10px;padding:.8rem 1rem;margin:.4rem 0;">
            <legend style="font-weight:700;font-size:.85rem;">Segment</legend>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Audience</label><select class="form-select" name="segment_status">
                    <option value="subscribed" <?= ($row['segment_status'] ?? 'subscribed') === 'subscribed' ? 'selected' : '' ?>>Subscribed only</option>
                    <option value="all" <?= ($row['segment_status'] ?? '') === 'all' ? 'selected' : '' ?>>Everyone in list</option>
                </select></div>
                <div class="form-group"><label class="form-label">Tag filter (optional)</label><input class="form-control" name="segment_tag" list="tagOpts" value="<?= e($row['segment_tag'] ?? '') ?>" placeholder="e.g. donor">
                    <datalist id="tagOpts"><?php foreach ($tags as $tg): foreach (array_filter(array_map('trim', explode(',', (string) $tg['t']))) as $one): ?><option value="<?= e($one) ?>"><?php endforeach; endforeach; ?></datalist>
                </div>
            </div>
        </fieldset>
        <div class="form-group"><label class="form-label">Email body (HTML)</label>
            <textarea class="form-textarea" name="body" data-wysiwyg style="min-height:280px;"><?= e($row['body'] ?? '') ?></textarea>
            <span class="form-hint">Merge tags: <code>{{name}}</code>, <code>{{email}}</code>, <code>{{site_name}}</code>. An unsubscribe footer + tracking are added automatically.</span>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save campaign</button><a class="btn btn-ghost" href="<?= e(admin_url('email-campaigns')) ?>">Cancel</a></div>
    </form></div>
    <?php include __DIR__ . '/partials/foot.php'; exit;
}

/* ---- List ---- */
$page_title = 'Email Campaigns';
$p = paginate("SELECT * FROM $table ORDER BY id DESC", [], 15);
$pill = ['draft' => 'pill-gray', 'scheduled' => 'pill-amber', 'sending' => 'pill-blue', 'sent' => 'pill-green', 'paused' => 'pill-gray'];
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head"><div><h1>Email Campaigns</h1><span class="muted"><?= (int) $p['total'] ?> total</span></div>
    <a class="btn btn-primary" href="<?= e(admin_url('email-campaigns?action=create')) ?>">+ New Campaign</a></div>
<div class="panel"><div class="panel-body">
<?php if ($p['items']): ?>
    <div class="table-wrap"><table class="admin-table"><thead><tr><th>Campaign</th><th>Status</th><th>Recipients</th><th>Opened</th><th>Clicked</th><th style="text-align:right;">Actions</th></tr></thead><tbody>
    <?php foreach ($p['items'] as $r): $orate = $r['sent_count'] > 0 ? round($r['open_count'] / $r['sent_count'] * 100) : 0; ?>
        <tr>
            <td><strong><a href="<?= e(admin_url('email-campaigns?action=report&id=' . $r['id'])) ?>"><?= e($r['name']) ?></a></strong><br><small class="text-muted"><?= e(excerpt($r['subject'], 10)) ?></small></td>
            <td><span class="pill <?= $pill[$r['status']] ?? 'pill-gray' ?>"><?= e($r['status']) ?></span></td>
            <td><?= number_format($r['total']) ?></td>
            <td><?= number_format($r['open_count']) ?> <small class="text-muted">(<?= $orate ?>%)</small></td>
            <td><?= number_format($r['click_count']) ?></td>
            <td><div class="actions">
                <a class="icon-btn" href="<?= e(admin_url('email-campaigns?action=report&id=' . $r['id'])) ?>" title="Report"><?= lucide('bar-chart-3') ?></a>
                <a class="icon-btn" href="<?= e(admin_url('email-campaigns?action=edit&id=' . $r['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                <form method="post" action="<?= e(admin_url('email-campaigns')) ?>" data-confirm="Delete this campaign and its send log?" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="_do" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button></form>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?= $p['links'] ?>
<?php else: ?><div class="empty-state"><div class="icon"><?= lucide('send') ?></div>No campaigns yet. <a href="<?= e(admin_url('email-campaigns?action=create')) ?>">Create your first campaign</a>.</div><?php endif; ?>
</div></div>
<?php include __DIR__ . '/partials/foot.php'; ?>
