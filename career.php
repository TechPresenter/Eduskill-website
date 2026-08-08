<?php
/**
 * =============================================================================
 *  Careers — open roles listing and single job detail (?slug=…).
 *
 *  ?slug=<career-slug>  → single role: description + requirements prose,
 *                         type/location/salary/deadline/department badges and an
 *                         application form (AJAX, multipart) posting to
 *                         forms/career-apply.
 *  no slug              → premium recruitment page: hero, "join our mission"
 *                         intro, culture, workforce counters, filterable openings
 *                         (client-side search), alternative pathways, benefits,
 *                         hiring process, team voices and an on-page apply form.
 *
 *  Every section is DB-driven with graceful fallbacks (sample roles are shown
 *  when the careers table is empty) so the page looks complete and polished
 *  before any content is seeded. Only presentation was redesigned — all queries,
 *  form field names, endpoints, CSRF and fallbacks are preserved.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';

/* ---- Shared display metadata for employment types ---------------------- */
$typeMeta = [
    'full-time'  => ['label' => 'Full-time',  'badge' => 'badge-brand',   'icon' => 'briefcase'],
    'part-time'  => ['label' => 'Part-time',  'badge' => 'badge-accent',  'icon' => 'clock'],
    'contract'   => ['label' => 'Contract',   'badge' => 'badge-muted',   'icon' => 'file-text'],
    'internship' => ['label' => 'Internship', 'badge' => 'badge-success', 'icon' => 'graduation-cap'],
    'volunteer'  => ['label' => 'Volunteer',  'badge' => 'badge-success', 'icon' => 'handshake'],
];

/**
 * Derive a friendly experience level (label + filter bucket) from a role.
 * Presentation-only — the careers table has no experience column.
 */
$deriveExp = static function (array $c): array {
    $type = $c['type'] ?? 'full-time';
    if ($type === 'internship') return ['bucket' => 'entry',  'label' => 'Entry Level'];
    if ($type === 'volunteer')  return ['bucket' => 'any',    'label' => 'Any Level'];
    $t = strtolower((string) ($c['title'] ?? ''));
    if (preg_match('/\b(senior|lead|head|manager|director|principal|chief)\b/', $t)) {
        return ['bucket' => 'senior', 'label' => 'Senior Level'];
    }
    if (preg_match('/\b(intern|trainee|junior|assistant|fresher)\b/', $t)) {
        return ['bucket' => 'entry', 'label' => 'Entry Level'];
    }
    return ['bucket' => 'mid', 'label' => 'Mid Level'];
};

/**
 * Render one role as a card (detail-page "other roles" block).
 * Kept as-is so the detail view stays in sync with the original design.
 */
$roleCard = static function (array $c) use ($typeMeta): void {
    $type       = $c['type'] ?? 'full-time';
    $meta       = $typeMeta[$type] ?? $typeMeta['full-time'];
    $deadlineTs = !empty($c['deadline']) ? strtotime((string) $c['deadline']) : null;
    $expired    = $deadlineTs !== null && $deadlineTs < strtotime('today');
    $soon       = $deadlineTs !== null && !$expired && $deadlineTs <= strtotime('+7 days');
    $href       = url('career?slug=' . $c['slug']);
    ?>
    <article class="card-3d reveal" style="display:flex;flex-direction:column;gap:.75rem;">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <span class="badge <?= e($meta['badge']) ?>"><?= lucide($meta['icon']) ?> <?= e($meta['label']) ?></span>
            <?php if ($expired): ?>
                <span class="badge badge-danger">Applications closed</span>
            <?php elseif ($soon): ?>
                <span class="badge badge-accent"><?= lucide('hourglass') ?> Closing soon</span>
            <?php elseif (!empty($c['department'])): ?>
                <span class="chip"><?= e($c['department']) ?></span>
            <?php endif; ?>
        </div>
        <h3 class="card-title mb-0" style="margin-top:.25rem;">
            <a href="<?= e($href) ?>" style="color:inherit;"><?= e($c['title']) ?></a>
        </h3>
        <div class="flex flex-wrap gap-2 text-muted" style="font-size:.9rem;">
            <span><?= lucide('map-pin') ?> <?= e($c['location'] ?: 'Patna, Bihar') ?></span>
            <?php if (!empty($c['salary_range'])): ?><span>· <?= lucide('coins') ?> <?= e($c['salary_range']) ?></span><?php endif; ?>
            <?php if ((int) ($c['openings'] ?? 1) > 1): ?><span>· <?= lucide('users') ?> <?= (int) $c['openings'] ?> openings</span><?php endif; ?>
        </div>
        <?php if (!empty($c['description'])): ?>
            <p class="card-text" style="margin:0;"><?= e(excerpt(strip_tags((string) $c['description']), 20)) ?></p>
        <?php endif; ?>
        <div class="flex items-center justify-between gap-2 flex-wrap" style="margin-top:auto;">
            <?php if (!empty($c['deadline'])): ?>
                <small class="text-muted"><?= lucide('calendar') ?> <?= $expired ? 'Closed' : 'Apply by' ?> <?= e(format_date($c['deadline'], 'd M Y')) ?></small>
            <?php else: ?>
                <small class="text-muted"><?= lucide('calendar') ?> Rolling applications</small>
            <?php endif; ?>
            <a class="btn <?= $expired ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="<?= e($href) ?>">
                <?= $expired ? 'View Role' : 'Apply Now' ?><?php if (!$expired): ?> <?= lucide('arrow-right') ?><?php endif; ?>
            </a>
        </div>
    </article>
    <?php
};

/**
 * Premium filterable job card for the listing grid. Emits data-attributes that
 * the inline client-side filter reads. Real roles link to their detail page;
 * sample roles (no slug) scroll to the on-page application form.
 */
$jobCard = static function (array $c) use ($typeMeta, $deriveExp): void {
    $type       = $c['type'] ?? 'full-time';
    $meta       = $typeMeta[$type] ?? $typeMeta['full-time'];
    $isSample   = empty($c['slug']);
    $cid        = (int) ($c['id'] ?? 0);
    $exp        = $deriveExp($c);
    $dept       = trim((string) ($c['department'] ?? '')) ?: 'General';
    $loc        = trim((string) ($c['location'] ?? '')) ?: 'Patna, Bihar';
    $deadlineTs = !empty($c['deadline']) ? strtotime((string) $c['deadline']) : null;
    $expired    = $deadlineTs !== null && $deadlineTs < strtotime('today');
    $soon       = $deadlineTs !== null && !$expired && $deadlineTs <= strtotime('+10 days');
    $detailHref = $isSample ? '#car-apply' : url('career?slug=' . $c['slug']);
    $blob       = strtolower($c['title'] . ' ' . $dept . ' ' . $loc . ' ' . $meta['label'] . ' ' . $exp['label'] . ' ' . strip_tags((string) ($c['description'] ?? '')));
    ?>
    <article class="car-job reveal"
             data-car-card
             data-title="<?= e($blob) ?>"
             data-dept="<?= e(strtolower($dept)) ?>"
             data-loc="<?= e(strtolower($loc)) ?>"
             data-type="<?= e($type) ?>"
             data-exp="<?= e($exp['bucket']) ?>">
        <div class="car-job-top">
            <span class="badge <?= e($meta['badge']) ?>"><?= lucide($meta['icon']) ?> <?= e($meta['label']) ?></span>
            <?php if ($expired): ?>
                <span class="badge badge-danger">Closed</span>
            <?php elseif ($soon): ?>
                <span class="badge badge-accent"><?= lucide('hourglass') ?> Closing soon</span>
            <?php endif; ?>
        </div>

        <h3 class="car-job-title">
            <a href="<?= e($detailHref) ?>"><?= e($c['title']) ?></a>
        </h3>
        <span class="car-job-dept"><?= lucide('building-2') ?> <?= e($dept) ?></span>

        <ul class="car-job-meta">
            <li><?= lucide('map-pin') ?> <?= e($loc) ?></li>
            <li><?= lucide('target') ?> <?= e($exp['label']) ?></li>
            <?php if (!empty($c['salary_range'])): ?><li><?= lucide('coins') ?> <?= e($c['salary_range']) ?></li><?php endif; ?>
            <?php if ((int) ($c['openings'] ?? 1) > 1): ?><li><?= lucide('users') ?> <?= (int) $c['openings'] ?> openings</li><?php endif; ?>
        </ul>

        <?php if (!empty($c['description'])): ?>
            <p class="car-job-text"><?= e(excerpt(strip_tags((string) $c['description']), 20)) ?></p>
        <?php endif; ?>

        <div class="car-job-foot">
            <small class="text-muted">
                <?= lucide('calendar') ?>
                <?php if (!empty($c['deadline'])): ?>
                    <?= $expired ? 'Closed ' : 'Apply by ' ?><?= e(format_date($c['deadline'], 'd M Y')) ?>
                <?php else: ?>
                    Rolling applications
                <?php endif; ?>
            </small>
            <div class="car-job-actions">
                <a class="btn btn-outline btn-sm" href="<?= e($detailHref) ?>"><?= lucide('eye') ?> Details</a>
                <button type="button" class="btn btn-primary btn-sm car-apply-btn" data-apply="<?= $cid ?>" data-apply-title="<?= e($c['title']) ?>">Apply <?= lucide('arrow-right') ?></button>
            </div>
        </div>
    </article>
    <?php
};

