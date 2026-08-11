<?php
/**
 * =============================================================================
 *  Admin — Registration Policy
 * =============================================================================
 *  The two switches that decide how much friction a new self-registered account
 *  meets, plus a read-only view of the role allowlist and the routing gaps.
 *
 *  Both switches are stored with set_setting() in the `registration` group and
 *  read on every registration by includes/member_auth.php:
 *      reg_auto_verify_enabled()   <- registration_auto_verify   (default '0')
 *      reg_auto_approve_enabled()  <- registration_auto_approve  (default '0')
 *
 *  They default to OFF deliberately. An unattended deploy must not silently
 *  ship an endpoint that mints verified, approved accounts; turning them on is
 *  a decision somebody makes on this page, having read what it costs.
 *
 *  NOTE — this is a standalone settings screen (like membership-settings.php and
 *  payment-settings.php) rather than a panel in admin/settings.php, which is
 *  owned elsewhere. It needs one nav entry to be reachable from the sidebar and
 *  to pass rbac_can_slug() once RBAC enforcement is switched on — see the
 *  reported edit to includes/rbac.php.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if (is_post() && post('_do') === 'save') {
    require_csrf();

    $autoVerify  = post('registration_auto_verify')  ? '1' : '0';
    $autoApprove = post('registration_auto_approve') ? '1' : '0';

    /* The site-wide hourly ceiling. Clamped, not trusted: a negative value is 0
       and an absurd one is capped, so a typo cannot remove the control. 0 means
       "no ceiling" and reg_hourly_cap() refuses to honour that while auto-verify
       is on, because there it is the only limit IP rotation cannot walk around. */
    $cap = (int) post('registration_hourly_cap', 60);
    $cap = max(0, min(10000, $cap));

    $wasVerify  = (string) get_setting('registration_auto_verify', '0');
    $wasApprove = (string) get_setting('registration_auto_approve', '0');
    $wasCap     = (string) get_setting('registration_hourly_cap', '60');

    set_setting('registration_auto_verify',  $autoVerify,  'registration', 'boolean');
    set_setting('registration_auto_approve', $autoApprove, 'registration', 'boolean');
    set_setting('registration_hourly_cap',   (string) $cap, 'registration', 'number');

    if ((string) $cap !== $wasCap && function_exists('sec_log')) {
        sec_log('registration_policy_changed',
            'registration_hourly_cap ' . $wasCap . ' -> ' . $cap
            . ($cap === 0 ? ' — the site-wide ceiling is now OFF' : ''),
            $cap === 0 ? 'warning' : 'notice');
    }

    /* Both directions are worth a security log line, not just switching on:
       "when did this become open" and "when was it closed again" are the two
       questions an incident review asks. sec_log() uses the non-spoofable IP. */
    if ($autoVerify !== $wasVerify && function_exists('sec_log')) {
        sec_log('registration_policy_changed',
            'registration_auto_verify ' . $wasVerify . ' -> ' . $autoVerify
            . ($autoVerify === '1'
                ? ' — new accounts are now treated as email-verified WITHOUT the owner confirming the address'
                : ' — new accounts must confirm their address again'),
            $autoVerify === '1' ? 'critical' : 'warning');
    }
    if ($autoApprove !== $wasApprove && function_exists('sec_log')) {
        sec_log('registration_policy_changed',
            'registration_auto_approve ' . $wasApprove . ' -> ' . $autoApprove,
            $autoApprove === '1' ? 'warning' : 'notice');
    }

    log_activity('update', 'settings',
        'Registration policy: auto_verify=' . $autoVerify . ' auto_approve=' . $autoApprove
        . ' hourly_cap=' . $cap);
    set_flash('success', 'Registration policy saved.');
    redirect('/admin/registration-settings');
}

$autoVerify  = reg_auto_verify_enabled();
$autoApprove = reg_auto_approve_enabled();
$hourlyCap   = reg_hourly_cap();
$capSetting  = (int) get_setting('registration_hourly_cap', '60');
$lastHour    = reg_recent_signup_count();
$selfRoles   = auth_self_registerable_roles();
$gaps        = auth_dashboard_gaps();
$trustProxy  = (int) get_setting('trust_proxy', 0) === 1;

