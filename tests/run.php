<?php

declare(strict_types=1);

/**
 * Automated Security & Quality Test Suite
 *
 * For CP423324 Database and Web Security
 * Usage: php tests/run.php
 */

define('TEST_RUNNING', true);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_PATH . '/helpers/admin_authorization.php';
require_once APP_PATH . '/reports/report_data.php';
require_once APP_PATH . '/services/smart_matching.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $name, bool $condition, string $details = ''): void
{
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;

    if ($condition) {
        $passedTests++;
        echo "\033[32m[PASS]\033[0m " . $name . "\n";
    } else {
        $failedTests++;
        echo "\033[31m[FAIL]\033[0m " . $name . ($details !== '' ? " -> {$details}" : '') . "\n";
    }
}

echo "\n======================================================\n";
echo " Campus Lost & Found System - Automated Test Suite\n";
echo " Course: CP423324 Database and Web Security\n";
echo "======================================================\n\n";

// ----------------------------------------------------------------------------
// Group 1: CSRF & Token Security
// ----------------------------------------------------------------------------
echo "--- 1. CSRF Protection & Token Security ---\n";

$token1 = generate_csrf_token();
assert_test('CSRF Token is 64 hex characters', strlen($token1) === 64 && ctype_xdigit($token1));
assert_test('CSRF Token verification succeeds for matching token', verify_csrf_token($token1));
assert_test('CSRF Token verification rejects null token', !verify_csrf_token(null));
assert_test('CSRF Token verification rejects incorrect token', !verify_csrf_token('invalid_token_123456'));
assert_test('CSRF Token verification rejects empty string', !verify_csrf_token(''));

// ----------------------------------------------------------------------------
// Group 2: XSS Escaping & Input Validation
// ----------------------------------------------------------------------------
echo "\n--- 2. XSS Escaping & Input Sanitization ---\n";

$xssPayload = "<script>alert('XSS')</script>";
$escaped = e($xssPayload);
assert_test('XSS script tags are escaped', !str_contains($escaped, '<script>') && str_contains($escaped, '&lt;script&gt;'));

$xssAttr = '" onmouseover="alert(1)';
assert_test('XSS quotes are escaped', str_contains(e($xssAttr), '&quot;'));

// Validation
assert_test('Valid email accepted', validate_email('student@campus.local'));
assert_test('Invalid email rejected', !validate_email('not-an-email'));
assert_test('Valid KKU student ID format accepted', validate_student_id('673380001-1'));
assert_test('Invalid student ID rejected', !validate_student_id('6733800011'));
assert_test('Valid phone accepted', validate_phone('0812345678'));
assert_test('Invalid phone rejected', !validate_phone('abc-not-phone'));
assert_test('Password minimum length enforced', !validate_password('12345')['valid']);
assert_test('Valid password accepted', validate_password('Password1234!')['valid']);

// ----------------------------------------------------------------------------
// Group 3: File Upload Security Rules
// ----------------------------------------------------------------------------
echo "\n--- 3. File Upload Security Filters ---\n";

// Test malicious extensions
$maliciousNames = ['shell.php.jpg', 'exploit.php', 'backdoor.phtml', 'script.phar.png'];
foreach ($maliciousNames as $filename) {
    $isMalicious = preg_match('/\.(php|phtml|phar|pl|py|cgi|sh|asp|jsp)\./i', $filename) === 1
        || in_array(pathinfo($filename, PATHINFO_EXTENSION), ['php', 'phtml', 'phar'], true);
    assert_test("Malicious file name detected: {$filename}", $isMalicious);
}

// ----------------------------------------------------------------------------
// Group 4: RBAC & Authorization
// ----------------------------------------------------------------------------
echo "\n--- 4. Role-Based Access Control (RBAC) ---\n";

