<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// ถ้า login อยู่แล้ว redirect ไป homepage
if (is_authenticated()) {
    redirect('/index.php');
}

$error = '';
$captcha_question = '';
$show_captcha = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check login throttle first
    $client_ip = get_client_ip();
    if (check_login_throttle($client_ip)) {
        log_security_event('login_throttled', null, 'blocked', "IP: {$client_ip}");
        $error = 'มีการพยายาม login ผิดพลาดหลายครั้ง กรุณารอ 15 นาที';
    } elseif (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'การยืนยันความปลอดภัยล้มเหลว';
    } else {
        $raw_identifier = $_POST['identifier'] ?? '';
        $raw_password = $_POST['password'] ?? '';

        if (!is_string($raw_identifier) || !is_string($raw_password)) {
            $error = 'รูปแบบข้อมูลไม่ถูกต้อง';
        } else {
            // Check if CAPTCHA is required
            if (is_captcha_required($client_ip)) {
                $show_captcha = true;

                // Verify CAPTCHA if submitted
                $captcha_answer = $_POST['captcha_answer'] ?? '';
                if (!is_string($captcha_answer) && !is_numeric($captcha_answer) || trim((string) $captcha_answer) === '') {
                    $error = 'กรุณากรอกคำตอบยืนยันตัวตนให้ครบถ้วน';
                    $captcha_question = generate_math_captcha();
                } elseif (!verify_captcha($captcha_answer)) {
                    $error = 'คำตอบยืนยันตัวตนไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
                    $captcha_question = generate_math_captcha();
                }
            }

            // Proceed to login if no CAPTCHA error
            if (empty($error)) {
                $identifier = sanitize_string($raw_identifier);
                $password = $raw_password;

                if (empty($identifier) || empty($password)) {
                    $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
                } else {
                    // Query user by email OR student_id
                    $conn = get_db_connection();
                    if (!$conn) {
                        $error = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
                    } else {
                        $stmt = $conn->prepare("
                            SELECT user_id, student_id, full_name, email, password_hash, role, account_status
                            FROM users
                            WHERE email = ? OR student_id = ?
                            LIMIT 1
                        ");

                        $stmt->bind_param('ss', $identifier, $identifier);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        if ($result->num_rows === 0) {
                            // User not found
                            log_security_event('login_failed', null, 'failed', "Unknown identifier: {$identifier}");
                            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
                        } else {
                            $user = $result->fetch_assoc();

                            // Verify password
                            if (!verify_password($password, $user['password_hash'])) {
                                log_security_event('login_failed', (int) $user['user_id'], 'failed', 'Invalid password');
                                $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
                            }
                            // Check account status
                            elseif ($user['account_status'] !== 'active') {
                                log_security_event('login_failed', (int) $user['user_id'], 'failed', "Account status: {$user['account_status']}");

                                if ($user['account_status'] === 'suspended') {
                                    $error = 'บัญชีของคุณถูกระงับการใช้งาน';
                                } else {
                                    $error = 'บัญชีของคุณยังไม่เปิดใช้งาน';
                                }
                            }
                            // Success
                            else {
                                log_security_event('login_success', (int) $user['user_id'], 'success', null);
                                clear_failed_login_attempts($client_ip);
                                login_user($user);
                                redirect('/index.php');
                            }
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }
}

// Generate CAPTCHA for display if needed
$client_ip = get_client_ip();
if (is_captcha_required($client_ip)) {
    $show_captcha = true;
    if (empty($captcha_question)) {
        $captcha_question = generate_math_captcha();
    }
}

render_header('เข้าสู่ระบบ - Campus Lost & Found System');
?>

<div class="mx-auto max-w-md">
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">เข้าสู่ระบบ</h1>
        <p class="mt-1 text-sm text-gray-500">กรอกข้อมูลเพื่อเข้าสู่บัญชีของคุณ</p>

        <?php if (!empty($error)): ?>
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3">
                <p class="text-sm text-red-700"><?= e($error) ?></p>
            </div>
        <?php elseif (isset($_GET['suspended'])): ?>
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3">
                <p class="text-sm text-red-700">บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="mt-4 rounded-md border border-yellow-200 bg-yellow-50 p-3">
                <p class="text-sm text-yellow-700">
                    <?php
                    $timeout_type = $_GET['timeout'] ?? '1';
                    $duration = (int)($_GET['duration'] ?? 0);

                    if ($timeout_type === '2') {
                        // Absolute timeout - แสดงเป็นชั่วโมง
                        $hours = floor($duration / 60);
                        echo "เซสชันของคุณหมดอายุแล้ว (เกินเวลาที่กำหนด {$hours} ชั่วโมง) กรุณาเข้าสู่ระบบอีกครั้ง";
                    } else {
                        // Idle timeout - แสดงเป็นนาที
                        echo "เซสชันของคุณหมดอายุแล้ว (ไม่มีกิจกรรม {$duration} นาที) กรุณาเข้าสู่ระบบอีกครั้ง";
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($show_captcha): ?>
            <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 p-3">
                <p class="text-sm text-blue-700">ตรวจพบความพยายาม login หลายครั้ง กรุณาตอบคำถามด้านล่าง</p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="mt-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="identifier" class="block text-sm font-medium text-gray-700">อีเมลหรือรหัสนักศึกษา</label>
                <input type="text" id="identifier" name="identifier" value="<?= e(is_string($_POST['identifier'] ?? null) ? $_POST['identifier'] : '') ?>" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="student@university.edu หรือรหัสนักศึกษา" autocomplete="username" required>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="รหัสผ่านของคุณ" autocomplete="current-password" required>
            </div>

            <?php if ($show_captcha): ?>
                <div class="rounded-md border border-gray-300 bg-gray-50 p-4">
                    <label for="captcha_answer" class="block text-sm font-medium text-gray-900">ยืนยันตัวตน: <?= e($captcha_question) ?></label>
                    <input type="number" id="captcha_answer" name="captcha_answer" class="mt-2 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="ใส่คำตอบ" required>
                    <p class="mt-1 text-xs text-gray-500">กรุณาคำนวณและใส่คำตอบ</p>
                </div>
            <?php endif; ?>

            <button type="submit" class="w-full rounded-md bg-gray-900 py-2 text-sm font-medium text-white hover:bg-gray-800">
                เข้าสู่ระบบ
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">
            ยังไม่มีบัญชี?
            <a href="/auth/register.php" class="font-medium text-gray-900 hover:underline">สมัครสมาชิก</a>
        </p>
    </div>
</div>

<?php
render_footer();
