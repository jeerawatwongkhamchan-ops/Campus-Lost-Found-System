<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';

$admin = require_admin_access();

$db = get_db_connection();
$logs = [];
$filter_event = trim((string)($_GET['event_type'] ?? ''));
$filter_outcome = trim((string)($_GET['outcome'] ?? ''));

$items_per_page = 25;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_items = 0;
$total_pages = 1;

// 2. ดึงข้อมูล Security Logs ตามฟิลเตอร์พร้อมการแบ่งหน้า
if ($db !== null) {
    $where_sql = '';
    $where_params = [];
    $where_types = '';

    if ($filter_event !== '') {
        $where_sql .= " AND event_type = ?";
        $where_params[] = $filter_event;
        $where_types .= "s";
    }

    if ($filter_outcome !== '') {
        $where_sql .= " AND outcome = ?";
        $where_params[] = $filter_outcome;
        $where_types .= "s";
    }

    // นับจำนวนทั้งหมด
    $count_sql = "SELECT COUNT(*) AS total FROM security_logs WHERE 1=1" . $where_sql;
    $c_stmt = $db->prepare($count_sql);
    if ($c_stmt) {
        if (!empty($where_params)) {
            $c_stmt->bind_param($where_types, ...$where_params);
        }
        $c_stmt->execute();
    $total_items = (int) ($c_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $c_stmt->close();
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="security_logs_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($out, ['Log ID', 'Event Type', 'Outcome', 'User ID', 'Target Type', 'Target ID', 'IP Address', 'Details', 'Timestamp']);

    $export_sql = "SELECT log_id, event_type, outcome, user_id, target_type, target_id, ip_address, details, created_at 
                   FROM security_logs 
                   WHERE 1=1" . $where_sql . " ORDER BY created_at DESC";

    $exp_stmt = $db->prepare($export_sql);
    if ($exp_stmt) {
        if (!empty($where_params)) {
            $exp_stmt->bind_param($where_types, ...$where_params);
        }
        $exp_stmt->execute();
        $res = $exp_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [
                $row['log_id'],
                $row['event_type'],
                $row['outcome'],
                $row['user_id'] ?? '-',
                $row['target_type'] ?? '-',
                $row['target_id'] ?? '-',
                $row['ip_address'],
                $row['details'] ?? '',
                $row['created_at'],
            ]);
        }
        $exp_stmt->close();
    }
    fclose($out);
    exit;
}

$total_pages = max(1, (int) ceil($total_items / $items_per_page));
    $current_page = min($current_page, $total_pages);
    $offset = ($current_page - 1) * $items_per_page;

    $sql = "SELECT log_id, event_type, outcome, user_id, target_type, target_id, ip_address, details, created_at 
            FROM security_logs 
            WHERE 1=1" . $where_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";

    $types = $where_types . 'ii';
    $params = array_merge($where_params, [$items_per_page, $offset]);

    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

$active_admin_tab = 'logs';
$admin_page_heading = 'บันทึกความปลอดภัย (Security Audit Logs)';
render_header('บันทึกประวัติความปลอดภัย - Campus Lost & Found System');
?>