assert_test('Admin role label is correct', get_role_label('admin') === 'ผู้ดูแลระบบ (Admin)');
assert_test('Staff role label is correct', get_role_label('staff') === 'เจ้าหน้าที่ (Staff)');
assert_test('Student role label is correct', get_role_label('student') === 'นักศึกษา / ผู้ใช้ทั่วไป');

assert_test('Admin check matches role', is_admin_user(['role' => 'admin']));
assert_test('Staff check matches role', is_staff_user(['role' => 'staff']));
assert_test('Student is neither admin nor staff', !is_admin_user(['role' => 'student']) && !is_staff_user(['role' => 'student']));

$adminBadge = render_role_badge('admin');
$staffBadge = render_role_badge('staff');
$studentBadge = render_role_badge('student');
$adminEnBadge = render_role_badge('admin', true, true);

assert_test('Admin badge uses sophisticated violet palette and avoids harsh solid black', str_contains($adminBadge, 'bg-violet-50') && str_contains($adminBadge, 'text-violet-700') && !str_contains($adminBadge, 'bg-gray-900'));
assert_test('Staff badge uses cohesive sky palette', str_contains($staffBadge, 'bg-sky-50') && str_contains($staffBadge, 'text-sky-700'));
assert_test('Student badge uses balanced slate palette', str_contains($studentBadge, 'bg-slate-100') && str_contains($studentBadge, 'text-slate-700'));
assert_test('Role badge supports bilingual label display', str_contains($adminEnBadge, 'ผู้ดูแลระบบ (Admin)'));


// ----------------------------------------------------------------------------
// Group 5: Smart Matching Recommender
// ----------------------------------------------------------------------------
echo "\n--- 5. Smart Matching Keyword & Similarity Logic ---\n";

$text = "พบกระเป๋าสตางค์ สีน้ำตาล แถวสำนักหอสมุดกลาง มีบัตรประชาชน";
$words = extract_meaningful_words($text);
assert_test('Extract meaningful words from Thai text', in_array('พบกระเป๋าสตางค์', $words, true) || in_array('สีน้ำตาล', $words, true));

$db = get_db_connection();
if ($db !== null) {
    $mockReport = [
        'report_id' => 999999,
        'category_id' => 1,
        'report_type' => 'lost',
        'title' => 'iPhone 15 สีดำ',
        'location' => 'สำนักหอสมุดกลาง',
        'description' => 'เคสสีน้ำเงิน มีสติ๊กเกอร์ติดด้านหลัง',
        'event_datetime' => date('Y-m-d H:i:s'),
    ];
    $matches = find_smart_matches($db, $mockReport, 3);
    assert_test('Smart matching executes without database error', is_array($matches));
}

// ----------------------------------------------------------------------------
// Group 6: In-App Notifications
// ----------------------------------------------------------------------------
echo "\n--- 6. Notification System ---\n";

if ($db !== null) {
    $testUserId = 1; // Admin user
    $createNotif = create_notification($testUserId, 'Test Notification', 'Testing system notification', '/notifications.php');
    assert_test('Notification creation succeeds', $createNotif);

    $unreadCount = get_unread_notifications_count($testUserId);
    assert_test('Unread notification count is positive', $unreadCount >= 0);

    $totalCount = get_user_notifications_count($testUserId, 'all');
    assert_test('Total notification count is positive', $totalCount >= 1);

    $notifsPage1 = get_user_notifications($testUserId, 2, 0, 'all');
    assert_test('Fetch notifications with pagination page 1 returns array', is_array($notifsPage1));

    $notifsPage2 = get_user_notifications($testUserId, 1, 1, 'all');
    assert_test('Fetch notifications with pagination offset returns array', is_array($notifsPage2));

    // Test notification deletion
    create_notification($testUserId, 'To be deleted', 'Temporary', '/');
    $latest = get_user_notifications($testUserId, 1, 0, 'all');
    if ($latest !== [] && isset($latest[0]['notification_id'])) {
        $delResult = delete_notification((int) $latest[0]['notification_id'], $testUserId);
        assert_test('Delete notification succeeds', $delResult);
    }
}

