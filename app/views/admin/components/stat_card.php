<?php if (!empty($card['link'])): ?>
    <a href="<?= e($card['link']) ?>" class="block rounded-xl border border-gray-200/80 bg-white p-5 shadow-xs transition hover:border-gray-400 hover:shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-xs font-medium text-gray-500"><?= e($card['label']) ?></h2>
            <span class="text-xs text-gray-400">&rarr;</span>
        </div>
        <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900"><?= number_format((int) $card['value']) ?></p>
    </a>
<?php else: ?>
    <section class="rounded-xl border border-gray-200/80 bg-white p-5 shadow-xs">
        <h2 class="text-xs font-medium text-gray-500"><?= e($card['label']) ?></h2>
        <p class="mt-2 text-2xl font-bold tracking-tight text-gray-900"><?= number_format((int) $card['value']) ?></p>
    </section>
<?php endif; ?>
