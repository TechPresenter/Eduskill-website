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
        /* Standalone by design — no app stylesheets, so print output carries no
           panel chrome. The palette is written out by hand here; every size,
           padding and the @page block below are untouched. The cool blue-greys
           this document shipped with (#eef2f7 field, #1f2937 ink, #64748b /
           #475569 / #94a3b8 labels, #e2e8f0 / #cbd5e1 rules) were all outside the
           brand and are re-pointed onto text #151818, muted #4B6754 and border
           #C1CCB3. */
        *{box-sizing:border-box;}
        body{margin:0;min-height:100vh;background:#F8FCF8;color:#151818;
             font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:10px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#0B4E3D;color:#fff;} .btn-o{background:#fff;color:#151818;border:1px solid #C1CCB3;}
        /* 16px radius = the design system's largest step (was 14px); the drop
           shadow is screen-only and now sits in the subtle band. */
        .sheet{width:100%;max-width:760px;background:#fff;border:1px solid #C1CCB3;border-radius:16px;
               padding:36px 40px;box-shadow:0 4px 12px -2px rgba(21,24,24,.08);}
        .m-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
                border-bottom:2px solid #151818;padding-bottom:16px;margin-bottom:24px;}
        .m-org{font-size:1.4rem;font-weight:800;letter-spacing:.2px;margin:0;}
        .m-tag{font-size:.78rem;color:#4B6754;margin-top:4px;text-transform:uppercase;letter-spacing:1.5px;}
        .m-title{font-size:1.05rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;text-align:right;}
        .m-meta{text-align:right;font-size:.85rem;color:#4B6754;margin-top:6px;line-height:1.6;}
        .m-meta strong{color:#151818;}
        .student{display:flex;flex-wrap:wrap;gap:14px 30px;margin-bottom:24px;}
        .field{min-width:150px;}
        .field h4{margin:0 0 3px;font-size:.68rem;text-transform:uppercase;letter-spacing:1.2px;color:#4B6754;font-weight:700;}
        .field .val{font-size:1rem;font-weight:700;}
        table.marks{width:100%;border-collapse:collapse;margin:8px 0 22px;}
        table.marks th,table.marks td{padding:11px 14px;text-align:left;font-size:.9rem;}
        table.marks thead th{background:#F8FCF8;color:#4B6754;text-transform:uppercase;font-size:.72rem;letter-spacing:1px;}
        table.marks tbody td{border-bottom:1px solid #C1CCB3;}
        table.marks tbody tr:last-child td{border-bottom:0;}
        .num{text-align:right;font-variant-numeric:tabular-nums;}
        .center{text-align:center;}
        .totals{margin-left:auto;width:100%;max-width:340px;}
        .totals td{padding:8px 14px;font-size:.92rem;}
        .totals .lbl{color:#4B6754;}
        .totals .grand td{border-top:2px solid #151818;font-weight:800;font-size:1rem;}
        /* Result pills: the three status hues, tinted from themselves so no fourth
           colour family enters. Each pill also carries its word ("Pass" / "Fail" /
           "Pending"), so the state never rests on colour alone. */
        .pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;
              text-transform:uppercase;letter-spacing:.5px;}
        .pill-pass{background:rgba(47,128,101,.12);color:#2F8065;} .pill-fail{background:rgba(179,38,30,.10);color:#B3261E;}
        .pill-pending{background:rgba(75,103,84,.12);color:#4B6754;}
        .remarks{margin:18px 0 4px;font-size:.9rem;color:#4B6754;}
        .remarks strong{color:#151818;}
        .m-foot{margin-top:30px;padding-top:18px;border-top:1px dashed #C1CCB3;
                display:flex;justify-content:space-between;align-items:flex-end;gap:20px;}
        .verify{display:flex;align-items:flex-end;gap:12px;font-size:.74rem;color:#4B6754;}
        .verify svg{display:block;width:76px;height:76px;}
        .verify .v-text{line-height:1.5;}
        .sign{text-align:center;color:#4B6754;}
        .sign .line{width:180px;border-top:1px solid #C1CCB3;margin-bottom:5px;}
        .note{text-align:center;font-size:.72rem;color:#4B6754;margin-top:22px;}
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
                    <tr><td colspan="4" class="center" style="color:#4B6754;">No subjects recorded.</td></tr>
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
