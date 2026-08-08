<?php
/**
 * Newsletter unsubscribe + preference centre.
 *   ?token=                        -> one-click unsubscribe (per-subscriber token)
 *   ?email=...&t=...               -> one-click unsubscribe (HMAC capability token)
 *   ?email=...&t=...&prefs=1       -> preference centre (manage subscription)
 *   POST (from the centre, with t) -> unsubscribe / resubscribe
 *  A bare ?email= is NOT enough to change state — anyone could forge it.
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/email_marketing.php';

$token = clean((string) get('token', ''));
$email = strtolower(clean((string) get('email', '')));
$t     = clean((string) get('t', ''));
$prefs = get('prefs') === '1';

/* ---- Preference-centre actions (capability token required) ---- */
if (is_post()) {
    require_csrf();
    $pe = strtolower(clean((string) post('email', '')));
    $pt = (string) post('t', '');
    if ($pe === '' || !hash_equals(newsletter_unsub_token($pe), $pt)) {
        set_flash('error', 'That link is not valid. Please use the unsubscribe link from one of our emails.');
        redirect('/unsubscribe');
    }
    $sub = find_by('newsletter_subscribers', 'email', $pe);
    if ($sub) {
        $status = post('act') === 'resubscribe' ? 'subscribed' : 'unsubscribed';
        db_update('newsletter_subscribers', ['status' => $status], 'id = :id', [':id' => (int) $sub['id']]);
        set_flash('success', $status === 'subscribed' ? 'You are subscribed again — welcome back!' : 'You have been unsubscribed. You can resubscribe anytime.');
    }
    redirect('/unsubscribe?email=' . rawurlencode($pe) . '&t=' . rawurlencode(newsletter_unsub_token($pe)) . '&prefs=1');
}

$sub = null;
if ($token !== '') {
    $sub = find_by('newsletter_subscribers', 'token', $token);
    if ($sub) { $email = strtolower((string) $sub['email']); }
} elseif ($email !== '') {
    $sub = find_by('newsletter_subscribers', 'email', $email);
}

/* Authorised only via the unguessable per-subscriber token or a valid HMAC t. */
$authorised = $sub && (
    ($token !== '' && hash_equals((string) ($sub['token'] ?? ''), $token))
    || ($t !== '' && hash_equals(newsletter_unsub_token($email), $t))
);

/* ---- One-click unsubscribe (not in prefs mode) ---- */
$done   = false;
$denied = $email !== '' && !$authorised; // link missing/invalid token — no state change
if (!$prefs && $authorised) {
    db_update('newsletter_subscribers', ['status' => 'unsubscribed'], 'id = :id', [':id' => (int) $sub['id']]);
    $sub['status'] = 'unsubscribed';
    $done = true;
}

seo_set(['title' => 'Email Preferences', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'Email Preferences', 'breadcrumb' => [['label' => 'Preferences']]];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container container-narrow text-center">
        <?php foreach (get_flashes() as $type => $list): foreach ($list as $m): ?>
            <div class="alert alert-<?= e($type) ?>"><?= e($m) ?></div>
        <?php endforeach; endforeach; ?>

        <?php if ($prefs && $authorised): ?>
            <div class="icon-badge accent" style="margin:0 auto 1rem;"><?= lucide('settings') ?></div>
            <h2>Email Preferences</h2>
            <p class="text-muted">Managing preferences for <strong><?= e($sub['email']) ?></strong>.</p>
            <div class="card" style="max-width:460px;margin:1.5rem auto 0;">
                <div class="card-body">
                    <p class="mb-2">Newsletter status:
                        <span class="badge <?= $sub['status'] === 'subscribed' ? 'badge-success' : 'badge-muted' ?>"><?= e(ucfirst($sub['status'])) ?></span>
                    </p>
                    <form method="post" action="<?= e(url('unsubscribe')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="email" value="<?= e($sub['email']) ?>">
                        <input type="hidden" name="t" value="<?= e(newsletter_unsub_token((string) $sub['email'])) ?>">
                        <?php if ($sub['status'] === 'subscribed'): ?>
                            <button class="btn btn-outline btn-block" name="act" value="unsubscribe" type="submit"><?= lucide('bell-off') ?> Unsubscribe from all emails</button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-block" name="act" value="resubscribe" type="submit"><?= lucide('bell') ?> Resubscribe</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

        <?php elseif ($done): ?>
            <div class="icon-badge" style="margin:0 auto 1rem;"><?= lucide('circle-check') ?></div>
            <h2>You've been unsubscribed</h2>
            <p class="text-muted">You will no longer receive our emails. Changed your mind?</p>
            <form method="post" action="<?= e(url('unsubscribe')) ?>" class="mt-3">
                <?= csrf_field() ?><input type="hidden" name="email" value="<?= e($sub['email'] ?? '') ?>">
                <input type="hidden" name="t" value="<?= e(newsletter_unsub_token((string) ($sub['email'] ?? ''))) ?>">
                <button class="btn btn-primary" name="act" value="resubscribe" type="submit"><?= lucide('bell') ?> Resubscribe</button>
            </form>

        <?php elseif ($denied): ?>
            <div class="icon-badge accent" style="margin:0 auto 1rem;"><?= lucide('shield-check') ?></div>
            <h2>This link isn't valid</h2>
            <p class="text-muted" style="max-width:520px;margin-inline:auto;">
                To protect subscribers, changes to email preferences need the personalised link we include
                in every email. Nothing has been changed. Please open any email from us and use the
                <strong>Unsubscribe</strong> or <strong>Email preferences</strong> link in its footer.
            </p>

        <?php else: ?>
            <div class="icon-badge accent" style="margin:0 auto 1rem;"><?= lucide('mail') ?></div>
            <h2>Manage your subscription</h2>
            <p class="text-muted" style="max-width:520px;margin-inline:auto;">
                Every email we send includes personalised <strong>Unsubscribe</strong> and
                <strong>Email preferences</strong> links in its footer — use those to manage your
                subscription instantly and securely.
            </p>
        <?php endif; ?>

        <a class="btn btn-outline mt-4" href="<?= e(url('/')) ?>"><?= lucide('arrow-left') ?> Back to Home</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
