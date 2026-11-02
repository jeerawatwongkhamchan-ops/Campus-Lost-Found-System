<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';

$admin = require_admin_access();

$connection = get_db_connection();
if ($connection === null) {
    http_response_code(503);
    exit('Database unavailable.');
}

$user_id = (int) $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'การยืนยันความปลอดภัยล้มเหลว กรุณาลองใหม่อีกครั้ง';
        $_SESSION['flash_type'] = 'error';
        redirect('/admin/categories.php');
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    // CREATE CATEGORY
    if ($action === 'create') {
        $category_name = trim((string) ($_POST['category_name'] ?? ''));
        $length = mb_strlen($category_name);

        if ($length < 2 || $length > 100) {
            $_SESSION['flash_message'] = 'ชื่อหมวดหมู่ต้องมีความยาว 2 - 100 ตัวอักษร';
            $_SESSION['flash_type'] = 'error';
        } else {
            $stmt = $connection->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
            $stmt->bind_param('s', $category_name);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists !== null) {
                $_SESSION['flash_message'] = 'หมวดหมู่นี้มีอยู่ในระบบแล้ว';
                $_SESSION['flash_type'] = 'error';
            } else {
                $stmt = $connection->prepare('INSERT INTO categories (category_name, is_active) VALUES (?, 1)');
                $stmt->bind_param('s', $category_name);
                $stmt->execute();
                $new_id = (int) $connection->insert_id;
                $stmt->close();

                log_security_event('category_created', $user_id, 'success', "Created category '{$category_name}'", 'category', (string) $new_id);

                $_SESSION['flash_message'] = "เพิ่มหมวดหมู่ \"{$category_name}\" เรียบร้อยแล้ว";
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/categories.php');
    }

    // RENAME / UPDATE CATEGORY
    elseif ($action === 'rename' || $action === 'update') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $new_name = trim((string) ($_POST['category_name'] ?? ''));
        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
        $length = mb_strlen($new_name);

        if ($category_id <= 0 || $length < 2 || $length > 100) {
            $_SESSION['flash_message'] = 'กรุณาระบุชื่อหมวดหมู่ความยาว 2 - 100 ตัวอักษร';
            $_SESSION['flash_type'] = 'error';
        } else {
            // Check duplicate
            $stmt = $connection->prepare('SELECT category_id FROM categories WHERE category_name = ? AND category_id != ? LIMIT 1');
            $stmt->bind_param('si', $new_name, $category_id);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists !== null) {
                $_SESSION['flash_message'] = 'ชื่อหมวดหมู่นี้มีอยู่แล้วในระบบ';
                $_SESSION['flash_type'] = 'error';
            } else {
                $stmt = $connection->prepare('UPDATE categories SET category_name = ?, is_active = ? WHERE category_id = ?');
                $stmt->bind_param('sii', $new_name, $is_active, $category_id);
                $stmt->execute();
                $stmt->close();

                log_security_event('category_updated', $user_id, 'success', "Updated category #{$category_id} to '{$new_name}' (active: {$is_active})", 'category', (string) $category_id);

                $_SESSION['flash_message'] = 'แก้ไขหมวดหมู่เรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/categories.php');
    }

    // TOGGLE STATUS
    elseif ($action === 'toggle') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            $stmt = $connection->prepare('UPDATE categories SET is_active = 1 - is_active WHERE category_id = ?');
            $stmt->bind_param('i', $category_id);
            $stmt->execute();
            $stmt->close();

            log_security_event('category_status_toggled', $user_id, 'success', "Toggled active state for category #{$category_id}", 'category', (string) $category_id);

            $_SESSION['flash_message'] = 'ปรับสถานะการใช้งานหมวดหมู่เรียบร้อยแล้ว';
            $_SESSION['flash_type'] = 'success';
        }
        redirect('/admin/categories.php');
    }

    // DELETE CATEGORY
    elseif ($action === 'delete') {
        $category_id = (int) ($_POST['category_id'] ?? 0);
        if ($category_id > 0) {
            // Check if reports exist
            $stmt = $connection->prepare('SELECT COUNT(*) AS total FROM item_reports WHERE category_id = ?');
            $stmt->bind_param('i', $category_id);
            $stmt->execute();
            $count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();

            if ($count > 0) {
                $_SESSION['flash_message'] = "ไม่สามารถลบหมวดหมู่นี้ได้เนื่องจากมีรายการสิ่งของใช้งานอยู่ {$count} รายการ กรุณาปิดการใช้งานแทน หรือย้ายรายการสิ่งของออกก่อน";
                $_SESSION['flash_type'] = 'error';
            } else {
                $stmt = $connection->prepare('DELETE FROM categories WHERE category_id = ?');
                $stmt->bind_param('i', $category_id);
                $stmt->execute();
                $stmt->close();

                log_security_event('category_deleted', $user_id, 'success', "Deleted category #{$category_id}", 'category', (string) $category_id);

                $_SESSION['flash_message'] = 'ลบหมวดหมู่เรียบร้อยแล้ว';
                $_SESSION['flash_type'] = 'success';
            }
        }
        redirect('/admin/categories.php');
    }
}

