<?php

declare(strict_types=1);

/** @var list<array{report_id: int, report_type: string, title: string, description?: string, category_name: string, location: string, event_datetime: string, status: string, image_filename?: string|null}> $reports */
/** @var bool $has_active_filters */
/** @var int $total_reports */

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
        'class' => 'bg-slate-50 text-slate-700 ring-1 ring-inset ring-slate-600/10',
        'badge_class' => 'bg-white/95 text-gray-700 border border-gray-200/80',
    ],
    'claimed' => [
        'label' => 'มีผู้ขอรับคืน',
        'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10',
        'badge_class' => 'bg-amber-50/95 text-amber-800 border border-amber-200/80',
    ],
    'resolved' => [
        'label' => 'ส่งคืนสำเร็จ',
        'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10',
        'badge_class' => 'bg-emerald-50/95 text-emerald-800 border border-emerald-200/80',
    ],
    'closed' => [
        'label' => 'ปิดเรื่องแล้ว',
        'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200',
        'badge_class' => 'bg-gray-100/95 text-gray-600 border border-gray-200/80',
    ],
];
?>

<div class="space-y-4">
    <!-- Header แถบแสดงจำนวนรายการ และปุ่มสลับมุมมอง (Grid / Table) -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-gray-200/80 bg-white px-5 py-3.5 shadow-xs">
        <div>
            <h2 class="text-sm font-semibold text-gray-900">
                <?= $has_active_filters ? 'ผลลัพธ์การค้นหา' : 'รายการสิ่งของทั้งหมด' ?>
            </h2>
            <p class="mt-0.5 text-xs text-gray-500">
                พบข้อมูลทั้งหมด <?= number_format($total_reports) ?> รายการ
            </p>
        </div>

        <?php if ($reports !== []): ?>
            <!-- View Mode Switcher -->
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 text-xs font-medium text-gray-600 self-start sm:self-auto">
                <button type="button" id="view-mode-grid" onclick="switchViewMode('grid')" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition font-semibold bg-white text-gray-900 shadow-xs">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>แบบการ์ด (Grid)</span>
                </button>
                <button type="button" id="view-mode-table" onclick="switchViewMode('table')" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition text-gray-500 hover:text-gray-900">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                    <span>แบบตาราง (Table)</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($reports === []): ?>
        <div class="px-6 py-12 text-center">
            <?php if ($has_active_filters): ?>
                <div class="mx-auto max-w-sm">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">ไม่พบข้อมูลที่ตรงกับการค้นหา</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        ลองเปลี่ยนคำค้นหา หรือล้างตัวกรองเพื่อดูรายการทั้งหมด
                    </p>
                    <div class="mt-4">
                        <a href="/reports/index.php" class="inline-flex rounded-lg border border-gray-200 bg-white px-3.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                            ล้างตัวกรองทั้งหมด
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="mx-auto max-w-sm">
                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                        </svg>
                    </div>
                    <h3 class="mt-3 text-sm font-semibold text-gray-900">ยังไม่มีรายการแจ้งของหายหรือพบของ</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        เริ่มต้นแจ้งข้อมูลสิ่งของที่พบหรือสูญหายในระบบได้ทันที
                    </p>
                    <div class="mt-4">
                        <a href="/reports/create.php" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            เริ่มแจ้งเรื่อง
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- 1. แบบการ์ด (Grid View - Default) -->
        <div id="reports-grid-view" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($reports as $report): ?>
                <?php 
                    $is_lost = $report['report_type'] === 'lost';
                    $status = $status_info[$report['status']] ?? [
                        'label' => ucfirst($report['status']),
                        'class' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10',
                        'badge_class' => 'bg-white/95 text-gray-700 border border-gray-200',
                    ];
                    $cat_name = $report['category_name'];
                    $th_category = $category_translations[$cat_name] ?? $cat_name;
                    $thumb_url = get_report_image_url($report['image_filename'] ?? null);
                ?>
                <article class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-gray-300 hover:shadow-xs">
                    <!-- รูปภาพ หรือ ไอคอนแบบการ์ด -->
                    <div class="relative h-44 w-full overflow-hidden bg-gray-50 border-b border-gray-100">
                        <?php if ($thumb_url !== null): ?>
                            <img 
                                src="<?= e($thumb_url) ?>" 
                                alt="<?= e($report['title']) ?>" 
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="flex h-full w-full flex-col items-center justify-center text-gray-400">
                                <svg class="h-8 w-8 stroke-[1.5] text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="mt-1.5 text-xs text-gray-400 font-medium">ไม่มีรูปภาพ</span>
                            </div>
                        <?php endif; ?>

                        <!-- ป้ายประเภท (ของหาย / พบสิ่งของ) ด้านซ้ายบน -->
                        <div class="absolute left-3 top-3">
                            <?php if ($is_lost): ?>
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white/95 px-2.5 py-1 text-xs font-medium text-rose-700 shadow-2xs border border-gray-200/80 backdrop-blur-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    ของหาย
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 rounded-md bg-white/95 px-2.5 py-1 text-xs font-medium text-emerald-700 shadow-2xs border border-gray-200/80 backdrop-blur-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    พบสิ่งของ
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- ป้ายสถานะ ด้านขวาบน -->
                        <div class="absolute right-3 top-3">
                            <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-medium shadow-2xs <?= $status['badge_class'] ?>">
                                <?= e($status['label']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- เนื้อหาการ์ด -->
                    <div class="flex flex-1 flex-col justify-between p-4 space-y-3.5">
                        <div class="space-y-2">
                            <!-- หมวดหมู่และวันเวลา -->
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span class="font-medium text-gray-600">
                                    <?= e($th_category) ?>
                                </span>
                                <time class="text-gray-400">
                                    <?= e(!empty($report['event_datetime']) && ($ts = strtotime($report['event_datetime'])) ? date('d/m/Y', $ts) : '-') ?>
                                </time>
                            </div>

                            <!-- ชื่อสิ่งของ -->
                            <h3 class="text-base font-semibold text-gray-900 line-clamp-1 leading-snug">
                                <a href="/reports/show.php?id=<?= (int) $report['report_id'] ?>" class="hover:text-blue-600 hover:underline focus:outline-none">
                                    <?= e($report['title']) ?>
                                </a>
                            </h3>

                            <!-- รายละเอียดสังเขป -->
                            <?php if (!empty($report['description'])): ?>
                                <p class="text-xs text-gray-500 line-clamp-2 leading-relaxed">
                                    <?= e($report['description']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- สถานที่ -->
                            <div class="flex items-center gap-1.5 text-xs text-gray-600 pt-0.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span class="truncate"><?= e($report['location']) ?></span>
                            </div>
                        </div>

                        <!-- แถบปุ่มด้านล่างการ์ด -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                            <?php if (in_array($report['status'], ['open', 'claimed'], true)): ?>
                                <a href="/claims/create.php?report_id=<?= (int) $report['report_id'] ?>" class="inline-flex items-center rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-gray-800">
                                    ยื่นขอรับคืน
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400">ปิดรายการแล้ว</span>
                            <?php endif; ?>

                            <a href="/reports/show.php?id=<?= (int) $report['report_id'] ?>" class="inline-flex items-center gap-1 text-xs font-medium text-gray-600 hover:text-gray-900 transition ml-auto">
                                <span>ดูรายละเอียด</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- 2. แบบตาราง (Table View - Alternative) -->
        <div id="reports-table-view" class="hidden overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50/70 text-xs font-medium text-gray-500">
                        <tr>
                            <th scope="col" class="px-5 py-3 text-left">ประเภท</th>
                            <th scope="col" class="px-5 py-3 text-left">ชื่อสิ่งของ</th>
                            <th scope="col" class="px-5 py-3 text-left">หมวดหมู่</th>
                            <th scope="col" class="px-5 py-3 text-left">สถานที่</th>
                            <th scope="col" class="px-5 py-3 text-left">วันและเวลา</th>
                            <th scope="col" class="px-5 py-3 text-left">สถานะ</th>
                            <th scope="col" class="px-5 py-3 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white text-xs">
                        <?php foreach ($reports as $report): ?>
                            <?php 
                                $is_lost = $report['report_type'] === 'lost';
                                $status = $status_info[$report['status']] ?? [
                                    'label' => ucfirst($report['status']),
                                    'class' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10',
                                ];
                                $cat_name = $report['category_name'];
                                $th_category = $category_translations[$cat_name] ?? $cat_name;
                                $thumb_url = get_report_image_url($report['image_filename'] ?? null);
                            ?>
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <?php if ($is_lost): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            ของหาย
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            พบสิ่งของ
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 font-medium text-gray-900">
                                    <div class="flex items-center gap-3">
                                        <?php if ($thumb_url !== null): ?>
                                            <img src="<?= e($thumb_url) ?>" alt="" class="h-9 w-9 shrink-0 rounded-lg object-cover border border-gray-200 shadow-2xs">
                                        <?php else: ?>
                                            <div class="h-9 w-9 shrink-0 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <a href="/reports/show.php?id=<?= (int) $report['report_id'] ?>" class="hover:text-gray-600 transition font-semibold">
                                            <?= e($report['title']) ?>
                                        </a>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-gray-600">
                                    <?= e($th_category) ?>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">
                                    <span class="line-clamp-1"><?= e($report['location']) ?></span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-gray-500 font-mono text-[11px]">
                                    <?= e(!empty($report['event_datetime']) && ($ts = strtotime($report['event_datetime'])) ? date('d/m/Y H:i', $ts) : ($report['event_datetime'] ?? '-')) ?>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium <?= $status['class'] ?>">
                                        <?= e($status['label']) ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if (in_array($report['status'], ['open', 'claimed'], true)): ?>
                                            <a href="/claims/create.php?report_id=<?= (int) $report['report_id'] ?>" class="rounded-lg bg-gray-900 px-2.5 py-1 text-xs font-medium text-white hover:bg-gray-800 transition shadow-xs">
                                                ยื่นขอรับคืน
                                            </a>
                                        <?php endif; ?>
                                        <a href="/reports/show.php?id=<?= (int) $report['report_id'] ?>" class="font-medium text-gray-500 hover:text-gray-900 transition">
                                            ดูรายละเอียด &rarr;
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function switchViewMode(mode) {
            const gridEl = document.getElementById('reports-grid-view');
            const tableEl = document.getElementById('reports-table-view');
            const gridBtn = document.getElementById('view-mode-grid');
            const tableBtn = document.getElementById('view-mode-table');
            
            if (!gridEl || !tableEl || !gridBtn || !tableBtn) return;
            
            if (mode === 'table') {
                gridEl.classList.add('hidden');
                tableEl.classList.remove('hidden');
                gridBtn.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition text-gray-500 hover:text-gray-900';
                tableBtn.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition font-semibold bg-white text-gray-900 shadow-xs';
                localStorage.setItem('reports_view_mode', 'table');
            } else {
                gridEl.classList.remove('hidden');
                tableEl.classList.add('hidden');
                gridBtn.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition font-semibold bg-white text-gray-900 shadow-xs';
                tableBtn.className = 'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 transition text-gray-500 hover:text-gray-900';
                localStorage.setItem('reports_view_mode', 'grid');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const savedMode = localStorage.getItem('reports_view_mode');
            if (savedMode === 'table') {
                switchViewMode('table');
            } else {
                switchViewMode('grid');
            }
        });
        </script>
    <?php endif; ?>
</div>
