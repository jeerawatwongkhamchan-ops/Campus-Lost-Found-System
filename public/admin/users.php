<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';

$admin = require_admin_access();

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
        redirect('/admin/users.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    // CREATE USER
    if ($action === 'create_user') {
        $student_id = trim((string) ($_POST['student_id'] ?? ''));
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? 'student'));
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];
        if (!validate_student_id($student_id)) {
            $errors[] = 'รูปแบบรหัสนักศึกษา/บุคลากรไม่ถูกต้อง (เช่น 673380001-1)';
        }
        if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 150) {
            $errors[] = 'ชื่อ-สกุล ต้องมีความยาว 2-150 ตัวอักษร';
        }
        if (!validate_email($email)) {
            $errors[] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }
        if (!empty($phone) && !validate_phone($phone)) {
            $errors[] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
        }
        if (!in_array($role, ['student', 'staff', 'admin'], true)) {
            $errors[] = 'บทบาทไม่ถูกต้อง';
        }
        if (strlen($password) < 8) {
            $errors[] = 'รหัสผ่านต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
        }

        if ($errors !== []) {
            $_SESSION['flash_message'] = implode(', ', $errors);
            $_SESSION['flash_type'] = 'error';
        } else {
            // Check unique student_id and email
            $stmt = $connection->prepare('SELECT user_id FROM users WHERE student_id = ? OR email = ? LIMIT 1');
            $stmt->bind_param('ss', $student_id, $email);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists !== null) {
                $_SESSION['flash_message'] = 'รหัสนักศึกษาหรืออีเมลนี้มีอยู่ในระบบแล้ว';
                $_SESSION['flash_type'] = 'error';
            } else {
                $password_hash = hash_password($password);
                $phone_val = $phone === '' ? null : $phone;
                $stmt = $connection->prepare(
                    'INSERT INTO users (student_id, full_name, email, phone, password_hash, role, account_status)
                     VALUES (?, ?, ?, ?, ?, ?, "active")'
                );
                $stmt->bind_param('ssssss', $student_id, $full_name, $email, $phone_val, $password_hash, $role);
                $stmt->execute();
                $new_user_id = (int) $connection->insert_id;
                $stmt->close();

                log_security_event('user_created', $current_user_id, 'success', "Admin created user #{$new_user_id} ({$email})", 'user', (string) $new_user_id);

                $_SESSION['flash_message'] = "เพิ่มผู้ใช้งาน \"{$full_name}\" เรียบร้อยแล้ว";
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/users.php');
    }

    // UPDATE USER INFO (Profile, Role, and Account Status)
    elseif ($action === 'update_user') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $student_id = trim((string) ($_POST['student_id'] ?? ''));
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $new_role = trim((string) ($_POST['role'] ?? ''));
        $new_status = trim((string) ($_POST['account_status'] ?? ''));

        if ($target_user_id <= 0 || mb_strlen($full_name) < 2 || !validate_email($email)) {
            $_SESSION['flash_message'] = 'ข้อมูลที่ระบุไม่ถูกต้อง';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Check duplicates
            $stmt = $connection->prepare('SELECT user_id FROM users WHERE (student_id = ? OR email = ?) AND user_id != ? LIMIT 1');
            $stmt->bind_param('ssi', $student_id, $email, $target_user_id);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists !== null) {
                $_SESSION['flash_message'] = 'รหัสนักศึกษาหรืออีเมลซ้ำกับผู้ใช้อื่นในระบบ';
                $_SESSION['flash_type'] = 'error';
            } else {
                // Fetch current user record to verify existence and check self restrictions
                $cur_stmt = $connection->prepare('SELECT role, account_status FROM users WHERE user_id = ? LIMIT 1');
                $cur_stmt->bind_param('i', $target_user_id);
                $cur_stmt->execute();
                $cur_user = $cur_stmt->get_result()->fetch_assoc();
                $cur_stmt->close();

                if ($cur_user !== null) {
                    $phone_val = $phone === '' ? null : $phone;
                    $is_self = $target_user_id === $current_user_id;

                    // Cannot change role or suspend self
                    $final_role = ($is_self || !in_array($new_role, ['student', 'staff', 'admin'], true))
                        ? (string) $cur_user['role']
                        : $new_role;

                    $final_status = ($is_self || !in_array($new_status, ['active', 'suspended'], true))
                        ? (string) $cur_user['account_status']
                        : $new_status;

                    $stmt = $connection->prepare('UPDATE users SET student_id = ?, full_name = ?, email = ?, phone = ?, role = ?, account_status = ? WHERE user_id = ?');
                    $stmt->bind_param('ssssssi', $student_id, $full_name, $email, $phone_val, $final_role, $final_status, $target_user_id);
                    $stmt->execute();
                    $stmt->close();

                    log_security_event('user_updated', $current_user_id, 'success', "Admin updated user #{$target_user_id} (role: {$final_role}, status: {$final_status})", 'user', (string) $target_user_id);

                    $_SESSION['flash_message'] = 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว';
                    $_SESSION['flash_type'] = 'success';
                }
            }
        }
        redirect('/admin/users.php');
    }

    // RESET PASSWORD
    elseif ($action === 'reset_password') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $new_password = (string) ($_POST['new_password'] ?? '');

        if ($target_user_id <= 0 || strlen($new_password) < 8) {
            $_SESSION['flash_message'] = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 8 ตัวอักษร';
            $_SESSION['flash_type'] = 'error';
        } else {
            $new_hash = hash_password($new_password);
            $stmt = $connection->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $stmt->bind_param('si', $new_hash, $target_user_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('password_reset_by_admin', $current_user_id, 'success', "Admin reset password for user #{$target_user_id}", 'user', (string) $target_user_id);

            $_SESSION['flash_message'] = 'รีเซ็ตรหัสผ่านสำหรับผู้ใช้เรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/users.php');
    }

    // TOGGLE STATUS
    elseif ($action === 'toggle_status') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        if ($target_user_id === $current_user_id) {
            $_SESSION['flash_message'] = 'คุณไม่สามารถระงับการใช้งานบัญชีของตนเองได้';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = $connection->prepare('SELECT account_status FROM users WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $target_user_id);
            $stmt->execute();
            $target = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($target !== null) {
                $new_status = $target['account_status'] === 'active' ? 'suspended' : 'active';
                $stmt = $connection->prepare('UPDATE users SET account_status = ? WHERE user_id = ?');
                $stmt->bind_param('si', $new_status, $target_user_id);
                $stmt->execute();
                $stmt->close();

                log_security_event('user_status_changed', $current_user_id, 'success', "Changed status of user #{$target_user_id} to {$new_status}", 'user', (string) $target_user_id);

                $_SESSION['flash_message'] = 'ปรับสถานะบัญชีผู้ใช้เรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/users.php');
    }

    // CHANGE ROLE
    elseif ($action === 'change_role') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $new_role = trim((string) ($_POST['role'] ?? ''));
        if (in_array($new_role, ['student', 'staff', 'admin'], true)) {
            if ($target_user_id === $current_user_id && $new_role !== 'admin') {
                $_SESSION['flash_message'] = 'คุณไม่สามารถลดระดับสิทธิ์ของบัญชีตนเองได้';
                $_SESSION['flash_type'] = 'error';
            } else {
                $stmt = $connection->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                $stmt->bind_param('si', $new_role, $target_user_id);
                $stmt->execute();
                $stmt->close();

                log_security_event('user_role_changed', $current_user_id, 'success', "Changed role of user #{$target_user_id} to {$new_role}", 'user', (string) $target_user_id);

                $_SESSION['flash_message'] = 'ปรับบทบาทของผู้ใช้เรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/users.php');
    }

    // DELETE USER
    elseif ($action === 'delete_user') {
        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        if ($target_user_id === $current_user_id) {
            $_SESSION['flash_message'] = 'คุณไม่สามารถลบบัญชีของตนเองได้';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = $connection->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $target_user_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('user_deleted', $current_user_id, 'success', "Deleted user #{$target_user_id}", 'user', (string) $target_user_id);

            $_SESSION['flash_message'] = 'ลบบัญชีผู้ใช้เรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/users.php');
    }
}

