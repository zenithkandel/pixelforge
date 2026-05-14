<?php
// includes/bootstrap.php

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Turn off in production

// Custom exception handler
set_exception_handler(function($e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => 'An internal server error occurred.']);
    exit;
});

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/redis.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/game_validator.php';
require_once __DIR__ . '/pxl.php';
require_once __DIR__ . '/achievement.php';

// Session Security Config
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Enable when using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);
ini_set('session.gc_maxlifetime', 86400);

session_name('PXLSESS');
if (class_exists('Redis')) {
    session_set_save_handler(new RedisSessionHandler(), true);
}
session_start();

set_security_headers();
