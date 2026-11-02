<?php

declare(strict_types=1);

/**
 * Authorization helpers for Staff and Admin portals.
 *
 * Implements Role-Based Access Control (RBAC) following the
 * Principle of Least Privilege:
 * - Student: Front-end user only (cannot access management area)
 * - Staff: Lost & Found operational staff (Reports & Claims management only)
 * - Admin: System administrator (Full access including Users, Categories, and Security Logs)
 */

/**
 * Require an active staff or admin account.
 * Used for pages accessible by operational staff and administrators (e.g. Dashboard, Reports, Claims).
 *
 * @return array{user_id: int, full_name: string, email: string, role: string, account_status: string}
 */
function require_staff_or_admin_access(): array
{
    $userId = filter_var(
        $_SESSION['user_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!is_int($userId)) {
        redirect('/auth/login.php');
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;
    if ($lastActivity !== null && (!is_int($lastActivity) || time() - $lastActivity > 1800)) {
        unset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['role']);
        session_regenerate_id(true);
        redirect('/auth/login.php?timeout=1');
    }

    try {
        $db = get_db_connection();
        if ($db === null) {
            throw new RuntimeException('Database unavailable');
        }

        $statement = $db->prepare(
            'SELECT user_id, full_name, email, role, account_status FROM users WHERE user_id = ? LIMIT 1'
        );
        $statement->bind_param('i', $userId);
        $statement->execute();
        $user = $statement->get_result()->fetch_assoc();
        $statement->close();
    } catch (Throwable $error) {
        error_log('Staff/Admin authorization failed: ' . $error->getMessage());
        http_response_code(503);
        exit('Service temporarily unavailable.');
    }

    if ($user === null || $user['account_status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['last_activity'], $_SESSION['role']);
        redirect('/auth/login.php');
    }

    if (!in_array($user['role'], ['admin', 'staff'], true)) {
        log_security_event(
            'unauthorized_management_access',
            (int) $user['user_id'],
            'failure',
            "User {$user['user_id']} ({$user['role']}) attempted to access staff/admin portal: {$_SERVER['REQUEST_URI']}",
            'system',
            null
        );
        $_SESSION['flash_message'] = 'คุณไม่มีสิทธิ์เข้าถึงระบบจัดการ (เฉพาะเจ้าหน้าที่หรือผู้ดูแลระบบเท่านั้น)';
        $_SESSION['flash_type'] = 'error';
        redirect('/reports/index.php');
    }

    $_SESSION['last_activity'] = time();
    $_SESSION['role'] = $user['role'];

    return [
        'user_id' => (int) $user['user_id'],
        'full_name' => (string) $user['full_name'],
        'email' => (string) $user['email'],
        'role' => (string) $user['role'],
        'account_status' => (string) $user['account_status'],
    ];
}

/**
 * Require an active administrator account.
 * Restricts access strictly to administrators (e.g. Users, Categories, Security Logs).
 *
 * @return array{user_id: int, full_name: string, email: string, role: string, account_status: string}
 */
function require_admin_access(): array
{
    $user = require_staff_or_admin_access();

    if ($user['role'] !== 'admin') {
        log_security_event(
            'access_denied_admin_only',
            (int) $user['user_id'],
            'failure',
            "Staff user {$user['user_id']} attempted to access admin-only page: {$_SERVER['REQUEST_URI']}",
            'system',
            null
        );
        $_SESSION['flash_message'] = 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (เฉพาะผู้ดูแลระบบ Admin เท่านั้น)';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/index.php');
    }

    return $user;
}

/**
 * Check if the given or authenticated user is an administrator.
 */
function is_admin_user(?array $user = null): bool
{
    $role = $user['role'] ?? ($_SESSION['role'] ?? null);
    return $role === 'admin';
}

/**
 * Check if the given or authenticated user is a staff member.
 */
function is_staff_user(?array $user = null): bool
{
    $role = $user['role'] ?? ($_SESSION['role'] ?? null);
    return $role === 'staff';
}

/**
 * Get human-readable Thai label for role.
 */
function get_role_label(string $role): string
{
    return match ($role) {
        'admin' => 'ผู้ดูแลระบบ (Admin)',
        'staff' => 'เจ้าหน้าที่ (Staff)',
        default => 'นักศึกษา / ผู้ใช้ทั่วไป',
    };
}
