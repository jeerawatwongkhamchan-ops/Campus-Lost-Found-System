<?php

declare(strict_types=1);

/**
 * Escape HTML special characters.
 *
 * @param string|null $value
 * @return string
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect to a given URL.
 *
 * @param string $url
 * @return void
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Get an environment variable with a default fallback.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    return $value;
}

/**
 * Render page header layout.
 *
 * @param string $title
 * @return void
 */
function render_header(string $title = 'Campus Lost and Found System'): void
{
    $page_title = $title;
    require APP_PATH . '/views/layouts/header.php';
}

/**
 * Render page footer layout.
 *
 * @return void
 */
function render_footer(): void
{
    require APP_PATH . '/views/layouts/footer.php';
}

/**
 * Render standard application error page.
 *
 * @param int $statusCode HTTP status code (e.g. 400, 403, 404, 500)
 * @param string $title Short title of the error
 * @param string $message User-friendly explanation
 * @return never
 */
function render_error(int $statusCode, string $title, string $message): never
{
    http_response_code($statusCode);
    $status_code = $statusCode;
    $error_title = $title;
    $error_message = $message;

    // If request asks for JSON, respond in JSON
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => $statusCode,
            'error' => $title,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    require APP_PATH . '/views/errors/error.php';
    exit;
}

/**
 * Render a cohesive, human-crafted role badge.
 *
 * @param string|null $role The user role (admin, staff, student)
 * @param bool $withIcon Whether to include a subtle micro-icon
 * @param bool $includeEnLabel Whether to include English label e.g. "ผู้ดูแลระบบ (Admin)"
 * @param string $extraClasses Additional CSS classes to apply
 * @return string HTML markup for the badge
 */
function render_role_badge(?string $role, bool $withIcon = true, bool $includeEnLabel = false, string $extraClasses = ''): string
{
    $role = strtolower(trim((string) $role));

    switch ($role) {
        case 'admin':
            $label = $includeEnLabel ? 'ผู้ดูแลระบบ (Admin)' : 'ผู้ดูแลระบบ';
            $badgeClasses = 'bg-violet-50 text-violet-700 ring-violet-700/15';
            $iconColor = 'text-violet-600';
            // Shield icon representing administrative security and governance
            $icon = '<svg class="h-3 w-3 ' . $iconColor . ' shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>';
            break;

        case 'staff':
            $label = $includeEnLabel ? 'เจ้าหน้าที่ (Staff)' : 'เจ้าหน้าที่';
            $badgeClasses = 'bg-sky-50 text-sky-700 ring-sky-600/20';
            $iconColor = 'text-sky-600';
            // Briefcase / Operations badge representing campus staff
            $icon = '<svg class="h-3 w-3 ' . $iconColor . ' shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>';
            break;

        case 'student':
        default:
            $label = $includeEnLabel ? 'นักศึกษา (Student)' : 'นักศึกษา';
            $badgeClasses = 'bg-slate-100/80 text-slate-700 ring-slate-500/15';
            $iconColor = 'text-slate-500';
            // Academic graduation cap representing campus student users
            $icon = '<svg class="h-3 w-3 ' . $iconColor . ' shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /></svg>';
            break;
    }

    $iconHtml = $withIcon ? $icon : '';
    $extra = $extraClasses !== '' ? ' ' . $extraClasses : '';

    return '<span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ' . $badgeClasses . $extra . '">'
        . $iconHtml
        . e($label)
        . '</span>';
}

