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
        redirect('/admin/reports.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    // CREATE REPORT
    if ($action === 'create_report') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $report_type = trim((string) ($_POST['report_type'] ?? 'lost'));
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $location = trim((string) ($_POST['location'] ?? ''));
        $event_datetime = trim((string) ($_POST['event_datetime'] ?? ''));
        $reporter_id = (int) ($_POST['reporter_id'] ?? $current_user_id);
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'open'));

        $errors = [];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 255) {
            $errors[] = 'ชื่อสิ่งของต้องมีความยาว 3-255 ตัวอักษร';
        }
        if (!in_array($report_type, ['lost', 'found'], true)) {
            $errors[] = 'ประเภทรายงานไม่ถูกต้อง';
        }
        if ($category_id <= 0) {
            $errors[] = 'กรุณาเลือกหมวดหมู่สิ่งของ';
        }
        if (mb_strlen($location) < 3 || mb_strlen($location) > 255) {
            $errors[] = 'สถานที่ต้องมีความยาว 3-255 ตัวอักษร';
        }
        if (mb_strlen($description) < 5) {
            $errors[] = 'รายละเอียดต้องมีความยาวอย่างน้อย 5 ตัวอักษร';
        }
        if (!in_array($status, ['open', 'claimed', 'resolved', 'closed'], true)) {
            $status = 'open';
        }

        $formatted_date = str_replace('T', ' ', $event_datetime);
        if (strlen($formatted_date) === 16) {
            $formatted_date .= ':00';
        }

        $upload_result = validate_and_upload_report_image($_FILES['image'] ?? null);
        if (!$upload_result['success']) {
            $errors[] = $upload_result['error'];
        }

        if ($errors !== []) {
            $_SESSION['flash_message'] = implode(', ', $errors);
            $_SESSION['flash_type'] = 'error';
        } else {
            $image_filename = $upload_result['filename'] ?? null;
            $stmt = $connection->prepare(
                'INSERT INTO item_reports (reporter_id, category_id, report_type, title, description, location, event_datetime, status, image_filename)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisssssss', $reporter_id, $category_id, $report_type, $title, $description, $location, $formatted_date, $status, $image_filename);
            $stmt->execute();
            $new_report_id = (int) $connection->insert_id;
            $stmt->close();

            log_security_event('report_created_by_admin', $current_user_id, 'success', "{$user_role_title} created report #{$new_report_id}", 'item_report', (string) $new_report_id);

            $_SESSION['flash_message'] = 'สร้างรายงานสิ่งของใหม่เรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php');
    }

    // UPDATE REPORT
    elseif ($action === 'update_report') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $report_type = trim((string) ($_POST['report_type'] ?? 'lost'));
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $location = trim((string) ($_POST['location'] ?? ''));
        $event_datetime = trim((string) ($_POST['event_datetime'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'open'));

        $formatted_date = str_replace('T', ' ', $event_datetime);
        if (strlen($formatted_date) === 16) {
            $formatted_date .= ':00';
        }

        $stmt = $connection->prepare('SELECT reporter_id, title, status, image_filename FROM item_reports WHERE report_id = ? LIMIT 1');
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $prev_report = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $existing_img = $prev_report['image_filename'] ?? null;
        $prev_status = $prev_report['status'] ?? null;

        $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
        $new_image = $existing_img;
        $errors = [];

        if ($remove_image) {
            $new_image = null;
            if (!empty($existing_img)) {
                delete_report_image($existing_img);
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_res = validate_and_upload_report_image($_FILES['image'], $existing_img);
            if (!$upload_res['success']) {
                $errors[] = $upload_res['error'];
            } else {
                $new_image = $upload_res['filename'];
            }
        }

        if ($errors !== []) {
            $_SESSION['flash_message'] = implode(', ', $errors);
            $_SESSION['flash_type'] = 'error';
        } else {
            $resolved_sql = $status === 'resolved' ? 'NOW()' : 'NULL';

            $stmt = $connection->prepare(
                "UPDATE item_reports SET title = ?, report_type = ?, category_id = ?, location = ?,
                 event_datetime = ?, description = ?, status = ?, image_filename = ?, resolved_at = {$resolved_sql} WHERE report_id = ?"
            );
            $stmt->bind_param('ssisssssi', $title, $report_type, $category_id, $location, $formatted_date, $description, $status, $new_image, $report_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('report_updated_by_admin', $current_user_id, 'success', "{$user_role_title} updated report #{$report_id}", 'item_report', (string) $report_id);

            if ($prev_report && $prev_status !== $status && (int) $prev_report['reporter_id'] !== $current_user_id && function_exists('create_notification')) {
                $status_labels = [
                    'open' => 'รอเจ้าของ',
                    'claimed' => 'มีผู้ขอรับคืน',
                    'resolved' => 'ส่งคืนสำเร็จ',
                    'closed' => 'ปิดเรื่องแล้ว',
                ];
                $st_name = $status_labels[$status] ?? $status;
                create_notification(
                    (int) $prev_report['reporter_id'],
                    'สถานะรายการของคุณมีการเปลี่ยนแปลง',
                    "รายการ \"{$prev_report['title']}\" ได้รับการปรับสถานะเป็น \"{$st_name}\" โดยผู้ดูแลระบบ",
                    "/reports/show.php?id={$report_id}"
                );
            }

            $_SESSION['flash_message'] = 'แก้ไขข้อมูลรายงานเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php');
    }

    // QUICK STATUS UPDATE
    elseif ($action === 'quick_status') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? 'open'));

        if ($report_id > 0 && in_array($status, ['open', 'claimed', 'resolved', 'closed'], true)) {
            // Fetch reporter and title for notification
            $rep_stmt = $connection->prepare('SELECT reporter_id, title FROM item_reports WHERE report_id = ? LIMIT 1');
            $rep_stmt->bind_param('i', $report_id);
            $rep_stmt->execute();
            $rep_info = $rep_stmt->get_result()->fetch_assoc();
            $rep_stmt->close();

            $resolved_sql = $status === 'resolved' ? 'NOW()' : 'NULL';
            $stmt = $connection->prepare("UPDATE item_reports SET status = ?, resolved_at = {$resolved_sql} WHERE report_id = ?");
            $stmt->bind_param('si', $status, $report_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('report_status_changed_by_admin', $current_user_id, 'success', "{$user_role_title} changed status of report #{$report_id} to {$status}", 'item_report', (string) $report_id);

            if ($rep_info && (int) $rep_info['reporter_id'] !== $current_user_id && function_exists('create_notification')) {
                $status_labels = [
                    'open' => 'รอเจ้าของ',
                    'claimed' => 'มีผู้ขอรับคืน',
                    'resolved' => 'ส่งคืนสำเร็จ',
                    'closed' => 'ปิดเรื่องแล้ว',
                ];
                $st_name = $status_labels[$status] ?? $status;
                create_notification(
                    (int) $rep_info['reporter_id'],
                    'สถานะรายการของคุณมีการเปลี่ยนแปลง',
                    "รายการ \"{$rep_info['title']}\" ได้รับการปรับสถานะเป็น \"{$st_name}\" โดยผู้ดูแลระบบ",
                    "/reports/show.php?id={$report_id}"
                );
            }

            $_SESSION['flash_message'] = 'ปรับสถานะรายงานเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php');
    }

    // SOFT DELETE REPORT (MOVE TO TRASH)
    elseif ($action === 'delete_report') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $stmt = $connection->prepare('UPDATE item_reports SET deleted_at = NOW() WHERE report_id = ?');
            $stmt->bind_param('i', $report_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('report_soft_deleted_by_admin', $current_user_id, 'success', "{$user_role_title} soft-deleted report #{$report_id}", 'item_report', (string) $report_id);

            $_SESSION['flash_message'] = 'ย้ายรายงานลงถังขยะเรียบร้อยแล้ว (สามารถกู้คืนได้)';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php');
    }

    // RESTORE REPORT FROM TRASH
    elseif ($action === 'restore_report') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $stmt = $connection->prepare('UPDATE item_reports SET deleted_at = NULL WHERE report_id = ?');
            $stmt->bind_param('i', $report_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('report_restored_by_admin', $current_user_id, 'success', "{$user_role_title} restored report #{$report_id}", 'item_report', (string) $report_id);

            $_SESSION['flash_message'] = 'กู้คืนรายงานสิ่งของเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php?trashed=1');
    }

    // PERMANENT DELETE (FORCE DELETE)
    elseif ($action === 'force_delete_report') {
        $report_id = (int) ($_POST['report_id'] ?? 0);
        if ($report_id > 0) {
            $stmt = $connection->prepare('SELECT image_filename FROM item_reports WHERE report_id = ? LIMIT 1');
            $stmt->bind_param('i', $report_id);
            $stmt->execute();
            $del_img = $stmt->get_result()->fetch_assoc()['image_filename'] ?? null;
            $stmt->close();

            $stmt = $connection->prepare('DELETE FROM item_reports WHERE report_id = ?');
            $stmt->bind_param('i', $report_id);
            $stmt->execute();
            $stmt->close();

            if (!empty($del_img)) {
                delete_report_image($del_img);
            }

            log_security_event('report_permanently_deleted', $current_user_id, 'success', "{$user_role_title} permanently deleted report #{$report_id}", 'item_report', (string) $report_id);

            $_SESSION['flash_message'] = 'ลบรายงานสิ่งของอย่างถาวรเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/reports.php?trashed=1');
    }
}

