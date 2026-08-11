<?php
/**
 * =============================================================================
 *  Learn — course player (lessons, progress, resources, live classes, exams)
 * =============================================================================
 *  Behind require_member(). Access is gated on an enrolment owned by the
 *  signed-in member, so one member can never open another's course. Marking a
 *  lesson complete (and YouTube "video ended") drives per-lesson + course
 *  progress via lms_set_lesson_progress().
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_member('/login');

$sess = current_member();
$mid  = (int) ($sess['id'] ?? 0);

$courseId = (int) get('course', 0);
$course   = find('courses', $courseId);
if (!$course) {
    set_flash('error', 'Course not found.');
    redirect('/my-learning');
}
$linkedStudent = db_row('SELECT id FROM school_students WHERE member_id = :m LIMIT 1', [':m' => $mid]);
$lsid = $linkedStudent ? (int) $linkedStudent['id'] : 0;
$enr = db_row("SELECT * FROM course_enrollments WHERE course_id = :c AND status <> 'dropped' AND (member_id = :m OR student_id = :s) LIMIT 1", [':c' => $courseId, ':m' => $mid, ':s' => $lsid]);
if (!$enr) {
    set_flash('error', 'You are not enrolled in this course.');
    redirect('/course?slug=' . urlencode($course['slug']));
}
$enrId = (int) $enr['id'];

$lessons = db_all('SELECT * FROM course_lessons WHERE course_id = :c ORDER BY sort_order ASC, id ASC', [':c' => $courseId]);
$progressRows = db_all('SELECT lesson_id, status FROM lesson_progress WHERE enrollment_id = :e', [':e' => $enrId]);
$done = [];
foreach ($progressRows as $pr) { $done[(int) $pr['lesson_id']] = $pr['status'] === 'completed'; }

/* -------- Mark lesson complete -------- */
if (is_post() && post('_do') === 'complete') {
    require_csrf();
    $lessonId = (int) post('lesson_id', 0);
    $valid = false;
    foreach ($lessons as $l) { if ((int) $l['id'] === $lessonId) { $valid = true; break; } }
    if ($valid) {
        lms_set_lesson_progress($enrId, $lessonId, 100, 0, true);
        set_flash('success', 'Lesson marked complete.');
    }
    redirect('/learn?course=' . $courseId . '&lesson=' . $lessonId);
}

/* -------- AJAX video progress -------- */
if (is_post() && post('_do') === 'progress') {
    require_csrf();
    $lessonId = (int) post('lesson_id', 0);
    $percent  = (float) post('percent', 0);
    $position = (int) post('position', 0);
    $valid = false;
    foreach ($lessons as $l) { if ((int) $l['id'] === $lessonId) { $valid = true; break; } }
    if ($valid) {
        lms_set_lesson_progress($enrId, $lessonId, $percent, $position, $percent >= 95);
        json_success('saved');
    }
    json_error('Invalid lesson');
}

// Current lesson: requested, else first incomplete, else first.
$current = null;
$reqLesson = (int) get('lesson', 0);
foreach ($lessons as $l) { if ((int) $l['id'] === $reqLesson) { $current = $l; break; } }
if (!$current) {
    foreach ($lessons as $l) { if (empty($done[(int) $l['id']])) { $current = $l; break; } }
}
if (!$current && $lessons) { $current = $lessons[0]; }

$resources    = db_all('SELECT * FROM course_resources WHERE course_id = :c ORDER BY id', [':c' => $courseId]);
$liveSessions = db_all("SELECT * FROM live_sessions WHERE course_id = :c AND status IN ('scheduled','live') ORDER BY scheduled_at ASC LIMIT 5", [':c' => $courseId]);
$exams        = db_all("SELECT * FROM exams WHERE course_id = :c AND status = 'published' ORDER BY id", [':c' => $courseId]);

seo_set(['title' => $course['title'] . ' — Learn', 'page_key' => 'learn', 'robots' => 'noindex,nofollow']);
include __DIR__ . '/includes/header.php';