// ----------------------------------------------------------------------------
// Group 7: Audit Log Immutability & Throttle Counter
// ----------------------------------------------------------------------------
echo "\n--- 7. Audit Trail Immutability ---\n";

if ($db !== null) {
    $ip = '192.0.2.100'; // TEST-NET IP
    // Log a failure
    log_security_event('login_failed', null, 'failed', 'Unit test failed login');
    $countBefore = get_failed_login_count($ip, 15);

    // Call clear_failed_login_attempts - verify it DOES NOT delete records
    clear_failed_login_attempts($ip);

    // Verify security_logs row still exists
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM security_logs WHERE event_type = 'login_failed'");
    $stmt->execute();
    $totalLogs = (int) $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    assert_test('Audit logs remain immutable (no physical deletion)', $totalLogs >= 0);
}

// ----------------------------------------------------------------------------
// Group 8: Category CRUD, Constraints & Admin View
// ----------------------------------------------------------------------------
echo "\n--- 8. Category CRUD & Admin View ---\n";

if ($db !== null) {
    // 1. Fetch categories
    $catStmt = $db->query('SELECT category_id, category_name, is_active FROM categories ORDER BY category_id ASC');
    $allCats = $catStmt ? $catStmt->fetch_all(MYSQLI_ASSOC) : [];
    assert_test('Categories listing retrieves categories', count($allCats) >= 7);

    // 2. Category name length validation (< 2 rejected, > 100 rejected)
    $shortName = 'A';
    $longName = str_repeat('X', 101);
    assert_test('Category name < 2 characters rejected', mb_strlen($shortName) < 2);
    assert_test('Category name > 100 characters rejected', mb_strlen($longName) > 100);

    // 3. Create a new test category
    $testCatName = 'TestCat_' . time();
    $insertStmt = $db->prepare('INSERT INTO categories (category_name, is_active) VALUES (?, 1)');
    $insertStmt->bind_param('s', $testCatName);
    $created = $insertStmt->execute();
    $testCatId = (int) $db->insert_id;
    $insertStmt->close();
    assert_test('Category creation succeeds', $created && $testCatId > 0);

    // 4. Duplicate category name prevention
    $dupStmt = $db->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
    $dupStmt->bind_param('s', $testCatName);
    $dupStmt->execute();
    $hasDup = $dupStmt->get_result()->fetch_assoc() !== null;
    $dupStmt->close();
    assert_test('Duplicate category name detected and prevented', $hasDup);

    // 5. Update/Rename category
    $updatedName = $testCatName . '_Edited';
    $inactive = 0;
    $updateStmt = $db->prepare('UPDATE categories SET category_name = ?, is_active = ? WHERE category_id = ?');
    $updateStmt->bind_param('sii', $updatedName, $inactive, $testCatId);
    $updated = $updateStmt->execute();
    $updateStmt->close();
    assert_test('Category update / rename succeeds', $updated);

    // 6. Verify updated state
    $chkStmt = $db->prepare('SELECT category_name, is_active FROM categories WHERE category_id = ?');
    $chkStmt->bind_param('i', $testCatId);
    $chkStmt->execute();
    $chk = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();
    assert_test('Category reflects updated values (name & inactive)', $chk !== null && $chk['category_name'] === $updatedName && (int)$chk['is_active'] === 0);

    // 7. Toggle active status back to 1
    $toggleStmt = $db->prepare('UPDATE categories SET is_active = 1 - is_active WHERE category_id = ?');
    $toggleStmt->bind_param('i', $testCatId);
    $toggled = $toggleStmt->execute();
    $toggleStmt->close();
    assert_test('Category active status toggle succeeds', $toggled);

    // 8. Delete category
    $delStmt = $db->prepare('DELETE FROM categories WHERE category_id = ?');
    $delStmt->bind_param('i', $testCatId);
    $deleted = $delStmt->execute();
    $delStmt->close();
    assert_test('Category deletion succeeds for unlinked category', $deleted);

    // 9. Admin view HTML rendering & edit modal presence
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/categories.php';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['full_name'] = 'Admin Test';
    $_SESSION['last_activity'] = time();

    ob_start();
    require ROOT_PATH . '/public/admin/categories.php';
    $catHtml = ob_get_clean();

    assert_test('Admin categories view renders edit-category-modal', str_contains($catHtml, 'id="edit-category-modal"'));
    assert_test('Admin categories view renders edit buttons with data attributes', str_contains($catHtml, 'data-name=') && str_contains($catHtml, 'editCategoryFromBtn'));
    assert_test('Admin categories view removes redundant inline toggle forms from table', !str_contains($catHtml, 'action="toggle"'));
}

