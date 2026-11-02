<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';
require_once APP_PATH . '/reports/report_data.php';
require_once APP_PATH . '/services/smart_matching.php';

$reportId = reports_positive_id($_GET['id'] ?? null);
if ($reportId === null) {
    reports_error(400, 'ไม่พบรหัสรายงานที่ระบุ');
}

$user = null;
$user_claim = null;
$smart_matches = [];

try {
    $database = reports_database();
    $report = reports_find($database, $reportId);
    if ($report === null) {
        reports_error(404, 'ไม่พบข้อมูลรายงานสิ่งของนี้');
    }
    $user = reports_current_user($database);
    $smart_matches = find_smart_matches($database, $report, 4);

    if ($user !== null) {
        $stmt = $database->prepare(
            'SELECT claim_id, status, claim_type, created_at FROM claims WHERE report_id = ? AND claimant_id = ? ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->bind_param('ii', $reportId, $user['user_id']);
        $stmt->execute();
        $user_claim = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
} catch (Throwable $error) {
    reports_log_failure($error);
}

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$category_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา / นาฬิกา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];

$status_info = [
    'open' => [
        'label' => 'รอเจ้าของ',
        'class' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10',
    ],
    'claimed' => [
        'label' => 'มีผู้ขอรับคืนแล้ว',
        'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10',
    ],
    'resolved' => [
        'label' => 'ส่งคืนสำเร็จ',
        'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10',
    ],
    'closed' => [
        'label' => 'ปิดเรื่องแล้ว',
        'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200',
    ],
];

$claim_status_info = [
    'pending' => [
        'label' => 'รอนัดรับ & ตรวจสอบ',
        'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10',
    ],
    'approved' => [
        'label' => 'ส่งมอบสำเร็จแล้ว',
        'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10',
    ],
    'rejected' => [
        'label' => 'ไม่ผ่านการตรวจสอบ',
        'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10',
    ],
    'cancelled' => [
        'label' => 'ยกเลิกคำขอแล้ว',
        'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200',
    ],
];

$is_lost = $report['report_type'] === 'lost';
$status = $status_info[$report['status']] ?? [
    'label' => ucfirst($report['status']),
    'class' => 'bg-gray-100 text-gray-700',
];
$cat_name = $report['category_name'];
$th_category = $category_translations[$cat_name] ?? $cat_name;

$canManage = reports_can_manage($report, $user);

$report_claims = [];
if ($canManage && isset($database) && $database instanceof mysqli) {
    $stmt = $database->prepare(
        'SELECT cl.claim_id, cl.claimant_id, cl.claim_type, cl.message, cl.proof_image, cl.status, cl.created_at, cl.reviewed_at,
                u.full_name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone, u.student_id AS claimant_student_id
         FROM claims cl
         JOIN users u ON u.user_id = cl.claimant_id
         WHERE cl.report_id = ?
         ORDER BY (cl.status = "pending") DESC, cl.created_at DESC'
    );
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $report_claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

render_header($report['title'] . ' - Campus Lost & Found System');
?>

<article class="mx-auto max-w-2xl space-y-6">
    <?php if ($flash_message !== null): ?>
        <div class="rounded-xl border p-4 text-xs font-medium <?= $flash_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>">
            <?= e($flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="rounded-xl border border-gray-200/80 bg-white p-6 sm:p-8 shadow-xs space-y-6">
        <div>
            <a class="inline-flex items-center text-xs font-medium text-gray-500 transition hover:text-gray-900" href="/reports/index.php">
                &larr; กลับไปหน้ารายการสิ่งของ
            </a>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <?php if ($is_lost): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        ของหาย
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        พบสิ่งของ
                    </span>
                <?php endif; ?>

                <span class="inline-flex rounded-md px-2.5 py-1 text-xs font-medium <?= $status['class'] ?>">
                    <?= e($status['label']) ?>
                </span>
                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                    <?= e($th_category) ?>
                </span>
            </div>

            <h1 class="mt-3 text-2xl font-bold text-gray-900"><?= e($report['title']) ?></h1>
        </div>

        <?php $img_url = get_report_image_url($report['image_filename'] ?? null); ?>
        <?php if ($img_url !== null): ?>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50/50 flex justify-center p-2">
                <a href="<?= e($img_url) ?>" target="_blank" title="คลิกเพื่อดูรูปภาพขนาดเต็ม" class="block group relative">
                    <img
                        src="<?= e($img_url) ?>"
                        alt="<?= e($report['title']) ?>"
                        class="max-h-96 w-auto rounded-lg object-contain transition group-hover:opacity-95"
                    >
                    <span class="absolute bottom-2 right-2 rounded-md bg-gray-900/75 px-2 py-1 text-[10px] font-medium text-white backdrop-blur-xs opacity-0 transition group-hover:opacity-100">
                        คลิกเพื่อดูขนาดเต็ม
                    </span>
                </a>
            </div>
        <?php endif; ?>

        <!-- ข้อมูลสรุป -->
        <dl class="grid gap-4 border-y border-gray-100 py-5 text-xs sm:grid-cols-2 bg-gray-50/50 p-4 rounded-xl">
            <div>
                <dt class="font-medium text-gray-500">สถานที่พบ / ทำหาย</dt>
                <dd class="mt-1 font-semibold text-gray-900"><?= e($report['location']) ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">วันและเวลาที่เกิดเหตุ</dt>
                <dd class="mt-1 font-semibold text-gray-900"><?= e($report['event_datetime']) ?></dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">ผู้แจ้งรายงาน</dt>
                <dd class="mt-1 font-semibold text-gray-900">
                    <?= e($report['reporter_name']) ?>
                    <?php if (!empty($report['reporter_student_id'])): ?>
                        <span class="ml-1 text-[11px] font-normal text-gray-500">(<?= e($report['reporter_student_id']) ?>)</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">ลงบันทึกในระบบเมื่อ</dt>
                <dd class="mt-1 font-semibold text-gray-900"><?= e($report['created_at']) ?></dd>
            </div>
            <?php if ($user !== null && !empty($report['reporter_phone'])): ?>
            <div>
                <dt class="font-medium text-gray-500">เบอร์โทรติดต่อผู้แจ้ง</dt>
                <dd class="mt-1">
                    <a href="tel:<?= e($report['reporter_phone']) ?>" class="font-semibold text-emerald-700 hover:underline inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span><?= e($report['reporter_phone']) ?></span>
                    </a>
                </dd>
            </div>
            <?php endif; ?>
            <?php if ($user !== null && !empty($report['reporter_email'])): ?>
            <div>
                <dt class="font-medium text-gray-500">อีเมลติดต่อผู้แจ้ง</dt>
                <dd class="mt-1">
                    <a href="mailto:<?= e($report['reporter_email']) ?>" class="font-semibold text-gray-800 hover:underline inline-flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span><?= e($report['reporter_email']) ?></span>
                    </a>
                </dd>
            </div>
            <?php endif; ?>
        </dl>

        <!-- รายละเอียด -->
        <div>
            <h2 class="text-sm font-semibold text-gray-900">รายละเอียดและจุดสังเกต</h2>
            <div class="mt-2.5 rounded-xl border border-gray-100 bg-gray-50/70 p-4">
                <p class="whitespace-pre-wrap text-xs text-gray-700 leading-relaxed"><?= e($report['description']) ?></p>
            </div>
        </div>

        <?php if ($canManage && !empty($report['private_note'])): ?>
            <!-- ข้อมูลลับเฉพาะ (Private Note - Manager Only) -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-amber-500"></span>
                    <h2 class="text-xs font-semibold text-amber-900">ข้อมูลลับเฉพาะสำหรับยืนยันความเป็นเจ้าของ (Private Note)</h2>
                    <span class="rounded bg-amber-200/60 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">เห็นเฉพาะผู้แจ้งและเจ้าหน้าที่</span>
                </div>
                <p class="mt-2 whitespace-pre-wrap text-xs text-amber-900 leading-relaxed font-mono"><?= e($report['private_note']) ?></p>
            </div>
        <?php endif; ?>

        <!-- แถบดำเนินการ (จัดการ หรือ ติดต่อเคลม) -->
        <?php if ($canManage): ?>
            <div class="border-t border-gray-100 pt-5 space-y-4">
                <div>
                    <h3 class="text-xs font-medium text-gray-700">เปลี่ยนสถานะสิ่งของ</h3>
                    <form method="post" action="/reports/status.php" class="mt-2 flex flex-wrap items-center gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <select name="status" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 focus:border-gray-900 focus:outline-none">
                            <option value="open" <?= $report['status'] === 'open' ? 'selected' : '' ?>>รอเจ้าของ / กำลังค้นหา</option>
                            <option value="resolved" <?= $report['status'] === 'resolved' ? 'selected' : '' ?>>ส่งคืนสำเร็จแล้ว</option>
                            <option value="closed" <?= $report['status'] === 'closed' ? 'selected' : '' ?>>ปิดการค้นหา</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-gray-900 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-gray-800 transition">
                            อัปเดตสถานะ
                        </button>
                    </form>
                </div>

                <div class="pt-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="/reports/edit.php?id=<?= $reportId ?>" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                            แก้ไขข้อมูล
                        </a>
                        <a href="/claims/index.php?tab=received" class="rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                            ดูประวัติคำขอทั้งหมด (<?= count($report_claims) ?>)
                        </a>
                    </div>
                    <form method="post" action="/reports/delete.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรายงานนี้?');">
                        <input type="hidden" name="report_id" value="<?= $reportId ?>">
                        <input type="hidden" name="csrf_token" value="<?= e(reports_csrf_token()) ?>">
                        <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50/50 px-3.5 py-2 text-xs font-medium text-rose-700 hover:bg-rose-100/70 transition">
                            ลบรายงานนี้
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="border-t border-gray-100 pt-5">
                <?php if ($user === null): ?>
                    <div class="rounded-xl bg-gray-50/70 p-4.5 border border-gray-200/80">
                        <h3 class="text-xs font-semibold text-gray-900">เป็นเจ้าของสิ่งของชิ้นนี้ใช่ไหม?</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            เข้าสู่ระบบเพื่อยื่นคำขอรับคืน และนัดรับของกับผู้พบได้โดยตรงในมหาวิทยาลัย
                        </p>
                        <div class="mt-3">
                            <a href="/auth/login.php" class="inline-flex items-center rounded-lg bg-gray-900 px-3.5 py-1.5 text-xs font-medium text-white hover:bg-gray-800 transition">
                                เข้าสู่ระบบเพื่อยื่นคำขอ
                            </a>
                        </div>
                    </div>
                <?php elseif ($user_claim !== null): ?>
                    <div class="rounded-xl border border-blue-200/80 bg-blue-50/50 p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                            <div>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold <?= $claim_status_info[$user_claim['status']]['class'] ?? 'bg-blue-100 text-blue-800' ?>">
                                    <?= e($claim_status_info[$user_claim['status']]['label'] ?? $user_claim['status']) ?>
                                </span>
                                <h3 class="mt-1.5 text-sm font-bold text-gray-900">คุณได้ยื่นคำขอรับคืนสิ่งของชิ้นนี้แล้ว</h3>
                            </div>
                            <a href="/claims/index.php" class="inline-flex items-center text-xs font-medium text-blue-700 hover:text-blue-900 underline">
                                ดูประวัติคำขอของฉัน &rarr;
                            </a>
                        </div>

                        <?php if ($user_claim['status'] === 'pending'): ?>
                            <div class="rounded-lg bg-white p-4 border border-blue-100 shadow-2xs space-y-3">
                                <div class="text-xs text-gray-700">
                                    <span class="font-semibold text-gray-900">ช่องทางติดต่อผู้พบสิ่งของเพื่อนัดรับ:</span>
                                    กรุณาติดต่อผู้พบสิ่งของเพื่อนัดวันเวลาและสถานที่ในการตรวจสอบหลักฐาน
                                </div>
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <span class="text-xs text-gray-600 font-medium">ผู้พบ: <strong class="text-gray-900"><?= e($report['reporter_name']) ?></strong></span>
                                    <?php if (!empty($report['reporter_student_id'])): ?>
                                        <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] text-gray-600"><?= e($report['reporter_student_id']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 pt-1">
                                    <?php if (!empty($report['reporter_phone'])): ?>
                                        <a href="tel:<?= e($report['reporter_phone']) ?>" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs transition hover:bg-emerald-700">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span>โทรหาผู้พบ (<?= e($report['reporter_phone']) ?>)</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($report['reporter_email'])): ?>
                                        <a href="mailto:<?= e($report['reporter_email']) ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-medium text-gray-700 shadow-2xs transition hover:bg-gray-50">
                                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span>ส่งอีเมล (<?= e($report['reporter_email']) ?>)</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="text-[11px] text-gray-500 pt-1 border-t border-gray-100 flex items-start gap-1.5">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-gray-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span><strong>ข้อแนะนำ:</strong> นัดพบในพื้นที่ส่วนกลาง เช่น โรงอาหาร หรือใต้ตึกคณะ เพื่อความปลอดภัย และเตรียมหลักฐาน (เช่น ปลดล็อคหน้าจอ) เมื่อได้รับของแล้ว ผู้พบจะกดยืนยันส่งมอบในระบบ</span>
                                </div>
                            </div>
                        <?php elseif ($user_claim['status'] === 'approved'): ?>
                            <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-xs text-emerald-800 flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>ผู้พบสิ่งของได้ยืนยันการส่งมอบสิ่งของคืนให้คุณเรียบร้อยแล้ว</span>
                            </div>
                        <?php elseif ($user_claim['status'] === 'rejected'): ?>
                            <div class="rounded-lg bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800">
                                คำขอของคุณไม่ผ่านการตรวจสอบจากผู้พบสิ่งของหรือเจ้าหน้าที่
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!in_array($report['status'], ['resolved', 'closed'], true)): ?>
                    <div class="rounded-xl bg-gray-50/70 p-4.5 border border-gray-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="text-xs font-semibold text-gray-900">เป็นเจ้าของสิ่งของชิ้นนี้ใช่ไหม?</h3>
                            <p class="mt-0.5 text-xs text-gray-500">
                                ยื่นคำขอยืนยันเพื่อรับช่องทางติดต่อผู้พบ และนัดรับของคืนได้โดยตรงในมหาวิทยาลัย
                            </p>
                        </div>
                        <a href="/claims/create.php?report_id=<?= $reportId ?>" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white hover:bg-gray-800 transition shadow-xs whitespace-nowrap">
                            ยื่นคำขอรับคืนสิ่งของ
                        </a>
                    </div>
                <?php else: ?>
                    <div class="rounded-xl bg-gray-50 p-4 text-xs text-gray-500 text-center">
                        รายการนี้ถูกปิดหรือส่งคืนเจ้าของเรียบร้อยแล้ว ไม่สามารถส่งคำขอรับคืนได้
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <!-- กล่องจัดการคำขอรับคืน & ส่งมอบสิ่งของ (Peer-to-Peer Ecosystem) -->
        <div class="rounded-xl border border-gray-200/80 bg-white p-6 sm:p-8 shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-gray-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </span>
                        <h2 class="text-base font-bold text-gray-900">คำขอรับคืน & ส่งมอบสิ่งของ (Peer-to-Peer)</h2>
                        <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                            <?= count($report_claims) ?> รายการ
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        ติดต่อเพื่อนที่ยื่นขอเพื่อนัดตรวจหลักฐาน และกดปุ่ม <span class="font-medium text-emerald-700">"ยืนยันส่งมอบของให้คนนี้แล้ว"</span> เมื่อส่งมอบของถึงมือเรียบร้อย
                    </p>
                </div>
                <a href="/claims/index.php?tab=received" class="inline-flex items-center text-xs font-medium text-gray-600 hover:text-gray-900 transition">
                    ไปที่หน้าประวัติคำขอ &rarr;
                </a>
            </div>

            <!-- กล่องคำแนะนำการนัดรับในมหาวิทยาลัย -->
            <div class="rounded-lg bg-emerald-50/60 p-3.5 border border-emerald-100 flex items-start gap-2.5">
                <svg class="h-4 w-4 shrink-0 text-emerald-700 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-xs text-emerald-950 leading-relaxed">
                    <strong class="font-semibold text-emerald-900">คำแนะนำการนัดหมาย:</strong>
                    โทรหรืออีเมลนัดพบกันในพื้นที่ส่วนกลางของมหาวิทยาลัย เช่น โรงอาหาร ใต้ตึกคณะ หรือจุดบริการนักศึกษา ตรวจสอบหลักฐานหรือตำหนิสิ่งของต่อหน้า เมื่อส่งมอบสำเร็จสามารถกดยืนยันด้านล่างได้ทันทีโดยไม่ต้องรอเจ้าหน้าที่
                </div>
            </div>

            <?php if ($report_claims === []): ?>
                <div class="py-6 text-center text-xs text-gray-400">
                    ยังไม่มีเพื่อนในระบบยื่นคำขอรับคืนสิ่งของชิ้นนี้ หากมีผู้ยื่นคำขอ รายชื่อและช่องทางติดต่อจะแสดงที่นี่ทันที
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($report_claims as $cl): ?>
                        <?php
                            $c_status = $claim_status_info[$cl['status']] ?? [
                                'label' => ucfirst($cl['status']),
                                'class' => 'bg-gray-100 text-gray-700',
                            ];
                        ?>
                        <div class="rounded-xl border <?= $cl['status'] === 'pending' ? 'border-amber-200/90 bg-amber-50/20' : 'border-gray-200/80 bg-gray-50/30' ?> p-4.5 space-y-3 transition">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 border-b border-gray-100 pb-2.5">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-xs text-gray-400">#<?= e((string) $cl['claim_id']) ?></span>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold <?= $c_status['class'] ?>">
                                            <?= e($c_status['label']) ?>
                                        </span>
                                        <span class="text-xs text-gray-500 font-medium">ประเภท: <?= e($cl['claim_type']) ?></span>
                                    </div>
                                    <div class="mt-1 text-sm font-bold text-gray-900 flex flex-wrap items-center gap-2">
                                        <?= e($cl['claimant_name']) ?>
                                        <?php if (!empty($cl['claimant_student_id'])): ?>
                                            <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-[11px] font-normal text-gray-600">
                                                <?= e($cl['claimant_student_id']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-400 sm:text-right">
                                    ยื่นเรื่องเมื่อ: <?= e($cl['created_at']) ?>
                                </div>
                            </div>

                            <!-- ช่องทางติดต่อผู้ขอเคลม -->
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs text-gray-500 font-medium">ติดต่อผู้ขอเคลม:</span>
                                <?php if (!empty($cl['claimant_phone'])): ?>
                                    <a href="tel:<?= e($cl['claimant_phone']) ?>" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-white px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition shadow-2xs">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span>โทร <?= e($cl['claimant_phone']) ?></span>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($cl['claimant_email'])): ?>
                                    <a href="mailto:<?= e($cl['claimant_email']) ?>" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs">
                                        <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span>ส่งอีเมล (<?= e($cl['claimant_email']) ?>)</span>
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- ข้อความหลักฐาน -->
                            <div class="rounded-lg bg-white p-3 text-xs border border-gray-200/80 shadow-2xs space-y-2">
                                <div>
                                    <span class="font-semibold text-gray-800">รายละเอียด / หลักฐานยืนยัน:</span>
                                    <p class="mt-1 whitespace-pre-wrap text-gray-600 leading-relaxed"><?= e($cl['message']) ?></p>
                                </div>

                                <?php if (!empty($cl['proof_image'])): ?>
                                    <?php $proof_url = get_claim_image_url($cl['proof_image']); ?>
                                    <?php if ($proof_url !== null): ?>
                                        <div class="mt-2 flex items-center gap-3 pt-2 border-t border-gray-100">
                                            <img src="<?= e($proof_url) ?>" alt="รูปหลักฐานยืนยัน" class="h-12 w-12 rounded object-cover border border-gray-200">
                                            <div class="text-xs">
                                                <span class="font-semibold text-gray-800">มีรูปภาพหลักฐานแนบมา</span>
                                                <a href="<?= e($proof_url) ?>" target="_blank" class="block text-blue-600 hover:underline text-[11px] font-medium">
                                                    คลิกเพื่อดูรูปขนาดเต็ม &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- ปุ่ม Action ส่งมอบ / ปฏิเสธ -->
                            <?php if ($cl['status'] === 'pending'): ?>
                                <div class="mt-3 pt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200/60">
                                    <div class="text-[11px] text-gray-500">
                                        เมื่อตรวจหลักฐานและส่งมอบสิ่งของถึงมือแล้ว กดยืนยัน:
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="/claims/action.php" onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้ใช่หรือไม่?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="claim_id" value="<?= (int) $cl['claim_id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="redirect_to" value="/reports/show.php?id=<?= $reportId ?>">
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 transition shadow-2xs">
                                                ปฏิเสธคำขอ
                                            </button>
                                        </form>

                                        <form method="POST" action="/claims/action.php" onsubmit="return confirm('ยืนยันว่าคุณได้ส่งมอบสิ่งของนี้ให้ <?= e($cl['claimant_name']) ?> เรียบร้อยแล้วใช่หรือไม่? ระบบจะเปลี่ยนสถานะรายการนี้เป็น \'ส่งคืนสำเร็จ\' ทันที');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="claim_id" value="<?= (int) $cl['claim_id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="redirect_to" value="/reports/show.php?id=<?= $reportId ?>">
                                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition shadow-xs">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span>ยืนยันส่งมอบของให้คนนี้แล้ว</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php elseif ($cl['status'] === 'approved'): ?>
                                <div class="rounded-lg bg-emerald-50 p-2.5 text-xs text-emerald-800 border border-emerald-200 flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>ส่งมอบสิ่งของให้ <strong><?= e($cl['claimant_name']) ?></strong> เรียบร้อยแล้ว (ปิดเคสสำเร็จ)</span>
                                </div>
                            <?php elseif ($cl['status'] === 'rejected'): ?>
                                <div class="text-[11px] text-rose-600">
                                    คำขอนี้ได้รับการปฏิเสธแล้ว
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($smart_matches)): ?>
        <!-- รายการสิ่งของที่อาจตรงกัน (Smart Match Recommendations) -->
        <div class="rounded-xl border border-gray-200/80 bg-white p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </span>
                    <h2 class="text-sm font-semibold text-gray-900">
                        สิ่งของที่อาจตรงกัน (Smart Match Recommendations)
                    </h2>
                </div>
                <span class="text-[11px] text-gray-400 hidden sm:inline">วิเคราะห์จากหมวดหมู่ สถานที่ และช่วงเวลา</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ($smart_matches as $match): ?>
                    <a href="/reports/show.php?id=<?= $match['report_id'] ?>" class="group block rounded-lg border border-gray-200/80 bg-gray-50/50 p-3.5 transition hover:border-gray-300 hover:bg-white hover:shadow-xs">
                        <div class="flex items-start justify-between gap-2">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-medium <?= $match['report_type'] === 'lost' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' ?>">
                                <?= $match['report_type'] === 'lost' ? 'ของหาย' : 'พบของ' ?>
                            </span>
                            <span class="rounded bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">
                                ความเป็นไปได้ <?= $match['match_score'] ?>%
                            </span>
                        </div>
                        <h3 class="mt-2 text-xs font-semibold text-gray-900 group-hover:text-gray-900 group-hover:underline transition truncate">
                            <?= e($match['title']) ?>
                        </h3>
                        <p class="mt-0.5 text-[11px] text-gray-500 truncate flex items-center gap-1">
                            <svg class="h-3 w-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span><?= e($match['location']) ?></span>
                        </p>
                        <div class="mt-2 pt-2 border-t border-gray-200/60 flex flex-wrap gap-1">
                            <?php foreach ($match['match_reasons'] as $reason): ?>
                                <span class="rounded bg-white px-1.5 py-0.5 text-[10px] text-gray-500 border border-gray-200">
                                    <?= e($reason) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</article>

<?php render_footer(); ?>
