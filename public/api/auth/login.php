<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Method not allowed', 405);
}

require_csrf();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
check_rate_limit('login_fail', $ip, 5, 900); // We will increment this only on fail

$json = json_decode(file_get_contents('php://input'), true);
$username = trim($json['username'] ?? '');
$password = $json['password'] ?? '';

if (empty($username) || empty($password)) {
    respond_error('invalid_input', 'Username and password are required.');
}

$db = DB::getInstance();
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && $user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
    respond_error('locked_out', 'Account is temporarily locked due to multiple failed login attempts.');
}

if (!$user || !password_verify($password, $user['password_hash'])) {
    if ($user) {
        $fails = $user['failed_login_count'] + 1;
        $lockout = ($fails >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;
        $stmt = $db->prepare("UPDATE users SET failed_login_count = ?, lockout_until = ? WHERE id = ?");
        $stmt->execute([$fails, $lockout, $user['id']]);
    }
    
    // Also track by IP
    $redis = RedisClient::getInstance();
    $redis->incr("rl:login_fail:$ip");
    
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 0)");
    $stmt->execute([$ip, $username]);
    
    respond_error('invalid_credentials', 'Invalid username or password.');
}

if ($user['is_banned']) {
    respond_error('banned', 'This account has been banned.');
}

// Success! Reset fails.
$stmt = $db->prepare("UPDATE users SET failed_login_count = 0, lockout_until = NULL WHERE id = ?");
$stmt->execute([$user['id']]);

$stmt = $db->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 1)");
$stmt->execute([$ip, $username]);

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email_verified'] = $user['email_verified'];

// Process daily login streak
$today = date('Y-m-d');
$streak_bonus_earned = 0;
if ($user['last_login_date'] !== $today) {
    $last_login = $user['last_login_date'] ? new DateTime($user['last_login_date']) : null;
    $current_today = new DateTime($today);
    
    $diff = $last_login ? $last_login->diff($current_today)->days : 2; // >1 day default if null
    
    if ($diff == 1) {
        $new_streak = $user['login_streak'] + 1;
    } else {
        $new_streak = 1;
    }
    
    $stmt = $db->prepare("UPDATE users SET login_streak = ?, last_login_date = ? WHERE id = ?");
    $stmt->execute([$new_streak, $today, $user['id']]);
    $user['login_streak'] = $new_streak;
    
    // Check streak milestones
    $milestones = [1 => 2, 2 => 3, 3 => 5, 5 => 8, 7 => 15, 14 => 25, 30 => 50];
    if (isset($milestones[$new_streak])) {
        $streak_bonus_earned = $milestones[$new_streak];
        pxl_credit($user['id'], $streak_bonus_earned, 'streak_bonus', 'streak_'.$new_streak, "Daily login streak ($new_streak days)");
    } elseif ($new_streak > 30 && $new_streak % 30 == 0) {
        $streak_bonus_earned = 50;
        pxl_credit($user['id'], $streak_bonus_earned, 'streak_bonus', 'streak_'.$new_streak, "Daily login streak ($new_streak days)");
    }
    
    // Check achievements
    if ($new_streak >= 3) check_and_grant_achievement($user['id'], 'streak_3');
    if ($new_streak >= 7) check_and_grant_achievement($user['id'], 'streak_7');
    if ($new_streak >= 30) check_and_grant_achievement($user['id'], 'streak_30');
}

respond_success([
    'user_id' => $user['id'],
    'username' => $user['username'],
    'pxl_balance' => $user['pxl_balance'],
    'login_streak' => $user['login_streak'],
    'streak_bonus_earned' => $streak_bonus_earned
]);
