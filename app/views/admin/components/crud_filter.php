<?php

declare(strict_types=1);

/**
 * Shared Admin CRUD Filter Bar Component
 *
 * @var string $filter_action Form action URL (e.g. '/admin/categories.php')
 * @var string|null $filter_search_value Current search query
 * @var string|null $filter_search_placeholder Placeholder for search input
 * @var string|null $filter_fields_html Additional HTML dropdown selects / inputs
 * @var bool|null $filter_has_active_filters Whether filters are currently active (to display reset button)
 * @var string|null $filter_reset_url URL to reset filters
 */

$filter_action = $filter_action ?? '';
$filter_search_value = $filter_search_value ?? '';
$filter_search_placeholder = $filter_search_placeholder ?? 'ค้นหา...';
$filter_fields_html = $filter_fields_html ?? '';
$filter_has_active_filters = $filter_has_active_filters ?? false;
$filter_reset_url = $filter_reset_url ?? $filter_action;
?>

<form method="GET" action="<?= e($filter_action) ?>" class="rounded-xl border border-gray-200/80 bg-white p-4 sm:p-5 shadow-xs">
    <div class="flex flex-col gap-3.5 md:flex-row md:items-end">
        <!-- Search Input -->
        <div class="flex-1">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ค้นหา</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    name="q"
                    value="<?= e($filter_search_value) ?>"
                    placeholder="<?= e($filter_search_placeholder) ?>"
                    class="block w-full rounded-lg border border-gray-200 bg-gray-50/50 pl-10 pr-3.5 py-2 text-xs sm:text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-gray-900 focus:outline-none transition"
                >
            </div>
        </div>

        <!-- Custom Dropdown Slots -->
        <?php if (!empty($filter_fields_html)): ?>
            <?= $filter_fields_html ?>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2">
            <button
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
            >
                ค้นหา
            </button>
            <?php if ($filter_has_active_filters): ?>
                <a
                    href="<?= e($filter_reset_url) ?>"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition"
                >
                    ล้างตัวกรอง
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>