// Fetch categories for select
$stmt = $connection->prepare('SELECT category_id, category_name FROM categories WHERE is_active = 1 ORDER BY category_name ASC');
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch users for reporter select
$stmt = $connection->prepare('SELECT user_id, student_id, full_name FROM users ORDER BY full_name ASC');
$stmt->execute();
$all_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Check if edit mode is active
$edit_id = reports_positive_id($_GET['edit'] ?? null);
$editing_report = null;
if ($edit_id !== null) {
    $stmt = $connection->prepare('SELECT * FROM item_reports WHERE report_id = ? LIMIT 1');
    $stmt->bind_param('i', $edit_id);
    $stmt->execute();
    $editing_report = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Filters & Query
$search = trim((string) ($_GET['q'] ?? ''));
$filter_type = trim((string) ($_GET['type'] ?? ''));
$filter_cat = (int) ($_GET['category_id'] ?? 0);
$filter_status = trim((string) ($_GET['status'] ?? ''));
$filter_trashed = trim((string) ($_GET['trashed'] ?? '0'));

$sql = 'SELECT r.report_id, r.reporter_id, r.category_id, r.report_type, r.title, r.description, r.location,
               r.event_datetime, r.image_filename, r.status, r.created_at, r.deleted_at,
               c.category_name, u.full_name AS reporter_name,
               (SELECT COUNT(*) FROM claims WHERE report_id = r.report_id) AS total_claims
        FROM item_reports r
        JOIN categories c ON c.category_id = r.category_id
        JOIN users u ON u.user_id = r.reporter_id
        WHERE 1=1';

$count_sql = 'SELECT COUNT(*) AS total FROM item_reports r
              JOIN categories c ON c.category_id = r.category_id
              JOIN users u ON u.user_id = r.reporter_id WHERE 1=1';

$where_sql = '';
$where_types = '';
$where_params = [];

if ($filter_trashed === '1') {
    $where_sql .= ' AND r.deleted_at IS NOT NULL';
} else {
    $where_sql .= ' AND r.deleted_at IS NULL';
}

if ($search !== '') {
    $where_sql .= ' AND (r.title LIKE ? OR r.location LIKE ? OR r.description LIKE ?)';
    $st = '%' . $search . '%';
    $where_types .= 'sss';
    $where_params[] = $st;
    $where_params[] = $st;
    $where_params[] = $st;
}

if (in_array($filter_type, ['lost', 'found'], true)) {
    $where_sql .= ' AND r.report_type = ?';
    $where_types .= 's';
    $where_params[] = $filter_type;
}

if ($filter_cat > 0) {
    $where_sql .= ' AND r.category_id = ?';
    $where_types .= 'i';
    $where_params[] = $filter_cat;
}

if (in_array($filter_status, ['open', 'claimed', 'resolved', 'closed'], true)) {
    $where_sql .= ' AND r.status = ?';
    $where_types .= 's';
    $where_params[] = $filter_status;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reports_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Thai Excel
    fputcsv($out, ['ID', 'ประเภท', 'ชื่อสิ่งของ', 'หมวดหมู่', 'สถานที่', 'วันเวลาเกิดเหตุ', 'สถานะ', 'ผู้แจ้ง', 'วันที่สร้าง']);

    $export_sql = 'SELECT r.report_id, r.report_type, r.title, c.category_name, r.location, r.event_datetime, r.status, u.full_name AS reporter_name, r.created_at
                   FROM item_reports r
                   JOIN categories c ON c.category_id = r.category_id
                   JOIN users u ON u.user_id = r.reporter_id
                   WHERE 1=1' . $where_sql . ' ORDER BY r.created_at DESC';

    $exp_stmt = $connection->prepare($export_sql);
    if ($exp_stmt) {
        if ($where_params !== []) {
            $exp_stmt->bind_param($where_types, ...$where_params);
        }
        $exp_stmt->execute();
        $res = $exp_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['report_id'],
                $row['report_type'] === 'lost' ? 'ของหาย' : 'พบของ',
                $row['title'],
                $row['category_name'],
                $row['location'],
                $row['event_datetime'],
                $row['status'],
                $row['reporter_name'],
                $row['created_at'],
            ]);
        }
        $exp_stmt->close();
    }
    fclose($out);
    exit;
}

