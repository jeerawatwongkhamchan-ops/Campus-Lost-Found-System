<?php

declare(strict_types=1);

/**
 * Admin / Staff Navigation Bar Component
 *
 * @var string $active_admin_tab Current active tab: 'dashboard' | 'reports' | 'claims' | 'categories' | 'users' | 'logs'
 */

$current_tab = $active_admin_tab ?? 'dashboard';
$user_role = (string) ($_SESSION['role'] ?? 'guest');
$is_admin = $user_role === 'admin';

$nav_items = [
    'dashboard' => ['label' => 'ภาพรวมแดชบอร์ด', 'url' => '/admin/index.php'],
    'reports' => ['label' => 'จัดการรายงานสิ่งของ', 'url' => '/admin/reports.php'],
    'claims' => ['label' => 'จัดการคำขอรับคืน', 'url' => '/admin/claims.php'],
];

if ($is_admin) {
    $nav_items['categories'] = ['label' => 'จัดการหมวดหมู่', 'url' => '/admin/categories.php'];
    $nav_items['users'] = ['label' => 'จัดการผู้ใช้งาน', 'url' => '/admin/users.php'];
    $nav_items['logs'] = ['label' => 'บันทึกความปลอดภัย', 'url' => '/admin/logs.php'];
}
?>

<div class="border-b border-gray-200 pb-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <a href="/reports/index.php" class="hover:text-gray-900 transition">&larr; หน้ารายการสิ่งของทั่วไป</a>
                <span class="text-gray-300">/</span>
                <span class="font-medium text-gray-700">
                    <?= $is_admin ? 'ระบบจัดการหลังบ้าน (Admin Panel)' : 'ระบบงานเจ้าหน้าที่ (Staff Operations)' ?>
                </span>
            </div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900">
                <?= e($admin_page_heading ?? ($is_admin ? 'ระบบจัดการหลังบ้าน' : 'ระบบงานเจ้าหน้าที่')) ?>
            </h1>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <?= render_role_badge($is_admin ? 'admin' : 'staff', true, true, 'py-1') ?>
            <span class="font-semibold text-gray-900"><?= e($_SESSION['full_name'] ?? 'ผู้ใช้งาน') ?></span>
        </div>
    </div>

    <!-- แถบเมนูย่อยระบบหลังบ้าน -->
    <div class="mt-4 flex items-center overflow-x-auto rounded-lg bg-gray-100 p-0.5 text-xs font-medium text-gray-600">
        <?php foreach ($nav_items as $key => $item): ?>
            <?php $is_active = $current_tab === $key; ?>
            <a
                href="<?= e($item['url']) ?>"
                class="rounded-md px-3.5 py-1.5 whitespace-nowrap transition <?= $is_active ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>"
            >
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
