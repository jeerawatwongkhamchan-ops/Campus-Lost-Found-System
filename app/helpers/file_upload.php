<?php

declare(strict_types=1);

/**
 * File Upload Security and Management Helper
 *
 * Provides strict validation, MIME sniffing, image integrity checking,
 * secure filename generation, and safe storage for uploaded files.
 */

const MAX_IMAGE_FILE_SIZE = 3145728; // 3 MB (3 * 1024 * 1024 bytes)

const ALLOWED_IMAGE_MIMES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

/**
 * Get the absolute directory path where report images are stored.
 */
function get_report_upload_dir(): string
{
    return PUBLIC_PATH . '/uploads/reports';
}

function get_claim_upload_dir(): string
{
    return PUBLIC_PATH . '/uploads/claims';
}

/**
 * Ensure the upload directory exists and has protection against direct script execution.
 */
function ensure_upload_directory_secure(): void
{
    $upload_base = PUBLIC_PATH . '/uploads';
    $reports_dir = $upload_base . '/reports';
    $claims_dir = $upload_base . '/claims';

    if (!is_dir($upload_base)) {
        mkdir($upload_base, 0755, true);
    }

    if (!is_dir($reports_dir)) {
        mkdir($reports_dir, 0755, true);
    }

    if (!is_dir($claims_dir)) {
        mkdir($claims_dir, 0755, true);
    }

    // Protect uploads directory from script execution via .htaccess
    $htaccess_file = $upload_base . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = <<<'HTACCESS'
# Disable execution of scripts in upload directories
<FilesMatch "(?i)\.(php|phtml|php3|php4|php5|phar|pl|py|jsp|asp|sh|cgi|exe)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

Options -ExecCGI -Indexes
php_flag engine off
HTACCESS;
        file_put_contents($htaccess_file, $htaccess_content);
    }

    // Place blank index.html files to prevent directory indexing
    $index_base = $upload_base . '/index.html';
    if (!file_exists($index_base)) {
        file_put_contents($index_base, '');
    }

    $index_reports = $reports_dir . '/index.html';
    if (!file_exists($index_reports)) {
        file_put_contents($index_reports, '');
    }

    $index_claims = $claims_dir . '/index.html';
    if (!file_exists($index_claims)) {
        file_put_contents($index_claims, '');
    }
}

/**
 * Validate and securely save an uploaded report image.
 *
 * @param array<string, mixed>|null $file $_FILES['image'] array
 * @param string|null $old_filename Existing filename to keep or replace
 * @return array{success: bool, filename: ?string, error: ?string}
 */
