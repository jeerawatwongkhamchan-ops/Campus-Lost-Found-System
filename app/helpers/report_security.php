<?php

declare(strict_types=1);

function reports_database(): mysqli
{
    $database = get_db_connection();
    if ($database === null) {
        reports_error(503, 'Reports are temporarily unavailable.');
    }
    return $database;
}

function reports_error(int $status, string $message): never
{
    $titles = [
        400 => 'คำขอไม่ถูกต้อง (Bad Request)',
        403 => 'ไม่มีสิทธิ์เข้าถึง (Forbidden)',
        404 => 'ไม่พบข้อมูลที่ต้องการ (Not Found)',
        405 => 'ไม่อนุญาตวิธีส่งข้อมูลนี้ (Method Not Allowed)',
        500 => 'ข้อผิดพลาดภายในเซิร์ฟเวอร์ (Internal Server Error)',
        503 => 'ระบบไม่พร้อมใช้งานชั่วคราว (Service Unavailable)',
    ];
    $title = $titles[$status] ?? "ข้อผิดพลาด ({$status})";
    render_error($status, $title, $message);
}

function reports_positive_id(mixed $value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $id === false ? null : $id;
}

/** @return array{user_id: int, role: string}|null */
function reports_current_user(mysqli $database): ?array
{
    $userId = reports_positive_id($_SESSION['user_id'] ?? null);
    if ($userId === null) {
        return null;
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;
    if (is_int($lastActivity) && time() - $lastActivity > 1800) {
        unset($_SESSION['user_id'], $_SESSION['last_activity']);
        session_regenerate_id(true);
        return null;
    }

    $statement = $database->prepare(
        'SELECT user_id, role, account_status FROM users WHERE user_id = ? LIMIT 1'
    );
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($user === null || $user['account_status'] !== 'active') {
        return null;
    }

    $_SESSION['last_activity'] = time();
    return ['user_id' => (int) $user['user_id'], 'role' => $user['role']];
}

/** @return array{user_id: int, role: string} */
function reports_require_user(mysqli $database): array
{
    $user = reports_current_user($database);
    if ($user === null) {
        redirect('/auth/login.php');
    }
    return $user;
}

/** @param array<string, mixed> $report @param array{user_id: int, role: string}|null $user */
function reports_can_manage(array $report, ?array $user): bool
{
    return $user !== null
        && ($user['role'] === 'admin' || $user['role'] === 'staff' || (int) $report['reporter_id'] === $user['user_id']);
}

function reports_csrf_token(): string
{
    return generate_csrf_token();
}

function reports_verify_csrf(mixed $token): void
{
    if (!is_string($token) || !verify_csrf_token($token)) {
        reports_error(403, 'การยืนยันความปลอดภัยล้มเหลว กรุณาโหลดหน้าเว็บใหม่และลองอีกครั้ง');
    }
}

function reports_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        reports_error(405, 'Method not allowed.');
    }
}

function reports_log_failure(Throwable $error): never
{
    error_log('Report request failed: ' . $error->getMessage());
    reports_error(503, 'Reports are temporarily unavailable. Please try again later.');
}
