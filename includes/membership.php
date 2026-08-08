<?php
/**
 * =============================================================================
 *  Membership domain layer
 * =============================================================================
 *  The reusable spine for the Member Management module: membership IDs, QR
 *  verification tokens, tier/plan lookups, effective status, activation,
 *  renewal (+ payment records) and the per-member audit trail.
 *
 *  Loaded globally by bootstrap.php. The heavier, endpoint-specific pieces
 *  (QR encoder, card renderer, SMS + Cashfree adapters) are required on demand
 *  from the pages that need them, not here.
 * =============================================================================
 */

declare(strict_types=1);

/* =============================================================================
 |  TIERS / PLANS
 |============================================================================*/

/** All plans, cheapest tier first. Active-only by default. */
function membership_plans_all(bool $activeOnly = true): array
{
    $where = $activeOnly ? 'status = 1' : '1=1';
    return db_all(
        "SELECT * FROM membership_plans WHERE $where ORDER BY tier_level ASC, sort_order ASC, id ASC"
    );
}

/** A single plan row (request-cached), or null. */
function membership_plan(?int $planId): ?array
{
    static $cache = [];
    $planId = (int) $planId;
    if ($planId <= 0) {
        return null;
    }
    if (!array_key_exists($planId, $cache)) {
        $cache[$planId] = find('membership_plans', $planId);
    }
    return $cache[$planId];
}

/** Accent colour for a plan's ID card / tier badge. */
function membership_tier_color(?array $plan): string
{
    if (!empty($plan['color'])) {
        return $plan['color'];
    }
    $name = strtolower((string) ($plan['name'] ?? ''));
    foreach ([
        'platinum' => '#4b5563',
        'diamond'  => '#084881',
        'gold'     => '#b45309',
        'silver'   => '#64748b',
        'bronze'   => '#92400e',
        'basic'    => '#063566',
        'partner'  => '#084881',
    ] as $needle => $hex) {
        if (str_contains($name, $needle)) {
            return $hex;
        }
    }
    return '#063566';
}

/** Human tier label for a member (plan name, or a sensible fallback). */
function member_tier_label(array $member): string
{
    $plan = membership_plan((int) ($member['plan_id'] ?? 0));
    return $plan['name'] ?? 'Member';
}

/* =============================================================================
 |  MEMBERSHIP ID + QR TOKEN
 |============================================================================*/

/** Build a human membership ID, e.g. PWF-2026-00042. */
function member_generate_code(int $id): string
{
    $prefix = strtoupper((string) get_setting('membership_code_prefix', 'PWF'));
    $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'PWF';
    return sprintf('%s-%s-%05d', $prefix, date('Y'), $id);
}

/** A fresh, unguessable, table-unique QR token. */
function member_new_qr_token(): string
{
    do {
        $token = bin2hex(random_bytes(16)); // 32 hex chars
    } while (find_by('members', 'qr_token', $token));
    return $token;
}

/**
 * Ensure a member has both a membership code and a QR token, persisting any
 * that were missing. Returns the (possibly updated) member row.
 */
function member_ensure_identity(array $member): array
{
    $update = [];
    if (empty($member['member_code'])) {
        $update['member_code'] = member_generate_code((int) $member['id']);
    }
    if (empty($member['qr_token'])) {
        $update['qr_token'] = member_new_qr_token();
    }
    if ($update) {
        db_update('members', $update, 'id = :id', [':id' => (int) $member['id']]);
        $member = array_merge($member, $update);
    }
    return $member;
}

/** Absolute public verification URL encoded into a member's QR code. */
function member_verify_url(array $member): string
{
    return abs_url('verify-member?token=' . urlencode((string) ($member['qr_token'] ?? '')));
}

/* =============================================================================
 |  EFFECTIVE STATUS
 |============================================================================*/

