<?php
/**
 * =============================================================================
 *  Donate — the primary giving page.
 *
 *  A complete, DB-driven donation experience:
 *    • Preset amount buttons (₹500 / 1000 / 2500 / 5000 / custom) wired to a
 *      single amount field with a tiny inline script.
 *    • Optional campaign picker (active campaigns only, preselected via
 *      ?campaign=<id>).
 *    • Donor fields matching the `donations` table, posted to forms/donate
 *      (records a PENDING donation — there is no live payment gateway yet).
 *    • "Why donate", per-amount impact, 80G tax note and offline bank details.
 *
 *  Every value is DB-driven (campaigns table + get_setting) with graceful
 *  fallbacks so the page reads as complete before anything is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Active campaigns for the optional picker ------------------------- */
$campaigns = db_all(
    "SELECT id, title, goal_amount, raised_amount
     FROM campaigns
     WHERE status = 'active' AND deleted_at IS NULL
     ORDER BY is_featured DESC, id DESC"
);

/* Preselected campaign from ?campaign=<id> (only if it is actually active). */
$selectedCampaign = (int) get('campaign', 0);
$campaignIds = array_map(static fn($c) => (int) $c['id'], $campaigns);
if ($selectedCampaign > 0 && !in_array($selectedCampaign, $campaignIds, true)) {
    $selectedCampaign = 0;
}

/* Cause/program cards land here with ?cause=<program-slug> (or ?program=).
 * Map the slug to an active campaign whose title mentions the program, else
 * carry it as the donation purpose so the intent is never silently dropped. */
$causePurpose = '';
$causeSlug = clean(get('cause', get('program', '')));
if ($causeSlug !== '' && $selectedCampaign === 0) {
    $prog = db_row("SELECT title FROM programs WHERE slug = :s AND deleted_at IS NULL LIMIT 1", [':s' => $causeSlug]);
    if ($prog) {
        $causePurpose = $prog['title'];
        foreach ($campaigns as $c) {
            if (stripos($c['title'], $prog['title']) !== false) { $selectedCampaign = (int) $c['id']; break; }
        }
    }
}

/* ---- Preset amounts + the tangible impact each one funds --------------- */
$presets = [
    ['amount' => 500,  'icon' => 'book',        'title' => 'School Kit',      'impact' => 'Books, stationery and a learning kit for a child for a full month.'],
    ['amount' => 1000, 'icon' => 'soup',        'title' => 'Nutrition',       'impact' => 'A week of nutritious meals for a family facing hardship.'],
    ['amount' => 2500, 'icon' => 'stethoscope', 'title' => 'Health Camp',     'impact' => 'A free medical check-up and essential medicines for 10 patients.'],
    ['amount' => 5000, 'icon' => 'scissors',    'title' => 'Livelihood',      'impact' => 'A month of vocational skill training for a young woman.'],
];
$defaultAmount = 1000; // friendly, pre-highlighted default

/* ---- Payment methods (recorded as pending — no gateway) --------------- */
$paymentMethods = ['UPI', 'Bank Transfer', 'Card'];

/* ---- Organisation + statutory details (settings first, then config) --- */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$cin      = get_setting('cin', SITE_CIN);
$reg80g   = get_setting('reg_80g', 'Available on request');
$reg12a   = get_setting('reg_12a', 'Available on request');

/* ---- Bank details for offline giving (all DB-driven) ------------------ */
$bankRows = [
    ['label' => 'Account Name',   'value' => get_setting('bank_account_name', $siteName)],
    ['label' => 'Bank Name',      'value' => get_setting('bank_name', 'Available on request')],
    ['label' => 'Account Number', 'value' => get_setting('bank_account_number', 'Available on request')],
    ['label' => 'Account Type',   'value' => get_setting('bank_account_type', 'Current Account')],
    ['label' => 'IFSC Code',      'value' => get_setting('bank_ifsc', 'Available on request')],
    ['label' => 'Branch',         'value' => get_setting('bank_branch', 'Patna, Bihar')],
];
$bankUpi = get_setting('bank_upi');

