<?php

declare(strict_types=1);

/**
 * Shared Admin Delete Confirmation Modal
 *
 * Provides a standardized accessible modal dialog for all delete actions.
 */
?>

<div
    id="admin-delete-modal"
    class="fixed inset-0 z-50 hidden overflow-y-auto"
    aria-labelledby="delete-modal-title"
    role="dialog"
    aria-modal="true"
>
    <!-- Backdrop overlay with smooth fade-in animation -->
    <div
        class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs modal-backdrop-animate"
        onclick="closeAdminDeleteModal()"
    ></div>

    <div class="relative z-10 flex min-h-full items-center justify-center p-4 text-center pointer-events-none">
        <div class="relative w-full max-w-md transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl modal-content-animate border border-gray-100 p-6 pointer-events-auto">
            <div class="flex items-start gap-3.5">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 id="delete-modal-title" class="text-base font-bold text-gray-900">
                        ยืนยันการลบข้อมูล
                    </h3>
                    <p id="delete-modal-message" class="mt-1 text-xs sm:text-sm text-gray-600 leading-relaxed">
                        คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้? การกระทำนี้ไม่สามารถย้อนกลับได้
                    </p>
                    <div id="delete-modal-warning" class="mt-3 hidden rounded-lg bg-amber-50 p-2.5 text-xs text-amber-800 border border-amber-200 leading-relaxed">
                        <!-- Extra warning message -->
                    </div>
                </div>
            </div>

            <!-- Delete Form -->
            <form id="admin-delete-form" method="POST" action="" class="mt-5 flex items-center justify-end gap-2.5 pt-3 border-t border-gray-100">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="delete-form-action" value="delete">
                <input type="hidden" name="id" id="delete-form-id" value="">

                <button
                    type="button"
                    onclick="closeAdminDeleteModal()"
                    class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
                >
                    ยกเลิก
                </button>
                <button
                    type="submit"
                    id="delete-form-submit-btn"
                    class="rounded-lg bg-red-600 px-4 py-2 text-xs sm:text-sm font-medium text-white hover:bg-red-700 transition shadow-xs cursor-pointer"
                >
                    ยืนยันลบข้อมูล
                </button>
            </form>
        </div>
    </div>
</div>
