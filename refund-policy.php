<?php
/**
 * =============================================================================
 *  Refund & Cancellation Policy — CMS-driven with a complete, real fallback.
 *
 *  If an admin has published a `pages` row with slug 'refund-policy', its body
 *  is rendered in a `.prose` block. Otherwise a full, genuine donation refund /
 *  cancellation policy (voluntary donations, refund window, the refund process,
 *  non-refundable cases, dispute contact and an 80G note) is shown so the page
 *  is always complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Organisation identity (settings first, then config constants) ------ */
$siteName = get_setting('site_name', SITE_NAME);
$email    = get_setting('contact_email', SITE_EMAIL);
$phone    = get_setting('contact_phone', SITE_PHONE);
$address  = get_setting('contact_address', SITE_ADDRESS);
$cin      = get_setting('cin', SITE_CIN);
$phoneTel = preg_replace('/[^0-9+]/', '', (string) $phone);

/* ---- Refund window (DB-driven, sensible default) ------------------------ */
$refundDays = (int) get_setting('refund_window_days', 7);
if ($refundDays < 1) {
    $refundDays = 7;
}
$processingDays = e(get_setting('refund_processing_days', '7–10 business days'));

/* ---- CMS page (admin-managed) with graceful fallback -------------------- */
$page = db_row(
    "SELECT * FROM pages WHERE slug = :slug AND status = 'published' LIMIT 1",
    [':slug' => 'refund-policy']
);

$hasContent   = $page && trim((string) ($page['content'] ?? '')) !== '';
$pageTitle    = $page['title']    ?? 'Refund & Cancellation Policy';
$pageSubtitle = $page['subtitle'] ?? 'Donations to ' . $siteName . ' are voluntary gifts, yet genuine mistakes happen. This policy explains when and how a contribution can be cancelled or refunded.';

/* ---- "Last updated" — CMS timestamp, then a setting, then a stable date -- */
$updatedRaw = !empty($page['updated_at'])
    ? $page['updated_at']
    : get_setting('refund_updated_at', '2026-01-01');
$updated = format_date($updatedRaw, 'd F Y');
if ($updated === '') {
    $updated = (string) $updatedRaw;
}

/* ---- Refund promises (at-a-glance cards) -------------------------------- */
$promises = [
    ['icon' => 'heart', 'title' => 'Given Voluntarily', 'text' => 'Every donation is a voluntary gift towards our charitable mission — never a purchase of goods or services.'],
    ['icon' => 'clock', 'title' => $refundDays . '-Day Window', 'text' => 'Report a duplicate, incorrect or unauthorised transaction within ' . $refundDays . ' days and we will review it in good faith.'],
    ['icon' => 'undo-2', 'title' => 'Fair, Prompt Process', 'text' => 'Approved refunds are returned to your original payment method, typically within ' . $processingDays . '.'],
    ['icon' => 'receipt', 'title' => '80G Handled Correctly', 'text' => 'If an 80G receipt was already issued, it is cancelled or adjusted so your tax records stay accurate.'],
];

/* ---- How-to-request steps (process-steps) ------------------------------- */
$steps = [
    ['title' => 'Reach Out Promptly', 'text' => 'Email us at ' . $email . ' within ' . $refundDays . ' days of the transaction with the subject line "Refund Request".'],
    ['title' => 'Share the Details', 'text' => 'Include the donor name, donation date, amount, transaction/reference ID and the reason for the request (e.g. duplicate or incorrect amount).'],
    ['title' => 'We Verify', 'text' => 'Our finance team verifies the payment with our records and payment gateway. We may contact you if we need anything further.'],
    ['title' => 'Refund Issued', 'text' => 'Once approved, the amount is refunded to your original payment method within ' . $processingDays . ', and any 80G receipt is updated accordingly.'],
];

