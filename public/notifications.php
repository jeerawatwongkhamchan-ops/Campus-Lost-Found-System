<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

require_auth();

$user_id = (int) $_SESSION['user_id'];
$db = get_db_connection();

// Handle notification actions (mark all read, mark single read, delete, clear read)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $action = (string) ($_POST['action'] ?? '');
        $redirect_tab = trim((string) ($_POST['tab'] ?? 'all'));
        $redirect_page = max(1, (int) ($_POST['page'] ?? 1));

        if ($action === 'mark_all_read') {
            mark_all_notifications_as_read($user_id);
            $_SESSION['flash_message'] = 'ทำเครื่องหมายว่าอ่านแล้วทั้งหมดเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        } elseif ($action === 'mark_read') {
            $notif_id = (int) ($_POST['notification_id'] ?? 0);
            if ($notif_id > 0) {
                mark_notification_as_read($notif_id, $user_id);
            }
        } elseif ($action === 'delete') {
            $notif_id = (int) ($_POST['notification_id'] ?? 0);
            if ($notif_id > 0) {
                delete_notification($notif_id, $user_id);
                $_SESSION['flash_message'] = 'ลบการแจ้งเตือนเรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        } elseif ($action === 'clear_read') {
            clear_read_notifications($user_id);
            $_SESSION['flash_message'] = 'ล้างรายการแจ้งเตือนที่อ่านแล้วเรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }

        $params = [];
        if ($redirect_tab !== 'all') {
            $params['tab'] = $redirect_tab;
        }
        if ($redirect_page > 1) {
            $params['page'] = $redirect_page;
        }
        $query = http_build_query($params);
        redirect('/notifications.php' . ($query !== '' ? '?' . $query : ''));
    }
    redirect('/notifications.php');
}

// Active Tab
$tab = trim((string) ($_GET['tab'] ?? 'all'));
if (!in_array($tab, ['all', 'claims', 'reports', 'unread'], true)) {
    $tab = 'all';
}

// Category Counts
$count_all = get_user_notifications_count($user_id, 'all');
$count_claims = get_user_notifications_count($user_id, 'claims');
$count_reports = get_user_notifications_count($user_id, 'reports');
$count_unread = get_user_notifications_count($user_id, 'unread');
$count_read = max(0, $count_all - $count_unread);

// Pagination
$items_per_page = 10;
$total_items = match ($tab) {
    'claims' => $count_claims,
    'reports' => $count_reports,
    'unread' => $count_unread,
    default => $count_all,
};

$total_pages = $total_items > 0 ? (int) ceil($total_items / $items_per_page) : 1;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
if ($current_page > $total_pages) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $items_per_page;

$notifications = get_user_notifications($user_id, $items_per_page, $offset, $tab);

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

render_header('การแจ้งเตือน - Campus Lost & Found System');
?>

