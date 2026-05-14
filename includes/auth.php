<?php
declare(strict_types=1);

function require_auth(): array {
    if (empty($_SESSION['user_id'])) {
        respond_error('unauthenticated', 'Login required', 401);
    }
    return get_current_user_data();
}

function require_verified(): array {
    $user = require_auth();
    if (!$user['email_verified']) {
        respond_error('email_not_verified', 'Please verify your email first', 403);
    }
    return $user;
}

function require_admin(): array {
    if (empty($_SESSION['admin_id'])) {
        respond_error('unauthenticated', 'Admin access required', 401);
    }
    return ['id' => $_SESSION['admin_id'], 'username' => $_SESSION['admin_username'] ?? ''];
}

function get_current_user_data(): array {
    if (empty($_SESSION['user_id'])) {
        return [];
    }
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, username, email, pxl_balance, total_pxl_earned, total_pxl_spent, login_streak, last_login_date, email_verified, is_banned, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        session_unset();
        session_destroy();
        return [];
    }
    if ($user['is_banned']) {
        session_unset();
        session_destroy();
        respond_error('banned', $user['ban_reason'] ?? 'Account banned', 403);
    }
    return $user;
}

function require_csrf(string $token): void {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        respond_error('invalid_csrf', 'Invalid or missing CSRF token', 403);
    }
}

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && preg_match('/^[\d\.,]+$/', $_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $ip;
}