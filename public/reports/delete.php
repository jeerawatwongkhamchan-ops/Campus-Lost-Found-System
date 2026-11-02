<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';
require_once APP_PATH . '/reports/report_data.php';

reports_require_post();
$reportId = reports_positive_id($_POST['report_id'] ?? null);
if ($reportId === null) {
    reports_error(400, 'Invalid report ID.');
}

try {
    $database = reports_database();
    $user = reports_require_user($database);
    reports_verify_csrf($_POST['csrf_token'] ?? null);
    $report = reports_find($database, $reportId);
    if ($report === null) {
        reports_error(404, 'Report not found.');
    }
    if (!reports_can_manage($report, $user)) {
        reports_error(403, 'Access denied.');
    }
    reports_delete($database, $reportId, $user['user_id']);
    redirect('/reports/index.php');
} catch (Throwable $error) {
    reports_log_failure($error);
}
