<?php
/**
 * =============================================================================
 *  Admin — My Profile  (SPECIAL admin module).
 *  Lets the currently logged-in admin edit their own account details and change
 *  their password. Two independent forms in two panels:
 *    1. Profile   — name, email, phone, avatar (upload media, delete old), bio.
 *    2. Password  — verify current password, set a new one (min 8) + confirm.
 *  Standard admin conventions apply: CSRF, validation, prepared statements,
 *  avatar upload with old-file cleanup, activity logging, session refresh.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$table  = 'users';
$userId = (int) current_user_id();

/* -------------------------------------------------------------- SAVE PROFILE */
if (is_post() && post('_do') === 'profile') {
    require_csrf();

    $errors = validate($_POST, [
        'name'  => 'required|max:128',
        'email' => 'required|email|max:191',
    ]);
    if ($errors) {
        set_flash('error', reset($errors));
        redirect('/admin/profile');
    }

    $email = strtolower(clean(post('email')));

    // Email must remain unique across all accounts (excluding my own row).
    if (db_value("SELECT id FROM $table WHERE email = :e AND id <> :id", [':e' => $email, ':id' => $userId]) !== null) {
        set_flash('error', 'That email address is already in use.');
        redirect('/admin/profile');
    }

    $data = [
        'name'  => clean(post('name')),
        'email' => $email,
        'phone' => clean(post('phone', '')),
        'bio'   => clean(post('bio', '')),
    ];

    // Optional avatar upload (replaces + deletes the old one).
    if (!empty($_FILES['avatar']['name'])) {
        $up = upload_image($_FILES['avatar'], 'media');
        if (!$up['success']) {
            set_flash('error', $up['error']);
            redirect('/admin/profile');
        }
        $data['avatar'] = $up['path'];
        $old = find($table, $userId);
        if ($old && !empty($old['avatar'])) delete_upload($old['avatar']);
    }

    db_update($table, $data, 'id = :id', [':id' => $userId]);

    // Keep the live session in sync so the topbar reflects the changes.
    $_SESSION['user']['name']  = $data['name'];
    $_SESSION['user']['email'] = $data['email'];
    if (isset($data['avatar'])) {
        $_SESSION['user']['avatar'] = $data['avatar'];
    }

    log_activity('update', 'profile', 'Updated own profile #' . $userId);
    set_flash('success', 'Profile updated successfully.');
    redirect('/admin/profile');
}

/* -------------------------------------------------------------- CHANGE PASSWORD */
if (is_post() && post('_do') === 'password') {
    require_csrf();

    $current = (string) post('current_password', '');
    $new     = (string) post('new_password', '');
    $confirm = (string) post('confirm_password', '');

    $me = find($table, $userId);
    if (!$me) {
        set_flash('error', 'Your account could not be loaded.');
        redirect('/admin/profile');
    }

    if ($current === '' || !password_verify($current, $me['password'])) {
        set_flash('error', 'Your current password is incorrect.');
        redirect('/admin/profile');
    }
    // Enforce the Security Center password policy (length + complexity).
    if (function_exists('sec_password_validate')) {
        $pv = sec_password_validate($new);
        if (!$pv['ok']) {
            set_flash('error', implode(' ', $pv['errors']));
            redirect('/admin/profile');
        }
    } elseif (mb_strlen($new) < 8) {
        set_flash('error', 'The new password must be at least 8 characters.');
        redirect('/admin/profile');
    }
    if ($new !== $confirm) {
        set_flash('error', 'The new password and its confirmation do not match.');
        redirect('/admin/profile');
    }
    // Prevent reuse of recent passwords when the policy requires it.
    if (function_exists('sec_password_check_history') && !sec_password_check_history($userId, $new)) {
        set_flash('error', 'Please choose a password you have not used recently.');
        redirect('/admin/profile');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    db_update($table, ['password' => $hash], 'id = :id', [':id' => $userId]);
    if (function_exists('sec_password_record')) {
        sec_password_record($userId, $hash); // records history + stamps password_changed_at, clears must_change
    }

    // A password change must terminate every OTHER authenticated context: null
    // the remember-me token (revoking ALL persistent-login cookies, including one
    // an attacker may have planted — the user re-opts-in to "remember me" on next
    // login) and give the current device a fresh session id so a captured
    // pre-change session id cannot be replayed. The live session stays valid, so
    // the user is not logged out of the device they just used.
    if (function_exists('remember_forget')) {
        remember_forget('users', 'pwf_remember', $userId); // nulls remember_token in DB
    }
    session_regenerate_id(true);

    log_activity('update', 'profile', 'Changed own password #' . $userId);
    set_flash('success', 'Password changed successfully. Any "remember me" sessions on other devices have been signed out.');
    redirect('/admin/profile');
}

/* -------------------------------------------------------------- VIEW */
$row = find($table, $userId);
if (!$row) {
    set_flash('error', 'Your account could not be loaded.');
    redirect('/admin');
}

$page_title = 'My Profile';
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div><h1>My Profile</h1><span class="muted">Manage your account details and password</span></div>
    <a class="btn btn-secondary" href="<?= e(url('/')) ?>" target="_blank"><?= lucide('globe') ?> View Site</a>
</div>

<div class="grid grid-2">
    <!-- ---------------------------------------------------------- Profile details -->
    <div class="panel">
        <div class="panel-head"><h2>Profile Details</h2></div>
        <form class="admin-form panel-body" method="post" enctype="multipart/form-data" action="<?= e(admin_url('profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="profile">

            <div class="form-group">
                <label class="form-label">Avatar</label>
                <input class="form-control" type="file" name="avatar" accept="image/*" data-preview="#imgPreview">
                <img id="imgPreview" class="img-preview" src="<?= e(image_url($row['avatar'] ?? null, 'avatar')) ?>" alt="preview">
            </div>

            <div class="form-group">
                <label class="form-label">Name <span class="req">*</span></label>
                <input class="form-control" name="name" required maxlength="128" value="<?= e($row['name'] ?? '') ?>">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Email <span class="req">*</span></label>
                    <input class="form-control" type="email" name="email" required maxlength="191" value="<?= e($row['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input class="form-control" name="phone" maxlength="32" value="<?= e($row['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea class="form-textarea" name="bio" style="min-height:120px;"><?= e($row['bio'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Profile</button>
            </div>
        </form>
    </div>

    <!-- ---------------------------------------------------------- Change password -->
    <div class="panel" id="change-password">
        <div class="panel-head"><h2>Change Password</h2></div>
        <form class="admin-form panel-body" method="post" action="<?= e(admin_url('profile')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="_do" value="password">

            <div class="form-group">
                <label class="form-label">Current Password <span class="req">*</span></label>
                <input class="form-control" type="password" name="current_password" autocomplete="current-password" required>
            </div>

            <div class="form-group">
                <label class="form-label">New Password <span class="req">*</span></label>
                <input class="form-control" type="password" name="new_password" autocomplete="new-password" required minlength="8" placeholder="At least 8 characters">
                <small class="text-muted">Use at least 8 characters.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password <span class="req">*</span></label>
                <input class="form-control" type="password" name="confirm_password" autocomplete="new-password" required minlength="8">
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Change Password</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/partials/foot.php'; ?>
