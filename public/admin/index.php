<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';
require_once APP_PATH . '/admin/dashboard_data.php';

$admin = require_staff_or_admin_access();
$is_admin = is_admin_user($admin);

try {
    $connection = get_db_connection();
    if ($connection === null) {
        throw new RuntimeException('Database unavailable');
    }
    $dashboard = load_admin_dashboard($connection);
} catch (Throwable $error) {
    error_log('Staff/Admin dashboard failed: ' . $error->getMessage());
    http_response_code(503);
    exit('Service temporarily unavailable.');
}

$active_admin_tab = 'dashboard';
$admin_page_heading = $is_admin ? 'ภาพรวมแดชบอร์ดผู้ดูแลระบบ' : 'ภาพรวมระบบงานเจ้าหน้าที่ (Staff Dashboard)';
$page_title = $is_admin ? 'จัดการระบบ (Admin) - Campus Lost & Found System' : 'ระบบงานเจ้าหน้าที่ (Staff) - Campus Lost & Found System';
render_header($page_title);
require APP_PATH . '/views/admin/dashboard.php';
render_footer();
