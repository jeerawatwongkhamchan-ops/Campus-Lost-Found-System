<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';
require_once APP_PATH . '/reports/report_data.php';

$reportId = reports_positive_id($_GET['id'] ?? null);
if ($reportId === null) {
    reports_error(400, 'Invalid report ID.');
}

try {
    $database = reports_database();
    $user = reports_require_user($database);
    $report = reports_find($database, $reportId);
    if ($report === null) {
        reports_error(404, 'Report not found.');
    }
    if (!reports_can_manage($report, $user)) {
        reports_error(403, 'Access denied.');
    }

    $values = [
        'report_type' => $report['report_type'],
        'title' => $report['title'],
        'category_id' => (string) $report['category_id'],
        'location' => $report['location'],
        'event_datetime' => str_replace(' ', 'T', substr($report['event_datetime'], 0, 16)),
        'description' => $report['description'],
        'private_note' => $report['private_note'] ?? '',
        'image_filename' => $report['image_filename'] ?? '',
    ];
    $errors = [];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        reports_verify_csrf($_POST['csrf_token'] ?? null);
        $validated = reports_validate($database, $_POST);
        $values = $validated['values'];
        $values['image_filename'] = $report['image_filename'] ?? '';
        $errors = $validated['errors'];

        $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';
        $current_image = $report['image_filename'] ?? null;
        $new_image = $current_image;
        $update_image = false;

        if ($remove_image) {
            $new_image = null;
            $update_image = true;
            if (!empty($current_image)) {
                delete_report_image($current_image);
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = validate_and_upload_report_image($_FILES['image'], $current_image);
            if (!$upload_result['success']) {
                $errors['image'] = $upload_result['error'];
            } else {
                $new_image = $upload_result['filename'];
                $update_image = true;
            }
        }

        if ($errors === []) {
            reports_update($database, $reportId, $user['user_id'], $values, $new_image, $update_image);
            redirect('/reports/show.php?id=' . $reportId);
        }
    }
    $categories = reports_categories($database);
} catch (Throwable $error) {
    reports_log_failure($error);
}

$action = '/reports/edit.php?id=' . $reportId;
$submitLabel = 'บันทึกการแก้ไข';
render_header('แก้ไขรายงาน - Campus Lost & Found System');
?>

<div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 sm:p-8 shadow-sm">
    <div class="border-b border-gray-100 pb-4">
        <a href="/reports/show.php?id=<?= $reportId ?>" class="inline-flex items-center text-xs font-medium text-gray-500 hover:text-gray-900 mb-2">
            ← กลับไปหน้ารายละเอียดสิ่งของ
        </a>
        <h1 class="text-xl font-bold text-gray-900">แก้ไขข้อมูลรายงาน</h1>
        <p class="mt-1 text-sm text-gray-500">ปรับปรุงข้อมูลสิ่งของเพื่อให้ตรงกับความเป็นจริงมากที่สุด</p>
    </div>
    <?php require APP_PATH . '/views/reports/components/form.php'; ?>
</div>

<?php render_footer(); ?>
