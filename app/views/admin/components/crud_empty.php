<?php

declare(strict_types=1);

/**
 * Shared Admin Empty State Component
 *
 * @var string|null $empty_title Title (e.g. 'ไม่พบข้อมูล')
 * @var string|null $empty_message Detailed explanation
 * @var int|null $empty_colspan Number of columns to span (default: 7)
 */

$empty_title = $empty_title ?? 'ไม่พบข้อมูลในระบบ';
$empty_message = $empty_message ?? 'ลองปรับคำค้นหา ตัวกรอง หรือเพิ่มรายการใหม่เข้าสู่ระบบ';
$empty_colspan = $empty_colspan ?? 7;
?>

<tr>
    <td colspan="<?= (int) $empty_colspan ?>" class="px-5 py-14 text-center">
        <div class="mx-auto flex flex-col items-center justify-center text-center">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-3">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
            </div>
            <h3 class="text-sm sm:text-base font-bold text-gray-900"><?= e($empty_title) ?></h3>
            <p class="mt-1.5 text-xs sm:text-sm text-gray-500 max-w-md leading-relaxed"><?= e($empty_message) ?></p>
        </div>
    </td>
</tr>
