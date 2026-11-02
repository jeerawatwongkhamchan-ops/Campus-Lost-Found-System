<?php

declare(strict_types=1);

/**
 * Universal Pagination Component
 *
 * @var int $current_page Current page number (1-indexed)
 * @var int $total_pages Total number of pages
 * @var int $total_items Total number of records
 * @var int $items_per_page Number of items per page
 * @var string $base_url Target URL for pagination links
 * @var array<string, mixed> $query_params Current query string parameters to preserve
 */

if (!isset($total_pages) || $total_pages <= 0 || !isset($total_items) || $total_items <= 0) {
    return;
}

$page = max(1, min($current_page ?? 1, $total_pages));
$per_page = $items_per_page ?? 20;
$start_item = (($page - 1) * $per_page) + 1;
$end_item = min($total_items, $page * $per_page);
$target_url = $base_url ?? (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$params = $query_params ?? $_GET;

$make_url = static function (int $target_page) use ($target_url, $params): string {
    $p = $params;
    $p['page'] = $target_page;
    $p = array_filter($p, static fn ($val): bool => $val !== '' && $val !== null);
    if ($target_page === 1) {
        unset($p['page']);
    }
    $query = http_build_query($p);
    return $target_url . ($query === '' ? '' : '?' . $query);
};

$first_page = max(1, $page - 2);
$last_page = min($total_pages, $page + 2);
?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-gray-200/80 bg-white p-3.5 shadow-xs text-xs">
    <div class="text-gray-500">
        แสดงรายการที่ <span class="font-semibold text-gray-900"><?= $start_item ?></span> ถึง <span class="font-semibold text-gray-900"><?= $end_item ?></span> จากทั้งหมด <span class="font-semibold text-gray-900"><?= number_format($total_items) ?></span> รายการ
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="flex items-center gap-1.5" aria-label="Pagination">
            <!-- ปุ่มหน้าก่อนหน้า -->
            <?php if ($page > 1): ?>
                <a href="<?= e($make_url($page - 1)) ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                    &larr; ก่อนหน้า
                </a>
            <?php else: ?>
                <span class="inline-flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-300 cursor-not-allowed">
                    &larr; ก่อนหน้า
                </span>
            <?php endif; ?>

            <!-- หน้าแรก หากอยู่นอกระยะ -->
            <?php if ($first_page > 1): ?>
                <a href="<?= e($make_url(1)) ?>" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                    1
                </a>
                <?php if ($first_page > 2): ?>
                    <span class="px-1 text-gray-400">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <!-- เลขหน้า -->
            <?php for ($p = $first_page; $p <= $last_page; $p++): ?>
                <?php if ($p === $page): ?>
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gray-900 text-xs font-bold text-white shadow-xs" aria-current="page">
                        <?= $p ?>
                    </span>
                <?php else: ?>
                    <a href="<?= e($make_url($p)) ?>" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                        <?= $p ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>

            <!-- หน้าสุดท้าย หากอยู่นอกระยะ -->
            <?php if ($last_page < $total_pages): ?>
                <?php if ($last_page < $total_pages - 1): ?>
                    <span class="px-1 text-gray-400">...</span>
                <?php endif; ?>
                <a href="<?= e($make_url($total_pages)) ?>" class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-gray-200 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                    <?= $total_pages ?>
                </a>
            <?php endif; ?>

            <!-- ปุ่มหน้าถัดไป -->
            <?php if ($page < $total_pages): ?>
                <a href="<?= e($make_url($page + 1)) ?>" class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs">
                    ถัดไป &rarr;
                </a>
            <?php else: ?>
                <span class="inline-flex items-center justify-center rounded-lg border border-gray-100 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-300 cursor-not-allowed">
                    ถัดไป &rarr;
                </span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
