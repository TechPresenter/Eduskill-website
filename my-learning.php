<?php
/**
 * =============================================================================
 *  My Learning — student/member course portal dashboard
 * =============================================================================
 *  Behind require_member(). Lists the signed-in member's course enrolments with
 *  progress, targeted notices, and quick links to awards, assignments and the
 *  student ID card.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_member('/login');

$sess    = current_member();
$mid     = (int) ($sess['id'] ?? 0);
$student = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
$sid     = $student ? (int) $student['id'] : 0;

$enrollments = db_all(
    "SELECT e.*, c.title, c.slug, c.thumbnail, c.category
       FROM course_enrollments e JOIN courses c ON c.id = e.course_id
      WHERE e.member_id = :m OR e.student_id = :s
      ORDER BY e.id DESC",
    [':m' => $mid, ':s' => $sid]
);

// Targeted notices: all-audience, plus the student's school / enrolled courses.
$courseIds = array_map(static fn($e) => (int) $e['course_id'], $enrollments);
$noticeWhere = ["(audience = 'all')"];
$noticeParams = [];
if ($student && !empty($student['school_id'])) {
    $noticeWhere[] = "(audience = 'school' AND school_id = :sch)";
    $noticeParams[':sch'] = (int) $student['school_id'];
}
if ($courseIds) {
    $in = implode(',', array_map('intval', $courseIds));
    $noticeWhere[] = "(audience = 'course' AND course_id IN ($in))";
}
$notices = db_all(
    "SELECT * FROM notices WHERE status = 'published' AND (" . implode(' OR ', $noticeWhere) . ")
     ORDER BY pinned DESC, COALESCE(published_at, created_at) DESC LIMIT 8",
    $noticeParams
);

seo_set(['title' => 'My Learning', 'page_key' => 'my-learning', 'robots' => 'noindex,nofollow']);
$page_hero = [
    'title'      => 'My Learning',
    'subtitle'   => 'Your courses, progress and updates in one place.',
    'breadcrumb' => [['label' => 'My Learning']],
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container">
        <!-- Quick links -->
        <div class="flex flex-wrap gap-2 mb-4">
            <a class="btn btn-outline btn-sm" href="<?= e(url('courses')) ?>"><?= lucide('book-open') ?> Browse courses</a>
            <a class="btn btn-outline btn-sm" href="<?= e(url('my-assignments')) ?>"><?= lucide('clipboard-check') ?> Assignments</a>
            <?php if ($student): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(url('my-awards')) ?>"><?= lucide('award') ?> Certificates & Marksheets</a>
                <a class="btn btn-outline btn-sm" href="<?= e(url('student-id-card')) ?>"><?= lucide('id-card') ?> Student ID</a>
            <?php endif; ?>
        </div>

        <div class="grid grid-sidebar gap-4 items-start">
            <div>
                <div class="section-head left mb-3"><span class="eyebrow">Courses</span><h2 class="section-title mb-0">My courses</h2></div>
                <?php if ($enrollments): ?>
                    <div class="grid grid-2 gap-3">
                        <?php foreach ($enrollments as $e):
                            $pct = (float) $e['progress_percent'];
                        ?>
                            <div class="glass-card reveal" style="display:flex;flex-direction:column;">
                                <div class="flex items-center justify-between">
                                    <strong style="font-size:1.05rem;"><?= e($e['title']) ?></strong>
                                    <span class="badge <?= $e['status'] === 'completed' ? 'badge-success' : 'badge-brand' ?>"><?= e(ucfirst($e['status'])) ?></span>
                                </div>
                                <?php if (!empty($e['category'])): ?><small class="text-muted"><?= e($e['category']) ?></small><?php endif; ?>
                                <div style="background:#e5e7eb;border-radius:99px;height:8px;margin:.8rem 0 .3rem;overflow:hidden;">
                                    <div style="background:var(--brand-600,#063566);height:100%;width:<?= (int) round($pct) ?>%;"></div>
                                </div>
                                <small class="text-muted"><?= (int) round($pct) ?>% complete</small>
                                <a class="btn btn-primary btn-sm mt-2" href="<?= e(url('learn?course=' . (int) $e['course_id'])) ?>">
                                    <?= $pct > 0 ? 'Continue' : 'Start' ?> <?= lucide('arrow-right') ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-card reveal" style="text-align:center;padding:2rem;">
                        <p class="text-muted mb-2">You're not enrolled in any course yet.</p>
                        <a class="btn btn-primary" href="<?= e(url('courses')) ?>">Browse courses</a>
                    </div>
                <?php endif; ?>
            </div>

            <aside>
                <div class="glass-card reveal">
                    <h3 class="mb-2"><?= lucide('bell') ?> Notices</h3>
                    <?php if ($notices): ?>
                        <ul style="list-style:none;padding:0;margin:0;">
                            <?php foreach ($notices as $n): ?>
                                <li style="padding:.6rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
                                    <strong><?= e($n['title']) ?></strong>
                                    <div class="text-muted" style="font-size:.85rem;"><?= e(excerpt($n['body'], 24)) ?></div>
                                    <small class="text-muted"><?= e(format_date($n['published_at'] ?: $n['created_at'], 'd M Y')) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?><p class="text-muted mb-0">No notices right now.</p><?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
