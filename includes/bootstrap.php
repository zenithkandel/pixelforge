<?php

// Load configuration first
require_once __DIR__ . '/config.php';

// Load database and cache
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/redis.php';

// Session and security
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/security.php';

// Helper functions
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/validate.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/game_validator.php';
require_once __DIR__ . '/pxl.php';
require_once __DIR__ . '/achievement.php';

// Set security headers
set_security_headers();

// Initialize database and Redis connections (will throw if they fail)
try {
    Database::getInstance();
    Redis::getInstance();
} catch (Exception $e) {
    log_error('Failed to initialize connections', ['error' => $e->getMessage()]);
    respond_error('service_error', 'Service temporarily unavailable', 503);
}

// Global error handler
set_exception_handler(function ($exception) {
    log_error('Uncaught exception', ['exception' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
    respond_error('server_error', 'An error occurred', 500);
});

?>