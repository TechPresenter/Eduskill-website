<?php
/**
 * =============================================================================
 *  My Profile — the member self-service page.
 *
 *  Members previously had no way to change any of their own details: account.php
 *  showed a read-only card, and every field could only be edited by an admin.
 *  This page lets a signed-in member maintain their own record — personal
 *  details, contact, address, photo and password — and shows their membership
 *  standing alongside it.
 *
 *  Every write is CSRF-protected and scoped to the signed-in member's own id;
 *  nothing here accepts an id from the request.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_member('/login');

/* The session only carries id/name/email/avatar (member_set_session), so every
   other field has to come from the table. Always re-read after a write so the
   form reflects what was actually stored rather than a stale session copy. */
$mid = (int) (current_member()['id'] ?? 0);

/** Full members row for the signed-in member, or null if it vanished. */
$loadMember = static function () use ($mid): ?array {
    return $mid ? db_row('SELECT * FROM members WHERE id = :id LIMIT 1', [':id' => $mid]) : null;
};

$member = $loadMember();
if (!$member) {                      // account deleted mid-session
    member_logout();
    redirect('/login');
}

$errors  = [];
$success = '';
$tab     = (string) get('tab', 'details');

/* ---------------------------------------------------------------- actions */
if (is_post()) {
    require_csrf();
    $action = (string) post('_action');

    /* ---- 1. Personal + contact + address ---- */
    if ($action === 'details') {
        $name  = trim((string) post('name'));
        $phone = trim((string) post('phone'));
        $dob   = trim((string) post('dob'));

        if ($name === '') {
            $errors[] = 'Please enter your full name.';
        }
        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $errors[] = 'Date of birth must be a valid date.';
        }
        // Phone is validated against the selected country, same as the public forms.
        $iso = strtoupper(trim((string) post('country_iso')));
        if ($phone !== '' && function_exists('country_validate_phone') && $iso !== ''
            && !country_validate_phone($iso, $phone)) {
            $errors[] = 'That phone number does not look valid for the country selected.';
        }

        if (!$errors) {
            $data = [
                'name'         => $name,
                'phone'        => $phone,
                'dob'          => $dob !== '' ? $dob : null,
                'gender'       => in_array(post('gender'), ['male', 'female', 'other'], true) ? post('gender') : null,
                'occupation'   => trim((string) post('occupation')),
                'address'      => trim((string) post('address')),
                'city'         => trim((string) post('city')),
                'state'        => trim((string) post('state')),
                'pincode'      => trim((string) post('pincode')),
                'country_name' => trim((string) post('country_name')),
                'country_iso'  => $iso !== '' ? $iso : null,
                'country_dial' => trim((string) post('country_dial')),
            ];
            db_update('members', $data, 'id = :id', [':id' => $mid]);
            $success = 'Your details have been saved.';
            $member  = $loadMember();
        }
        $tab = 'details';
    }

    /* ---- 2. Profile photo ---- */
    if ($action === 'avatar') {
        if (!empty($_FILES['avatar']['name'])) {
            /* Two bugs lived on these two lines.
               1. upload_file()'s third parameter is `array $opts`, and this
                  passed ALLOWED_IMAGE_EXT — a STRING ('jpg,jpeg,png,...'). In
                  PHP 8 that is a TypeError, so every avatar upload was a fatal:
                  the feature could never have worked once.
               2. The failure branch read $up['message'], but upload_file()
                  returns its reason under 'error'. So even after fixing the
                  crash, the member would have been told "That image could not
                  be uploaded" instead of "The file is too large" — the wrong
                  message is the one that stops them solving it themselves.
               upload_image() is the wrapper built for exactly this: it sets
               allowed + image_only, which also runs getimagesize() on raster
               files and demands a real <svg> root on SVGs, so a script renamed
               .png cannot get through. */
            $up = upload_image($_FILES['avatar'], 'members');
            if (!empty($up['success'])) {
                db_update('members', ['avatar' => $up['path']], 'id = :id', [':id' => $mid]);
                $success = 'Profile photo updated.';
                $member  = $loadMember();
            } else {
                $errors[] = $up['error'] ?? 'That image could not be uploaded.';
            }
        } else {
            $errors[] = 'Please choose an image first.';
        }
        $tab = 'details';
    }

    /* ---- 3. Password ---- */
    if ($action === 'password') {
        $cur = (string) post('current_password');
        $new = (string) post('new_password');
        $rep = (string) post('confirm_password');

        // OAuth-only accounts have no password to verify against.
        $hasPassword = !empty($member['password']);

        if ($hasPassword && !password_verify($cur, (string) $member['password'])) {
            $errors[] = 'Your current password is not correct.';
        }
        if (strlen($new) < 8) {
            $errors[] = 'The new password must be at least 8 characters.';
        }
        if ($new !== $rep) {
            $errors[] = 'The two new passwords do not match.';
        }
        if (!$errors) {
            db_update('members', ['password' => password_hash($new, PASSWORD_DEFAULT)],
                'id = :id', [':id' => $mid]);
            $success = $hasPassword ? 'Your password has been changed.' : 'A password has been set for your account.';
            $member  = $loadMember();
        }
        $tab = 'security';
    }
}

