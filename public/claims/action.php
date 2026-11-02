<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';

require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/claims/index.php');
}

if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf_token($_POST['csrf_token'])) {
    $_SESSION['flash_message'] = 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่อีกครั้ง';
    $_SESSION['flash_type'] = 'error';
    redirect('/claims/index.php');
}

$claim_id = reports_positive_id($_POST['claim_id'] ?? null);
$action = trim((string) ($_POST['action'] ?? ''));

if ($claim_id === null || !in_array($action, ['approve', 'reject', 'cancel'], true)) {
    $_SESSION['flash_message'] = 'คำสั่งไม่ถูกต้อง';
    $_SESSION['flash_type'] = 'error';
    redirect('/claims/index.php');
}

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$user_id = (int) $_SESSION['user_id'];
$user_role = (string) ($_SESSION['role'] ?? 'student');
$is_admin_or_staff = in_array($user_role, ['admin', 'staff'], true);

// Fetch claim with report information
$stmt = $connection->prepare(
    'SELECT cl.claim_id, cl.report_id, cl.claimant_id, cl.status, r.reporter_id, r.title AS report_title,
            rep.full_name AS reporter_name
     FROM claims cl
     JOIN item_reports r ON r.report_id = cl.report_id
     LEFT JOIN users rep ON rep.user_id = r.reporter_id
     WHERE cl.claim_id = ? AND r.deleted_at IS NULL LIMIT 1'
);
$stmt->bind_param('i', $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

$redirect_to = trim((string) ($_POST['redirect_to'] ?? ''));
if ($redirect_to === '' || !str_starts_with($redirect_to, '/') || str_starts_with($redirect_to, '//')) {
    $redirect_to = '/claims/index.php';
}

if ($claim === null) {
    $_SESSION['flash_message'] = 'ไม่พบข้อมูลคำขอนี้';
    $_SESSION['flash_type'] = 'error';
    redirect($redirect_to);
}

$is_reporter = ((int) $claim['reporter_id']) === $user_id;
$is_claimant = ((int) $claim['claimant_id']) === $user_id;
$report_id = (int) $claim['report_id'];

if ($action === 'approve') {
    if (!$is_reporter && !$is_admin_or_staff) {
        $_SESSION['flash_message'] = 'คุณไม่มีสิทธิ์ยืนยันการส่งมอบคำขอนี้';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    if ($claim['status'] !== 'pending') {
        $_SESSION['flash_message'] = 'คำขอนี้ได้รับการดำเนินการไปแล้ว';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    $connection->begin_transaction();
    try {
        // 1. Approve this claim
        $stmt = $connection->prepare(
            'UPDATE claims SET status = "approved", reviewed_by = ?, reviewed_at = NOW() WHERE claim_id = ?'
        );
        $stmt->bind_param('ii', $user_id, $claim_id);
        $stmt->execute();
        $stmt->close();

        // 2. Mark report as resolved
        $stmt = $connection->prepare(
            'UPDATE item_reports SET status = "resolved", resolved_at = NOW() WHERE report_id = ?'
        );
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $stmt->close();

        // 3. Reject other pending claims for this report
        $stmt = $connection->prepare(
            'UPDATE claims SET status = "rejected", reviewed_by = ?, reviewed_at = NOW() WHERE report_id = ? AND claim_id != ? AND status = "pending"'
        );
        $stmt->bind_param('iii', $user_id, $report_id, $claim_id);
        $stmt->execute();
        $stmt->close();

        log_security_event('claim_approved', $user_id, 'success', 'Approved claim #' . $claim_id . ' on report #' . $report_id, 'claim', (string) $claim_id);

        $connection->commit();

        // Dispatch notification to claimant
        $verifier_title = $is_reporter ? 'ผู้พบสิ่งของ' : 'เจ้าหน้าที่';
        create_notification(
            (int) $claim['claimant_id'],
            'คำขอรับคืนสิ่งของ: ส่งมอบสำเร็จ',
            "{$verifier_title}ได้ยืนยันการส่งมอบสิ่งของ \"{$claim['report_title']}\" ให้คุณเรียบร้อยแล้ว",
            '/claims/index.php'
        );

        $_SESSION['flash_message'] = 'ยืนยันการส่งมอบสิ่งของเรียบร้อยแล้ว รายการถูกปรับสถานะเป็น "ส่งคืนสำเร็จ"';
        $_SESSION['flash_type'] = 'success';
    } catch (Throwable $e) {
        $connection->rollback();
        error_log('Approve claim error: ' . $e->getMessage());
        $_SESSION['flash_message'] = 'เกิดข้อผิดพลาดในการยืนยันการส่งมอบคำขอ';
        $_SESSION['flash_type'] = 'error';
    }
} elseif ($action === 'reject') {
    if (!$is_reporter && !$is_admin_or_staff) {
        $_SESSION['flash_message'] = 'คุณไม่มีสิทธิ์ปฏิเสธคำขอนี้';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    if ($claim['status'] !== 'pending') {
        $_SESSION['flash_message'] = 'คำขอนี้ได้รับการดำเนินการไปแล้ว';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    $stmt = $connection->prepare(
        'UPDATE claims SET status = "rejected", reviewed_by = ?, reviewed_at = NOW() WHERE claim_id = ?'
    );
    $stmt->bind_param('ii', $user_id, $claim_id);
    $stmt->execute();
    $stmt->close();

    log_security_event('claim_rejected', $user_id, 'success', 'Rejected claim #' . $claim_id . ' on report #' . $report_id, 'claim', (string) $claim_id);

    // Dispatch notification to claimant
    create_notification(
        (int) $claim['claimant_id'],
        'คำขอรับคืนสิ่งของ: ไม่ผ่านการตรวจสอบ',
        "คำขอรับคืนสำหรับสิ่งของ \"{$claim['report_title']}\" ได้รับการปฏิเสธจากผู้พบสิ่งของหรือเจ้าหน้าที่",
        '/claims/index.php'
    );

    $_SESSION['flash_message'] = 'ปฏิเสธคำขอรับคืนสิ่งของเรียบร้อยแล้ว';
    $_SESSION['flash_type'] = 'info';
} elseif ($action === 'cancel') {
    if (!$is_claimant) {
        $_SESSION['flash_message'] = 'คุณสามารถยกเลิกได้เฉพาะคำขอของคุณเท่านั้น';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    if ($claim['status'] !== 'pending') {
        $_SESSION['flash_message'] = 'คำขอนี้ไม่สามารถยกเลิกได้แล้ว';
        $_SESSION['flash_type'] = 'error';
        redirect($redirect_to);
    }

    $stmt = $connection->prepare('UPDATE claims SET status = "cancelled" WHERE claim_id = ?');
    $stmt->bind_param('i', $claim_id);
    $stmt->execute();
    $stmt->close();

    log_security_event('claim_cancelled', $user_id, 'success', 'Cancelled claim #' . $claim_id, 'claim', (string) $claim_id);

    $_SESSION['flash_message'] = 'ยกเลิกคำขอเรียบร้อยแล้ว';
    $_SESSION['flash_type'] = 'info';
}

redirect($redirect_to);