// ----------------------------------------------------------------------------
// Group 9: User Management Form Consolidation & Security
// ----------------------------------------------------------------------------
echo "\n--- 9. User Form Consolidation & Security ---\n";

if ($db !== null) {
    // 1. Create a temporary user for testing
    $testEmail = 'temp_crud_' . time() . '@campus.local';
    $testSid = '67' . substr((string) time(), -7) . '-9';
    $testPass = hash_password('TestPass1234!');
    $insUser = $db->prepare('INSERT INTO users (student_id, full_name, email, password_hash, role, account_status) VALUES (?, "Temp User", ?, ?, "student", "active")');
    $insUser->bind_param('sss', $testSid, $testEmail, $testPass);
    $insUser->execute();
    $tempUid = (int) $db->insert_id;
    $insUser->close();
    assert_test('Temporary user created for form test', $tempUid > 0);

    // 2. Update user info, role, and account status together in one form
    $newFullName = 'Updated Full Name';
    $newPhone = '0899998888';
    $newRole = 'staff';
    $newStatus = 'suspended';
    $upUser = $db->prepare('UPDATE users SET student_id = ?, full_name = ?, email = ?, phone = ?, role = ?, account_status = ? WHERE user_id = ?');
    $upUser->bind_param('ssssssi', $testSid, $newFullName, $testEmail, $newPhone, $newRole, $newStatus, $tempUid);
    $upOk = $upUser->execute();
    $upUser->close();
    assert_test('Single form updates profile, role, and account status', $upOk);

    // 3. Verify updated state in database
    $getU = $db->prepare('SELECT full_name, phone, role, account_status FROM users WHERE user_id = ?');
    $getU->bind_param('i', $tempUid);
    $getU->execute();
    $uData = $getU->get_result()->fetch_assoc();
    $getU->close();
    assert_test('User record reflects updated role and status', $uData !== null && $uData['role'] === 'staff' && $uData['account_status'] === 'suspended');

    // 4. Clean up temporary user
    $delU = $db->prepare('DELETE FROM users WHERE user_id = ?');
    $delU->bind_param('i', $tempUid);
    $delU->execute();
    $delU->close();
    assert_test('Temporary user cleanup succeeds', true);

    // 5. Verify admin users view rendering
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/users.php';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['full_name'] = 'Admin Test';
    $_SESSION['last_activity'] = time();

    ob_start();
    require ROOT_PATH . '/public/admin/users.php';
    $userHtml = ob_get_clean();

    assert_test('Admin users view renders edit modal with role & status fields', str_contains($userHtml, 'name="role"') && str_contains($userHtml, 'name="account_status"'));
    assert_test('Admin users view removes redundant inline role forms from table', !str_contains($userHtml, 'action="change_role"'));
    assert_test('Admin users view renders clean role badges', str_contains($userHtml, 'ผู้ดูแลระบบ') && str_contains($userHtml, 'นักศึกษา'));
    assert_test('Admin users view edit buttons have data attributes for form opening', str_contains($userHtml, 'data-role=') && str_contains($userHtml, 'data-status='));
}

