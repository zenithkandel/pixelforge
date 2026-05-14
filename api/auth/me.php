<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

require_auth();

$user = get_current_user_data();

if (!$user) {
    respond_error('user_not_found', 'User not found', 404);
}

respond_success([
    'id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'pxl_balance' => (int)$user['pxl_balance'],
    'is_email_verified' => (int)$user['is_email_verified'],
    'login_streak' => (int)$user['login_streak'],
    'created_at' => $user['created_at']
], 'User data retrieved successfully');

?>
