<?php
/**
 * =============================================================================
 *  Donation documents — 80G receipt + annual 80G/12A tax certificate
 * =============================================================================
 *  Standalone, print-ready HTML documents (browser print-to-PDF — no library).
 *  Required on demand by the receipt / certificate pages.
 * =============================================================================
 */

declare(strict_types=1);

/** Shared org header block for receipts/certificates. */
function donation_org_block(): array
{
    return [
        'name'    => get_setting('site_name', SITE_NAME),
        'address' => get_setting('contact_address', SITE_ADDRESS),
        'email'   => get_setting('contact_email', SITE_EMAIL),
        'phone'   => get_setting('contact_phone', SITE_PHONE),
        'pan'     => get_setting('org_pan', ''),
        'reg80g'  => get_setting('org_80g_number', ''),
        'reg12a'  => get_setting('org_12a_number', ''),
        'cin'     => SITE_CIN,
    ];
}

/** Standalone HTML 80G donation receipt for one donation row. */
function donation_receipt_html(array $d): string
{
    $org  = donation_org_block();
    $sym  = donation_symbol($d['currency'] ?? 'INR');
    $amt  = money($d['amount'], $sym, 2);
    $date = format_date($d['donated_at'] ?: $d['created_at'], 'd M Y');
    $campaign = !empty($d['campaign_id']) ? (find('campaigns', (int) $d['campaign_id'])['title'] ?? '') : '';
    $back = admin_url('donations');

    $rows = '';
    $rows .= '<tr><th>Receipt No.</th><td>' . e($d['receipt_no'] ?: '—') . '</td><th>Date</th><td>' . e($date) . '</td></tr>';
    $rows .= '<tr><th>Received from</th><td colspan="3">' . e($d['donor_name']) . '</td></tr>';
    if (!empty($d['address'])) { $rows .= '<tr><th>Address</th><td colspan="3">' . e($d['address']) . '</td></tr>'; }
    if (!empty($d['pan']))     { $rows .= '<tr><th>Donor PAN</th><td>' . e($d['pan']) . '</td><th>Mode</th><td>' . e($d['payment_method'] ?: '—') . '</td></tr>'; }
    if ($campaign !== '')      { $rows .= '<tr><th>Towards</th><td colspan="3">' . e($campaign) . '</td></tr>'; }
    if (!empty($d['transaction_id'])) { $rows .= '<tr><th>Txn ID</th><td colspan="3">' . e($d['transaction_id']) . '</td></tr>'; }

    ob_start(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Receipt <?= e($d['receipt_no']) ?></title>
<style>
@page{size:A4;margin:16mm;}
body{font-family:'Segoe UI',system-ui,sans-serif;color:#1f2937;margin:0;padding:26px;background:#eef2f7;}
.doc{max-width:760px;margin:0 auto;background:#fff;padding:38px;border:1px solid #e5e7eb;border-radius:10px;}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #0B4E3D;padding-bottom:14px;margin-bottom:20px;}
.org h1{margin:0;font-size:1.4rem;color:#1e3a8a;} .org p{margin:2px 0;font-size:.8rem;color:#6b7280;}
.badge{background:#eff6ff;color:#174D3D;padding:8px 14px;border-radius:8px;font-weight:800;text-align:center;font-size:.8rem;}
h2.title{text-align:center;letter-spacing:.1em;color:#111827;margin:6px 0 20px;}
table{width:100%;border-collapse:collapse;} table th,table td{padding:8px 10px;border:1px solid #e5e7eb;font-size:.86rem;text-align:left;vertical-align:top;}
table th{background:#f8fafc;width:130px;color:#374151;}
.amount{margin:22px 0;text-align:center;} .amount .v{font-size:2rem;font-weight:800;color:#1F5C48;}
.foot{margin-top:26px;font-size:.76rem;color:#6b7280;display:flex;justify-content:space-between;align-items:flex-end;}
.sign{text-align:center;} .sign .line{border-top:1px solid #9ca3af;width:180px;margin:34px auto 4px;}
.toolbar{max-width:760px;margin:0 auto 14px;display:flex;gap:8px;justify-content:flex-end;}
.btn{border:0;border-radius:8px;padding:9px 16px;font-weight:700;cursor:pointer;text-decoration:none;font-size:.86rem;}
.btn-p{background:#0B4E3D;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
@media print{.toolbar{display:none;}body{background:#fff;padding:0;}.doc{border:0;}}
</style></head><body>
<div class="toolbar">
    <button class="btn btn-p" onclick="window.print()">Print / Save PDF</button>
    <a class="btn btn-o" href="<?= e($back) ?>">← Back</a>
</div>
<div class="doc">
    <div class="head">
        <div class="org"><h1><?= e($org['name']) ?></h1>
            <p><?= e($org['address']) ?></p>
            <p><?= e($org['email']) ?> · <?= e($org['phone']) ?><?= $org['cin'] ? ' · CIN ' . e($org['cin']) : '' ?></p>
        </div>
        <div class="badge">80G<br>EXEMPT</div>
    </div>
    <h2 class="title">DONATION RECEIPT</h2>
    <table><tbody><?= $rows ?></tbody></table>
    <div class="amount"><div>Amount received (with thanks)</div><div class="v"><?= e($amt) ?></div></div>
    <div class="foot">
        <div>
            <?php if ($org['reg80g']): ?><div>80G Reg. No.: <strong><?= e($org['reg80g']) ?></strong></div><?php endif; ?>
            <?php if ($org['reg12a']): ?><div>12A Reg. No.: <strong><?= e($org['reg12a']) ?></strong></div><?php endif; ?>
            <?php if ($org['pan']): ?><div>Org PAN: <strong><?= e($org['pan']) ?></strong></div><?php endif; ?>
            <p>Donations are exempt under Section 80G of the Income Tax Act, 1961.</p>
        </div>
        <div class="sign"><div class="line"></div>Authorised Signatory</div>
    </div>
</div>
</body></html>
    <?php
    return (string) ob_get_clean();
}

/**
 * Annual 80G/12A tax exemption certificate aggregating a donor's completed
 * donations in a financial/calendar year.
 * @param array $donations completed donation rows for the donor+year
 * @param array $donor     ['name','pan','address','email']
 */
function donation_80g_certificate_html(array $donations, array $donor, int $year): string
{
    $org   = donation_org_block();
    $total = 0.0;
    $lines = '';
    foreach ($donations as $d) {
        $total += (float) $d['amount'];
        $lines .= '<tr><td>' . e(format_date($d['donated_at'] ?: $d['created_at'], 'd M Y')) . '</td>'
            . '<td>' . e($d['receipt_no'] ?: '—') . '</td>'
            . '<td>' . e($d['payment_method'] ?: '—') . '</td>'
            . '<td style="text-align:right;">' . e(money($d['amount'], '₹', 2)) . '</td></tr>';
    }
    ob_start(); ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>80G Certificate <?= (int) $year ?></title>
<style>
@page{size:A4;margin:16mm;}
body{font-family:'Segoe UI',system-ui,sans-serif;color:#1f2937;margin:0;padding:26px;background:#eef2f7;}
.doc{max-width:780px;margin:0 auto;background:#fff;padding:40px;border:1px solid #e5e7eb;border-radius:10px;}
.head{text-align:center;border-bottom:3px solid #b45309;padding-bottom:14px;margin-bottom:18px;}
.head h1{margin:0;color:#92400e;font-size:1.5rem;} .head p{margin:2px 0;font-size:.8rem;color:#6b7280;}
h2.title{text-align:center;letter-spacing:.08em;margin:8px 0 18px;}
p.intro{font-size:.92rem;line-height:1.6;}
table{width:100%;border-collapse:collapse;margin:16px 0;} th,td{border:1px solid #e5e7eb;padding:8px 10px;font-size:.85rem;} th{background:#fff7ed;}
.tot{text-align:right;font-weight:800;font-size:1.05rem;color:#92400e;margin-top:6px;}
.foot{margin-top:30px;font-size:.78rem;color:#6b7280;display:flex;justify-content:space-between;align-items:flex-end;}
.sign{text-align:center;} .sign .line{border-top:1px solid #9ca3af;width:190px;margin:34px auto 4px;}
.toolbar{max-width:780px;margin:0 auto 14px;display:flex;gap:8px;justify-content:flex-end;}
.btn{border:0;border-radius:8px;padding:9px 16px;font-weight:700;cursor:pointer;text-decoration:none;font-size:.86rem;}
.btn-p{background:#b45309;color:#fff;} .btn-o{background:#fff;color:#1f2937;border:1px solid #cbd5e1;}
@media print{.toolbar{display:none;}body{background:#fff;padding:0;}.doc{border:0;}}
</style></head><body>
<div class="toolbar">
    <button class="btn btn-p" onclick="window.print()">Print / Save PDF</button>
    <a class="btn btn-o" href="<?= e(admin_url('tax-certificates')) ?>">← Back</a>
</div>
<div class="doc">
    <div class="head"><h1><?= e($org['name']) ?></h1>
        <p><?= e($org['address']) ?></p>
        <p><?= e($org['email']) ?> · <?= e($org['phone']) ?></p>
    </div>
    <h2 class="title">80G TAX EXEMPTION CERTIFICATE — <?= (int) $year ?></h2>
    <p class="intro">This is to certify that <strong><?= e($donor['name']) ?></strong>
        <?= !empty($donor['pan']) ? '(PAN: <strong>' . e($donor['pan']) . '</strong>)' : '' ?>
        has made the following donation(s) to <?= e($org['name']) ?> during <?= (int) $year ?>,
        eligible for deduction under Section 80G of the Income Tax Act, 1961.</p>
    <table><thead><tr><th>Date</th><th>Receipt No.</th><th>Mode</th><th style="text-align:right;">Amount</th></tr></thead>
        <tbody><?= $lines ?></tbody></table>
    <div class="tot">Total donated in <?= (int) $year ?>: <?= e(money($total, '₹', 2)) ?></div>
    <div class="foot">
        <div>
            <?php if ($org['reg80g']): ?><div>80G Reg. No.: <strong><?= e($org['reg80g']) ?></strong></div><?php endif; ?>
            <?php if ($org['reg12a']): ?><div>12A Reg. No.: <strong><?= e($org['reg12a']) ?></strong></div><?php endif; ?>
            <?php if ($org['pan']): ?><div>Org PAN: <strong><?= e($org['pan']) ?></strong></div><?php endif; ?>
        </div>
        <div class="sign"><div class="line"></div>Authorised Signatory</div>
    </div>
</div>
</body></html>
    <?php
    return (string) ob_get_clean();
}