function validate_and_upload_report_image(?array $file, ?string $old_filename = null): array
{
    // Case 1: No file was uploaded in this request
    if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [
            'success' => true,
            'filename' => $old_filename,
            'error' => null,
        ];
    }

    // Case 2: Handle standard PHP file upload error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'ขนาดไฟล์เกินค่าจำกัดสูงสุดของระบบ (php.ini)',
            UPLOAD_ERR_FORM_SIZE  => 'ขนาดไฟล์เกินขีดจำกัดที่กำหนดในแบบฟอร์ม',
            UPLOAD_ERR_PARTIAL    => 'การอัปโหลดไฟล์ไม่สมบูรณ์ กรุณาลองใหม่อีกครั้ง',
            UPLOAD_ERR_NO_TMP_DIR => 'ระบบเซิร์ฟเวอร์ไม่พบโฟลเดอร์ชั่วคราวสำหรับจัดเก็บไฟล์',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้',
            UPLOAD_ERR_EXTENSION  => 'การอัปโหลดไฟล์ถูกระงับโดยส่วนขยายของระบบ',
        ];

        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => $error_messages[$file['error']] ?? 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์',
        ];
    }

    $tmp_path = (string) ($file['tmp_name'] ?? '');
    $original_name = (string) ($file['name'] ?? '');
    $file_size = (int) ($file['size'] ?? 0);

    // Verify it is a real uploaded file
    if (!is_uploaded_file($tmp_path)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ไฟล์ที่อัปโหลดไม่ถูกต้อง หรือไม่ได้มาจากการส่งฟอร์ม',
        ];
    }

    // Validate file size (Max 3 MB, Min 100 bytes)
    if ($file_size > MAX_IMAGE_FILE_SIZE) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ขนาดไฟล์รูปภาพเกินขีดจำกัด (สูงสุด 3 MB)',
        ];
    }

    if ($file_size < 100) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ไฟล์รูปภาพมีขนาดเล็กเกินไป หรืออาจเป็นไฟล์เสีย',
        ];
    }

    // Check client extension against whitelist
    $original_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($original_ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'นามสกุลไฟล์ไม่ถูกต้อง อนุญาตเฉพาะไฟล์ JPG, JPEG, PNG หรือ WEBP เท่านั้น',
        ];
    }

    // Check for malicious double extensions (e.g. test.php.jpg)
    if (preg_match('/\.(php|phtml|phar|pl|py|cgi|sh|asp|jsp)\./i', $original_name)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ตรวจพบชื่อไฟล์ที่ไม่ปลอดภัย กรุณาเปลี่ยนชื่อไฟล์ก่อนอัปโหลด',
        ];
    }

    // Strict MIME type sniffing from file contents using Fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ระบบไม่สามารถตรวจสอบประเภทเนื้อหาไฟล์ได้',
        ];
    }

    $detected_mime = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);

    if (!is_string($detected_mime) || !array_key_exists($detected_mime, ALLOWED_IMAGE_MIMES)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ประเภทเนื้อหาไฟล์ไม่ถูกต้อง อนุญาตเฉพาะไฟล์ภาพ JPG, PNG หรือ WEBP เท่านั้น',
        ];
    }

    $safe_extension = ALLOWED_IMAGE_MIMES[$detected_mime];

    // Deep validation using getimagesize() to verify image structure & dimensions
    $image_info = @getimagesize($tmp_path);
    if ($image_info === false) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ไฟล์ที่อัปโหลดไม่ใช่โครงสร้างภาพที่ถูกต้อง หรือไฟล์อาจเสียหาย',
        ];
    }

    $width = $image_info[0] ?? 0;
    $height = $image_info[1] ?? 0;
    $image_type = $image_info[2] ?? 0;

    if ($width <= 0 || $height <= 0) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ขนาดมิติภาพไม่ถูกต้อง',
        ];
    }

    // Prevent decompression bombs (max 6000x6000px)
    if ($width > 6000 || $height > 6000) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ความละเอียดของภาพสูงเกินไป (ขนาดสูงสุด 6000x6000 พิกเซล)',
        ];
    }

    // Verify image type constant matches MIME
    $valid_image_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!in_array($image_type, $valid_image_types, true)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'ประเภทโครงสร้างภาพไม่ตรงกับมาตรฐานที่รองรับ',
        ];
    }

    // Ensure upload directory exists and is secured
    ensure_upload_directory_secure();

    // Generate cryptographically secure random filename
    $new_filename = bin2hex(random_bytes(16)) . '.' . $safe_extension;
    $destination = get_report_upload_dir() . '/' . $new_filename;

    if (!move_uploaded_file($tmp_path, $destination)) {
        return [
            'success' => false,
            'filename' => $old_filename,
            'error' => 'เกิดข้อผิดพลาดในการย้ายไฟล์ไปยังตำแหน่งจัดเก็บ',
        ];
    }

    // Set safe file permissions (read-only for group/others, not executable)
    chmod($destination, 0644);

    // Delete old image file if replacing an existing one
    if (!empty($old_filename) && $old_filename !== $new_filename) {
        delete_report_image($old_filename);
    }

    return [
        'success' => true,
        'filename' => $new_filename,
        'error' => null,
    ];
}

/**
 * Safely delete an uploaded report image from disk.
 *
 * @param string|null $filename Filename to delete
 * @return bool True if deleted or already absent, false on error
 */
