<?php
/**
 * =============================================================================
 *  Admin — Student ID card endpoint (PDF / PNG / printable page)
 * =============================================================================
 *  ?id=..&format=pdf|png|print
 *    pdf   → application/pdf attachment (student card as a single-page PDF)
 *    png   → image/png inline (raw card image)
 *    print → standalone print-ready page embedding the PNG (default)
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/student_card.php';
require_admin();

$id     = (int) get('id', 0);
$format = (string) get('format', 'print');

$student = find('school_students', $id);
if (!$student) {
    set_flash('error', 'Student not found.');
    redirect(admin_url('school-students'));
}
$student  = student_ensure_full($student);
$fileBase = 'student-' . ($student['student_code'] ?: $id);

if ($format === 'pdf') {
    $pdf = student_card_pdf($student);
    log_activity('view', 'students', 'ID card');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if ($format === 'png') {
    $png = student_card_png($student);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="' . $fileBase . '.png"');
    echo $png;
    exit;
}

/* -------- Printable HTML page (default) -------- */
$backUrl = admin_url('student-profile?id=' . $id);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Student ID Card · <?= e($student['name']) ?></title>
    <style>
        body{margin:0;min-height:100vh;background:#eef2f7;font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#063566;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
        .card-wrap img{max-width:100%;height:auto;border-radius:16px;box-shadow:0 10px 30px rgba(6,53,102,.18);}
        @media print{ .toolbar{display:none;} body{background:#fff;} .card-wrap img{box-shadow:none;} }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=pdf">Download PDF</a>
        <a class="btn btn-o" href="?id=<?= $id ?>&format=png" download>Download PNG</a>
        <a class="btn btn-o" href="<?= e($backUrl) ?>">← Back</a>
    </div>

    <div class="card-wrap">
        <img src="?id=<?= $id ?>&format=png" alt="Student ID card · <?= e($student['name']) ?>">
    </div>
</body>
</html>