/* ------------------------------------------------------------------- data */
$plan = null;
if (!empty($member['plan_id'])) {
    try { $plan = find('membership_plans', (int) $member['plan_id']); } catch (Throwable $e) {}
}

$donations = [];
try {
    $donations = db_all(
        "SELECT * FROM donations
         WHERE (member_id = :m OR email = :e) AND status IN ('success','completed','paid')
         ORDER BY created_at DESC LIMIT 25",
        [':m' => $mid, ':e' => (string) $member['email']]
    );
} catch (Throwable $e) {
    // donations may not carry member_id on older installs — fall back to email only
    try {
        $donations = db_all(
            "SELECT * FROM donations WHERE email = :e AND status IN ('success','completed','paid')
             ORDER BY created_at DESC LIMIT 25", [':e' => (string) $member['email']]
        );
    } catch (Throwable $e2) {}
}
$totalGiven = 0.0;
foreach ($donations as $d) { $totalGiven += (float) ($d['amount'] ?? 0); }

seo_set(['title' => 'My Profile', 'page_key' => 'account-profile', 'robots' => 'noindex,nofollow']);
$page_hero = [
    'title'      => 'My Profile',
    'subtitle'   => 'Keep your details up to date, manage your membership and review your giving.',
    'breadcrumb' => [['label' => 'My Account', 'url' => url('account')], ['label' => 'Profile']],
];
include __DIR__ . '/includes/header.php';

