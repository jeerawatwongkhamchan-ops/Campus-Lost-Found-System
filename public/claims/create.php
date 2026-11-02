<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';

require_auth();

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$user_id = (int) $_SESSION['user_id'];

// Verify user account status from database
$user_stmt = $connection->prepare('SELECT account_status FROM users WHERE user_id = ? LIMIT 1');
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if ($current_user === null || $current_user['account_status'] !== 'active') {
    logout_user();
    redirect('/auth/login.php?suspended=1');
}

$requested_report_id = reports_positive_id($_GET['report_id'] ?? $_POST['report_id'] ?? null);

// Fetch all active reports that can be claimed (filter out deleted_at)
$stmt = $connection->prepare(
    'SELECT r.report_id, r.reporter_id, r.report_type, r.title, r.description, r.location,
            r.event_datetime, r.status, c.category_name, u.full_name AS reporter_name
     FROM item_reports r
     JOIN categories c ON c.category_id = r.category_id
     JOIN users u ON u.user_id = r.reporter_id
     WHERE r.status IN ("open", "claimed") AND r.deleted_at IS NULL
     ORDER BY r.created_at DESC'
);
$stmt->execute();
$available_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected_report = null;
if ($requested_report_id !== null) {
    foreach ($available_reports as $rep) {
        if ((int) $rep['report_id'] === $requested_report_id) {
            $selected_report = $rep;
            break;
        }
    }
    if ($selected_report === null) {
        $errors['report_id'] = 'ไม่พบรายการสิ่งของ หรือรายการนี้ถูกลบ/ปิดไปแล้ว';
    }
}

// If no report specified or not found in active list, default to first available report if any
if ($requested_report_id === null && $selected_report === null && $available_reports !== []) {
    $selected_report = $available_reports[0];
}