// Search and filter params
$search_query = trim((string) ($_GET['q'] ?? ''));
$filter_role = trim((string) ($_GET['role'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));

$sql = 'SELECT u.user_id, u.student_id, u.full_name, u.email, u.phone, u.role, u.account_status, u.created_at,
               (SELECT COUNT(*) FROM item_reports WHERE reporter_id = u.user_id) AS total_reports,
               (SELECT COUNT(*) FROM claims WHERE claimant_id = u.user_id) AS total_claims
        FROM users u WHERE 1=1';

$where_sql = '';
$where_types = '';
$where_params = [];

if ($search_query !== '') {
    $where_sql .= ' AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)';
    $st = '%' . $search_query . '%';
    $where_types .= 'sss';
    $where_params[] = $st;
    $where_params[] = $st;
    $where_params[] = $st;
}

if (in_array($filter_role, ['student', 'staff', 'admin'], true)) {
    $where_sql .= ' AND u.role = ?';
    $where_types .= 's';
    $where_params[] = $filter_role;
}

if (in_array($filter_status, ['active', 'suspended'], true)) {
    $where_sql .= ' AND u.account_status = ?';
    $where_types .= 's';
    $where_params[] = $filter_status;
}

$items_per_page = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));

// Count total
$count_sql = 'SELECT COUNT(*) AS total FROM users u WHERE 1=1' . $where_sql;
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