/** True when the membership has a past expiry date. */
function member_is_expired(array $member): bool
{
    if (empty($member['expiry_date'])) {
        return false;
    }
    return strtotime((string) $member['expiry_date']) < strtotime(date('Y-m-d'));
}

/**
 * The real membership state, reconciling the stored flag with the expiry date.
 * Returns one of: none | active | expired | cancelled.
 */
function member_effective_status(array $member): string
{
    $stored = (string) ($member['membership_status'] ?? 'none');
    if ($stored === 'none' || $stored === 'cancelled') {
        return $stored;
    }
    return member_is_expired($member) ? 'expired' : 'active';
}

/** Whole days until expiry (negative if past, null if no expiry set). */
function member_days_left(array $member): ?int
{
    if (empty($member['expiry_date'])) {
        return null;
    }
    $secs = strtotime((string) $member['expiry_date']) - strtotime(date('Y-m-d'));
    return (int) floor($secs / 86400);
}

/** Display label for an effective status. */
function membership_status_label(string $status): string
{
    return [
        'none'      => 'Not enrolled',
        'active'    => 'Active',
        'expired'   => 'Expired',
        'cancelled' => 'Cancelled',
    ][$status] ?? ucfirst($status);
}

/** Pill CSS class for an effective status (matches the admin theme). */
function membership_status_pill(string $status): string
{
    return [
        'none'      => 'pill-gray',
        'active'    => 'pill-green',
        'expired'   => 'pill-red',
        'cancelled' => 'pill-amber',
    ][$status] ?? 'pill-gray';
}

/* =============================================================================
 |  AUDIT TRAIL
 |============================================================================*/

/**
 * Append a row to a member's audit trail. Best-effort; never throws.
 * $changes is an assoc array of field => [old, new].
 */
