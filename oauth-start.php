<?php
/**
 * =============================================================================
 *  Social login — step 1: hand the visitor to the provider's consent screen.
 * =============================================================================
 *  /oauth-start?provider=google
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/oauth.php';

$provider = strtolower(clean(get('provider', '')));

if (!in_array($provider, oauth_providers(), true) || !oauth_enabled($provider)) {
    set_flash('error', 'That sign-in method is not available right now.');
    redirect('/login');
}

// Already signed in? Nothing to do.
if (is_member_logged_in()) {
    redirect('/account');
}

// Remember where they were heading so the callback can return them there.
$intended = (string) ($_SESSION['_member_intended'] ?? '');
redirect(oauth_authorize_url($provider, $intended));
