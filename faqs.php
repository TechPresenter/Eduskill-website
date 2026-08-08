<?php
/**
 * =============================================================================
 *  Frequently Asked Questions — everything supporters, volunteers, donors and
 *  beneficiaries ask us, in one searchable, filterable place.
 *
 *  Fully DB-driven (faqs table) with a curated fallback set so the page is
 *  complete and premium before any content is seeded. Category filtering uses
 *  the documented .tabs pattern (handled by premium.js); a small inline search
 *  script filters accordions live. Every echoed value is escaped with e().
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$rows = db_all("SELECT * FROM faqs WHERE status = 1 ORDER BY category ASC, sort_order ASC, id ASC");

/* Curated fallback FAQ set (used before any rows are seeded). */
if (!$rows) {
    $rows = [
        // General ----------------------------------------------------------
        ['category' => 'General', 'question' => 'What is EDUSKILL INDIA FOUNDATION?',
         'answer' => "EDUSKILL INDIA FOUNDATION is a registered non-profit (CIN " . SITE_CIN . ") based in Patna, Bihar. We work hand-in-hand with communities to deliver education, healthcare, skill development and emergency relief — always with compassion, transparency and accountability."],
        ['category' => 'General', 'question' => 'Where does the Foundation work?',
         'answer' => "We are rooted in Patna, Bihar, and reach both rural and urban communities that are too often left behind. Our teams and volunteers take programmes directly to the doorstep of the people who need them most."],
        ['category' => 'General', 'question' => 'Is my contribution and involvement tax deductible?',
         'answer' => "We are a registered non-profit and issue receipts for every contribution. Where applicable, eligible donations qualify for tax benefits under Indian law. Please share your PAN while donating so we can send the correct receipt."],

        // Donations --------------------------------------------------------
        ['category' => 'Donations', 'question' => 'How can I make a donation?',
         'answer' => "You can donate securely online through our Donate page, or reach out to us directly for bank transfer and offline options. Every rupee is tracked from donation to delivery, and you will always receive a receipt."],
        ['category' => 'Donations', 'question' => 'Can I choose which programme my donation supports?',
         'answer' => "Yes. You can direct your gift towards a specific programme or campaign — such as education, healthcare or clean water — or give to our general fund and let us allocate it where the need is greatest."],
        ['category' => 'Donations', 'question' => 'Will I receive a receipt and updates?',
         'answer' => "Absolutely. A receipt is issued for every donation, and we share regular impact updates so you can see exactly how your generosity is changing lives on the ground."],

        // Volunteering -----------------------------------------------------
        ['category' => 'Volunteering', 'question' => 'How do I become a volunteer?',
         'answer' => "Simply fill in the volunteer form on our Get Involved page. Tell us your areas of interest and availability, and our team will get in touch to match you with an opportunity that fits."],
        ['category' => 'Volunteering', 'question' => 'Do I need special skills or experience to volunteer?',
         'answer' => "Not at all. We welcome people from every background. Whether you can teach, organise, design, fundraise or simply lend a hand at a camp, there is a meaningful role for you."],
        ['category' => 'Volunteering', 'question' => 'Do you offer internships and certificates?',
         'answer' => "Yes. We run structured internships across programme, communications and field roles, and issue certificates on completion. Volunteers who complete their engagement also receive a certificate of appreciation."],

        // Membership -------------------------------------------------------
        ['category' => 'Membership', 'question' => 'What are the benefits of becoming a member?',
         'answer' => "Members become part of our extended family of changemakers — with priority updates, invitations to events, and the ability to shape and support our programmes throughout the year."],
        ['category' => 'Membership', 'question' => 'How do I apply for membership?',
         'answer' => "Choose a membership plan that suits you and complete the short application. Once reviewed, our team will confirm your membership and welcome you aboard."],

        // Programs ---------------------------------------------------------
        ['category' => 'Programs', 'question' => 'What programmes does the Foundation run?',
         'answer' => "Our core programmes span education for all, healthcare and medical camps, women empowerment, skill development, clean water and sanitation, and disaster relief and rehabilitation. Explore each one on our Programs page."],
        ['category' => 'Programs', 'question' => 'How do you measure the impact of your work?',
         'answer' => "Every programme is designed with clear goals and tracked against measurable outcomes — lives reached, projects completed, villages served. We report openly so supporters can see the difference their support makes."],
    ];
}

/* Group FAQs by category, preserving first-seen order. */
$grouped = [];
foreach ($rows as $r) {
    $cat = trim((string) ($r['category'] ?? '')) ?: 'General';
    $grouped[$cat][] = $r;
}
$categories = array_keys($grouped);

/**
 * Pick a Lucide icon name for a category name (rendered via lucide()).
 */
$catIcon = static function (string $name): string {
    $key = strtolower($name);
    $map = [
        'general'      => 'info',
        'donation'     => 'gift',
        'donations'    => 'gift',
        'donate'       => 'gift',
        'volunteer'    => 'handshake',
        'volunteering' => 'handshake',
        'membership'   => 'id-card',
        'member'       => 'id-card',
        'program'      => 'target',
        'programs'     => 'target',
        'programmes'   => 'target',
        'event'        => 'calendar-days',
        'events'       => 'calendar-days',
        'career'       => 'briefcase',
        'careers'      => 'briefcase',
        'partner'      => 'handshake',
        'partnership'  => 'handshake',
        'scholarship'  => 'graduation-cap',
        'scholarships' => 'graduation-cap',
    ];
    return $map[$key] ?? 'circle-help';
};

