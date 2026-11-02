<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// ถ้า login อยู่แล้ว redirect ไป homepage
if (is_authenticated()) {
    redirect('/index.php');
}

$errors = [];
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors['general'] = 'การยืนยันความปลอดภัยล้มเหลว';
    } else {
        // Validate input types
        if (
            (isset($_POST['student_id']) && !is_string($_POST['student_id'])) ||
            (isset($_POST['full_name']) && !is_string($_POST['full_name'])) ||
            (isset($_POST['email']) && !is_string($_POST['email'])) ||
            (isset($_POST['phone']) && !is_string($_POST['phone'])) ||
            (isset($_POST['password']) && !is_string($_POST['password'])) ||
            (isset($_POST['confirm_password']) && !is_string($_POST['confirm_password']))
        ) {
            $errors['general'] = 'รูปแบบข้อมูลไม่ถูกต้อง';
        } else {
            // Sanitize inputs
            $student_id = sanitize_string($_POST['student_id'] ?? '');
            $full_name = sanitize_string($_POST['full_name'] ?? '');
            $email = sanitize_string($_POST['email'] ?? '');
            $phone = !empty($_POST['phone']) ? sanitize_string($_POST['phone']) : null;
            $password = (string) ($_POST['password'] ?? '');
            $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        // Validate student_id
        if (empty($student_id)) {
            $errors['student_id'] = 'กรุณากรอกรหัสนักศึกษา';
        } elseif (!validate_student_id($student_id)) {
            $errors['student_id'] = 'รูปแบบรหัสไม่ถูกต้อง';
        }

        // Validate full_name
        if (empty($full_name)) {
            $errors['full_name'] = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 150) {
            $errors['full_name'] = 'ชื่อต้องมีความยาว 2-150 ตัวอักษร';
        }

        // Validate email
        if (empty($email)) {
            $errors['email'] = 'กรุณากรอกอีเมล';
        } elseif (!validate_email($email)) {
            $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }

        // Validate phone (optional)
        if ($phone !== null && !validate_phone($phone)) {
            $errors['phone'] = 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง';
        }

        // Validate password
        $password_validation = validate_password($password);
        if (!$password_validation['valid']) {
            $errors['password'] = implode(' ', $password_validation['errors']);
        }

        // Validate confirm password
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'รหัสผ่านไม่ตรงกัน';
        }

        // Check uniqueness (student_id and email)
        if (empty($errors)) {
            $conn = get_db_connection();
            if (!$conn) {
                $errors['general'] = 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้';
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE student_id = ? OR email = ?");
                $stmt->bind_param('ss', $student_id, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $errors['general'] = 'รหัสนักศึกษาหรืออีเมลนี้มีในระบบแล้ว';
                }

                $stmt->close();
            }
        }

        // Insert new user
        if (empty($errors)) {
            $password_hash = hash_password($password);
            $role = 'student';
            $account_status = 'active';

            $conn = get_db_connection();
            $stmt = $conn->prepare("
                INSERT INTO users (student_id, full_name, email, phone, password_hash, role, account_status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param('sssssss', $student_id, $full_name, $email, $phone, $password_hash, $role, $account_status);

            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                $stmt->close();

                // Log success
                log_security_event('register', $user_id, 'success', null);

                // Fetch complete user record for login
                $stmt = $conn->prepare("SELECT user_id, student_id, full_name, email, role FROM users WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                // Auto-login and redirect
                login_user($user);
                redirect('/index.php');
            } else {
                log_security_event('register', null, 'failed', 'Database insert error');
                $errors['general'] = 'เกิดข้อผิดพลาดในการสมัครสมาชิก กรุณาลองใหม่';
            }
        } else {
            // Log failed registration (validation errors)
            log_security_event('register', null, 'failed', 'Validation errors');
        }
    }
}
}

render_header('สมัครสมาชิก - Campus Lost & Found System');
?>

