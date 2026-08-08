<?php
/**
 * =============================================================================
 *  Course — public course detail + enrol (LMS)
 * =============================================================================
 *  A logged-in member can self-enrol (links their linked student record when
 *  present); guests are prompted to sign in.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
// Must be loaded up-front, not inside the paid-enrolment branch below: the
// branch's own guard calls cashfree_enabled(), which lives in payments.php
// (pulled in by pay.php). Requiring it lazily made that guard always false.
require_once __DIR__ . '/includes/pay.php';

$slug   = clean(get('slug', ''));
$course = $slug !== '' ? db_row("SELECT * FROM courses WHERE slug = :s AND status = 'published' LIMIT 1", [':s' => $slug]) : null;
if (!$course) {
    // Proper 404 inside the normal layout (house pattern — see blog-details.php).
    http_response_code(404);
    seo_set(['title' => 'Course Not Found', 'page_key' => 'course', 'robots' => 'noindex,nofollow']);
    $page_hero = ['title' => 'Course Not Found', 'subtitle' => 'The course you are looking for does not exist or is no longer published.', 'breadcrumb' => [['label' => 'Courses', 'url' => url('courses')], ['label' => 'Not found']]];
    include __DIR__ . '/includes/header.php'; ?>
    <section class="section"><div class="container" style="max-width:560px;text-align:center;">
        <div class="card-3d reveal" style="padding:2.2rem;">
            <div class="icon-badge" style="margin:0 auto 1rem;font-size:1.6rem;"><?= lucide('search-x') ?></div>
            <h2 class="mb-1">We couldn't find that course</h2>
            <p class="text-muted">It may have been renamed or unpublished. Browse the full catalogue instead.</p>
            <a class="btn btn-primary mt-2" href="<?= e(url('courses')) ?>"><?= lucide('arrow-left') ?> All Courses</a>
        </div>
    </div></section>
    <?php include __DIR__ . '/includes/footer.php'; exit;
}
$courseId = (int) $course['id'];
$mid      = is_member_logged_in() ? (int) current_member()['id'] : 0;
$enrolled = $mid ? db_row("SELECT id FROM course_enrollments WHERE course_id = :c AND member_id = :m AND status IN ('active','completed') LIMIT 1", [':c' => $courseId, ':m' => $mid]) : null;

/* -------- Self-enrol -------- */
if (is_post() && post('_do') === 'enroll') {
    require_csrf();
    if (!$mid) {
        // Send them back to this course after signing in.
        $_SESSION['_member_intended'] = BASE_URI . '/course?slug=' . rawurlencode($slug);
        set_flash('error', 'Please sign in to enrol.');
        redirect('/login');
    }
    // Paid course → always route through the gateway; fulfilment enrols the
    // member automatically once the payment is confirmed paid.
    //
    // This MUST fail closed. Gateway availability is a configuration state, not
    // an authorisation decision: falling through to the free-enrolment path when
    // the gateway is off (or its credentials are empty/rotated) would hand out a
    // priced course for nothing. Same pattern as membership-renew.php.
    $price = (float) ($course['price'] ?? 0);
    if ($price > 0 && !$enrolled) {
        if (!cashfree_enabled()) {
            set_flash('info', 'Online payment is not available right now. Please contact our office to enrol in this course.');
            redirect('/course?slug=' . rawurlencode($slug));
        }
        $mem = find('members', $mid);
        $r = pay_start([
            'context_type' => 'course', 'context_id' => $courseId, 'member_id' => $mid,
            'name' => $mem['name'] ?? '', 'email' => $mem['email'] ?? '', 'phone' => $mem['phone'] ?? '',
            'amount' => $price, 'purpose' => 'Course: ' . $course['title'],
        ]);
        if ($r['ok']) { redirect($r['url']); }
        set_flash('error', $r['error'] ?? 'Could not start the payment. Please try again.');
        redirect('/course?slug=' . rawurlencode($slug));
    }

    // Free course, or an already-enrolled member re-submitting (lms_enroll is
    // idempotent and returns the existing enrolment).
    $student = db_row('SELECT id FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
    lms_enroll($courseId, ['member_id' => $mid, 'student_id' => $student ? (int) $student['id'] : null]);
    set_flash('success', 'You are enrolled. Happy learning!');
    redirect('/learn?course=' . $courseId);
}

$lessons  = db_all('SELECT id, module, title, type, is_preview, duration_min FROM course_lessons WHERE course_id = :c ORDER BY sort_order ASC, id ASC', [':c' => $courseId]);
$objectives   = array_filter(array_map('trim', explode("\n", (string) $course['objectives'])));
$prereqs      = array_filter(array_map('trim', explode("\n", (string) $course['prerequisites'])));

seo_set(['title' => $course['title'], 'description' => $course['short_description'] ?: excerpt($course['description'], 30), 'page_key' => 'course']);
$page_hero = [
    'title'      => $course['title'],
    'subtitle'   => $course['short_description'] ?? '',
    'breadcrumb' => [['label' => 'Courses', 'url' => url('courses')], ['label' => $course['title']]],
];
include __DIR__ . '/includes/header.php';
echo json_ld_course($course);
?>
<section class="section">
    <div class="container grid grid-sidebar gap-4 items-start">
        <div>
            <?php if (!empty($course['thumbnail'])): ?>
                <img src="<?= e(image_url($course['thumbnail'], 'blog')) ?>" alt="<?= e($course['title']) ?>" width="900" height="506" style="width:100%;height:auto;border-radius:14px;margin-bottom:1.2rem;">
            <?php endif; ?>
            <div class="glass-card reveal">
                <h2 class="mb-2">About this course</h2>
                <div class="prose"><?= rich_text($course['description']) ?></div>
                <?php if ($objectives): ?>
                    <h3 class="mt-3 mb-2">What you'll learn</h3>
                    <ul class="list-check"><?php foreach ($objectives as $o): ?><li><?= e($o) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if ($prereqs): ?>
                    <h3 class="mt-3 mb-2">Prerequisites</h3>
                    <ul class="list-check"><?php foreach ($prereqs as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>

            <div class="glass-card reveal mt-3">
                <h3 class="mb-2">Curriculum</h3>
                <?php if ($lessons):
                    // Group lessons under their module headings (in sort order).
                    $modules = [];
                    foreach ($lessons as $l) { $modules[$l['module'] ?: 'Lessons'][] = $l; }
                    $n = 0; ?>
                    <?php foreach ($modules as $modName => $modLessons): ?>
                        <?php if (count($modules) > 1): ?>
                            <h4 class="mt-2 mb-1" style="font-size:.95rem;color:var(--text-soft);"><?= lucide('folder') ?> <?= e($modName) ?></h4>
                        <?php endif; ?>
                        <ol style="padding-left:1.1rem;margin:.2rem 0 .6rem;" start="<?= $n + 1 ?>">
                            <?php foreach ($modLessons as $l): $n++; ?>
                                <li style="padding:.35rem 0;"><?= e($l['title']) ?>
                                    <?php if (!empty($l['is_preview'])): ?><span class="badge badge-success" style="font-size:.65rem;">Preview</span><?php endif; ?>
                                    <small class="text-muted"><?= e(ucfirst($l['type'])) ?><?= $l['duration_min'] ? ' · ' . (int) $l['duration_min'] . ' min' : '' ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endforeach; ?>
                <?php else: ?><p class="text-muted mb-0">Curriculum coming soon.</p><?php endif; ?>
            </div>
        </div>

        <aside class="glass-card reveal" style="position:sticky;top:100px;text-align:center;">
            <div style="font-size:2rem;font-weight:800;color:var(--brand-600,#063566);"><?= (float) $course['price'] > 0 ? e(money($course['price'], '₹', 0)) : 'Free' ?></div>
            <ul class="list-check" style="text-align:left;margin:1rem 0;">
                <li><?= lucide('signal') ?> <?= e(ucfirst($course['level'])) ?></li>
                <?php if (!empty($course['duration'])): ?><li><?= lucide('clock') ?> <?= e($course['duration']) ?></li><?php endif; ?>
                <li><?= lucide('list') ?> <?= count($lessons) ?> lessons</li>
                <?php if (!empty($course['certificate_enabled'])): ?><li><?= lucide('award') ?> Certificate on completion</li><?php endif; ?>
            </ul>
            <?php if ($enrolled): ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('learn?course=' . $courseId)) ?>"><?= lucide('play') ?> Continue learning</a>
            <?php else: ?>
                <form method="post" action="<?= e(url('course?slug=' . urlencode($slug))) ?>">
                    <?= csrf_field() ?><input type="hidden" name="_do" value="enroll">
                    <button class="btn btn-3d btn-block btn-lg" type="submit"><?= lucide('graduation-cap') ?> <?= $mid ? 'Enrol now' : 'Sign in to enrol' ?></button>
                </form>
            <?php endif; ?>
        </aside>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
