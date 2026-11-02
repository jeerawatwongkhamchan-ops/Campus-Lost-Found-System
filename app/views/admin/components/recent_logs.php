<?php
$event_translations = [
    'login_success' => 'เข้าสู่ระบบสำเร็จ',
    'AUTH_LOGIN_SUCCESS' => 'เข้าสู่ระบบสำเร็จ',
    'login_failed' => 'เข้าสู่ระบบไม่สำเร็จ',
    'AUTH_LOGIN_FAILED' => 'เข้าสู่ระบบไม่สำเร็จ',
    'logout' => 'ออกจากระบบ',
    'AUTH_LOGOUT' => 'ออกจากระบบ',
    'register_success' => 'สมัครสมาชิกสำเร็จ',
    'AUTH_REGISTER_SUCCESS' => 'สมัครสมาชิกสำเร็จ',
    'login_throttled' => 'บล็อกความถี่ล็อกอิน',
    'LOGIN_THROTTLED' => 'บล็อกความถี่ล็อกอิน',
    'report_created' => 'สร้างรายงานสิ่งของ',
    'claim_submitted' => 'ยื่นคำขอรับของคืน',
];

$outcome_map = [
    'success' => ['label' => 'สำเร็จ', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'SUCCESS' => ['label' => 'สำเร็จ', 'class' => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/10'],
    'failure' => ['label' => 'ไม่สำเร็จ', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10'],
    'FAILURE' => ['label' => 'ไม่สำเร็จ', 'class' => 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/10'],
    'blocked' => ['label' => 'ถูกระงับ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
    'BLOCKED' => ['label' => 'ถูกระงับ', 'class' => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/10'],
];
?>
<section class="rounded-xl border border-gray-200/80 bg-white shadow-xs overflow-hidden">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
        <h2 class="text-sm font-semibold text-gray-900">บันทึกความปลอดภัยล่าสุด (Security Logs)</h2>
        <a href="/admin/logs.php" class="text-xs font-medium text-gray-500 hover:text-gray-900 transition">ดูทั้งหมด &rarr;</a>
    </div>

    <?php if ($dashboard['logs'] === []): ?>
        <p class="p-6 text-center text-xs text-gray-500">ยังไม่มีประวัติบันทึกความปลอดภัย</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs">
                <thead class="bg-gray-50/70 text-gray-500 font-medium">
                    <tr>
                        <th scope="col" class="px-5 py-3">เหตุการณ์</th>
                        <th scope="col" class="px-5 py-3">ผลลัพธ์</th>
                        <th scope="col" class="px-5 py-3">วันและเวลา</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach ($dashboard['logs'] as $log): ?>
                        <?php
                            $ev_raw = (string) $log['event_type'];
                            $event_name = $event_translations[$ev_raw] ?? str_replace('_', ' ', $ev_raw);
                            $out_raw = (string) $log['outcome'];
                            $outcome = $outcome_map[$out_raw] ?? ['label' => $out_raw, 'class' => 'bg-gray-100 text-gray-700'];
                        ?>
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                <div><?= e($event_name) ?></div>
                                <div class="text-[10px] font-mono text-gray-400 mt-0.5"><?= e($ev_raw) ?></div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium <?= $outcome['class'] ?>">
                                    <?= e($outcome['label']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-gray-500">
                                <?= e($log['created_at']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
