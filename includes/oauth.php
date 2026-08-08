<?php
/**
 * =============================================================================
 *  Social login — Google + Facebook OAuth 2.0
 * =============================================================================
 *  Self-contained (no Composer): plain cURL against each provider's token and
 *  profile endpoints.
 *
 *  NOTHING here is hardcoded. Every credential is read from the `settings`
 *  table at call time (Admin -> Website Settings -> Social Login), so changing
 *  a client id or secret in the panel takes effect on the very next request
 *  with no code edit and no deploy.
 *
 *  The redirect URI defaults to <APP_URL>/oauth-callback?provider=xxx, derived
 *  at runtime, so the same database works on localhost and in production. An
 *  admin can override it per provider when the site sits behind a proxy whose
 *  public hostname differs from APP_URL.
 *
 *  Security notes:
 *   - A random `state` is stored in the session and compared on return. This is
 *     the CSRF defence for the OAuth handshake; a callback without a matching
 *     state is discarded.
 *   - Secrets are never echoed, never logged, and never sent to the browser.
 *   - Accounts are linked by provider UID first and only then by verified
 *     email, so a provider that returns an unverified address cannot be used
 *     to take over an existing password account.
 * =============================================================================
 */

declare(strict_types=1);

/** Providers this module understands. */
function oauth_providers(): array
{
    return ['google', 'facebook'];
}

/**
 * Resolved config for a provider: credentials + redirect URI + enabled flag.
 */
function oauth_config(string $provider): array
{
    if ($provider === 'google') {
        $cfg = [
            'enabled'  => (string) get_setting('google_login_enabled', '0') === '1',
            'id'       => trim((string) get_setting('google_client_id', '')),
            'secret'   => trim((string) get_setting('google_client_secret', '')),
            'redirect' => trim((string) get_setting('google_redirect_uri', '')),
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
            'scope'    => 'openid email profile',
        ];
    } elseif ($provider === 'facebook') {
        $cfg = [
            'enabled'  => (string) get_setting('facebook_login_enabled', '0') === '1',
            'id'       => trim((string) get_setting('facebook_app_id', '')),
            'secret'   => trim((string) get_setting('facebook_app_secret', '')),
            'redirect' => trim((string) get_setting('facebook_redirect_uri', '')),
            'auth_url' => 'https://www.facebook.com/v19.0/dialog/oauth',
            'token_url' => 'https://graph.facebook.com/v19.0/oauth/access_token',
            'profile_url' => 'https://graph.facebook.com/v19.0/me?fields=id,name,email,picture.type(large)',
            'scope'    => 'email public_profile',
        ];
    } else {
        return ['enabled' => false, 'id' => '', 'secret' => '', 'redirect' => ''];
    }

    // Derive the redirect URI when the admin has not pinned one.
    if ($cfg['redirect'] === '') {
        $cfg['redirect'] = rtrim(APP_URL, '/') . '/oauth-callback?provider=' . $provider;
    }
    return $cfg;
}

/** Is a provider fully configured and switched on? */
function oauth_enabled(string $provider): bool
{
    $c = oauth_config($provider);
    return !empty($c['enabled']) && $c['id'] !== '' && $c['secret'] !== '';
}

/** Any provider available? Used to show/hide the whole social block. */
function oauth_any_enabled(): bool
{
    foreach (oauth_providers() as $p) {
        if (oauth_enabled($p)) {
            return true;
        }
    }
    return false;
}

/**
 * Build the provider's consent-screen URL and remember the CSRF state.
 * $intended is where to send the member once they come back.
 */
function oauth_authorize_url(string $provider, string $intended = ''): string
{
    $c = oauth_config($provider);
    $state = bin2hex(random_bytes(16));
    $_SESSION['_oauth_state']    = $state;
    $_SESSION['_oauth_provider'] = $provider;
    if ($intended !== '') {
        $_SESSION['_member_intended'] = $intended;
    }

    return $c['auth_url'] . '?' . http_build_query([
        'client_id'     => $c['id'],
        'redirect_uri'  => $c['redirect'],
        'response_type' => 'code',
        'scope'         => $c['scope'],
        'state'         => $state,
        'prompt'        => $provider === 'google' ? 'select_account' : null,
    ], '', '&', PHP_QUERY_RFC3986);
}

/** Small cURL helper. Returns a decoded array, or [] on failure. */
function oauth_http(string $url, array $post = [], array $headers = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
    ]);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        error_log('[oauth] transport error: ' . $err);   // never logs credentials
        return [];
    }
    $data = json_decode((string) $body, true);
    return is_array($data) ? $data : [];
}

/**
 * Exchange an authorization code for a normalised profile.
 * Returns ['ok'=>bool, 'error'=>string, 'uid','email','name','avatar','verified'].
 */
