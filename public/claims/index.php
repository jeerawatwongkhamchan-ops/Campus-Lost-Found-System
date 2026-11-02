<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

require_auth();

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$user_id = (int) $_SESSION['user_id'];
$user_role = (string) ($_SESSION['role'] ?? 'guest');
$is_admin_or_staff = in_array($user_role, ['admin', 'staff'], true);

$tab = trim((string) ($_GET['tab'] ?? 'all'));
if (!in_array($tab, ['all', 'sent', 'received'], true)) {
    $tab = 'all';
}

$items_per_page = 15;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_items = 0;

$count_sql = 'SELECT COUNT(*) AS total FROM claims AS cl JOIN item_reports AS r ON r.report_id = cl.report_id';
if ($is_admin_or_staff) {
    if ($tab === 'sent') {
        $count_sql .= ' WHERE cl.claimant_id = ?';
        $c_stmt = $connection->prepare($count_sql);
        $c_stmt->bind_param('i', $user_id);
    } elseif ($tab === 'received') {
        $count_sql .= ' WHERE r.reporter_id = ?';
        $c_stmt = $connection->prepare($count_sql);
        $c_stmt->bind_param('i', $user_id);
    } else {
        $c_stmt = $connection->prepare($count_sql);
    }
} else {
    if ($tab === 'sent') {
        $count_sql .= ' WHERE cl.claimant_id = ?';
        $c_stmt = $connection->prepare($count_sql);
        $c_stmt->bind_param('i', $user_id);
    } elseif ($tab === 'received') {
        $count_sql .= ' WHERE r.reporter_id = ?';
        $c_stmt = $connection->prepare($count_sql);
        $c_stmt->bind_param('i', $user_id);
    } else {
        $count_sql .= ' WHERE cl.claimant_id = ? OR r.reporter_id = ?';
        $c_stmt = $connection->prepare($count_sql);
        $c_stmt->bind_param('ii', $user_id, $user_id);
    }
}

if ($c_stmt) {
    $c_stmt->execute();
    $total_items = (int) ($c_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $c_stmt->close();
}

$total_pages = max(1, (int) ceil($total_items / $items_per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $items_per_page;

$claims = [];

if ($is_admin_or_staff) {
    $sql = 'SELECT cl.claim_id, cl.report_id, cl.claimant_id, cl.claim_type, cl.message, cl.proof_image, cl.status, cl.created_at, cl.reviewed_at,
                   r.title AS report_title, r.report_type, r.reporter_id, r.status AS report_status,
                   u.full_name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone, u.student_id AS claimant_student_id,
                   rep.full_name AS reporter_name, rep.email AS reporter_email, rep.phone AS reporter_phone, rep.student_id AS reporter_student_id,
                   rev.full_name AS reviewer_name
            FROM claims AS cl
            JOIN item_reports AS r ON r.report_id = cl.report_id
            JOIN users AS u ON u.user_id = cl.claimant_id
            LEFT JOIN users AS rep ON rep.user_id = r.reporter_id
            LEFT JOIN users AS rev ON rev.user_id = cl.reviewed_by';

    if ($tab === 'sent') {
        $sql .= ' WHERE cl.claimant_id = ? ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iii', $user_id, $items_per_page, $offset);
    } elseif ($tab === 'received') {
        $sql .= ' WHERE r.reporter_id = ? ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iii', $user_id, $items_per_page, $offset);
    } else {
        $sql .= ' ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('ii', $items_per_page, $offset);
    }
} else {
    $sql = 'SELECT cl.claim_id, cl.report_id, cl.claimant_id, cl.claim_type, cl.message, cl.proof_image, cl.status, cl.created_at, cl.reviewed_at,
                   r.title AS report_title, r.report_type, r.reporter_id, r.status AS report_status,
                   u.full_name AS claimant_name, u.email AS claimant_email, u.phone AS claimant_phone, u.student_id AS claimant_student_id,
                   rep.full_name AS reporter_name, rep.email AS reporter_email, rep.phone AS reporter_phone, rep.student_id AS reporter_student_id,
                   rev.full_name AS reviewer_name
            FROM claims AS cl
            JOIN item_reports AS r ON r.report_id = cl.report_id
            JOIN users AS u ON u.user_id = cl.claimant_id
            LEFT JOIN users AS rep ON rep.user_id = r.reporter_id
            LEFT JOIN users AS rev ON rev.user_id = cl.reviewed_by';

    if ($tab === 'sent') {
        $sql .= ' WHERE cl.claimant_id = ? ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iii', $user_id, $items_per_page, $offset);
    } elseif ($tab === 'received') {
        $sql .= ' WHERE r.reporter_id = ? ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iii', $user_id, $items_per_page, $offset);
    } else {
        $sql .= ' WHERE cl.claimant_id = ? OR r.reporter_id = ? ORDER BY cl.created_at DESC LIMIT ? OFFSET ?';
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('iiii', $user_id, $user_id, $items_per_page, $offset);
    }
}

