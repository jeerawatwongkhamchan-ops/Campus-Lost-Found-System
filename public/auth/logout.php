<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// ต้อง login ก่อนถึง logout ได้
if (!is_authenticated()) {
    redirect('/auth/login.php');
}

// Log ก่อน destroy session
$user_id = get_auth_user_id();
log_security_event('logout', $user_id, 'success', null);

// Destroy session
logout_user();

// Redirect
redirect('/auth/login.php');