/* Who is currently waiting. Both queues are real: 'pending' members cannot sign
   in (auth_status_gate), and unverified ones cannot either. */
$pendingApproval = db_count('members', "status = 'pending' AND email_verified_at IS NOT NULL");
$unverified      = db_count('members', 'email_verified_at IS NULL');

$recent = db_all(
    "SELECT id, name, email, role, status, email_verified_at, created_at
       FROM members ORDER BY id DESC LIMIT 8"
);

$page_title = 'Registration Policy';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>Registration Policy</h1><span class="muted">System / Public sign-up</span></div>
    <div class="flex" style="gap:.5rem;">
        <span class="pill <?= $autoVerify ? 'pill-red' : 'pill-green' ?>">
            Auto-verify <?= $autoVerify ? 'ON' : 'OFF' ?>
        </span>
        <span class="pill <?= $autoApprove ? 'pill-amber' : 'pill-green' ?>">
            Auto-approve <?= $autoApprove ? 'ON' : 'OFF' ?>
        </span>
        <a class="btn btn-outline" href="<?= e(admin_url('members?status=pending')) ?>"><?= lucide('user-check') ?> Approval queue</a>
    </div>
</div>

<?php if ($autoVerify): ?>
<div class="alert alert-error">
    <?= lucide('triangle-alert') ?>
    <span><strong>Email auto-verification is ON.</strong> Anyone can create a working account on
    an email address they do not own — including a real person's. Leave it on only while you are
    watching the <a href="<?= e(admin_url('members')) ?>">member list</a>, and turn it off again
    afterwards.</span>
</div>
<?php endif; ?>