$errors = [];
$values = [
    'report_id' => $selected_report !== null ? (string) $selected_report['report_id'] : '',
    'claim_type' => 'owner',
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors['general'] = 'การยืนยันความปลอดภัยไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $report_id = reports_positive_id($_POST['report_id'] ?? null);
        $raw_claim_type = $_POST['claim_type'] ?? '';
        $raw_message = $_POST['message'] ?? '';
        $claim_type = is_string($raw_claim_type) ? trim($raw_claim_type) : '';
        $message = is_string($raw_message) ? trim($raw_message) : '';

        $values['report_id'] = (string) ($report_id ?? '');
        $values['claim_type'] = $claim_type;
        $values['message'] = $message;

        if ($report_id === null) {
            $errors['report_id'] = 'กรุณาเลือกสิ่งของที่ต้องการยื่นคำขอรับคืน';
        } else {
            // Find report directly from database ensuring it is active and not soft-deleted
            $check_stmt = $connection->prepare(
                'SELECT r.report_id, r.reporter_id, r.report_type, r.title, r.description, r.location,
                        r.event_datetime, r.status, c.category_name, u.full_name AS reporter_name
                 FROM item_reports r
                 JOIN categories c ON c.category_id = r.category_id
                 JOIN users u ON u.user_id = r.reporter_id
                 WHERE r.report_id = ? AND r.status IN ("open", "claimed") AND r.deleted_at IS NULL
                 LIMIT 1'
            );
            $check_stmt->bind_param('i', $report_id);
            $check_stmt->execute();
            $target_report = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($target_report === null) {
                $errors['report_id'] = 'ไม่พบรายการสิ่งของ หรือรายการนี้ถูกปิด/ลบไปแล้ว';
            } else {
                $selected_report = $target_report;

                // Check duplicate pending claim by this user
                $stmt = $connection->prepare(
                    'SELECT claim_id FROM claims WHERE report_id = ? AND claimant_id = ? AND status = "pending" LIMIT 1'
                );
                $stmt->bind_param('ii', $report_id, $user_id);
                $stmt->execute();
                $existing_claim = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing_claim !== null) {
                    $errors['general'] = 'คุณได้ส่งคำขอสำหรับสิ่งของชิ้นนี้แล้ว และอยู่ระหว่างรอการตรวจสอบ';
                }
            }
        }

        $valid_types = [
            'owner' => 'ยืนยันความเป็นเจ้าของ',
            'finder' => 'เป็นผู้เก็บสิ่งของได้',
            'info' => 'แจ้งเบาะแส / ข้อมูลเพิ่มเติม',
        ];

        if (!array_key_exists($claim_type, $valid_types)) {
            $errors['claim_type'] = 'กรุณาเลือกประเภทคำขอที่ถูกต้อง';
        }

        $length = mb_strlen($message);
        if ($length < 5 || $length > 2000) {
            $errors['message'] = 'กรุณาระบุรายละเอียดหรือหลักฐานยืนยันความยาว 5 - 2,000 ตัวอักษร';
        }

        // Process proof image upload if provided
        $upload_result = validate_and_upload_claim_image($_FILES['proof_image'] ?? null);
        if (!$upload_result['success']) {
            $errors['proof_image'] = $upload_result['error'];
        }

        if ($errors === [] && $selected_report !== null) {
            // Re-verify user is still active before inserting claim
            $user_recheck = $connection->prepare('SELECT account_status FROM users WHERE user_id = ? LIMIT 1');
            $user_recheck->bind_param('i', $user_id);
            $user_recheck->execute();
            $recheck_user = $user_recheck->get_result()->fetch_assoc();
            $user_recheck->close();

            if ($recheck_user === null || $recheck_user['account_status'] !== 'active') {
                logout_user();
                redirect('/auth/login.php?suspended=1');
            }

            $type_label = $valid_types[$claim_type];
            $target_report_id = (int) $selected_report['report_id'];
            $proof_filename = $upload_result['filename'];

            $stmt = $connection->prepare(
                'INSERT INTO claims (report_id, claimant_id, claim_type, message, proof_image, status) VALUES (?, ?, ?, ?, ?, "pending")'
            );
            $stmt->bind_param('iisss', $target_report_id, $user_id, $type_label, $message, $proof_filename);
            $stmt->execute();
            $claim_id = (int) $connection->insert_id;
            $stmt->close();

            log_security_event('claim_created', $user_id, 'success', 'Submitted claim #' . $claim_id . ' for report #' . $target_report_id, 'claim', (string) $claim_id);

            // Dispatch in-app notification to reporter
            $reporter_id = (int) $selected_report['reporter_id'];
            if ($reporter_id !== $user_id) {
                create_notification(
                    $reporter_id,
                    'คำขอรับคืนสิ่งของ: มีผู้ขอนัดรับของ',
                    "มีผู้ยื่นคำขอรับคืนสำหรับสิ่งของ: \"{$selected_report['title']}\" ติดต่อเพื่อนัดตรวจหลักฐานและส่งมอบได้เลย",
                    '/reports/show.php?id=' . $target_report_id
                );
            }

            $_SESSION['flash_message'] = 'ยื่นคำขอรับคืนสิ่งของเรียบร้อยแล้ว คุณสามารถติดต่อผู้พบสิ่งของเพื่อนัดรับได้โดยตรง';
            $_SESSION['flash_type'] = 'success';
            redirect('/reports/show.php?id=' . $target_report_id);
        }
    }
}

$category_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา / นาฬิกา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];

render_header('ยื่นคำขอรับคืนสิ่งของ - Campus Lost & Found System');
?>

