# Security & Audit Logging Documentation

เอกสารนี้อธิบายสถาปัตยกรรมความปลอดภัย ฟังก์ชัน Helper ส่วนกลาง และข้อกำหนดการบันทึกประวัติความปลอดภัย (Audit Trail) 
---

## 1. Security Architecture & Helpers

ฟังก์ชันความปลอดภัยส่วนกลางถูกประกาศไว้ที่ `app/helpers/security.php` และโหลดเข้าสู่ระบบผ่าน `app/bootstrap.php`

### ฟังก์ชันหลักในระบบ:
- **Audit Logging (`log_security_event`):** บันทึกเหตุการณ์สำคัญที่เกี่ยวกับความปลอดภัยลงตาราง `security_logs` ใน MariaDB อัตโนมัติ
- **Client IP Resolution (`get_client_ip`):** ค้นหา IP Address ที่แท้จริงของผู้ใช้ โดยรองรับ Reverse Proxy, Docker Gateway และ CDN/Cloudflare
- **CSRF Protection (`generate_csrf_token`, `csrf_field`, `verify_csrf_token`):** สร้างและตรวจสอบ Token สุ่มสำหรับฟอร์ม POST เพื่อป้องกัน Cross-Site Request Forgery
- **XSS Escaping (`e`):** ตัวช่วยกรองอักขระพิเศษสำหรับแสดงผลข้อความออกสู่หน้าจอ HTML
- **Session Security:** กำหนดค่าคุกกี้เซสชันที่ปลอดภัยใน `bootstrap.php` (ตั้งค่า `httponly = true`, `samesite = 'Lax'`, และ `secure` เมื่อรันบน HTTPS)

---

## 2. ข้อกำหนดการบันทึก Log (`security_logs`)

ตาราง `security_logs` ใช้จัดเก็บประวัติเหตุการณ์ตามมาตรฐาน OWASP Application Logging Guide

### โครงสร้างฟังก์ชัน:
```php
log_security_event(
    string $eventType,
    string $outcome,
    ?int $userId = null,
    ?string $targetType = null,
    ?string $targetId = null,
    array|string|null $details = null
): bool

ก. การบันทึก Log ในโมดูล Auth (public/auth/login.php):
// เมื่อตรวจสอบรหัสผ่านไม่ผ่าน
log_security_event('AUTH_LOGIN_FAILED', 'FAILURE', null, 'USER', $email, [
    'reason' => 'Invalid email or password'
]);

// เมื่อตรวจสอบรหัสผ่านถูกต้อง
log_security_event('AUTH_LOGIN_SUCCESS', 'SUCCESS', (int)$user['user_id'], 'USER', (string)$user['user_id'], [
    'email' => $user['email']
]);

ข. การใส่ CSRF Protection ในแบบฟอร์ม (<form>):
ในไฟล์ View หรือหน้าแบบฟอร์ม (เช่น public/reports/create.php):

<form method="POST" action="/reports/create.php">
    <?= csrf_field() ?>
    <!-- ฟิลด์อื่นๆ -->
</form>

ในจุดที่รับค่า POST:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        log_security_event('CSRF_TOKEN_INVALID', 'FAILURE', $_SESSION['user_id'] ?? null, 'ROUTE', '/reports/create.php');
        http_response_code(403);
        die('Invalid CSRF Token');
    }
}

ค. การตรวจจับ Unauthorized Access (public/admin/index.php):
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    log_security_event('ACCESS_DENIED', 'FAILURE', $_SESSION['user_id'] ?? null, 'ROUTE', '/admin/index.php', [
        'required_role' => 'admin'
    ]);
    header('Location: /auth/login.php');
    exit;
}

4. ผลการทดสอบ (Verification & Proof of Work)
ได้ดำเนินการทดสอบผ่านระบบฐานข้อมูล MariaDB ในเครื่องพัฒนา (Local Environment) ด้วยคำสั่งทดสอบจริง ผลลัพธ์แสดงการจัดเก็บข้อมูลได้อย่างถูกต้อง:
+--------+---------------------+---------+------------+----------------------------------+---------------------+
| log_id | event_type          | outcome | ip_address | details                          | created_at          |
+--------+---------------------+---------+------------+----------------------------------+---------------------+
|      3 | LOGIN_SUCCESS       | SUCCESS | 172.20.0.1 | NULL                             | 2026-09-30 15:54:54 |
|      2 | UNAUTHORIZED_ACCESS | FAILURE | 172.20.0.1 | NULL                             | 2026-09-30 15:54:54 |
|      1 | AUTH_FAILED         | FAILURE | 172.20.0.1 | {"reason":"Wrong password"}      | 2026-09-30 15:54:54 |
+--------+---------------------+---------+------------+----------------------------------+---------------------+
ตาราง: security_logs
ตรวจสอบความถูกต้องของการแปลง JSON, การตรวจจับ IP Address และการลงเวลาประทับ(Timestamp) ครบถ้วน