<?php
/**
 * =============================================================================
 *  Admin — Email Settings hub.
 *  Tabs: accounts | signatures | sending | deliverability.
 *  - Accounts: full mailbox accounts (Identity + SMTP + IMAP + POP3) with
 *    encrypted-at-rest passwords (ec_secret_write / preserve-when-blank),
 *    default toggle, live "Test SMTP" tool and honest incoming-mail banner.
 *  - Signatures: WYSIWYG signature CRUD + default.
 *  - Sending: settings group "email" (from/reply/queue batch/rate/enabled).
 *  - Deliverability: SPF / DKIM records + copy-ready DNS helper.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/email_center.php';

$SLUG      = 'email-settings';
$validTabs = ['accounts', 'signatures', 'sending', 'deliverability'];
$tab       = in_array(get('tab'), $validTabs, true) ? get('tab') : 'accounts';

/* trim without stripping the special characters a password/API key may hold. */
$raw = static fn(string $k): string => trim((string) post($k, ''));
$secures = ['none' => 'None', 'tls' => 'STARTTLS', 'ssl' => 'SSL/TLS'];

/* -------------------------------------------------------------------------
 |  POST HANDLERS
 * ---------------------------------------------------------------------- */
if (is_post()) {
    $do = post('_do');

    /* ---- Save account (create/update) --------------------------------- */
    if ($do === 'save_account') {
        require_csrf();
        $editId = (int) post('id', 0);
        $errors = validate($_POST, [
            'name'  => 'required|max:128',
            'email' => 'required|email',
        ]);
        if ($errors) {
            set_flash('error', reset($errors));
            redirect('/admin/' . $SLUG . '?tab=accounts&edit=' . ($editId ?: 'new'));
        }

        $secpick = static function (string $field, string $def) use ($raw, $secures): string {
            $v = $raw($field);
            return array_key_exists($v, $secures) ? $v : $def;
        };

        $data = [
            'name'         => clean(post('name')),
            'email'        => strtolower(clean(post('email'))),
            'reply_to'     => strtolower(clean(post('reply_to', ''))),
            'signature_id' => ((int) post('signature_id', 0)) ?: null,
            'smtp_host'    => clean(post('smtp_host', '')),
            'smtp_port'    => (int) post('smtp_port', 587) ?: 587,
            'smtp_secure'  => $secpick('smtp_secure', 'tls'),
            'smtp_user'    => clean(post('smtp_user', '')),
            'imap_host'    => clean(post('imap_host', '')),
            'imap_port'    => (int) post('imap_port', 993) ?: 993,
            'imap_secure'  => $secpick('imap_secure', 'ssl'),
            'imap_user'    => clean(post('imap_user', '')),
            'pop3_host'    => clean(post('pop3_host', '')),
            'pop3_port'    => (int) post('pop3_port', 995) ?: 995,
            'pop3_secure'  => $secpick('pop3_secure', 'ssl'),
            'pop3_user'    => clean(post('pop3_user', '')),
            'dkim_selector'=> clean(post('dkim_selector', '')),
            'is_default'   => post('is_default') ? 1 : 0,
            'status'       => post('status') ? 1 : 0,
        ];

        /* Preserve-when-blank: only overwrite a password when a new one is typed. */
        foreach (['smtp_pass', 'imap_pass', 'pop3_pass'] as $pf) {
            $plain = $raw($pf);
            if ($plain !== '') { $data[$pf] = ec_secret_write($plain); }
        }

        if ($data['is_default']) { db_query("UPDATE email_accounts SET is_default = 0"); }

        if ($editId && find('email_accounts', $editId)) {
            db_update('email_accounts', $data, 'id = :id', [':id' => $editId]);
            set_flash('success', 'Account “' . $data['name'] . '” updated.');
        } else {
            $editId = db_insert('email_accounts', $data);
            set_flash('success', 'Account “' . $data['name'] . '” added.');
        }
        // Guarantee at least one default exists.
        if (!db_value("SELECT COUNT(*) FROM email_accounts WHERE is_default = 1")) {
            db_query("UPDATE email_accounts SET is_default = 1 WHERE id = :id", [':id' => $editId]);
        }
        log_activity('update', $SLUG, 'Saved email account #' . $editId);
        redirect('/admin/' . $SLUG . '?tab=accounts');
    }

    /* ---- Delete account ---------------------------------------------- */
    if ($do === 'delete_account') {
        require_csrf();
        $id  = (int) post('id', 0);
        $acc = find('email_accounts', $id);
        if ($acc) {
            db_delete('email_accounts', 'id = :id', [':id' => $id]);
            // Promote a new default if we removed the default one.
            if (!empty($acc['is_default']) && !db_value("SELECT COUNT(*) FROM email_accounts WHERE is_default = 1")) {
                $next = db_value("SELECT id FROM email_accounts ORDER BY id ASC LIMIT 1");
                if ($next) { db_query("UPDATE email_accounts SET is_default = 1 WHERE id = :id", [':id' => (int) $next]); }
            }
            set_flash('success', 'Account deleted.');
            log_activity('delete', $SLUG, 'Deleted email account #' . $id);
        }
        redirect('/admin/' . $SLUG . '?tab=accounts');
    }

    /* ---- Test SMTP ---------------------------------------------------- */
    if ($do === 'test_smtp') {
        require_csrf();
        $acc = find('email_accounts', (int) post('account_id', 0));
        $to  = strtolower(clean(post('test_to', '')));
        if (!$acc) {
            set_flash('error', 'Pick an account to test.');
        } elseif (empty($acc['smtp_host'])) {
            set_flash('error', 'That account has no SMTP host configured — add one first.');
        } elseif (!is_email($to)) {
            set_flash('error', 'Enter a valid destination email to receive the test.');
        } else {
            $arr = ec_account_smtp($acc);
            $ok  = em_smtp_send(
                $arr,
                $to,
                'PWF SMTP Test',
                mail_layout('<p>If you can read this, SMTP works.</p>', 'Test'),
                []
            );
            if ($ok) {
                set_flash('success', 'Test message sent from “' . $acc['name'] . '” to ' . $to . '. Check that inbox.');
            } else {
                set_flash('error', 'SMTP test failed for “' . $acc['name'] . '”. Verify host ' . e($acc['smtp_host']) . ':' . (int) $acc['smtp_port'] . ', encryption and credentials.');
            }
            log_activity('test', $SLUG, 'SMTP test on account #' . $acc['id'] . ' → ' . ($ok ? 'ok' : 'fail'));
        }
        redirect('/admin/' . $SLUG . '?tab=accounts');
    }

    /* ---- Save signature ---------------------------------------------- */
    if ($do === 'save_sig') {
        require_csrf();
        $editId = (int) post('id', 0);
        $name   = clean(post('name', ''));
        if ($name === '') {
            set_flash('error', 'Signature name is required.');
            redirect('/admin/' . $SLUG . '?tab=signatures&edit_sig=' . ($editId ?: 'new'));
        }
        $data = [
            'name'       => $name,
            'body_html'  => post('body_html', ''),
            'is_default' => post('is_default') ? 1 : 0,
        ];
        if ($data['is_default']) { db_query("UPDATE email_signatures SET is_default = 0"); }
        if ($editId && find('email_signatures', $editId)) {
            db_update('email_signatures', $data, 'id = :id', [':id' => $editId]);
            set_flash('success', 'Signature updated.');
        } else {
            $editId = db_insert('email_signatures', $data);
            set_flash('success', 'Signature added.');
        }
        if (!db_value("SELECT COUNT(*) FROM email_signatures WHERE is_default = 1")) {
            db_query("UPDATE email_signatures SET is_default = 1 WHERE id = :id", [':id' => $editId]);
        }
        log_activity('update', $SLUG, 'Saved signature #' . $editId);
        redirect('/admin/' . $SLUG . '?tab=signatures');
    }

    /* ---- Delete signature -------------------------------------------- */
    if ($do === 'delete_sig') {
        require_csrf();
        db_delete('email_signatures', 'id = :id', [':id' => (int) post('id', 0)]);
        set_flash('success', 'Signature deleted.');
        redirect('/admin/' . $SLUG . '?tab=signatures');
    }

    /* ---- Save sending settings --------------------------------------- */
    if ($do === 'save_sending') {
        require_csrf();
        set_setting('email_default_from',  strtolower(clean(post('email_default_from', ''))),  'email', 'email');
        set_setting('email_default_reply', strtolower(clean(post('email_default_reply', ''))), 'email', 'email');
        set_setting('email_queue_batch',   (string) max(1, (int) post('email_queue_batch', 50)),  'email', 'number');
        set_setting('email_rate_per_min',  (string) max(1, (int) post('email_rate_per_min', 60)), 'email', 'number');
        set_setting('email_queue_enabled', post('email_queue_enabled') ? '1' : '0', 'email', 'boolean');
        log_activity('update', $SLUG, 'Saved email sending settings');
        set_flash('success', 'Sending settings saved.');
        redirect('/admin/' . $SLUG . '?tab=sending');
    }

    /* ---- Save deliverability settings -------------------------------- */
    if ($do === 'save_deliver') {
        require_csrf();
        set_setting('email_dkim_selector', clean(post('email_dkim_selector', '')), 'email', 'text');
        set_setting('email_dkim_record',   trim((string) post('email_dkim_record', '')), 'email', 'textarea');
        set_setting('email_spf_record',    trim((string) post('email_spf_record', '')),  'email', 'textarea');
        log_activity('update', $SLUG, 'Saved deliverability settings');
        set_flash('success', 'Deliverability records saved.');
        redirect('/admin/' . $SLUG . '?tab=deliverability');
    }
}