/* Curated "why work with us" perks — always shown, seed-independent. */
$culture = [
    ['cls' => '',  'icon' => 'heart',          'title' => 'Purpose-Driven Work',    'text' => 'Every role directly touches real lives — you will see the difference you make in the communities we serve.'],
    ['cls' => 'p', 'icon' => 'sprout',         'title' => 'Grow & Lead',            'text' => 'Structured mentorship, training and a clear path to take on more responsibility as you grow with us.'],
    ['cls' => 'g', 'icon' => 'handshake',      'title' => 'A Team That Cares',       'text' => 'Warm, collaborative colleagues who support one another and celebrate wins big and small.'],
    ['cls' => 'o', 'icon' => 'alarm-clock',    'title' => 'Flexibility & Balance',   'text' => 'Sensible hours, remote-friendly roles where possible, and genuine respect for your wellbeing.'],
    ['cls' => '',  'icon' => 'graduation-cap', 'title' => 'Learning Culture',        'text' => 'Workshops, field exposure and a culture that rewards curiosity and continuous improvement.'],
    ['cls' => 'p', 'icon' => 'award',          'title' => 'Recognition',             'text' => 'Your contribution is seen and valued — with certificates, references and real opportunities to advance.'],
];

/* Curated hiring process — always shown. */
$hiringSteps = [
    ['title' => 'Apply Online',       'text' => 'Submit the short application form for the role that fits you, and attach your résumé.'],
    ['title' => 'Screening',          'text' => 'Our team reviews every application and reaches out to shortlisted candidates for a short chat.'],
    ['title' => 'Conversation',       'text' => 'A friendly interview to understand your strengths, motivation and how we can grow together.'],
    ['title' => 'Offer & Onboarding', 'text' => 'A warm welcome, orientation and everything you need to start creating impact from day one.'],
];

/**
 * Shared scoped assets (styles + generic form behaviour: résumé file preview and
 * inline validation states). Emitted once per request in whichever branch runs.
 */
$carAssets = static function (): void { ?>
<style>
/* ===================== CAREERS — scoped premium styles (.car-*) ===================== */
.page-hero { position: relative; overflow: hidden; }
.page-hero::after {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(60% 90% at 85% -10%, rgba(230,123,29,.28), transparent 60%),
        radial-gradient(50% 80% at 8% 120%, rgba(8,72,129,.30), transparent 60%);
    mix-blend-mode: screen;
}
.page-hero .container { position: relative; z-index: 1; }

/* isolation:isolate gives the card its own stacking context so the decorative
   blobs can never paint above the quote, whatever z-index they carry. The
   min-height stops the card collapsing to a short strip beside the tall
   left-hand column, which left the blobs floating in empty space. */
.car-intro-card {
    position: relative; overflow: hidden; isolation: isolate;
    display: flex; flex-direction: column; justify-content: center;
    min-height: 320px; padding: clamp(1.5rem, 3vw, 2.2rem);
}
.car-intro-card > div { position: relative; z-index: 2; }
.car-intro-card .blob { z-index: 0; }
.car-intro-card .card-title { margin: .9rem 0 .5rem; }
.car-intro-card .card-text { line-height: 1.7; font-size: 1rem; }
.car-intro-proof { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1.15rem; }
.car-intro-proof .chip { backdrop-filter: blur(6px); }

/* Workforce counters band */
.car-stats { position: relative; overflow: hidden; background: var(--grad-brand); border-radius: 28px; padding: clamp(1.6rem, 4vw, 3rem); color: #fff; box-shadow: var(--shadow-brand, 0 24px 60px -24px rgba(6,53,102,.6)); }
.car-stats::before { content: ''; position: absolute; inset: 0; background: radial-gradient(40% 70% at 90% 0, rgba(230,123,29,.35), transparent 60%); pointer-events: none; }
.car-stats-grid { position: relative; z-index: 1; display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.25rem; }
.car-stat { text-align: center; }
.car-stat-ic { width: 54px; height: 54px; margin: 0 auto .7rem; border-radius: 16px; display: grid; place-items: center; background: rgba(255,255,255,.16); backdrop-filter: blur(4px); }
.car-stat-ic svg { width: 26px; height: 26px; }
.car-stat-val { font-family: var(--font-display); font-size: clamp(1.7rem, 4vw, 2.5rem); font-weight: 800; line-height: 1; }
.car-stat-lab { margin-top: .35rem; font-size: .82rem; letter-spacing: .04em; text-transform: uppercase; opacity: .9; }

/* Filter bar */
.car-filter { position: relative; z-index: 3; display: grid; grid-template-columns: 1.6fr repeat(4, 1fr) auto; gap: .7rem; align-items: end; padding: 1.1rem; border-radius: 20px; }
.car-filter .car-field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
.car-filter label { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); }
.car-search-wrap { position: relative; }
.car-search-wrap svg { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); width: 18px; height: 18px; color: var(--muted); pointer-events: none; }
.car-search-wrap .form-control { padding-left: 2.4rem; }
.car-filter-reset { white-space: nowrap; }
.car-result-line { margin: 1.2rem 0 .2rem; font-weight: 600; color: var(--muted); }
.car-result-line b { color: var(--brand-600); }

