<?php
/**
 * Coupon validation endpoint. POST { code, context, amount, email? }.
 * Returns the discount + final amount so any checkout form can apply a coupon.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) json_error('Method not allowed.', [], 405);
require_csrf();

$code    = (string) post('code', '');
$context = in_array(post('context'), ['course', 'membership', 'donation'], true) ? post('context') : 'donation';
$amount  = max(0, (float) post('amount', 0));
$email   = strtolower(clean(post('email', '')));

$r = coupon_validate($code, $context, $amount, $email ?: null);
if (!$r['ok']) {
    json_error($r['error'] ?? 'This coupon cannot be applied.');
}

json_success('Coupon applied.', [
    'code'     => $r['coupon']['code'],
    'discount' => round($r['discount'], 2),
    'final'    => round($r['final'], 2),
    'label'    => $r['coupon']['discount_type'] === 'percent'
        ? ((float) $r['coupon']['value']) . '% off'
        : ($r['coupon']['discount_type'] === 'waiver' ? 'Fully waived' : number_format($r['discount']) . ' off'),
]);
