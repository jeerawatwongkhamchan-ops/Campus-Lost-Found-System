<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/services/report_search.php';

$filters = normalize_report_filters($_GET);
if ($filters['my'] && is_authenticated()) {
    $filters['reporter_id'] = (int) $_SESSION['user_id'];
}
$has_active_filters = $filters['q'] !== ''
    || $filters['report_type'] !== ''
    || $filters['category_id'] !== null
    || $filters['status'] !== ''
    || $filters['my'];

$categories = [];
$reports = [];
$total_reports = 0;
$current_page = 1;
$total_pages = 1;
$database_available = false;
$connection = get_db_connection();

if ($connection !== null) {
    $categories = get_active_report_categories($connection);
    $total_reports = count_item_reports($connection, $filters);
    $total_pages = max(1, (int) ceil($total_reports / REPORTS_PER_PAGE));
    $current_page = min($filters['page'], $total_pages);
    $filters['page'] = $current_page;
    $reports = search_item_reports(
        $connection,
        $filters,
        REPORTS_PER_PAGE,
        ($current_page - 1) * REPORTS_PER_PAGE
    );
    $database_available = true;
}

render_header('รายการสิ่งของหายและพบ - Campus Lost & Found System');
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">รายการสิ่งของหายและพบ</h1>
            <p class="mt-1 text-sm text-gray-500">
                ค้นหาสิ่งของที่ลืมไว้ หรือช่วยส่งต่อข้อมูลของที่พบเพื่อส่งคืนเจ้าของ
            </p>
        </div>
        <div class="shrink-0">
            <a href="/reports/create.php" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-xs transition hover:bg-gray-800">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                แจ้งของหาย / แจ้งพบของ
            </a>
        </div>
    </div>

    <?php if (!$database_available): ?>
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">
            ระบบฐานข้อมูลขัดข้องชั่วคราว ไม่สามารถแสดงรายการสิ่งของได้ในขณะนี้ กรุณาลองใหม่อีกครั้งในภายหลัง
        </div>
    <?php else: ?>
        <?php require APP_PATH . '/views/components/report_filters.php'; ?>
        <?php require APP_PATH . '/views/components/report_results_table.php'; ?>
        <?php require APP_PATH . '/views/components/report_pagination.php'; ?>
    <?php endif; ?>
</div>

<?php if (!is_authenticated()): ?>
    <!-- Welcome / Guest Auth Modal -->
    <div id="guest-auth-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <!-- Backdrop -->
        <div id="guest-modal-backdrop" class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <!-- Modal Card -->
            <div class="relative transform overflow-hidden rounded-2xl bg-white p-6 text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-md sm:p-7 border border-gray-100">
                <!-- Close Button -->
                <button type="button" id="close-modal-btn" class="absolute right-4 top-4 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition" aria-label="ปิดหน้าต่าง">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Icon -->
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 sm:mx-0">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <!-- Text Content -->
                <div class="mt-4 text-center sm:text-left">
                    <h3 id="modal-title" class="text-lg font-bold text-gray-900">
                        ยินดีต้อนรับสู่ Campus Lost &amp; Found
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">
                        คุณสามารถค้นหาและดูรายการสิ่งของได้ทันที แต่หากต้องการ<span class="font-medium text-gray-900">แจ้งของหาย แจ้งพบของ</span> หรือ<span class="font-medium text-gray-900">ยื่นขอรับของคืน</span> จำเป็นต้องเข้าสู่ระบบก่อนใช้งาน
                    </p>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex flex-col gap-2.5">
                    <a href="/auth/login.php" class="flex w-full items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800">
                        เข้าสู่ระบบ
                    </a>
                    <a href="/auth/register.php" class="flex w-full items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                        สมัครสมาชิกใหม่
                    </a>
                    <button type="button" id="dismiss-modal-btn" class="mt-1 text-center text-xs font-medium text-gray-500 hover:text-gray-800 transition">
                        เข้าดูรายการสิ่งของก่อน
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('guest-auth-modal');
            const backdrop = document.getElementById('guest-modal-backdrop');
            const closeBtn = document.getElementById('close-modal-btn');
            const dismissBtn = document.getElementById('dismiss-modal-btn');

            if (!modal) return;

            const hasSeen = sessionStorage.getItem('lost_found_guest_prompt_seen');
            if (!hasSeen) {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                sessionStorage.setItem('lost_found_guest_prompt_seen', '1');
            }

            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (dismissBtn) dismissBtn.addEventListener('click', closeModal);
            if (backdrop) backdrop.addEventListener('click', closeModal);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        });
    </script>
<?php endif; ?>

<?php render_footer(); ?>
