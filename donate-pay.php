<?php
/**
 * =============================================================================
 *  Donate (secure online payment) — Razorpay / Stripe / PayPal / Cashfree
 * =============================================================================
 *  Creates a pending donation then dispatches to the chosen gateway:
 *   - Stripe / PayPal / Cashfree → server-side redirect to hosted checkout
 *   - Razorpay → renders Checkout.js which posts back to /donation-verify
 *   - none (offline) → records a pledge and shows a thank-you
 *  Recurring donations also create a donation_subscriptions record.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

$siteName = get_setting('site_name', SITE_NAME);
$gateways = donation_enabled_gateways();
$campaign = (int) get('campaign', 0) ? find('campaigns', (int) get('campaign', 0)) : null;
$presets  = [500, 1000, 2500, 5000];
$errors   = [];

if (is_post() && post('_do') === 'donate') {
    require_csrf();
    $name   = clean(post('donor_name'));
    $email  = strtolower(clean(post('email')));
    $amount = (float) post('amount', 0);
    $gw     = (string) post('gateway', '');
    $freq   = in_array(post('frequency'), ['monthly', 'quarterly', 'annual'], true) ? post('frequency') : '';
    $campaignId = (int) post('campaign_id', 0) ?: null;
    if ($campaignId && !find('campaigns', $campaignId)) { $campaignId = null; }

    if ($name === '')            { $errors[] = 'Please enter your name.'; }
    if ($amount < 1)             { $errors[] = 'Please enter a valid amount.'; }
    if ($email !== '' && !is_email($email)) { $errors[] = 'Please enter a valid email.'; }
    if ($gw !== '' && !isset($gateways[$gw])) { $errors[] = 'Selected payment method is unavailable.'; }

    if (!$errors) {
        $currency = (string) get_setting('donation_currency', 'INR');

        // Recurring subscription record (first charge processed now).
        $subId = null;
        if ($freq !== '') {
            $subId = db_insert('donation_subscriptions', [
                'campaign_id' => $campaignId,
                'donor_name'  => $name,
                'email'       => $email ?: null,
                'phone'       => clean(post('phone')) ?: null,
                'amount'      => $amount,
                'currency'    => $currency,
                'frequency'   => $freq,
                'gateway'     => $gw ?: null,
                'status'      => 'created',
                'next_charge_at' => subscription_next_date($freq),
            ]);
        }

        $donationId = db_insert('donations', [
            'campaign_id'    => $campaignId,
            'donor_name'     => $name,
            'email'          => $email ?: null,
            'phone'          => clean(post('phone')) ?: null,
            'pan'            => clean(post('pan')) ?: null,
            'address'        => clean(post('address')) ?: null,
            'amount'         => $amount,
            'currency'       => $currency,
            'payment_method' => $gw ?: 'offline',
            'gateway'        => $gw ?: null,
            'message'        => clean(post('message')) ?: null,
            'is_anonymous'   => post('is_anonymous') ? 1 : 0,
            'subscription_id'=> $subId,
            'status'         => 'pending',
        ]);

        // ---- Offline pledge ----
        if ($gw === '') {
            set_flash('success', 'Thank you! Your pledge of ' . money($amount) . ' is recorded — we will email payment details and your 80G receipt.');
            redirect('/donate-pay?done=1');
        }

        donation_load_gateway($gw);
        // Carry the receipt capability token so the return page can prove the
        // visitor owns this donation before disclosing amount/receipt details.
        $ret = abs_url('donation-return?donation=' . $donationId . '&gateway=' . $gw
            . '&t=' . donation_receipt_token($donationId));
        $cancel = abs_url('donate-pay?campaign=' . ($campaignId ?: 0));

        // ---- Stripe: hosted checkout redirect ----
        if ($gw === 'stripe') {
            $res = stripe_create_checkout($amount, $currency, 'Donation — ' . $siteName,
                $ret . '&session={CHECKOUT_SESSION_ID}', $cancel, ['donation_id' => (string) $donationId]);
            if ($res['success']) {
                db_update('donations', ['gateway_order_id' => $res['session_id']], 'id = :id', [':id' => $donationId]);
                redirect($res['url']);
            }
            $errors[] = 'Stripe: ' . $res['message'];
        }
        // ---- PayPal: approve redirect ----
        elseif ($gw === 'paypal') {
            $res = paypal_create_order($amount, $currency, $ret, $cancel, (string) $donationId);
            if ($res['success']) {
                db_update('donations', ['gateway_order_id' => $res['order_id']], 'id = :id', [':id' => $donationId]);
                redirect($res['approve_url']);
            }
            $errors[] = 'PayPal: ' . $res['message'];
        }
        // ---- Cashfree: payment link redirect ----
        elseif ($gw === 'cashfree') {
            require_once __DIR__ . '/includes/payments.php';
            $linkId = 'don_' . $donationId . '_' . substr(bin2hex(random_bytes(3)), 0, 5);
            db_update('donations', ['gateway_order_id' => $linkId], 'id = :id', [':id' => $donationId]);
            $res = cashfree_create_link([
                'linkId' => $linkId, 'amount' => $amount, 'purpose' => 'Donation to ' . $siteName,
                'customerName' => $name, 'customerEmail' => $email, 'customerPhone' => clean(post('phone')),
                'returnUrl' => $ret, 'notifyUrl' => abs_url('api/v1/cashfree-webhook'),
            ]);
            if ($res['success']) { redirect($res['link_url']); }
            $errors[] = 'Cashfree: ' . $res['message'];
        }
        // ---- Razorpay: render Checkout.js ----
        elseif ($gw === 'razorpay') {
            $res = razorpay_create_order($amount, $currency, 'don_' . $donationId);
            if ($res['success']) {
                db_update('donations', ['gateway_order_id' => $res['order_id']], 'id = :id', [':id' => $donationId]);
                render_razorpay_checkout($res, $donationId, $name, $email, clean(post('phone')), $siteName);
                exit;
            }
            $errors[] = 'Razorpay: ' . $res['message'];
        }
        // If a gateway failed, mark the donation failed and fall through to show the error.
        db_update('donations', ['status' => 'failed'], 'id = :id', [':id' => $donationId]);
    }
}

/** Render the Razorpay Checkout.js page (posts result to /donation-verify). */
function render_razorpay_checkout(array $res, int $donationId, string $name, string $email, string $phone, string $siteName): void
{
    $ret = url('donation-verify');
    ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Complete your donation</title><style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#eef2f7;color:#334155;}</style></head>
<body><div>Opening secure payment…</div>
<form id="rzpForm" method="post" action="<?= e($ret) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="donation_id" value="<?= (int) $donationId ?>">
    <input type="hidden" name="razorpay_payment_id" id="rzp_pid">
    <input type="hidden" name="razorpay_order_id" id="rzp_oid">
    <input type="hidden" name="razorpay_signature" id="rzp_sig">
</form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var rzp = new Razorpay({
    key: <?= json_encode($res['key_id']) ?>,
    order_id: <?= json_encode($res['order_id']) ?>,
    amount: <?= (int) $res['amount_paise'] ?>,
    currency: <?= json_encode($res['currency']) ?>,
    name: <?= json_encode($siteName) ?>,
    description: 'Donation',
    prefill: { name: <?= json_encode($name) ?>, email: <?= json_encode($email) ?>, contact: <?= json_encode($phone) ?> },
    theme: { color: '#0B4E3D' },
    handler: function (r) {
        document.getElementById('rzp_pid').value = r.razorpay_payment_id;
        document.getElementById('rzp_oid').value = r.razorpay_order_id;
        document.getElementById('rzp_sig').value = r.razorpay_signature;
        document.getElementById('rzpForm').submit();
    },
    modal: { ondismiss: function () { window.location = <?= json_encode(url('donate-pay')) ?>; } }
});
rzp.open();
</script>
</body></html>
    <?php
}

