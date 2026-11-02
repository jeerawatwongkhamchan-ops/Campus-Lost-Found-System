<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Returns a connection to the MariaDB database using credentials from environment variables.
 *
 * @return mysqli|null
 */
function get_db_connection(): ?mysqli
{
    static $connection = null;

    if ($connection !== null) {
        return $connection;
    }

    $host = env('DB_HOST', 'db');
    $port = (int) env('DB_PORT', 3306);
    $database = env('DB_NAME', 'campus_lost_found');
    $user = env('DB_USER', 'campus_lost_found_app');
    $password = env('DB_PASSWORD', 'change_me');

    $prev_reporting = error_reporting(0);
    try {
        $connection = @new mysqli($host, $user, $password, $database, $port);
        error_reporting($prev_reporting);
        if ($connection->connect_errno) {
            error_log('Database connection failed: ' . $connection->connect_error);
            $connection = null;
            return null;
        }
        $connection->set_charset('utf8mb4');
        return $connection;
    } catch (Throwable $e) {
        error_reporting($prev_reporting);
        error_log('Database connection failed: ' . $e->getMessage());
        $connection = null;
        return null;
    }
}

/**
 * Check database connection status without querying application tables.
 *
 * @return array{connected: bool, message: string}
 */
function get_db_status(): array
{
    try {
        $connection = get_db_connection();
        if ($connection !== null && $connection->ping()) {
            return [
                'connected' => true,
                'message' => 'Connected to MariaDB successfully.',
            ];
        }

        return [
            'connected' => false,
            'message' => 'Database connection unavailable.',
        ];
    } catch (Throwable $e) {
        return [
            'connected' => false,
            'message' => 'Database connection error: ' . $e->getMessage(),
        ];
    }
}
