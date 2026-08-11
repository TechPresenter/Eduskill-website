<?php
/**
 * =============================================================================
 *  Admin — School Dashboard (per-school analytics)
 * =============================================================================
 *  ?id=<school>. Headline stats (students, batches, staff, fees, dues,
 *  attendance), a student-status chart, and quick links into the school's
 *  students / batches / staff / fees / attendance (all pre-filtered).
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id     = (int) get('id', 0);
$school = find('schools', $id);
if (!$school) {
    set_flash('error', 'School not found.');
    redirect('/admin/schools');
}
$school = school_ensure_code($school);
$stats  = school_stats($id);

$typeLabels = ['government' => 'Government', 'private' => 'Private', 'aided' => 'Aided', 'other' => 'Other'];

$statusRows = db_all("SELECT status, COUNT(*) c FROM school_students WHERE school_id = :s GROUP BY status", [':s' => $id]);
$statusData = ['enrolled' => 0, 'active' => 0, 'completed' => 0, 'dropped' => 0];
foreach ($statusRows as $r) { $statusData[$r['status']] = (int) $r['c']; }

$batches  = db_all("SELECT b.*, (SELECT COUNT(*) FROM school_students s WHERE s.batch_id = b.id) AS enrolled
                      FROM school_batches b WHERE b.school_id = :s ORDER BY b.id DESC LIMIT 8", [':s' => $id]);
$staff    = db_all("SELECT * FROM school_staff WHERE school_id = :s AND status = 'active' ORDER BY id DESC LIMIT 8", [':s' => $id]);
$payments = db_all("SELECT p.*, st.name AS student_name FROM school_fee_payments p
                      LEFT JOIN school_students st ON st.id = p.student_id
                     WHERE p.school_id = :s ORDER BY p.id DESC LIMIT 8", [':s' => $id]);

$q = 'school=' . $id;
$page_title   = $school['name'];
$load_charts  = true;
include __DIR__ . '/partials/head.php';
?>
<div class="admin-page-head">
    <div>
        <h1><?= e($school['name']) ?></h1>
        <span class="muted">Schools / <?= e($school['code']) ?> · <?= e($typeLabels[$school['type']] ?? $school['type']) ?><?= $school['city'] ? ' · ' . e($school['city']) : '' ?>
            <span class="pill <?= e(school_status_pill($school['status'])) ?>"><?= e(ucfirst($school['status'])) ?></span></span>
    </div>
    <div class="flex" style="gap:.5rem;">
        <a class="btn btn-outline" href="<?= e(admin_url('schools?action=edit&id=' . $id)) ?>"><?= lucide('pencil') ?> Edit</a>
        <a class="btn btn-secondary" href="<?= e(admin_url('schools')) ?>">← All schools</a>
    </div>
</div>

<!-- Quick links -->
<div class="flex flex-wrap" style="gap:.5rem;margin-bottom:1rem;">
    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('school-students?' . $q)) ?>"><?= lucide('users') ?> Students</a>
    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('school-batches?' . $q)) ?>"><?= lucide('layers') ?> Batches</a>
    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('school-staff?' . $q)) ?>"><?= lucide('user-cog') ?> Staff</a>
    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('school-fees?' . $q)) ?>"><?= lucide('receipt-indian-rupee') ?> Fees</a>
    <a class="btn btn-outline btn-sm" href="<?= e(admin_url('school-attendance?' . $q)) ?>"><?= lucide('calendar-check') ?> Attendance</a>
</div>

<!-- Stats -->
<div class="stat-grid">
    <a class="stat-card" href="<?= e(admin_url('school-students?' . $q)) ?>"><div class="stat-icon bg-blue"><?= lucide('users') ?></div><div><div class="stat-value"><?= (int) $stats['students'] ?></div><div class="stat-label"><?= (int) $stats['active_students'] ?> active students</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('school-batches?' . $q)) ?>"><div class="stat-icon bg-violet"><?= lucide('layers') ?></div><div><div class="stat-value"><?= (int) $stats['batches'] ?></div><div class="stat-label"><?= (int) $stats['ongoing_batches'] ?> ongoing batches</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('school-staff?' . $q)) ?>"><div class="stat-icon bg-green"><?= lucide('user-cog') ?></div><div><div class="stat-value"><?= (int) $stats['staff'] ?></div><div class="stat-label">Staff assigned</div></div></a>
    <a class="stat-card" href="<?= e(admin_url('school-fees?' . $q)) ?>"><div class="stat-icon bg-amber"><?= lucide('indian-rupee') ?></div><div><div class="stat-value"><?= e(money($stats['fee_collected'], '₹', 0)) ?></div><div class="stat-label"><?= e(money($stats['fee_due'], '₹', 0)) ?> dues</div></div></a>
    <div class="stat-card"><div class="stat-icon bg-rose"><?= lucide('calendar-check') ?></div><div><div class="stat-value"><?= $stats['attendance_rate'] === null ? '—' : e($stats['attendance_rate']) . '%' ?></div><div class="stat-label">Attendance (30d)</div></div></div>
</div>

<div class="grid-2" style="align-items:start;gap:1.25rem;">
    <div>
        <!-- Profile -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-2">Profile</h3>
            <div class="table-wrap"><table class="admin-table"><tbody>
                <tr><th style="width:150px;">Board</th><td><?= $school['board'] ? e($school['board']) : '—' ?></td></tr>
                <tr><th>Accreditation</th><td><?= $school['accreditation'] ? e($school['accreditation']) : '—' ?></td></tr>
                <tr><th>UDISE</th><td><?= $school['udise_code'] ? e($school['udise_code']) : '—' ?></td></tr>
                <tr><th>Established</th><td><?= $school['established_year'] ? e($school['established_year']) : '—' ?></td></tr>
                <tr><th>Contact</th><td><?= $school['contact_person'] ? e($school['contact_person']) : '—' ?>
                    <?= $school['phone'] ? '· <a href="tel:' . e($school['phone']) . '">' . e($school['phone']) . '</a>' : '' ?></td></tr>
                <tr><th>Email</th><td><?= $school['email'] ? '<a href="mailto:' . e($school['email']) . '">' . e($school['email']) . '</a>' : '—' ?></td></tr>
                <tr><th>Address</th><td><?= e(trim(implode(', ', array_filter([$school['address'], $school['city'], $school['state'], $school['pincode']]))) ?: '—') ?></td></tr>
            </tbody></table></div>
        </div></div>

        <!-- Batches -->
        <div class="panel"><div class="panel-body">
            <div class="flex items-center justify-between mb-2"><h3 class="mb-0">Batches</h3><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-batches?' . $q)) ?>">Manage</a></div>
            <?php if ($batches): ?>
            <div class="table-wrap"><table class="admin-table">
                <thead><tr><th>Batch</th><th>Schedule</th><th>Enrolled</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($batches as $b): ?>
                    <tr><td><strong><?= e($b['name']) ?></strong></td><td><?= $b['schedule'] ? e($b['schedule']) : '—' ?></td>
                        <td><?= (int) $b['enrolled'] ?><?= $b['capacity'] ? ' / ' . (int) $b['capacity'] : '' ?></td>
                        <td><span class="pill <?= e(batch_status_pill($b['status'])) ?>"><?= e(ucfirst($b['status'])) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?><p class="text-muted">No batches yet.</p><?php endif; ?>
        </div></div>
    </div>

    <div>
        <!-- Student status chart -->
        <div class="panel"><div class="panel-body">
            <h3 class="mb-2">Students by status</h3>
            <?php if (array_sum($statusData) > 0): ?>
                <canvas id="studentChart" height="180"></canvas>
            <?php else: ?><p class="text-muted">No students enrolled yet. <a href="<?= e(admin_url('school-students?' . $q)) ?>">Add students</a>.</p><?php endif; ?>
        </div></div>

        <!-- Recent fee payments -->
        <div class="panel"><div class="panel-body">
            <div class="flex items-center justify-between mb-2"><h3 class="mb-0">Recent fee payments</h3><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-fees?' . $q)) ?>">Manage</a></div>
            <?php if ($payments): ?>
            <div class="table-wrap"><table class="admin-table">
                <thead><tr><th>Date</th><th>Student</th><th>For</th><th>Paid</th><th>Status</th></tr></thead><tbody>
                <?php foreach ($payments as $pm): ?>
                    <tr><td><?= e(format_date($pm['created_at'], 'd M')) ?></td><td><?= $pm['student_name'] ? e($pm['student_name']) : '<span class="text-muted">—</span>' ?></td>
                        <td><?= e($pm['title']) ?></td><td><?= e(money($pm['amount_paid'], '₹', 0)) ?></td>
                        <td><span class="pill <?= e(fee_status_pill($pm['status'])) ?>"><?= e(ucfirst($pm['status'])) ?></span></td></tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php else: ?><p class="text-muted">No fee payments recorded.</p><?php endif; ?>
        </div></div>

        <!-- Staff -->
        <div class="panel"><div class="panel-body">
            <div class="flex items-center justify-between mb-2"><h3 class="mb-0">Assigned staff</h3><a class="btn btn-ghost btn-sm" href="<?= e(admin_url('school-staff?' . $q)) ?>">Manage</a></div>
            <?php if ($staff): ?>
                <ul class="list-check" style="margin:0;">
                    <?php foreach ($staff as $st): ?><li><strong><?= e($st['name']) ?></strong> — <?= e(school_role_labels()[$st['role']] ?? $st['role']) ?><?= $st['subject'] ? ' (' . e($st['subject']) . ')' : '' ?></li><?php endforeach; ?>
                </ul>
            <?php else: ?><p class="text-muted">No staff assigned.</p><?php endif; ?>
        </div></div>
    </div>
</div>

<?php if (array_sum($statusData) > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    new Chart(document.getElementById('studentChart'), {
        type: 'doughnut',
        data: {
            labels: ['Enrolled', 'Active', 'Completed', 'Dropped'],
            datasets: [{
                data: [<?= (int) $statusData['enrolled'] ?>, <?= (int) $statusData['active'] ?>, <?= (int) $statusData['completed'] ?>, <?= (int) $statusData['dropped'] ?>],
                backgroundColor: ['#174D3D', '#2F8065', '#94a3b8', '#ef4444'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/partials/foot.php'; ?>
