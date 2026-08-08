<?php
/**
 * =============================================================================
 *  Management Body — governance page.
 *
 *  Shows the board / registered directors (team_members WHERE is_leadership=1),
 *  a governance & accountability note, the organisational structure, and the
 *  foundation's statutory registration details. Every block is DB-driven with a
 *  graceful fallback so the page looks complete before any content is seeded.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Data ------------------------------------------------------------- */
$leaders = db_all(
    "SELECT * FROM team_members
     WHERE is_leadership = 1 AND status = 1
     ORDER BY sort_order ASC, id ASC"
);

/* Fallback: the two registered directors on record with the RoC. */
if (!$leaders) {
    $leaders = [];
}

/* Board strength for the summary line (real rows or the fallback directors). */
$boardCount = count($leaders);

/* Governance pillars — the principles that guide the board. */
$pillars = [
    ['icon' => 'search',    'title' => 'Transparency',       'text' => 'Every rupee is tracked from donation to delivery, and our books are open to the communities and donors we answer to.'],
    ['icon' => 'scale',     'title' => 'Compliance',          'text' => 'We operate as a registered non-profit and adhere to statutory, financial and reporting obligations without exception.'],
    ['icon' => 'handshake', 'title' => 'Accountability',      'text' => 'The board reviews programme outcomes and finances regularly, holding the organisation to its commitments.'],
    ['icon' => 'gem',       'title' => 'Ethical Conduct',      'text' => 'Decisions are guided by integrity, fairness and the dignity of the people we serve — never by personal gain.'],
];

/* Organisational structure — top-down governance flow. */
$structure = [
    ['level' => '01', 'title' => 'General Body / Members',    'text' => 'The foundational members of the organisation who elect the board and uphold the objectives set out in our constitution.'],
    ['level' => '02', 'title' => 'Board of Directors',        'text' => 'The registered directors who provide governance, set strategy, approve budgets and safeguard accountability.'],
    ['level' => '03', 'title' => 'Executive & Secretariat',   'text' => 'The management team responsible for translating board decisions into day-to-day operations and compliance.'],
    ['level' => '04', 'title' => 'Programme & Operations Teams', 'text' => 'Coordinators and staff who design, run and monitor our education, healthcare, skilling and relief initiatives.'],
    ['level' => '05', 'title' => 'Volunteers & Field Workers', 'text' => 'The grassroots force that carries our work to villages and neighbourhoods, close to the communities we serve.'],
];

/* Statutory registration details (from constants / settings). */
$registration = [
    ['label' => 'Registered Name',   'value' => get_setting('site_name', SITE_NAME)],
    ['label' => 'CIN',               'value' => get_setting('cin', SITE_CIN)],
    ['label' => 'Entity Type',       'value' => 'Non-Profit Company (Section 8)'],
    ['label' => 'Registered Office', 'value' => get_setting('contact_address', SITE_ADDRESS)],
    ['label' => 'Contact',           'value' => get_setting('contact_email', SITE_EMAIL)],
];

seo_set([
    'title'       => 'Management Body',
    'description' => 'Meet the management body and registered directors of EDUSKILL INDIA FOUNDATION. Learn how our board governs with transparency, accountability and integrity across Bihar.',
    'page_key'    => 'management-body',
]);

$page_hero = [
    'title'      => 'Management Body',
    'subtitle'   => 'The people and principles that govern EDUSKILL INDIA FOUNDATION — leading with transparency, accountability and integrity.',
    'breadcrumb' => [
        ['label' => 'About', 'url' => url('about')],
        ['label' => 'Management Body'],
    ],
];

include __DIR__ . '/includes/header.php';
?>

<!-- ============================== GOVERNANCE INTRO ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4 items-center">
        <div class="reveal">
            <span class="eyebrow">Governance</span>
            <h2 class="section-title">Governed with integrity, accountable to every community we serve</h2>
            <p class="text-muted"><?= e(get_setting('governance_intro', 'EDUSKILL INDIA FOUNDATION is a registered non-profit governed by a dedicated Board of Directors. Our governance framework keeps decision-making transparent, our finances open, and our programmes answerable to the very communities they exist to serve. Good governance is not paperwork for us — it is the promise that keeps trust intact.')) ?></p>
            <ul class="list-check mt-3">
                <li>A registered board that meets regularly to review impact and finances</li>
                <li>Clear separation between governance and day-to-day management</li>
                <li>Every rupee tracked — from donation to delivery</li>
                <li>Compliant with statutory, financial and reporting obligations</li>
            </ul>
            <a class="btn btn-primary mt-4" href="<?= e(url('contact')) ?>">Request Our Governance Documents</a>
        </div>

        <div class="card-3d reveal delay-1" style="background:var(--grad-brand);color:#fff;">
            <span class="eyebrow" style="color:#fff;opacity:.9;">On Record</span>
            <h3 class="text-white">Registered &amp; Accountable</h3>
            <p style="color:rgba(255,255,255,.9);">We are incorporated as a not-for-profit and led by <?= (int) $boardCount ?> registered
                director<?= $boardCount === 1 ? '' : 's' ?>. Our statutory details are published here so supporters can
                verify exactly who they are trusting.</p>
            <ul class="footer-contact" style="list-style:none;padding:0;margin-top:1rem;line-height:2;color:rgba(255,255,255,.95);">
                <li><?= lucide('landmark') ?> <span>CIN: <?= e(get_setting('cin', SITE_CIN)) ?></span></li>
                <li><?= lucide('map-pin') ?> <span><?= e(get_setting('contact_address', SITE_ADDRESS)) ?></span></li>
                <li><?= lucide('mail') ?> <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>" style="color:#fff;text-decoration:underline;"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a></li>
            </ul>
        </div>
    </div>
