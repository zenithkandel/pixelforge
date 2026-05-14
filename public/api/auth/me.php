<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_method('GET');

$user = get_current_user_data();

if (empty($user)) {
    respond_error('unauthenticated', 'Not logged in', 401);
}

respond_success([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'pxl_balance' => $user['pxl_balance'],
    'total_pxl_earned' => $user['total_pxl_earned'],
    'total_pxl_spent' => $user['total_pxl_spent'],
    'login_streak' => $user['login_streak'],
    'email_verified' => (bool)$user['email_verified'],
    'created_at' => $user['created_at'],
]);