// Search and filter parameters
$search_query = trim((string) ($_GET['q'] ?? ''));
$filter_status = trim((string) ($_GET['status'] ?? ''));

$where_sql = '';
$where_types = '';
$where_params = [];

if ($search_query !== '') {
    $where_sql .= ' AND c.category_name LIKE ?';
    $where_types .= 's';
    $where_params[] = '%' . $search_query . '%';
}

if ($filter_status === '1' || $filter_status === '0') {
    $where_sql .= ' AND c.is_active = ?';
    $where_types .= 'i';
    $where_params[] = (int) $filter_status;
}

$items_per_page = 10;
$current_page = max(1, (int) ($_GET['page'] ?? 1));

// Count total matching
$c_stmt = $connection->prepare('SELECT COUNT(*) AS total FROM categories c WHERE 1=1' . $where_sql);
if ($where_params !== []) {
    $c_stmt->bind_param($where_types, ...$where_params);
}
$c_stmt->execute();
$total_items = (int) ($c_stmt->get_result()->fetch_assoc()['total'] ?? 0);
$c_stmt->close();

$total_pages = max(1, (int) ceil($total_items / $items_per_page));
$current_page = min($current_page, $total_pages);
$offset = ($current_page - 1) * $items_per_page;

// Fetch paginated categories
$sql = 'SELECT c.category_id, c.category_name, c.is_active, c.created_at,
               COUNT(r.report_id) AS total_reports
        FROM categories c
        LEFT JOIN item_reports r ON r.category_id = c.category_id
        WHERE 1=1' . $where_sql . '
        GROUP BY c.category_id, c.category_name, c.is_active, c.created_at
        ORDER BY c.category_id ASC
        LIMIT ? OFFSET ?';
$types = $where_types . 'ii';
$params = array_merge($where_params, [$items_per_page, $offset]);

$stmt = $connection->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$active_admin_tab = 'categories';
$admin_page_heading = 'จัดการหมวดหมู่สิ่งของ (Categories)';
render_header('จัดการหมวดหมู่สิ่งของ - Campus Lost & Found System');
?>

