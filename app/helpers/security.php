<?php

declare(strict_types=1);

/**
 * Security Helper Functions
 *
 * Centralized security helpers including CSRF protection, input validation,
 * audit logging, rate limiting (throttle), and CAPTCHA.
 */

// ============================================================================
// CSRF Protection
// ============================================================================

/**
 * Generate a new CSRF token and store it in the session.
 *
 * @return string The generated token (64 hex characters)
 */
function generate_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Generate a hidden HTML input field with the CSRF token.
 *
 * @return string HTML input element
 */
function csrf_field(): string
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify that a submitted CSRF token matches the session token.
 *
 * @param mixed $token The token from POST data
 * @return bool True if valid, false otherwise
 */
function verify_csrf_token(mixed $token): bool
{
    if (!is_string($token)) {
        return false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================================
// Input Validation & Sanitization
// ============================================================================

/**
 * Validate an email address.
 *
 * @param mixed $email The email to validate
 * @return bool True if valid, false otherwise
 */
function validate_email(mixed $email): bool
{
    if (!is_string($email)) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate a phone number (optional field).
 *
 * @param mixed $phone The phone number to validate
 * @return bool True if valid or null, false otherwise
 */
function validate_phone(mixed $phone): bool
{
    if ($phone === null) {
        return true;
    }

    if (!is_string($phone)) {
        return false;
    }

    if (trim($phone) === '') {
        return true;
    }

    if (strlen($phone) < 8 || strlen($phone) > 30) {
        return false;
    }

    return preg_match('/^[0-9\-\+\(\)\s]+$/', $phone) === 1;
}

/**
 * Validate a student/staff ID (Format: 9 digits, dash, 1 digit).
 *
 * @param mixed $id The ID to validate
 * @return bool True if valid, false otherwise
 */
function validate_student_id(mixed $id): bool
{
    if (!is_string($id) || empty($id)) {
        return false;
    }

    return preg_match('/^\d{9}-\d$/', $id) === 1;
}

/**
 * Validate a password.
 *
 * @param mixed $password The password to validate
 * @return array{valid: bool, errors: string[]}
 */
function validate_password(mixed $password): array
{
    if (!is_string($password)) {
        return [
            'valid' => false,
            'errors' => ['รหัสผ่านต้องเป็นข้อความ'],
        ];
    }

    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Sanitize a string input.
 *
 * @param mixed $input The input to sanitize
 * @return string The sanitized string
 */
function sanitize_string(mixed $input): string
{
    if (!is_string($input)) {
        return '';
    }

    return trim($input);
}

// ============================================================================
// Client IP & User Agent Resolution
// ============================================================================

/**
 * Get the client's IP address.
 *
 * On direct servers (default), only REMOTE_ADDR is trusted to prevent header spoofing
 * and rate limiting bypass attacks. Proxy headers (CF-Connecting-IP, X-Forwarded-For, X-Real-IP)
 * are only evaluated when TRUST_PROXY_HEADERS is explicitly set or REMOTE_ADDR is in TRUSTED_PROXIES.
 *
 * @return string The client IP address
 */
function get_client_ip(): string
{
    $remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $trustProxy = getenv('TRUST_PROXY_HEADERS') ?: ($_ENV['TRUST_PROXY_HEADERS'] ?? '');
    $trustedProxies = getenv('TRUSTED_PROXIES') ?: ($_ENV['TRUSTED_PROXIES'] ?? '');

    $isTrusted = false;
    if ($trustProxy === 'true' || $trustProxy === '1') {
        $isTrusted = true;
    } elseif (!empty($trustedProxies) && !empty($remoteAddr)) {
        $trustedList = array_map('trim', explode(',', (string) $trustedProxies));
        if (in_array($remoteAddr, $trustedList, true)) {
            $isTrusted = true;
        }
    }

    if ($isTrusted) {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ipList = explode(',', (string) $_SERVER[$header]);
                $ip = trim($ipList[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return mb_substr($ip, 0, 45);
                }
            }
        }
    }

    if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
        return mb_substr($remoteAddr, 0, 45);
    }

    return '0.0.0.0';
}

/**
 * Get the user agent string.
 *
 * @return string The user agent (truncated to 500 chars for DB)
 */
function get_user_agent(): string
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $sanitized = preg_replace('/[^\x20-\x7E]/', '', (string)$ua) ?? 'Unknown';
    return substr($sanitized, 0, 500);
}

// ============================================================================
// Security Logging
// ============================================================================

/**
 * Log a security event to the security_logs table.
 * Supports both signatures from feature/security and feature/auth:
 *
 * Signature A (feature/security):
 * log_security_event(string $eventType, string $outcome, ?int $userId, ?string $targetType, ?string $targetId, $details): bool
 *
 * Signature B (feature/auth):
 * log_security_event(string $eventType, ?int $userId, string $outcome, ?string $details): bool
 *
 * @param string $eventType
 * @param mixed $arg2
 * @param mixed $arg3
 * @param mixed $arg4
 * @param mixed $arg5
 * @param mixed $arg6
 * @return bool
 */
function log_security_event(
    string $eventType,
    mixed $arg2 = null,
    mixed $arg3 = null,
    mixed $arg4 = null,
    mixed $arg5 = null,
    mixed $arg6 = null
): bool {
    try {
        $db = get_db_connection();
        if ($db === null) {
            error_log("[SecurityLog] Database unavailable for logging event: {$eventType}");
            return false;
        }

        $userId = null;
        $outcome = 'UNKNOWN';
        $targetType = null;
        $targetId = null;
        $details = null;

        // Detect call style
        // Signature B (feature/auth): ($eventType, ?int $userId, string $outcome, ?string $details = null)
        if (
            is_int($arg2) ||
            ($arg2 === null && is_string($arg3) && in_array(strtolower($arg3), ['success', 'failed', 'failure', 'blocked', 'denied'], true))
        ) {
            $userId = $arg2 !== null ? (int)$arg2 : null;
            $outcome = (string)$arg3;
            $details = $arg4;
        } else {
            // Signature A (feature/security): ($eventType, string $outcome, ?int $userId, ?string $targetType, ?string $targetId, $details)
            $outcome = is_string($arg2) ? $arg2 : 'UNKNOWN';
            $userId = is_int($arg3) ? $arg3 : ($arg3 !== null ? (int)$arg3 : null);
            $targetType = is_string($arg4) ? $arg4 : null;
            $targetId = is_string($arg5) || is_int($arg5) ? (string)$arg5 : null;
            $details = $arg6;
        }

        if ($userId === null && isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        }

        $ipAddress = get_client_ip();
        $userAgent = get_user_agent();

        $detailsJson = null;
        if (is_array($details)) {
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_string($details)) {
            $detailsJson = $details;
        }

        $sql = "INSERT INTO `security_logs` 
                (`user_id`, `event_type`, `target_type`, `target_id`, `outcome`, `ip_address`, `user_agent`, `details`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log("[SecurityLog] Prepare failed: " . $db->error);
            return false;
        }

        $stmt->bind_param(
            "isssssss",
            $userId,
            $eventType,
            $targetType,
            $targetId,
            $outcome,
            $ipAddress,
            $userAgent,
            $detailsJson
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    } catch (Throwable $e) {
        error_log("[SecurityLog] Logging error: " . $e->getMessage());
        return false;
    }
}

// ============================================================================
// Login Throttle
// ============================================================================

/**
 * Check if an IP address is currently throttled (too many failed login attempts).
 *
 * @param string $ip The IP address to check
 * @return bool True if throttled, false otherwise
 */
function check_login_throttle(string $ip): bool
{
    $failed_count = get_failed_login_count($ip, 15);
    return $failed_count >= 5;
}

/**
 * Get the number of failed login attempts for an IP within a time window.
 * Only counts failures since the most recent successful login to allow
 * immediate recovery upon valid login without deleting audit trail logs.
 *
 * @param string $ip The IP address
 * @param int $minutes Time window in minutes
 * @return int Number of failed attempts
 */
function get_failed_login_count(string $ip, int $minutes): int
{
    $conn = get_db_connection();
    if (!$conn) {
        return 0;
    }

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as failed_count
            FROM security_logs
            WHERE ip_address = ?
              AND event_type = 'login_failed'
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
              AND created_at > COALESCE((
                  SELECT MAX(created_at)
                  FROM security_logs
                  WHERE ip_address = ? AND event_type = 'login_success'
              ), '1970-01-01 00:00:00')
        ");

        $stmt->bind_param('sis', $ip, $minutes, $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int) ($row['failed_count'] ?? 0);
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to check login throttle: " . $e->getMessage());
        return 0;
    }
}

/**
 * Reset failed login attempt tracking for an IP after successful login.
 *
 * Audit Trail Immutability:
 * We intentionally DO NOT DELETE records from security_logs to preserve full forensic evidence.
 * Since get_failed_login_count() queries only failures after the latest 'login_success',
 * inserting the 'login_success' log automatically resets the throttle counter.
 *
 * @param string $ip The IP address to clear
 * @return void
 */
function clear_failed_login_attempts(string $ip): void
{
    // Preserved for backward compatibility. 
    // Audit logs remain immutable; throttle resets automatically via login_success timestamp.
}

// ============================================================================
// HTTP Security Headers
// ============================================================================

/**
 * Send standard HTTP security headers for defense-in-depth against XSS, clickjacking,
 * MIME sniffing, and information disclosure.
 */
function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    // Prevent framing / Clickjacking
    header('X-Frame-Options: SAMEORIGIN');

    // Prevent MIME-sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Modern XSS auditor disabling in favor of CSP
    header('X-XSS-Protection: 0');

    // Disable unwanted browser features
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Content Security Policy
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// ============================================================================
// Math CAPTCHA
// ============================================================================

/**
 * Generate a simple math CAPTCHA and store it in session.
 * 
 * @return string The CAPTCHA question (e.g., "5 + 3 = ?")
 */
function generate_math_captcha(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $answer = $num1 + $num2;

    $_SESSION['captcha_answer'] = $answer;
    $_SESSION['captcha_generated_at'] = time();

    return "{$num1} + {$num2} = ?";
}

/**
 * Verify CAPTCHA answer.
 * 
 * @param mixed $user_answer The user's submitted answer
 * @return bool True if correct, false otherwise
 */
function verify_captcha(mixed $user_answer): bool
{
    if (!is_string($user_answer) && !is_numeric($user_answer)) {
        return false;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['captcha_answer']) || !isset($_SESSION['captcha_generated_at'])) {
        return false;
    }

    // CAPTCHA expires after 10 minutes
    if (time() - (int)$_SESSION['captcha_generated_at'] > 600) {
        unset($_SESSION['captcha_answer'], $_SESSION['captcha_generated_at']);
        return false;
    }

    $correct = (int) $_SESSION['captcha_answer'] === (int) $user_answer;

    // Clear CAPTCHA after verification (one-time use)
    unset($_SESSION['captcha_answer'], $_SESSION['captcha_generated_at']);

    return $correct;
}

/**
 * Check if CAPTCHA is required for the current IP.
 * Required after 3+ failed login attempts.
 * 
 * @param string $ip The IP address
 * @return bool True if CAPTCHA is required
 */
function is_captcha_required(string $ip): bool
{
    return get_failed_login_count($ip, 15) >= 3;
}