function oauth_fetch_profile(string $provider, string $code): array
{
    $fail = static fn(string $m): array => ['ok' => false, 'error' => $m];
    $c = oauth_config($provider);
    if (!oauth_enabled($provider)) {
        return $fail('That sign-in method is not available right now.');
    }

    $token = oauth_http($c['token_url'], [
        'code'          => $code,
        'client_id'     => $c['id'],
        'client_secret' => $c['secret'],
        'redirect_uri'  => $c['redirect'],
        'grant_type'    => 'authorization_code',
    ]);
    $access = $token['access_token'] ?? '';
    if ($access === '') {
        // Providers put the reason in error_description; surface a generic
        // message to the visitor and keep the detail in the log.
        error_log('[oauth] ' . $provider . ' token exchange failed: '
            . (string) ($token['error_description'] ?? $token['error']['message'] ?? 'no access_token'));
        return $fail('We could not complete that sign-in. Please try again.');
    }

    $me = oauth_http($c['profile_url'], [], ['Authorization: Bearer ' . $access]);
    if (!$me) {
        return $fail('We could not read your profile from that provider.');
    }

    if ($provider === 'google') {
        return [
            'ok'       => true,
            'uid'      => (string) ($me['sub'] ?? ''),
            'email'    => strtolower(trim((string) ($me['email'] ?? ''))),
            'name'     => (string) ($me['name'] ?? ''),
            'avatar'   => (string) ($me['picture'] ?? ''),
            'verified' => !empty($me['email_verified']),
        ];
    }
    return [
        'ok'       => true,
        'uid'      => (string) ($me['id'] ?? ''),
        'email'    => strtolower(trim((string) ($me['email'] ?? ''))),
        'name'     => (string) ($me['name'] ?? ''),
        'avatar'   => (string) ($me['picture']['data']['url'] ?? ''),
        // Facebook only returns an email at all when it is confirmed on their side.
        'verified' => !empty($me['email']),
    ];
}

/**
 * Find or create the member behind an OAuth profile, then start their session.
 * Returns ['ok'=>bool, 'error'=>string].
 */
function oauth_login_member(string $provider, array $p): array
{
    $fail = static fn(string $m): array => ['ok' => false, 'error' => $m];
    if (empty($p['uid'])) {
        return $fail('That provider did not identify your account.');
    }

    // 1. Already linked? Straight in.
    $member = db_row(
        'SELECT * FROM members WHERE oauth_provider = :p AND oauth_uid = :u LIMIT 1',
        [':p' => $provider, ':u' => $p['uid']]
    );

    // 2. Otherwise match on a VERIFIED email and link the accounts. Refusing to
    //    match an unverified address is what stops someone registering a
    //    provider account with a victim's address and inheriting their member
    //    record.
    if (!$member && !empty($p['email']) && !empty($p['verified'])) {
        $member = db_row('SELECT * FROM members WHERE email = :e LIMIT 1', [':e' => $p['email']]);
        if ($member) {
            db_update('members', [
                'oauth_provider' => $provider,
                'oauth_uid'      => $p['uid'],
            ], 'id = :id', [':id' => (int) $member['id']]);
        }
    }

    // 3. Still nothing — create a member. There is no password: the account can
    //    only be entered through the provider until the member sets one via the
    //    normal reset-password flow.
    if (!$member) {
        if (empty($p['email'])) {
            return $fail('Your provider did not share an email address, so we could not create an account.');
        }
        $id = db_insert('members', [
            'name'              => $p['name'] !== '' ? $p['name'] : 'Member',
            'email'             => $p['email'],
            'password'          => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT),
            'oauth_provider'    => $provider,
            'oauth_uid'         => $p['uid'],
            'avatar_url'        => $p['avatar'] ?: null,
            'email_verified_at' => !empty($p['verified']) ? date('Y-m-d H:i:s') : null,
            'status'            => 'active',
            'join_date'         => date('Y-m-d'),
        ]);
        $member = find('members', (int) $id);
        if (!$member) {
            return $fail('We could not create your account. Please try again.');
        }
    }

    if (($member['status'] ?? 'active') !== 'active') {
        return $fail('That account is not active. Please contact us for help.');
    }

    // Keep the remote avatar fresh on every sign-in.
    if (!empty($p['avatar']) && ($member['avatar_url'] ?? '') !== $p['avatar']) {
        db_update('members', ['avatar_url' => $p['avatar']], 'id = :id', [':id' => (int) $member['id']]);
        $member['avatar_url'] = $p['avatar'];
    }

    member_set_session($member);
    db_update('members', [
        'last_login_at' => date('Y-m-d H:i:s'),
        'last_login_ip' => client_ip(),
    ], 'id = :id', [':id' => (int) $member['id']]);

    return ['ok' => true, 'error' => ''];
}
