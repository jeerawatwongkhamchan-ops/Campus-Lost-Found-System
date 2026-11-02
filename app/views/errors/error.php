<?php

declare(strict_types=1);

/** @var int $status_code */
/** @var string $error_title */
/** @var string $error_message */

render_header("ข้อผิดพลาด {$status_code} - Campus Lost & Found System");
?>

<div class="mx-auto max-w-lg text-center py-12">
    <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-rose-50 text-rose-600 mb-4">
        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
    </div>
    <span class="inline-block rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-800">
        HTTP Status <?= e((string)$status_code) ?>
    </span>
    <h1 class="mt-3 text-2xl font-bold tracking-tight text-gray-900"><?= e($error_title) ?></h1>
    <p class="mt-2 text-sm text-gray-600"><?= e($error_message) ?></p>

    <div class="mt-6 flex items-center justify-center gap-3">
        <a href="/reports/index.php" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-800">
            กลับหน้ารายการสิ่งของ
        </a>
        <button onclick="window.history.back()" type="button" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">
            ย้อนกลับ
        </button>
    </div>
</div>

<?php render_footer(); ?>
