<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// ต้อง login ก่อน
require_auth();

$current_user = get_auth_user();
$success_message = '';
$errors = [];

// Fetch complete user data including password_hash for password change
$conn = get_db_connection();
$stmt = $conn->prepare("
    SELECT user_id, student_id, full_name, email, phone, password_hash, role, account_status
    FROM users
    WHERE user_id = ?
");
$user_id = get_auth_user_id();
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user_data) {
    logout_user();
    redirect('/auth/login.php');
}

// Activity summary stats
$user_counts = [
    'reports' => 0,
    'claims_sent' => 0,
    'claims_received' => 0,
];

if ($conn) {
    // Count user reports
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM item_reports WHERE reporter_id = ? AND deleted_at IS NULL');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_counts['reports'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Count user claims sent
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM claims WHERE claimant_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_counts['claims_sent'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Count incoming claims on user reports
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM claims cl JOIN item_reports r ON r.report_id = cl.report_id WHERE r.reporter_id = ? AND r.deleted_at IS NULL');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_counts['claims_received'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'การยืนยันความปลอดภัยล้มเหลว';
    } else {
        $action = $_POST['action'] ?? '';

        // Handle profile update
        if ($action === 'update_profile') {
            $full_name = sanitize_string($_POST['full_name'] ?? '');
            $phone = !empty($_POST['phone']) ? sanitize_string($_POST['phone']) : null;

            // Validate
            if (empty($full_name)) {
                $errors['full_name'] = 'กรุณากรอกชื่อ-นามสกุล';
            } elseif (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 150) {
                $errors['full_name'] = 'ชื่อต้องมีความยาว 2-150 ตัวอักษร';
            }

            if ($phone !== null && !validate_phone($phone)) {
                $errors['phone'] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
            }

            // Update database
            if (empty($errors)) {
                $conn = get_db_connection();
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
                $stmt->bind_param('ssi', $full_name, $phone, $user_id);

                if ($stmt->execute()) {
                    $stmt->close();

                    // Refresh session data from database
                    refresh_session_user();

                    // Reload user data for display
                    $user_data['full_name'] = $full_name;
                    $user_data['phone'] = $phone;

                    $success_message = 'อัปเดตข้อมูลสำเร็จ';
                } else {
                    $errors['general'] = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
                }
            }
        }

        // Handle password change
        elseif ($action === 'change_password') {
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_new_password = $_POST['confirm_new_password'] ?? '';

            // Verify old password
            if (empty($old_password)) {
                $errors['old_password'] = 'กรุณากรอกรหัสผ่านเดิม';
            } elseif (!verify_password($old_password, $user_data['password_hash'])) {
                $errors['old_password'] = 'รหัสผ่านเดิมไม่ถูกต้อง';
            }

            // Validate new password
            if (empty($new_password)) {
                $errors['new_password'] = 'กรุณากรอกรหัสผ่านใหม่';
            } else {
                $password_validation = validate_password($new_password);
                if (!$password_validation['valid']) {
                    $errors['new_password'] = implode(' ', $password_validation['errors']);
                }
            }

            // Confirm match
            if ($new_password !== $confirm_new_password) {
                $errors['confirm_new_password'] = 'รหัสผ่านใหม่ไม่ตรงกัน';
            }

            // Update password
            if (empty($errors)) {
                $new_hash = hash_password($new_password);

                $conn = get_db_connection();
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param('si', $new_hash, $user_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    log_security_event('password_change', $user_id, 'success', null);

                    // Update local data
                    $user_data['password_hash'] = $new_hash;

                    $success_message = 'เปลี่ยนรหัสผ่านสำเร็จ';
                } else {
                    $errors['general'] = 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน';
                }
            }
        }
    }
}

// Role and status translations
$role_labels = [
    'student' => 'นักศึกษา',
    'staff' => 'เจ้าหน้าที่',
    'admin' => 'ผู้ดูแลระบบ',
];

$status_labels = [
    'active' => 'ใช้งานได้',
    'inactive' => 'ยังไม่เปิดใช้งาน',
    'suspended' => 'ถูกระงับ',
];

render_header('โปรไฟล์ - Campus Lost & Found System');
?>

