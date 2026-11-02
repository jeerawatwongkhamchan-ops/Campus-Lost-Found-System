<?php

declare(strict_types=1);

/**
 * Shared Admin CRUD Header Component
 *
 * @var string $crud_title Title of the module (e.g. 'จัดการหมวดหมู่สิ่งของ')
 * @var string|null $crud_subtitle Subtitle / English title (e.g. 'Categories Management')
 * @var int|null $crud_total Total count of items
 * @var string|null $crud_create_label Label for the create primary button (e.g. '+ เพิ่มหมวดหมู่ใหม่')
 * @var string|null $crud_create_modal_id Target modal DOM ID (default: 'create-modal')
 * @var string|null $crud_extra_actions Additional HTML for actions (e.g. CSV Export)
 */

$crud_title = $crud_title ?? '';
$crud_subtitle = $crud_subtitle ?? null;
$crud_total = $crud_total ?? null;
$crud_create_label = $crud_create_label ?? null;
$crud_create_modal_id = $crud_create_modal_id ?? 'create-modal';
$crud_extra_actions = $crud_extra_actions ?? null;
?>

<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between py-1">
    <div class="flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-bold tracking-tight text-gray-900">
            <?= e($crud_title) ?>
            <?php if (!empty($crud_subtitle)): ?>
                <span class="text-xs sm:text-sm font-normal text-gray-500 ml-1.5">(<?= e($crud_subtitle) ?>)</span>
            <?php endif; ?>
        </h2>
        <?php if ($crud_total !== null): ?>
            <span class="inline-flex items-center rounded-md bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-600/10">
                <?= number_format($crud_total) ?> รายการ
            </span>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <?php if (!empty($crud_extra_actions)): ?>
            <?= $crud_extra_actions ?>
        <?php endif; ?>

        <?php if (!empty($crud_create_label)): ?>
            <button
                type="button"
                onclick="openAdminModal('<?= e($crud_create_modal_id) ?>')"
                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-3.5 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <?= e($crud_create_label) ?>
            </button>
        <?php endif; ?>
    </div>
</div>
