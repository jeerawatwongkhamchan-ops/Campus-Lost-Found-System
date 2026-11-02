<?php
$claim_status_map = [
    'pending' => ['label' => 'รอดำเนินการ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
    'approved' => ['label' => 'อนุมัติแล้ว', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'rejected' => ['label' => 'ปฏิเสธ', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10'],
    'cancelled' => ['label' => 'ยกเลิกแล้ว', 'class' => 'bg-gray-50 text-gray-500 ring-1 ring-inset ring-gray-200'],
];
?>
<section class="rounded-xl border border-gray-200/80 bg-white shadow-xs overflow-hidden">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-semibold text-gray-900">คำขอรับคืนสิ่งของล่าสุด</h2>
        <a href="/admin/claims.php" class="text-xs font-medium text-gray-500 hover:text-gray-900 transition">ดูทั้งหมด &rarr;</a>
    </div>

    <?php if ($dashboard['claims'] === []): ?>
        <p class="p-6 text-center text-xs text-gray-500">ยังไม่มีรายการคำขอรับคืนสิ่งของ</p>
    <?php else: ?>
        <ul class="divide-y divide-gray-100">
            <?php foreach ($dashboard['claims'] as $claim): ?>
                <?php
                    $status = $claim_status_map[$claim['status']] ?? ['label' => ucfirst($claim['status']), 'class' => 'bg-gray-100 text-gray-700'];
                ?>
                <li class="p-4 transition hover:bg-gray-50/60">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-medium text-gray-900">
                            <?= e($claim['report_title']) ?>
                        </span>
                        <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium <?= $status['class'] ?>">
                            <?= e($status['label']) ?>
                        </span>
                    </div>
                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2 text-[11px] text-gray-500">
                        <span>ผู้ยื่นคำขอ: <?= e($claim['claimant_name']) ?></span>
                        <span class="text-gray-300">·</span>
                        <span><?= e($claim['created_at']) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
