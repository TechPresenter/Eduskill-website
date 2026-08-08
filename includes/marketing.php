<?php
/**
 * =============================================================================
 *  Marketing tools — referral tracking + coupon validation/redemption.
 * =============================================================================
 *  Loaded by bootstrap. ref_capture() runs on every public GET to remember an
 *  incoming ?ref=CODE; conversions are attributed with ref_record(). Coupons are
 *  validated with coupon_validate() and consumed with coupon_redeem().
 * =============================================================================
 */

declare(strict_types=1);

/* ------------------------------------------------------------- REFERRALS */

/** Remember an incoming ?ref=CODE (session + 30-day cookie) and count the click. */
function ref_capture(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !isset($_GET['ref'])) {
        return;
    }
    $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $_GET['ref']);
    if ($code === '') return;
    try {
        $row = find_by('referral_codes', 'code', $code);
        if ($row && (int) $row['status'] === 1) {
            if (($_SESSION['_ref'] ?? '') !== $code) {
                db_update('referral_codes', ['clicks' => (int) $row['clicks'] + 1], 'id = :id', [':id' => (int) $row['id']]);
            }
            $_SESSION['_ref'] = $code;
            @setcookie('pwf_ref', $code, ['expires' => time() + 2592000, 'path' => '/', 'samesite' => 'Lax']);
        }
    } catch (Throwable $e) {
        // referral_codes may be absent pre-migration — ignore.
    }
}

/** The currently-attributed referral code row (session/cookie), or null. */
function ref_active_code(): ?array
{
    $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_SESSION['_ref'] ?? ($_COOKIE['pwf_ref'] ?? '')));
    if ($code === '') return null;
    try {
        $row = find_by('referral_codes', 'code', $code);
        return ($row && (int) $row['status'] === 1) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Get (or create) a unique referral code for an owner (member/volunteer/user). */
function ref_code_for(string $type, int $id, string $name): array
{
    $existing = db_row("SELECT * FROM referral_codes WHERE owner_type = :t AND owner_id = :i LIMIT 1", [':t' => $type, ':i' => $id]);
    if ($existing) return $existing;
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', substr($name ?: $type, 0, 6)) ?: 'REF');
    do { $code = $base . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4)); } while (find_by('referral_codes', 'code', $code));
    $newId = db_insert('referral_codes', ['owner_type' => $type, 'owner_id' => $id, 'owner_name' => $name, 'code' => $code, 'status' => 1]);
    return find('referral_codes', $newId);
}

/** Record a conversion (signup/donation/membership/enrollment) for the captured referral. */
function ref_record(string $type, array $data = []): void
{
    $code = ref_active_code();
    if (!$code) return;
    $amount = (float) ($data['amount'] ?? 0);
    $reward = (float) ($data['reward'] ?? 0);
    try {
        db_insert('referral_conversions', [
            'code_id'   => (int) $code['id'],
            'type'      => in_array($type, ['signup', 'donation', 'membership', 'enrollment'], true) ? $type : 'signup',
            'ref_name'  => $data['name'] ?? null,
            'ref_email' => $data['email'] ?? null,
            'amount'    => $amount,
            'reward'    => $reward,
            'note'      => $data['note'] ?? null,
        ]);
        $upd = [];
        if ($type === 'signup')     { $upd['signups'] = (int) $code['signups'] + 1; }
        if ($type === 'donation')   { $upd['donations_count'] = (int) $code['donations_count'] + 1; $upd['donations_amount'] = (float) $code['donations_amount'] + $amount; }
        if ($reward > 0)            { $upd['reward_total'] = (float) $code['reward_total'] + $reward; }
        if ($upd) { db_update('referral_codes', $upd, 'id = :id', [':id' => (int) $code['id']]); }
    } catch (Throwable $e) {
        error_log('[ref_record] ' . $e->getMessage());
    }
}

/* -------------------------------------------------------------- COUPONS */

/** Look up a coupon by code (case-insensitive). */
function coupon_find(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    try { return find_by('coupons', 'code', $code) ?: null; } catch (Throwable $e) { return null; }
}

/** Compute the discount a coupon gives on an amount. */
function coupon_discount(array $c, float $amount): float
{
    return match ($c['discount_type']) {
        'waiver' => $amount,
        'fixed'  => min($amount, (float) $c['value']),
        default  => round($amount * min(100, (float) $c['value']) / 100, 2),
    };
}

/**
 * Validate a coupon for a context (course/membership/donation) + amount.
 * @return array{ok:bool, coupon?:array, discount?:float, final?:float, error?:string}
 */
function coupon_validate(string $code, string $context, float $amount, ?string $email = null): array
{
    $c = coupon_find($code);
    if (!$c)                       return ['ok' => false, 'error' => 'Invalid coupon code.'];
    if ((int) $c['status'] !== 1)  return ['ok' => false, 'error' => 'This coupon is inactive.'];
    $now = time();
    if (!empty($c['starts_at'])  && strtotime((string) $c['starts_at'])  > $now) return ['ok' => false, 'error' => 'This coupon is not active yet.'];
    if (!empty($c['expires_at']) && strtotime((string) $c['expires_at']) < $now) return ['ok' => false, 'error' => 'This coupon has expired.'];

    $map = ['course' => 'courses', 'membership' => 'memberships', 'donation' => 'donations'];
    if ($c['applies_to'] !== 'all' && $c['applies_to'] !== ($map[$context] ?? $context)) {
        return ['ok' => false, 'error' => 'This coupon does not apply to this purchase.'];
    }
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
        return ['ok' => false, 'error' => 'This coupon has reached its usage limit.'];
    }
    if ($amount < (float) $c['min_amount']) {
        return ['ok' => false, 'error' => 'A minimum amount of ' . number_format((float) $c['min_amount']) . ' is required.'];
    }
    if ($email && (int) $c['per_user_limit'] > 0) {
        $used = (int) db_value("SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = :c AND user_email = :e", [':c' => (int) $c['id'], ':e' => strtolower($email)]);
        if ($used >= (int) $c['per_user_limit']) return ['ok' => false, 'error' => 'You have already used this coupon.'];
    }
    $discount = coupon_discount($c, $amount);
    return ['ok' => true, 'coupon' => $c, 'discount' => $discount, 'final' => max(0, $amount - $discount)];
}

/** Record a coupon redemption + bump its usage. Call after a successful checkout. */
function coupon_redeem(array $c, string $context, float $amountBefore, float $discount, ?string $email = null, ?int $contextId = null): void
{
    try {
        db_insert('coupon_redemptions', [
            'coupon_id'     => (int) $c['id'],
            'code'          => $c['code'],
            'context'       => in_array($context, ['course', 'membership', 'donation', 'other'], true) ? $context : 'other',
            'context_id'    => $contextId,
            'user_email'    => $email ? strtolower($email) : null,
            'amount_before' => $amountBefore,
            'discount'      => $discount,
            'amount_after'  => max(0, $amountBefore - $discount),
        ]);
        db_update('coupons', ['used_count' => (int) $c['used_count'] + 1], 'id = :id', [':id' => (int) $c['id']]);
    } catch (Throwable $e) {
        error_log('[coupon_redeem] ' . $e->getMessage());
    }
}
