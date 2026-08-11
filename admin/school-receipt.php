<?php
/**
 * =============================================================================
 *  Admin — Printable school fee receipt (standalone, no admin chrome)
 * =============================================================================
 *  ?id=<school_fee_payments id>. Renders a clean, print-ready receipt for a
 *  single fee payment. Browser print-to-PDF — no PDF library required.
 * =============================================================================
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) get('id', 0);

$payment = find('school_fee_payments', $id);
if (!$payment) {
    set_flash('error', 'Fee payment not found.');
    redirect('/admin/school-fees');
}

$row = db_row(
    "SELECT p.*,
            s.name  AS school_name,  s.code AS school_code,
            st.name AS student_name, st.student_code AS student_code
       FROM school_fee_payments p
       LEFT JOIN schools s          ON s.id  = p.school_id
       LEFT JOIN school_students st ON st.id = p.student_id
      WHERE p.id = :id",
    [':id' => $id]
);
if (!$row) {
    set_flash('error', 'Fee payment not found.');
    redirect('/admin/school-fees');
}

$methods = [
    'cash'   => 'Cash',
    'upi'    => 'UPI',
    'cheque' => 'Cheque',
    'bank'   => 'Bank transfer',
    'online' => 'Online',
    'other'  => 'Other',
];
$statuses = [
    'pending' => 'Pending',
    'partial' => 'Partial',
    'paid'    => 'Paid',
    'waived'  => 'Waived',
];

$siteName  = (string) get_setting('site_name', SITE_NAME);
$receiptNo = $row['receipt_no'] ?: school_receipt_no($id);
$dateStr   = format_date($row['paid_at'] ?: $row['created_at'], 'd M Y, h:i A');

$amount   = (float) $row['amount'];
$paid     = (float) $row['amount_paid'];
$due      = ($row['status'] ?? '') === 'waived' ? 0.0 : $amount - $paid;

$schoolId = (int) $row['school_id'];
$backUrl  = admin_url('school-fees?school=' . $schoolId);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Fee Receipt · <?= e($receiptNo) ?></title>
    <style>
        /* Standalone by design — no app stylesheets, so print output carries no
           panel chrome. Palette written out by hand; sizes, padding and the print
           block are untouched. The cool blue-greys this receipt shipped with
           (#eef2f7 field, #1f2937 ink, #64748b / #475569 / #94a3b8 labels,
           #e2e8f0 / #cbd5e1 rules) were outside the brand and are re-pointed onto
           text #151818, muted #4B6754 and border #C1CCB3. */
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
        .receipt{width:100%;max-width:720px;background:#fff;border:1px solid #C1CCB3;border-radius:16px;
                 padding:34px 38px;box-shadow:0 4px 12px -2px rgba(21,24,24,.08);}
        .r-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
                border-bottom:2px solid #151818;padding-bottom:16px;margin-bottom:22px;}
        .r-org{font-size:1.35rem;font-weight:800;letter-spacing:.2px;margin:0;}
        .r-tag{font-size:.8rem;color:#4B6754;margin-top:4px;text-transform:uppercase;letter-spacing:1.5px;}
        .r-title{font-size:1.05rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;text-align:right;}
        .r-meta{text-align:right;font-size:.85rem;color:#4B6754;margin-top:6px;line-height:1.6;}
        .r-meta strong{color:#151818;}
        .parties{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:24px;}
        .party{flex:1 1 240px;}
        .party h4{margin:0 0 6px;font-size:.72rem;text-transform:uppercase;letter-spacing:1.2px;color:#4B6754;font-weight:700;}
        .party .name{font-size:1.02rem;font-weight:700;}
        .party .sub{font-size:.85rem;color:#4B6754;margin-top:2px;}
        table{width:100%;border-collapse:collapse;margin:8px 0 20px;}
        th,td{padding:11px 14px;text-align:left;font-size:.9rem;}
        thead th{background:#F8FCF8;color:#4B6754;text-transform:uppercase;font-size:.72rem;letter-spacing:1px;}
        tbody td{border-bottom:1px solid #C1CCB3;}
        tbody tr:last-child td{border-bottom:0;}
        .num{text-align:right;font-variant-numeric:tabular-nums;}
        .totals{margin-left:auto;width:100%;max-width:320px;}
        .totals td{padding:8px 14px;font-size:.92rem;}
        .totals .lbl{color:#4B6754;}
        .totals .grand td{border-top:2px solid #151818;font-weight:800;font-size:1rem;}
        /* Balance due and the status pill use the three status hues only, tinted
           from themselves. The balance figure is also labelled "Balance due" and
           each pill carries its word, so neither state rests on colour alone. */
        .due-open{color:#B3261E;} .due-clear{color:#2F8065;}
        .pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;
              text-transform:uppercase;letter-spacing:.5px;}
        .pill-paid{background:rgba(47,128,101,.12);color:#2F8065;} .pill-partial{background:rgba(178,106,0,.12);color:#B26A00;}
        .pill-pending{background:rgba(179,38,30,.10);color:#B3261E;} .pill-waived{background:rgba(75,103,84,.12);color:#4B6754;}
        .r-foot{margin-top:26px;padding-top:16px;border-top:1px dashed #C1CCB3;
                display:flex;justify-content:space-between;align-items:flex-end;gap:20px;
                font-size:.78rem;color:#4B6754;}
        .sign{text-align:center;color:#4B6754;}
        .sign .line{width:170px;border-top:1px solid #C1CCB3;margin-bottom:5px;}
        @media print{
            body{background:#fff;padding:0;}
            .toolbar{display:none;}
            .receipt{border:0;border-radius:0;box-shadow:none;max-width:none;padding:24px 8px;}
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-p" onclick="window.print()">Print</button>
        <a class="btn btn-o" href="<?= e($backUrl) ?>">&larr; Back to fees</a>
    </div>

    <div class="receipt">
        <div class="r-head">
            <div>
                <h1 class="r-org"><?= e($siteName) ?></h1>
                <div class="r-tag">Fee Receipt</div>
            </div>
            <div>
                <div class="r-title">Fee Receipt</div>
                <div class="r-meta">
                    <div>Receipt No: <strong><?= e($receiptNo) ?></strong></div>
                    <div>Date: <strong><?= e($dateStr) ?></strong></div>
                </div>
            </div>
        </div>

        <div class="parties">
            <div class="party">
                <h4>School</h4>
                <div class="name"><?= e($row['school_name'] ?? '—') ?></div>
                <?php if (!empty($row['school_code'])): ?>
                    <div class="sub">Code: <?= e($row['school_code']) ?></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($row['student_name'])): ?>
            <div class="party">
                <h4>Student</h4>
                <div class="name"><?= e($row['student_name']) ?></div>
                <?php if (!empty($row['student_code'])): ?>
                    <div class="sub">Code: <?= e($row['student_code']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Fee particulars</th>
                    <th class="num">Method</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= e($row['title']) ?></td>
                    <td class="num"><?= e($methods[$row['method']] ?? ucfirst((string) $row['method'])) ?></td>
                    <td class="num"><?= e(money($amount, '₹', 2)) ?></td>
                </tr>
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="lbl">Total amount</td>
                <td class="num"><?= e(money($amount, '₹', 2)) ?></td>
            </tr>
            <tr>
                <td class="lbl">Amount paid</td>
                <td class="num"><?= e(money($paid, '₹', 2)) ?></td>
            </tr>
            <tr class="grand">
                <td>Balance due</td>
                <td class="num <?= $due > 0.001 ? 'due-open' : 'due-clear' ?>"><?= e(money($due, '₹', 2)) ?></td>
            </tr>
        </table>

        <div class="r-foot">
            <div>
                Status:
                <span class="pill pill-<?= e($row['status']) ?>"><?= e($statuses[$row['status']] ?? ucfirst((string) $row['status'])) ?></span>
                <div style="margin-top:10px;">This is a computer-generated receipt.</div>
            </div>
            <div class="sign">
                <div class="line"></div>
                Authorised signatory
            </div>
        </div>
    </div>
</body>
</html>
