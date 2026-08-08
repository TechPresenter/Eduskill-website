<?php
/**
 * =============================================================================
 *  Admin — Printable student marksheet (standalone, no admin chrome)
 * =============================================================================
 *  ?id=<marksheets id>. Renders a clean, print-ready "Statement of Marks" for a
 *  single marksheet with an inline verification QR. Browser print-to-PDF — no
 *  PDF library required.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student.php';
require_once __DIR__ . '/../includes/lib/qrcode.php';

$id = (int) get('id', 0);

$ms = find('marksheets', $id);
if (!$ms) {
    set_flash('error', 'Marksheet not found.');
    redirect('/admin/marksheets');
}
// Admins, or the student who owns this marksheet, may view it.
student_portal_or_admin_guard((int) $ms['student_id']);
$ms = marksheet_ensure_identity($ms);

$subjects = db_all(
    'SELECT * FROM marksheet_subjects WHERE marksheet_id = :m ORDER BY sort_order, id',
    [':m' => $id]
);

$student = db_row(
    "SELECT st.name, st.student_code, st.grade, sc.name AS school_name
       FROM school_students st
       LEFT JOIN schools sc ON sc.id = st.school_id
      WHERE st.id = :sid",
    [':sid' => (int) $ms['student_id']]
) ?: [];

$results = ['pass' => 'Pass', 'fail' => 'Fail', 'pending' => 'Pending'];
$resultPill = ['pass' => 'pill-pass', 'fail' => 'pill-fail', 'pending' => 'pill-pending'];

$siteName = (string) get_setting('site_name', SITE_NAME);
$serial   = (string) ($ms['serial'] ?? '');
$host     = (string) (parse_url((string) APP_URL, PHP_URL_HOST) ?? '');
$verifyQR = PWF_QR::encode(marksheet_verify_url($ms), 'M')->svg(2, 3);

/** Trim trailing zeros from a marks/percentage value (85.00 -> 85, 85.50 -> 85.5). */
$num = static fn($v): string => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');

