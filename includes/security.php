<?php
// includes/security.php

function set_security_headers() {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';");
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf() {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        require_once __DIR__ . '/response.php';
        respond_error('invalid_csrf', 'Invalid CSRF token', 403);
    }
}