<div class="mx-auto max-w-3xl space-y-6">
    <!-- หัวข้อและการจัดการหลัก -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-gray-100">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">การแจ้งเตือน</h1>
            <p class="mt-1 text-xs text-gray-500">
                ประวัติการแจ้งเตือนเกี่ยวกับคำขอรับคืนสิ่งของ และสถานะรายการของคุณ
            </p>
        </div>
        <div class="flex items-center gap-2">
            <?php if ($count_unread > 0): ?>
                <form method="POST" action="/notifications.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_all_read">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <input type="hidden" name="page" value="<?= (int) $current_page ?>">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50 transition">
                        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        อ่านแล้วทั้งหมด
                    </button>
                </form>
            <?php endif; ?>
            <?php if ($count_read > 0): ?>
                <form method="POST" action="/notifications.php" onsubmit="return confirm('คุณต้องการลบการแจ้งเตือนที่อ่านแล้วทั้งหมดใช่หรือไม่?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_read">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-xs hover:bg-gray-50 hover:text-rose-600 transition">
                        <svg class="h-3.5 w-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        ล้างที่อ่านแล้ว
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($flash_message !== null): ?>
        <div class="rounded-xl border p-4 text-xs font-medium <?= $flash_type === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>">
            <?= e($flash_message) ?>
        </div>
    <?php endif; ?>

    <!-- Tabs: ทั้งหมด / คำขอรับคืนสิ่งของ / สถานะรายการของคุณ / ยังไม่ได้อ่าน -->
    <div class="flex items-center gap-1.5 overflow-x-auto rounded-xl bg-gray-100 p-1 text-xs font-medium">
        <a href="/notifications.php?tab=all" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition <?= $tab === 'all' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50' ?>">
            <span>ทั้งหมด</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] <?= $tab === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-200 text-gray-700' ?>">
                <?= number_format($count_all) ?>
            </span>
        </a>
        <a href="/notifications.php?tab=claims" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition <?= $tab === 'claims' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50' ?>">
            <span>คำขอรับคืนสิ่งของ</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] <?= $tab === 'claims' ? 'bg-amber-600 text-white' : 'bg-amber-100 text-amber-800' ?>">
                <?= number_format($count_claims) ?>
            </span>
        </a>
        <a href="/notifications.php?tab=reports" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition <?= $tab === 'reports' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50' ?>">
            <span>สถานะรายการของคุณ</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] <?= $tab === 'reports' ? 'bg-blue-600 text-white' : 'bg-blue-100 text-blue-800' ?>">
                <?= number_format($count_reports) ?>
            </span>
        </a>
        <a href="/notifications.php?tab=unread" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition <?= $tab === 'unread' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-600 hover:text-gray-900 hover:bg-white/50' ?>">
            <span>ยังไม่ได้อ่าน</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] <?= $tab === 'unread' ? 'bg-rose-600 text-white' : ($count_unread > 0 ? 'bg-rose-100 text-rose-800 font-bold' : 'bg-gray-200 text-gray-600') ?>">
                <?= number_format($count_unread) ?>
            </span>
        </a>
    </div>

    <!-- รายการแจ้งเตือน -->
    <div class="rounded-xl border border-gray-200/80 bg-white shadow-xs divide-y divide-gray-100 overflow-hidden">
        <?php if ($notifications === []): ?>
            <div class="p-12 text-center text-xs text-gray-500">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-gray-900 mb-1">ไม่มีประวัติการแจ้งเตือนในหมวดนี้</h3>
                <p class="text-gray-500 max-w-sm mx-auto">
                    <?= $tab === 'unread' ? 'คุณได้อ่านการแจ้งเตือนทั้งหมดเรียบร้อยแล้ว' : 'เมื่อมีการอัปเดตคำขอรับคืน หรือสถานะสิ่งของที่คุณแจ้ง ข้อมูลจะแสดงที่นี่' ?>
                </p>
                <?php if ($tab !== 'all'): ?>
                    <div class="mt-4">
                        <a href="/notifications.php?tab=all" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                            ดูการแจ้งเตือนทั้งหมด
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?php
                    $is_unread = (int) $n['is_read'] === 0;
                    $is_claim = str_contains($n['link'] ?? '', '/claims/') || str_contains($n['title'], 'คำขอรับคืน') || str_contains($n['title'], 'เคลม');
                    $is_report = str_contains($n['link'] ?? '', '/reports/') || str_contains($n['title'], 'สถานะ');
                ?>
                <div class="p-4 sm:p-5 transition hover:bg-gray-50/70 flex items-start justify-between gap-4 <?= $is_unread ? 'bg-gray-50/60' : 'bg-white' ?>">
                    <div class="flex items-start gap-3.5 min-w-0 flex-1">
                        <span class="mt-1 flex h-2.5 w-2.5 shrink-0 rounded-full <?= $is_unread ? 'bg-gray-900 ring-4 ring-gray-200' : 'bg-transparent' ?>"></span>
                        <div class="space-y-1.5 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($is_claim): ?>
                                    <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800 ring-1 ring-inset ring-amber-600/10">
                                        คำขอรับคืน
                                    </span>
                                <?php elseif ($is_report): ?>
                                    <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-700 ring-1 ring-inset ring-blue-600/10">
                                        สถานะรายการ
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10">
                                        ทั่วไป
                                    </span>
                                <?php endif; ?>
                                <h2 class="text-xs sm:text-sm font-semibold text-gray-900 truncate">
                                    <?= e($n['title']) ?>
                                </h2>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-wrap"><?= e($n['message']) ?></p>
                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                <span class="text-[11px] text-gray-400">
                                    <?= e(!empty($n['created_at']) && ($ts = strtotime($n['created_at'])) ? date('d/m/Y H:i', $ts) : '-') ?>
                                </span>
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?= e($n['link']) ?>" class="text-[11px] font-medium text-gray-900 hover:text-gray-600 underline">
                                        ดูรายละเอียด &rarr;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <?php if ($is_unread): ?>
                            <form method="POST" action="/notifications.php" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
                                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                                <input type="hidden" name="page" value="<?= (int) $current_page ?>">
                                <button type="submit" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[11px] text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition shadow-2xs" title="ทำเครื่องหมายว่าอ่านแล้ว">
                                    ✓ อ่านแล้ว
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" action="/notifications.php" class="inline" onsubmit="return confirm('คุณต้องการลบการแจ้งเตือนนี้ใช่หรือไม่?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="notification_id" value="<?= (int) $n['notification_id'] ?>">
                            <input type="hidden" name="tab" value="<?= e($tab) ?>">
                            <input type="hidden" name="page" value="<?= (int) $current_page ?>">
                            <button type="submit" class="rounded-md p-1 text-gray-400 hover:text-rose-600 hover:bg-rose-50 transition" title="ลบการแจ้งเตือน">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination Component -->
    <?php
    $current_page = $current_page;
    $total_items = $total_items;
    $items_per_page = $items_per_page;
    $total_pages = $total_pages;
    $base_url = '/notifications.php';
    $query_params = $tab !== 'all' ? ['tab' => $tab] : [];
    require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<?php render_footer(); ?>