function member_audit_log(int $memberId, string $action, array $changes = [], ?string $note = null, ?int $userId = null): void
{
    try {
        db_insert('member_audit', [
            'member_id'  => $memberId,
            'user_id'    => $userId ?? ($_SESSION['user']['id'] ?? null),
            'action'     => $action,
            'changes'    => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'note'       => $note,
            'ip_address' => client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('[member_audit] ' . $e->getMessage());
    }
}

/** Fetch a member's audit trail, newest first. */
function member_audit_trail(int $memberId, int $limit = 50): array
{
    return db_all(
        'SELECT a.*, u.name AS admin_name
           FROM member_audit a
      LEFT JOIN users u ON u.id = a.user_id
          WHERE a.member_id = :m
       ORDER BY a.id DESC
          LIMIT ' . max(1, $limit),
        [':m' => $memberId]
    );
}

/* =============================================================================
 |  ACTIVATION + RENEWAL
 |============================================================================*/

/**
 * The [start, end] dates a new/renewed term would span for a member on a plan.
 * Renewals stack onto an unexpired term; expired/none start today.
 */
function membership_next_period(array $member, array $plan): array
{
    $days  = max(1, (int) $plan['duration_days']);
    $now   = strtotime(date('Y-m-d'));
    $start = date('Y-m-d');
    if (!empty($member['expiry_date']) && strtotime((string) $member['expiry_date']) > $now) {
        $start = date('Y-m-d', strtotime((string) $member['expiry_date']));
    }
    $end = date('Y-m-d', strtotime($start . ' +' . $days . ' days'));
    return [$start, $end];
}

/**
 * Activate / set a membership directly (admin, no payment record).
 * Assigns plan, join + expiry dates, membership ID and QR token. Returns the
 * refreshed member row, or null if member/plan not found.
 */
function member_activate(int $memberId, int $planId, ?string $start = null, ?int $adminId = null): ?array
{
    $member = find('members', $memberId);
    $plan   = membership_plan($planId);
    if (!$member || !$plan) {
        return null;
    }
    $member = member_ensure_identity($member);
    $start  = $start ?: date('Y-m-d');
    $days   = max(1, (int) $plan['duration_days']);
    // If the member already has an unexpired term, this is a tier change, not a
    // fresh activation — keep the remaining paid-up time instead of resetting it.
    $isActive = !empty($member['expiry_date']) && strtotime((string) $member['expiry_date']) > strtotime(date('Y-m-d'));
    $end = $isActive
        ? (string) $member['expiry_date']
        : date('Y-m-d', strtotime($start . ' +' . $days . ' days'));

    db_update('members', [
        'plan_id'           => $planId,
        'join_date'         => $member['join_date'] ?: $start,
        'expiry_date'       => $end,
        'membership_status' => 'active',
    ], 'id = :id', [':id' => $memberId]);

    member_audit_log($memberId, $isActive ? 'plan_changed' : 'activated', [
        'plan_id'     => [$member['plan_id'] ?? null, $planId],
        'expiry_date' => [$member['expiry_date'] ?? null, $end],
    ], ($isActive ? 'Plan changed to ' : 'Membership activated: ') . $plan['name'], $adminId);

    return find('members', $memberId);
}

/**
 * Create a renewal / payment record for a member. Does NOT extend the
 * membership by itself — call membership_apply_renewal() once it is paid.
 *
 * $opts: amount, method(cashfree|offline|manual|other), gateway, order_id,
 *        payment_id, status(pending|paid|...), note, created_by.
 *
 * Returns the new renewal id, or null if member/plan invalid.
 */
function membership_create_renewal(int $memberId, int $planId, array $opts = []): ?int
{
    $member = find('members', $memberId);
    $plan   = membership_plan($planId);
    if (!$member || !$plan) {
        return null;
    }
    [$start, $end] = membership_next_period($member, $plan);

    return db_insert('membership_renewals', [
        'member_id'    => $memberId,
        'plan_id'      => $planId,
        'amount'       => isset($opts['amount']) ? (float) $opts['amount'] : (float) $plan['price'],
        'currency'     => $opts['currency'] ?? 'INR',
        'period_start' => $start,
        'period_end'   => $end,
        'method'       => $opts['method'] ?? 'manual',
        'gateway'      => $opts['gateway'] ?? null,
        'order_id'     => $opts['order_id'] ?? null,
        'payment_id'   => $opts['payment_id'] ?? null,
        'status'       => $opts['status'] ?? 'pending',
        'note'         => $opts['note'] ?? null,
        'created_by'   => $opts['created_by'] ?? ($_SESSION['user']['id'] ?? null),
    ]);
}

/**
 * Apply a PAID renewal to the member: extend expiry to the renewal's period_end,
 * (re)assign the plan and mark the membership active. Idempotent. Returns true
 * when the membership term was set from this renewal.
 */
function membership_apply_renewal(int $renewalId, ?int $adminId = null): bool
{
    $r = find('membership_renewals', $renewalId);
    if (!$r || $r['status'] !== 'paid') {
        return false;
    }
    // Idempotency: atomically claim the renewal so a duplicate webhook / return
    // callback / admin re-mark can never add a second term. Only the winner runs.
    $claim = db_query(
        'UPDATE membership_renewals SET applied_at = NOW() WHERE id = :id AND applied_at IS NULL',
        [':id' => (int) $r['id']]
    );
    if ($claim->rowCount() === 0) {
        return true; // already applied
    }
    $member = find('members', (int) $r['member_id']);
    if (!$member) {
        return false;
    }
    $member = member_ensure_identity($member);

    // Extend from the member's CURRENT expiry (not the value frozen at creation),
    // so deferred and stacked renewals each add a full term.
    $plan = membership_plan((int) ($r['plan_id'] ?: $member['plan_id']));
    if ($plan) {
        [$start, $end] = membership_next_period($member, $plan);
    } else {
        $start = (string) $r['period_start'];
        $end   = (string) $r['period_end'];
    }

    db_update('members', [
        'plan_id'           => $r['plan_id'] ?: $member['plan_id'],
        'join_date'         => $member['join_date'] ?: $start,
        'expiry_date'       => $end,
        'membership_status' => 'active',
    ], 'id = :id', [':id' => (int) $member['id']]);

    // Persist the term actually granted (correcting any stale quote).
    db_update('membership_renewals', ['period_start' => $start, 'period_end' => $end], 'id = :id', [':id' => (int) $r['id']]);

    member_audit_log((int) $member['id'], 'renewed', [
        'expiry_date' => [$member['expiry_date'] ?? null, $end],
    ], 'Renewal applied (' . $r['method'] . ') — valid until ' . $end, $adminId);

    return true;
}

/**
 * Reverse an applied (paid) renewal when it is refunded/cancelled/failed: roll
 * the member's expiry back to this renewal's period_start — but only if this
 * renewal still owns the current term (its period_end == the member's current
 * expiry), so a later stacked renewal is never clobbered.
 */
function membership_revoke_renewal(int $renewalId, ?int $adminId = null): bool
{
    $r = find('membership_renewals', $renewalId);
    if (!$r) {
        return false;
    }
    $member = find('members', (int) $r['member_id']);
    if (!$member) {
        return false;
    }
    // Not the renewal that set the current expiry → leave the term untouched.
    if ((string) $member['expiry_date'] !== (string) $r['period_end']) {
        member_audit_log((int) $member['id'], 'renewal_reversed', [], 'Renewal #' . $renewalId
            . ' reversed (' . $r['status'] . '); term left unchanged — a later renewal owns the current period.', $adminId);
        return true;
    }
    $back   = (string) $r['period_start'];
    $status = $back !== '' && strtotime($back) > strtotime(date('Y-m-d')) ? 'active' : 'expired';
    db_update('members', [
        'expiry_date'       => $back ?: null,
        'membership_status' => $status,
    ], 'id = :id', [':id' => (int) $member['id']]);
    member_audit_log((int) $member['id'], 'renewal_reversed', [
        'expiry_date' => [$member['expiry_date'] ?? null, $back],
    ], 'Renewal #' . $renewalId . ' reversed (' . $r['status'] . ') — term rolled back', $adminId);
    return true;
}

/**
 * Transition a renewal's payment status. When it becomes 'paid', the membership
 * term is applied automatically. Returns true on success.
 */
function membership_mark_renewal(int $renewalId, string $status, ?string $paymentId = null, ?int $adminId = null): bool
{
    $r = find('membership_renewals', $renewalId);
    if (!$r) {
        return false;
    }
    $was  = (string) $r['status'];
    $data = ['status' => $status];
    if ($paymentId !== null) {
        $data['payment_id'] = $paymentId;
    }
    db_update('membership_renewals', $data, 'id = :id', [':id' => $renewalId]);

    if ($status === 'paid') {
        membership_apply_renewal($renewalId, $adminId);
    } elseif ($was === 'paid' && in_array($status, ['refunded', 'cancelled', 'failed'], true)) {
        membership_revoke_renewal($renewalId, $adminId);
    }
    return true;
}

/** A member's renewal history, newest first. */
function member_renewals(int $memberId, int $limit = 50): array
{
    return db_all(
        'SELECT r.*, p.name AS plan_name
           FROM membership_renewals r
      LEFT JOIN membership_plans p ON p.id = r.plan_id
          WHERE r.member_id = :m
       ORDER BY r.id DESC
          LIMIT ' . max(1, $limit),
        [':m' => $memberId]
    );
}

/* =============================================================================
 |  REMINDERS CONFIG
 |============================================================================*/

/** Parsed reminder-day thresholds, e.g. [30, 15, 7]. */
function membership_reminder_days(): array
{
    $raw  = (string) get_setting('membership_reminder_days', '30,15,7');
    $days = array_filter(array_map('intval', array_map('trim', explode(',', $raw))), fn($d) => $d > 0);
    $days = array_values(array_unique($days));
    rsort($days);
    return $days ?: [30, 15, 7];
}
