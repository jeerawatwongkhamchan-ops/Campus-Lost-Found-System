<?php

declare(strict_types=1);

// Define application paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Load helper functions
require_once APP_PATH . '/helpers/functions.php';

// Load database configuration (needed by auth and security helpers)
require_once APP_PATH . '/config/database.php';

// Load authentication and security helpers
require_once APP_PATH . '/helpers/auth.php';
require_once APP_PATH . '/helpers/security.php';
require_once APP_PATH . '/helpers/file_upload.php';
require_once APP_PATH . '/helpers/notification.php';

// Load environment variables from .env if present
$env_file = ROOT_PATH . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                if (getenv($key) === false) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}

// Configure error reporting based on environment
$app_env = env('APP_ENV', 'development');
if ($app_env === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Configure basic session cookie settings and start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (env('APP_ENV') === 'production'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Send standard defense-in-depth HTTP security headers
send_security_headers();

// Check session timeout for authenticated users
check_session_timeout();