<div class="mx-auto max-w-md">
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-gray-900">สมัครสมาชิก</h1>
        <p class="mt-1 text-sm text-gray-500">ลงทะเบียนด้วยข้อมูลของคุณเพื่อรายงานหรือเคลมสิ่งของ</p>

        <?php if (isset($errors['general'])): ?>
            <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3">
                <p class="text-sm text-red-700"><?= e($errors['general']) ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="mt-6 space-y-4">
            <?= csrf_field() ?>

            <div>
                <label for="student_id" class="block text-sm font-medium text-gray-700">รหัสนักศึกษา</label>
                <input type="text" id="student_id" name="student_id" value="<?= e(is_string($_POST['student_id'] ?? null) ? $_POST['student_id'] : '') ?>" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="กรอกรหัสประจำตัว" required>
                <?php if (isset($errors['student_id'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['student_id']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="full_name" class="block text-sm font-medium text-gray-700">ชื่อ-นามสกุล</label>
                <input type="text" id="full_name" name="full_name" value="<?= e(is_string($_POST['full_name'] ?? null) ? $_POST['full_name'] : '') ?>" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="กรอกชื่อและนามสกุล" required>
                <?php if (isset($errors['full_name'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['full_name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">อีเมล</label>
                <input type="email" id="email" name="email" value="<?= e(is_string($_POST['email'] ?? null) ? $_POST['email'] : '') ?>" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="student@university.edu" autocomplete="email" required>
                <?php if (isset($errors['email'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['email']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">เบอร์โทรศัพท์</label>
                <input type="tel" id="phone" name="phone" value="<?= e(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '') ?>" class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="กรอกเบอร์ติดต่อ (ไม่บังคับ)">
                <?php if (isset($errors['phone'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['phone']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <div class="relative mt-1">
                    <input type="password" id="password" name="password" class="block w-full rounded-md border border-gray-300 pr-10 pl-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="อย่างน้อย 8 ตัวอักษร" autocomplete="new-password" required>
                    <button type="button" id="toggle-password-btn" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                        <svg id="eye-icon-password" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg id="eye-off-icon-password" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>

                <!-- หลอดวัดความปลอดภัยของรหัสผ่าน (Password Strength Meter) -->
                <div id="password-strength-container" class="mt-2.5 rounded-lg border border-gray-100 bg-gray-50/70 p-3 space-y-2 hidden transition-all">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500 font-medium">ระดับความปลอดภัย:</span>
                        <span id="password-strength-text" class="font-semibold text-rose-600">ง่ายเกินไป</span>
                    </div>

                    <!-- หลอด 4 ส่วน (4-segment progress bar) -->
                    <div class="grid grid-cols-4 gap-1.5 h-1.5 w-full">
                        <div id="strength-bar-1" class="h-full rounded-full bg-gray-200 transition-colors duration-200"></div>
                        <div id="strength-bar-2" class="h-full rounded-full bg-gray-200 transition-colors duration-200"></div>
                        <div id="strength-bar-3" class="h-full rounded-full bg-gray-200 transition-colors duration-200"></div>
                        <div id="strength-bar-4" class="h-full rounded-full bg-gray-200 transition-colors duration-200"></div>
                    </div>

                    <!-- รายการเกณฑ์ความปลอดภัย (Checklist) -->
                    <ul class="space-y-1 pt-1 text-[11px] text-gray-500">
                        <li id="rule-length" class="flex items-center gap-1.5 transition-colors duration-150">
                            <svg class="h-3.5 w-3.5 text-gray-300 rule-icon shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="rule-text">อย่างน้อย 8 ตัวอักษร</span>
                        </li>
                        <li id="rule-cases" class="flex items-center gap-1.5 transition-colors duration-150">
                            <svg class="h-3.5 w-3.5 text-gray-300 rule-icon shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="rule-text">มีทั้งตัวพิมพ์เล็กและพิมพ์ใหญ่ (a-z, A-Z)</span>
                        </li>
                        <li id="rule-number" class="flex items-center gap-1.5 transition-colors duration-150">
                            <svg class="h-3.5 w-3.5 text-gray-300 rule-icon shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="rule-text">มีตัวเลขอย่างน้อย 1 ตัว (0-9)</span>
                        </li>
                        <li id="rule-special" class="flex items-center gap-1.5 transition-colors duration-150">
                            <svg class="h-3.5 w-3.5 text-gray-300 rule-icon shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="rule-text">มีอักขระพิเศษ (เช่น !@#$%^&* หรือสัญลักษณ์)</span>
                        </li>
                    </ul>
                </div>

                <?php if (isset($errors['password'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['password']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700">ยืนยันรหัสผ่าน</label>
                <div class="relative mt-1">
                    <input type="password" id="confirm_password" name="confirm_password" class="block w-full rounded-md border border-gray-300 pr-10 pl-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none" placeholder="ใส่รหัสผ่านอีกครั้ง" autocomplete="new-password" required>
                    <button type="button" id="toggle-confirm-password-btn" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none" aria-label="แสดงหรือซ่อนรหัสผ่าน">
                        <svg id="eye-icon-confirm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg id="eye-off-icon-confirm" class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <p class="mt-1 text-sm text-red-600"><?= e($errors['confirm_password']) ?></p>
                <?php endif; ?>
            </div>

            <button type="submit" class="w-full rounded-md bg-gray-900 py-2 text-sm font-medium text-white hover:bg-gray-800">
                สมัครสมาชิก
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">
            มีบัญชีอยู่แล้ว?
            <a href="/auth/login.php" class="font-medium text-gray-900 hover:underline">เข้าสู่ระบบ</a>
        </p>
    </div>
</div>

<script>
// Real-time validation for register form
document.addEventListener('DOMContentLoaded', function() {
    const studentId = document.getElementById('student_id');
    const fullName = document.getElementById('full_name');
    const email = document.getElementById('email');
    const phone = document.getElementById('phone');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    // Helper: show error
    function showError(input, message) {
        clearError(input);
        const error = document.createElement('p');
        error.className = 'mt-1 text-sm text-red-600';
        error.textContent = message;
        error.setAttribute('data-error', 'true');
        input.parentElement.appendChild(error);
        input.classList.add('border-red-500');
    }

    // Helper: clear error
    function clearError(input) {
        const existing = input.parentElement.querySelector('[data-error="true"]');
        if (existing) existing.remove();
        input.classList.remove('border-red-500');
    }

    // Validate student_id
    studentId.addEventListener('blur', function() {
        const value = this.value.trim();
        if (!value) {
            showError(this, 'กรุณากรอกรหัสนักศึกษา');
        } else if (!/^[0-9]{9}-[0-9]$/.test(value)) {
            showError(this, 'รูปแบบรหัสไม่ถูกต้อง (เช่น 671234567-8)');
        } else {
            clearError(this);
        }
    });

    // Validate full_name
    fullName.addEventListener('blur', function() {
        const value = this.value.trim();
        if (!value) {
            showError(this, 'กรุณากรอกชื่อ-นามสกุล');
        } else if (value.length < 2 || value.length > 150) {
            showError(this, 'ชื่อต้องมีความยาว 2-150 ตัวอักษร');
        } else {
            clearError(this);
        }
    });

    // Validate email
    email.addEventListener('blur', function() {
        const value = this.value.trim();
        if (!value) {
            showError(this, 'กรุณากรอกอีเมล');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            showError(this, 'รูปแบบอีเมลไม่ถูกต้อง');
        } else {
            clearError(this);
        }
    });

    // Validate phone (optional)
    phone.addEventListener('blur', function() {
        const value = this.value.trim();
        if (value && !/^0[0-9]{9}$/.test(value)) {
            showError(this, 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง (เช่น 0812345678)');
        } else {
            clearError(this);
        }
    });

    // Validate password
    password.addEventListener('blur', function() {
        const value = this.value;
        if (!value) {
            showError(this, 'กรุณากรอกรหัสผ่าน');
        } else if (value.length < 8) {
            showError(this, 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร');
        } else {
            clearError(this);
        }
    });

    // Validate confirm_password
    confirmPassword.addEventListener('blur', function() {
        const value = this.value;
        const passwordValue = password.value;
        if (!value) {
            showError(this, 'กรุณายืนยันรหัสผ่าน');
        } else if (value !== passwordValue) {
            showError(this, 'รหัสผ่านไม่ตรงกัน');
        } else {
            clearError(this);
        }
    });

    // Password Strength Meter & Real-time Checklist
    const strengthContainer = document.getElementById('password-strength-container');
    const strengthText = document.getElementById('password-strength-text');
    const bar1 = document.getElementById('strength-bar-1');
    const bar2 = document.getElementById('strength-bar-2');
    const bar3 = document.getElementById('strength-bar-3');
    const bar4 = document.getElementById('strength-bar-4');
    const bars = [bar1, bar2, bar3, bar4];

    function updateRule(elementId, passed) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const icon = el.querySelector('.rule-icon');
        if (passed) {
            el.className = 'flex items-center gap-1.5 transition-colors duration-150 text-emerald-700 font-medium';
            if (icon) icon.className = 'h-3.5 w-3.5 text-emerald-600 rule-icon shrink-0';
        } else {
            el.className = 'flex items-center gap-1.5 transition-colors duration-150 text-gray-500';
            if (icon) icon.className = 'h-3.5 w-3.5 text-gray-300 rule-icon shrink-0';
        }
    }

    function checkPasswordStrength(val) {
        if (!val || val.length === 0) {
            strengthContainer.classList.add('hidden');
            bars.forEach(b => {
                b.className = 'h-full rounded-full bg-gray-200 transition-colors duration-200';
            });
            return;
        }

        strengthContainer.classList.remove('hidden');

        const hasLength = val.length >= 8;
        const hasCases = /[a-z]/.test(val) && /[A-Z]/.test(val);
        const hasNumber = /[0-9]/.test(val);
        const hasSpecial = /[^a-zA-Z0-9]/.test(val);

        updateRule('rule-length', hasLength);
        updateRule('rule-cases', hasCases);
        updateRule('rule-number', hasNumber);
        updateRule('rule-special', hasSpecial);

        let score = 0;
        if (hasLength) score++;
        if (hasCases) score++;
        if (hasNumber) score++;
        if (hasSpecial) score++;

        // Reset all bars to neutral gray
        bars.forEach(b => {
            b.className = 'h-full rounded-full bg-gray-200 transition-colors duration-200';
        });

        if (score <= 1) {
            strengthText.textContent = 'ง่ายเกินไป';
            strengthText.className = 'font-semibold text-rose-600';
            bar1.className = 'h-full rounded-full bg-rose-500 transition-colors duration-200';
        } else if (score === 2) {
            strengthText.textContent = 'พอใช้';
            strengthText.className = 'font-semibold text-amber-600';
            bar1.className = 'h-full rounded-full bg-amber-500 transition-colors duration-200';
            bar2.className = 'h-full rounded-full bg-amber-500 transition-colors duration-200';
        } else if (score === 3) {
            strengthText.textContent = 'ค่อนข้างปลอดภัย';
            strengthText.className = 'font-semibold text-sky-600';
            bar1.className = 'h-full rounded-full bg-sky-500 transition-colors duration-200';
            bar2.className = 'h-full rounded-full bg-sky-500 transition-colors duration-200';
            bar3.className = 'h-full rounded-full bg-sky-500 transition-colors duration-200';
        } else {
            strengthText.textContent = 'ปลอดภัยสูง';
            strengthText.className = 'font-semibold text-emerald-600';
            bars.forEach(b => {
                b.className = 'h-full rounded-full bg-emerald-500 transition-colors duration-200';
            });
        }
    }

    // Show/Hide password toggle handlers
    function setupPasswordToggle(btnId, inputId, eyeId, eyeOffId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        const eye = document.getElementById(eyeId);
        const eyeOff = document.getElementById(eyeOffId);
        if (!btn || !input || !eye || !eyeOff) return;

        btn.addEventListener('click', function() {
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.add('hidden');
                eyeOff.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye.classList.remove('hidden');
                eyeOff.classList.add('hidden');
            }
        });
    }

    setupPasswordToggle('toggle-password-btn', 'password', 'eye-icon-password', 'eye-off-icon-password');
    setupPasswordToggle('toggle-confirm-password-btn', 'confirm_password', 'eye-icon-confirm', 'eye-off-icon-confirm');

    // Also validate confirm when password changes and update strength meter
    password.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        if (this.value.length >= 8) {
            clearError(this);
        }
        if (confirmPassword.value) {
            if (confirmPassword.value !== this.value) {
                showError(confirmPassword, 'รหัสผ่านไม่ตรงกัน');
            } else {
                clearError(confirmPassword);
            }
        }
    });

    if (password.value) {
        checkPasswordStrength(password.value);
    }
});
</script>

<?php
render_footer();
