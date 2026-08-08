<?php
/**
 * =============================================================================
 *  Campaign domain layer (5.8)
 * =============================================================================
 *  Goal progress, raised/donor recompute from completed donations, milestone
 *  markers, auto-expiry (goal reached / end date), and donor notifications for
 *  campaign updates. Loaded by bootstrap.php.
 * =============================================================================
 */

declare(strict_types=1);

/** Progress snapshot for a campaign row. */
function campaign_progress(array $c): array
{
    $goal   = (float) ($c['goal_amount'] ?? 0);
    $raised = (float) ($c['raised_amount'] ?? 0);
    $pct    = $goal > 0 ? min(100.0, round($raised / $goal * 100, 1)) : ($raised > 0 ? 100.0 : 0.0);
    return [
        'goal'    => $goal,
        'raised'  => $raised,
        'percent' => $pct,
        'donors'  => (int) ($c['donor_count'] ?? 0),
        'remaining' => max(0.0, $goal - $raised),
    ];
}

/** Recompute raised_amount + donor_count from completed donations, then expiry. */
function campaign_recompute(int $campaignId): void
{
    // Net partial refunds out of the raised total (refund_amount accumulates on
    // the still-'completed' donation); fully refunded donations flip to
    // 'refunded' and drop out entirely.
    $row = db_row(
        "SELECT COALESCE(SUM(amount - COALESCE(refund_amount,0)),0) AS total, COUNT(*) AS cnt
           FROM donations WHERE campaign_id = :c AND status = 'completed'",
        [':c' => $campaignId]
    );
    db_update('campaigns', [
        'raised_amount' => (float) ($row['total'] ?? 0),
        'donor_count'   => (int) ($row['cnt'] ?? 0),
    ], 'id = :id', [':id' => $campaignId]);

    campaign_check_expiry($campaignId);
}

/**
 * Auto-close a campaign when the goal is reached or the end date has passed,
 * firing a thank-you notice to donors on the goal-reached transition.
 */
function campaign_check_expiry(int $campaignId): bool
{
    $c = find('campaigns', $campaignId);
    if (!$c || $c['status'] === 'completed' || $c['status'] === 'draft') {
        return false;
    }
    $goalReached = (float) $c['goal_amount'] > 0 && (float) $c['raised_amount'] >= (float) $c['goal_amount'];
    $ended = !empty($c['end_date']) && strtotime((string) $c['end_date']) < strtotime(date('Y-m-d'));
    if (!$goalReached && !$ended) {
        return false;
    }
    db_update('campaigns', [
        'status'    => 'completed',
        'closed_at' => date('Y-m-d H:i:s'),
    ], 'id = :id', [':id' => $campaignId]);

    if ($goalReached) {
        campaign_thankyou_blast($campaignId);
    }
    return true;
}

/** Email all donors of a campaign a thank-you when it hits its goal. */
function campaign_thankyou_blast(int $campaignId): int
{
    $c = find('campaigns', $campaignId);
    if (!$c) {
        return 0;
    }
    $donors = db_all(
        "SELECT DISTINCT email, donor_name FROM donations
          WHERE campaign_id = :c AND status = 'completed' AND email IS NOT NULL AND email <> ''",
        [':c' => $campaignId]
    );
    $sent = 0;
    foreach ($donors as $d) {
        $ok = send_mail($d['email'], 'Goal reached — thank you!',
            '<p>Dear ' . e($d['donor_name']) . ',</p>'
            . '<p>Thanks to supporters like you, the campaign <strong>' . e($c['title'])
            . '</strong> has reached its goal of ' . money($c['goal_amount']) . '. '
            . 'Your generosity is making a real difference.</p>'
            . '<p>With gratitude,<br>' . e(get_setting('site_name', SITE_NAME)) . '</p>');
        $sent += $ok ? 1 : 0;
    }
    return $sent;
}

/** Milestone markers ([amount, percent, reached]) from the JSON milestones. */
function campaign_milestones(array $c): array
{
    $goal   = (float) ($c['goal_amount'] ?? 0);
    $raised = (float) ($c['raised_amount'] ?? 0);
    $out = [];
    foreach (json_column($c['milestones']) as $amt) {
        $amt = (float) $amt;
        if ($amt <= 0) {
            continue;
        }
        $out[] = [
            'amount'  => $amt,
            'percent' => $goal > 0 ? min(100.0, round($amt / $goal * 100, 1)) : 0.0,
            'reached' => $raised >= $amt,
        ];
    }
    usort($out, static fn($a, $b) => $a['amount'] <=> $b['amount']);
    return $out;
}

/** Notify a campaign's donors of a posted update. Returns count emailed. */
function campaign_update_notify(int $updateId): int
{
    $u = find('campaign_updates', $updateId);
    if (!$u) {
        return 0;
    }
    $c = find('campaigns', (int) $u['campaign_id']);
    $donors = db_all(
        "SELECT DISTINCT email, donor_name FROM donations
          WHERE campaign_id = :c AND status = 'completed' AND email IS NOT NULL AND email <> ''",
        [':c' => (int) $u['campaign_id']]
    );
    $sent = 0;
    foreach ($donors as $d) {
        $ok = send_mail($d['email'], 'Update: ' . $u['title'],
            '<p>Dear ' . e($d['donor_name']) . ',</p>'
            . '<p>A new update on <strong>' . e($c['title'] ?? 'the campaign') . '</strong> you supported:</p>'
            . '<h3>' . e($u['title']) . '</h3><p>' . nl2br(e((string) $u['body'])) . '</p>'
            . '<p>Thank you for your continued support.</p>');
        $sent += $ok ? 1 : 0;
    }
    db_update('campaign_updates', ['notified' => 1], 'id = :id', [':id' => $updateId]);
    return $sent;
}

/* -------- pills -------- */
function campaign_status_pill(string $s): string
{
    return ['active' => 'pill-green', 'completed' => 'pill-blue', 'paused' => 'pill-amber', 'draft' => 'pill-gray'][$s] ?? 'pill-gray';
}

/** Active campaigns for dropdowns: [id => title]. */
function campaign_options(): array
{
    $out = [];
    foreach (db_all("SELECT id, title FROM campaigns WHERE deleted_at IS NULL ORDER BY title") as $r) {
        $out[(int) $r['id']] = $r['title'];
    }
    return $out;
}
