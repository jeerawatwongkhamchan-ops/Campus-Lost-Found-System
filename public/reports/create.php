<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/report_security.php';
require_once APP_PATH . '/reports/report_data.php';

$values = [
    'report_type' => 'lost', 'title' => '', 'category_id' => '', 'location' => '',
    'event_datetime' => '', 'description' => '', 'private_note' => '', 'image_filename' => '',
];
$errors = [];

try {
    $database = reports_database();
    $user = reports_require_user($database);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        reports_verify_csrf($_POST['csrf_token'] ?? null);
        $validated = reports_validate($database, $_POST);
        $values = $validated['values'];
        $values['image_filename'] = '';
        $errors = $validated['errors'];

        // Process secure image upload if provided
        $upload_result = validate_and_upload_report_image($_FILES['image'] ?? null);
        if (!$upload_result['success']) {
            $errors['image'] = $upload_result['error'];
        }

        if ($errors === []) {
            $imageFilename = $upload_result['filename'];
            $reportId = reports_create($database, $user['user_id'], $values, $imageFilename);
            redirect('/reports/show.php?id=' . $reportId);
        }
    }
    $categories = reports_categories($database);
} catch (Throwable $error) {
    reports_log_failure($error);
}

$action = '/reports/create.php';
$submitLabel = 'บันทึกและส่งรายงาน';
render_header('แจ้งของหายหรือพบของ - Campus Lost & Found System');
?>

<div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 sm:p-8 shadow-sm">
    <div class="border-b border-gray-100 pb-4">
        <a href="/reports/index.php" class="inline-flex items-center text-xs font-medium text-gray-500 hover:text-gray-900 mb-2">
            ← กลับไปหน้ารายการสิ่งของ
        </a>
        <h1 class="text-xl font-bold text-gray-900">แจ้งของหาย / แจ้งพบสิ่งของ</h1>
        <p class="mt-1 text-sm text-gray-500">กรอกข้อมูลรายละเอียดของสิ่งของ เพื่อให้ระบบช่วยประสานงานติดตามส่งคืนเจ้าของได้อย่างรวดเร็ว</p>
    </div>
    <?php require APP_PATH . '/views/reports/components/form.php'; ?>
</div>

<?php render_footer(); ?>
