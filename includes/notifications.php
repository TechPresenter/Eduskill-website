<?php
/**
 * =============================================================================
 *  In-app notifications — real-time notification centre for admins/users.
 * =============================================================================
 *  notify()             create a notification (targeted or broadcast)
 *  notif_unread_count() unread badge count for a user (incl. broadcasts)
 *  notif_recent()       recent notifications for the bell dropdown
 *  notif_mark_read()    mark one / all read
 *
 *  A NULL user_id is a broadcast to every admin. All helpers fail safe.
 * =============================================================================
 */

declare(strict_types=1);

/**
 * Create an in-app notification.
 * @param array $opts ['user_id'=>?int, 'body'=>string, 'url'=>string, 'icon'=>string, 'type'=>string]
 */
function notify(string $title, array $opts = []): int
{
    try {
        return db_insert('notifications', [
            'user_id' => $opts['user_id'] ?? null,
            'title'   => mb_substr($title, 0, 191),
            'body'    => isset($opts['body']) ? mb_substr((string) $opts['body'], 0, 500) : null,
            'url'     => $opts['url'] ?? null,
            'icon'    => $opts['icon'] ?? 'bell',
            'type'    => $opts['type'] ?? 'info',
        ]);
    } catch (Throwable $e) {
        error_log('[notify] ' . $e->getMessage());
        return 0;
    }
}

/** Unread count for a user (their own + broadcasts). */
function notif_unread_count(?int $userId): int
{
    try {
        return (int) db_value(
            "SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND (user_id = :u OR user_id IS NULL)",
            [':u' => $userId]
        );
    } catch (Throwable $e) {
        return 0;
    }
}

/** Recent notifications for a user (their own + broadcasts), newest first. */
function notif_recent(?int $userId, int $limit = 15): array
{
    try {
        return db_all(
            "SELECT * FROM notifications WHERE (user_id = :u OR user_id IS NULL)
             ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(50, $limit)),
            [':u' => $userId]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** Mark a single notification (by id) or all of a user's notifications read. */
function notif_mark_read(?int $userId, ?int $id = null): void
{
    try {
        if ($id) {
            db_query("UPDATE notifications SET read_at = NOW() WHERE id = :id AND (user_id = :u OR user_id IS NULL) AND read_at IS NULL", [':id' => $id, ':u' => $userId]);
        } else {
            db_query("UPDATE notifications SET read_at = NOW() WHERE read_at IS NULL AND (user_id = :u OR user_id IS NULL)", [':u' => $userId]);
        }
    } catch (Throwable $e) {
        error_log('[notif mark] ' . $e->getMessage());
    }
}