/** Build the embed HTML for a lesson. */
function lesson_embed(array $l): string
{
    if ($l['type'] === 'video') {
        if ($l['video_provider'] === 'youtube' && $l['video_url']) {
            $yid = youtube_id($l['video_url']);
            if ($yid) {
                return '<iframe id="ytplayer" width="100%" height="440" style="border-radius:12px;" src="https://www.youtube.com/embed/' . e($yid) . '?enablejsapi=1" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            }
        }
        if ($l['video_provider'] === 'vimeo' && $l['video_url'] && preg_match('#(\d{6,})#', $l['video_url'], $m)) {
            return '<iframe width="100%" height="440" style="border-radius:12px;" src="https://player.vimeo.com/video/' . e($m[1]) . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
        }
        if ($l['video_file']) {
            return '<video controls style="width:100%;border-radius:12px;max-height:460px;" src="' . e(upload_url($l['video_file'])) . '"></video>';
        }
    }
    if ($l['type'] === 'pdf' && $l['pdf_file']) {
        return '<a class="btn btn-primary" target="_blank" href="' . e(upload_url($l['pdf_file'])) . '">Open PDF material</a>';
    }
    return '<div>' . rich_text($l['content']) . '</div>';
}
?>
<section class="section">
    <div class="container">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
            <div><a href="<?= e(url('my-learning')) ?>" class="text-muted">← My Learning</a>
                <h2 class="mb-0"><?= e($course['title']) ?></h2></div>
            <div style="min-width:200px;">
                <div style="background:#e5e7eb;border-radius:99px;height:8px;overflow:hidden;">
                    <div style="background:var(--brand-600,#0B4E3D);height:100%;width:<?= (int) round((float) $enr['progress_percent']) ?>%;"></div>
                </div>
                <small class="text-muted"><?= (int) round((float) $enr['progress_percent']) ?>% complete</small>
            </div>
        </div>

        <div class="grid grid-sidebar gap-4 items-start">
            <!-- Lesson list -->
            <aside class="glass-card reveal sticky-aside" style="position:sticky;top:100px;">
                <h3 class="mb-2">Lessons</h3>
                <ul style="list-style:none;padding:0;margin:0;">
                    <?php foreach ($lessons as $i => $l): $isCur = $current && (int) $l['id'] === (int) $current['id']; ?>
                        <li style="margin-bottom:.3rem;">
                            <a href="<?= e(url('learn?course=' . $courseId . '&lesson=' . (int) $l['id'])) ?>"
                               style="display:flex;gap:.5rem;align-items:center;padding:.5rem .6rem;border-radius:8px;text-decoration:none;color:inherit;<?= $isCur ? 'background:var(--brand-50,#eff6ff);font-weight:700;' : '' ?>">
                                <span style="flex:none;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-size:.7rem;<?= !empty($done[(int) $l['id']]) ? 'background:#2F8065;color:#fff;' : 'background:#e5e7eb;color:#6b7280;' ?>">
                                    <?= !empty($done[(int) $l['id']]) ? '✓' : ($i + 1) ?>
                                </span>
                                <span style="font-size:.9rem;"><?= e($l['title']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($exams): ?>
                    <h3 class="mb-2 mt-3">Exams & Quizzes</h3>
                    <?php foreach ($exams as $ex): ?>
                        <a class="btn btn-outline btn-sm btn-block mb-1" href="<?= e(url('take-exam?exam=' . (int) $ex['id'])) ?>"><?= lucide('file-question') ?> <?= e($ex['title']) ?></a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>

            <!-- Current lesson -->
            <div>
                <?php if ($current): ?>
                    <div class="glass-card reveal">
                        <?php if (!empty($current['module'])): ?><small class="text-muted"><?= e($current['module']) ?></small><?php endif; ?>
                        <h2 class="mb-2"><?= e($current['title']) ?></h2>
                        <div style="margin-bottom:1rem;"><?= lesson_embed($current) ?></div>
                        <?php if ($current['type'] !== 'text' && !empty($current['content'])): ?>
                            <div class="mb-2"><?= rich_text($current['content']) ?></div>
                        <?php endif; ?>
                        <?php /* The action MUST carry ?course= — learn.php:18 resolves the
                                 course from $_GET only, so posting to a bare /learn made
                                 $courseId 0 and bounced the request to /my-learning with
                                 "Course not found." before the _do=complete handler ran.
                                 Matches the fetch URL used by the video-ended handler below. */ ?>
                        <form method="post" action="<?= e(url('learn?course=' . $courseId)) ?>" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_do" value="complete">
                            <input type="hidden" name="lesson_id" value="<?= (int) $current['id'] ?>">
                            <button class="btn btn-success" type="submit" <?= !empty($done[(int) $current['id']]) ? 'disabled' : '' ?>>
                                <?= lucide('check') ?> <?= !empty($done[(int) $current['id']]) ? 'Completed' : 'Mark as complete' ?></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="glass-card reveal"><p class="text-muted mb-0">This course has no lessons yet.</p></div>
                <?php endif; ?>

                <?php if ($liveSessions): ?>
                    <div class="glass-card reveal mt-3">
                        <h3 class="mb-2"><?= lucide('video') ?> Live classes</h3>
                        <?php foreach ($liveSessions as $ls): ?>
                            <div class="flex items-center justify-between" style="padding:.4rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
                                <div><strong><?= e($ls['title']) ?></strong><br><small class="text-muted"><?= e(format_datetime($ls['scheduled_at'])) ?> · <?= e(ucfirst($ls['platform'])) ?></small></div>
                                <?php if (!empty($ls['link'])): ?><a class="btn btn-primary btn-sm" target="_blank" rel="noopener" href="<?= e($ls['link']) ?>">Join</a><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($resources): ?>
                    <div class="glass-card reveal mt-3">
                        <h3 class="mb-2"><?= lucide('file-down') ?> Resources</h3>
                        <?php foreach ($resources as $r): ?>
                            <div class="flex items-center justify-between" style="padding:.35rem 0;">
                                <span><?= e($r['title']) ?></span>
                                <?php if (!empty($r['downloadable'])): ?>
                                    <a class="btn btn-outline btn-sm" target="_blank" href="<?= e(upload_url($r['file_path'])) ?>">Download</a>
                                <?php else: ?><span class="text-muted" style="font-size:.85rem;">View only</span><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($current && $current['type'] === 'video' && $current['video_provider'] === 'youtube'): ?>
<script>
// Auto-mark complete when the YouTube video ends.
(function () {
    var tag = document.createElement('script'); tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
    window.onYouTubeIframeAPIReady = function () {
        if (!document.getElementById('ytplayer')) return;
        new YT.Player('ytplayer', { events: { 'onStateChange': function (e) {
            if (e.data === YT.PlayerState.ENDED) {
                var f = new FormData();
                f.append('<?= e(CSRF_TOKEN_NAME) ?>', '<?= e(csrf_token()) ?>');
                f.append('_do', 'progress'); f.append('lesson_id', '<?= (int) $current['id'] ?>'); f.append('percent', '100');
                fetch('<?= e(url('learn?course=' . $courseId)) ?>', { method: 'POST', body: f, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function () { location.reload(); });
            }
        } } });
    };
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