seo_set(['title' => 'Donate', 'description' => 'Make a secure online donation to ' . $siteName . '.', 'page_key' => 'donate-pay']);
$page_hero = ['title' => 'Make a Donation', 'subtitle' => 'Your contribution is 80G tax-exempt and goes straight to the cause.', 'breadcrumb' => [['label' => 'Donate']]];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:640px;">
        <?php if (get('done')): ?>
            <div class="card-3d reveal" style="text-align:center;padding:2.4rem;">
                <div class="icon-badge" style="margin:0 auto 1rem;background:var(--grad-green,#2F8065);color:#fff;font-size:2rem;"><?= lucide('heart') ?></div>
                <h2 class="mb-1">Thank you!</h2>
                <p class="text-muted">Your generosity means the world. We'll be in touch with your receipt.</p>
                <a class="btn btn-primary mt-2" href="<?= e(url('/')) ?>">Back to home</a>
            </div>
        <?php else: ?>
            <?php if ($errors): ?><div class="alert alert-warning"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <form class="glass-card reveal" method="post" action="<?= e(url('donate-pay')) ?>" style="padding:1.8rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="_do" value="donate">
                <input type="hidden" name="campaign_id" value="<?= (int) (post('campaign_id') ?: ($campaign['id'] ?? 0)) ?>">
                <?php if ($campaign): ?><p class="text-muted">Supporting: <strong><?= e($campaign['title']) ?></strong></p><?php endif; ?>

                <label class="form-label">Amount (<?= e(donation_symbol(get_setting('donation_currency', 'INR'))) ?>)</label>
                <div class="flex flex-wrap gap-2 mb-2">
                    <?php foreach ($presets as $p): ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amt').value=<?= $p ?>"><?= e(money($p)) ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="form-group"><input class="form-control" type="number" id="amt" name="amount" min="1" step="1" required value="<?= e(post('amount', '')) ?>" placeholder="Enter amount"></div>

                <div class="grid grid-2 gap-3">
                    <div class="form-group"><label class="form-label">Name <span class="req">*</span></label><input class="form-control" name="donor_name" required value="<?= e(post('donor_name', '')) ?>"></div>
                    <div class="form-group"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e(post('email', '')) ?>"></div>
                    <div class="form-group"><?= country_field(['name' => 'phone', 'label' => 'Phone']) ?></div>
                    <div class="form-group"><label class="form-label">PAN (for 80G)</label><input class="form-control" name="pan" value="<?= e(post('pan', '')) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e(post('address', '')) ?>"></div>
                <div class="form-group"><label class="form-label">Message (optional)</label><textarea class="form-textarea" name="message" style="min-height:70px;"><?= e(post('message', '')) ?></textarea></div>

                <div class="form-group"><label class="form-label">Frequency</label>
                    <select class="form-select" name="frequency">
                        <option value="">One-time</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annual">Annual</option>
                    </select></div>

                <div class="form-group"><label class="form-label">Payment method</label>
                    <?php if ($gateways): ?>
                        <select class="form-select" name="gateway" required>
                            <?php foreach ($gateways as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="gateway" value="">
                        <div class="alert alert-info" style="margin:.3rem 0;">Online payment isn't configured yet — your donation will be recorded as a pledge and we'll email you payment details.</div>
                    <?php endif; ?>
                </div>

                <label class="checkbox mb-2"><input type="checkbox" name="is_anonymous" value="1"> Keep my donation anonymous in public listings</label>
                <button class="btn btn-3d btn-lg btn-block" type="submit"><?= lucide('heart') ?> <?= $gateways ? 'Donate securely' : 'Pledge donation' ?></button>
                <p class="form-hint text-center mt-2">80G tax-exempt · secure payment</p>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
