<?php

declare(strict_types=1);

/** @var list<array<string, mixed>> $reports */
?>

<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Location</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php if ($reports === []): ?>
                <tr><td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No reports have been submitted.</td></tr>
            <?php endif; ?>
            <?php foreach ($reports as $report): ?>
                <tr>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700"><?= e(ucfirst($report['report_type'])) ?></td>
                    <td class="px-6 py-4 text-sm font-medium"><a class="text-gray-900 underline hover:text-gray-600" href="/reports/show.php?id=<?= (int) $report['report_id'] ?>"><?= e($report['title']) ?></a></td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700"><?= e($report['category_name']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= e($report['location']) ?></td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700"><?= e($report['event_datetime']) ?></td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700"><?= e(ucfirst($report['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