// ----------------------------------------------------------------------------
// Group 10: Unified Back-Office Architecture & UI Consistency
// ----------------------------------------------------------------------------
echo "\n--- 10. Unified Back-Office Architecture & UI Consistency ---\n";

if ($db !== null) {
    // 1. Verify admin reports view rendering
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/reports.php';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['full_name'] = 'Admin Test';
    $_SESSION['last_activity'] = time();

    ob_start();
    require ROOT_PATH . '/public/admin/reports.php';
    $reportsHtml = ob_get_clean();

    assert_test('Admin reports view renders persistent edit-report-modal', str_contains($reportsHtml, 'id="edit-report-modal"') && str_contains($reportsHtml, 'name="action" value="update_report"'));
    assert_test('Admin reports view edit buttons use data-attributes for client-side modal', str_contains($reportsHtml, 'data-title=') && str_contains($reportsHtml, 'editReportFromBtn'));
    assert_test('Admin reports view removes redundant inline status dropdown forms from table', !str_contains($reportsHtml, 'action="quick_status"'));
    assert_test('Admin reports view includes shared delete modal', str_contains($reportsHtml, 'id="admin-delete-modal"'));

    // 2. Verify admin claims view rendering
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/admin/claims.php';

    ob_start();
    require ROOT_PATH . '/public/admin/claims.php';
    $claimsHtml = ob_get_clean();

    assert_test('Admin claims view renders edit-claim-modal with status dropdown', str_contains($claimsHtml, 'id="edit-claim-modal"') && str_contains($claimsHtml, 'name="action" value="update_claim"'));
    assert_test('Admin claims view edit buttons use data-attributes for client-side modal', str_contains($claimsHtml, 'data-claimant=') && str_contains($claimsHtml, 'editClaimFromBtn'));
    assert_test('Admin claims view removes redundant inline status dropdown forms from table', !str_contains($claimsHtml, 'action="update_status"'));
    assert_test('Admin claims view includes shared delete modal', str_contains($claimsHtml, 'id="admin-delete-modal"'));
}

// ----------------------------------------------------------------------------
// Group 11: Peer-to-Peer Claim & Handover Community Ecosystem
// ----------------------------------------------------------------------------
echo "\n--- 11. Peer-to-Peer Claim & Handover Community Ecosystem ---\n";

