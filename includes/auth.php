<?php
// includes/auth.php

function require_auth() {
    if (empty($_SESSION['user_id'])) {
        require_once __DIR__ . '/response.php';
        respond_error('unauthenticated', 'You must be logged in', 401);
    }
}

function require_verified() {
    require_auth();
    if (empty($_SESSION['email_verified'])) {
        require_once __DIR__ . '/response.php';
        respond_error('unverified', 'Please verify your email address', 403);
    }
}

function get_current_user_data() {
    if (empty($_SESSION['user_id'])) return null;
    
    $db = DB::getInstance();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
