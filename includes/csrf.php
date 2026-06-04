<?php
require_once __DIR__ . '/auth.php';

function csrf_token() {
    start_safe_session();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify() {
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), $_POST['csrf_token']);
}

function csrf_header_verify() {
    return isset($_SERVER['HTTP_X_CSRF_TOKEN']) && hash_equals(csrf_token(), $_SERVER['HTTP_X_CSRF_TOKEN']);
}
