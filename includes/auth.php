<?php

function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        respond_error('unauthorized', 'User not authenticated', 401);
    }
}

function require_verified() {
    require_auth();
    
    $user = Database::fetch(
        'SELECT is_email_verified FROM users WHERE id = ?',
        [$_SESSION['user_id']]
    );
    
    if (!$user || !$user['is_email_verified']) {
        respond_error('email_not_verified', 'Email not verified', 403);
    }
}

function get_current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_current_user_data() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return null;
    }
    
    return Database::fetch(
        'SELECT id, username, email, pxl_balance, created_at, is_email_verified, login_streak FROM users WHERE id = ?',
        [$user_id]
    );
}

function login_user($user_id) {
    regenerate_session();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['logged_in_at'] = time();
}

function logout_user() {
    destroy_session();
}

?>