$items_per_page = 15;
$current_page = max(1, (int) ($_GET['page'] ?? 1));

$count_sql .= $where_sql;
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

$sql .= $where_sql . ' ORDER BY r.created_at DESC LIMIT ? OFFSET ?';
$types = $where_types . 'ii';
$params = array_merge($where_params, [$items_per_page, $offset]);

$stmt = $connection->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$category_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];

$status_info = [
    'open' => ['label' => 'รอเจ้าของ', 'class' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10'],
    'claimed' => ['label' => 'มีผู้ขอรับคืน', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
    'resolved' => ['label' => 'ส่งคืนสำเร็จ', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'closed' => ['label' => 'ปิดเรื่องแล้ว', 'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200'],
];

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$active_admin_tab = 'reports';
$admin_page_heading = 'จัดการรายงานสิ่งของ (Item Reports)';
render_header('จัดการรายงานสิ่งของ - Campus Lost & Found System');
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
    $crud_title = $filter_trashed === '1' ? 'ถังขยะรายงานสิ่งของ' : 'จัดการรายงานสิ่งของ';
    $crud_subtitle = $filter_trashed === '1' ? 'Trashed Reports' : 'Item Reports Management';
    $crud_total = $total_items;
    $crud_create_label = $filter_trashed === '1' ? null : 'สร้างรายงานใหม่';
    $crud_create_modal_id = 'create-report-modal';

    ob_start();
    ?>
    <a
        href="/admin/reports.php?export=csv<?= !empty($_SERVER['QUERY_STRING']) ? '&' . e(http_build_query(array_diff_key($_GET, ['export' => '', 'page' => '']))) : '' ?>"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs sm:text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 transition"
    >
        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        ส่งออก CSV
    </a>
    <?php
    $crud_extra_actions = ob_get_clean();
    require APP_PATH . '/views/admin/components/crud_header.php';
    ?>

    <!-- Unified CRUD Filter -->
    <?php
    $filter_action = '/admin/reports.php';
    $filter_search_value = $search;
    $filter_search_placeholder = 'ค้นหาชื่อสิ่งของ, สถานที่, รายละเอียด...';
    $filter_has_active_filters = ($search !== '' || $filter_type !== '' || $filter_cat > 0 || $filter_status !== '' || $filter_trashed === '1');
    ob_start();
    ?>
    <div class="w-full sm:w-36">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ประเภท</label>
        <select name="type" onchange="this.form.submit()" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <option value="">ทั้งหมด</option>
            <option value="lost" <?= $filter_type === 'lost' ? 'selected' : '' ?>>ของหาย (Lost)</option>
            <option value="found" <?= $filter_type === 'found' ? 'selected' : '' ?>>พบของ (Found)</option>
        </select>
    </div>

    <div class="w-full sm:w-44">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">หมวดหมู่</label>
        <select name="category_id" onchange="this.form.submit()" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <option value="">ทุกหมวดหมู่</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= (int) $c['category_id'] ?>" <?= $filter_cat === (int) $c['category_id'] ? 'selected' : '' ?>>
                    <?= e($category_translations[$c['category_name']] ?? $c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="w-full sm:w-40">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
        <select name="status" onchange="this.form.submit()" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <option value="">ทุกสถานะ</option>
            <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>รอเจ้าของ</option>
            <option value="claimed" <?= $filter_status === 'claimed' ? 'selected' : '' ?>>มีผู้ขอรับคืน</option>
            <option value="resolved" <?= $filter_status === 'resolved' ? 'selected' : '' ?>>ส่งคืนสำเร็จ</option>
            <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>ปิดเรื่องแล้ว</option>
        </select>
    </div>

    <div class="w-full sm:w-40">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะรายการ</label>
        <select name="trashed" onchange="this.form.submit()" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <option value="0" <?= $filter_trashed !== '1' ? 'selected' : '' ?>>รายการปกติ</option>
            <option value="1" <?= $filter_trashed === '1' ? 'selected' : '' ?>>ถังขยะ (Recycle Bin)</option>
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
                <?= $filter_trashed === '1' ? 'รายการในถังขยะ' : 'รายการรายงานสิ่งของ' ?> (<?= number_format($total_items) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs sm:text-sm">
                <thead class="bg-gray-50/70 text-gray-500 font-medium text-xs">
                    <tr>
                        <th class="px-5 py-3">รหัส &amp; รูปภาพ</th>
                        <th class="px-5 py-3">ชื่อสิ่งของ &amp; หมวดหมู่</th>
                        <th class="px-5 py-3">ประเภท</th>
                        <th class="px-5 py-3">ผู้รายงาน</th>
                        <th class="px-5 py-3">สถานที่ &amp; วันเวลา</th>
                        <th class="px-5 py-3">สถานะ</th>
                        <th class="px-5 py-3 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($reports === []): ?>
                        <?php
                        $empty_title = $filter_trashed === '1' ? 'ไม่มีรายการในถังขยะ' : 'ไม่พบข้อมูลรายงานสิ่งของ';
                        $empty_message = $filter_trashed === '1' ? 'ถังขยะว่างเปล่า ไม่มีรายงานที่ถูกย้ายลงถังขยะ' : 'ไม่พบรายงานที่ตรงกับคำค้นหาหรือตัวกรองที่เลือก';
                        $empty_colspan = 7;
                        require APP_PATH . '/views/admin/components/crud_empty.php';
                        ?>
                    <?php endif; ?>
                    <?php foreach ($reports as $r): ?>
                        <?php
                        $st = $status_info[$r['status']] ?? ['label' => ucfirst($r['status']), 'class' => 'bg-gray-100 text-gray-700'];
                        $is_trashed = !empty($r['deleted_at']);
                        $img_url = !empty($r['image_filename']) ? get_report_image_url($r['image_filename']) : null;
                        ?>
                        <tr class="transition hover:bg-gray-50/60 <?= $is_trashed ? 'bg-rose-50/20' : '' ?>">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <?php if ($img_url !== null): ?>
                                        <img src="<?= e($img_url) ?>" alt="" class="h-10 w-10 rounded-lg object-cover border border-gray-200 shadow-2xs">
                                    <?php else: ?>
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="font-mono text-gray-400 font-medium text-xs">#<?= (int) $r['report_id'] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="/reports/show.php?id=<?= (int) $r['report_id'] ?>" target="_blank" class="font-semibold text-gray-900 hover:text-gray-600 transition text-sm">
                                    <?= e($r['title']) ?>
                                </a>
                                <div class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-1.5">
                                    <span><?= e($category_translations[$r['category_name']] ?? $r['category_name']) ?></span>
                                    <?php if ((int) $r['total_claims'] > 0): ?>
                                        <span>·</span>
                                        <a href="/admin/claims.php?report_id=<?= (int) $r['report_id'] ?>" class="font-medium text-gray-700 hover:underline">
                                            <?= (int) $r['total_claims'] ?> คำขอ
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if ($r['report_type'] === 'lost'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        ของหาย
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        พบของ
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-700 font-medium text-xs sm:text-sm">
                                <?= e($r['reporter_name']) ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-600 text-xs sm:text-sm">
                                <div class="font-medium text-gray-800"><?= e($r['location']) ?></div>
                                <div class="text-xs text-gray-400 mt-0.5"><?= substr(e($r['event_datetime']), 0, 16) ?></div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if ($is_trashed): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        ในถังขยะ
                                    </span>
                                <?php elseif ($r['status'] === 'open'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        รอเจ้าของ
                                    </span>
                                <?php elseif ($r['status'] === 'claimed'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20">
                                        <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                                        มีผู้ขอรับคืน
                                    </span>
                                <?php elseif ($r['status'] === 'resolved'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        ส่งคืนสำเร็จ
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        ปิดเรื่องแล้ว
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1.5">
                                <?php if (!$is_trashed): ?>
                                    <!-- ปุ่มแก้ไข (เปิด Edit Modal ทันทีด้วย client-side JS) -->
                                    <button
                                        type="button"
                                        data-id="<?= (int) $r['report_id'] ?>"
                                        data-title="<?= e($r['title']) ?>"
                                        data-reporttype="<?= e($r['report_type']) ?>"
                                        data-categoryid="<?= (int) $r['category_id'] ?>"
                                        data-location="<?= e($r['location']) ?>"
                                        data-eventdatetime="<?= substr(str_replace(' ', 'T', (string)$r['event_datetime']), 0, 16) ?>"
                                        data-status="<?= e($r['status']) ?>"
                                        data-description="<?= e($r['description'] ?? '') ?>"
                                        data-imageurl="<?= !empty($r['image_filename']) ? e(get_report_image_url($r['image_filename'])) : '' ?>"
                                        onclick="editReportFromBtn(this)"
                                        class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
                                        title="แก้ไขข้อมูลและสถานะรายงาน"
                                    >
                                        แก้ไข
                                    </button>

                                    <!-- ปุ่มย้ายลงถังขยะ (เปิด Shared Delete Modal) -->
                                    <button
                                        type="button"
                                        data-id="<?= (int) $r['report_id'] ?>"
                                        data-title="<?= e($r['title']) ?>"
                                        onclick="confirmSoftDeleteReportFromBtn(this)"
                                        class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition shadow-xs cursor-pointer"
                                        title="ย้ายลงถังขยะ"
                                    >
                                        ลบ
                                    </button>
                                <?php else: ?>
                                    <!-- ปุ่มกู้คืน -->
                                    <button
                                        type="button"
                                        data-id="<?= (int) $r['report_id'] ?>"
                                        data-title="<?= e($r['title']) ?>"
                                        onclick="confirmRestoreReportFromBtn(this)"
                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 transition shadow-xs cursor-pointer"
                                        title="กู้คืนรายการจากถังขยะ"
                                    >
                                        กู้คืน
                                    </button>

                                    <!-- ปุ่มลบถาวร -->
                                    <button
                                        type="button"
                                        data-id="<?= (int) $r['report_id'] ?>"
                                        data-title="<?= e($r['title']) ?>"
                                        onclick="confirmForceDeleteReportFromBtn(this)"
                                        class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition shadow-xs cursor-pointer"
                                        title="ลบรายงานอย่างถาวร"
                                    >
                                        ลบถาวร
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php
    $base_url = '/admin/reports.php';
    $query_params = $_GET;
    require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<!-- Modal สร้างรายงานใหม่ (Create Report Modal) -->
<?php
$modal_id = 'create-report-modal';
$modal_title = 'สร้างรายงานสิ่งของใหม่';
$modal_subtitle = 'กรอกข้อมูลสิ่งของที่สูญหายหรือถูกเก็บได้เพื่อประกาศในระบบ';
$modal_max_width = 'max-w-2xl';
ob_start();
?>
<form method="POST" action="/admin/reports.php" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_report">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อสิ่งของ <span class="text-rose-500">*</span></label>
            <input type="text" name="title" required placeholder="เช่น กระเป๋าเป้สีดำ, โทรศัพท์ iPhone 15" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ประเภทรายงาน <span class="text-rose-500">*</span></label>
            <select name="report_type" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="lost">ของหาย (Lost)</option>
                <option value="found">พบสิ่งของ (Found)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">หมวดหมู่ <span class="text-rose-500">*</span></label>
            <select name="category_id" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="">เลือกหมวดหมู่</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['category_id'] ?>">
                        <?= e($category_translations[$cat['category_name']] ?? $cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานที่เกิดเหตุ <span class="text-rose-500">*</span></label>
            <input type="text" name="location" required placeholder="เช่น โรงอาหารคอมเพล็กซ์, สำนักหอสมุด" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">วันและเวลาที่เกิดเหตุ <span class="text-rose-500">*</span></label>
            <input type="datetime-local" name="event_datetime" required value="<?= date('Y-m-d\TH:i') ?>" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ผู้รายงาน</label>
            <select name="reporter_id" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <?php foreach ($all_users as $usr): ?>
                    <option value="<?= (int) $usr['user_id'] ?>" <?= ((int) $usr['user_id']) === $current_user_id ? 'selected' : '' ?>>
                        <?= e($usr['full_name']) ?> (<?= e($usr['student_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รายละเอียดสิ่งของ <span class="text-rose-500">*</span></label>
            <textarea name="description" rows="3" required placeholder="ระบุสี ขนาด ยี่ห้อ หรือจุดสังเกตเฉพาะ..." class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"></textarea>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รูปภาพสิ่งของ (ถ้ามี)</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <p class="mt-1 text-xs text-gray-500">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 3 MB</p>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('create-report-modal')"
            class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
        >
            ยกเลิก
        </button>
        <button
            type="submit"
            class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
        >
            สร้างรายงาน
        </button>
    </div>
</form>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Modal แก้ไขรายงาน (Edit Report Modal - พร้อมใช้งานตลอดเวลา Client-side Populated) -->
<?php
$modal_id = 'edit-report-modal';
$modal_title = 'แก้ไขข้อมูลรายงาน';
$modal_subtitle = 'ปรับปรุงรายละเอียด สถานะ หรือรูปภาพของรายงาน';
$modal_max_width = 'max-w-2xl';
ob_start();
?>
<form method="POST" action="/admin/reports.php" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_report">
    <input type="hidden" name="report_id" id="edit-report-id" value="<?= (int) ($editing_report['report_id'] ?? 0) ?>">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อสิ่งของ <span class="text-rose-500">*</span></label>
            <input type="text" name="title" id="edit-report-title" required value="<?= e($editing_report['title'] ?? '') ?>" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ประเภทรายงาน <span class="text-rose-500">*</span></label>
            <select name="report_type" id="edit-report-type" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="lost" <?= ($editing_report['report_type'] ?? '') === 'lost' ? 'selected' : '' ?>>ของหาย (Lost)</option>
                <option value="found" <?= ($editing_report['report_type'] ?? '') === 'found' ? 'selected' : '' ?>>พบสิ่งของ (Found)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">หมวดหมู่ <span class="text-rose-500">*</span></label>
            <select name="category_id" id="edit-report-category" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['category_id'] ?>" <?= ((int) ($editing_report['category_id'] ?? 0)) === ((int) $cat['category_id']) ? 'selected' : '' ?>>
                        <?= e($category_translations[$cat['category_name']] ?? $cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานที่ <span class="text-rose-500">*</span></label>
            <input type="text" name="location" id="edit-report-location" required value="<?= e($editing_report['location'] ?? '') ?>" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">วันและเวลาที่เกิดเหตุ <span class="text-rose-500">*</span></label>
            <input type="datetime-local" name="event_datetime" id="edit-report-datetime" required value="<?= !empty($editing_report['event_datetime']) ? substr(str_replace(' ', 'T', $editing_report['event_datetime']), 0, 16) : '' ?>" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
            <select name="status" id="edit-report-status" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="open" <?= ($editing_report['status'] ?? '') === 'open' ? 'selected' : '' ?>>รอเจ้าของ (Open)</option>
                <option value="claimed" <?= ($editing_report['status'] ?? '') === 'claimed' ? 'selected' : '' ?>>มีผู้ขอรับคืน (Claimed)</option>
                <option value="resolved" <?= ($editing_report['status'] ?? '') === 'resolved' ? 'selected' : '' ?>>ส่งคืนสำเร็จ (Resolved)</option>
                <option value="closed" <?= ($editing_report['status'] ?? '') === 'closed' ? 'selected' : '' ?>>ปิดเรื่องแล้ว (Closed)</option>
            </select>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รายละเอียด <span class="text-rose-500">*</span></label>
            <textarea name="description" id="edit-report-description" rows="3" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"><?= e($editing_report['description'] ?? '') ?></textarea>
        </div>

        <div class="sm:col-span-2">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รูปภาพสิ่งของ</label>
            <div id="edit-report-img-wrap" class="<?= !empty($editing_report['image_filename']) ? '' : 'hidden' ?> mb-2.5 flex items-center gap-3.5 rounded-lg border border-gray-200 bg-gray-50/70 p-3">
                <img id="edit-report-img" src="<?= !empty($editing_report['image_filename']) ? e(get_report_image_url($editing_report['image_filename'])) : '' ?>" alt="" class="h-14 w-14 rounded-lg object-cover border border-gray-200 shadow-2xs">
                <div>
                    <span class="font-semibold text-gray-800 text-sm">มีรูปภาพประกอบปัจจุบัน</span>
                    <label class="mt-1 flex items-center gap-2 cursor-pointer text-rose-600 font-medium text-xs hover:text-rose-700">
                        <input type="checkbox" name="remove_image" id="edit-report-remove-img" value="1" class="rounded border-gray-300 text-rose-600 focus:ring-rose-500">
                        ลบรูปภาพนี้ออกจากรายงาน
                    </label>
                </div>
            </div>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            <p class="mt-1 text-xs text-gray-500">รองรับไฟล์ JPG, PNG, WEBP ขนาดไม่เกิน 3 MB</p>
        </div>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('edit-report-modal')"
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
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Shared Delete Confirmation Modal -->
<?php require APP_PATH . '/views/admin/components/crud_delete_modal.php'; ?>

<script>
function editReportFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    const type = btn.getAttribute('data-reporttype');
    const catId = btn.getAttribute('data-categoryid');
    const location = btn.getAttribute('data-location');
    const datetime = btn.getAttribute('data-eventdatetime');
    const status = btn.getAttribute('data-status');
    const desc = btn.getAttribute('data-description');
    const imgUrl = btn.getAttribute('data-imageurl');

    editReport(id, title, type, catId, location, datetime, status, desc, imgUrl);
}

function editReport(id, title, type, catId, location, datetime, status, desc, imgUrl) {
    const titleModalEl = document.getElementById('edit-report-modal-title');
    if (titleModalEl) {
        titleModalEl.textContent = 'แก้ไขข้อมูลรายงาน #' + id;
    }
    const idEl = document.getElementById('edit-report-id');
    const titleEl = document.getElementById('edit-report-title');
    const typeEl = document.getElementById('edit-report-type');
    const catEl = document.getElementById('edit-report-category');
    const locEl = document.getElementById('edit-report-location');
    const dateEl = document.getElementById('edit-report-datetime');
    const statusEl = document.getElementById('edit-report-status');
    const descEl = document.getElementById('edit-report-description');
    const imgWrap = document.getElementById('edit-report-img-wrap');
    const imgEl = document.getElementById('edit-report-img');
    const removeImgEl = document.getElementById('edit-report-remove-img');

    if (idEl) idEl.value = id;
    if (titleEl) titleEl.value = title;
    if (typeEl) typeEl.value = type;
    if (catEl) catEl.value = catId;
    if (locEl) locEl.value = location;
    if (dateEl) dateEl.value = datetime;
    if (statusEl) statusEl.value = status;
    if (descEl) descEl.value = desc;
    if (removeImgEl) removeImgEl.checked = false;

    if (imgWrap && imgEl) {
        if (imgUrl && imgUrl.trim() !== '') {
            imgEl.src = imgUrl;
            imgWrap.classList.remove('hidden');
        } else {
            imgEl.src = '';
            imgWrap.classList.add('hidden');
        }
    }

    if (typeof openAdminModal === 'function') {
        openAdminModal('edit-report-modal');
    } else {
        const modal = document.getElementById('edit-report-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            document.body.classList.add('overflow-hidden');
            if (titleEl) setTimeout(() => titleEl.focus(), 50);
        }
    }
}

function confirmSoftDeleteReportFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    confirmSoftDeleteReport(id, title);
}

function confirmSoftDeleteReport(id, title) {
    openAdminDeleteModal({
        url: '/admin/reports.php',
        action: 'delete_report',
        idName: 'report_id',
        idValue: id,
        title: 'ยืนยันย้ายรายงานลงถังขยะ',
        message: 'คุณต้องการย้ายรายงาน "' + title + '" ลงถังขยะใช่หรือไม่?',
        warning: 'รายการจะถูกซ่อนจากหน้ารายงานทั่วไป แต่ยังสามารถกู้คืนได้จากเมนูถังขยะ',
        submitLabel: 'ย้ายลงถังขยะ'
    });
}

function confirmForceDeleteReportFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    confirmForceDeleteReport(id, title);
}

function confirmForceDeleteReport(id, title) {
    openAdminDeleteModal({
        url: '/admin/reports.php',
        action: 'force_delete_report',
        idName: 'report_id',
        idValue: id,
        title: 'ยืนยันลบรายงานอย่างถาวร',
        message: 'คุณต้องการลบรายงาน "' + title + '" ออกจากฐานข้อมูลอย่างถาวรใช่หรือไม่?',
        warning: 'การกระทำนี้จะลบข้อมูลและไฟล์รูปภาพทั้งหมดอย่างถาวร และไม่สามารถย้อนกลับได้',
        submitLabel: 'ยืนยันลบถาวร'
    });
}

function confirmRestoreReportFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    openAdminDeleteModal({
        url: '/admin/reports.php',
        action: 'restore_report',
        idName: 'report_id',
        idValue: id,
        title: 'ยืนยันกู้คืนรายงาน',
        message: 'คุณต้องการกู้คืนรายงาน "' + title + '" กลับมาสู่ระบบใช่หรือไม่?',
        warning: 'รายงานจะกลับมาแสดงในหน้ารายงานสิ่งของตามปกติ',
        submitLabel: 'ยืนยันกู้คืน'
    });
}
</script>

<?php if ($editing_report !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    editReport(
        '<?= (int) $editing_report['report_id'] ?>',
        '<?= e(addslashes($editing_report['title'])) ?>',
        '<?= e(addslashes($editing_report['report_type'])) ?>',
        '<?= (int) $editing_report['category_id'] ?>',
        '<?= e(addslashes($editing_report['location'])) ?>',
        '<?= substr(str_replace(' ', 'T', (string)$editing_report['event_datetime']), 0, 16) ?>',
        '<?= e(addslashes($editing_report['status'])) ?>',
        '<?= e(addslashes($editing_report['description'])) ?>',
        '<?= !empty($editing_report['image_filename']) ? e(get_report_image_url($editing_report['image_filename'])) : '' ?>'
    );
});
</script>
<?php endif; ?>

<?php render_footer(); ?>
