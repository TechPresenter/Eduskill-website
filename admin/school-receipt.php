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
        *{box-sizing:border-box;}
        body{margin:0;min-height:100vh;background:#eef2f7;color:#1f2937;
             font-family:system-ui,Segoe UI,Roboto,sans-serif;
             display:flex;flex-direction:column;align-items:center;gap:22px;padding:30px;}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;}
        .btn{border:0;border-radius:9px;padding:10px 18px;font-weight:700;cursor:pointer;text-decoration:none;
             font-size:.92rem;display:inline-flex;align-items:center;gap:6px;}
        .btn-p{background:#063566;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
        .receipt{width:100%;max-width:720px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;
                 padding:34px 38px;box-shadow:0 10px 30px rgba(6,53,102,.08);}
        .r-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;
                border-bottom:2px solid #1f2937;padding-bottom:16px;margin-bottom:22px;}
        .r-org{font-size:1.35rem;font-weight:800;letter-spacing:.2px;margin:0;}
        .r-tag{font-size:.8rem;color:#64748b;margin-top:4px;text-transform:uppercase;letter-spacing:1.5px;}
        .r-title{font-size:1.05rem;font-weight:800;text-transform:uppercase;letter-spacing:2px;text-align:right;}
        .r-meta{text-align:right;font-size:.85rem;color:#475569;margin-top:6px;line-height:1.6;}
        .r-meta strong{color:#1f2937;}
        .parties{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:24px;}
        .party{flex:1 1 240px;}
        .party h4{margin:0 0 6px;font-size:.72rem;text-transform:uppercase;letter-spacing:1.2px;color:#64748b;font-weight:700;}
        .party .name{font-size:1.02rem;font-weight:700;}
        .party .sub{font-size:.85rem;color:#64748b;margin-top:2px;}
        table{width:100%;border-collapse:collapse;margin:8px 0 20px;}
        th,td{padding:11px 14px;text-align:left;font-size:.9rem;}
        thead th{background:#f1f5f9;color:#475569;text-transform:uppercase;font-size:.72rem;letter-spacing:1px;}
        tbody td{border-bottom:1px solid #eef2f7;}
        tbody tr:last-child td{border-bottom:0;}
        .num{text-align:right;font-variant-numeric:tabular-nums;}
        .totals{margin-left:auto;width:100%;max-width:320px;}
        .totals td{padding:8px 14px;font-size:.92rem;}
        .totals .lbl{color:#64748b;}
        .totals .grand td{border-top:2px solid #1f2937;font-weight:800;font-size:1rem;}
        .due-open{color:#b91c1c;} .due-clear{color:#308629;}
        .pill{display:inline-block;padding:3px 12px;border-radius:999px;font-size:.75rem;font-weight:700;
              text-transform:uppercase;letter-spacing:.5px;}
        .pill-paid{background:#dcfce7;color:#308629;} .pill-partial{background:#fef3c7;color:#b45309;}
        .pill-pending{background:#fee2e2;color:#b91c1c;} .pill-waived{background:#e2e8f0;color:#475569;}
        .r-foot{margin-top:26px;padding-top:16px;border-top:1px dashed #cbd5e1;
                display:flex;justify-content:space-between;align-items:flex-end;gap:20px;
                font-size:.78rem;color:#94a3b8;}
        .sign{text-align:center;color:#475569;}
        .sign .line{width:170px;border-top:1px solid #94a3b8;margin-bottom:5px;}
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
