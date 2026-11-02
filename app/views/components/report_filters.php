<?php

declare(strict_types=1);

/** @var array{q: string, report_type: string, category_id: int|null, status: string, page: int, my: bool} $filters */
/** @var list<array{category_id: int, category_name: string}> $categories */

$category_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา / นาฬิกา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];

$is_my = !empty($filters['my']);

$has_filters = $filters['q'] !== ''
    || $filters['report_type'] !== ''
    || $filters['category_id'] !== null
    || $filters['status'] !== ''
    || $is_my;
?>

<div class="rounded-xl border border-gray-200/80 bg-white p-4 sm:p-5 shadow-xs">
    <form id="report-filter-form" method="get" action="/reports/index.php" class="space-y-3.5">
        <input type="hidden" name="my" id="filter-my" value="<?= $is_my ? '1' : '' ?>">

        <!-- แถบค้นหาหลัก -->
        <div class="relative flex items-center">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <input 
                type="search" 
                name="q" 
                value="<?= e($filters['q']) ?>" 
                placeholder="ค้นหาชื่อสิ่งของ, สถานที่ หรือรายละเอียด..." 
                class="block w-full rounded-lg border border-gray-200 bg-gray-50/50 py-2.5 pl-10 pr-20 text-sm text-gray-900 placeholder-gray-400 transition hover:bg-white focus:bg-white focus:border-gray-900 focus:outline-none"
            >
            <button 
                type="submit" 
                class="absolute right-1.5 top-1.5 bottom-1.5 rounded-md bg-gray-900 px-3 text-xs font-medium text-white transition hover:bg-gray-800"
            >
                ค้นหา
            </button>
        </div>

        <!-- แถบตัวกรองและประเภท -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-0.5">
            <!-- Segmented Control สำหรับประเภทสิ่งของ -->
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium text-gray-600 flex-wrap">
                <input type="hidden" name="report_type" id="filter-report-type" value="<?= e($filters['report_type']) ?>">
                
                <button 
                    type="button" 
                    onclick="setFilterType('')"
                    class="rounded-md px-3 py-1.5 transition <?= ($filters['report_type'] === '' && !$is_my) ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>"
                >
                    ทั้งหมด
                </button>
                
                <button 
                    type="button" 
                    onclick="setFilterType('lost')"
                    class="rounded-md px-3 py-1.5 transition <?= $filters['report_type'] === 'lost' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>"
                >
                    ของหาย
                </button>
                
                <button 
                    type="button" 
                    onclick="setFilterType('found')"
                    class="rounded-md px-3 py-1.5 transition <?= $filters['report_type'] === 'found' ? 'bg-white text-gray-900 shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>"
                >
                    พบสิ่งของ
                </button>

                <?php if (is_authenticated()): ?>
                    <button 
                        type="button" 
                        onclick="toggleMyReports()"
                        class="rounded-md px-3 py-1.5 transition <?= $is_my ? 'bg-gray-900 text-white shadow-xs font-semibold' : 'text-gray-500 hover:text-gray-900' ?>"
                    >
                        รายงานของฉัน
                    </button>
                <?php endif; ?>
            </div>

            <!-- Dropdown กรองหมวดหมู่ & สถานะ -->
            <div class="flex flex-wrap items-center gap-2">
                <select 
                    name="category_id" 
                    onchange="this.form.submit()" 
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:border-gray-300 focus:border-gray-900 focus:outline-none"
                >
                    <option value="">หมวดหมู่ทั้งหมด</option>
                    <?php foreach ($categories as $category): ?>
                        <?php 
                            $cat_name = $category['category_name'];
                            $th_name = $category_translations[$cat_name] ?? $cat_name;
                        ?>
                        <option value="<?= $category['category_id'] ?>" <?= $filters['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>>
                            <?= e($th_name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select 
                    name="status" 
                    onchange="this.form.submit()" 
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:border-gray-300 focus:border-gray-900 focus:outline-none"
                >
                    <option value="">สถานะทั้งหมด</option>
                    <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>รอเจ้าของ</option>
                    <option value="claimed" <?= $filters['status'] === 'claimed' ? 'selected' : '' ?>>มีผู้ขอรับคืน</option>
                    <option value="resolved" <?= $filters['status'] === 'resolved' ? 'selected' : '' ?>>ส่งคืนสำเร็จ</option>
                    <option value="closed" <?= $filters['status'] === 'closed' ? 'selected' : '' ?>>ปิดเรื่องแล้ว</option>
                </select>

                <?php if ($has_filters): ?>
                    <a 
                        href="/reports/index.php" 
                        class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition"
                        title="รีเซ็ตตัวกรองทั้งหมด"
                    >
                        ล้างตัวกรอง
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<script>
function setFilterType(type) {
    const input = document.getElementById('filter-report-type');
    const form = document.getElementById('report-filter-form');
    if (input && form) {
        input.value = type;
        form.submit();
    }
}

function toggleMyReports() {
    const myInput = document.getElementById('filter-my');
    const form = document.getElementById('report-filter-form');
    if (myInput && form) {
        myInput.value = myInput.value === '1' ? '' : '1';
        form.submit();
    }
}
</script>
