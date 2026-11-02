<?php
$cat_translations = [
    'Electronics' => 'อุปกรณ์อิเล็กทรอนิกส์',
    'Documents' => 'เอกสาร / บัตรประจำตัว',
    'Bags' => 'กระเป๋า / เป้',
    'Accessories' => 'เครื่องประดับ / แว่นตา',
    'Clothing' => 'เสื้อผ้า / เครื่องแต่งกาย',
    'Keys' => 'กุญแจ / พวงกุญแจ',
    'Other' => 'อื่นๆ',
];

$report_status_map = [
    'open' => ['label' => 'รอเจ้าของ', 'class' => 'bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-600/10'],
    'claimed' => ['label' => 'มีผู้ขอรับคืน', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
    'resolved' => ['label' => 'ส่งคืนสำเร็จ', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'closed' => ['label' => 'ปิดเรื่องแล้ว', 'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200'],
];
?>
<section class="rounded-xl border border-gray-200/80 bg-white shadow-xs overflow-hidden">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-semibold text-gray-900">รายงานสิ่งของล่าสุด</h2>
        <a href="/reports/index.php" class="text-xs font-medium text-gray-500 hover:text-gray-900 transition">ดูทั้งหมด &rarr;</a>
    </div>

    <?php if ($dashboard['reports'] === []): ?>
        <p class="p-6 text-center text-xs text-gray-500">ยังไม่มีรายการแจ้งของหายหรือพบของ</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($dashboard['reports'] as $report): ?>
                <?php
                    $is_lost = $report['report_type'] === 'lost';
                    $status = $report_status_map[$report['status']] ?? ['label' => ucfirst($report['status']), 'class' => 'bg-gray-100 text-gray-700'];
                    $cat = $cat_translations[$report['category_name']] ?? $report['category_name'];
                ?>
                <li class="p-4 transition hover:bg-gray-50/60">
                    <div class="flex items-center justify-between gap-3">
                        <a class="text-xs font-medium text-gray-900 hover:text-gray-600 transition" href="/reports/show.php?id=<?= (int) $report['report_id'] ?>">
                            <?= e($report['title']) ?>
                        </a>
                        <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium <?= $status['class'] ?>">
                            <?= e($status['label']) ?>
                        </span>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500">
                        <?php if ($is_lost): ?>
                            <span class="text-rose-600 font-medium">ของหาย</span>
                        <?php else: ?>
                            <span class="text-emerald-600 font-medium">พบสิ่งของ</span>
                        <?php endif; ?>
                        <span class="text-gray-300">·</span>
                        <span><?= e($cat) ?></span>
                        <span class="text-gray-300">·</span>
                        <span>ผู้แจ้ง: <?= e($report['reporter_name']) ?></span>
                        <span class="text-gray-300">·</span>
                        <span><?= e($report['created_at']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