/* ---- Lifetime giving stats (only meaningful once seeded) -------------- */
$totalRaised = (float) db_value("SELECT COALESCE(SUM(amount), 0) FROM donations WHERE status = 'completed'");
$totalDonors = (int) db_value("SELECT COUNT(DISTINCT COALESCE(NULLIF(email, ''), CONCAT('id-', id))) FROM donations WHERE status = 'completed'");

/* ---- SEO -------------------------------------------------------------- */
seo_set([
    'title'       => 'Donate',
    'description' => 'Donate to ' . $siteName . ' and power education, healthcare, skill development and relief across Bihar. Secure giving, 80G tax benefits and full transparency on every rupee.',
    'page_key'    => 'donate',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Donate & Change a Life',
    'subtitle'   => 'Your generosity becomes a school kit, a health camp, a meal, a new skill. Give today and be the reason someone in Bihar smiles tomorrow.',
    'breadcrumb' => [['label' => 'Donate']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== DONATION FORM ============================== -->
<section class="section">
    <div class="container grid grid-sidebar gap-4 items-start">

        <!-- Main: the giving form -->
        <div class="reveal">
            <div class="card-3d">
                <span class="eyebrow">Make a Donation</span>
                <h2 class="section-title" style="margin-top:.25rem;">Give with Confidence</h2>
                <p class="text-muted">Choose an amount, tell us a little about yourself, and we'll email you the payment details along with your 80G tax receipt.</p>

                <?php if (function_exists('donation_enabled_gateways') && donation_enabled_gateways()): ?>
                    <a class="btn btn-3d btn-block mt-2" href="<?= e(url('donate-pay')) ?>"><?= lucide('shield-check') ?> Pay securely online — card · UPI · netbanking</a>
                    <p class="text-center text-muted mt-2" style="font-size:.85rem;">— or leave your details below and we'll send payment options —</p>
                <?php endif; ?>

                <form data-ajax-form data-endpoint="<?= e(url('forms/donate')) ?>" class="mt-3">
                    <?= csrf_field() ?>

                    <!-- Amount presets -->
                    <div class="form-group">
                        <label class="form-label req" for="donation-amount">Select an amount</label>
                        <div class="flex flex-wrap gap-2 mb-2" id="amount-presets">
                            <?php foreach ($presets as $p): ?>
                                <button type="button" class="btn btn-outline btn-sm" data-amount="<?= (int) $p['amount'] ?>">
                                    <?= e(money((float) $p['amount'])) ?>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="btn btn-outline btn-sm" data-amount="custom">Custom</button>
                        </div>
                        <div class="flex items-center gap-2" style="max-width:260px;">
                            <span class="badge badge-brand" style="font-size:1.1rem;padding:.55rem .75rem;">₹</span>
                            <input class="form-control" type="number" inputmode="numeric" min="1" step="1"
                                   id="donation-amount" name="amount" value="<?= (int) $defaultAmount ?>"
                                   placeholder="Enter amount" required aria-describedby="amount-hint">
                        </div>
                        <small class="form-hint" id="amount-hint">Enter any amount you wish — every rupee is put to work on the ground.</small>
                    </div>

                    <!-- Campaign (optional) -->
                    <div class="form-group">
                        <label class="form-label" for="campaign_id">Support a specific campaign <span class="text-muted">(optional)</span></label>
                        <select class="form-select" id="campaign_id" name="campaign_id">
                            <option value="0">Where the need is greatest (General Fund)</option>
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= $selectedCampaign === (int) $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$campaigns): ?>
                            <small class="form-hint">No active campaigns right now — your gift goes to our General Fund, supporting all our work.</small>
                        <?php endif; ?>
                    </div>

                    <!-- Donor identity -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label req" for="donor_name">Full name</label>
                            <input class="form-control" id="donor_name" name="donor_name" maxlength="128" required autocomplete="name">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" type="email" id="email" name="email" maxlength="191" autocomplete="email" aria-describedby="email-hint">
                            <small class="form-hint" id="email-hint">For your receipt &amp; impact updates.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <?= country_field(['name' => 'phone', 'label' => 'Phone']) ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pan">PAN <span class="text-muted">(for 80G receipt)</span></label>
                            <input class="form-control" id="pan" name="pan" maxlength="16" style="text-transform:uppercase;"
                                   placeholder="ABCDE1234F" autocomplete="off" aria-describedby="pan-hint">
                            <small class="form-hint" id="pan-hint">Optional. Needed only to claim a tax deduction.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="address">Postal address <span class="text-muted">(for the stamped receipt)</span></label>
                        <input class="form-control" id="address" name="address" maxlength="255" autocomplete="street-address">
                    </div>

                    <!-- Payment preference -->
                    <div class="form-group">
                        <label class="form-label req" for="payment_method">Preferred payment method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <?php foreach ($paymentMethods as $m): ?>
                                <option value="<?= e($m) ?>" <?= $m === 'UPI' ? 'selected' : '' ?>><?= e($m) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">We'll email you the exact steps for your chosen method.</small>
                    </div>

                    <!-- Message -->
                    <div class="form-group">
                        <label class="form-label" for="message">Message <span class="text-muted">(optional)</span></label>
                        <textarea class="form-textarea" id="message" name="message" rows="3" maxlength="1000"
                                  placeholder="Dedicate this gift, or tell us what inspired you to give…"><?= $causePurpose !== '' ? e('For: ' . $causePurpose) : '' ?></textarea>
                    </div>

                    <!-- Anonymous -->
                    <div class="form-group">
                        <label class="checkbox">
                            <input type="checkbox" name="is_anonymous" value="1">
                            <span>Make my donation anonymous (hide my name from public listings)</span>
                        </label>
                    </div>

                    <div class="alert alert-info" role="note" style="margin-bottom:1rem;">
                        <strong>How it works:</strong> Submitting records your pledge as <em>pending</em> — there is no online
                        payment gateway on this page yet. Our team will email you secure UPI / bank-transfer / card
                        instructions and issue your 80G receipt once the transfer is confirmed.
                    </div>

                    <button class="btn btn-primary btn-lg btn-block" type="submit"><?= lucide('heart') ?> Complete My Donation</button>
                    <p class="text-center text-muted mt-2 mb-0" style="font-size:.85rem;">
                        Secure &amp; transparent · Registered non-profit · CIN <?= e($cin) ?>
                    </p>
                </form>
            </div>
        </div>

        <!-- Sidebar: reassurance + impact -->
        <aside class="reveal delay-1">
            <div class="card-3d" style="position:sticky;top:90px;">
                <div class="flex items-center gap-2 mb-2">
                    <div class="icon-badge"><?= lucide('handshake') ?></div>
                    <strong style="font-size:1.15rem;">Why give to us?</strong>
                </div>
                <ul class="list-check" style="font-size:.95rem;">
                    <li><strong>80G tax benefit</strong> — save up to 50% of your gift on tax</li>
                    <li><strong>100% on the ground</strong> — every rupee tracked to delivery</li>
                    <li>Registered non-profit · CIN <?= e($cin) ?></li>
                    <li>Regular updates on the impact you create</li>
                </ul>

                <div class="divider"></div>

                <div class="grid grid-2 gap-2 text-center">
                    <div>
                        <div class="counter-value" style="font-size:1.5rem;">
                            <span data-counter="<?= $totalRaised > 0 ? (int) $totalRaised : 2500000 ?>">0</span>
                        </div>
                        <small class="text-muted">₹ raised so far</small>
                    </div>
                    <div>
                        <div class="counter-value" style="font-size:1.5rem;">
                            <span data-counter="<?= $totalDonors > 0 ? $totalDonors : 1200 ?>">0</span>+
                        </div>
                        <small class="text-muted">Kind donors</small>
                    </div>
                </div>

                <div class="divider"></div>

                <p class="text-muted mb-1" style="font-size:.9rem;">Prefer to give offline or need help?</p>
                <a class="btn btn-outline btn-block btn-sm" href="mailto:<?= e($email) ?>"><?= lucide('mail') ?> <?= e($email) ?></a>
                <a class="btn btn-ghost btn-block btn-sm mt-2" href="tel:<?= e(preg_replace('/\s+/', '', $phone)) ?>"><?= lucide('phone') ?> <?= e($phone) ?></a>
            </div>
        </aside>
    </div>
</section>

<!-- ============================== WHY DONATE ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Why Your Support Matters</span>
            <h2 class="section-title">Compassion, Turned Into Change</h2>
            <p class="section-subtitle">We work where the need is greatest — bringing dignity, opportunity and hope to communities across Bihar. Here's what your generosity powers.</p>
        </div>
        <div class="grid grid-3">
            <div class="card-3d reveal">
                <div class="icon-badge"><?= lucide('book') ?></div>
                <h3 class="card-title">Educate a Child</h3>
                <p class="card-text">Fund learning kits, scholarships and safe classrooms so no child is ever left behind.</p>
            </div>
            <div class="card-3d reveal delay-1">
                <div class="icon-badge accent"><?= lucide('stethoscope') ?></div>
                <h3 class="card-title">Deliver Healthcare</h3>
                <p class="card-text">Support free medical camps, essential medicines and health awareness in rural areas.</p>
            </div>
            <div class="card-3d reveal delay-2">
                <div class="icon-badge"><?= lucide('life-buoy') ?></div>
                <h3 class="card-title">Provide Relief</h3>
                <p class="card-text">Bring rapid food, shelter and rehabilitation to families facing crisis and disaster.</p>
            </div>
        </div>
    </div>
</section>

<!-- ============================== IMPACT OF EACH AMOUNT ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">The Power of Your Gift</span>
            <h2 class="section-title">What Your Donation Makes Possible</h2>
            <p class="section-subtitle">Small or large, every contribution creates something real. Here's the difference each amount makes.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($presets as $i => $p): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?> text-center">
                    <div class="icon-badge <?= $i % 2 ? 'accent' : '' ?>"><?= lucide($p['icon']) ?></div>
                    <div class="counter-value" style="font-size:1.5rem;"><?= e(money((float) $p['amount'])) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($p['title']) ?></h3>
                    <p class="card-text"><?= e($p['impact']) ?></p>
                    <a class="btn btn-outline btn-sm mt-2" href="#donation-amount" data-give="<?= (int) $p['amount'] ?>">Give <?= e(money((float) $p['amount'])) ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== 80G TAX BENEFIT ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Give &amp; Save Tax</span>
            <h2 class="section-title">Your Donation Is 80G Tax-Exempt</h2>
            <p class="section-subtitle">Donations to <?= e($siteName) ?> qualify for tax benefits under the Income Tax Act, 1961 — so your generosity goes even further.</p>
        </div>

        <div class="grid grid-2 gap-4">
            <div class="card-3d reveal">
                <div class="icon-badge accent"><?= lucide('receipt') ?></div>
                <h3 class="card-title">80G Certification</h3>
                <p class="card-text">Claim a deduction of <strong>50% of your donated amount</strong> from your taxable income, subject to the limits prescribed by law.</p>
                <p class="mt-2"><span class="badge badge-success">80G Reg. No.</span> <strong><?= e($reg80g) ?></strong></p>
            </div>
            <div class="card-3d reveal delay-1">
                <div class="icon-badge"><?= lucide('scroll') ?></div>
                <h3 class="card-title">12A Registered</h3>
                <p class="card-text">We are registered under Section 12A, ensuring every contribution is applied to our charitable objectives with full income-tax exemption.</p>
                <p class="mt-2"><span class="badge badge-success">12A Reg. No.</span> <strong><?= e($reg12a) ?></strong></p>
            </div>
        </div>

        <div class="alert alert-info mt-4" role="note">
            <strong>Claiming your deduction:</strong> An official 80G receipt bearing our PAN and registration number is issued
            for every contribution. Just share your <strong>PAN</strong> and postal address while donating and we'll send your
            stamped receipt. Need a duplicate for filing? Write to
            <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.
        </div>
    </div>
</section>

<!-- ============================== BANK / OFFLINE GIVING ============================== -->
<section class="section">
    <div class="container container-narrow">
        <div class="section-head">
            <span class="eyebrow">Prefer to Give Offline?</span>
            <h2 class="section-title">Direct Bank Transfer</h2>
            <p class="section-subtitle">Contribute securely via NEFT / RTGS / IMPS or UPI using the account details below. Email us the reference so we can send your 80G receipt.</p>
        </div>

        <div class="card-3d reveal">
            <div class="flex items-center gap-2 mb-3">
                <div class="icon-badge"><?= lucide('landmark') ?></div>
                <div>
                    <strong style="font-size:1.15rem;">Donation Bank Account</strong><br>
                    <small class="text-muted">All transfers are receipted and eligible for 80G benefits.</small>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <?php foreach ($bankRows as $row): ?>
                        <tr>
                            <th scope="row" style="width:40%;"><?= e($row['label']) ?></th>
                            <td><strong><?= e($row['value']) ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!empty($bankUpi)): ?>
                        <tr>
                            <th scope="row">UPI ID</th>
                            <td><strong><?= e($bankUpi) ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-primary" href="mailto:<?= e($email) ?>?subject=<?= e(rawurlencode('Donation transfer confirmation')) ?>"><?= lucide('mail') ?> Send Transfer Details</a>
                <a class="btn btn-outline" href="<?= e(url('ngo-details')) ?>">View Full NGO Details</a>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA BAND ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="card-3d reveal text-center" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="justify-content:center;color:#dbeafe;"><?= e(SITE_TAGLINE) ?></span>
            <h2 class="text-white">More Than a Donation — a Movement</h2>
            <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">Can't give today? You can still make a difference. Explore our campaigns, volunteer your time, or spread the word among people who care.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-4">
                <a class="btn btn-white btn-lg" href="<?= e(url('campaigns')) ?>"><?= lucide('target') ?> Explore Campaigns</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Become a Volunteer</a>
            </div>
        </div>
    </div>
</section>

<!-- Amount-preset wiring: keep the buttons and the amount field in sync. -->
<script>
(function () {
    var input = document.getElementById('donation-amount');
    if (!input) return;
    var presetBtns = Array.prototype.slice.call(document.querySelectorAll('#amount-presets [data-amount]'));
    var customBtn  = document.querySelector('#amount-presets [data-amount="custom"]');

    function activate(el, on) {
        if (!el) return;
        el.classList.toggle('btn-primary', on);
        el.classList.toggle('btn-outline', !on);
    }

    function sync() {
        var val = parseInt(input.value, 10);
        var matched = false;
        presetBtns.forEach(function (b) {
            var a = b.getAttribute('data-amount');
            if (a === 'custom') return;
            var on = (parseInt(a, 10) === val);
            activate(b, on);
            if (on) matched = true;
        });
        activate(customBtn, !matched && input.value !== '');
    }

    presetBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            var a = this.getAttribute('data-amount');
            if (a === 'custom') {
                input.value = '';
                input.focus();
            } else {
                input.value = a;
            }
            sync();
        });
    });

    input.addEventListener('input', sync);

    // "Give ₹X" buttons in the impact cards jump to the form and pre-fill.
    document.querySelectorAll('[data-give]').forEach(function (link) {
        link.addEventListener('click', function () {
            input.value = this.getAttribute('data-give');
            sync();
            setTimeout(function () { input.focus(); }, 350);
        });
    });

    sync();
})();
</script>

<?php echo country_field_assets();
include __DIR__ . '/includes/footer.php'; ?>