/* -------------------------------------------------------------------------
 |  GET DATA
 * ---------------------------------------------------------------------- */
$accounts   = db_all("SELECT * FROM email_accounts ORDER BY is_default DESC, id ASC");
$signatures = db_all("SELECT * FROM email_signatures ORDER BY is_default DESC, id ASC");
$imapOn     = ec_imap_available();

// Accounts tab: which account (if any) is being edited/added.
$editParam = get('edit');                         // numeric id | 'new' | null
$showAcctForm = ($tab === 'accounts' && $editParam !== null);
$acctId  = (int) $editParam;
$acct    = $acctId ? (find('email_accounts', $acctId) ?: []) : [];

// Signatures tab: which signature is being edited/added.
$editSig    = get('edit_sig');
$showSigForm = ($tab === 'signatures' && $editSig !== null);
$sigId  = (int) $editSig;
$sig    = $sigId ? (find('email_signatures', $sigId) ?: []) : [];

// Domain used in the deliverability helper.
$appHost   = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
$fromAddr  = (string) get_setting('email_default_from', '');
$mailDomain = ($fromAddr !== '' && strpos($fromAddr, '@') !== false)
    ? substr(strrchr($fromAddr, '@'), 1)
    : $appHost;

$encAtRest = function_exists('sec_has_encryption') ? sec_has_encryption() : false;