<div class="space-y-6">
    <?php require APP_PATH . '/views/admin/components/nav.php'; ?>

    <?php if ($flash_message !== null): ?>
        <div class="alert-animate rounded-2xl border p-4 text-xs sm:text-sm font-medium shadow-xs flex items-center gap-3 <?= $flash_type === 'success' ? 'border-emerald-200/90 bg-emerald-50/90 text-emerald-900' : 'border-rose-200/90 bg-rose-50/90 text-rose-900' ?>">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl <?= $flash_type === 'success' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                <?php if ($flash_type === 'success'): ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                <?php else: ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01" />
                    </svg>
                <?php endif; ?>
            </div>
            <div class="flex-1"><?= e($flash_message) ?></div>
        </div>
    <?php endif; ?>

    <!-- Unified CRUD Header -->
    <?php
    $crud_title = 'จัดการหมวดหมู่สิ่งของ';
    $crud_subtitle = 'Categories Management';
    $crud_total = $total_items;
    $crud_create_label = 'เพิ่มหมวดหมู่ใหม่';
    $crud_create_modal_id = 'create-category-modal';
    require APP_PATH . '/views/admin/components/crud_header.php';
    ?>

    <!-- Unified CRUD Filter -->
    <?php
    $filter_action = '/admin/categories.php';
    $filter_search_value = $search_query;
    $filter_search_placeholder = 'ค้นหาชื่อหมวดหมู่...';
    $filter_has_active_filters = ($search_query !== '' || $filter_status !== '');
    ob_start();
    ?>
    <div class="w-full sm:w-52">
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะ</label>
        <select
            name="status"
            onchange="this.form.submit()"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs sm:text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="">ทั้งหมด</option>
            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>เปิดใช้งาน (Active)</option>
            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>ปิดใช้งาน (Inactive)</option>
        </select>
    </div>
    <?php
    $filter_fields_html = ob_get_clean();
    require APP_PATH . '/views/admin/components/crud_filter.php';
    ?>

    <!-- Unified CRUD Table -->
    <div class="overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-xs">
        <div class="border-b border-gray-100 px-5 py-3.5 flex items-center justify-between">
            <h3 class="text-xs sm:text-sm font-semibold text-gray-900">
                รายการหมวดหมู่ (<?= number_format($total_items) ?>)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-left text-xs sm:text-sm">
                <thead class="bg-gray-50/70 text-gray-500 font-medium text-xs">
                    <tr>
                        <th class="px-5 py-3 w-20">รหัส</th>
                        <th class="px-5 py-3">ชื่อหมวดหมู่</th>
                        <th class="px-5 py-3">จำนวนรายงานที่ใช้</th>
                        <th class="px-5 py-3">สถานะ</th>
                        <th class="px-5 py-3">วันที่สร้าง</th>
                        <th class="px-5 py-3 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if ($categories === []): ?>
                        <?php
                        $empty_title = 'ไม่พบหมวดหมู่สิ่งของ';
                        $empty_message = 'ยังไม่มีหมวดหมู่ที่ตรงกับเงื่อนไข หรือยังไม่มีการสร้างหมวดหมู่ในระบบ';
                        $empty_colspan = 6;
                        require APP_PATH . '/views/admin/components/crud_empty.php';
                        ?>
                    <?php endif; ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="transition hover:bg-gray-50/60">
                            <td class="px-5 py-3.5 font-mono text-gray-400 font-medium">#<?= (int) $cat['category_id'] ?></td>
                            <td class="px-5 py-3.5 font-semibold text-gray-900 text-sm">
                                <?= e($cat['category_name']) ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <a href="/admin/reports.php?category_id=<?= (int) $cat['category_id'] ?>" class="font-medium text-gray-700 hover:text-gray-900 transition">
                                    <span><?= (int) $cat['total_reports'] ?></span> รายการ
                                </a>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <?php if ((int) $cat['is_active'] === 1): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/10">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        เปิดใช้งาน
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        ปิดใช้งาน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-gray-500 text-xs"><?= e($cat['created_at']) ?></td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-1.5">
                                <!-- ปุ่มแก้ไข (เปิด Edit Modal) -->
                                <button
                                    type="button"
                                    data-id="<?= (int) $cat['category_id'] ?>"
                                    data-name="<?= e($cat['category_name']) ?>"
                                    data-active="<?= (int) $cat['is_active'] ?>"
                                    onclick="editCategoryFromBtn(this)"
                                    class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
                                    title="แก้ไขข้อมูลหมวดหมู่"
                                >
                                    แก้ไข
                                </button>


                                <!-- ปุ่มลบ (เปิด Unified Delete Modal) -->
                                <button
                                    type="button"
                                    data-id="<?= (int) $cat['category_id'] ?>"
                                    data-name="<?= e($cat['category_name']) ?>"
                                    data-reports="<?= (int) $cat['total_reports'] ?>"
                                    onclick="confirmDeleteCategoryFromBtn(this)"
                                    class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-100 transition shadow-xs cursor-pointer"
                                    title="ลบหมวดหมู่"
                                >
                                    ลบ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php
    $base_url = '/admin/categories.php';
    $query_params = $_GET;
    require APP_PATH . '/views/components/pagination.php';
    ?>
</div>

<!-- Modal สร้างหมวดหมู่ใหม่ -->
<?php
$modal_id = 'create-category-modal';
$modal_title = 'เพิ่มหมวดหมู่สิ่งของใหม่';
$modal_subtitle = 'ระบุชื่อหมวดหมู่ที่ต้องการใช้สำหรับการจัดกลุ่มของหายและของที่พบ';
ob_start();
?>
<form method="POST" action="/admin/categories.php" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <div>
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อหมวดหมู่ <span class="text-rose-500">*</span></label>
        <input
            type="text"
            name="category_name"
            required
            minlength="2"
            maxlength="100"
            placeholder="เช่น อุปกรณ์กีฬา, ยานพาหนะ, เครื่องดนตรี"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 focus:border-gray-900 focus:outline-none transition"
        >
        <p class="mt-1 text-xs text-gray-500">ความยาว 2 - 100 ตัวอักษร และต้องไม่ซ้ำกับหมวดหมู่ที่มีอยู่แล้ว</p>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('create-category-modal')"
            class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
        >
            ยกเลิก
        </button>
        <button
            type="submit"
            class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
        >
            บันทึกหมวดหมู่
        </button>
    </div>
