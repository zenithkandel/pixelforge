<?php

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        log_warn('AUTH', 'Unauthenticated access attempt', ['page' => $_SERVER['REQUEST_URI'] ?? '']);
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
    $_SESSION['role'] = $user['role'];
}

function require_admin(): void {
    require_login();
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['role'] !== 'admin') {
        log_sec('ADMIN', 'Unauthorized admin access attempt');
        http_response_code(403);
        echo '<h1>403 Forbidden</h1><p>Admin access required.</p>';
        exit();
    }
}

function is_admin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function get_current_user(): ?array {
    if (!is_logged_in()) return null;
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, username, email, balance, xp, level, role, streak_days, avatar_color FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function update_last_login(): void {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT last_login_date, streak_days FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return;

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $streak_days = $user['streak_days'];
    $new_streak = 1;
    $streak_bonus = 0;

    if ($user['last_login_date'] === $yesterday) {
        $new_streak = $streak_days + 1;
        $streak_bonus = get_streak_bonus($new_streak);
    } elseif ($user['last_login_date'] === $today) {
        $new_streak = $streak_days;
    }

    if ($streak_bonus > 0) {
        $pdo->prepare('UPDATE users SET balance = balance + ?, streak_days = ?, last_login_date = ? WHERE id = ?')
            ->execute([$streak_bonus, $new_streak, $today, $_SESSION['user_id']]);
        $_SESSION['streak_bonus'] = $streak_bonus;
        log_info('AUTH', 'Daily streak bonus awarded', ['streak' => $new_streak, 'bonus' => $streak_bonus]);
    } else {
        $pdo->prepare('UPDATE users SET streak_days = ?, last_login_date = ? WHERE id = ?')
            ->execute([$new_streak, $today, $_SESSION['user_id']]);
    }
}

function get_streak_bonus(int $days): int {
    $bonuses = [1 => 10, 2 => 20, 3 => 35, 5 => 60, 7 => 150, 14 => 400, 30 => 800];
    $best = 0;
    foreach ($bonuses as $d => $b) {
        if ($days >= $d && $b > $best) $best = $b;
    }
    return $best;
}

function do_login(string $identifier, string $password): array {
    $pdo = get_db();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $stmt = $pdo->prepare('SELECT id, attempts, last_attempt FROM login_attempts WHERE ip_address = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([$ip]);
    $last_attempt = $stmt->fetch();

    if ($last_attempt) {
        $minutes_ago = (time() - strtotime($last_attempt['last_attempt'])) / 60;
        if ($last_attempt['attempts'] >= 5 && $minutes_ago < 15) {
            $remaining = (int)(15 - $minutes_ago) * 60;
            log_sec('AUTH', 'Login rate limit — IP blocked', ['ip' => $ip, 'attempts' => $last_attempt['attempts']]);
            return ['success' => false, 'error' => 'Too many attempts. Try again later.', 'remaining' => $remaining];
        }
    }

    $stmt = $pdo->prepare('SELECT id, password_hash, username, role FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        record_failed_attempt($ip);
        log_sec('AUTH', 'Failed login — user not found', ['input' => substr($identifier, 0, 8)]);
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        record_failed_attempt($ip);
        log_sec('AUTH', 'Failed login — wrong password', ['input' => substr($identifier, 0, 8)]);
        return ['success' => false, 'error' => 'Invalid credentials'];
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    log_info('AUTH', 'User logged in', ['username' => $user['username']]);

    update_last_login();

    return ['success' => true, 'user' => $user];
}

function record_failed_attempt(string $ip): void {
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT id, attempts, last_attempt FROM login_attempts WHERE ip_address = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([$ip]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare('UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE id = ?')->execute([$existing['id']]);
    } else {
        $pdo->prepare('INSERT INTO login_attempts (ip_address, attempts) VALUES (?, 1)')->execute([$ip]);
    }
}

function do_logout(): void {
    log_info('AUTH', 'User logged out');
    session_unset();
    session_destroy();
    session_start();
    session_regenerate_id(true);
}