$backUrl = admin_url('marksheets');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Statement of Marks · <?= e($serial ?: ($student['name'] ?? '')) ?></title>
    <style>
        *{box-sizing:border-box;}
        body{margin:0;min-height:100vh;background:#eef2f7;color:#1f2937;
             font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#063566;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
        .sheet{width:100%;max-width:760px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;
               padding:36px 40px;box-shadow:0 10px 30px rgba(6,53,102,.08);}
        .m-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
                border-bottom:2px solid #1f2937;padding-bottom:16px;margin-bottom:24px;}
        .m-org{font-size:1.4rem;font-weight:800;letter-spacing:.2px;margin:0;}
        .m-tag{font-size:.78rem;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:1.5px;}
        .m-title{font-size:1.05rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;text-align:right;}
        .m-meta{text-align:right;font-size:.85rem;color:#475569;margin-top:6px;line-height:1.6;}
        .m-meta strong{color:#1f2937;}
        .student{display:flex;flex-wrap:wrap;gap:14px 30px;margin-bottom:24px;}
        .field{min-width:150px;}
        .field h4{margin:0 0 3px;font-size:.68rem;text-transform:uppercase;letter-spacing:1.2px;color:#64748b;font-weight:700;}
        .field .val{font-size:1rem;font-weight:700;}
        table.marks{width:100%;border-collapse:collapse;margin:8px 0 22px;}
        table.marks th,table.marks td{padding:11px 14px;text-align:left;font-size:.9rem;}
        table.marks thead th{background:#f1f5f9;color:#475569;text-transform:uppercase;font-size:.72rem;letter-spacing:1px;}
        table.marks tbody td{border-bottom:1px solid #eef2f7;}
        table.marks tbody tr:last-child td{border-bottom:0;}
        .num{text-align:right;font-variant-numeric:tabular-nums;}
        .center{text-align:center;}
        .totals{margin-left:auto;width:100%;max-width:340px;}
        .totals td{padding:8px 14px;font-size:.92rem;}
        .totals .lbl{color:#64748b;}
        .totals .grand td{border-top:2px solid #1f2937;font-weight:800;font-size:1rem;}
        .pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;
              text-transform:uppercase;letter-spacing:.5px;}
        .pill-pass{background:#dcfce7;color:#308629;} .pill-fail{background:#fee2e2;color:#b91c1c;}
        .pill-pending{background:#e2e8f0;color:#475569;}
        .remarks{margin:18px 0 4px;font-size:.9rem;color:#475569;}
        .remarks strong{color:#1f2937;}
        .m-foot{margin-top:30px;padding-top:18px;border-top:1px dashed #cbd5e1;
                display:flex;justify-content:space-between;align-items:flex-end;gap:20px;}
        .verify{display:flex;align-items:flex-end;gap:12px;font-size:.74rem;color:#64748b;}
        .verify svg{display:block;width:76px;height:76px;}
        .verify .v-text{line-height:1.5;}
        .sign{text-align:center;color:#475569;}
        .sign .line{width:180px;border-top:1px solid #94a3b8;margin-bottom:5px;}
        .note{text-align:center;font-size:.72rem;color:#94a3b8;margin-top:22px;}
        @media print{
            @page{size:A4 portrait;margin:14mm;}
            body{background:#fff;padding:0;gap:0;}
            .toolbar{display:none;}
            .sheet{border:0;border-radius:0;box-shadow:none;max-width:none;padding:0;}
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="<?= e($backUrl) ?>">&larr; Back to marksheets</a>
    </div>

    <div class="sheet">
        <div class="m-head">
            <div>
                <h1 class="m-org"><?= e($siteName) ?></h1>
                <div class="m-tag">Statement of Marks</div>
            </div>
            <div>
                <div class="m-title">Statement of Marks</div>
                <div class="m-meta">
                    <div>Serial: <strong><?= e($serial ?: '—') ?></strong></div>
                    <?php if (!empty($ms['title'])): ?>
                        <div><?= e($ms['title']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="student">
            <div class="field">
                <h4>Student</h4>
                <div class="val"><?= e($student['name'] ?? '—') ?></div>
            </div>
            <div class="field">
                <h4>Code</h4>
                <div class="val"><?= e($student['student_code'] ?? '—') ?></div>
            </div>
            <div class="field">
                <h4>Class</h4>
                <div class="val"><?= e($student['grade'] ?? '—') ?: '—' ?></div>
            </div>
            <div class="field">
                <h4>Term</h4>
                <div class="val"><?= e($ms['term'] ?? '—') ?: '—' ?></div>
            </div>
            <div class="field">
                <h4>Academic Year</h4>
                <div class="val"><?= e($ms['academic_year'] ?? '—') ?: '—' ?></div>
            </div>
        </div>

        <table class="marks">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th class="num">Max</th>
                    <th class="num">Obtained</th>
                    <th class="center">Grade</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($subjects): ?>
                    <?php foreach ($subjects as $s): ?>
                        <tr>
                            <td><?= e($s['subject']) ?></td>
                            <td class="num"><?= e($num($s['max_marks'])) ?></td>
                            <td class="num"><?= e($num($s['obtained_marks'])) ?></td>
                            <td class="center"><?= e($s['grade'] ?? '—') ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="center" style="color:#94a3b8;">No subjects recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="lbl">Total marks</td>
                <td class="num"><?= e($num($ms['obtained_total'])) ?> / <?= e($num($ms['max_total'])) ?></td>
            </tr>
            <tr>
                <td class="lbl">Percentage</td>
                <td class="num"><?= e($num($ms['percentage'])) ?>%</td>
            </tr>
            <tr>
                <td class="lbl">Grade</td>
                <td class="num"><strong><?= e($ms['grade'] ?? '—') ?: '—' ?></strong></td>
            </tr>
            <?php if (!empty($ms['rank'])): ?>
            <tr>
                <td class="lbl">Rank</td>
                <td class="num"><?= (int) $ms['rank'] ?></td>
            </tr>
            <?php endif; ?>
            <tr class="grand">
                <td>Result</td>
                <td class="num">
                    <span class="pill <?= e($resultPill[$ms['result']] ?? 'pill-pending') ?>"><?= e($results[$ms['result']] ?? ucfirst((string) $ms['result'])) ?></span>
                </td>
            </tr>
        </table>

        <?php if (!empty($ms['remarks'])): ?>
            <div class="remarks"><strong>Remarks:</strong> <?= e($ms['remarks']) ?></div>
        <?php endif; ?>

        <div class="m-foot">
            <div class="verify">
                <?= $verifyQR ?>
                <div class="v-text">
                    Scan to verify.<br>
                    Verify at <?= e($host . '/verify-award') ?>
                </div>
            </div>
            <div class="sign">
                <div class="line"></div>
                Authorised signatory
            </div>
        </div>

        <div class="note">This is a computer-generated statement of marks.</div>
    </div>
</body>
</html>