<div class="mx-auto max-w-3xl space-y-6">
    <div class="pt-1">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900">โปรไฟล์ของฉัน</h1>
        <p class="mt-1 text-sm text-gray-500">จัดการข้อมูลส่วนตัว ความปลอดภัย และตรวจสอบกิจกรรมของคุณ</p>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-medium text-emerald-800">
            <?= e($success_message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-800">
            <?= e($errors['general']) ?>
        </div>
    <?php endif; ?>

    <!-- กิจกรรมและสถิติของผู้ใช้ -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="/reports/index.php?my=1" class="rounded-xl border border-gray-200/80 bg-white p-4.5 shadow-xs transition hover:border-gray-400 hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">รายงานที่คุณแจ้ง</span>
                <span class="text-xs text-gray-400">&rarr;</span>
            </div>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900"><?= number_format($user_counts['reports']) ?></p>
            <p class="mt-1 text-[11px] text-gray-400">คลิกเพื่อดูและจัดการรายงาน</p>
        </a>

        <a href="/claims/index.php?tab=sent" class="rounded-xl border border-gray-200/80 bg-white p-4.5 shadow-xs transition hover:border-gray-400 hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">คำขอรับคืนที่คุณส่ง</span>
                <span class="text-xs text-gray-400">&rarr;</span>
            </div>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900"><?= number_format($user_counts['claims_sent']) ?></p>
            <p class="mt-1 text-[11px] text-gray-400">ติดตามคำขอยืนยันความเป็นเจ้าของ</p>
        </a>

        <a href="/claims/index.php?tab=received" class="rounded-xl border border-gray-200/80 bg-white p-4.5 shadow-xs transition hover:border-gray-400 hover:shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-gray-500">คำขอที่ส่งถึงรายงานคุณ</span>
                <span class="text-xs text-gray-400">&rarr;</span>
            </div>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900"><?= number_format($user_counts['claims_received']) ?></p>
            <p class="mt-1 text-[11px] text-gray-400">ตรวจสอบและอนุมัติการส่งคืน</p>
        </a>
    </div>

    <!-- ข้อมูลบัญชี -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-5 sm:p-6 shadow-xs">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-700">ข้อมูลบัญชี</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 text-xs">
            <div>
                <dt class="font-medium text-gray-500">รหัสนักศึกษา / บุคลากร</dt>
                <dd class="mt-1 font-semibold text-gray-900 font-mono"><?= e($user_data['student_id']) ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">อีเมล</dt>
                <dd class="mt-1 font-semibold text-gray-900"><?= e($user_data['email']) ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">บทบาทในระบบ</dt>
                <dd class="mt-1">
                    <?= render_role_badge($user_data['role'], true, true) ?>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">สถานะบัญชี</dt>
                <dd class="mt-1">
                    <?php if ($user_data['account_status'] === 'active'): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            <?= e($status_labels[$user_data['account_status']] ?? 'ใช้งานได้') ?>
                        </span>
                    <?php elseif ($user_data['account_status'] === 'suspended'): ?>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            <?= e($status_labels[$user_data['account_status']] ?? 'ถูกระงับ') ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            <?= e($status_labels[$user_data['account_status']] ?? 'ยังไม่เปิดใช้งาน') ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </div>

    <!-- ฟอร์มแก้ไขข้อมูลส่วนตัว -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-5 sm:p-6 shadow-xs">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-700">แก้ไขข้อมูลส่วนตัว</h2>
        <form method="POST" action="" class="mt-4 space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div>
                <label for="full_name" class="block font-medium text-gray-700">ชื่อ - สกุล</label>
                <input type="text" id="full_name" name="full_name" value="<?= e($user_data['full_name']) ?>" class="mt-1 block w-full rounded-lg border border-gray-200 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-gray-900 focus:outline-none" required>
                <?php if (isset($errors['full_name'])): ?>
                    <p class="mt-1 text-xs text-rose-600"><?= e($errors['full_name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="phone" class="block font-medium text-gray-700">เบอร์โทรศัพท์ (สำหรับติดต่อกรณีส่งคืนของ)</label>
                <input type="tel" id="phone" name="phone" value="<?= e($user_data['phone'] ?? '') ?>" class="mt-1 block w-full rounded-lg border border-gray-200 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-gray-900 focus:outline-none" placeholder="เช่น 0812345678">
                <?php if (isset($errors['phone'])): ?>
                    <p class="mt-1 text-xs text-rose-600"><?= e($errors['phone']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white hover:bg-gray-800 transition">
                บันทึกข้อมูล
            </button>
        </form>
    </div>

    <!-- ฟอร์มเปลี่ยนรหัสผ่าน -->
    <div class="rounded-xl border border-gray-200/80 bg-white p-5 sm:p-6 shadow-xs">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-700">เปลี่ยนรหัสผ่าน</h2>
        <form method="POST" action="" class="mt-4 space-y-4 text-xs">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">

            <div>
                <label for="old_password" class="block font-medium text-gray-700">รหัสผ่านเดิม</label>
                <input type="password" id="old_password" name="old_password" class="mt-1 block w-full rounded-lg border border-gray-200 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-gray-900 focus:outline-none" autocomplete="current-password" required>
                <?php if (isset($errors['old_password'])): ?>
                    <p class="mt-1 text-xs text-rose-600"><?= e($errors['old_password']) ?></p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="new_password" class="block font-medium text-gray-700">รหัสผ่านใหม่ (อย่างน้อย 8 ตัวอักษร)</label>
                    <input type="password" id="new_password" name="new_password" class="mt-1 block w-full rounded-lg border border-gray-200 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-gray-900 focus:outline-none" autocomplete="new-password" required>
                    <?php if (isset($errors['new_password'])): ?>
                        <p class="mt-1 text-xs text-rose-600"><?= e($errors['new_password']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="confirm_new_password" class="block font-medium text-gray-700">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="mt-1 block w-full rounded-lg border border-gray-200 px-3.5 py-2 text-xs text-gray-900 shadow-xs focus:border-gray-900 focus:outline-none" autocomplete="new-password" required>
                    <?php if (isset($errors['confirm_new_password'])): ?>
                        <p class="mt-1 text-xs text-rose-600"><?= e($errors['confirm_new_password']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white hover:bg-gray-800 transition">
                เปลี่ยนรหัสผ่าน
            </button>
        </form>
    </div>
</div>

<?php
render_footer();