<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <a href="/claims/index.php" class="hover:text-gray-900 transition">&larr; ระบบการเคลม</a>
            <span>/</span>
            <a href="/reports/index.php" class="hover:text-gray-900 transition">รายการสิ่งของ</a>
        </div>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900">ยื่นคำขอรับคืนสิ่งของ</h1>
        <p class="mt-1 text-sm text-gray-500">กรอกข้อมูลและหลักฐานยืนยันความเป็นเจ้าของ เพื่อประสานงานและนัดรับของคืนกับผู้พบได้โดยตรง</p>
    </div>

    <!-- กล่องแนะนำ Community Handover -->
    <div class="rounded-xl bg-emerald-50/60 p-4 border border-emerald-100 flex items-start gap-3">
        <svg class="h-5 w-5 shrink-0 text-emerald-700 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <div class="text-xs text-emerald-950 leading-relaxed">
            <strong class="font-semibold text-emerald-900">ระบบ Community Lost & Found:</strong>
            เมื่อคุณส่งคำขอแล้ว ระบบจะแจ้งเตือนผู้พบสิ่งของ และคุณจะได้รับข้อมูลติดต่อ (เบอร์โทร / อีเมล) ของผู้พบทันที เพื่อให้สามารถนัดเจอกันตามพื้นที่ส่วนกลางในมหาวิทยาลัย และตรวจหลักฐานเพื่อรับของคืนได้โดยตรง
        </div>
    </div>

    <?php if ($available_reports === []): ?>
        <div class="rounded-xl border border-gray-200/80 bg-white p-8 text-center shadow-xs">
            <h3 class="text-sm font-semibold text-gray-900">ขณะนี้ไม่มีรายการสิ่งของที่เปิดให้ยื่นคำขอรับคืน</h3>
            <p class="mt-1 text-xs text-gray-500">ยังไม่มีรายงานสิ่งของ หรือรายการทั้งหมดได้รับการส่งคืนเรียบร้อยแล้ว</p>
            <div class="mt-4">
                <a href="/reports/create.php" class="inline-flex items-center rounded-lg bg-gray-900 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-gray-800 transition">
                    แจ้งของหายหรือพบของใหม่
                </a>
            </div>
        </div>
    <?php else: ?>
        <form method="POST" action="/claims/create.php" enctype="multipart/form-data" class="space-y-5 rounded-xl border border-gray-200/80 bg-white p-5 sm:p-6 shadow-xs">
            <?= csrf_field() ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 border border-rose-200">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <!-- เลือกสิ่งของที่ต้องการเคลม -->
            <div>
                <label for="report_id" class="block text-xs font-medium text-gray-700">
                    เลือกรายการสิ่งของที่ต้องการยื่นคำขอ <span class="text-rose-500">*</span>
                </label>
                <select
                    name="report_id"
                    id="report_id"
                    onchange="window.location.href = '/claims/create.php?report_id=' + this.value"
                    class="mt-1.5 block w-full rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs text-gray-900 focus:border-gray-900 focus:outline-none"
                >
                    <?php foreach ($available_reports as $rep): ?>
                        <?php
                            $is_current = $selected_report !== null && ((int) $selected_report['report_id']) === ((int) $rep['report_id']);
                            $type_text = $rep['report_type'] === 'lost' ? '[ของหาย]' : '[พบของ]';
                            $owner_hint = ((int) $rep['reporter_id']) === $user_id ? ' (คุณเป็นผู้แจ้ง)' : '';
                        ?>
                        <option value="<?= (int) $rep['report_id'] ?>" <?= $is_current ? 'selected' : '' ?>>
                            <?= e($type_text . ' ' . $rep['title'] . ' - สถานที่: ' . $rep['location'] . $owner_hint) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['report_id'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['report_id']) ?></p>
                <?php endif; ?>
            </div>

            <!-- ข้อมูลสรุปของสิ่งของที่เลือก -->
            <?php if ($selected_report !== null): ?>
                <?php
                    $is_lost = $selected_report['report_type'] === 'lost';
                    $cat_name = $selected_report['category_name'];
                    $th_category = $category_translations[$cat_name] ?? $cat_name;
                    $is_own_report = ((int) $selected_report['reporter_id']) === $user_id;
                ?>
                <div class="rounded-xl border border-gray-200/80 bg-gray-50/50 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium <?= $is_lost ? 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10' : 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10' ?>">
                                <?= $is_lost ? 'ของหาย' : 'พบสิ่งของ' ?>
                            </span>
                            <span class="text-xs text-gray-500"><?= e($th_category) ?></span>
                        </div>
                        <?php if ($is_own_report): ?>
                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-600/10">
                                คุณเป็นผู้แจ้งรายการนี้ (โหมดทดสอบ)
                            </span>
                        <?php endif; ?>
                    </div>
                    <h2 class="mt-2 text-sm font-semibold text-gray-900"><?= e($selected_report['title']) ?></h2>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-gray-600">
                        <div><span class="font-medium text-gray-700">สถานที่:</span> <?= e($selected_report['location']) ?></div>
                        <div><span class="font-medium text-gray-700">วันเวลา:</span> <?= e($selected_report['event_datetime']) ?></div>
                        <div><span class="font-medium text-gray-700">ผู้แจ้งรายงาน:</span> <?= e($selected_report['reporter_name']) ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ประเภทคำขอ -->
            <div>
                <label class="block text-xs font-medium text-gray-700">ประเภทคำขอ</label>
                <div class="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50">
                        <input type="radio" name="claim_type" value="owner" <?= $values['claim_type'] === 'owner' ? 'checked' : '' ?> class="mt-0.5 text-gray-900 focus:ring-gray-900">
                        <div>
                            <div class="text-xs font-medium text-gray-900">ฉันเป็นเจ้าของ</div>
                            <div class="text-[11px] text-gray-500">ยืนยันความเป็นเจ้าของของชิ้นนี้</div>
                        </div>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50">
                        <input type="radio" name="claim_type" value="finder" <?= $values['claim_type'] === 'finder' ? 'checked' : '' ?> class="mt-0.5 text-gray-900 focus:ring-gray-900">
                        <div>
                            <div class="text-xs font-medium text-gray-900">ฉันเป็นผู้พบ</div>
                            <div class="text-[11px] text-gray-500">พบสิ่งของที่ตรงกับรายการนี้</div>
                        </div>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 p-3 transition hover:bg-gray-50">
                        <input type="radio" name="claim_type" value="info" <?= $values['claim_type'] === 'info' ? 'checked' : '' ?> class="mt-0.5 text-gray-900 focus:ring-gray-900">
                        <div>
                            <div class="text-xs font-medium text-gray-900">แจ้งเบาะแส</div>
                            <div class="text-[11px] text-gray-500">มีข้อมูลเพิ่มเติมที่เป็นประโยชน์</div>
                        </div>
                    </label>
                </div>
                <?php if (!empty($errors['claim_type'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['claim_type']) ?></p>
                <?php endif; ?>
            </div>

            <!-- รายละเอียดหรือหลักฐาน -->
            <div>
                <label for="message" class="block text-xs font-medium text-gray-700">
                    รายละเอียดและหลักฐานยืนยัน <span class="text-rose-500">*</span>
                </label>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    ระบุลักษณะจำเพาะ เช่น รอยตำหนิ, สีของเคส, รายการของที่อยู่ข้างใน, รหัสหน้าจอ หรือวันเวลาสถานที่ที่ทำหาย เพื่อให้ผู้แจ้งสามารถตรวจสอบได้อย่างมั่นใจ
                </p>
                <textarea
                    id="message"
                    name="message"
                    rows="5"
                    required
                    class="mt-2 block w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-xs text-gray-900 placeholder-gray-400 focus:border-gray-900 focus:outline-none"
                    placeholder="อธิบายรายละเอียดเพื่อยืนยันความเป็นเจ้าของ เช่น 'กระเป๋าสตางค์ใบนี้ข้างในมีบัตรประชาชนชื่อ... มีพวงกุญแจห้อยสีน้ำตาล'"><?= e($values['message']) ?></textarea>
                <?php if (!empty($errors['message'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['message']) ?></p>
                <?php endif; ?>
            </div>

            <!-- แนบรูปภาพหลักฐาน (Proof of Ownership Upload) -->
            <div>
                <label for="proof_image" class="block text-xs font-medium text-gray-700">
                    แนบรูปภาพหลักฐานยืนยันความเป็นเจ้าของ (ถ้ามี)
                </label>
                <p class="mt-0.5 text-[11px] text-gray-500">
                    เช่น ภาพถ่ายสิ่งของที่เคยถ่ายไว้, ใบเสร็จ, ซองบรรจุภัณฑ์, หรือบัตรประจำตัว (รองรับ JPG, PNG, WEBP สูงสุด 3 MB - ตรวจสอบได้เฉพาะผู้แจ้งและเจ้าหน้าที่)
                </p>
                <div class="mt-2 flex items-center gap-3">
                    <input
                        type="file"
                        id="proof_image"
                        name="proof_image"
                        accept="image/jpeg,image/png,image/webp"
                        class="block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200 cursor-pointer"
                    >
                </div>
                <?php if (!empty($errors['proof_image'])): ?>
                    <p class="mt-1.5 text-xs text-rose-600"><?= e($errors['proof_image']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100">
                <a href="/claims/index.php" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition">
                    ยกเลิก
                </a>
                <button type="submit" class="rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white hover:bg-gray-800 transition">
                    ส่งคำขอรับคืน
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php
render_footer();
