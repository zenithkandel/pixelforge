<?php

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    if (!is_logged_in()) return false;
    $user = Database::fetch("SELECT role FROM users WHERE id = ?", [$_SESSION['user_id']]);
    return $user && $user['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1><p>Access denied.</p></body></html>';
        exit;
    }
}

function get_current_user() {
    if (!is_logged_in()) return null;
    return Database::fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
}

function logout_user() {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

function check_login_attempts($ip) {
    Database::query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $attempts = Database::fetch(
        "SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$ip]
    );
    return $attempts['cnt'] >= 5;
}

function record_failed_login($ip) {
    Database::query("INSERT INTO login_attempts (ip_address) VALUES (?)", [$ip]);
}

function get_streak_bonus($day) {
    $bonuses = [
        1 => 10, 2 => 20, 3 => 35, 5 => 60, 7 => 150, 14 => 400, 30 => 800
    ];
    foreach ([30, 14, 7, 5, 3, 2, 1] as $threshold) {
        if ($day >= $threshold) return $bonuses[$threshold];
    }
    return 0;
}

function process_login($username_or_email, $password) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (check_login_attempts($ip)) {
        return ['error' => 'Too many login attempts. Please try again in 15 minutes.'];
    }

    $user = Database::fetch(
        "SELECT * FROM users WHERE username = ? OR email = ?",
        [$username_or_email, $username_or_email]
    );

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_login($ip);
        return ['error' => 'Invalid username or password.'];
    }

    login_user($user);

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $streak_bonus = 0;
    if ($user['last_login_date'] === $yesterday) {
        $new_streak = $user['streak_days'] + 1;
    } elseif ($user['last_login_date'] !== $today) {
        $new_streak = 1;
    } else {
        $new_streak = $user['streak_days'];
    }

    if ($user['last_login_date'] !== $today) {
        $streak_bonus = get_streak_bonus($new_streak);
        if ($streak_bonus > 0) {
            Database::query(
                "UPDATE users SET balance = balance + ?, streak_days = ?, last_login_date = ? WHERE id = ?",
                [$streak_bonus, $new_streak, $today, $user['id']]
            );
            $_SESSION['streak_bonus'] = $streak_bonus;
            $_SESSION['streak_days'] = $new_streak;
        } else {
            Database::query(
                "UPDATE users SET last_login_date = ? WHERE id = ?",
                [$today, $user['id']]
            );
        }
    }

    return ['success' => true, 'user' => $user];
}

function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    return strlen($password) >= 8 && preg_match('/[0-9]/', $password) && preg_match('/[a-zA-Z]/', $password);
}

$AVATAR_COLORS = [
    '#7c3aed', '#ef4444', '#3b82f6', '#22c55e', '#f59e0b',
    '#ec4899', '#06b6d4', '#8b5cf6', '#f97316', '#14b8a6'
];

function register_user($username, $email, $password) {
    global $AVATAR_COLORS;

    if (!validate_username($username)) {
        return ['error' => 'Username must be 3-30 characters (letters, numbers, underscore only).'];
    }
    if (!validate_email($email)) {
        return ['error' => 'Invalid email format.'];
    }
    if (!validate_password($password)) {
        return ['error' => 'Password must be at least 8 characters with at least one number and one letter.'];
    }

    $existing = Database::fetch(
        "SELECT id FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );
    if ($existing) {
        return ['error' => 'Username or email already taken.'];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    Database::query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $recent = Database::fetch(
        "SELECT COUNT(*) as cnt FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND (SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)) > 0",
        [$ip]
    );
    if ($recent['cnt'] >= 3) {
        return ['error' => 'Too many registrations from this IP. Please try again later.'];
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $avatar_color = $AVATAR_COLORS[array_rand($AVATAR_COLORS)];

    Database::query(
        "INSERT INTO users (username, email, password_hash, balance, xp, level, avatar_color, streak_days, last_login_date) VALUES (?, ?, ?, 0, 0, 1, ?, 1, CURDATE())",
        [$username, $email, $password_hash, $avatar_color]
    );

    return ['success' => true];
}