<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();

$user = get_current_user_data();

respond_success([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'pxl_balance' => $user['pxl_balance'],
    'login_streak' => $user['login_streak'],
    'email_verified' => (bool)$user['email_verified']
]);