<div class="grid grid-2" style="align-items:start;gap:1.5rem;">

    <!-- ============================================================ POLICY -->
    <div class="panel">
        <div class="panel-head"><h3 class="panel-title"><?= lucide('shield-alert') ?> Sign-up friction</h3></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('registration-settings')) ?>">
            <?= csrf_field() ?><input type="hidden" name="_do" value="save">

            <!-- ---------------------------------------------- AUTO VERIFY -->
            <div style="padding:1rem;border:1.5px solid var(--border);border-radius:12px;margin-bottom:1.1rem;">
                <label class="checkbox" style="align-items:flex-start;">
                    <input type="checkbox" name="registration_auto_verify" value="1" <?= $autoVerify ? 'checked' : '' ?>>
                    <span>
                        <strong>Skip email verification — treat the address as confirmed at sign-up</strong>
                        <span class="pill pill-red" style="margin-left:.4rem;">High risk</span>
                    </span>
                </label>
                <div class="form-hint" style="margin-top:.6rem;line-height:1.6;">
                    <p style="margin:0 0 .5rem;"><strong>What it does.</strong> New accounts are marked
                    email-verified the moment the form is submitted, so the person can sign in
                    immediately without opening the emailed link.</p>

                    <p style="margin:0 0 .5rem;"><strong>What you give up.</strong>
                    The verification link is the <em>only</em> proof that whoever filled the form
                    actually owns the address they typed. Without it:</p>
                    <ul style="margin:0 0 .5rem 1.1rem;padding:0;">
                        <li>Anyone can register using <strong>somebody else's email address</strong> and hold
                            a working account on it. Notifications — and password-reset mail — go to the
                            real owner, but the account already exists.</li>
                        <li>The sign-up form becomes an <strong>account mint</strong>: one script can create
                            as many verified accounts as the rate limit allows.</li>
                        <li>It <strong>reveals which addresses have accounts</strong>. Normally a sign-up on an
                            existing address is answered identically to a new one, so the form cannot be used
                            to test whether somebody is a member. With this on, a new address ends up signed
                            in and an existing one does not — a difference anyone can measure. That is
                            unavoidable: we cannot sign somebody in to an account they have not proved
                            they own.</li>
                    </ul>

                    <p style="margin:0;"><strong>What still protects you</strong> (always on, whatever this
                    setting says): the per-IP and per-address rate limits — tightened to 3 per hour while
                    this is on, and switched to refuse rather than allow if the counter cannot be
                    written; a CSRF token on the form; the real address owner is emailed regardless so
                    they learn an account was created; and every auto-verified registration is written to
                    the <a href="<?= e(admin_url('activity-logs')) ?>">activity log</a> as a warning.</p>
                </div>
            </div>

            <!-- --------------------------------------------- AUTO APPROVE -->
            <div style="padding:1rem;border:1.5px solid var(--border);border-radius:12px;margin-bottom:1.1rem;">
                <label class="checkbox" style="align-items:flex-start;">
                    <input type="checkbox" name="registration_auto_approve" value="1" <?= $autoApprove ? 'checked' : '' ?>>
                    <span>
                        <strong>Approve new accounts automatically</strong>
                        <span class="pill pill-amber" style="margin-left:.4rem;">Medium risk</span>
                    </span>
                </label>
                <div class="form-hint" style="margin-top:.6rem;line-height:1.6;">
                    <p style="margin:0 0 .5rem;"><strong>What it does.</strong> New accounts are created
                    active instead of waiting in the approval queue, and confirming the email address is
                    enough to open the account. Nobody has to click Approve.</p>
                    <p style="margin:0 0 .5rem;"><strong>What OFF means</strong> (and this is the part that
                    was previously not true): every new account waits for a human. It is created
                    <code>pending</code>, and confirming the email address does <em>not</em> release it —
                    somebody has to open the <a href="<?= e(admin_url('members?status=pending')) ?>">approval
                    queue</a> and approve it. Until then the account cannot sign in.</p>
                    <p style="margin:0 0 .5rem;"><strong>On its own it opens nothing:</strong> an active
                    account still cannot sign in until the address is verified. It is the pair — this
                    <em>and</em> auto-verification — that produces "register and you are straight in".</p>
                    <p style="margin:0;"><strong>It never applies to School accounts.</strong> A school
                    account governs other people's data (student rosters, fees, attendance, results), so
                    it always waits for a human. That is fixed in code, not here.</p>
                </div>
            </div>

            <!-- ------------------------------------------------ HOURLY CAP -->
            <div style="padding:1rem;border:1.5px solid var(--border);border-radius:12px;margin-bottom:1.1rem;">
                <label class="form-label" for="registration_hourly_cap">
                    <strong>Maximum new sign-ups per hour, site-wide</strong>
                </label>
                <input class="form-control" style="max-width:160px;" type="number" min="0" max="10000"
                       id="registration_hourly_cap" name="registration_hourly_cap"
                       value="<?= (int) $capSetting ?>">
                <div class="form-hint" style="margin-top:.6rem;line-height:1.6;">
                    <p style="margin:0 0 .5rem;"><strong>Why this is separate from the other limits.</strong>
                    The per-connection and per-address limits count against one IP address, and a script
                    that rotates through a proxy pool gets a fresh budget with every address it borrows —
                    so "3 per hour" becomes "3 per hour × however many addresses it has". This ceiling
                    counts every account created across the whole site, so rotating the source buys
                    nothing.</p>
                    <p style="margin:0 0 .5rem;">Public sign-ups in the last hour:
                    <strong><?= (int) $lastHour ?></strong>
                    of <strong><?= $hourlyCap > 0 ? (int) $hourlyCap : 'unlimited' ?></strong>.
                    Members you add by hand, CSV imports and admission enrolments do
                    <em>not</em> count against it — only the public form does, so a school
                    intake cannot pause sign-ups.
                    <?php if ($hourlyCap > 0 && $lastHour >= $hourlyCap): ?>
                        <span class="pill pill-red">Ceiling reached — sign-ups are paused</span>
                    <?php endif; ?></p>
                    <p style="margin:0;">Set it above your busiest genuine hour — a campaign launch or a
                    school intake, not an average day. <strong>0 turns it off</strong>, which is only
                    honoured while auto-verification is off; with auto-verification on it is the only
                    control an IP-rotating script cannot get around, so 0 is treated as 60.</p>
                </div>
            </div>

            <div class="alert alert-info">
                <?= lucide('info') ?>
                <span>All three settings are read on every registration, so a change takes effect on the
                next sign-up. Nothing already created is altered.</span>
            </div>

            <?php if ($trustProxy): ?>
            <div class="alert alert-error">
                <?= lucide('triangle-alert') ?>
                <span><strong><code>trust_proxy</code> is ON.</strong> The rate limits are keyed on the
                client IP, and with this on that IP is taken from the <code>X-Forwarded-For</code> /
                <code>CF-Connecting-IP</code> header — which the client sends. Unless a reverse proxy you
                control is stripping and rewriting those headers, a single machine can rotate the header
                and every per-IP limit on this page becomes free. Turn it off in
                <a href="<?= e(admin_url('security')) ?>">Security Center</a> if there is no proxy in
                front of this site.</span>
            </div>
            <?php endif; ?>

            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save policy</button></div>
        </form>
    </div>

    <div>
        <!-- ================================================ WHAT HAPPENS NOW -->
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('route') ?> With these settings, a sign-up…</h3></div>
            <div class="panel-body">
                <div class="table-wrap"><table class="admin-table"><tbody>
                    <tr>
                        <th style="width:190px;">Email verification</th>
                        <td><?= $autoVerify
                            ? '<span class="pill pill-red">Skipped</span> marked verified at sign-up'
                            : '<span class="pill pill-green">Required</span> 24-hour link, or a 6-digit code' ?></td>
                    </tr>
                    <tr>
                        <th>Account status</th>
                        <td><?= $autoApprove
                            ? '<span class="pill pill-amber">Active immediately</span>'
                            : '<span class="pill pill-green">Pending</span> — and it stays pending until an admin approves it' ?></td>
                    </tr>
                    <tr>
                        <th>Can sign in at once?</th>
                        <td><?php
                        /* All four cells spelled out, because two of them used to be
                           wrong: with auto-approve OFF this table said the account
                           "waits for approval" while verification silently activated
                           it, and it described 'pending' as lasting only "until
                           verified". Both now match what the code does. */
                        if ($autoVerify && $autoApprove) {
                            echo '<strong>Yes</strong> — logged in on the spot, straight to their dashboard';
                        } elseif ($autoVerify) {
                            echo '<strong>No</strong> — verified, but waiting for an admin to approve it';
                        } elseif ($autoApprove) {
                            echo '<strong>No</strong> — must confirm the email address first, then straight in';
                        } else {
                            echo '<strong>No</strong> — must confirm the email address first, <em>and then</em> wait for an admin to approve it';
                        }
                        ?></td>
                    </tr>
                    <tr>
                        <th>Volume ceiling</th>
                        <td><?= $hourlyCap > 0
                            ? '<span class="pill pill-green">' . (int) $hourlyCap . '/hour</span> site-wide, plus '
                              . ($autoVerify ? '3' : '5') . ' per connection'
                            : '<span class="pill pill-red">None</span> — only the per-connection limit, which IP rotation defeats' ?></td>
                    </tr>
                    <tr>
                        <th>School accounts</th>
                        <td><span class="pill pill-amber">Always reviewed</span> regardless of the above</td>
                    </tr>
                    <tr>
                        <th>Notification email</th>
                        <td><span class="pill pill-green">Always sent</span> to the address that was used</td>
                    </tr>
                </tbody></table></div>
            </div>
        </div>

        <!-- ==================================================== THE QUEUES -->
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('user-check') ?> Waiting right now</h3></div>
            <div class="panel-body">
                <div class="stat-grid" style="margin:0;">
                    <a class="stat-card" href="<?= e(admin_url('members?status=pending')) ?>">
                        <div class="stat-icon bg-amber"><?= lucide('hourglass') ?></div>
                        <div><div class="stat-value"><?= (int) $pendingApproval ?></div>
                             <div class="stat-label">Verified, awaiting approval</div></div>
                    </a>
                    <a class="stat-card" href="<?= e(admin_url('members')) ?>">
                        <div class="stat-icon bg-rose"><?= lucide('mail-question') ?></div>
                        <div><div class="stat-value"><?= (int) $unverified ?></div>
                             <div class="stat-label">Never verified their email</div></div>
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================= THE ROLE ALLOWLIST -->
        <div class="panel">
            <div class="panel-head"><h3 class="panel-title"><?= lucide('list-checks') ?> Who may self-register</h3></div>
            <div class="panel-body">
                <p class="text-muted" style="margin-top:0;">Server-side allowlist. Anything not on it is
                    refused and logged, even if the request asks for it.</p>
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Role</th><th>Self-register</th><th>Approval</th></tr></thead>
                    <tbody>
                    <?php foreach (auth_roles() as $slug => $cfg):
                        $can    = in_array($slug, $selfRoles, true);
                        $human  = auth_role_requires_admin_approval($slug);
                    ?>
                        <tr>
                            <td><strong><?= e($slug) ?></strong></td>
                            <td><span class="pill <?= $can ? 'pill-green' : 'pill-gray' ?>"><?= $can ? 'Allowed' : 'Admin-created only' ?></span></td>
                            <td><?= $human
                                ? '<span class="pill pill-amber">Always a human</span>'
                                : '<span class="pill pill-blue">Follows the policy</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================= ROUTING GAPS (BUILD QUEUE) -->
