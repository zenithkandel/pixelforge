<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');

$data = get_json_body();

if (!isset($data['csrf_token']) || !isset($data['username']) || !isset($data['password'])) {
    respond_error('missing_fields', 'All fields are required', 400);
}

require_csrf($data['csrf_token']);

if (!check_rate_limit('login_fail:' . get_client_ip(), 5, 900)) {
    respond_error('locked_out', 'Too many failed attempts. Try again in 15 minutes.', 429);
}

$pdo = get_db();
$redis = get_redis();

$stmt = $pdo->prepare("SELECT id, username, email, password_hash, pxl_balance, total_pxl_earned, login_streak, last_login_date, email_verified, failed_login_count, lockout_until, is_banned, ban_reason FROM users WHERE username = ?");
$stmt->execute([$data['username']]);
$user = $stmt->fetch();

if (!$user) {
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 0)");
    $stmt->execute([get_client_ip(), $data['username']]);
    respond_error('invalid_credentials', 'Invalid username or password', 401);
}

if ($user['is_banned']) {
    respond_error('banned', $user['ban_reason'] ?? 'Account banned', 403);
}

if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
    respond_error('locked_out', 'Account locked. Try again in 15 minutes.', 429);
}

if (!password_verify($data['password'], $user['password_hash'])) {
    $failed = $user['failed_login_count'] + 1;
    $lockout = $failed >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
    $stmt = $pdo->prepare("UPDATE users SET failed_login_count = ?, lockout_until = ? WHERE id = ?");
    $stmt->execute([$failed, $lockout, $user['id']]);
    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 0)");
    $stmt->execute([get_client_ip(), $data['username']]);
    respond_error('invalid_credentials', 'Invalid username or password', 401);
}

$stmt = $pdo->prepare("UPDATE users SET failed_login_count = 0, lockout_until = NULL WHERE id = ?");
$stmt->execute([$user['id']]);

session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];

$today = date('Y-m-d');
$last_login = $user['last_login_date'];
$streak_bonus_earned = 0;
$daily_bonus_earned = 0;

if ($last_login !== $today) {
    $yesterday = date('Y-m-d', strtotime('yesterday'));
    $new_streak = ($last_login === $yesterday) ? $user['login_streak'] + 1 : 1;

    $streak_bonus_map = [1 => 2, 2 => 3, 3 => 5, 5 => 8, 7 => 15, 14 => 25, 30 => 50];
    $streak_bonus = $streak_bonus_map[$new_streak] ?? ($new_streak > 30 ? 50 : 0);

    if ($streak_bonus > 0) {
        $streak_bonus_earned = $streak_bonus;
        $pdo->beginTransaction();
        credit_pxl($pdo, $user['id'], $streak_bonus, 'streak_bonus', '', "Login streak {$new_streak} days");
        $pdo->commit();
    }

    $stmt = $pdo->prepare("UPDATE users SET login_streak = ?, last_login_date = ? WHERE id = ?");
    $stmt->execute([$new_streak, $today, $user['id']]);

    check_and_grant_achievements($pdo, $user['id'], 'login', []);

    $daily_bonus_key = "daily_bonus:{$user['id']}:{$today}";
    if (!$redis->exists($daily_bonus_key)) {
        $redis->setex($daily_bonus_key, 86400, '1');
        $daily_bonus_earned = 5;
        $pdo->beginTransaction();
        credit_pxl($pdo, $user['id'], 5, 'daily_bonus', '', 'Daily login bonus');
        $pdo->commit();
    }
}

$stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 1)");
$stmt->execute([get_client_ip(), $data['username']]);

log_audit('login', $user['id']);

respond_success([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'pxl_balance' => $user['pxl_balance'] + $streak_bonus_earned + $daily_bonus_earned,
    'login_streak' => ($last_login !== $today) ? (($last_login === date('Y-m-d', strtotime('yesterday'))) ? $user['login_streak'] + 1 : 1) : $user['login_streak'],
    'daily_bonus_earned' => $daily_bonus_earned,
    'streak_bonus_earned' => $streak_bonus_earned,
]);