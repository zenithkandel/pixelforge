<?php
require_once __DIR__ . '/db.php';

function start_safe_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please log in to continue';
        header('Location: /');
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([current_user_id()]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function is_admin() {
    return current_user()['role'] === 'admin';
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die("403 Forbidden");
    }
}

function login_user($user_id, $username) {
    start_safe_session();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    regenerate_session();
    
    $stmt = db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    
    return true;
}

function logout_user() {
    session_start();
    session_destroy();
    $_SESSION = [];
}
