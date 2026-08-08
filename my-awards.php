<?php
/**
 * =============================================================================
 *  My Awards — student portal: certificates + marksheets
 * =============================================================================
 *  Behind require_member(). Lists the linked student's certificates and
 *  published marksheets with download + public-verify links.
 * =============================================================================
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/student.php';
require_member('/login');

$sess    = current_member();
$student = db_row('SELECT * FROM school_students WHERE member_id = :m LIMIT 1', [':m' => (int) ($sess['id'] ?? 0)]);

$certs = $marksheets = [];
if ($student) {
    $sid = (int) $student['id'];
    $certs = db_all("SELECT c.*, co.title AS course_title FROM student_certificates c
                     LEFT JOIN courses co ON co.id = c.course_id
                     WHERE c.student_id = :s AND c.status = 'valid' ORDER BY c.id DESC", [':s' => $sid]);
    $marksheets = db_all("SELECT * FROM marksheets WHERE student_id = :s AND published = 1 ORDER BY id DESC", [':s' => $sid]);
}

seo_set(['title' => 'My Certificates & Marksheets', 'page_key' => 'my-awards', 'robots' => 'noindex,nofollow']);
$page_hero = ['title' => 'Certificates & Marksheets', 'subtitle' => 'Download and share your achievements.', 'breadcrumb' => [['label' => 'My Learning', 'url' => url('my-learning')], ['label' => 'Awards']]];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container" style="max-width:820px;">
        <?php if (!$student): ?>
            <div class="glass-card reveal" style="text-align:center;padding:2.2rem;"><p class="text-muted mb-0">No student record is linked to your account yet.</p></div>
        <?php else: ?>
            <div class="glass-card reveal mb-4">
                <h2 class="mb-2"><?= lucide('award') ?> Certificates</h2>
                <?php if ($certs): ?>
                    <?php foreach ($certs as $c): ?>
                        <div class="flex items-center justify-between flex-wrap gap-2" style="padding:.6rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
                            <div><strong><?= e($c['title']) ?></strong><br><small class="text-muted"><?= e($c['serial']) ?> · <?= $c['issue_date'] ? e(format_date($c['issue_date'])) : '' ?><?= $c['course_title'] ? ' · ' . e($c['course_title']) : '' ?></small></div>
                            <div class="flex gap-2">
                                <a class="btn btn-primary btn-sm" target="_blank" href="<?= e(admin_url('certificate-view?id=' . (int) $c['id'])) ?>"><?= lucide('download') ?> Open</a>
                                <a class="btn btn-outline btn-sm" target="_blank" href="<?= e(url('verify-award?serial=' . urlencode((string) $c['serial']))) ?>">Verify</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?><p class="text-muted mb-0">No certificates yet — complete a course to earn one.</p><?php endif; ?>
            </div>

            <div class="glass-card reveal">
                <h2 class="mb-2"><?= lucide('file-spreadsheet') ?> Marksheets</h2>
                <?php if ($marksheets): ?>
                    <?php foreach ($marksheets as $m): ?>
                        <div class="flex items-center justify-between flex-wrap gap-2" style="padding:.6rem 0;border-bottom:1px solid var(--border,#e5e7eb);">
                            <div><strong><?= e($m['title']) ?></strong><br><small class="text-muted"><?= e($m['serial']) ?> · <?= e($m['percentage']) ?>% · <?= e(ucfirst($m['result'])) ?></small></div>
                            <div class="flex gap-2">
                                <a class="btn btn-primary btn-sm" target="_blank" href="<?= e(admin_url('marksheet-view?id=' . (int) $m['id'])) ?>"><?= lucide('download') ?> Open</a>
                                <a class="btn btn-outline btn-sm" target="_blank" href="<?= e(url('verify-award?serial=' . urlencode((string) $m['serial']))) ?>">Verify</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?><p class="text-muted mb-0">No published marksheets yet.</p><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