$totalFaqs   = count($rows);
$contactEmail = get_setting('contact_email', SITE_EMAIL);
$contactPhone = get_setting('contact_phone', SITE_PHONE);
$phoneDigits  = preg_replace('/[^0-9]/', '', $contactPhone);

seo_set([
    'title'       => 'Frequently Asked Questions',
    'description' => 'Answers to the most common questions about EDUSKILL INDIA FOUNDATION — donations, volunteering, membership, our programmes and how you can get involved and create lasting change.',
    'page_key'    => 'faqs',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Frequently Asked Questions',
    'subtitle'   => 'Everything you need to know about our work, donations, volunteering and how to get involved — all in one place.',
    'breadcrumb' => [['label' => 'FAQs']],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== QUICK HELP ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">How Can We Help?</span>
            <h2 class="section-title">Popular Ways To Get Involved</h2>
            <p class="section-subtitle">Jump straight to what you are looking for, or browse all <?= (int) $totalFaqs ?>+ answers below.</p>
        </div>
        <div class="grid grid-4">
            <a class="glass-card icon-box reveal" href="<?= e(url('donate')) ?>" style="color:inherit;text-decoration:none;">
                <div class="ib-icon"><?= lucide('gift') ?></div>
                <h3 class="card-title">Make a Donation</h3>
                <p class="card-text">Every rupee is tracked from donation to delivery.</p>
                <span style="color:var(--brand-600);font-weight:700;">Donate <?= lucide('arrow-right') ?></span>
            </a>
            <a class="glass-card icon-box reveal delay-1" href="<?= e(url('volunteer')) ?>" style="color:inherit;text-decoration:none;">
                <div class="ib-icon" style="background:var(--grad-green);"><?= lucide('handshake') ?></div>
                <h3 class="card-title">Volunteer With Us</h3>
                <p class="card-text">Give your time and skills to a cause that matters.</p>
                <span style="color:var(--brand-600);font-weight:700;">Join Us <?= lucide('arrow-right') ?></span>
            </a>
            <a class="glass-card icon-box reveal delay-2" href="<?= e(url('membership')) ?>" style="color:inherit;text-decoration:none;">
                <div class="ib-icon" style="background:var(--grad-purple);"><?= lucide('id-card') ?></div>
                <h3 class="card-title">Become a Member</h3>
                <p class="card-text">Join our family of changemakers across Bihar.</p>
                <span style="color:var(--brand-600);font-weight:700;">Explore <?= lucide('arrow-right') ?></span>
            </a>
            <a class="glass-card icon-box reveal delay-3" href="<?= e(url('contact')) ?>" style="color:inherit;text-decoration:none;">
                <div class="ib-icon" style="background:var(--grad-orange);"><?= lucide('mail') ?></div>
                <h3 class="card-title">Contact Our Team</h3>
                <p class="card-text">Can't find your answer? We're happy to help.</p>
                <span style="color:var(--brand-600);font-weight:700;">Reach Out <?= lucide('arrow-right') ?></span>
            </a>
        </div>
    </div>
</section>

<!-- ============================== FAQ LIST (tabs + search) ============================== -->
<?php
/* Premium FAQ section — theme, animation, hover and icon styling are all
   driven from Admin -> Website Settings -> FAQ Section. Replaces the old
   category-tab accordion; the built-in search covers findability. */
echo faq_section([
    "eyebrow"   => "FAQ",
    "title"     => "Questions,",
    "highlight" => "answered",
    "subtitle"  => "Everything supporters usually ask us. Still stuck? Get in touch and a real person will reply.",
]);
?>

<!-- ============================== STILL HAVE QUESTIONS CTA ============================== -->
<section class="section section-brand" style="position:relative;overflow:hidden;">
    <span class="blob b2" style="top:-60px;right:-40px;"></span>
    <span class="blob b1" style="bottom:-70px;left:-50px;"></span>
    <div class="container text-center reveal" style="position:relative;z-index:1;">
        <span class="eyebrow" style="color:#bfdbfe;justify-content:center;">Still Need Help?</span>
        <h2 class="section-title text-white">Didn't Find Your Answer?</h2>
        <p class="section-subtitle" style="color:rgba(255,255,255,.9);max-width:640px;margin-inline:auto;">
            Our team is always happy to help. Reach out and we'll get back to you as soon as we can — usually within one working day.
        </p>
        <div class="flex flex-wrap justify-center gap-2 mt-4">
            <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>"><?= lucide('mail') ?> Contact Us</a>
            <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>
            <?php if ($phoneDigits): ?>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="tel:+<?= e($phoneDigits) ?>"><?= lucide('phone') ?> <?= e($contactPhone) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
/* Live FAQ search — filters accordion items by question + answer text.
   Works alongside the documented .tabs category filter (premium.js). */
(function () {
    var input = document.getElementById('faqSearch');
    var noRes = document.getElementById('faqNoResults');
    if (!input) return;
    var items = Array.prototype.slice.call(document.querySelectorAll('.faq-item'));

    function apply() {
        var q = input.value.trim().toLowerCase();
        items.forEach(function (item) {
            var hay = item.getAttribute('data-search') || '';
            item.style.display = (!q || hay.indexOf(q) !== -1) ? '' : 'none';
        });
        // Count items visible within the currently active tab panel.
        var visible = items.filter(function (item) {
            return item.style.display !== 'none' && item.offsetParent !== null;
        });
        if (noRes) noRes.style.display = visible.length ? 'none' : '';
    }

    input.addEventListener('input', apply);
    // Re-evaluate after a category tab switch so the empty state stays correct.
    document.querySelectorAll('.tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () { setTimeout(apply, 0); });
    });
})();
</script>
