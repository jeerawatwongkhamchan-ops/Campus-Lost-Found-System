<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/reports/index.php');
}

if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่อีกครั้ง';
    $_SESSION['flash_type'] = 'error';
    redirect('/reports/index.php');
}

$report_id = reports_positive_id($_POST['report_id'] ?? null);
$new_status = trim((string) ($_POST['status'] ?? ''));

if ($report_id === null || !in_array($new_status, ['open', 'resolved', 'closed'], true)) {
    redirect('/reports/index.php');
}

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$user_id = (int) $_SESSION['user_id'];
$user_role = (string) ($_SESSION['role'] ?? 'student');
$is_admin = in_array($user_role, ['admin', 'staff'], true);

// Fetch report (exclude soft-deleted)
$stmt = $connection->prepare('SELECT report_id, reporter_id, status, title FROM item_reports WHERE report_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $report_id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($report === null) {
    redirect('/reports/index.php');
}

if (((int) $report['reporter_id']) !== $user_id && !$is_admin) {
    $_SESSION['flash_message'] = 'คุณไม่มีสิทธิ์แก้ไขสถานะของรายงานนี้';
    $_SESSION['flash_type'] = 'error';
    redirect('/reports/show.php?id=' . $report_id);
}

$resolved_at_sql = $new_status === 'resolved' ? 'NOW()' : 'NULL';

$stmt = $connection->prepare(
    "UPDATE item_reports SET status = ?, resolved_at = {$resolved_at_sql} WHERE report_id = ?"
);
$stmt->bind_param('si', $new_status, $report_id);
$stmt->execute();
$stmt->close();

log_security_event('report_status_changed', $user_id, 'success', "Changed status of report #{$report_id} to {$new_status}", 'item_report', (string) $report_id);

$status_labels = [
    'open' => 'รอเจ้าของ / กำลังค้นหา',
    'resolved' => 'ส่งคืนสำเร็จแล้ว',
    'closed' => 'ปิดการค้นหา',
];
$status_label = $status_labels[$new_status] ?? $new_status;

// Notify original reporter if updated by staff/admin
$reporter_id = (int) $report['reporter_id'];
if ($user_id !== $reporter_id && function_exists('create_notification')) {
    create_notification(
        $reporter_id,
        'สถานะรายการของคุณมีการเปลี่ยนแปลง',
        "รายการ \"{$report['title']}\" ได้รับการปรับสถานะเป็น \"{$status_label}\" โดยเจ้าหน้าที่",
        "/reports/show.php?id={$report_id}"
    );
}

// If report is resolved or closed, notify pending claimants
if (in_array($new_status, ['resolved', 'closed'], true) && function_exists('create_notification')) {
    $c_stmt = $connection->prepare('SELECT claimant_id FROM claims WHERE report_id = ? AND status = "pending"');
    if ($c_stmt) {
        $c_stmt->bind_param('i', $report_id);
        $c_stmt->execute();
        $claimants = $c_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $c_stmt->close();

        foreach ($claimants as $cl) {
            $cid = (int) $cl['claimant_id'];
            if ($cid !== $user_id) {
                create_notification(
                    $cid,
                    'รายการที่คุณยื่นคำขอมีการเปลี่ยนสถานะ',
                    "รายการสิ่งของ \"{$report['title']}\" ที่คุณยื่นคำขอรับคืน ได้รับการปรับสถานะเป็น \"{$status_label}\"",
                    "/reports/show.php?id={$report_id}"
                );
            }
        }
    }
}

$_SESSION['flash_message'] = 'อัปเดตสถานะของสิ่งของเรียบร้อยแล้ว';
$_SESSION['flash_type'] = 'success';
redirect('/reports/show.php?id=' . $report_id);