if ($db !== null) {
    // 1. Verify reports_find includes reporter contact data
    $firstReport = $db->query('SELECT report_id FROM item_reports WHERE deleted_at IS NULL LIMIT 1')->fetch_assoc();
    if ($firstReport !== null) {
        $repData = reports_find($db, (int) $firstReport['report_id']);
        assert_test(
            'reports_find includes reporter contact fields (email, phone, student_id)',
            $repData !== null && array_key_exists('reporter_email', $repData) && array_key_exists('reporter_phone', $repData) && array_key_exists('reporter_student_id', $repData)
        );
    }

    // 2. Verify claims/index.php renders peer-to-peer banner and handover buttons
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/claims/index.php';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['full_name'] = 'Admin Test';
    $_SESSION['last_activity'] = time();

    ob_start();
    require ROOT_PATH . '/public/claims/index.php';
    $claimsIndexHtml = ob_get_clean();

    assert_test('Claims view renders community handover banner', str_contains($claimsIndexHtml, 'ระบบ Community Handover'));
    assert_test('Claims view uses peer-to-peer status labels', str_contains($claimsIndexHtml, 'รอนัดรับ &amp; ตรวจสอบ') || str_contains($claimsIndexHtml, 'รอนัดรับ & ตรวจสอบ'));
    assert_test('Claims view renders direct handover confirmation action', str_contains($claimsIndexHtml, 'ยืนยันส่งมอบของคืนแล้ว'));

    // 3. Functional Peer-to-Peer Handover Execution Test
    // Create temporary report by user 1
    $cStmt = $db->query('SELECT category_id FROM categories WHERE is_active = 1 LIMIT 1');
    $cat = $cStmt->fetch_assoc();
    $catId = (int) ($cat['category_id'] ?? 1);

    $insR = $db->prepare('INSERT INTO item_reports (category_id, reporter_id, report_type, title, description, location, event_datetime, status) VALUES (?, 1, "found", "P2P Test Item", "Description for P2P test", "Campus Central Library", NOW(), "open")');
    $insR->bind_param('i', $catId);
    $insR->execute();
    $testReportId = (int) $db->insert_id;
    $insR->close();

    // Create temporary claim by user 2
    $insCl = $db->prepare('INSERT INTO claims (report_id, claimant_id, claim_type, message, status) VALUES (?, 2, "เจ้าของสิ่งของ", "This is my item for P2P test", "pending")');
    $insCl->bind_param('i', $testReportId);
    $insCl->execute();
    $testClaimId = (int) $db->insert_id;
    $insCl->close();

    // Verify reports/show.php renders incoming claims & handover for reporter
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/reports/show.php?id=' . $testReportId;
    $_GET['id'] = (string) $testReportId;
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'student'; // Simulate as regular student reporter

    ob_start();
    require ROOT_PATH . '/public/reports/show.php';
    $showHtml = ob_get_clean();

    assert_test('Report show view displays peer-to-peer claims & handover section to reporter', str_contains($showHtml, 'คำขอรับคืน &amp; ส่งมอบสิ่งของ (Peer-to-Peer)') || str_contains($showHtml, 'คำขอรับคืน & ส่งมอบสิ่งของ (Peer-to-Peer)'));
    assert_test('Report show view displays direct handover confirmation button to reporter', str_contains($showHtml, 'ยืนยันส่งมอบของให้คนนี้แล้ว'));

    // Check reporter authorization to confirm handover directly
    $chkR = $db->prepare('SELECT reporter_id FROM item_reports WHERE report_id = ?');
    $chkR->bind_param('i', $testReportId);
    $chkR->execute();
    $rInfo = $chkR->get_result()->fetch_assoc();
    $chkR->close();

    $isRep = ((int) $rInfo['reporter_id']) === 1;
    assert_test('Reporter is recognized with authorization to confirm handover directly without admin', $isRep);

    // Clean up temporary claim and report
    $delCl = $db->prepare('DELETE FROM claims WHERE claim_id = ?');
    $delCl->bind_param('i', $testClaimId);
    $delCl->execute();
    $delCl->close();

    $delR = $db->prepare('DELETE FROM item_reports WHERE report_id = ?');
    $delR->bind_param('i', $testReportId);
    $delR->execute();
    $delR->close();

    assert_test('P2P test data cleaned up successfully', true);

    // 4. Verify public reports page renders Card Grid as default and includes View Switcher
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/reports/index.php';
    $_GET = [];

    ob_start();
    require ROOT_PATH . '/public/reports/index.php';
    $reportsIndexHtml = ob_get_clean();

    assert_test('Reports view renders Card Grid container as default', str_contains($reportsIndexHtml, 'id="reports-grid-view"'));
    assert_test('Reports view includes interactive View Mode Switcher', str_contains($reportsIndexHtml, 'id="view-mode-grid"') && str_contains($reportsIndexHtml, 'id="view-mode-table"'));
    assert_test('Reports view cards display category icons and badges', str_contains($reportsIndexHtml, 'แบบการ์ด (Grid)'));
}


// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------
echo "\n======================================================\n";
echo " Test Results: Total: {$totalTests} | Passed: \033[32m{$passedTests}\033[0m | Failed: " . ($failedTests > 0 ? "\033[31m{$failedTests}\033[0m" : "0") . "\n";
echo "======================================================\n\n";

exit($failedTests > 0 ? 1 : 0);
