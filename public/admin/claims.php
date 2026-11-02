<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';
require_once APP_PATH . '/helpers/report_security.php';

$admin = require_staff_or_admin_access();
$user_role_title = $admin['role'] === 'admin' ? 'Admin' : 'Staff';

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$current_user_id = (int) $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่อีกครั้ง';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/claims.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    // CREATE CLAIM
    if ($action === 'create_claim') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $claimant_id = (int) ($_POST['claimant_id'] ?? $current_user_id);
        $claim_type = trim((string) ($_POST['claim_type'] ?? 'ยืนยันความเป็นเจ้าของ'));
        $message = trim((string) ($_POST['message'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'pending'));

        if ($report_id <= 0 || mb_strlen($message) < 5) {
            $_SESSION['flash_message'] = 'กรุณาเลือกสิ่งของและระบุรายละเอียดอย่างน้อย 5 ตัวอักษร';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = $connection->prepare(
                'INSERT INTO claims (report_id, claimant_id, claim_type, message, status) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisss', $report_id, $claimant_id, $claim_type, $message, $status);
            $stmt->execute();
            $new_claim_id = (int) $connection->insert_id;
            $stmt->close();

            log_security_event('claim_created_by_admin', $current_user_id, 'success', "{$user_role_title} created claim #{$new_claim_id}", 'claim', (string) $new_claim_id);

            $_SESSION['flash_message'] = 'สร้างคำขอรับคืนสิ่งของเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/claims.php');
    }

    // UPDATE CLAIM (UNIFIED EDIT: STATUS, TYPE, MESSAGE)
    elseif ($action === 'update_claim') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        $new_status = trim((string) ($_POST['status'] ?? 'pending'));
        $claim_type = trim((string) ($_POST['claim_type'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($claim_id > 0 && mb_strlen($message) >= 5 && in_array($new_status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            // Fetch claim
            $stmt = $connection->prepare('SELECT report_id, claimant_id, status FROM claims WHERE claim_id = ? LIMIT 1');
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            $cl = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cl !== null) {
                $report_id = (int) $cl['report_id'];
                $claimant_id = (int) $cl['claimant_id'];
                $prev_status = (string) $cl['status'];

                $reviewed_sql = in_array($new_status, ['approved', 'rejected'], true) ? 'NOW()' : 'NULL';
                $reviewer_val = in_array($new_status, ['approved', 'rejected'], true) ? $current_user_id : null;

                $stmt = $connection->prepare(
                    "UPDATE claims SET claim_type = ?, message = ?, status = ?, reviewed_by = ?, reviewed_at = {$reviewed_sql} WHERE claim_id = ?"
                );
                $stmt->bind_param('sssii', $claim_type, $message, $new_status, $reviewer_val, $claim_id);
                $stmt->execute();
                $stmt->close();

                // If approved and previously wasn't approved, resolve the item report
                if ($new_status === 'approved' && $prev_status !== 'approved') {
                    $stmt = $connection->prepare('UPDATE item_reports SET status = "resolved", resolved_at = NOW() WHERE report_id = ?');
                    $stmt->bind_param('i', $report_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // If status changed, notify claimant
                if ($prev_status !== $new_status && function_exists('create_notification')) {
                    $status_text = match ($new_status) {
                        'approved' => 'อนุมัติการรับคืนเรียบร้อยแล้ว',
                        'rejected' => 'ถูกปฏิเสธคำขอรับคืน',
                        'cancelled' => 'ถูกยกเลิกแล้ว',
                        default => 'มีการปรับปรุงสถานะ',
                    };
                    create_notification(
                        $claimant_id,
                        'อัปเดตคำขอรับคืนสิ่งของ #' . $claim_id,
                        "คำขอรับคืนสิ่งของของคุณได้รับการดำเนินการ: {$status_text}",
                        '/reports/show.php?id=' . $report_id
                    );
                }

                log_security_event('claim_updated_by_admin', $current_user_id, 'success', "{$user_role_title} updated claim #{$claim_id} (status: {$new_status})", 'claim', (string) $claim_id);

                $_SESSION['flash_message'] = 'แก้ไขและปรับปรุงข้อมูลคำขอรับคืนเรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        } else {
            $_SESSION['flash_message'] = 'กรุณาระบุรายละเอียดข้อความอย่างน้อย 5 ตัวอักษร';
            $_SESSION['flash_type'] = 'error';
        }
        redirect('/admin/claims.php');
    }

    // UPDATE STATUS
    elseif ($action === 'update_status') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        $new_status = trim((string) ($_POST['status'] ?? ''));

        if ($claim_id > 0 && in_array($new_status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            // Fetch claim
            $stmt = $connection->prepare('SELECT report_id, claimant_id FROM claims WHERE claim_id = ? LIMIT 1');
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            $cl = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($cl !== null) {
                $report_id = (int) $cl['report_id'];
                $claimant_id = (int) $cl['claimant_id'];
                $reviewed_sql = in_array($new_status, ['approved', 'rejected'], true) ? 'NOW()' : 'NULL';
                $reviewer_val = in_array($new_status, ['approved', 'rejected'], true) ? $current_user_id : null;

                $stmt = $connection->prepare(
                    "UPDATE claims SET status = ?, reviewed_by = ?, reviewed_at = {$reviewed_sql} WHERE claim_id = ?"
                );
                $stmt->bind_param('sii', $new_status, $reviewer_val, $claim_id);
                $stmt->execute();
                $stmt->close();

                // If approved, update report to resolved
                if ($new_status === 'approved') {
                    $stmt = $connection->prepare('UPDATE item_reports SET status = "resolved", resolved_at = NOW() WHERE report_id = ?');
                    $stmt->bind_param('i', $report_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Notify claimant
                if (function_exists('create_notification')) {
                    $status_text = match ($new_status) {
                        'approved' => 'อนุมัติการรับคืนเรียบร้อยแล้ว',
                        'rejected' => 'ถูกปฏิเสธคำขอรับคืน',
                        'cancelled' => 'ถูกยกเลิกแล้ว',
                        default => 'มีการปรับปรุงสถานะ',
                    };
                    create_notification(
                        $claimant_id,
                        'อัปเดตคำขอรับคืนสิ่งของ #' . $claim_id,
                        "คำขอรับคืนสิ่งของของคุณได้รับการดำเนินการ: {$status_text}",
                        '/reports/show.php?id=' . $report_id
                    );
                }

                log_security_event('claim_status_changed_by_admin', $current_user_id, 'success', "{$user_role_title} changed claim #{$claim_id} to {$new_status}", 'claim', (string) $claim_id);

                $_SESSION['flash_message'] = 'ปรับสถานะคำขอรับคืนเรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/claims.php');
    }

    // UPDATE CLAIM DETAILS
    elseif ($action === 'update_details') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        $claim_type = trim((string) ($_POST['claim_type'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($claim_id > 0 && mb_strlen($message) >= 5) {
            $stmt = $connection->prepare('UPDATE claims SET claim_type = ?, message = ? WHERE claim_id = ?');
            $stmt->bind_param('ssi', $claim_type, $message, $claim_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('claim_updated_by_admin', $current_user_id, 'success', "{$user_role_title} updated claim #{$claim_id}", 'claim', (string) $claim_id);

            $_SESSION['flash_message'] = 'แก้ไขรายละเอียดคำขอเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/claims.php');
    }

    // DELETE CLAIM
    elseif ($action === 'delete_claim') {
        $claim_id = (int) ($_POST['claim_id'] ?? 0);
        if ($claim_id > 0) {
            // Delete image if exists
            $stmt = $connection->prepare('SELECT proof_image FROM claims WHERE claim_id = ? LIMIT 1');
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            $del_proof = $stmt->get_result()->fetch_assoc()['proof_image'] ?? null;
            $stmt->close();

            if (!empty($del_proof) && function_exists('delete_claim_image')) {
                delete_claim_image($del_proof);
            }

            $stmt = $connection->prepare('DELETE FROM claims WHERE claim_id = ?');
            $stmt->bind_param('i', $claim_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('claim_deleted_by_admin', $current_user_id, 'success', "{$user_role_title} deleted claim #{$claim_id}", 'claim', (string) $claim_id);

            $_SESSION['flash_message'] = 'ลบคำขอรับคืนเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/claims.php');
    }
}

// Fetch active reports for create
$stmt = $connection->prepare('SELECT report_id, title, report_type, location FROM item_reports WHERE status IN ("open", "claimed") AND deleted_at IS NULL ORDER BY created_at DESC');
$stmt->execute();
$active_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch users for claimant
$stmt = $connection->prepare('SELECT user_id, student_id, full_name FROM users ORDER BY full_name ASC');
$stmt->execute();
$all_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Filter & query
$filter_status = trim((string) ($_GET['status'] ?? ''));
$filter_report_id = (int) ($_GET['report_id'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT cl.claim_id, cl.report_id, cl.claimant_id, cl.claim_type, cl.message, cl.proof_image, cl.status,
               cl.reviewed_at, cl.created_at,
               r.title AS report_title, r.report_type, r.location AS report_location,
               u.full_name AS claimant_name, u.student_id AS claimant_student_id, u.email AS claimant_email, u.phone AS claimant_phone,
               rev.full_name AS reviewer_name
        FROM claims cl
        JOIN item_reports r ON r.report_id = cl.report_id
        JOIN users u ON u.user_id = cl.claimant_id
        LEFT JOIN users rev ON rev.user_id = cl.reviewed_by
        WHERE 1=1';

$where_sql = '';
$where_types = '';
$where_params = [];

if (in_array($filter_status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
    $where_sql .= ' AND cl.status = ?';
    $where_types .= 's';
    $where_params[] = $filter_status;
}

if ($filter_report_id > 0) {
    $where_sql .= ' AND cl.report_id = ?';
    $where_types .= 'i';
    $where_params[] = $filter_report_id;
}

if ($search !== '') {
    $where_sql .= ' AND (r.title LIKE ? OR u.full_name LIKE ? OR cl.message LIKE ?)';
    $st = '%' . $search . '%';
    $where_types .= 'sss';
    $where_params[] = $st;
    $where_params[] = $st;
    $where_params[] = $st;
}

$items_per_page = 15;
$current_page = max(1, (int) ($_GET['page'] ?? 1));

// Count query
$count_sql = 'SELECT COUNT(*) AS total
              FROM claims cl
              JOIN item_reports r ON r.report_id = cl.report_id
              JOIN users u ON u.user_id = cl.claimant_id
              LEFT JOIN users rev ON rev.user_id = cl.reviewed_by
              WHERE 1=1' . $where_sql;

$c_stmt = $connection->prepare($count_sql);
if ($where_params !== []) {
    $c_stmt->bind_param($where_types, ...$where_params);
}
$c_stmt->execute();
$total_items = (int) ($c_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$c_stmt->close();

$total_pages = max(1, (int) ceil($total_items / $items_per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $items_per_page;

$sql .= $where_sql . ' ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
$types = $where_types . 'ii';
$params = array_merge($where_params, [$items_per_page, $offset]);

$stmt = $connection->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$status_info = [
    'pending' => ['label' => 'รอดำเนินการ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
    'approved' => ['label' => 'อนุมัติส่งคืนแล้ว', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'rejected' => ['label' => 'ปฏิเสธคำขอ', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200'],
];

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$active_admin_tab = 'claims';
$admin_page_heading = 'จัดการคำขอรับคืนสิ่งของ (Claims)';
render_header('จัดการคำขอรับคืนสิ่งของ - Campus Lost & Found System');
?>

<div class="space-y-6">
    <?php require APP_PATH . '/views/admin/components/nav.php'; ?>

    <?php if ($flash_message !== null): ?>
        <div class="alert-animate rounded-xl border p-4 text-xs sm:text-sm font-medium shadow-xs flex items-center gap-3 <?= $flash_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-rose-200 bg-rose-50 text-rose-900' ?>">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg <?= $flash_type === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                <?php if ($flash_type === 'success'): ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                <?php else: ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" />
                    </svg>
                <?php endif; ?>
            </div>
            <div class="flex-1"><?= e($flash_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Unified CRUD Header -->
    <?php
    $crud_title = 'จัดการคำขอรับคืนสิ่งของ';
    $crud_subtitle = 'Claims Management';
    $crud_total = $total_items;
    $crud_create_label = 'สร้างคำขอใหม่';
    $crud_create_modal_id = 'create-claim-modal';
    require APP_PATH . '/views/admin/components/crud_header.php';
    ?>

    <!-- Unified CRUD Filter -->
    <?php
    $filter_action = '/admin/claims.php';
    $filter_search_value = $search;
    $filter_search_placeholder = 'ค้นหาชื่อสิ่งของ, ผู้ยื่น, ข้อความ...';
    $filter_has_active_filters = ($search !== '' || $filter_status !== '' || $filter_report_id > 0);
    ob_start();
    ?>
    <div class="w-full sm:w-48">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
        <select
            name="status"
            onchange="this.form.submit()"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="">ทุกสถานะ</option>
            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>รอดำเนินการ</option>
            <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>อนุมัติส่งคืนแล้ว</option>
            <option value="rejected" <?= $filter_status === 'rejected' ? 'selected' : '' ?>>ปฏิเสธคำขอ</option>
            <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>ยกเลิกแล้ว</option>
        </select>
    </div>
    <?php
    $filter_fields_html = ob_get_clean();
    require APP_PATH . '/views/admin/components/crud_filter.php';
    ?>

    <!-- Unified CRUD Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
        <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
            <h3 class="text-xs sm:text-sm font-semibold text-gray-900">
                รายการคำขอรับคืนทั้งหมด (<?= number_format($total_items) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs sm:text-sm">
                <thead class="bg-gray-50/70 text-gray-500 font-medium text-xs">
                    <tr>
                        <th class="px-5 py-3 w-20">รหัส</th>
                        <th class="px-5 py-3">สิ่งของที่เกี่ยวข้อง</th>
                        <th class="px-5 py-3">ผู้ยื่นคำขอ</th>
                        <th class="px-5 py-3">ประเภท / รายละเอียด</th>
                        <th class="px-5 py-3">สถานะ</th>
                        <th class="px-5 py-3">วันที่ยื่น / ผู้ตรวจ</th>
                        <th class="px-5 py-3 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($claims === []): ?>
                        <?php
                        $empty_title = 'ไม่พบข้อมูลคำขอรับคืน';
                        $empty_message = 'ยังไม่มีคำขอรับคืนสิ่งของตามเงื่อนไขที่เลือก หรือยังไม่มีผู้ยื่นคำขอในระบบ';
                        $empty_colspan = 7;
                        require APP_PATH . '/views/admin/components/crud_empty.php';
                        ?>
                    <?php endif; ?>
                    <?php foreach ($claims as $c): ?>
                        <?php
                        $proof_url = !empty($c['proof_image']) ? get_claim_image_url($c['proof_image']) : null;
                        $status_badge = $status_info[$c['status']] ?? ['label' => ucfirst($c['status']), 'class' => 'bg-gray-100 text-gray-700'];
                        ?>
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-5 py-3.5 font-mono text-gray-400 font-medium">#<?= (int) $c['claim_id'] ?></td>
                            <td class="px-5 py-3.5">
                                <a href="/reports/show.php?id=<?= (int) $c['report_id'] ?>" target="_blank" class="font-semibold text-gray-900 hover:text-gray-600 transition text-sm">
                                    <?= e($c['report_title']) ?>
                                </a>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    #<?= (int) $c['report_id'] ?> · <?= e($c['report_location']) ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-700 whitespace-nowrap">
                                <div class="font-semibold text-gray-900 text-sm"><?= e($c['claimant_name']) ?></div>
                                <div class="text-xs text-gray-500"><?= e($c['claimant_email']) ?></div>
                                <?php if (!empty($c['claimant_phone'])): ?>
                                    <div class="text-xs text-gray-400"><?= e($c['claimant_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-gray-600 max-w-xs">
                                <span class="inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                    <?= e($c['claim_type']) ?>
                                </span>
                                <p class="text-xs text-gray-600 line-clamp-2 mt-1.5 leading-relaxed"><?= e($c['message']) ?></p>
                                <?php if ($proof_url !== null): ?>
                                    <a href="<?= e($proof_url) ?>" target="_blank" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 transition underline">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        ดูรูปหลักฐาน
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if ($c['status'] === 'pending'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        รอดำเนินการ
                                    </span>
                                <?php elseif ($c['status'] === 'approved'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        อนุมัติแล้ว
                                    </span>
                                <?php elseif ($c['status'] === 'rejected'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        ปฏิเสธคำขอ
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        ยกเลิกแล้ว
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-500">
                                <div class="text-xs"><?= substr(e($c['created_at']), 0, 16) ?></div>
                                <?php if ($c['reviewed_at'] !== null): ?>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        ตรวจ: <span class="font-medium text-gray-600"><?= e($c['reviewer_name'] ?? 'เจ้าหน้าที่') ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1.5">
                                <!-- ปุ่มแก้ไข / ตรวจสอบคำขอ (เปิด Edit Modal ทันทีด้วย data attributes) -->
                                <button
                                    type="button"
                                    data-id="<?= (int) $c['claim_id'] ?>"
                                    data-report="<?= e($c['report_title']) ?>"
                                    data-reportid="<?= (int) $c['report_id'] ?>"
                                    data-claimant="<?= e($c['claimant_name']) ?>"
                                    data-studentid="<?= e($c['claimant_student_id']) ?>"
                                    data-contact="<?= e($c['claimant_email'] . (!empty($c['claimant_phone']) ? ' · ' . $c['claimant_phone'] : '')) ?>"
                                    data-claimtype="<?= e($c['claim_type']) ?>"
                                    data-status="<?= e($c['status']) ?>"
                                    data-message="<?= e($c['message']) ?>"
                                    data-proof="<?= e($proof_url ?? '') ?>"
                                    onclick="editClaimFromBtn(this)"
                                    class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
                                    title="ตรวจสอบและแก้ไขคำขอรับคืน"
                                >
                                    แก้ไข
                                </button>

                                <!-- ปุ่มลบ (เปิด Shared Delete Modal) -->
                                <button
                                    type="button"
                                    data-id="<?= (int) $c['claim_id'] ?>"
                                    data-report="<?= e($c['report_title']) ?>"
                                    data-claimant="<?= e($c['claimant_name']) ?>"
                                    onclick="confirmDeleteClaimFromBtn(this)"
                                    class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition shadow-xs cursor-pointer"
                                    title="ลบคำขอรับคืน"
                                >
                                    ลบ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php
    $base_url = '/admin/claims.php';
    $query_params = $_GET;
    require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<!-- Modal สร้างคำขอรับคืนใหม่ -->
<?php
$modal_id = 'create-claim-modal';
$modal_title = 'สร้างคำขอรับคืนใหม่';
$modal_subtitle = 'สร้างคำขอยืนยันความเป็นเจ้าของหรือข้อมูลสิ่งของในระบบ';
$modal_max_width = 'max-w-xl';
ob_start();
?>
<form method="POST" action="/admin/claims.php" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_claim">

    <div>
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">เลือกสิ่งของที่เกี่ยวข้อง <span class="text-rose-500">*</span></label>
        <select name="report_id" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <option value="">-- เลือกรายการสิ่งของ --</option>
            <?php foreach ($active_reports as $rep): ?>
                <option value="<?= (int) $rep['report_id'] ?>" <?= $filter_report_id === (int) $rep['report_id'] ? 'selected' : '' ?>>
                    #<?= (int) $rep['report_id'] ?> <?= e($rep['title']) ?> (<?= e($rep['location']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ผู้ยื่นคำขอ <span class="text-rose-500">*</span></label>
            <select name="claimant_id" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <?php foreach ($all_users as $usr): ?>
                    <option value="<?= (int) $usr['user_id'] ?>" <?= ((int) $usr['user_id']) === $current_user_id ? 'selected' : '' ?>>
                        <?= e($usr['full_name'] . ' (' . $usr['student_id'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ประเภทคำขอ <span class="text-rose-500">*</span></label>
            <select name="claim_type" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="ยืนยันความเป็นเจ้าของ">ยืนยันความเป็นเจ้าของ (Owner)</option>
                <option value="เป็นผู้เก็บสิ่งของได้">เป็นผู้เก็บสิ่งของได้ (Finder)</option>
                <option value="แจ้งเบาะแส / ข้อมูลเพิ่มเติม">แจ้งเบาะแส / ข้อมูลเพิ่มเติม (Info)</option>
            </select>
        </div>
    </div>

    <div>
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รายละเอียดและหลักฐาน <span class="text-rose-500">*</span></label>
        <textarea name="message" rows="3" required placeholder="ระบุจุดสังเกตเฉพาะหรือหลักฐานยืนยัน..." class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"></textarea>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('create-claim-modal')"
            class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
        >
            ยกเลิก
        </button>
        <button
            type="submit"
            class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
        >
            สร้างคำขอ
        </button>
    </div>
</form>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Modal ตรวจสอบและแก้ไขคำขอรับคืน (Edit Claim Modal) -->
<?php
$modal_id = 'edit-claim-modal';
$modal_title = 'ตรวจสอบและแก้ไขคำขอรับคืน';
$modal_subtitle = 'ปรับปรุงสถานะ ตรวจสอบหลักฐาน หรือแก้ไขรายละเอียดคำขอ';
$modal_max_width = 'max-w-lg';
ob_start();
?>
<div class="space-y-4">
    <div class="rounded-xl bg-gray-50 p-4 border border-gray-200 space-y-1.5 text-xs sm:text-sm">
        <div><span class="text-gray-500 font-medium">สิ่งของที่เกี่ยวข้อง:</span> <span id="edit-claim-report" class="font-bold text-gray-900"></span></div>
        <div><span class="text-gray-500 font-medium">ผู้ยื่นคำขอ:</span> <span id="edit-claim-user" class="font-semibold text-gray-900"></span></div>
    </div>

    <div id="edit-claim-proof-wrap" class="hidden">
        <div class="flex items-center justify-between mb-1.5">
            <label class="block text-xs sm:text-sm font-medium text-gray-700">รูปภาพหลักฐานความเป็นเจ้าของ</label>
            <a id="edit-claim-proof-link" href="#" target="_blank" class="text-xs text-sky-600 hover:underline inline-flex items-center gap-1 font-medium">
                เปิดภาพขนาดเต็ม &rarr;
            </a>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50/60 p-2.5">
            <img id="edit-claim-proof-img" src="" alt="หลักฐาน" class="max-h-56 mx-auto rounded-lg object-contain shadow-2xs">
        </div>
    </div>

    <form method="POST" action="/admin/claims.php" class="space-y-3.5 pt-3 border-t border-gray-100">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_claim">
        <input type="hidden" name="claim_id" id="edit-claim-id" value="">

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะคำขอรับคืน <span class="text-rose-500">*</span></label>
            <select name="status" id="edit-claim-status" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="pending">รอดำเนินการ (Pending)</option>
                <option value="approved">อนุมัติส่งคืนแล้ว (Approved)</option>
                <option value="rejected">ปฏิเสธคำขอ (Rejected)</option>
                <option value="cancelled">ยกเลิกแล้ว (Cancelled)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ประเภทคำขอ</label>
            <select name="claim_type" id="edit-claim-type" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="ยืนยันความเป็นเจ้าของ">ยืนยันความเป็นเจ้าของ (Owner)</option>
                <option value="เป็นผู้เก็บสิ่งของได้">เป็นผู้เก็บสิ่งของได้ (Finder)</option>
                <option value="แจ้งเบาะแส / ข้อมูลเพิ่มเติม">แจ้งเบาะแส / ข้อมูลเพิ่มเติม (Info)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ข้อความรายละเอียดและหลักฐาน <span class="text-rose-500">*</span></label>
            <textarea name="message" id="edit-claim-message" rows="3" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-2">
            <button
                type="button"
                onclick="closeAdminModal('edit-claim-modal')"
                class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
            >
                ยกเลิก
            </button>
            <button
                type="submit"
                class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
            >
                บันทึกการแก้ไข
            </button>
        </div>
    </form>
</div>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Shared Delete Confirmation Modal -->
<?php require APP_PATH . '/views/admin/components/crud_delete_modal.php'; ?>

<script>
function editClaimFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const reportTitle = btn.getAttribute('data-report');
    const reportId = btn.getAttribute('data-reportid');
    const claimantName = btn.getAttribute('data-claimant');
    const studentId = btn.getAttribute('data-studentid');
    const contact = btn.getAttribute('data-contact');
    const claimType = btn.getAttribute('data-claimtype');
    const status = btn.getAttribute('data-status');
    const message = btn.getAttribute('data-message');
    const proofUrl = btn.getAttribute('data-proof');

    editClaim(id, reportTitle, reportId, claimantName, studentId, contact, claimType, status, message, proofUrl);
}

function editClaim(id, reportTitle, reportId, claimantName, studentId, contact, claimType, status, message, proofUrl) {
    const reportEl = document.getElementById('edit-claim-report');
    const userEl = document.getElementById('edit-claim-user');
    const idEl = document.getElementById('edit-claim-id');
    const typeEl = document.getElementById('edit-claim-type');
    const statusEl = document.getElementById('edit-claim-status');
    const messageEl = document.getElementById('edit-claim-message');

    if (reportEl) reportEl.textContent = '#' + (reportId || '') + ' ' + (reportTitle || '');
    if (userEl) userEl.textContent = (claimantName || '') + (studentId ? ' (' + studentId + ')' : '') + (contact ? ' · ' + contact : '');
    if (idEl) idEl.value = id;
    if (typeEl) typeEl.value = claimType;
    if (statusEl) statusEl.value = status || 'pending';
    if (messageEl) messageEl.value = message;

    const proofWrap = document.getElementById('edit-claim-proof-wrap');
    const proofImg = document.getElementById('edit-claim-proof-img');
    const proofLink = document.getElementById('edit-claim-proof-link');
    if (proofWrap && proofImg) {
        if (proofUrl && proofUrl.trim() !== '') {
            proofImg.src = proofUrl;
            if (proofLink) proofLink.href = proofUrl;
            proofWrap.classList.remove('hidden');
        } else {
            proofImg.src = '';
            if (proofLink) proofLink.href = '#';
            proofWrap.classList.add('hidden');
        }
    }

    if (typeof openAdminModal === 'function') {
        openAdminModal('edit-claim-modal');
    } else {
        const modal = document.getElementById('edit-claim-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            document.body.classList.add('overflow-hidden');
        }
    }
}

// Alias for backwards compatibility
function viewClaim(id, reportTitle, claimantName, claimType, message, proofUrl) {
    editClaim(id, reportTitle, '', claimantName, '', '', claimType, 'pending', message, proofUrl);
}

function confirmDeleteClaimFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const reportTitle = btn.getAttribute('data-report');
    const claimantName = btn.getAttribute('data-claimant');
    confirmDeleteClaim(id, reportTitle, claimantName);
}

function confirmDeleteClaim(id, reportTitle, claimantName) {
    openAdminDeleteModal({
        url: '/admin/claims.php',
        action: 'delete_claim',
        idName: 'claim_id',
        idValue: id,
        title: 'ยืนยันลบคำขอรับคืน #' + id,
        message: 'คุณต้องการลบคำขอรับคืนสำหรับสิ่งของ "' + reportTitle + '" ของ ' + claimantName + ' ออกจากระบบใช่หรือไม่?',
        warning: 'หากลบคำขอรับคืนนี้แล้ว ข้อมูลข้อความและรูปภาพหลักฐานจะถูกลบอย่างถาวร',
        submitLabel: 'ยืนยันลบคำขอ'
    });
}
</script>

<?php render_footer(); ?>
