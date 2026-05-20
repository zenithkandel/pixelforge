<?php

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        log_warn('AUTH', 'Unauthenticated access attempt', ['page' => $_SERVER['REQUEST_URI'] ?? 'unknown']);
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
    $db = get_db();
    try {
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            log_warn('AUTH', 'Session user no longer exists — logged out');
            header('Location: ' . BASE_URL . '/login.php');
            exit();
        }
    } catch (PDOException $e) {
        log_error('DB', 'Database error in require_login: ' . $e->getMessage(), ['code' => $e->getCode()]);
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
        exit();
    }
}

function is_admin(): bool {
    if (!is_logged_in()) return false;
    $db = get_db();
    try {
        $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
        return $user && $user['role'] === 'admin';
    } catch (PDOException $e) {
        log_error('DB', 'Database error in is_admin: ' . $e->getMessage(), ['code' => $e->getCode()]);
        return false;
    }
}

function require_admin(): void {
    if (!is_logged_in()) {
        log_sec('ADMIN', 'Unauthorized admin access attempt');
        http_response_code(403);
        exit('Access denied');
    }
    if (!is_admin()) {
        log_sec('ADMIN', 'Non-admin user attempted admin access', ['user_id' => $_SESSION['user_id']]);
        http_response_code(403);
        exit('Access denied');
    }
    log_admin('ADMIN', 'Admin panel accessed', ['page' => basename($_SERVER['PHP_SELF'])]);
}

if (!function_exists('get_current_user')) {
    function get_current_user(): ?array {
        if (!is_logged_in()) return null;
        $db = get_db();
        try {
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([(int)$_SESSION['user_id']]);
            return $stmt->fetch() ?: null;
        } catch (PDOException $e) {
            log_error('DB', 'Database error in get_current_user: ' . $e->getMessage(), ['code' => $e->getCode()]);
            return null;
        }
    }
}