if ($stmt) {
    $stmt->execute();
    $claims = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$status_info = [
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

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

render_header('การขอรับคืนสิ่งของ (Claims) - Campus Lost & Found System');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">คำขอรับคืนสิ่งของ</h1>
            <p class="mt-1 text-sm text-gray-500">
                ระบบนัดรับสิ่งของแบบ Peer-to-Peer: ติดต่อเพื่อนในมอเพื่อนัดตรวจหลักฐาน และยืนยันการส่งมอบคืนได้โดยตรง
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="/reports/index.php" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3.5 py-2 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                &larr; รายการสิ่งของทั้งหมด
            </a>
            <a href="/claims/create.php" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-2 text-xs font-medium text-white shadow-xs transition hover:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                ยื่นคำขอรับคืนสิ่งของ
            </a>
        </div>
    </div>

    <!-- กล่องแนะนำ Community Lost & Found -->
    <div class="rounded-xl bg-emerald-50/60 p-4 border border-emerald-100 flex items-start gap-3">
        <svg class="h-5 w-5 shrink-0 text-emerald-700 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <div class="text-xs text-emerald-950 leading-relaxed">
            <strong class="font-semibold text-emerald-900">ระบบ Community Handover:</strong>
            ผู้พบสิ่งของและผู้ขอรับคืนสามารถติดต่อเพื่อนัดเจอกันตามพื้นที่ส่วนกลางในมหาวิทยาลัย (เช่น โรงอาหาร ใต้ตึกคณะ หรือจุดบริการนักศึกษา) ได้โดยตรง เมื่อตรวจสอบหลักฐานและส่งมอบของถึงมือแล้ว ผู้พบสามารถกดปุ่ม <strong>"ยืนยันส่งมอบของคืนแล้ว"</strong> ในระบบได้ทันทีโดยไม่ต้องรอเจ้าหน้าที่
        </div>
    </div>

    <?php if ($flash_message !== null): ?>
        <div class="rounded-xl border p-4 text-xs font-medium <?= $flash_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : ($flash_type === 'error' ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-gray-200 bg-gray-50 text-gray-800') ?>">
            <?= e($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Segmented Tabs -->
    <div class="flex items-center justify-between">
        <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium text-gray-600">
            <a href="/claims/index.php?tab=all" class="rounded-md px-3 py-1.5 transition <?= $tab === 'all' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>">
                ทั้งหมด
            </a>
            <a href="/claims/index.php?tab=sent" class="rounded-md px-3 py-1.5 transition <?= $tab === 'sent' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>">
                คำขอที่ฉันส่ง
            </a>
            <a href="/claims/index.php?tab=received" class="rounded-md px-3 py-1.5 transition <?= $tab === 'received' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>">
                คำขอที่ส่งถึงรายงานของฉัน
            </a>
        </div>
        <div class="text-xs text-gray-500">
            พบ <?= number_format($total_items) ?> รายการ
        </div>
    </div>

    <!-- รายการ Cards / Table -->
    <?php if ($claims === []): ?>
        <div class="rounded-xl border border-gray-200/80 bg-white px-6 py-12 text-center shadow-xs">
            <div class="mx-auto max-w-sm">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-semibold text-gray-900">ยังไม่มีรายการคำขอรับคืน</h3>
                <p class="mt-1 text-xs text-gray-500">
                    เมื่อคุณยื่นคำขอ หรือมีคนส่งคำขอยืนยันความเป็นเจ้าของสิ่งของที่คุณแจ้ง ข้อมูลจะแสดงที่นี่
                </p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                    <a href="/claims/create.php" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-1.5 text-xs font-medium text-white shadow-xs transition hover:bg-gray-800">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        ยื่นคำขอรับคืนสิ่งของ
                    </a>
                    <a href="/reports/index.php" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                        ค้นหารายการสิ่งของ
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($claims as $claim): ?>
                <?php
                    $is_mine = ((int) $claim['claimant_id']) === $user_id;
                    $is_reporter = ((int) $claim['reporter_id']) === $user_id;
                    $can_review = ($is_reporter || $is_admin_or_staff) && $claim['status'] === 'pending';
                    $can_cancel = $is_mine && $claim['status'] === 'pending';
                    $st = $status_info[$claim['status']] ?? [
                        'label' => ucfirst($claim['status']),
                        'class' => 'bg-gray-100 text-gray-700',
                    ];
                ?>
                <div class="rounded-xl border border-gray-200/80 bg-white p-5 shadow-xs transition hover:border-gray-300">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 pb-3 border-b border-gray-100">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs text-gray-400">#<?= e((string) $claim['claim_id']) ?></span>
                                <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold <?= $st['class'] ?>">
                                    <?= e($st['label']) ?>
                                </span>
                                <span class="text-xs text-gray-500 font-medium">ประเภท: <?= e($claim['claim_type']) ?></span>
                            </div>
                            <h2 class="mt-1.5 text-base font-semibold text-gray-900">
                                <a href="/reports/show.php?id=<?= (int) $claim['report_id'] ?>" class="hover:text-gray-600 transition">
                                    <?= e($claim['report_title']) ?>
                                </a>
                            </h2>
                        </div>
                        <div class="text-xs text-gray-400 sm:text-right">
                            ยื่นเรื่องเมื่อ: <?= e($claim['created_at']) ?>
                        </div>
                    </div>

                    <!-- ข้อมูลการติดต่อกันระหว่างผู้พบและผู้ขอเคลม (Peer-to-Peer Contact Cards) -->
                    <div class="mt-3.5 space-y-3">
                        <?php if ($is_mine): ?>
                            <!-- ฝั่งผู้ยื่นเคลม: แสดงข้อมูลติดต่อผู้พบสิ่งของ -->
                            <div class="rounded-lg bg-blue-50/60 p-3 text-xs border border-blue-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-blue-950">ผู้พบสิ่งของ:</span>
                                    <span class="text-blue-900 font-medium"><?= e($claim['reporter_name'] ?? 'ผู้แจ้ง') ?></span>
                                    <?php if (!empty($claim['reporter_student_id'])): ?>
                                        <span class="rounded bg-blue-100 px-1.5 py-0.5 font-mono text-[11px] text-blue-800"><?= e($claim['reporter_student_id']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if (!empty($claim['reporter_phone'])): ?>
                                        <a href="tel:<?= e($claim['reporter_phone']) ?>" class="inline-flex items-center gap-1 rounded-md bg-white border border-emerald-300 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition shadow-2xs">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span>โทรหาผู้พบ (<?= e($claim['reporter_phone']) ?>)</span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($claim['reporter_email'])): ?>
                                        <a href="mailto:<?= e($claim['reporter_email']) ?>" class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span>ส่งอีเมล</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- ฝั่งผู้พบสิ่งของ/เจ้าหน้าที่: แสดงข้อมูลติดต่อผู้ขอเคลม -->
                            <div class="rounded-lg bg-gray-50/80 p-3 text-xs border border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-gray-800">ผู้ขอรับคืน:</span>
                                    <span class="text-gray-900 font-medium"><?= e($claim['claimant_name']) ?></span>
                                    <?php if (!empty($claim['claimant_student_id'])): ?>
                                        <span class="rounded bg-gray-200/60 px-1.5 py-0.5 font-mono text-[11px] text-gray-600"><?= e($claim['claimant_student_id']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <?php if (!empty($claim['claimant_phone'])): ?>
                                        <a href="tel:<?= e($claim['claimant_phone']) ?>" class="inline-flex items-center gap-1 rounded-md bg-white border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 transition shadow-2xs">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span>โทร <?= e($claim['claimant_phone']) ?></span>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($claim['claimant_email'])): ?>
                                        <a href="mailto:<?= e($claim['claimant_email']) ?>" class="inline-flex items-center gap-1 rounded-md bg-white border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-2xs">
                                            <svg class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span>ส่งอีเมล</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- ข้อความหลักฐาน -->
                        <div class="rounded-lg bg-gray-50/80 p-3 text-xs text-gray-700 border border-gray-100">
                            <span class="font-medium text-gray-900">รายละเอียด / หลักฐานยืนยัน:</span>
                            <p class="mt-1 whitespace-pre-wrap text-gray-600 leading-relaxed"><?= e($claim['message']) ?></p>

                            <?php if (!empty($claim['proof_image'])): ?>
                                <?php $proof_url = get_claim_image_url($claim['proof_image']); ?>
                                <?php if ($proof_url !== null): ?>
                                    <div class="mt-3 flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-2.5">
                                        <img src="<?= e($proof_url) ?>" alt="รูปหลักฐานยืนยัน" class="h-14 w-14 rounded-md object-cover border border-gray-100 shadow-2xs">
                                        <div class="text-xs">
                                            <span class="font-semibold text-gray-900">รูปภาพหลักฐานยืนยันที่แนบมา</span>
                                            <a href="<?= e($proof_url) ?>" target="_blank" class="mt-0.5 block text-gray-900 hover:text-gray-600 underline font-medium">
                                                คลิกเพื่อดูรูปภาพขนาดเต็ม &rarr;
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($claim['reviewed_at'] !== null): ?>
                            <div class="text-[11px] text-gray-400">
                                ดำเนินการตรวจสอบเมื่อ: <?= e($claim['reviewed_at']) ?> <?= !empty($claim['reviewer_name']) ? 'โดย ' . e($claim['reviewer_name']) : '' ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- แถบปุ่มจัดการ -->
                    <div class="mt-4 pt-3 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100">
                        <div>
                            <a href="/reports/show.php?id=<?= (int) $claim['report_id'] ?>" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs inline-flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>ดูหน้ารายละเอียดสิ่งของ</span>
                            </a>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <?php if ($can_cancel): ?>
                                <form method="POST" action="/claims/action.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำขอนี้?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['claim_id'] ?>">
                                    <input type="hidden" name="action" value="cancel">
                                    <button type="submit" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                                        ยกเลิกคำขอของฉัน
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($can_review): ?>
                                <form method="POST" action="/claims/action.php" onsubmit="return confirm('ยืนยันปฏิเสธคำขอนี้ใช่หรือไม่?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['claim_id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100 transition shadow-xs">
                                        ปฏิเสธคำขอ
                                    </button>
                                </form>

                                <form method="POST" action="/claims/action.php" onsubmit="return confirm('ยืนยันว่าได้ส่งมอบสิ่งของนี้คืนให้ <?= e($claim['claimant_name']) ?> เรียบร้อยแล้วใช่หรือไม่? ระบบจะเปลี่ยนสถานะรายการสิ่งของเป็น \'ส่งคืนสำเร็จ\' ทันที');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['claim_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 transition shadow-xs">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span>ยืนยันส่งมอบของคืนแล้ว</span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php
            $base_url = '/claims/index.php';
            $query_params = $_GET;
            require APP_PATH . '/views/components/pagination.php';
        ?>
    <?php endif; ?>
</div>

<?php
render_footer();