</section>

<!-- ============================== MANAGEMENT BODY / BOARD ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">The Board</span>
            <h2 class="section-title">Our Management Body</h2>
            <p class="section-subtitle">The directors who set our direction, safeguard our resources and hold the Foundation accountable to its mission.</p>
        </div>

        <?php if ($leaders): ?>
        <div class="grid <?= $boardCount <= 2 ? 'grid-2' : 'grid-3' ?>">
            <?php foreach ($leaders as $i => $m):
                $socials = is_array($m['socials'] ?? null) ? $m['socials'] : json_column($m['socials'] ?? null);
            ?>
                <article class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="card-media" style="aspect-ratio:4/3;">
                        <img src="<?= e(image_url($m['photo'] ?? null, 'avatar')) ?>" alt="<?= e($m['name']) ?>" loading="lazy" width="600" height="450" style="object-fit:cover;">
                    </div>
                    <div class="card-body">
                        <h3 class="card-title mb-1"><?= e($m['name']) ?></h3>
                        <?php if (!empty($m['designation'])): ?>
                            <span class="badge badge-brand"><?= e($m['designation']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($m['department'])): ?>
                            <span class="badge badge-muted"><?= e($m['department']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($m['bio'])): ?>
                            <p class="card-text mt-2"><?= e(excerpt($m['bio'], 45)) ?></p>
                        <?php endif; ?>

                        <?php if ($socials || !empty($m['email']) || !empty($m['phone'])): ?>
                        <div class="social-links mt-2">
                            <?php foreach ($socials as $platform => $link): if (empty($link)) continue; ?>
                                <a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e((string) $platform) ?>">
                                    <?= social_fa((string) $platform) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!empty($m['email'])): ?>
                                <a href="mailto:<?= e($m['email']) ?>" aria-label="Email <?= e($m['name']) ?>"><?= lucide('mail') ?></a>
                            <?php endif; ?>
                            <?php if (!empty($m['phone'])): ?>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $m['phone'])) ?>" aria-label="Call <?= e($m['name']) ?>"><?= lucide('phone') ?></a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="icon-badge"><?= lucide('users') ?></div>
            <h3>Board details coming soon</h3>
            <p class="text-muted">Our management body information is being updated. Please check back shortly or reach out to us directly.</p>
            <a class="btn btn-primary mt-3" href="<?= e(url('contact')) ?>">Contact Us</a>
        </div>
        <?php endif; ?>

        <p class="text-muted text-center mt-4" style="max-width:720px;margin-inline:auto;">
            The Foundation is governed by its registered Board of Directors in accordance with its constitution and
            applicable regulations. Board members serve on a voluntary, non-remunerated basis.
        </p>
    </div>
</section>

<!-- ============================== ORGANISATIONAL STRUCTURE ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">How We Are Organised</span>
            <h2 class="section-title">Organisational Structure</h2>
            <p class="section-subtitle">A clear line of governance — from our members and board to the volunteers who carry our work into the field.</p>
        </div>

        <div class="grid gap-3" style="max-width:820px;margin-inline:auto;">
            <?php foreach ($structure as $i => $node): ?>
                <div class="card reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="card-body flex items-center gap-3">
                        <div class="icon-badge<?= $i === 1 ? ' accent' : '' ?>" style="flex:0 0 auto;font-size:1.05rem;"><?= e($node['level']) ?></div>
                        <div>
                            <h3 class="card-title mb-1"><?= e($node['title']) ?></h3>
                            <p class="card-text"><?= e($node['text']) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== GOVERNANCE & ACCOUNTABILITY ============================== -->
<section class="section section-alt">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Our Commitment</span>
            <h2 class="section-title">Governance &amp; Accountability</h2>
            <p class="section-subtitle">Four principles anchor every decision our board takes and every programme we run.</p>
        </div>
        <div class="grid grid-4">
            <?php foreach ($pillars as $i => $p): ?>
                <div class="card-3d reveal <?= 'delay-' . (($i % 4) + 1) ?>">
                    <div class="icon-badge<?= $i % 2 ? ' accent' : '' ?>"><?= lucide($p['icon']) ?></div>
                    <h3 class="card-title"><?= e($p['title']) ?></h3>
                    <p class="card-text"><?= e($p['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== STATUTORY / REGISTRATION DETAILS ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4 items-start">
        <div class="reveal">
            <span class="eyebrow">On the Record</span>
            <h2 class="section-title">Statutory &amp; Registration Details</h2>
            <p class="text-muted">Transparency starts with knowing exactly who we are. Below are the official particulars of the
                Foundation as registered with the authorities.</p>
            <div class="table-wrap mt-3">
                <table class="table">
                    <tbody>
                        <?php foreach ($registration as $row): ?>
                            <tr>
                                <th scope="row" style="white-space:nowrap;"><?= e($row['label']) ?></th>
                                <td><?= e($row['value']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-3d reveal delay-1" style="background:var(--grad-accent);color:#fff;">
            <h2 class="text-white">Have a governance question?</h2>
            <p style="color:rgba(255,255,255,.92);">We welcome scrutiny. If you would like details about our board, our
                finances, or how a specific programme is run, our team is glad to help.</p>
            <div class="hero-actions mt-3">
                <a class="btn btn-white btn-lg" href="<?= e(url('contact')) ?>">Contact the Team</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('about')) ?>">About the Foundation</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