$page_title = 'Email Settings';
include __DIR__ . '/partials/head.php';

/** Small helper: render a padlock/status pill line under the accounts header. */
?>
<style>
/* Scoped to the Email Settings page (.es-) — theme-aware via CSS vars. */
.es-wrap .tab-nav { margin-bottom: 1.4rem; }
.es-acct-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.es-card { position: relative; display: flex; flex-direction: column; gap: .55rem; padding: 1.1rem 1.15rem; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-sm); transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
.es-card:hover { border-color: color-mix(in srgb, var(--brand-600) 32%, transparent); box-shadow: var(--shadow); transform: translateY(-2px); }
.es-card.is-default { border-color: color-mix(in srgb, var(--brand-600) 45%, transparent); }
.es-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: .6rem; }
.es-card-ico { width: 40px; height: 40px; flex: 0 0 40px; display: grid; place-items: center; border-radius: 11px; background: var(--grad-brand-soft); color: var(--brand-600); }
.es-card-ico svg { width: 20px; height: 20px; }
.es-card h3 { margin: 0; font-size: 1rem; font-weight: 750; line-height: 1.2; }
.es-card .es-email { color: var(--muted); font-size: .84rem; word-break: break-all; }
.es-card-meta { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .15rem; }
.es-card-foot { display: flex; align-items: center; gap: .4rem; margin-top: .35rem; padding-top: .7rem; border-top: 1px solid var(--border-soft); }
.es-card-foot .spacer { flex: 1; }
.es-chip { display: inline-flex; align-items: center; gap: .3rem; font-size: .72rem; font-weight: 650; color: var(--text-soft); background: var(--surface-2); border: 1px solid var(--border-soft); padding: .18rem .5rem; border-radius: 999px; }
.es-chip svg { width: 12px; height: 12px; }
.es-fieldset { border: 1px solid var(--border); border-radius: var(--radius); padding: 1.1rem 1.2rem 1.3rem; margin: 0 0 1.15rem; }
.es-fieldset > legend { font-weight: 750; font-size: .92rem; padding: 0 .5rem; display: inline-flex; align-items: center; gap: .45rem; color: var(--text); }
.es-fieldset > legend svg { width: 16px; height: 16px; color: var(--brand-600); }
.es-grid-3 { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 1.1rem; }
.es-dns { font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace; font-size: .82rem; background: var(--surface-2); border: 1px solid var(--border); border-radius: 10px; padding: .7rem .85rem; color: var(--text); overflow-x: auto; white-space: pre; }
.es-dns-row { display: grid; grid-template-columns: 120px 1fr; gap: .6rem; padding: .55rem 0; border-bottom: 1px dashed var(--border-soft); align-items: start; }
.es-dns-row:last-child { border-bottom: 0; }
.es-dns-row .k { font-weight: 700; font-size: .82rem; color: var(--text-soft); }
@media (max-width: 720px) { .es-grid-3 { grid-template-columns: 1fr; } .es-dns-row { grid-template-columns: 1fr; gap: .2rem; } }
</style>

