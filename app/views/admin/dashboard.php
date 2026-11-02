<?php
/** @var array<string, mixed> $admin */
/** @var array<string, mixed> $dashboard */

$is_admin = ($admin['role'] ?? '') === 'admin';

if ($is_admin) {
    $cards = [
        ['label' => 'ผู้ใช้ที่ลงทะเบียน', 'value' => (int) ($dashboard['counts']['users'] ?? 0), 'link' => '/admin/users.php'],
        ['label' => 'หมวดหมู่ที่เปิดใช้งาน', 'value' => (int) ($dashboard['counts']['categories'] ?? 0), 'link' => '/admin/categories.php'],
        ['label' => 'รายงานที่กำลังค้นหา', 'value' => (int) ($dashboard['counts']['open_reports'] ?? 0), 'link' => '/admin/reports.php?status=open'],
        ['label' => 'คำขอรับคืนที่รอดำเนินการ', 'value' => (int) ($dashboard['counts']['pending_claims'] ?? 0), 'link' => '/admin/claims.php?status=pending'],
    ];
} else {
    // Staff operational cards (focused on items & claims inventory)
    $cards = [
        ['label' => 'รายงานที่กำลังค้นหา', 'value' => (int) ($dashboard['counts']['open_reports'] ?? 0), 'link' => '/admin/reports.php?status=open'],
        ['label' => 'คำขอรับคืนที่รอดำเนินการ', 'value' => (int) ($dashboard['counts']['pending_claims'] ?? 0), 'link' => '/admin/claims.php?status=pending'],
        ['label' => 'รายงานสิ่งของทั้งหมด', 'value' => (int) ($dashboard['counts']['total_reports'] ?? 0), 'link' => '/admin/reports.php'],
        ['label' => 'คำขอรับคืนทั้งหมด', 'value' => (int) ($dashboard['counts']['total_claims'] ?? 0), 'link' => '/admin/claims.php'],
    ];
}

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<div class="space-y-6">
    <?php require APP_PATH . '/views/admin/components/nav.php'; ?>

    <?php if ($flash_message !== null): ?>
        <div class="rounded-xl border <?= $flash_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?> p-4 text-xs font-medium">
            <?= e($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($cards as $card): ?>
            <?php require APP_PATH . '/views/admin/components/stat_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <!-- Recent Reports & Claims -->
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <?php require APP_PATH . '/views/admin/components/recent_reports.php'; ?>
        <?php require APP_PATH . '/views/admin/components/recent_claims.php'; ?>
    </div>

    <?php if ($is_admin): ?>
        <!-- Recent Security Events (Admin Only) -->
        <?php require APP_PATH . '/views/admin/components/recent_logs.php'; ?>
    <?php endif; ?>
</div>
