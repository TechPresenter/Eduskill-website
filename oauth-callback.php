<?php
/**
 * =============================================================================
 *  Social login — step 2: the provider sends the visitor back here.
 * =============================================================================
 *  /oauth-callback?provider=google&code=...&state=...
 *
 *  Verifies the CSRF `state`, exchanges the code for a profile, then links or
 *  creates the member and starts their session.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

$provider = strtolower(clean(get('provider', '')));
$code     = (string) get('code', '');
$state    = (string) get('state', '');

// The state is single-use: consume it immediately so a replayed callback fails.
$expectedState    = (string) ($_SESSION['_oauth_state'] ?? '');
$expectedProvider = (string) ($_SESSION['_oauth_provider'] ?? '');
unset($_SESSION['_oauth_state'], $_SESSION['_oauth_provider']);

/** Send the visitor back to the login page with a message. */
$bail = static function (string $msg): void {
    set_flash('error', $msg);
    redirect('/login');
};

if (!in_array($provider, oauth_providers(), true)) {
    $bail('Unknown sign-in provider.');
}

// The visitor pressed "Cancel" on the consent screen.
if ($code === '' && (get('error') !== null || get('error_reason') !== null)) {
    set_flash('info', 'Sign-in was cancelled.');
    redirect('/login');
}

if ($code === '') {
    $bail('That sign-in did not complete. Please try again.');
}

// CSRF: the state must match the one we issued, for this same provider.
if ($expectedState === '' || !hash_equals($expectedState, $state) || $expectedProvider !== $provider) {
    $bail('That sign-in link has expired. Please try again.');
}

$profile = oauth_fetch_profile($provider, $code);
if (empty($profile['ok'])) {
    $bail((string) ($profile['error'] ?? 'Sign-in failed.'));
}

$res = oauth_login_member($provider, $profile);
if (empty($res['ok'])) {
    $bail((string) ($res['error'] ?? 'Sign-in failed.'));
}

log_activity('login', 'auth', 'Member signed in with ' . ucfirst($provider));
set_flash('success', 'Welcome back!');

// Return them wherever they were headed before signing in.
$intended = (string) ($_SESSION['_member_intended'] ?? '');
unset($_SESSION['_member_intended']);
redirect($intended !== '' ? $intended : '/account');