$sql .= $where_sql . ' ORDER BY u.user_id DESC LIMIT ? OFFSET ?';
$types = $where_types . 'ii';
$params = array_merge($where_params, [$items_per_page, $offset]);

$stmt = $connection->prepare($sql);
if ($params !== []) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$role_labels = [
    'student' => 'นักศึกษา',
    'staff' => 'เจ้าหน้าที่',
    'admin' => 'ผู้ดูแลระบบ',
];

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$active_admin_tab = 'users';
$admin_page_heading = 'จัดการผู้ใช้งานระบบ (Users)';
render_header('จัดการผู้ใช้งานระบบ - Campus Lost & Found System');
?>

<div class="space-y-6">
    <?php require APP_PATH . '/views/admin/components/nav.php'; ?>

    <?php if ($flash_message !== null): ?>
        <div class="alert-animate rounded-2xl border p-4 text-xs sm:text-sm font-medium shadow-xs flex items-center gap-3 <?= $flash_type === 'success' ? 'border-emerald-200/90 bg-emerald-50/90 text-emerald-900' : 'border-rose-200/90 bg-rose-50/90 text-rose-900' ?>">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl <?= $flash_type === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                <?php if ($flash_type === 'success'): ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                <?php else: ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01" />
                    </svg>
                <?php endif; ?>
            </div>
            <div class="flex-1"><?= e($flash_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Unified CRUD Header -->
    <?php
    $crud_title = 'จัดการผู้ใช้งานระบบ';
    $crud_subtitle = 'Users Management';
    $crud_total = $total_items;
    $crud_create_label = 'เพิ่มผู้ใช้งานใหม่';
    $crud_create_modal_id = 'create-user-modal';
    require APP_PATH . '/views/admin/components/crud_header.php';
    ?>

    <!-- Unified CRUD Filter -->
    <?php
    $filter_action = '/admin/users.php';
    $filter_search_value = $search_query;
    $filter_search_placeholder = 'ค้นหาชื่อ, รหัสนักศึกษา หรืออีเมล...';
    $filter_has_active_filters = ($search_query !== '' || $filter_role !== '' || $filter_status !== '');
    ob_start();
    ?>
    <div class="w-full sm:w-48">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">บทบาท</label>
        <select
            name="role"
            onchange="this.form.submit()"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="">ทั้งหมด</option>
            <option value="student" <?= $filter_role === 'student' ? 'selected' : '' ?>>นักศึกษา (Student)</option>
            <option value="staff" <?= $filter_role === 'staff' ? 'selected' : '' ?>>เจ้าหน้าที่ (Staff)</option>
            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ (Admin)</option>
        </select>
    </div>
    <div class="w-full sm:w-44">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
        <select
            name="status"
            onchange="this.form.submit()"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="">ทั้งหมด</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>ปกติ (Active)</option>
            <option value="suspended" <?= $filter_status === 'suspended' ? 'selected' : '' ?>>ถูกระงับ (Suspended)</option>
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
                รายชื่อผู้ใช้ทั้งหมด (<?= number_format($total_items) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs sm:text-sm">
                <thead class="bg-gray-50/70 text-gray-500 font-medium text-xs">
                    <tr>
                        <th class="px-5 py-3">รหัส / รหัสนักศึกษา</th>
                        <th class="px-5 py-3">ชื่อ - สกุล</th>
                        <th class="px-5 py-3">อีเมล / เบอร์โทร</th>
                        <th class="px-5 py-3">รายงาน / เคลม</th>
                        <th class="px-5 py-3">บทบาท</th>
                        <th class="px-5 py-3">สถานะบัญชี</th>
                        <th class="px-5 py-3 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($users === []): ?>
                        <?php
                        $empty_title = 'ไม่พบข้อมูลผู้ใช้งาน';
                        $empty_message = 'ไม่พบผู้ใช้ที่ตรงกับเงื่อนไขการค้นหาหรือตัวกรองที่เลือก';
                        $empty_colspan = 7;
                        require APP_PATH . '/views/admin/components/crud_empty.php';
                        ?>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <?php $is_self = ((int) $u['user_id']) === $current_user_id; ?>
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="font-mono text-gray-900 font-semibold text-sm"><?= e($u['student_id']) ?></div>
                                <div class="text-xs text-gray-400 font-mono">#<?= (int) $u['user_id'] ?></div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap font-semibold text-gray-900 text-sm">
                                <?= e($u['full_name']) ?>
                                <?php if ($is_self): ?>
                                    <span class="ml-1 text-xs text-gray-500 font-normal">(คุณ)</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-600 text-xs sm:text-sm">
                                <div class="font-medium text-gray-800"><?= e($u['email']) ?></div>
                                <?php if (!empty($u['phone'])): ?>
                                    <div class="text-xs text-gray-400"><?= e($u['phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-600 text-xs">
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-medium text-gray-700">รายงาน <?= (int) $u['total_reports'] ?></span>
                                    <span class="rounded-md bg-gray-100 px-2 py-0.5 font-medium text-gray-700">เคลม <?= (int) $u['total_claims'] ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?= render_role_badge($u['role']) ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if ($u['account_status'] === 'active'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        ปกติ
                                    </span>
                                <?php elseif ($u['account_status'] === 'suspended'): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        ถูกระงับ
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex rounded-md bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-200">
                                        <?= e($u['account_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1.5">
                                <!-- ปุ่มแก้ไขผู้ใช้ (เปิด Edit Modal จัดการข้อมูล บทบาท และสถานะ) -->
                                <button
                                    type="button"
                                    data-id="<?= (int) $u['user_id'] ?>"
                                    data-studentid="<?= e($u['student_id']) ?>"
                                    data-name="<?= e($u['full_name']) ?>"
                                    data-email="<?= e($u['email']) ?>"
                                    data-phone="<?= e($u['phone'] ?? '') ?>"
                                    data-role="<?= e($u['role']) ?>"
                                    data-status="<?= e($u['account_status']) ?>"
                                    data-isself="<?= $is_self ? '1' : '0' ?>"
                                    onclick="editUserFromBtn(this)"
                                    class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
                                    title="แก้ไขข้อมูล บทบาท และสถานะบัญชี"
                                >
                                    แก้ไข
                                </button>

                                <?php if (!$is_self): ?>
                                    <!-- ปุ่มลบ (เปิด Unified Delete Modal) -->
                                    <button
                                        type="button"
                                        data-id="<?= (int) $u['user_id'] ?>"
                                        data-name="<?= e($u['full_name']) ?>"
                                        data-reports="<?= (int) $u['total_reports'] ?>"
                                        data-claims="<?= (int) $u['total_claims'] ?>"
                                        onclick="confirmDeleteUserFromBtn(this)"
                                        class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition shadow-xs cursor-pointer"
                                        title="ลบบัญชีผู้ใช้"
                                    >
                                        ลบ
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400 font-medium">บัญชีคุณ</span>
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
    $base_url = '/admin/users.php';
    $query_params = $_GET;
    require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<!-- Modal เพิ่มผู้ใช้งานใหม่ -->
<?php
$modal_id = 'create-user-modal';
$modal_title = 'เพิ่มผู้ใช้งานใหม่';
$modal_subtitle = 'สร้างบัญชีผู้ใช้งานใหม่สำหรับนักศึกษา เจ้าหน้าที่ หรือผู้ดูแลระบบ';
$modal_max_width = 'max-w-xl';
ob_start();
?>
<form method="POST" action="/admin/users.php" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create_user">

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รหัสนักศึกษา/บุคลากร <span class="text-rose-500">*</span></label>
            <input type="text" name="student_id" required placeholder="เช่น 673380001-1" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อ - นามสกุล <span class="text-rose-500">*</span></label>
            <input type="text" name="full_name" required placeholder="ชื่อ นามสกุล" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">อีเมล <span class="text-rose-500">*</span></label>
            <input type="email" name="email" required placeholder="student@kkumail.com" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">เบอร์โทรศัพท์</label>
            <input type="tel" name="phone" placeholder="0812345678" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">บทบาท <span class="text-rose-500">*</span></label>
            <select name="role" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                <option value="student">นักศึกษา (Student)</option>
                <option value="staff">เจ้าหน้าที่ (Staff)</option>
                <option value="admin">ผู้ดูแลระบบ (Admin)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รหัสผ่านเริ่มต้น <span class="text-rose-500">*</span></label>
            <input type="password" name="password" required minlength="8" placeholder="อย่างน้อย 8 ตัวอักษร" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
        </div>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('create-user-modal')"
            class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
        >
            ยกเลิก
        </button>
        <button
            type="submit"
            class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
        >
            สร้างผู้ใช้งาน
        </button>
    </div>
</form>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Modal แก้ไขข้อมูลผู้ใช้ -->
<?php
$modal_id = 'edit-user-modal';
$modal_title = 'แก้ไขข้อมูลผู้ใช้งาน';
$modal_subtitle = 'ปรับปรุงรายละเอียดบัญชีผู้ใช้ หรือรีเซ็ตรหัสผ่าน';
$modal_max_width = 'max-w-xl';
ob_start();
?>
<div class="space-y-6">
    <form method="POST" action="/admin/users.php" class="space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="target_user_id" id="edit-user-id" value="">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">รหัสนักศึกษา/บุคลากร <span class="text-rose-500">*</span></label>
                <input type="text" name="student_id" id="edit-user-student-id" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            </div>

            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อ - นามสกุล <span class="text-rose-500">*</span></label>
                <input type="text" name="full_name" id="edit-user-fullname" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            </div>

            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">อีเมล <span class="text-rose-500">*</span></label>
                <input type="email" name="email" id="edit-user-email" required class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            </div>

            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">เบอร์โทรศัพท์</label>
                <input type="tel" name="phone" id="edit-user-phone" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
            </div>

            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">บทบาท (Role) <span class="text-rose-500">*</span></label>
                <select name="role" id="edit-user-role" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                    <option value="student">นักศึกษา (Student)</option>
                    <option value="staff">เจ้าหน้าที่ (Staff)</option>
                    <option value="admin">ผู้ดูแลระบบ (Admin)</option>
                </select>
                <p id="edit-user-self-warning-role" class="mt-1 text-xs text-amber-600 hidden">ไม่สามารถเปลี่ยนบทบาทของบัญชีที่คุณกำลังใช้งานได้</p>
            </div>

            <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะบัญชี <span class="text-rose-500">*</span></label>
                <select name="account_status" id="edit-user-status" class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition">
                    <option value="active">ปกติ (Active)</option>
                    <option value="suspended">ถูกระงับการใช้งาน (Suspended)</option>
                </select>
                <p id="edit-user-self-warning-status" class="mt-1 text-xs text-amber-600 hidden">ไม่สามารถระงับบัญชีที่คุณกำลังใช้งานได้</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
            <button
                type="button"
                onclick="closeAdminModal('edit-user-modal')"
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

    <!-- รีเซ็ตรหัสผ่านผู้ใช้ -->
    <div class="pt-5 border-t border-gray-100">
        <h4 class="text-xs sm:text-sm font-semibold text-gray-900 mb-2.5 flex items-center gap-2">
            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
            </svg>
            รีเซ็ตรหัสผ่าน (Reset Password)
        </h4>
        <form method="POST" action="/admin/users.php" class="flex flex-col sm:flex-row gap-2.5">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="target_user_id" id="reset-pwd-user-id" value="">
            <div class="flex-1">
                <input
                    type="password"
                    name="new_password"
                    required
                    minlength="8"
                    placeholder="รหัสผ่านใหม่อย่างน้อย 8 ตัวอักษร"
                    class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
                >
            </div>
            <button
                type="submit"
                class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer whitespace-nowrap"
            >
                เปลี่ยนรหัสผ่าน
            </button>
        </form>
    </div>
</div>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Shared Delete Confirmation Modal -->
<?php require APP_PATH . '/views/admin/components/crud_delete_modal.php'; ?>

<script>
function editUserFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const studentId = btn.getAttribute('data-studentid');
    const fullName = btn.getAttribute('data-name');
    const email = btn.getAttribute('data-email');
    const phone = btn.getAttribute('data-phone');
    const role = btn.getAttribute('data-role');
    const status = btn.getAttribute('data-status');
    const isSelf = btn.getAttribute('data-isself') === '1';

    editUser(id, studentId, fullName, email, phone, role, status, isSelf);
}

function editUser(id, studentId, fullName, email, phone, role = 'student', status = 'active', isSelf = false) {
    const editIdEl = document.getElementById('edit-user-id');
    const resetIdEl = document.getElementById('reset-pwd-user-id');
    const studentIdEl = document.getElementById('edit-user-student-id');
    const nameEl = document.getElementById('edit-user-fullname');
    const emailEl = document.getElementById('edit-user-email');
    const phoneEl = document.getElementById('edit-user-phone');
    const roleEl = document.getElementById('edit-user-role');
    const statusEl = document.getElementById('edit-user-status');
    const warnRoleEl = document.getElementById('edit-user-self-warning-role');
    const warnStatusEl = document.getElementById('edit-user-self-warning-status');

    if (editIdEl) editIdEl.value = id;
    if (resetIdEl) resetIdEl.value = id;
    if (studentIdEl) studentIdEl.value = studentId;
    if (nameEl) nameEl.value = fullName;
    if (emailEl) emailEl.value = email;
    if (phoneEl) phoneEl.value = phone || '';
    if (roleEl) {
        roleEl.value = role;
        roleEl.disabled = isSelf;
    }
    if (statusEl) {
        statusEl.value = status;
        statusEl.disabled = isSelf;
    }
    if (warnRoleEl) warnRoleEl.classList.toggle('hidden', !isSelf);
    if (warnStatusEl) warnStatusEl.classList.toggle('hidden', !isSelf);

    if (typeof openAdminModal === 'function') {
        openAdminModal('edit-user-modal');
    } else {
        const modal = document.getElementById('edit-user-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            document.body.classList.add('overflow-hidden');
            if (nameEl) setTimeout(() => nameEl.focus(), 50);
        }
    }
}

function confirmDeleteUserFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const totalReports = parseInt(btn.getAttribute('data-reports') || '0', 10);
    const totalClaims = parseInt(btn.getAttribute('data-claims') || '0', 10);
    confirmDeleteUser(id, name, totalReports, totalClaims);
}

function confirmDeleteUser(id, name, totalReports, totalClaims) {
    let warning = '';
    if (totalReports > 0 || totalClaims > 0) {
        warning = 'ผู้ใช้งานนี้มีรายงาน ' + totalReports + ' รายการ และคำขอรับคืน ' + totalClaims + ' รายการ ข้อมูลที่เกี่ยวข้องทั้งหมดจะถูกลบออกจากระบบด้วย';
    }

    if (typeof openAdminDeleteModal === 'function') {
        openAdminDeleteModal({
            url: '/admin/users.php',
            action: 'delete_user',
            idName: 'target_user_id',
            idValue: id,
            title: 'ยืนยันลบบัญชีผู้ใช้ "' + name + '"',
            message: 'คุณต้องการลบบัญชีผู้ใช้นี้ออกจากระบบอย่างถาวรใช่หรือไม่?',
            warning: warning,
            submitLabel: 'ยืนยันลบผู้ใช้'
        });
    } else {
        if (confirm('คุณต้องการลบบัญชีผู้ใช้ "' + name + '" ออกจากระบบอย่างถาวรใช่หรือไม่?' + (warning ? '\n\n' + warning : ''))) {
            const form = document.getElementById('admin-delete-form');
            if (form) {
                const actionInput = document.getElementById('delete-form-action');
                const idInput = document.getElementById('delete-form-id');
                if (actionInput) actionInput.value = 'delete_user';
                if (idInput) {
                    idInput.name = 'target_user_id';
                    idInput.value = id;
                }
                form.submit();
            }
        }
    }
}
</script>

<?php render_footer(); ?>
