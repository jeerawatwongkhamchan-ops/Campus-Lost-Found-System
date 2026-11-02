<?php

declare(strict_types=1);

/**
 * In-App Notification Helper Functions
 *
 * Provides notification dispatching and retrieval for claim events,
 * status changes, and administration alerts.
 */

/**
 * Create a new notification for a specific user.
 *
 * @param int $userId Target user ID
 * @param string $title Notification title
 * @param string $message Detailed notification message
 * @param string|null $link Optional relative URL to redirect the user
 * @return bool True on success, false on failure
 */
function create_notification(int $userId, string $title, string $message, ?string $link = null): bool
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare(
            'INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isss', $userId, $title, $message, $link);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to create notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get count of unread notifications for a user.
 *
 * @param int $userId User ID
 * @return int Unread count
 */
function get_unread_notifications_count(int $userId): int
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return 0;
    }

    try {
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0');
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($res['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('Failed to count unread notifications: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Fetch notifications for a user with pagination and optional type filtering.
 *
 * @param int $userId User ID
 * @param int $limit Maximum notifications to fetch
 * @param int $offset Records to skip for pagination
 * @param string $type Filter type ('all', 'unread', 'read', 'claims', 'reports')
 * @return list<array{notification_id: int, user_id: int, title: string, message: string, link: ?string, is_read: int, created_at: string}>
 */
function get_user_notifications(int $userId, int $limit = 10, int $offset = 0, string $type = 'all'): array
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return [];
    }

    $limit = max(1, min($limit, 100));
    $offset = max(0, $offset);

    try {
        $sql = 'SELECT notification_id, user_id, title, message, link, is_read, created_at
                FROM notifications
                WHERE user_id = ?';

        if ($type === 'unread') {
            $sql .= ' AND is_read = 0';
        } elseif ($type === 'read') {
            $sql .= ' AND is_read = 1';
        } elseif ($type === 'claims') {
            $sql .= " AND (link LIKE '%/claims/%' OR title LIKE '%คำขอรับคืน%' OR title LIKE '%เคลม%' OR message LIKE '%คำขอรับคืน%')";
        } elseif ($type === 'reports') {
            $sql .= " AND (link LIKE '%/reports/%' OR title LIKE '%สถานะ%' OR title LIKE '%รายงาน%')";
        }

        $sql .= ' ORDER BY created_at DESC, notification_id DESC LIMIT ? OFFSET ?';

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iii', $userId, $limit, $offset);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $notifications;
    } catch (Throwable $e) {
        error_log('Failed to fetch user notifications: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get total notification count for a user with optional type filtering.
 *
 * @param int $userId User ID
 * @param string $type Filter type ('all', 'unread', 'read', 'claims', 'reports')
 * @return int Total notifications matching criteria
 */
function get_user_notifications_count(int $userId, string $type = 'all'): int
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return 0;
    }

    try {
        $sql = 'SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?';

        if ($type === 'unread') {
            $sql .= ' AND is_read = 0';
        } elseif ($type === 'read') {
            $sql .= ' AND is_read = 1';
        } elseif ($type === 'claims') {
            $sql .= " AND (link LIKE '%/claims/%' OR title LIKE '%คำขอรับคืน%' OR title LIKE '%เคลม%' OR message LIKE '%คำขอรับคืน%')";
        } elseif ($type === 'reports') {
            $sql .= " AND (link LIKE '%/reports/%' OR title LIKE '%สถานะ%' OR title LIKE '%รายงาน%')";
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int) ($res['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('Failed to count notifications: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Mark a single notification as read.
 *
 * @param int $notificationId
 * @param int $userId
 * @return bool
 */
function mark_notification_as_read(int $notificationId, int $userId): bool
{
    $db = get_db_connection();
    if ($db === null || $notificationId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to mark notification as read: ' . $e->getMessage());
        return false;
    }
}

/**
 * Mark all notifications as read for a user.
 *
 * @param int $userId
 * @return bool
 */
function mark_all_notifications_as_read(int $userId): bool
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to mark all notifications as read: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete a single notification owned by the user.
 *
 * @param int $notificationId
 * @param int $userId
 * @return bool
 */
function delete_notification(int $notificationId, int $userId): bool
{
    $db = get_db_connection();
    if ($db === null || $notificationId <= 0 || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM notifications WHERE notification_id = ? AND user_id = ?');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to delete notification: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clear all read notifications for a user.
 *
 * @param int $userId
 * @return bool
 */
function clear_read_notifications(int $userId): bool
{
    $db = get_db_connection();
    if ($db === null || $userId <= 0) {
        return false;
    }

    try {
        $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log('Failed to clear read notifications: ' . $e->getMessage());
        return false;
    }
}
