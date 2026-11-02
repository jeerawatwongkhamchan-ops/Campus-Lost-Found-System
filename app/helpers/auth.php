<?php

declare(strict_types=1);

/**
 * Authentication Helper Functions
 *
 * Session management, authentication checks, and authorization
 */

// ============================================================================
// Password Operations
// ============================================================================

/**
 * Hash a password using the default algorithm.
 *
 * @param string $password The plain text password
 * @return string The hashed password
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a hash.
 *
 * @param string $password The plain text password
 * @param string $hash The hashed password
 * @return bool True if password matches, false otherwise
 */
function verify_password(mixed $password, mixed $hash): bool
{
    if (!is_string($password) || !is_string($hash)) {
        return false;
    }
    return password_verify($password, $hash);
}

// ============================================================================
// Session State
// ============================================================================

/**
 * Check if a user is currently authenticated.
 *
 * @return bool True if authenticated, false otherwise
 */
function is_authenticated(): bool
{
    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

/**
 * Get all authenticated user data from the session.
 *
 * @return array|null User data array or null if not authenticated
 */
function get_auth_user(): ?array
{
    if (!is_authenticated()) {
        return null;
    }

    return [
        'user_id' => $_SESSION['user_id'],
        'student_id' => $_SESSION['student_id'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'student',
    ];
}

/**
 * Get the authenticated user's ID.
 *
 * @return int|null User ID or null if not authenticated
 */
function get_auth_user_id(): ?int
{
    return is_authenticated() ? $_SESSION['user_id'] : null;
}

/**
 * Get the authenticated user's role.
 *
 * @return string|null Role (student/staff/admin) or null if not authenticated
 */
function get_auth_user_role(): ?string
{
    return is_authenticated() ? ($_SESSION['role'] ?? 'student') : null;
}

/**
 * Check for session timeout and logout if expired.
 *
 * Should be called on every request (automatically via bootstrap.php).
 *
 * @return void
 */
function check_session_timeout(): void
{
    if (!is_authenticated()) {
        return; // Not logged in, nothing to check
    }

    // Idle Timeout: 30 minutes of inactivity
    $idle_timeout = 1800; // 30 minutes in seconds
    $last_activity = $_SESSION['last_activity'] ?? 0;

    if ((time() - $last_activity) > $idle_timeout) {
        // Idle session expired - clear CAPTCHA attempts
        $client_ip = get_client_ip();
        clear_failed_login_attempts($client_ip);
        logout_user();
        $idle_minutes = floor($idle_timeout / 60);
        redirect("/auth/login.php?timeout=1&duration={$idle_minutes}");
    }

    // Absolute Timeout: 8 hours from login (regardless of activity)
    $absolute_timeout = 28800; // 8 hours in seconds
    $session_start = $_SESSION['session_start_time'] ?? 0;

    if ((time() - $session_start) > $absolute_timeout) {
        // Absolute session expired - clear CAPTCHA attempts
        $client_ip = get_client_ip();
        clear_failed_login_attempts($client_ip);
        logout_user();
        $absolute_minutes = floor($absolute_timeout / 60);
        redirect("/auth/login.php?timeout=2&duration={$absolute_minutes}");
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// ============================================================================
// Authentication Actions
// ============================================================================

/**
 * Log in a user by storing their data in the session.
 *
 * @param array $user User data from database (must include user_id, student_id, full_name, email, role)
 * @return void
 */
function login_user(array $user): void
{
    // Prevent session fixation attack by regenerating session ID
    session_regenerate_id(true);

    // Store user data in session
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['student_id'] = $user['student_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['session_start_time'] = time(); // For absolute timeout
}

/**
 * Log out the current user by destroying the session completely.
 *
 * @return void
 */
function logout_user(): void
{
    // Clear all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();
}

/**
 * Refresh the current user's session data from the database.
 *
 * Useful after updating user profile to sync changes to the session.
 * Will logout if user no longer exists or account is not active.
 *
 * @return void
 */
function refresh_session_user(): void
{
    if (!is_authenticated()) {
        return;
    }

    $user_id = get_auth_user_id();
    $conn = get_db_connection();

    if (!$conn) {
        return;
    }

    try {
        $stmt = $conn->prepare("
            SELECT user_id, student_id, full_name, email, role, account_status
            FROM users
            WHERE user_id = ?
        ");

        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            // User deleted from database
            logout_user();
            redirect('/auth/login.php');
            return;
        }

        // Update session data (preserve last_activity)
        $_SESSION['student_id'] = $user['student_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Check if account was suspended or deactivated
        if ($user['account_status'] !== 'active') {
            logout_user();
            redirect('/auth/login.php?suspended=1');
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Failed to refresh session user: " . $e->getMessage());
    }
}

// ============================================================================
// Authorization
// ============================================================================

/**
 * Require authentication and active account status. Redirect to login if not authenticated or suspended.
 *
 * @param string|null $redirect_to Where to redirect if not authenticated
 * @return void
 */
function require_auth(?string $redirect_to = '/auth/login.php'): void
{
    if (!is_authenticated()) {
        redirect($redirect_to ?? '/auth/login.php');
    }

    refresh_session_user();

    if (!is_authenticated()) {
        redirect($redirect_to ?? '/auth/login.php');
    }
}

/**
 * Check if the authenticated user has a specific role.
 *
 * @param string $role Role to check (student, staff, admin)
 * @return bool True if user has the role, false otherwise
 */
function has_auth_role(string $role): bool
{
    return get_auth_user_role() === $role;
}

/**
 * Require a specific role. Redirect to home if user doesn't have it.
 *
 * @param string $role Required role
 * @return void
 */
function require_role(string $role): void
{
    if (!is_authenticated()) {
        redirect('/auth/login.php');
    }

    if (!has_auth_role($role)) {
        redirect('/index.php');
    }
}