$tabs = [
    'details'    => ['Personal details', 'user-round'],
    'membership' => ['Membership',       'id-card'],
    'giving'     => ['My giving',        'heart'],
    'security'   => ['Security',         'shield'],
];
?>
<section class="section">
    <div class="container acct-grid">

        <!-- ------------------------------------------------ sidebar -->
        <aside class="acct-side">
            <div class="acct-card acct-me">
                <img class="acct-avatar" src="<?= e(image_url($member['avatar'] ?? null, 'avatar')) ?>"
                     alt="" width="72" height="72">
                <strong class="acct-name"><?= e($member['name']) ?></strong>
                <span class="acct-mail"><?= e($member['email']) ?></span>
                <?php if (!empty($member['member_code'])): ?>
                    <span class="badge mt-2"><?= e($member['member_code']) ?></span>
                <?php endif; ?>
            </div>
            <nav class="acct-nav" aria-label="Profile sections">
                <?php foreach ($tabs as $key => [$label, $icon]): ?>
                    <a class="acct-tab<?= $tab === $key ? ' is-active' : '' ?>"
                       href="<?= e(url('account-profile') . '?tab=' . $key) ?>"
                       <?= $tab === $key ? 'aria-current="page"' : '' ?>>
                        <?= lucide($icon) ?> <span><?= e($label) ?></span>
                    </a>
                <?php endforeach; ?>
                <a class="acct-tab" href="<?= e(url('member-card')) ?>"><?= lucide('credit-card') ?> <span>My ID card</span></a>
                <a class="acct-tab" href="<?= e(url('account')) ?>"><?= lucide('layout-dashboard') ?> <span>Dashboard</span></a>
            </nav>
        </aside>

        <!-- ------------------------------------------------ panels -->
        <div class="acct-main">
            <?php if ($errors): ?>
                <div class="alert alert-error" role="alert">
                    <ul class="m-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status"><?= e($success) ?></div>
            <?php endif; ?>

            <?php /* ---------- DETAILS ---------- */ if ($tab === 'details'): ?>
            <div class="acct-card">
                <h2 class="acct-h">Personal details</h2>
                <form method="post" class="acct-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="details">
                    <div class="acct-row">
                        <label>Full name <span class="req">*</span>
                            <input class="form-control" name="name" required value="<?= e($member['name']) ?>">
                        </label>
                        <label>Email
                            <input class="form-control" value="<?= e($member['email']) ?>" disabled>
                            <small class="form-hint">Contact us to change the email on your account.</small>
                        </label>
                    </div>
                    <div class="acct-row">
                        <label>Date of birth
                            <input class="form-control" type="date" name="dob" value="<?= e($member['dob'] ?? '') ?>">
                        </label>
                        <label>Gender
                            <select class="form-select" name="gender">
                                <option value="">Prefer not to say</option>
                                <?php foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= ($member['gender'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <?php
                    /* Same country + dial-code control used by every public form, so
                       the stored country_name/iso/dial stay consistent. */
                    if (function_exists('country_field')) {
                        echo country_field([
                            'iso'   => $member['country_iso']  ?? '',
                            'phone' => $member['phone']        ?? '',
                        ]);
                    } else { ?>
                        <div class="acct-row">
                            <label>Phone <input class="form-control" name="phone" value="<?= e($member['phone'] ?? '') ?>"></label>
                        </div>
                    <?php } ?>
                    <div class="acct-row">
                        <label>Occupation
                            <input class="form-control" name="occupation" value="<?= e($member['occupation'] ?? '') ?>">
                        </label>
                        <label>Address
                            <input class="form-control" name="address" value="<?= e($member['address'] ?? '') ?>">
                        </label>
                    </div>
                    <div class="acct-row acct-row-3">
                        <label>City <input class="form-control" name="city" value="<?= e($member['city'] ?? '') ?>"></label>
                        <label>State <input class="form-control" name="state" value="<?= e($member['state'] ?? '') ?>"></label>
                        <label>PIN / ZIP <input class="form-control" name="pincode" value="<?= e($member['pincode'] ?? '') ?>"></label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= lucide('save') ?> Save changes</button>
                </form>
            </div>

            <div class="acct-card">
                <h2 class="acct-h">Profile photo</h2>
                <form method="post" enctype="multipart/form-data" class="acct-photo">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="avatar">
                    <img class="acct-avatar lg" src="<?= e(image_url($member['avatar'] ?? null, 'avatar')) ?>" alt="" width="96" height="96">
                    <div>
                        <input class="form-control" type="file" name="avatar" accept="image/*" aria-label="Choose a profile photo">
                        <small class="form-hint">JPG, PNG or WebP, up to <?= (int) (UPLOAD_MAX_BYTES / 1048576) ?> MB. Used on your ID card.</small>
                        <button class="btn btn-outline btn-sm mt-2" type="submit"><?= lucide('upload') ?> Upload photo</button>
                    </div>
                </form>
            </div>

            <?php /* ---------- MEMBERSHIP ---------- */ elseif ($tab === 'membership'): ?>
            <div class="acct-card">
                <h2 class="acct-h">Membership</h2>
                <?php $ms = (string) ($member['membership_status'] ?? 'none'); ?>
                <dl class="acct-facts">
                    <div><dt>Status</dt><dd><span class="badge <?= $ms === 'active' ? 'badge-accent' : '' ?>"><?= e(ucfirst($ms ?: 'Not enrolled')) ?></span></dd></div>
                    <div><dt>Member ID</dt><dd><?= e($member['member_code'] ?: '—') ?></dd></div>
                    <div><dt>Plan</dt><dd><?= e($plan['name'] ?? '—') ?></dd></div>
                    <div><dt>Member since</dt><dd><?= e(!empty($member['join_date']) ? format_date($member['join_date']) : '—') ?></dd></div>
                    <div><dt>Valid through</dt><dd><?= e(!empty($member['expiry_date']) ? format_date($member['expiry_date']) : 'Lifetime') ?></dd></div>
                    <div><dt>Auto-renew</dt><dd><?= !empty($member['auto_renew']) ? 'On' : 'Off' ?></dd></div>
                </dl>
                <div class="acct-actions">
                    <a class="btn btn-primary" href="<?= e(url('member-card')) ?>"><?= lucide('credit-card') ?> View / download ID card</a>
                    <?php if ($ms === 'active'): ?>
                        <a class="btn btn-outline" href="<?= e(url('membership-renew')) ?>"><?= lucide('refresh-cw') ?> Renew</a>
                    <?php else: ?>
                        <a class="btn btn-outline" href="<?= e(url('membership')) ?>"><?= lucide('badge-check') ?> View plans</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php /* ---------- GIVING ---------- */ elseif ($tab === 'giving'): ?>
            <div class="acct-card">
                <h2 class="acct-h">My giving</h2>
                <?php if ($donations): ?>
                    <p class="acct-total">Total given: <strong><?= e(money($totalGiven)) ?></strong> across <?= count($donations) ?> contribution<?= count($donations) === 1 ? '' : 's' ?>.</p>
                    <div class="table-wrap">
                        <table class="admin-table acct-table">
                            <thead><tr><th>Date</th><th>Purpose</th><th class="ta-r">Amount</th><th>Receipt</th></tr></thead>
                            <tbody>
                            <?php foreach ($donations as $d): ?>
                                <tr>
                                    <td><?= e(format_date($d['created_at'])) ?></td>
                                    <td><?= e($d['purpose'] ?? $d['campaign'] ?? 'General fund') ?></td>
                                    <td class="ta-r"><strong><?= e(money((float) $d['amount'])) ?></strong></td>
                                    <td>
                                        <?php if (!empty($d['receipt_token'])): ?>
                                            <a class="link-underline" href="<?= e(url('donation-receipt') . '?t=' . urlencode((string) $d['receipt_token'])) ?>">Download</a>
                                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="acct-empty">You have not made a donation yet. Every contribution is tracked from donation to delivery, and you will find your 80G receipts here.</p>
                <?php endif; ?>
                <div class="acct-actions">
                    <a class="btn btn-primary" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate now</a>
                    <a class="btn btn-outline" href="<?= e(url('campaigns')) ?>">Browse campaigns <?= lucide('arrow-right') ?></a>
                </div>
            </div>

            <?php /* ---------- SECURITY ---------- */ else: ?>
            <div class="acct-card">
                <h2 class="acct-h"><?= empty($member['password']) ? 'Set a password' : 'Change password' ?></h2>
                <?php if (empty($member['password'])): ?>
                    <p class="form-hint mb-3">You signed in with <?= e(ucfirst((string) ($member['oauth_provider'] ?: 'a social account'))) ?>. Setting a password lets you sign in with your email as well.</p>
                <?php endif; ?>
                <form method="post" class="acct-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="password">
                    <?php if (!empty($member['password'])): ?>
                        <label>Current password <span class="req">*</span>
                            <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                        </label>
                    <?php endif; ?>
                    <div class="acct-row">
                        <label>New password <span class="req">*</span>
                            <input class="form-control" type="password" name="new_password" required minlength="8" autocomplete="new-password">
                        </label>
                        <label>Confirm new password <span class="req">*</span>
                            <input class="form-control" type="password" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= lucide('shield-check') ?> Update password</button>
                </form>
            </div>
            <div class="acct-card">
                <h2 class="acct-h">Account activity</h2>
                <dl class="acct-facts">
                    <div><dt>Email verified</dt><dd><?= !empty($member['email_verified_at']) ? 'Yes' : 'Not yet' ?></dd></div>
                    <div><dt>Last sign-in</dt><dd><?= e(!empty($member['last_login_at']) ? format_datetime($member['last_login_at']) : '—') ?></dd></div>
                    <div><dt>Account created</dt><dd><?= e(!empty($member['created_at']) ? format_date($member['created_at']) : '—') ?></dd></div>
                </dl>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