<div class="es-wrap adm-rise">
    <div class="admin-page-head">
        <div>
            <h1>Email Settings</h1>
            <span class="muted">Accounts, signatures, sending &amp; deliverability for the mail engine</span>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('email-dashboard')) ?>"><?= lucide('gauge') ?> Dashboard</a>
            <a class="btn btn-outline btn-sm" href="<?= e(admin_url('smtp-profiles')) ?>"><?= lucide('server') ?> SMTP Profiles</a>
        </div>
    </div>

    <nav class="tab-nav">
        <?php
        $tabsMeta = [
            'accounts'       => ['mail',        'Accounts'],
            'signatures'     => ['pen-line',    'Signatures'],
            'sending'        => ['send',        'Sending'],
            'deliverability' => ['shield-check','Deliverability'],
        ];
        foreach ($tabsMeta as $key => [$ico, $label]): ?>
            <a class="tab-btn<?= $tab === $key ? ' is-active' : '' ?>"
               href="<?= e(admin_url($SLUG . '?tab=' . $key)) ?>"
               style="display:inline-flex;align-items:center;gap:.45rem;text-decoration:none;">
                <?= lucide($ico) ?> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php /* ================================================================
        TAB: ACCOUNTS
    ================================================================= */ ?>
    <?php if ($tab === 'accounts'): ?>

        <?php if ($imapOn): ?>
            <div class="alert alert-success"><?= lucide('circle-check') ?> <strong>Incoming ready.</strong> The PHP <code>imap</code> extension is enabled — configured accounts can sync their inbox.</div>
        <?php else: ?>
            <div class="alert alert-info"><?= lucide('info') ?> Incoming mail sync requires the PHP <code>imap</code> extension (currently disabled). Outgoing SMTP works now; enable <code>imap</code> in <code>php.ini</code> to activate the Inbox.</div>
        <?php endif; ?>

        <?php if ($showAcctForm): ?>
            <?php // ---------- ADD / EDIT ACCOUNT FORM ---------- ?>
            <div class="panel">
                <div class="panel-head">
                    <h3 class="panel-title"><?= lucide($acct ? 'pencil' : 'plus') ?> <?= $acct ? 'Edit account' : 'Add account' ?></h3>
                    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($SLUG . '?tab=accounts')) ?>"><?= lucide('arrow-left') ?> Back to list</a>
                </div>
                <form class="admin-form panel-body" method="post" action="<?= e(admin_url($SLUG)) ?>" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_do" value="save_account">
                    <input type="hidden" name="id" value="<?= (int) ($acct['id'] ?? 0) ?>">

                    <fieldset class="es-fieldset">
                        <legend><?= lucide('id-card') ?> Identity</legend>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Display name <span class="req">*</span></label>
                                <input class="form-control" name="name" required maxlength="128" value="<?= e($acct['name'] ?? '') ?>" placeholder="PWF Support"></div>
                            <div class="form-group"><label class="form-label">Email address <span class="req">*</span></label>
                                <input class="form-control" type="email" name="email" required value="<?= e($acct['email'] ?? '') ?>" placeholder="support@<?= e($mailDomain) ?>"></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Reply-to</label>
                                <input class="form-control" type="email" name="reply_to" value="<?= e($acct['reply_to'] ?? '') ?>" placeholder="Optional — defaults to the address above"></div>
                            <div class="form-group"><label class="form-label">Default signature</label>
                                <select class="form-select" name="signature_id">
                                    <option value="0">— None —</option>
                                    <?php foreach ($signatures as $s): ?>
                                        <option value="<?= (int) $s['id'] ?>" <?= (int) ($acct['signature_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                        </div>
                        <label class="checkbox"><input type="checkbox" name="is_default" value="1" <?= !empty($acct['is_default']) ? 'checked' : '' ?>> Make this the default sending account</label>
                        <label class="checkbox"><input type="checkbox" name="status" value="1" <?= (!$acct || !empty($acct['status'])) ? 'checked' : '' ?>> Active</label>
                    </fieldset>

                    <fieldset class="es-fieldset">
                        <legend><?= lucide('send') ?> SMTP — outgoing</legend>
                        <div class="es-grid-3">
                            <div class="form-group"><label class="form-label">Host</label>
                                <input class="form-control" name="smtp_host" value="<?= e($acct['smtp_host'] ?? '') ?>" placeholder="smtp.mailprovider.com"></div>
                            <div class="form-group"><label class="form-label">Port</label>
                                <input class="form-control" type="number" name="smtp_port" value="<?= (int) ($acct['smtp_port'] ?? 587) ?>"></div>
                            <div class="form-group"><label class="form-label">Encryption</label>
                                <select class="form-select" name="smtp_secure">
                                    <?php foreach ($secures as $k => $lbl): ?><option value="<?= $k ?>" <?= ($acct['smtp_secure'] ?? 'tls') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Username</label>
                                <input class="form-control" name="smtp_user" value="<?= e($acct['smtp_user'] ?? '') ?>" autocomplete="off"></div>
                            <div class="form-group"><label class="form-label">Password / API key</label>
                                <input class="form-control" type="password" name="smtp_pass" autocomplete="new-password" placeholder="<?= !empty($acct['smtp_pass']) ? '•••• (unchanged)' : 'Enter password' ?>"></div>
                        </div>
                    </fieldset>

                    <fieldset class="es-fieldset">
                        <legend><?= lucide('inbox') ?> IMAP — incoming</legend>
                        <div class="es-grid-3">
                            <div class="form-group"><label class="form-label">Host</label>
                                <input class="form-control" name="imap_host" value="<?= e($acct['imap_host'] ?? '') ?>" placeholder="imap.mailprovider.com"></div>
                            <div class="form-group"><label class="form-label">Port</label>
                                <input class="form-control" type="number" name="imap_port" value="<?= (int) ($acct['imap_port'] ?? 993) ?>"></div>
                            <div class="form-group"><label class="form-label">Encryption</label>
                                <select class="form-select" name="imap_secure">
                                    <?php foreach ($secures as $k => $lbl): ?><option value="<?= $k ?>" <?= ($acct['imap_secure'] ?? 'ssl') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Username</label>
                                <input class="form-control" name="imap_user" value="<?= e($acct['imap_user'] ?? '') ?>" autocomplete="off"></div>
                            <div class="form-group"><label class="form-label">Password</label>
                                <input class="form-control" type="password" name="imap_pass" autocomplete="new-password" placeholder="<?= !empty($acct['imap_pass']) ? '•••• (unchanged)' : 'Enter password' ?>"></div>
                        </div>
                    </fieldset>

                    <fieldset class="es-fieldset">
                        <legend><?= lucide('download') ?> POP3 — incoming (alternative)</legend>
                        <div class="es-grid-3">
                            <div class="form-group"><label class="form-label">Host</label>
                                <input class="form-control" name="pop3_host" value="<?= e($acct['pop3_host'] ?? '') ?>" placeholder="pop.mailprovider.com"></div>
                            <div class="form-group"><label class="form-label">Port</label>
                                <input class="form-control" type="number" name="pop3_port" value="<?= (int) ($acct['pop3_port'] ?? 995) ?>"></div>
                            <div class="form-group"><label class="form-label">Encryption</label>
                                <select class="form-select" name="pop3_secure">
                                    <?php foreach ($secures as $k => $lbl): ?><option value="<?= $k ?>" <?= ($acct['pop3_secure'] ?? 'ssl') === $k ? 'selected' : '' ?>><?= $lbl ?></option><?php endforeach; ?>
                                </select></div>
                        </div>
                        <div class="grid-2">
                            <div class="form-group"><label class="form-label">Username</label>
                                <input class="form-control" name="pop3_user" value="<?= e($acct['pop3_user'] ?? '') ?>" autocomplete="off"></div>
                            <div class="form-group"><label class="form-label">Password</label>
                                <input class="form-control" type="password" name="pop3_pass" autocomplete="new-password" placeholder="<?= !empty($acct['pop3_pass']) ? '•••• (unchanged)' : 'Enter password' ?>"></div>
                        </div>
                    </fieldset>

                    <div class="form-group" style="max-width:340px;">
                        <label class="form-label">DKIM selector <span class="muted" style="font-weight:400;">(optional)</span></label>
                        <input class="form-control" name="dkim_selector" value="<?= e($acct['dkim_selector'] ?? '') ?>" placeholder="e.g. mail, default, s1">
                    </div>

                    <p class="form-hint" style="margin-top:.4rem;">
                        <?= lucide('lock') ?> Passwords are <?= $encAtRest ? 'encrypted at rest with the Security Center key' : 'stored using the Security Center secret store' ?>. Leave a password field blank to keep the current value.
                    </p>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> <?= $acct ? 'Update account' : 'Add account' ?></button>
                        <a class="btn btn-ghost" href="<?= e(admin_url($SLUG . '?tab=accounts')) ?>">Cancel</a>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <?php // ---------- ACCOUNTS LIST + TEST TOOL ---------- ?>
            <div class="dash-section-head">
                <h2><?= lucide('mail') ?> Mailbox accounts</h2>
                <a class="btn btn-primary btn-sm" href="<?= e(admin_url($SLUG . '?tab=accounts&edit=new')) ?>"><?= lucide('plus') ?> Add account</a>
            </div>

            <?php if ($accounts): ?>
                <div class="es-acct-grid">
                    <?php foreach ($accounts as $a): ?>
                        <?php $hasSmtp = !empty($a['smtp_host']); $hasIn = !empty($a['imap_host']) || !empty($a['pop3_host']); ?>
                        <div class="es-card<?= !empty($a['is_default']) ? ' is-default' : '' ?>">
                            <div class="es-card-top">
                                <div style="display:flex;gap:.7rem;align-items:center;min-width:0;">
                                    <span class="es-card-ico"><?= lucide('at-sign') ?></span>
                                    <div style="min-width:0;">
                                        <h3><?= e($a['name']) ?></h3>
                                        <div class="es-email"><?= e($a['email']) ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($a['is_default'])): ?><span class="pill pill-blue" style="flex:0 0 auto;"><?= lucide('star') ?> Default</span><?php endif; ?>
                            </div>

                            <div class="es-card-meta">
                                <span class="pill <?= !empty($a['status']) ? 'pill-green' : 'pill-gray' ?>"><?= !empty($a['status']) ? 'Active' : 'Disabled' ?></span>
                                <span class="es-chip"><?= lucide('send') ?> <?= $hasSmtp ? e($a['smtp_host']) . ':' . (int) $a['smtp_port'] : 'No SMTP' ?></span>
                                <?php if ($hasIn): ?>
                                    <span class="es-chip"><?= lucide('inbox') ?> <?= !empty($a['imap_host']) ? 'IMAP' : 'POP3' ?> <?= $imapOn ? '' : '· idle' ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="es-card-foot">
                                <a class="btn btn-outline btn-sm" href="<?= e(admin_url($SLUG . '?tab=accounts&edit=' . $a['id'])) ?>"><?= lucide('pencil') ?> Edit</a>
                                <span class="spacer"></span>
                                <form method="post" action="<?= e(admin_url($SLUG)) ?>" data-confirm="Delete account “<?= e($a['name']) ?>”?" style="display:inline;">
                                    <?= csrf_field() ?><input type="hidden" name="_do" value="delete_account"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php // ---------- TEST SMTP TOOL ---------- ?>
                <?php $smtpAccounts = array_values(array_filter($accounts, static fn ($a) => !empty($a['smtp_host']))); ?>
                <div class="panel" style="margin-top:1.5rem;">
                    <div class="panel-head"><h3 class="panel-title"><?= lucide('flask-conical') ?> Test SMTP</h3></div>
                    <div class="panel-body">
                        <?php if ($smtpAccounts): ?>
                            <p class="muted" style="margin:0 0 1rem;">Send a one-line test message through an account's SMTP to confirm the credentials work end to end.</p>
                            <form class="admin-form" method="post" action="<?= e(admin_url($SLUG)) ?>">
                                <?= csrf_field() ?><input type="hidden" name="_do" value="test_smtp">
                                <div class="grid-2">
                                    <div class="form-group"><label class="form-label">Account</label>
                                        <select class="form-select" name="account_id" required>
                                            <?php foreach ($smtpAccounts as $a): ?>
                                                <option value="<?= (int) $a['id'] ?>" <?= !empty($a['is_default']) ? 'selected' : '' ?>><?= e($a['name']) ?> &lt;<?= e($a['email']) ?>&gt;</option>
                                            <?php endforeach; ?>
                                        </select></div>
                                    <div class="form-group"><label class="form-label">Send test to</label>
                                        <input class="form-control" type="email" name="test_to" required value="<?= e(current_user()['email'] ?? '') ?>" placeholder="you@example.com"></div>
                                </div>
                                <button class="btn btn-secondary" type="submit"><?= lucide('send-horizontal') ?> Send test</button>
                            </form>
                        <?php else: ?>
                            <div class="empty-state" style="padding:1.5rem;"><div class="icon"><?= lucide('server-off') ?></div>No account has an SMTP host yet. Add SMTP details to an account to enable testing.</div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="empty-state">
                    <div class="icon"><?= lucide('mailbox') ?></div>
                    No email accounts yet. Add one to start sending from a branded address.
                    <div style="margin-top:1rem;"><a class="btn btn-primary" href="<?= e(admin_url($SLUG . '?tab=accounts&edit=new')) ?>"><?= lucide('plus') ?> Add account</a></div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    <?php /* ================================================================
        TAB: SIGNATURES
    ================================================================= */ ?>
    <?php elseif ($tab === 'signatures'): ?>

        <?php if ($showSigForm): ?>
            <div class="panel">
                <div class="panel-head">
                    <h3 class="panel-title"><?= lucide($sig ? 'pencil' : 'plus') ?> <?= $sig ? 'Edit signature' : 'Add signature' ?></h3>
                    <a class="btn btn-ghost btn-sm" href="<?= e(admin_url($SLUG . '?tab=signatures')) ?>"><?= lucide('arrow-left') ?> Back to list</a>
                </div>
                <form class="admin-form panel-body" method="post" action="<?= e(admin_url($SLUG)) ?>">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="save_sig"><input type="hidden" name="id" value="<?= (int) ($sig['id'] ?? 0) ?>">
                    <div class="form-group"><label class="form-label">Name <span class="req">*</span></label>
                        <input class="form-control" name="name" required maxlength="128" value="<?= e($sig['name'] ?? '') ?>" placeholder="Fundraising team"></div>
                    <div class="form-group"><label class="form-label">Signature HTML</label>
                        <textarea class="form-textarea" name="body_html" data-wysiwyg style="min-height:220px;"><?= e($sig['body_html'] ?? '') ?></textarea>
                        <span class="form-hint">Appended below outgoing messages. Keep it light — a name, title and contact line render best across clients.</span></div>
                    <label class="checkbox"><input type="checkbox" name="is_default" value="1" <?= !empty($sig['is_default']) ? 'checked' : '' ?>> Default signature</label>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><?= lucide('save') ?> <?= $sig ? 'Update' : 'Add' ?> signature</button>
                        <a class="btn btn-ghost" href="<?= e(admin_url($SLUG . '?tab=signatures')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="dash-section-head">
                <h2><?= lucide('pen-line') ?> Signatures</h2>
                <a class="btn btn-primary btn-sm" href="<?= e(admin_url($SLUG . '?tab=signatures&edit_sig=new')) ?>"><?= lucide('plus') ?> Add signature</a>
            </div>

            <?php if ($signatures): ?>
                <div class="panel"><div class="panel-body">
                    <div class="table-wrap"><table class="admin-table">
                        <thead><tr><th>Name</th><th>Preview</th><th>Default</th><th style="text-align:right;">Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($signatures as $s): ?>
                            <tr>
                                <td><strong><?= e($s['name']) ?></strong></td>
                                <td><small class="text-muted"><?= e(excerpt(strip_tags($s['body_html'] ?? ''), 16)) ?></small></td>
                                <td><?= !empty($s['is_default']) ? '<span class="pill pill-blue">' . lucide('star') . ' Default</span>' : '<span class="muted">—</span>' ?></td>
                                <td style="text-align:right;"><div class="actions" style="justify-content:flex-end;">
                                    <a class="icon-btn" href="<?= e(admin_url($SLUG . '?tab=signatures&edit_sig=' . $s['id'])) ?>" title="Edit"><?= lucide('pencil') ?></a>
                                    <form method="post" action="<?= e(admin_url($SLUG)) ?>" data-confirm="Delete signature “<?= e($s['name']) ?>”?" style="display:inline;">
                                        <?= csrf_field() ?><input type="hidden" name="_do" value="delete_sig"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button class="icon-btn danger" type="submit" title="Delete"><?= lucide('trash-2') ?></button>
                                    </form>
                                </div></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                </div></div>
            <?php else: ?>
                <div class="empty-state"><div class="icon"><?= lucide('pen-line') ?></div>No signatures yet. Add one to sign off outgoing mail consistently.</div>
            <?php endif; ?>
        <?php endif; ?>

    <?php /* ================================================================
        TAB: SENDING
    ================================================================= */ ?>
    <?php elseif ($tab === 'sending'): ?>

        <div class="dash-section-head">
            <h2><?= lucide('send') ?> Sending &amp; queue</h2>
            <span class="muted">Defaults and throttle for outbound mail</span>
        </div>
        <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url($SLUG)) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save_sending">
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Default from address</label>
                    <input class="form-control" type="email" name="email_default_from" value="<?= e((string) get_setting('email_default_from', '')) ?>" placeholder="no-reply@<?= e($mailDomain) ?>">
                    <span class="form-hint">Used when a campaign or automation doesn't specify its own sender.</span></div>
                <div class="form-group"><label class="form-label">Default reply-to</label>
                    <input class="form-control" type="email" name="email_default_reply" value="<?= e((string) get_setting('email_default_reply', '')) ?>" placeholder="hello@<?= e($mailDomain) ?>"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Queue batch size</label>
                    <input class="form-control" type="number" min="1" name="email_queue_batch" value="<?= (int) get_setting('email_queue_batch', 50) ?>">
                    <span class="form-hint">Messages processed per cron run (<code>admin/cron-email.php</code>).</span></div>
                <div class="form-group"><label class="form-label">Rate limit (per minute)</label>
                    <input class="form-control" type="number" min="1" name="email_rate_per_min" value="<?= (int) get_setting('email_rate_per_min', 60) ?>">
                    <span class="form-hint">Cap on messages/minute to stay within provider limits.</span></div>
            </div>
            <label class="checkbox"><input type="checkbox" name="email_queue_enabled" value="1" <?= (string) get_setting('email_queue_enabled', '1') === '1' ? 'checked' : '' ?>> Enable background send queue</label>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save sending settings</button></div>
        </form></div>

    <?php /* ================================================================
        TAB: DELIVERABILITY
    ================================================================= */ ?>
    <?php elseif ($tab === 'deliverability'): ?>

        <div class="dash-section-head">
            <h2><?= lucide('shield-check') ?> Deliverability</h2>
            <span class="muted">SPF &amp; DKIM authentication for <code><?= e($mailDomain) ?></code></span>
        </div>

        <div class="grid-2" style="align-items:start;">
            <div class="panel"><form class="admin-form panel-body" method="post" action="<?= e(admin_url($SLUG)) ?>">
                <?= csrf_field() ?><input type="hidden" name="_do" value="save_deliver">
                <div class="form-group"><label class="form-label">DKIM selector</label>
                    <input class="form-control" name="email_dkim_selector" value="<?= e((string) get_setting('email_dkim_selector', '')) ?>" placeholder="mail">
                    <span class="form-hint">The label before <code>._domainkey</code> in your DKIM DNS record.</span></div>
                <div class="form-group"><label class="form-label">DKIM record (public key TXT)</label>
                    <textarea class="form-textarea" name="email_dkim_record" rows="4" placeholder="v=DKIM1; k=rsa; p=MIGfMA0GCSq..."><?= e((string) get_setting('email_dkim_record', '')) ?></textarea></div>
                <div class="form-group"><label class="form-label">SPF record</label>
                    <textarea class="form-textarea" name="email_spf_record" rows="3" placeholder="v=spf1 include:_spf.yourprovider.com ~all"><?= e((string) get_setting('email_spf_record', '')) ?></textarea>
                    <span class="form-hint">The TXT record published at your domain root authorising senders.</span></div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save records</button></div>
            </form></div>

            <div class="panel">
                <div class="panel-head"><h3 class="panel-title"><?= lucide('book-open') ?> How authentication works</h3></div>
                <div class="panel-body">
                    <p class="muted" style="margin-top:0;">Publish these TXT records in your DNS so receiving servers trust mail from <code><?= e($mailDomain) ?></code>. Both reduce the chance of landing in spam.</p>

                    <h4 style="margin:.4rem 0 .5rem;font-size:.9rem;"><?= lucide('shield') ?> SPF</h4>
                    <p class="muted" style="margin:0 0 .5rem;font-size:.85rem;">Lists the servers allowed to send for your domain. One SPF TXT record at the root.</p>
                    <div class="es-dns-row"><span class="k">Host</span><div class="es-dns"><?= e($mailDomain) ?></div></div>
                    <div class="es-dns-row"><span class="k">Type / Value</span><div class="es-dns">TXT  <?= e((string) get_setting('email_spf_record', 'v=spf1 include:_spf.yourprovider.com ~all')) ?></div></div>

                    <h4 style="margin:1rem 0 .5rem;font-size:.9rem;"><?= lucide('key-round') ?> DKIM</h4>
                    <p class="muted" style="margin:0 0 .5rem;font-size:.85rem;">Cryptographically signs each message. Publish the public key under the selector below.</p>
                    <?php $selector = (string) get_setting('email_dkim_selector', 'mail'); ?>
                    <div class="es-dns-row"><span class="k">Host</span><div class="es-dns"><?= e(($selector ?: 'mail')) ?>._domainkey.<?= e($mailDomain) ?></div></div>
                    <div class="es-dns-row"><span class="k">Type / Value</span><div class="es-dns">TXT  <?= e((string) get_setting('email_dkim_record', 'v=DKIM1; k=rsa; p=<your-public-key>')) ?></div></div>

                    <h4 style="margin:1rem 0 .5rem;font-size:.9rem;"><?= lucide('at-sign') ?> DMARC <span class="muted" style="font-weight:400;font-size:.78rem;">(recommended)</span></h4>
                    <div class="es-dns-row"><span class="k">Host</span><div class="es-dns">_dmarc.<?= e($mailDomain) ?></div></div>
                    <div class="es-dns-row"><span class="k">Type / Value</span><div class="es-dns">TXT  v=DMARC1; p=none; rua=mailto:dmarc@<?= e($mailDomain) ?></div></div>

                    <div class="alert alert-info" style="margin:1rem 0 0;"><?= lucide('info') ?> DNS changes can take up to 48 hours to propagate. Verify with a tool like MXToolbox after publishing.</div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
