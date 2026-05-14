<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_method('POST');

$ip = get_client_ip();

if (!check_rate_limit("login_fail:{$ip}", 5, 900)) {
    respond_error('locked_out', 'Too many failed attempts. Please try again in 15 minutes.', 429);
}

$data = get_json_body();

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

if (empty($username) || empty($password)) {
    respond_error('invalid_credentials', 'Username and password are required', 400);
}

if (!verify_csrf($csrf_token)) {
    respond_error('invalid_csrf', 'Invalid CSRF token', 403);
}

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 0)");
        $stmt->execute([$ip, $username]);

        check_rate_limit("login_fail:{$ip}", 5, 900);
        respond_error('invalid_credentials', 'Invalid username or password', 401);
    }

    if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
        respond_error('account_locked', 'This account is temporarily locked', 423);
    }

    if ($user['is_banned']) {
        respond_error('account_banned', 'This account has been banned', 403);
    }

    if (!password_verify($password, $user['password_hash'])) {
        $new_count = $user['failed_login_count'] + 1;
        $lockout = $new_count >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;

        $stmt = $pdo->prepare("UPDATE users SET failed_login_count = ?, lockout_until = ? WHERE id = ?");
        $stmt->execute([$new_count, $lockout, $user['id']]);

        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 0)");
        $stmt->execute([$ip, $username]);

        check_rate_limit("login_fail:{$ip}", 5, 900);
        respond_error('invalid_credentials', 'Invalid username or password', 401);
    }

    $stmt = $pdo->prepare("UPDATE users SET failed_login_count = 0, lockout_until = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', time() - 86400);
    $last_login = $user['last_login_date'];

    $streak = $user['login_streak'];
    $streak_bonus = 0;
    $daily_bonus = 0;

    if ($last_login === $yesterday) {
        $streak++;
    } elseif ($last_login !== $today) {
        $streak = 1;
    }

    $redis = get_redis();
    if ($redis) {
        if (!has_daily_bonus($redis, $user['id'], $today)) {
            $streak_bonus = get_streak_bonus($streak);
            if ($streak_bonus > 0) {
                credit_pxl($pdo, $user['id'], $streak_bonus, 'streak_bonus', '', "Login streak bonus (day {$streak})");
                set_daily_bonus($redis, $user['id'], $today);
                $daily_bonus = $streak_bonus;
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE users SET login_streak = ?, last_login_date = ? WHERE id = ?");
    $stmt->execute([$streak, $today, $user['id']]);

    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 1)");
    $stmt->execute([$ip, $username]);

    log_audit('user_login', $user['id']);

    respond_success([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'pxl_balance' => $user['pxl_balance'] + $daily_bonus,
        'login_streak' => $streak,
        'daily_bonus_earned' => $daily_bonus,
        'streak_bonus_earned' => $streak_bonus,
        'email_verified' => (bool)$user['email_verified']
    ]);

} catch (Exception $e) {
    log_error('Login failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'An error occurred during login', 500);
}