function delete_report_image(?string $filename): bool
{
    if ($filename === null || trim($filename) === '') {
        return true;
    }

    $sanitized = basename(trim($filename));

    // Strict validation of filename format (must be 32 hex chars + safe extension)
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $sanitized)) {
        return false;
    }

    $full_path = get_report_upload_dir() . '/' . $sanitized;
    if (file_exists($full_path)) {
        return @unlink($full_path);
    }

    return true;
}

/**
 * Get public URL for a report image.
 *
 * @param string|null $filename Filename of the report image
 * @return string|null Web-accessible URL or null if empty/missing
 */
function get_report_image_url(?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }

    $sanitized = basename(trim($filename));
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $sanitized)) {
        return null;
    }

    $full_path = get_report_upload_dir() . '/' . $sanitized;
    if (!file_exists($full_path)) {
        return null;
    }

    return '/uploads/reports/' . rawurlencode($sanitized);
}

/**
 * Get URL for a claim proof image.
 *
 * @param string|null $filename Filename of the claim proof image
 * @return string|null Web-accessible URL or null if empty/missing
 */
function get_claim_image_url(?string $filename): ?string
{
    if ($filename === null || trim($filename) === '') {
        return null;
    }

    $sanitized = basename(trim($filename));
    if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $sanitized)) {
        return null;
    }

    $full_path = get_claim_upload_dir() . '/' . $sanitized;
    if (!file_exists($full_path)) {
        return null;
    }

    return '/uploads/claims/' . rawurlencode($sanitized);
}

/**
 * Validate and securely upload a proof image for a claim.
 *
 * @param array<string, mixed>|null $file
 * @return array{success: bool, filename: ?string, error: ?string}
 */
function validate_and_upload_claim_image(?array $file): array
{
    if ($file === null || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์หลักฐาน'];
    }

    $tmp_path = (string) ($file['tmp_name'] ?? '');
    $original_name = (string) ($file['name'] ?? '');
    $file_size = (int) ($file['size'] ?? 0);

    if (!is_uploaded_file($tmp_path)) {
        return ['success' => false, 'filename' => null, 'error' => 'ไฟล์ที่อัปโหลดไม่ถูกต้อง'];
    }

    if ($file_size > MAX_IMAGE_FILE_SIZE) {
        return ['success' => false, 'filename' => null, 'error' => 'ไฟล์รูปภาพหลักฐานต้องมีขนาดไม่เกิน 3 MB'];
    }

    $original_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (!in_array($original_ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        return ['success' => false, 'filename' => null, 'error' => 'อนุญาตเฉพาะไฟล์ JPG, PNG หรือ WEBP'];
    }

    if (preg_match('/\.(php|phtml|phar|pl|py|cgi|sh|asp|jsp)\./i', $original_name)) {
        return ['success' => false, 'filename' => null, 'error' => 'ตรวจพบชื่อไฟล์ที่ไม่ปลอดภัย'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detected_mime = $finfo !== false ? finfo_file($finfo, $tmp_path) : false;
    if ($finfo !== false) finfo_close($finfo);

    if (!is_string($detected_mime) || !array_key_exists($detected_mime, ALLOWED_IMAGE_MIMES)) {
        return ['success' => false, 'filename' => null, 'error' => 'ประเภทไฟล์ภาพไม่ถูกต้อง'];
    }

    $safe_extension = ALLOWED_IMAGE_MIMES[$detected_mime];
    $image_info = @getimagesize($tmp_path);
    if ($image_info === false) {
        return ['success' => false, 'filename' => null, 'error' => 'ไฟล์ภาพชำรุดหรือไม่ถูกต้อง'];
    }

    ensure_upload_directory_secure();
    $new_filename = bin2hex(random_bytes(16)) . '.' . $safe_extension;
    $destination = get_claim_upload_dir() . '/' . $new_filename;

    if (!move_uploaded_file($tmp_path, $destination)) {
        return ['success' => false, 'filename' => null, 'error' => 'ไม่สามารถบันทึกไฟล์ภาพหลักฐานได้'];
    }

    chmod($destination, 0644);
    return ['success' => true, 'filename' => $new_filename, 'error' => null];
}