</form>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Modal แก้ไขหมวดหมู่ -->
<?php
$modal_id = 'edit-category-modal';
$modal_title = 'แก้ไขข้อมูลหมวดหมู่';
$modal_subtitle = 'ปรับปรุงชื่อและสถานะการเปิดใช้งานหมวดหมู่';
ob_start();
?>
<form method="POST" action="/admin/categories.php" class="space-y-4">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="category_id" id="edit-category-id" value="">

    <div>
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">ชื่อหมวดหมู่ <span class="text-rose-500">*</span></label>
        <input
            type="text"
            name="category_name"
            id="edit-category-name"
            required
            minlength="2"
            maxlength="100"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
    </div>

    <div>
        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1.5">สถานะการใช้งาน</label>
        <select
            name="is_active"
            id="edit-category-active"
            class="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:border-gray-900 focus:outline-none transition"
        >
            <option value="1">เปิดใช้งาน (Active)</option>
            <option value="0">ปิดใช้งาน (Inactive)</option>
        </select>
    </div>

    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
        <button
            type="button"
            onclick="closeAdminModal('edit-category-modal')"
            class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs sm:text-sm font-medium text-gray-700 hover:bg-gray-50 transition shadow-xs cursor-pointer"
        >
            ยกเลิก
        </button>
        <button
            type="submit"
            class="rounded-lg bg-gray-900 px-4 py-2 text-xs sm:text-sm font-medium text-white shadow-xs hover:bg-gray-800 transition cursor-pointer"
        >
            บันทึกการแก้ไข
        </button>
    </div>
</form>
<?php
$modal_content = ob_get_clean();
require APP_PATH . '/views/admin/components/crud_modal.php';
?>

<!-- Shared Delete Confirmation Modal -->
<?php require APP_PATH . '/views/admin/components/crud_delete_modal.php'; ?>

<script>
function editCategoryFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const isActive = btn.getAttribute('data-active');
    editCategory(id, name, isActive);
}

function editCategory(id, name, isActive) {
    const idEl = document.getElementById('edit-category-id');
    const nameEl = document.getElementById('edit-category-name');
    const activeEl = document.getElementById('edit-category-active');

    if (idEl) idEl.value = id;
    if (nameEl) nameEl.value = name;
    if (activeEl) activeEl.value = String(isActive);

    if (typeof openAdminModal === 'function') {
        openAdminModal('edit-category-modal');
    } else {
        const modal = document.getElementById('edit-category-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.style.display = 'block';
            document.body.classList.add('overflow-hidden');
            if (nameEl) {
                setTimeout(() => nameEl.focus(), 50);
            }
        }
    }
}

function confirmDeleteCategoryFromBtn(btn) {
    if (!btn) return;
    const id = btn.getAttribute('data-id');
    const name = btn.getAttribute('data-name');
    const reportsCount = parseInt(btn.getAttribute('data-reports') || '0', 10);
    confirmDeleteCategory(id, name, reportsCount);
}

function confirmDeleteCategory(id, name, reportsCount) {
    let warning = '';
    if (reportsCount > 0) {
        warning = 'หมวดหมู่นี้มีรายงานผูกอยู่ ' + reportsCount + ' รายการ ระบบจะไม่อนุญาตให้ลบจนกว่าจะย้ายรายการออก หรือแนะนำให้ปิดการใช้งานแทน';
    }

    if (typeof openAdminDeleteModal === 'function') {
        openAdminDeleteModal({
            url: '/admin/categories.php',
            action: 'delete',
            idName: 'category_id',
            idValue: id,
            title: 'ยืนยันลบหมวดหมู่ "' + name + '"',
            message: 'คุณต้องการลบหมวดหมู่นี้ออกจากระบบอย่างถาวรใช่หรือไม่?',
            warning: warning,
            submitLabel: 'ยืนยันลบหมวดหมู่'
        });
    } else {
        if (confirm('คุณต้องการลบหมวดหมู่ "' + name + '" ออกจากระบบอย่างถาวรใช่หรือไม่?' + (warning ? '\n\n' + warning : ''))) {
            const form = document.getElementById('admin-delete-form');
            if (form) {
                const actionInput = document.getElementById('delete-form-action');
                const idInput = document.getElementById('delete-form-id');
                if (actionInput) actionInput.value = 'delete';
                if (idInput) {
                    idInput.name = 'category_id';
                    idInput.value = id;
                }
                form.submit();
            }
        }
    }
}
</script>

<?php render_footer(); ?>