/* ---- On-this-page navigation (used only for the fallback policy) -------- */
$toc = [
    'overview'       => 'Overview',
    'voluntary'      => 'Voluntary Donations',
    'eligibility'    => 'When a Refund May Apply',
    'window'         => 'Refund Window',
    'process'        => 'How to Request a Refund',
    'non-refundable' => 'Non-Refundable Cases',
    'cancellations'  => 'Recurring & Cancellations',
    'tax'            => '80G Tax Receipts',
    'disputes'       => 'Disputes & Chargebacks',
    'contact'        => 'Contact Us',
];

/* ---- SEO ---------------------------------------------------------------- */
seo_set([
    'title'       => 'Refund & Cancellation Policy',
    'description' => 'The donation refund and cancellation policy of ' . $siteName
        . ' — covering voluntary contributions, the ' . $refundDays . '-day refund window, how to request a refund, '
        . 'non-refundable cases, recurring donations, 80G tax receipts and dispute resolution.',
    'page_key'    => 'refund-policy',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => $pageTitle,
    'subtitle'   => $pageSubtitle,
    'breadcrumb' => [['label' => 'Refund & Cancellation Policy']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== AT A GLANCE ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Commitment</span>
            <h2 class="section-title">Refunds at a Glance</h2>
            <p class="section-subtitle">
                <?= e($siteName) ?> treats every contribution with care and complete transparency. Here is the short
                version of how cancellations and refunds work — the full policy follows below.
            </p>
        </div>

        <div class="grid grid-4">
            <?php foreach ($promises as $i => $c): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="ib-icon<?= $i % 2 ? ' o' : '' ?>"><?= lucide($c['icon']) ?></div>
                    <h3 class="card-title" style="font-size:1.05rem;"><?= e($c['title']) ?></h3>
                    <p class="card-text"><?= e($c['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== POLICY BODY ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="grid grid-sidebar items-start">

            <!-- Main policy content -->
            <div class="reveal">
                <div class="flex items-center flex-wrap gap-2 mb-3">
                    <span class="badge badge-brand">Last updated: <?= e($updated) ?></span>
                    <span class="badge badge-muted">CIN: <?= e($cin) ?></span>
                </div>

                <?php if ($hasContent): ?>
                    <!-- Admin-managed content from the CMS -->
                    <div class="prose">
                        <?= rich_text($page['content']) ?>
                    </div>
                <?php else: ?>
                    <!-- Complete generic donation refund / cancellation policy fallback -->
                    <div class="prose">
                        <h2 id="overview">1. Overview</h2>
                        <p>
                            This Refund &amp; Cancellation Policy explains how <?= e($siteName) ?>
                            (&ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;), a non-profit organisation registered
                            in India (CIN <?= e($cin) ?>), handles requests to cancel or refund a donation made through our
                            website or payment partners. It applies to all online contributions and should be read together
                            with our <a href="<?= e(url('terms')) ?>">Terms &amp; Conditions</a> and
                            <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>.
                        </p>

                        <h2 id="voluntary">2. Voluntary Donations</h2>
                        <p>
                            Donations to <?= e($siteName) ?> are <strong>voluntary gifts</strong> made in support of our
                            charitable objectives — education, healthcare, skill development and relief. A donation is not a
                            payment for goods or services and does not entitle the donor to any commercial benefit. Because
                            contributions are put to work in our programmes soon after they are received, they are generally
                            <strong>non-refundable</strong> once processed. We do, however, recognise that honest mistakes
                            happen, and we review every genuine request fairly and in good faith.
                        </p>

                        <h2 id="eligibility">3. When a Refund May Apply</h2>
                        <p>We will consider a refund where a donation was made in error, including situations such as:</p>
                        <ul class="list-check">
                            <li><strong>Duplicate transaction</strong> — you were charged more than once for the same intended donation.</li>
                            <li><strong>Incorrect amount</strong> — a clear typing error resulted in an amount materially different from what you intended to give.</li>
                            <li><strong>Unauthorised payment</strong> — the donation was made using your payment instrument without your consent.</li>
                            <li><strong>Technical error</strong> — you were debited but the donation failed to register, or the gateway charged you in error.</li>
                        </ul>
                        <p>
                            Each request is assessed individually. Approval is at the reasonable discretion of
                            <?= e($siteName) ?>, subject to verification with our records and the payment gateway.
                        </p>

                        <h2 id="window">4. Refund Window</h2>
                        <p>
                            To be eligible for review, a refund request must reach us within
                            <strong><?= e($refundDays) ?> days</strong> of the date of the transaction. Requests received
                            after this window may not be eligible, as funds are typically already committed to programme
                            work. Where a refund is approved, it is credited to the <strong>original payment method</strong>
                            used for the donation, generally within <strong><?= $processingDays ?></strong> after approval.
                            The exact time to reflect in your account depends on your bank or card issuer.
                        </p>

                        <h2 id="process">5. How to Request a Refund</h2>
                        <p>
                            To request a cancellation or refund, please email
                            <a href="mailto:<?= e($email) ?>?subject=Refund%20Request"><?= e($email) ?></a> with the subject
                            line &ldquo;Refund Request&rdquo; and include the following details so we can locate and verify
                            your donation quickly:
                        </p>
                        <ul class="list-check">
                            <li>Donor name (as entered at the time of donation)</li>
                            <li>Date and amount of the donation</li>
                            <li>Transaction / reference / order ID from your confirmation or bank statement</li>
                            <li>The reason for your request (e.g. duplicate charge, incorrect amount)</li>
                            <li>The email address and phone number used for the donation</li>
                        </ul>
                        <p>
                            We may ask for reasonable proof of payment and may need to verify your identity before processing
                            a refund. Providing complete and accurate information helps us resolve your request faster.
                        </p>

                        <h2 id="non-refundable">6. Non-Refundable Cases</h2>
                        <p>Refunds are ordinarily <strong>not</strong> available in the following situations:</p>
                        <ul class="list-check">
                            <li>A simple change of mind after a donation has been successfully processed.</li>
                            <li>Requests made after the <?= e($refundDays) ?>-day window has closed.</li>
                            <li>Donations for which an 80G tax-exemption benefit has already been claimed, unless the receipt is first surrendered or cancelled.</li>
                            <li>Contributions already disbursed to, or committed for, a specific project, campaign or beneficiary.</li>
                            <li>Nominal payment-gateway or transaction fees, which are levied by third-party providers and are non-recoverable.</li>
                            <li>Donations made in cash, in kind, or through offline channels not processed by our online payment partners.</li>
                        </ul>

                        <h2 id="cancellations">7. Recurring Donations &amp; Cancellations</h2>
                        <p>
                            If you have set up a recurring (monthly or periodic) donation, you may cancel future instalments
                            at any time by writing to us at <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>, or through
                            your account or the payment provider used to set up the mandate. Cancellation stops future
                            charges from the next billing cycle; instalments already collected and applied to our work are
                            treated in line with this policy and are not automatically refunded. We recommend cancelling at
                            least three business days before your next scheduled charge.
                        </p>

                        <h2 id="tax">8. 80G Tax Receipts</h2>
                        <p>
                            Eligible donations are issued an official receipt for tax exemption under Section 80G of the
                            Income Tax Act, 1961. If a donation is refunded, any 80G receipt already issued for that amount is
                            <strong>cancelled or adjusted</strong>, and it may not be used to claim a tax deduction. If you
                            have already filed a return using that receipt, you are responsible for making the appropriate
                            correction with the tax authorities. Please tell us at the time of your refund request whether an
                            80G receipt was issued so we can update our records accurately.
                        </p>

                        <h2 id="disputes">9. Disputes &amp; Chargebacks</h2>
                        <p>
                            We would much rather resolve any concern with you directly and quickly. If you believe a charge is
                            incorrect, please contact us <em>before</em> raising a dispute or chargeback with your bank, so we
                            can investigate and, where appropriate, refund you without delay. If a dispute is nonetheless
                            raised, we will cooperate fully and share the transaction records needed to resolve it fairly.
                        </p>

                        <h2 id="contact">10. Contact Us</h2>
                        <p>
                            For any question about this policy, or to request a cancellation or refund, please reach out to
                            our team:
                        </p>
                        <ul class="list-check">
                            <li><strong><?= e($siteName) ?></strong> (CIN <?= e($cin) ?>)</li>
                            <li>Email: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li>
                            <li>Phone: <a href="tel:<?= e($phoneTel) ?>"><?= e($phone) ?></a></li>
                            <li>Address: <?= e($address) ?></li>
                        </ul>
                        <p>
                            We aim to acknowledge every refund request within two business days and to resolve it as quickly
                            as our verification process allows. This policy may be updated from time to time; the current
                            version is always the one published on this page.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sticky helper sidebar -->
            <aside class="reveal delay-1">
                <div class="glass-card sticky-aside" style="position:sticky;top:90px;">
                    <div class="icon-badge accent"><?= lucide('handshake') ?></div>
                    <h3 class="card-title">Need Help With a Donation?</h3>
                    <p class="card-text">
                        Spotted a duplicate charge or an incorrect amount? Tell us within
                        <?= e($refundDays) ?> days and our finance team will make it right.
                    </p>
                    <ul class="list-check mt-2" style="font-size:.95rem;">
                        <li>Refunds go to your original payment method</li>
                        <li>Typically settled in <?= $processingDays ?></li>
                        <li>80G receipts adjusted automatically</li>
                    </ul>
                    <div class="flex flex-col gap-2 mt-4">
                        <a class="btn btn-3d btn-block" href="mailto:<?= e($email) ?>?subject=Refund%20Request">Request a Refund</a>
                        <a class="btn btn-outline btn-block" href="<?= e(url('contact')) ?>">Contact Page</a>
                    </div>

                    <?php if (!$hasContent): ?>
                        <hr class="divider">
                        <strong style="display:block;margin-bottom:.6rem;">On This Page</strong>
                        <ul class="toc-list">
                            <?php foreach ($toc as $anchor => $label): ?>
                                <li>
                                    <a href="#<?= e($anchor) ?>"><?= e($label) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>
</section>

<!-- ============================== REFUND PROCESS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Simple &amp; Transparent</span>
            <h2 class="section-title">How a Refund Request Works</h2>
            <p class="section-subtitle">
                Four straightforward steps — from the moment you get in touch to the day the amount is back in your account.
            </p>
        </div>
        <div class="grid grid-2 gap-4 items-start">
            <div class="process-steps reveal">
                <?php foreach ($steps as $s): ?>
                    <div class="process-step">
                        <h3 class="card-title mb-0" style="font-size:1.1rem;"><?= e($s['title']) ?></h3>
                        <p class="card-text"><?= e($s['text']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="grid gap-3 reveal delay-1">
                <div class="stat-premium">
                    <div class="counter-value text-grad-ocean"><span data-counter="<?= e($refundDays) ?>">0</span></div>
                    <div class="counter-label">Days to report an error</div>
                </div>
                <div class="glass-card">
                    <div class="icon-box" style="padding:0;">
                        <div class="ib-icon g" style="margin:0 0 1rem;"><?= lucide('receipt') ?></div>
                    </div>
                    <h3 class="card-title">Your Records Stay Clean</h3>
                    <p class="card-text">
                        Every approved refund is reconciled against our books and, where applicable, your 80G receipt — so
                        both your accounts and ours remain accurate and audit-ready.
                    </p>
                    <a class="btn btn-outline btn-sm mt-2" href="<?= e(url('ngo-details')) ?>">View NGO Details</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section section-brand">
    <div class="container text-center reveal">
        <h2 class="text-white">Every Rupee, Handled With Care</h2>
        <p style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            Your generosity powers real change across Bihar — and we treat it with the transparency it deserves. If
            anything about your donation needs attention, we are only an email away.
        </p>
        <div class="flex justify-center flex-wrap gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('donate')) ?>"><?= lucide('heart') ?> Donate Now</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="mailto:<?= e($email) ?>?subject=Refund%20Request">Request a Refund</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