<div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('construction') ?> Dashboards still to build</h3></div>
    <div class="panel-body">
        <?php if ($gaps): ?>
            <p class="text-muted" style="margin-top:0;">
                A signed-in user is routed by <code>auth_dashboard_for()</code>, which only ever returns a
                route that exists. These roles have no dashboard yet, so they are being served the nearest
                real page instead of a 404. Create the file and the routing picks it up automatically —
                no code change needed here.
            </p>
            <div class="table-wrap"><table class="admin-table">
                <thead><tr><th>Role</th><th>Intended dashboard</th><th>File to create</th><th>Currently served</th></tr></thead>
                <tbody>
                <?php foreach ($gaps as $slug => $g): ?>
                    <tr>
                        <td><strong><?= e($slug) ?></strong></td>
                        <td><code><?= e($g['intended']) ?></code></td>
                        <td><code><?= e($g['file']) ?></code></td>
                        <td><code><?= e($g['serving']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('circle-check') ?></div>
                Every role has a real dashboard. Nothing is being substituted.</div>
        <?php endif; ?>
    </div>
</div>

<!-- ==================================================== RECENT REGISTRATIONS -->
<div class="panel">
    <div class="panel-head"><h3 class="panel-title"><?= lucide('history') ?> Latest sign-ups</h3>
        <a class="btn btn-ghost btn-sm" href="<?= e(admin_url('members')) ?>">All members →</a></div>
    <div class="panel-body">
        <?php if ($recent): ?>
        <div class="table-wrap"><table class="admin-table">
            <thead><tr><th>Member</th><th>Role</th><th>Verified</th><th>Status</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $m): ?>
                <tr>
                    <td><a href="<?= e(admin_url('members?action=view&id=' . (int) $m['id'])) ?>"><strong><?= e($m['name']) ?></strong></a><br>
                        <small class="text-muted"><?= e($m['email']) ?></small></td>
                    <td><span class="pill pill-blue"><?= e($m['role'] ?: 'member') ?></span></td>
                    <td><span class="pill <?= $m['email_verified_at'] ? 'pill-green' : 'pill-gray' ?>">
                        <?= $m['email_verified_at'] ? 'Yes' : 'No' ?></span></td>
                    <td><span class="pill <?= $m['status'] === 'active' ? 'pill-green' : ($m['status'] === 'pending' ? 'pill-amber' : 'pill-red') ?>">
                        <?= e(ucfirst((string) $m['status'])) ?></span></td>
                    <td><small class="text-muted"><?= e(time_ago($m['created_at'])) ?></small></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?>
            <div class="empty-state"><div class="icon"><?= lucide('users') ?></div>No members yet.</div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