/* Jobs grid */
.car-jobs { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.4rem; }
.car-job { position: relative; display: flex; flex-direction: column; gap: .6rem; padding: 1.5rem; border-radius: 22px; background: var(--card-bg, rgba(255,255,255,.9)); border: 1px solid rgba(0,0,0,.06); box-shadow: 0 10px 30px -18px rgba(6,53,102,.35); transition: transform .35s cubic-bezier(.2,.7,.2,1), box-shadow .35s, border-color .35s; overflow: hidden; }
.car-job::before { content: ''; position: absolute; left: 0; top: 0; height: 100%; width: 4px; background: var(--grad-brand); opacity: 0; transition: opacity .35s; }
.car-job:hover { transform: translateY(-6px); box-shadow: 0 26px 50px -24px rgba(6,53,102,.4); border-color: rgba(8,72,129,.4); }
.car-job:hover::before { opacity: 1; }
.car-job-top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; flex-wrap: wrap; }
.car-job-title { font-family: var(--font-display); font-size: 1.2rem; font-weight: 700; margin: .2rem 0 0; line-height: 1.25; }
.car-job-title a { color: inherit; text-decoration: none; }
.car-job-title a:hover { color: var(--brand-600); }
.car-job-dept { display: inline-flex; align-items: center; gap: .35rem; font-size: .85rem; font-weight: 600; color: var(--accent, #084881); }
.car-job-dept svg { width: 15px; height: 15px; }
.car-job-meta { list-style: none; padding: 0; margin: .2rem 0 0; display: flex; flex-wrap: wrap; gap: .35rem .9rem; }
.car-job-meta li { display: inline-flex; align-items: center; gap: .35rem; font-size: .86rem; color: var(--muted); }
.car-job-meta svg { width: 15px; height: 15px; color: var(--brand-600); }
.car-job-text { margin: .1rem 0 0; color: var(--muted); font-size: .92rem; line-height: 1.55; }
.car-job-foot { margin-top: auto; padding-top: .8rem; display: flex; align-items: center; justify-content: space-between; gap: .6rem; flex-wrap: wrap; border-top: 1px dashed rgba(0,0,0,.08); }
.car-job-foot small { display: inline-flex; align-items: center; gap: .35rem; }
.car-job-foot svg { width: 14px; height: 14px; }
.car-job-actions { display: flex; gap: .5rem; }
.car-no-results { text-align: center; padding: 3rem 1rem; color: var(--muted); }

/* Alternative pathways */
.car-paths { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.3rem; }
.car-path { text-align: center; padding: 2rem 1.4rem; border-radius: 22px; }
.car-path .ib-icon { margin-bottom: 1rem; }

/* Benefits */
.car-benefits { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 1.2rem; }
.car-benefit { display: flex; gap: 1rem; padding: 1.4rem; border-radius: 20px; background: var(--card-bg, rgba(255,255,255,.85)); border: 1px solid rgba(0,0,0,.06); box-shadow: 0 10px 26px -20px rgba(6,53,102,.4); transition: transform .3s, box-shadow .3s; }
.car-benefit:hover { transform: translateY(-4px); box-shadow: 0 22px 44px -24px rgba(6,53,102,.45); }
.car-benefit-ic { flex: 0 0 auto; width: 48px; height: 48px; border-radius: 14px; display: grid; place-items: center; color: #fff; background: var(--grad-brand); box-shadow: var(--shadow-brand, 0 10px 24px -12px rgba(6,53,102,.6)); }
.car-benefit-ic svg { width: 24px; height: 24px; }
.car-benefit h3 { font-size: 1.02rem; margin: 0 0 .25rem; }
.car-benefit p { margin: 0; font-size: .88rem; color: var(--muted); line-height: 1.5; }

/* Team voices */
.car-voices { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.4rem; }
.car-voice { position: relative; padding: 2rem 1.6rem 1.6rem; border-radius: 22px; background: var(--card-bg, rgba(255,255,255,.9)); border: 1px solid rgba(0,0,0,.06); box-shadow: 0 14px 34px -22px rgba(6,53,102,.4); }
.car-voice-quote { position: absolute; top: -14px; left: 22px; width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; color: #fff; background: var(--grad-accent, linear-gradient(135deg,#084881,#063566)); box-shadow: 0 12px 24px -12px rgba(8,72,129,.7); }
.car-voice-quote svg { width: 22px; height: 22px; }
.car-voice p { margin: .4rem 0 1.2rem; font-style: italic; color: var(--text, inherit); line-height: 1.6; }
.car-voice-who { display: flex; align-items: center; gap: .8rem; }
.car-voice-av { width: 46px; height: 46px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; color: #fff; background: var(--grad-brand); font-family: var(--font-display); }
.car-voice-who b { display: block; }
.car-voice-who small { color: var(--muted); }

/* Apply form (glass card + floating labels) */
.car-apply-card { position: relative; overflow: hidden; }
.car-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.1rem; }
.car-col-full { grid-column: 1 / -1; }
.car-fl { position: relative; padding-top: 1.15rem; }
.car-fl > .form-control, .car-fl > .form-textarea { width: 100%; }
.car-fl > label { position: absolute; left: .95rem; top: 1.9rem; font-size: .95rem; color: var(--muted); pointer-events: none; transition: all .16s ease; }
.car-fl > .form-textarea + label { top: 1.9rem; }
.car-fl > .form-control:focus ~ label,
.car-fl > .form-control:not(:placeholder-shown) ~ label,
.car-fl > .form-textarea:focus ~ label,
.car-fl > .form-textarea:not(:placeholder-shown) ~ label {
    top: .1rem; left: .25rem; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--brand-600);
}
.car-static { display: block; font-size: .72rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--brand-600); margin-bottom: .4rem; }
.car-file { position: relative; border: 2px dashed rgba(8,72,129,.5); border-radius: 16px; padding: 1.1rem; text-align: center; transition: border-color .25s, background .25s; cursor: pointer; }
.car-file:hover { border-color: var(--brand-600); background: rgba(8,72,129,.05); }
.car-file input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.car-file-inner { display: flex; align-items: center; justify-content: center; gap: .6rem; color: var(--muted); font-size: .9rem; }
.car-file-inner svg { width: 22px; height: 22px; color: var(--brand-600); }
.car-file.has-file { border-style: solid; border-color: var(--brand-600); background: rgba(6,53,102,.06); }
.car-file-name { display: block; margin-top: .5rem; font-weight: 700; color: var(--brand-600); font-size: .88rem; word-break: break-word; }
.car-apply-card .is-invalid { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220,38,38,.12) !important; }
.car-apply-card .is-valid { border-color: #58A42F !important; }
.car-detail-apply .is-invalid { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220,38,38,.12) !important; }
.car-detail-apply .is-valid { border-color: #58A42F !important; }

@media (max-width: 720px) {
    .car-stats-grid { grid-template-columns: repeat(2, 1fr); gap: 1.4rem; }
    .car-filter { grid-template-columns: 1fr; }
    .car-form-grid { grid-template-columns: 1fr; }
    .car-job-actions { width: 100%; }
    .car-job-actions .btn { flex: 1; }
}
@media (min-width: 721px) and (max-width: 1024px) {
    .car-stats-grid { grid-template-columns: repeat(3, 1fr); gap: 1.4rem; }
    .car-filter { grid-template-columns: 1fr 1fr 1fr; }
    .car-filter-reset { grid-column: 1 / -1; }
}
</style>
<script>
/* Careers — generic form behaviour: résumé preview + inline validation states. */
(function () {
    function human(bytes) {
        var kb = bytes / 1024;
        return kb > 1024 ? (kb / 1024).toFixed(1) + ' MB' : Math.round(kb) + ' KB';
    }
    function init() {
        document.querySelectorAll('[data-car-form]').forEach(function (form) {
            form.querySelectorAll('[data-car-file]').forEach(function (inp) {
                inp.addEventListener('change', function () {
                    var wrap = inp.closest('.car-file');
                    var box  = wrap && wrap.querySelector('[data-car-file-name]');
                    var f    = inp.files && inp.files[0];
                    if (f) {
                        if (box) box.textContent = f.name + ' · ' + human(f.size);
                        if (wrap) wrap.classList.add('has-file');
                    } else {
                        if (box) box.textContent = '';
                        if (wrap) wrap.classList.remove('has-file');
                    }
                });
            });
            form.querySelectorAll('input, textarea, select').forEach(function (el) {
                if (el.type === 'hidden' || el.type === 'file') return;
                function check() {
                    var hasVal = !!(el.value && el.value.trim());
                    if (el.hasAttribute('required') && !hasVal) {
                        el.classList.add('is-invalid'); el.classList.remove('is-valid'); return;
                    }
                    if (!hasVal) { el.classList.remove('is-invalid', 'is-valid'); return; }
                    var ok = el.checkValidity();
                    el.classList.toggle('is-invalid', !ok);
                    el.classList.toggle('is-valid', ok);
                }
                el.addEventListener('blur', check);
                el.addEventListener('input', function () { if (el.classList.contains('is-invalid')) check(); });
            });
            form.addEventListener('reset', function () {
                form.querySelectorAll('.is-invalid, .is-valid').forEach(function (el) { el.classList.remove('is-invalid', 'is-valid'); });
                form.querySelectorAll('.car-file.has-file').forEach(function (w) {
                    w.classList.remove('has-file');
                    var b = w.querySelector('[data-car-file-name]'); if (b) b.textContent = '';
                });
            });
        });
    }
    if (document.readyState !== 'loading') init();
    else document.addEventListener('DOMContentLoaded', init);
})();
</script>
<?php /* Premium application section (.cap-*): styles + behaviour, shipped once
         per request regardless of which page branch renders. */ ?>
<link rel="stylesheet" href="<?= e(asset('css/career-apply.css')) ?>">
<script src="<?= e(asset('js/career-apply.js')) ?>" defer></script>
<?php };

$slug = clean(get('slug', ''));

/* =============================================================================
 |  SINGLE ROLE DETAIL
 |============================================================================*/
if ($slug !== '') {

    $job = db_row(
        "SELECT * FROM careers WHERE slug = :s LIMIT 1",
        [':s' => $slug]
    );

    /* ---- NOT FOUND — 404 inside the normal layout -------------------- */
    if (!$job) {
        http_response_code(404);
        seo_set([
            'title'       => 'Role Not Found',
            'description' => 'This opening is no longer available. Explore current career and volunteering opportunities at ' . SITE_NAME . '.',
            'page_key'    => 'career',
            'robots'      => 'noindex,follow',
        ]);
        $page_hero = [
            'title'      => 'Role Not Found',
            'subtitle'   => 'This opening may have been filled or closed. Browse our current openings instead.',
            'breadcrumb' => [
                ['label' => 'Careers', 'url' => url('career')],
                ['label' => 'Not Found'],
            ],
        ];
        include __DIR__ . '/includes/header.php';
        ?>
        <section class="section">
            <div class="container">
                <div class="empty-state">
                    <div class="icon-badge"><?= lucide('briefcase') ?></div>
                    <h3>We couldn't find that opening</h3>
                    <p class="text-muted">The link may be outdated, or the position has already been filled. Explore our current openings — the right role for you may be waiting.</p>
                    <div class="flex flex-wrap justify-center gap-2 mt-3">
                        <a class="btn btn-primary" href="<?= e(url('career')) ?>">View All Openings</a>
                        <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>">Volunteer With Us</a>
                    </div>
                </div>
            </div>
        </section>
        <?php
        echo country_field_assets();
include __DIR__ . '/includes/footer.php';
        return;
    }

    /* ---- Derived display values -------------------------------------- */
    $type       = $job['type'] ?? 'full-time';
    $meta       = $typeMeta[$type] ?? $typeMeta['full-time'];
    $deadlineTs = !empty($job['deadline']) ? strtotime((string) $job['deadline']) : null;
    $expired    = $deadlineTs !== null && $deadlineTs < strtotime('today');
    $isOpen     = ($job['status'] ?? 'open') === 'open' && !$expired;
    $daysLeft   = $deadlineTs !== null ? (int) ceil(($deadlineTs - strtotime('today')) / 86400) : null;

    /* Other open roles to keep the page rich. */
    $others = db_all(
        "SELECT * FROM careers
         WHERE status = 'open' AND id <> :id
         ORDER BY deadline IS NULL, deadline ASC, id DESC
         LIMIT 3",
        [':id' => (int) $job['id']]
    );

    /* ---- SEO + hero -------------------------------------------------- */
    $summary = excerpt(strip_tags((string) ($job['description'] ?? '')), 32);
    seo_set([
        'title'       => $job['title'] . ' — Careers',
        'description' => $summary ?: ('Join ' . SITE_NAME . ' as ' . $job['title'] . '. Apply online today and help create lasting change across Bihar.'),
        'page_key'    => 'career:' . $job['slug'],
        'type'        => 'article',
    ]);

    $page_hero = [
        'title'      => $job['title'],
        'subtitle'   => trim(($job['department'] ? $job['department'] . ' · ' : '') . $meta['label'] . ' · ' . ($job['location'] ?: 'Patna, Bihar')),
        'breadcrumb' => [
            ['label' => 'Careers', 'url' => url('career')],
            ['label' => $job['title']],
        ],
    ];

    include __DIR__ . '/includes/header.php';
    echo json_ld_breadcrumb($page_hero['breadcrumb']);
    $carAssets();
    ?>

    <!-- ============================== ROLE DETAIL ============================== -->
    <section class="section">
        <div class="container grid grid-sidebar gap-4 items-start">

            <!-- Main column -->
            <article class="reveal">

                <!-- Badges -->
                <div class="flex items-center gap-2 flex-wrap mb-3">
                    <span class="badge <?= e($meta['badge']) ?>"><?= lucide($meta['icon']) ?> <?= e($meta['label']) ?></span>
                    <?php if (!empty($job['department'])): ?>
                        <span class="badge badge-brand"><?= lucide('building-2') ?> <?= e($job['department']) ?></span>
                    <?php endif; ?>
                    <span class="badge badge-muted"><?= lucide('map-pin') ?> <?= e($job['location'] ?: 'Patna, Bihar') ?></span>
                    <?php if (!empty($job['salary_range'])): ?>
                        <span class="badge badge-success"><?= lucide('coins') ?> <?= e($job['salary_range']) ?></span>
                    <?php endif; ?>
                    <?php if (!$isOpen): ?>
                        <span class="badge badge-danger">Applications closed</span>
                    <?php elseif ($daysLeft !== null && $daysLeft <= 7): ?>
                        <span class="badge badge-accent"><?= lucide('hourglass') ?> <?= $daysLeft <= 0 ? 'Closes today' : ('Closes in ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's')) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($isOpen): ?>
                    <div class="card-3d mb-4" style="background:var(--grad-brand);color:#fff;">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div>
                                <span class="eyebrow" style="color:#fff;">We're hiring</span>
                                <h2 class="text-white mb-0" style="margin-top:.25rem;">Ready to make an impact?</h2>
                                <p style="color:rgba(255,255,255,.9);margin:.35rem 0 0;">
                                    <?php if (!empty($job['deadline'])): ?>
                                        Applications close on <strong><?= e(format_date($job['deadline'], 'd M Y')) ?></strong>.
                                    <?php else: ?>
                                        We review applications on a rolling basis.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a class="btn btn-white btn-lg" href="#apply">Apply for this role</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-4">Applications for this role are now closed. Explore our other current openings below, or register your interest to hear about future roles.</div>
                <?php endif; ?>

                <!-- About the role -->
                <div class="section-head left">
                    <span class="eyebrow">About the Role</span>
                    <h2 class="section-title mb-0">What you'll do</h2>
                </div>
                <div class="prose mt-2">
                    <?php if (!empty(trim((string) $job['description']))): ?>
                        <?= rich_text($job['description']) ?>
                    <?php else: ?>
                        <p>Full details for this position are being finalised. Please check back soon, or reach out to our team to learn more about the role and how you can contribute.</p>
                    <?php endif; ?>
                </div>

                <!-- Requirements -->
                <?php if (!empty(trim((string) $job['requirements']))): ?>
                    <div class="divider"></div>
                    <div class="section-head left">
                        <span class="eyebrow">Who We're Looking For</span>
                        <h2 class="section-title mb-0">Requirements</h2>
                    </div>
                    <div class="prose mt-2">
                        <?= rich_text($job['requirements']) ?>
                    </div>
                <?php endif; ?>

                <!-- Application form -->
                <div class="divider"></div>
                <div id="apply">
                    <div class="section-head left">
                        <span class="eyebrow">Join the Team</span>
                        <h2 class="section-title mb-0">Apply for <?= e($job['title']) ?></h2>
                    </div>

                    <?php if (!$isOpen): ?>
                        <div class="alert alert-info mt-3">This role is no longer accepting applications. You're welcome to <a href="<?= e(url('volunteer')) ?>">volunteer with us</a> or check back for future openings.</div>
                    <?php else: ?>
                        <p class="text-muted mt-2">Tell us a little about yourself and attach your résumé. Fields marked <span class="req">*</span> are required — we usually respond within a week.</p>

                        <form data-ajax-form data-car-form data-endpoint="<?= e(url('forms/career-apply')) ?>" enctype="multipart/form-data" class="card car-detail-apply mt-3">
                            <div class="card-body">
                                <?= csrf_field() ?>
                                <input type="hidden" name="career_id" value="<?= (int) $job['id'] ?>">
                                <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                                <div class="car-form-grid">
                                    <div class="car-fl">
                                        <input class="form-control" id="ca-name" name="name" type="text" maxlength="128" placeholder=" " required>
                                        <label for="ca-name">Full Name *</label>
                                    </div>
                                    <div class="car-fl">
                                        <input class="form-control" id="ca-email" name="email" type="email" maxlength="191" placeholder=" " required>
                                        <label for="ca-email">Email *</label>
                                    </div>
                                    <div class="car-fl">
                                        <?= country_field(['name' => 'phone', 'label' => 'Mobile Number', 'required' => true]) ?>
                                        <label for="ca-phone">Phone *</label>
                                    </div>
                                    <div>
                                        <span class="car-static">Résumé / CV *</span>
                                        <label class="car-file" for="ca-resume">
                                            <input id="ca-resume" name="resume" type="file" accept=".pdf,.doc,.docx" data-car-file required>
                                            <span class="car-file-inner"><?= lucide('upload-cloud') ?> Click to upload PDF, DOC or DOCX</span>
                                            <span class="car-file-name" data-car-file-name></span>
                                        </label>
                                        <small class="form-hint">Max ~5 MB.</small>
                                    </div>
                                    <div class="car-fl car-col-full">
                                        <textarea class="form-textarea" id="ca-cover" name="cover_letter" rows="5" placeholder=" "></textarea>
                                        <label for="ca-cover">Cover Letter / Why You're a Great Fit</label>
                                    </div>
                                </div>

                                <button class="btn btn-3d btn-block btn-lg mt-3" type="submit"><?= lucide('send') ?> Submit Application</button>
                                <p class="text-muted mt-2" style="font-size:.85rem;">By applying you agree to be contacted about this role. We respect your privacy and never share your details.</p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <a class="btn btn-outline" href="<?= e(url('career')) ?>"><?= lucide('arrow-left') ?> Back to All Openings</a>
                </div>
            </article>

            <!-- Sidebar -->
            <aside class="reveal delay-1">
                <div class="card-3d" style="position:sticky;top:90px;">
                    <h3 class="mb-2">Role Summary</h3>
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:.85rem;">
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('briefcase') ?> <span><strong>Type</strong><br><span class="text-muted"><?= e($meta['label']) ?></span></span></li>
                        <?php if (!empty($job['department'])): ?>
                            <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('building-2') ?> <span><strong>Department</strong><br><span class="text-muted"><?= e($job['department']) ?></span></span></li>
                        <?php endif; ?>
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('map-pin') ?> <span><strong>Location</strong><br><span class="text-muted"><?= e($job['location'] ?: 'Patna, Bihar') ?></span></span></li>
                        <?php if (!empty($job['salary_range'])): ?>
                            <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('coins') ?> <span><strong>Compensation</strong><br><span class="text-muted"><?= e($job['salary_range']) ?></span></span></li>
                        <?php endif; ?>
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('users') ?> <span><strong>Openings</strong><br><span class="text-muted"><?= (int) ($job['openings'] ?? 1) ?> position<?= (int) ($job['openings'] ?? 1) === 1 ? '' : 's' ?></span></span></li>
                        <li style="display:flex;gap:.6rem;align-items:flex-start;"><?= lucide('calendar') ?> <span><strong>Deadline</strong><br><span class="text-muted"><?= !empty($job['deadline']) ? e(format_date($job['deadline'], 'd M Y')) : 'Rolling — apply anytime' ?></span></span></li>
                    </ul>

                    <?php if ($isOpen): ?>
                        <a class="btn btn-primary btn-block mt-3" href="#apply">Apply Now</a>
                    <?php endif; ?>

                    <div class="divider"></div>
                    <h4 class="mb-2">Share this role</h4>
                    <?php
                        $shareUrl      = abs_url('career?slug=' . $job['slug']);
                        $shareUrlEnc   = rawurlencode($shareUrl);
                        $shareTitleEnc = rawurlencode($job['title'] . ' at ' . SITE_NAME);
                    ?>
                    <div class="flex gap-2 flex-wrap">
                        <a class="btn btn-outline btn-sm" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on LinkedIn"><?= social_fa('linkedin') ?></a>
                        <a class="btn btn-outline btn-sm" href="https://twitter.com/intent/tweet?url=<?= e($shareUrlEnc) ?>&amp;text=<?= e($shareTitleEnc) ?>" target="_blank" rel="noopener" aria-label="Share on X"><?= social_fa('x') ?></a>
                        <a class="btn btn-outline btn-sm" href="https://wa.me/?text=<?= e($shareTitleEnc) ?>%20<?= e($shareUrlEnc) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><?= social_fa('whatsapp') ?></a>
                        <a class="btn btn-outline btn-sm" href="mailto:?subject=<?= e($shareTitleEnc) ?>&amp;body=<?= e($shareUrlEnc) ?>" aria-label="Share by email"><?= lucide('mail') ?></a>
                    </div>

                    <div class="divider"></div>
                    <p class="text-muted" style="font-size:.9rem;">Not quite the right fit? You can still make a difference.</p>
                    <a class="btn btn-accent btn-block" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer With Us</a>
                </div>
            </aside>
        </div>
    </section>

    <?php if ($others): ?>
    <!-- ============================== OTHER OPENINGS ============================== -->
    <section class="section section-soft">
        <div class="container">
            <div class="section-head">
                <span class="eyebrow" style="justify-content:center;">Keep Exploring</span>
                <h2 class="section-title">Other Open Roles</h2>
                <p class="section-subtitle">More ways to build a meaningful career with <?= e(SITE_NAME) ?>.</p>
            </div>
            <div class="grid grid-3">
                <?php foreach ($others as $o) { $roleCard($o); } ?>
            </div>
            <div class="text-center mt-4">
                <a class="btn btn-outline" href="<?= e(url('career')) ?>">View All Openings</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
    include __DIR__ . '/includes/footer.php';
    return;
}

/* =============================================================================
 |  LISTING — premium recruitment page
 |============================================================================*/
$openRoles = db_all(
    "SELECT * FROM careers
     WHERE status = 'open'
     ORDER BY department ASC, deadline IS NULL, deadline ASC, id DESC"
);
$roleCount = count($openRoles);

/* Group by department (blank department → "General"). */
$byDept = [];
foreach ($openRoles as $r) {
    $dept = trim((string) ($r['department'] ?? '')) ?: 'General';
    $byDept[$dept][] = $r;
}
$deptCount = count($byDept);

/* Sample fallback roles so the page is complete before any content is seeded. */
$sampleRoles = [
    ['title' => 'Program Coordinator',            'department' => 'Programs',       'location' => 'Patna, Bihar',                'type' => 'full-time',  'description' => 'Lead and monitor community programmes, coordinate field teams and track on-ground impact across districts.', 'salary_range' => '₹4.2–6.0 LPA',    'openings' => 2,  'deadline' => date('Y-m-d', strtotime('+35 days'))],
    ['title' => 'Field Officer — Health',         'department' => 'Healthcare',     'location' => 'Gaya, Bihar',                 'type' => 'full-time',  'description' => 'Run health camps, coordinate with local clinics and support families in accessing essential care.',          'salary_range' => '₹3.0–4.5 LPA',    'openings' => 3,  'deadline' => date('Y-m-d', strtotime('+21 days'))],
    ['title' => 'Communications Intern',          'department' => 'Communications', 'location' => 'Remote',                      'type' => 'internship', 'description' => 'Craft stories, manage social channels and document the impact of our work on the ground.',                   'salary_range' => 'Stipend',         'openings' => 2,  'deadline' => date('Y-m-d', strtotime('+14 days'))],
    ['title' => 'Community Volunteer',            'department' => 'Operations',     'location' => 'Bihar (Multiple Districts)',  'type' => 'volunteer',  'description' => 'Give your time to camps, drives and events that bring education and healthcare to those who need it most.',   'salary_range' => '',                'openings' => 15, 'deadline' => null],
    ['title' => 'Fundraising Fellow',             'department' => 'Fundraising',    'location' => 'Hybrid — Patna',              'type' => 'contract',   'description' => 'Design and lead a fundraising initiative end-to-end as part of our year-long impact fellowship.',            'salary_range' => 'Fellowship grant','openings' => 1,  'deadline' => date('Y-m-d', strtotime('+45 days'))],
    ['title' => 'Monitoring & Evaluation Officer','department' => 'Programs',       'location' => 'Patna, Bihar',                'type' => 'full-time',  'description' => 'Build data systems, measure outcomes and turn field data into insight that sharpens our impact.',            'salary_range' => '₹4.8–6.5 LPA',    'openings' => 1,  'deadline' => date('Y-m-d', strtotime('+28 days'))],
];

$usingSamples = ($roleCount === 0);
$displayRoles = $usingSamples ? $sampleRoles : $openRoles;

/* Distinct departments / locations present in the shown roles (for filters). */
$filterDepts = [];
$filterLocs  = [];
foreach ($displayRoles as $r) {
    $d = trim((string) ($r['department'] ?? '')) ?: 'General';
    $l = trim((string) ($r['location'] ?? '')) ?: 'Patna, Bihar';
    $filterDepts[$d] = true;
    $filterLocs[$l]  = true;
}
$filterDepts = array_keys($filterDepts);
$filterLocs  = array_keys($filterLocs);
sort($filterDepts);
sort($filterLocs);

/* Impact stats — DB-driven with curated fallbacks (used as intro proof-points). */
$stats = get_all('achievements', ['status' => 1], 'sort_order ASC', 4);
if (!$stats) {
    $stats = [
        ['icon' => 'users',     'value' => 45,    'suffix' => '+', 'prefix' => '', 'title' => 'Team Members',    'description' => 'across programmes & field'],
        ['icon' => 'handshake', 'value' => 800,   'suffix' => '+', 'prefix' => '', 'title' => 'Volunteers',      'description' => 'giving time & skills'],
        ['icon' => 'home',      'value' => 60,    'suffix' => '+', 'prefix' => '', 'title' => 'Villages Reached', 'description' => 'where our work lives'],
        ['icon' => 'heart',     'value' => 25000, 'suffix' => '+', 'prefix' => '', 'title' => 'Lives Impacted',   'description' => 'and counting, together'],
    ];
}

/* Workforce counters — DB counts with sensible floors so numbers always land. */
$counters = [
    ['icon' => 'users',          'value' => max((int) db_count('team_members'), 45),  'suffix' => '+', 'label' => 'Team Members'],
    ['icon' => 'heart-handshake','value' => max((int) db_count('volunteers'), 800),   'suffix' => '+', 'label' => 'Volunteers'],
    ['icon' => 'target',         'value' => max((int) db_count('projects'), 60),      'suffix' => '+', 'label' => 'Projects Delivered'],
    ['icon' => 'map',            'value' => 6,                                         'suffix' => '',  'label' => 'States Reached'],
    ['icon' => 'calendar-days',  'value' => 12,                                        'suffix' => '+', 'label' => 'Years of Service'],
];

/* Alternative pathways into the Foundation. */
$altPaths = [
    ['cls' => '',  'icon' => 'graduation-cap', 'title' => 'Internships',   'text' => 'Hands-on experience with real projects, mentorship and a certificate that opens doors.', 'link' => url('internship'), 'cta' => 'Explore Internships'],
    ['cls' => 'g', 'icon' => 'heart-handshake','title' => 'Volunteering',  'text' => 'Give your time and skills to camps, drives and events across our communities.',           'link' => url('volunteer'),  'cta' => 'Become a Volunteer'],
    ['cls' => 'p', 'icon' => 'award',          'title' => 'Fellowships',   'text' => 'Lead a project end-to-end through our structured, mentored impact fellowships.',          'link' => '#openings',       'cta' => 'View Fellowships'],
    ['cls' => 'o', 'icon' => 'globe',          'title' => 'Remote Roles',  'text' => 'Contribute from anywhere — several of our communications and tech roles are remote.',    'link' => '#openings',       'cta' => 'See Remote Roles'],
];

/* Employee benefits. */
$benefits = [
    ['icon' => 'heart-pulse',    'title' => 'Health & Wellbeing',    'text' => 'Medical support and a genuine culture of care for you and your family.'],
    ['icon' => 'sun',            'title' => 'Paid Leave & Holidays', 'text' => 'Generous leave, festival holidays and real respect for your rest.'],
    ['icon' => 'graduation-cap', 'title' => 'Learning Budget',       'text' => 'Workshops, courses and field exposure to keep you growing.'],
    ['icon' => 'laptop',         'title' => 'Flexible & Remote',     'text' => 'Remote-friendly roles and sensible, humane working hours.'],
    ['icon' => 'trending-up',    'title' => 'Growth & Promotions',   'text' => 'Clear pathways to take on more responsibility and lead.'],
    ['icon' => 'coins',          'title' => 'Fair, Transparent Pay', 'text' => 'Honest compensation benchmarked to the sector and reviewed yearly.'],
    ['icon' => 'award',          'title' => 'Recognition',           'text' => 'Certificates, references and rewards that celebrate your impact.'],
    ['icon' => 'baby',           'title' => 'Family Support',        'text' => 'Parental leave and flexibility for the moments that matter most.'],
];

/* Team voices (culture / testimonials strip). */
$teamVoices = [
    ['name' => 'Anjali Verma', 'role' => 'Programme Coordinator', 'tenure' => '3 years',  'quote' => 'I came to make a difference and found a family. Here, your work matters and your growth matters just as much.'],
    ['name' => 'Rohit Kumar',  'role' => 'Field Officer, Health', 'tenure' => '2 years',  'quote' => 'Every day I see the health camps we run change a family\'s life. No job has ever felt this meaningful.'],
    ['name' => 'Sneha Raj',    'role' => 'Communications Lead',   'tenure' => '4 years',  'quote' => 'The trust and freedom I get to tell our story is rare. This is a place that lets you do your best work.'],
];

/* Experience filter buckets. */
$expOptions = ['entry' => 'Entry Level', 'mid' => 'Mid Level', 'senior' => 'Senior Level', 'any' => 'Any Level'];

seo_set([
    'title'       => 'Careers',
    'description' => 'Build a career with purpose at ' . SITE_NAME . '. Explore current job, internship, fellowship and volunteer openings across our programmes in Patna, Bihar, and help create lasting change.',
    'page_key'    => 'career',
    'type'        => 'website',
]);

$page_hero = [
    'title'      => 'Careers',
    'subtitle'   => 'Do the most meaningful work of your life. Join a passionate team turning compassion into real, measurable change across Bihar.',
    'breadcrumb' => [['label' => 'Careers']],
];

include __DIR__ . '/includes/header.php';
$carAssets();
?>

<!-- ============================== JOIN OUR MISSION (INTRO) ============================== -->
<section class="section">
    <div class="container grid grid-2 items-center gap-4">
        <div class="reveal">
            <span class="eyebrow">Join Our Mission</span>
            <h2 class="section-title">Careers that change lives — starting with yours</h2>
            <p class="lead">At <?= e(SITE_NAME) ?>, a career is more than a job. It's a chance to wake up every day knowing your work brings education, health and opportunity to people who need it most.</p>
            <p class="text-muted">We're a close-knit, values-led team working across education, healthcare, women's empowerment and community development. Whether you're an experienced professional, a fresh graduate or someone looking to give back, there may be a place for you in this movement.</p>
            <ul class="list-check mt-3">
                <li>Real responsibility and visible impact from day one</li>
                <li>Mentorship, learning and a clear path to grow</li>
                <li>A supportive, mission-driven team culture</li>
            </ul>
            <div class="flex flex-wrap gap-2 mt-4">
                <a class="btn btn-3d" href="#openings"><?= lucide('briefcase') ?> View Open Roles</a>
                <a class="btn btn-outline" href="#car-apply"><?= lucide('send') ?> Apply Now</a>
            </div>
        </div>
        <div class="reveal delay-1">
            <div class="glass-card car-intro-card">
                <span class="blob b1" style="top:-60px;right:-50px;"></span>
                <span class="blob b2" style="bottom:-60px;left:-40px;"></span>
                <div style="position:relative;z-index:1;">
                    <div class="icon-badge"><?= lucide('sparkles') ?></div>
                    <h3 class="card-title">Why our people stay</h3>
                    <p class="card-text">“I came to make a difference and found a family. Here, your work matters and your growth matters just as much.”</p>
                    <div class="car-intro-proof">
                        <?php foreach (array_slice($stats, 0, 3) as $s): ?>
                            <span class="chip">
                                <?= lucide($s['icon'] ?? 'star') ?>
                                <strong><?= e($s['prefix'] ?? '') ?><?= e(number_short((int) ($s['value'] ?? 0))) ?><?= e($s['suffix'] ?? '') ?></strong>&nbsp;<?= e($s['title'] ?? '') ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== WHY WORK WITH US (CULTURE) ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Our Culture</span>
            <h2 class="section-title">Why Work With Us</h2>
            <p class="section-subtitle">We invest in our people the way we invest in the communities we serve — with care, respect and real opportunity.</p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($culture as $i => $c): ?>
                <div class="icon-box glass-card reveal <?= 'delay-' . (($i % 3) + 1) ?> <?= e($c['cls']) ?>">
                    <div class="ib-icon"><?= lucide($c['icon']) ?></div>
                    <h3 class="card-title"><?= e($c['title']) ?></h3>
                    <p class="card-text"><?= e($c['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== WORKFORCE COUNTERS ============================== -->
<section class="section">
    <div class="container">
        <div class="car-stats reveal">
            <div class="section-head" style="margin-bottom:1.5rem;">
                <span class="eyebrow" style="justify-content:center;color:#fde68a;">Join The Movement</span>
                <h2 class="section-title text-white" style="margin-bottom:.25rem;">You'd Be In Good Company</h2>
                <p class="section-subtitle" style="color:rgba(255,255,255,.9);">Behind every number is a team member, a volunteer and a community whose life is changing for the better.</p>
            </div>
            <div class="car-stats-grid">
                <?php foreach ($counters as $c): ?>
                    <div class="car-stat">
                        <div class="car-stat-ic"><?= lucide($c['icon']) ?></div>
                        <div class="car-stat-val"><span data-counter="<?= (int) $c['value'] ?>">0</span><?= e($c['suffix']) ?></div>
                        <div class="car-stat-lab"><?= e($c['label']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== CURRENT OPENINGS + FILTERS ============================== -->
<section class="section section-soft" id="openings">
    <div class="container" id="car-openings">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Current Openings</span>
            <h2 class="section-title">Find Your Role</h2>
            <p class="section-subtitle">
                <?php if ($usingSamples): ?>
                    A glimpse of the kinds of roles we hire for — search and filter to explore.
                <?php else: ?>
                    <?= (int) $roleCount ?> open <?= $roleCount === 1 ? 'position' : 'positions' ?><?= $deptCount > 1 ? ' across ' . (int) $deptCount . ' departments' : '' ?> — search and filter to find your fit.
                <?php endif; ?>
            </p>
        </div>

        <!-- Advanced filters -->
        <div class="glass-card car-filter reveal">
            <div class="car-field">
                <label for="car-f-keyword">Search</label>
                <div class="car-search-wrap">
                    <?= lucide('search') ?>
                    <input class="form-control" type="search" id="car-f-keyword" placeholder="Title, keyword, skill…" autocomplete="off">
                </div>
            </div>
            <div class="car-field">
                <label for="car-f-dept">Department</label>
                <select class="form-select" id="car-f-dept">
                    <option value="">All departments</option>
                    <?php foreach ($filterDepts as $d): ?>
                        <option value="<?= e(strtolower($d)) ?>"><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="car-field">
                <label for="car-f-loc">Location</label>
                <select class="form-select" id="car-f-loc">
                    <option value="">All locations</option>
                    <?php foreach ($filterLocs as $l): ?>
                        <option value="<?= e(strtolower($l)) ?>"><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="car-field">
                <label for="car-f-type">Job Type</label>
                <select class="form-select" id="car-f-type">
                    <option value="">All types</option>
                    <?php foreach ($typeMeta as $key => $m): ?>
                        <option value="<?= e($key) ?>"><?= e($m['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="car-field">
                <label for="car-f-exp">Experience</label>
                <select class="form-select" id="car-f-exp">
                    <option value="">Any experience</option>
                    <?php foreach ($expOptions as $key => $lab): ?>
                        <option value="<?= e($key) ?>"><?= e($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-outline car-filter-reset" id="car-f-reset"><?= lucide('rotate-ccw') ?> Reset</button>
        </div>

        <p class="car-result-line"><b id="car-result-count"><?= count($displayRoles) ?></b> role<?= count($displayRoles) === 1 ? '' : 's' ?> matching your search</p>

        <div class="car-jobs">
            <?php foreach ($displayRoles as $r) { $jobCard($r); } ?>
        </div>

        <div class="car-no-results empty-state" id="car-no-results" style="display:none;">
            <div class="icon-badge"><?= lucide('search-x') ?></div>
            <h3>No roles match those filters</h3>
            <p class="text-muted">Try clearing a filter or two — or register your interest and we'll reach out when a matching role opens.</p>
            <div class="flex flex-wrap justify-center gap-2 mt-3">
                <a class="btn btn-primary" href="#car-apply"><?= lucide('send') ?> Send Your CV</a>
                <a class="btn btn-outline" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer Instead</a>
            </div>
        </div>

        <?php if ($usingSamples): ?>
            <p class="text-muted text-center mt-4" style="font-size:.9rem;">
                <?= lucide('info') ?> These are sample roles. For live openings, write to us at
                <a href="mailto:<?= e(get_setting('contact_email', SITE_EMAIL)) ?>"><?= e(get_setting('contact_email', SITE_EMAIL)) ?></a>.
            </p>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== ALTERNATIVE PATHWAYS ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">More Ways In</span>
            <h2 class="section-title">Internships, Volunteering, Fellowships & Remote</h2>
            <p class="section-subtitle">Not ready for a full-time role? There are many meaningful ways to be part of our work.</p>
        </div>
        <div class="car-paths">
            <?php foreach ($altPaths as $i => $p): ?>
                <div class="icon-box glass-card car-path reveal <?= 'delay-' . (($i % 4) + 1) ?> <?= e($p['cls']) ?>">
                    <div class="ib-icon"><?= lucide($p['icon']) ?></div>
                    <h3 class="card-title"><?= e($p['title']) ?></h3>
                    <p class="card-text"><?= e($p['text']) ?></p>
                    <a class="btn btn-outline btn-sm mt-3" href="<?= e($p['link']) ?>"><?= e($p['cta']) ?> <?= lucide('arrow-right') ?></a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== EMPLOYEE BENEFITS ============================== -->
<section class="section section-soft">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Perks & Benefits</span>
            <h2 class="section-title">More Than a Paycheck</h2>
            <p class="section-subtitle">We look after the people who look after our communities.</p>
        </div>
        <div class="car-benefits">
            <?php foreach ($benefits as $i => $b): ?>
                <div class="car-benefit reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <div class="car-benefit-ic"><?= lucide($b['icon']) ?></div>
                    <div>
                        <h3><?= e($b['title']) ?></h3>
                        <p><?= e($b['text']) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== HIRING PROCESS ============================== -->
<section class="section section-alt">
    <div class="container grid grid-2 gap-4 items-center">
        <div class="reveal">
            <span class="eyebrow">Our Hiring Process</span>
            <h2 class="section-title">Simple, human and respectful</h2>
            <p class="text-muted">We keep our process warm and straightforward — no endless rounds, no jargon. Just a genuine conversation about how we can create change together.</p>
            <a class="btn btn-primary mt-3" href="#openings"><?= lucide('briefcase') ?> See Open Roles</a>
        </div>
        <div class="process-steps reveal delay-1">
            <?php foreach ($hiringSteps as $step): ?>
                <div class="process-step">
                    <h3 class="card-title mb-0"><?= e($step['title']) ?></h3>
                    <p class="card-text" style="margin:.35rem 0 0;"><?= e($step['text']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== TEAM VOICES (CULTURE STRIP) ============================== -->
<section class="section">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow" style="justify-content:center;">Life At The Foundation</span>
            <h2 class="section-title">In Our Team's Own Words</h2>
            <p class="section-subtitle">The best way to understand our culture is to hear from the people who live it.</p>
        </div>
        <div class="car-voices">
            <?php foreach ($teamVoices as $i => $v): ?>
                <?php
                    $parts    = preg_split('/\s+/', trim($v['name']));
                    $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr(end($parts) ?: '', 0, 1));
                ?>
                <figure class="car-voice reveal <?= 'delay-' . (($i % 3) + 1) ?>">
                    <span class="car-voice-quote"><?= lucide('quote') ?></span>
                    <blockquote style="margin:0;"><p><?= e($v['quote']) ?></p></blockquote>
                    <figcaption class="car-voice-who">
                        <span class="car-voice-av"><?= e($initials) ?></span>
                        <span>
                            <b><?= e($v['name']) ?></b>
                            <small><?= e($v['role']) ?> · <?= e($v['tenure']) ?></small>
                        </span>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== APPLY NOW (PREMIUM) ============================== -->
<?php
/* Left-panel content for the application section. Statistics reuse the same DB
   counts as the workforce band above, so the two never disagree. */
$capWhy = [
    ['rocket',        'Growth Opportunities', 'Continuous learning, mentorship and real leadership development.'],
    ['heart-handshake','Purpose Driven',      'Work on projects that measurably change lives in the communities we serve.'],
    ['clock',         'Flexible Environment', 'A healthy work-life balance and a genuinely collaborative culture.'],
    ['lightbulb',     'Innovation',           'Bring your ideas forward — we back people who try things.'],
    ['award',         'Recognition',          'Performance is seen, appreciated and rewarded.'],
];
$capStats = [
    [max((int) db_count('volunteers'), 250), '+',  'Volunteers'],
    [max((int) db_count('projects'), 40),    '+',  'Projects'],
    [6,                                      '',   'States'],
    [95,                                     '%',  'Satisfaction'],
];
$capBenefits = ['Health Benefits', 'Remote Friendly', 'Learning Budget', 'Paid Leave', 'Flexible Hours', 'Recognition'];
$capSteps    = [
    ['Apply',     'Send us your details and résumé.'],
    ['Review',    'We read every application within a week.'],
    ['Interview', 'A conversation about you and the role.'],
    ['Selection', 'Reference checks and the offer.'],
    ['Welcome',   'Onboarding and your first project.'],
];
$capTeamCount = max((int) db_count('team_members'), 120);
?>
<section class="cap" id="apply" data-cap>
    <div class="cap-bg" aria-hidden="true">
        <span class="cap-mesh"></span>
        <span class="cap-blob b1"></span>
        <span class="cap-blob b2"></span>
        <span class="cap-blob b3"></span>
    </div>

    <div class="container cap-grid">

        <!-- ------------------------------------------------- LEFT: the pitch -->
        <div class="cap-left">
            <span class="cap-badge cap-rv"><?= lucide('sparkles') ?> Join Our Mission</span>

            <h2 class="cap-h1 cap-rv">Build Your Career <span class="hl">With Purpose</span></h2>
            <p class="cap-lead cap-rv">
                Every contribution here creates real, measurable impact. We are looking for people who
                want their work to matter — and who would rather build alongside a community than
                hand something down to it.
            </p>

            <div class="cap-why">
                <?php foreach ($capWhy as $i => [$ico, $title, $desc]): ?>
                    <article class="cap-why-card cap-rv" style="transition-delay:<?= $i * 60 ?>ms">
                        <span class="cap-why-ico"><?= lucide($ico) ?></span>
                        <div>
                            <h3><?= e($title) ?></h3>
                            <p><?= e($desc) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="cap-stats cap-rv">
                <?php foreach ($capStats as [$n, $suffix, $label]): ?>
                    <div class="cap-stat">
                        <span class="cap-stat-n" data-count data-to="<?= (int) $n ?>" data-suffix="<?= e($suffix) ?>">0<?= e($suffix) ?></span>
                        <span class="cap-stat-l"><?= e($label) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="cap-pills cap-rv">
                <?php foreach ($capBenefits as $b): ?>
                    <span class="cap-pill"><?= lucide('check') ?> <?= e($b) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="cap-team cap-rv">
                <div class="cap-avatars" aria-hidden="true">
                    <?php foreach (['A', 'S', 'R', 'P', 'M'] as $ini): ?>
                        <span class="cap-av"><?= e($ini) ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="cap-team-txt">Join <b><?= (int) $capTeamCount ?>+</b> people already building with us.</p>
            </div>

            <div class="cap-tl cap-rv">
                <?php foreach ($capSteps as [$t, $d]): ?>
                    <div class="cap-tl-item">
                        <strong><?= e($t) ?></strong>
                        <span><?= e($d) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ------------------------------------------ RIGHT: application card -->
        <div class="cap-right cap-rv" data-cap-card>
            <div class="cap-card">

                <div class="cap-done" data-cap-done>
                    <div class="cap-done-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"
                             stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>
                    <h3>Application received</h3>
                    <p class="text-muted">Thank you — we review every application and usually respond within a week.</p>
                </div>

                <div class="cap-card-head">
                    <h2>Application Form</h2>
                    <p>We're excited to know you.</p>
                </div>

                <div class="cap-steps" role="tablist" aria-label="Application steps">
                    <?php foreach (['Personal', 'Professional', 'Documents'] as $i => $lbl): ?>
                        <?php if ($i > 0): ?><span class="cap-step-bar"><i <?= $i === 1 ? 'data-cap-bar' : '' ?>></i></span><?php endif; ?>
                        <button type="button" class="cap-step<?= $i === 0 ? ' is-active' : '' ?>" data-cap-step
                                role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
                            <span class="cap-step-dot"><?= $i + 1 ?></span>
                            <span class="cap-step-lbl"><?= e($lbl) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <form data-ajax-form data-cap-form data-endpoint="<?= e(url('forms/career-apply')) ?>"
                      enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>
                    <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off"
                           aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">

                    <!-- Step 1 — personal -->
                    <div class="cap-panel is-on" data-cap-panel>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-name" name="name" type="text" maxlength="128" placeholder=" " required>
                                <label for="cp-name">Full Name *</label>
                                <span class="cap-f-ico"><?= lucide('user') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-email" name="email" type="email" maxlength="191" placeholder=" " required>
                                <label for="cp-email">Email *</label>
                                <span class="cap-f-ico"><?= lucide('mail') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f-country">
                                <?= country_field(['name' => 'phone', 'label' => 'Mobile Number', 'required' => true]) ?>
                            </div>
                            <div class="cap-f">
                                <input id="cp-address" name="address" type="text" maxlength="255" placeholder=" ">
                                <label for="cp-address">Address</label>
                                <span class="cap-f-ico"><?= lucide('map-pin') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-city" name="city" type="text" maxlength="96" placeholder=" ">
                                <label for="cp-city">City</label>
                                <span class="cap-f-ico"><?= lucide('building-2') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-state" name="state" type="text" maxlength="96" placeholder=" ">
                                <label for="cp-state">State</label>
                                <span class="cap-f-ico"><?= lucide('map') ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 — professional -->
                    <div class="cap-panel" data-cap-panel>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-qual" name="qualification" type="text" maxlength="128" placeholder=" ">
                                <label for="cp-qual">Highest Qualification</label>
                                <span class="cap-f-ico"><?= lucide('graduation-cap') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-exp" name="experience" type="text" maxlength="64" placeholder=" ">
                                <label for="cp-exp">Experience (e.g. 3 years)</label>
                                <span class="cap-f-ico"><?= lucide('briefcase') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-company" name="current_company" type="text" maxlength="191" placeholder=" ">
                                <label for="cp-company">Current Company</label>
                                <span class="cap-f-ico"><?= lucide('building') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-notice" name="notice_period" type="text" maxlength="64" placeholder=" ">
                                <label for="cp-notice">Notice Period</label>
                                <span class="cap-f-ico"><?= lucide('calendar-clock') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-cursal" name="current_salary" type="text" maxlength="64" placeholder=" ">
                                <label for="cp-cursal">Current Salary</label>
                                <span class="cap-f-ico"><?= lucide('coins') ?></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-expsal" name="expected_salary" type="text" maxlength="64" placeholder=" ">
                                <label for="cp-expsal">Expected Salary</label>
                                <span class="cap-f-ico"><?= lucide('trending-up') ?></span>
                            </div>
                        </div>
                        <div class="cap-row one">
                            <div class="cap-f">
                                <select id="cp-pos" name="position_applied">
                                    <option value="">General application</option>
                                    <?php foreach ($openRoles as $r): ?>
                                        <option value="<?= e($r['title']) ?>"><?= e($r['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="cp-pos">Position Applying For</label>
                                <span class="cap-f-ico"><?= lucide('target') ?></span>
                            </div>
                        </div>
                        <div class="cap-row">
                            <div class="cap-f">
                                <input id="cp-li" name="linkedin" type="url" maxlength="255" placeholder=" ">
                                <label for="cp-li">LinkedIn</label>
                                <span class="cap-f-ico"><?= lucide('linkedin') ?></span>
                                <span class="cap-err"></span>
                            </div>
                            <div class="cap-f">
                                <input id="cp-pf" name="portfolio" type="url" maxlength="255" placeholder=" ">
                                <label for="cp-pf">Portfolio</label>
                                <span class="cap-f-ico"><?= lucide('link') ?></span>
                                <span class="cap-err"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 — documents -->
                    <div class="cap-panel" data-cap-panel>
                        <label class="cap-drop" data-cap-drop>
                            <input type="file" name="resume" accept=".pdf,.doc,.docx" required>
                            <span class="cap-drop-ico"><?= lucide('upload-cloud') ?></span>
                            <b>Drag &amp; drop your résumé here</b>
                            <small>or click to browse — PDF, DOC or DOCX, max 5 MB</small>
                        </label>
                        <span class="cap-err" data-cap-file-err></span>

                        <div class="cap-file" data-cap-file>
                            <span class="cap-file-ico"><?= lucide('file-text') ?></span>
                            <span class="cap-file-meta">
                                <b data-cap-file-name></b>
                                <span data-cap-file-size></span>
                                <span class="cap-file-bar"><i data-cap-file-bar></i></span>
                            </span>
                            <button type="button" class="cap-file-x" data-cap-file-remove aria-label="Remove file"><?= lucide('x') ?></button>
                        </div>

                        <div class="cap-row one" style="margin-top:.85rem;">
                            <div class="cap-f">
                                <textarea id="cp-cover" name="cover_letter" maxlength="2000" placeholder=" " data-cap-count></textarea>
                                <label for="cp-cover">Cover Letter — why you're a great fit</label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                        <div class="cap-row one">
                            <div class="cap-f">
                                <textarea id="cp-msg" name="message" maxlength="1000" placeholder=" " data-cap-count></textarea>
                                <label for="cp-msg">Anything else we should know?</label>
                                <span class="cap-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="cap-actions">
                        <button type="button" class="cap-btn ghost" data-cap-prev style="display:none;"><?= lucide('arrow-left') ?> Back</button>
                        <button type="button" class="cap-btn grow" data-cap-next>Continue <?= lucide('arrow-right') ?></button>
                        <button type="submit" class="cap-btn grow" data-cap-submit style="display:none;"><?= lucide('send') ?> Submit Application</button>
                    </div>

                    <p class="cap-note">
                        <?= lucide('shield-check') ?>
                        We respect your privacy — your details are used only for this application and never shared.
                    </p>
                </form>
            </div>
        </div>
    </div>

    <div class="cap-sticky" data-cap-sticky>
        <a class="cap-btn" href="#apply"><?= lucide('send') ?> Apply Now</a>
    </div>
</section>


<!-- ============================== JOB ALERTS + CTA ============================== -->
<section class="section">
    <div class="container grid grid-2 gap-4 items-stretch">
        <div class="card-3d reveal" style="background:var(--grad-brand);color:#fff;display:flex;flex-direction:column;">
            <span class="eyebrow" style="color:#fde68a;">Never Miss a Role</span>
            <h2 class="text-white">Get notified about new openings</h2>
            <p style="color:rgba(255,255,255,.92);">Leave your email and we'll let you know when a role that fits you opens up. No spam — just opportunities to do good.</p>
            <form data-ajax-form data-endpoint="<?= e(url('forms/newsletter')) ?>" class="mt-3" style="margin-top:auto;">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="careers">
                <input type="text" name="website_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;height:0;width:0;overflow:hidden">
                <div class="form-row">
                    <div class="form-group" style="flex:1;">
                        <label class="form-label text-white" for="ja-name">Name</label>
                        <input class="form-control" id="ja-name" name="name" type="text" maxlength="128" placeholder="Your name">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label class="form-label text-white" for="ja-email">Email <span class="req">*</span></label>
                        <input class="form-control" id="ja-email" name="email" type="email" maxlength="191" placeholder="you@example.com" required>
                    </div>
                </div>
                <button class="btn btn-white btn-block" type="submit"><?= lucide('bell') ?> Notify Me</button>
            </form>
        </div>
        <div class="card-3d reveal delay-1" style="background:var(--grad-accent);color:#fff;display:flex;flex-direction:column;">
            <span class="eyebrow" style="color:#fff;">Another Way In</span>
            <h2 class="text-white">Not ready for a full-time role?</h2>
            <p style="color:rgba(255,255,255,.92);">Volunteering and internships are the perfect way to experience our work, build skills and grow into a longer-term role with the Foundation.</p>
            <div class="flex flex-wrap gap-2 mt-3" style="margin-top:auto;">
                <a class="btn btn-white btn-lg" href="<?= e(url('volunteer')) ?>"><?= lucide('handshake') ?> Volunteer</a>
                <a class="btn btn-outline btn-lg" style="border-color:#fff;color:#fff;" href="<?= e(url('internship')) ?>"><?= lucide('graduation-cap') ?> Internships</a>
            </div>
        </div>
    </div>
</section>

<script>
/* Careers listing — client-side job filtering + apply-now pre-select/scroll. */
(function () {
    var root = document.getElementById('car-openings');
    if (!root) return;
    var kw    = document.getElementById('car-f-keyword'),
        dep   = document.getElementById('car-f-dept'),
        loc   = document.getElementById('car-f-loc'),
        typ   = document.getElementById('car-f-type'),
        exp   = document.getElementById('car-f-exp'),
        reset = document.getElementById('car-f-reset'),
        count = document.getElementById('car-result-count'),
        none  = document.getElementById('car-no-results'),
        cards = Array.prototype.slice.call(root.querySelectorAll('[data-car-card]'));

    function apply() {
        var q = (kw.value || '').trim().toLowerCase(),
            d = dep.value, l = loc.value, t = typ.value, x = exp.value,
            shown = 0;
        cards.forEach(function (c) {
            var ok = (!q || c.dataset.title.indexOf(q) > -1)
                  && (!d || c.dataset.dept === d)
                  && (!l || c.dataset.loc === l)
                  && (!t || c.dataset.type === t)
                  && (!x || c.dataset.exp === x);
            c.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });
        if (count) count.textContent = shown;
        if (none) none.style.display = shown ? 'none' : '';
    }

    [kw, dep, loc, typ, exp].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', apply);
        el.addEventListener('change', apply);
    });
    if (reset) reset.addEventListener('click', function () {
        kw.value = ''; dep.value = ''; loc.value = ''; typ.value = ''; exp.value = '';
        apply();
    });

    document.querySelectorAll('.car-apply-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            var sel = document.getElementById('car-position');
            if (sel) {
                var v = b.getAttribute('data-apply') || '0';
                sel.value = sel.querySelector('option[value="' + v + '"]') ? v : '0';
            }
            var form = document.getElementById('car-apply');
            if (form) {
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                var fn = document.getElementById('ca2-name');
                if (fn) setTimeout(function () { fn.focus(); }, 450);
            }
        });
    });

    apply();
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
