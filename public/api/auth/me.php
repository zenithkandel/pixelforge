<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_method('GET');

$user = require_auth();

$redis = get_redis();
$today = date('Y-m-d');

$daily_bonus_available = true;
if ($redis && has_daily_bonus($redis, $user['id'], $today)) {
    $daily_bonus_available = false;
}

respond_success([
    'id' => $user['id'],
    'username' => $user['username'],
    'email' => $user['email'],
    'pxl_balance' => $user['pxl_balance'],
    'total_pxl_earned' => $user['total_pxl_earned'],
    'total_pxl_spent' => $user['total_pxl_spent'],
    'login_streak' => $user['login_streak'],
    'last_login_date' => $user['last_login_date'],
    'email_verified' => (bool)$user['email_verified'],
    'daily_bonus_available' => $daily_bonus_available,
    'is_banned' => (bool)$user['is_banned'],
    'created_at' => $user['created_at']
]);