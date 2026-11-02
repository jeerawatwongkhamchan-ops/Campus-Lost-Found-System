<?php

declare(strict_types=1);

/** @var array<string, mixed> $filters */
/** @var int $current_page */
/** @var int $total_pages */
/** @var int $total_reports */

$total_items = $total_reports ?? 0;
$items_per_page = REPORTS_PER_PAGE;
$base_url = '/reports/index.php';
$query_params = $_GET;

require APP_PATH . '/views/components/pagination.php';