<div class="space-y-6">
    <?php require APP_PATH . '/views/admin/components/nav.php'; ?>

    <!-- Unified CRUD Header -->
    <?php
    $crud_title = 'บันทึกความปลอดภัย';
    $crud_subtitle = 'Security Audit Logs';
    $crud_total = $total_items;
    $crud_create_label = null;

    ob_start();
    ?>
    <a
        href="/admin/logs.php?export=csv<?= !empty($_SERVER['QUERY_STRING']) ? '&' . e(http_build_query(array_diff_key($_GET, ['export' => '', 'page' => '']))) : '' ?>"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs sm:text-sm font-medium text-gray-700 shadow-xs hover:bg-gray-50 transition"
    >
        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        ส่งออก Security Logs (CSV)
    </a>
    <?php
    $crud_extra_actions = ob_get_clean();
    require APP_PATH . '/views/admin/components/crud_header.php';
    ?>

    <!-- Unified CRUD Filter -->
    <?php
    $filter_action = '/admin/logs.php';
    $filter_search_value = $filter_event;
    $filter_search_placeholder = 'ค้นหาประเภทเหตุการณ์ เช่น login_success...';
    $filter_has_active_filters = ($filter_event !== '' || $filter_outcome !== '');

    ob_start();
    ?>
    <div class="w-full sm:w-48">
        <label for="outcome" class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ผลลัพธ์ (Outcome)</label>
        <select
            name="outcome"
            id="outcome"
            onchange="this.form.submit()"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="">ทั้งหมด</option>
            <option value="SUCCESS" <?= $filter_outcome === 'SUCCESS' ? 'selected' : '' ?>>สำเร็จ (SUCCESS)</option>
            <option value="FAILURE" <?= $filter_outcome === 'FAILURE' ? 'selected' : '' ?>>ล้มเหลว (FAILURE)</option>
        </select>
    </div>
    <?php
    $filter_fields_html = ob_get_clean();
    require APP_PATH . '/views/admin/components/crud_filter.php';
    ?>

    <!-- ตารางแสดงรายการ Logs -->
    <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
        <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
            <h3 class="text-xs sm:text-sm font-semibold text-gray-900">ประวัติความปลอดภัยทั้งหมด (<?= number_format($total_items) ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs sm:text-sm">
                <thead class="bg-gray-50/70 text-gray-500 font-medium text-xs">
                    <tr>
                        <th class="px-5 py-3">รหัส</th>
                        <th class="px-5 py-3">วันและเวลา</th>
                        <th class="px-5 py-3">เหตุการณ์</th>
                        <th class="px-5 py-3">ผลลัพธ์</th>
                        <th class="px-5 py-3">ผู้ใช้</th>
                        <th class="px-5 py-3">เป้าหมาย</th>
                        <th class="px-5 py-3">IP Address</th>
                        <th class="px-5 py-3">รายละเอียด</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($logs)): ?>
                        <?php
                        $empty_title = 'ไม่พบข้อมูล Security Log';
                        $empty_message = 'ไม่พบบันทึกประวัติความปลอดภัยตามเงื่อนไขที่เลือก';
                        $empty_colspan = 8;
                        require APP_PATH . '/views/admin/components/crud_empty.php';
                        ?>
                    <?php else: ?>
                        <?php foreach ($logs as $row): ?>
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="px-5 py-3.5 font-mono text-gray-400 font-medium">#<?= e((string)$row['log_id']) ?></td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-gray-500 text-xs"><?= e($row['created_at']) ?></td>
                                <td class="px-5 py-3.5 font-mono font-medium text-gray-900 text-xs sm:text-sm"><?= e($row['event_type']) ?></td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <?php if ($row['outcome'] === 'SUCCESS' || $row['outcome'] === 'success'): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            สำเร็จ
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/10">
                                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                            ไม่สำเร็จ
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 text-xs font-medium text-gray-700">
                                    <?= $row['user_id'] !== null ? 'User #' . e((string)$row['user_id']) : '-' ?>
                                </td>
                                <td class="px-5 py-3.5 text-xs text-gray-600">
                                    <?php if (!empty($row['target_type'])): ?>
                                        <span class="font-medium text-gray-700"><?= e($row['target_type']) ?>:</span> 
                                        <?= e((string)$row['target_id']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-xs text-gray-600"><?= e($row['ip_address']) ?></td>
                                <td class="px-5 py-3.5 text-xs text-gray-500 max-w-xs truncate" title="<?= e($row['details'] ?? '') ?>">
                                    <?= !empty($row['details']) ? e($row['details']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php
        $base_url = '/admin/logs.php';
        $query_params = $_GET;
        require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<?php
render_footer();