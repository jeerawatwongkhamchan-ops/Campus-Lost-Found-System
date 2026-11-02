<?php

declare(strict_types=1);

/**
 * Shared Admin CRUD Modal Container
 *
 * @var string $modal_id Unique DOM ID (e.g. 'create-category-modal')
 * @var string $modal_title Title of modal
 * @var string|null $modal_subtitle Subtitle/Description
 * @var string $modal_content HTML content of the form inside modal
 * @var string|null $modal_max_width Max width class (e.g. 'max-w-md', 'max-w-xl', 'max-w-2xl', default: 'max-w-xl')
 */

$modal_id = $modal_id ?? 'admin-modal';
$modal_title = $modal_title ?? '';
$modal_subtitle = $modal_subtitle ?? null;
$modal_content = $modal_content ?? '';
$modal_max_width = $modal_max_width ?? 'max-w-xl';
?>

<div
    id="<?= e($modal_id) ?>"
    class="fixed inset-0 z-50 hidden overflow-y-auto"
    aria-labelledby="<?= e($modal_id) ?>-title"
    role="dialog"
    aria-modal="true"
>
    <!-- Backdrop overlay with smooth fade-in animation -->
    <div
        class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs modal-backdrop-animate"
        onclick="closeAdminModal('<?= e($modal_id) ?>')"
    ></div>

    <div class="relative z-10 flex min-h-full items-center justify-center p-4 text-center pointer-events-none">
        <!-- Dialog Card with smooth pop-in animation & friendly rounded corners -->
        <div class="relative w-full <?= e($modal_max_width) ?> transform overflow-hidden rounded-3xl bg-white text-left align-middle shadow-2xl modal-content-animate border border-gray-100 p-6 sm:p-7 pointer-events-auto">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-4 border-b border-gray-100">
                <div>
                    <h3 id="<?= e($modal_id) ?>-title" class="text-base sm:text-lg font-bold text-gray-900">
                        <?= e($modal_title) ?>
                    </h3>
                    <?php if (!empty($modal_subtitle)): ?>
                        <p class="mt-1 text-xs sm:text-sm text-gray-500 leading-relaxed"><?= e($modal_subtitle) ?></p>
                    <?php endif; ?>
                </div>
                <button
                    type="button"
                    onclick="closeAdminModal('<?= e($modal_id) ?>')"
                    class="rounded-xl p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition-colors duration-200 cursor-pointer"
                    aria-label="Close"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="mt-5">
                <?= $modal_content ?>
            </div>
        </div>
    </div>
</div>
