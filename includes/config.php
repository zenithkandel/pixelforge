<?php

// Load .env file
$env_file = dirname(__DIR__) . '/.env';
if (!file_exists($env_file)) {
    die('ERROR: .env file not found');
}

$env_vars = parse_ini_file($env_file);
foreach ($env_vars as $key => $value) {
    putenv("$key=$value");
}

// Define constants from environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_SECRET', getenv('APP_SECRET'));
define('GAME_HMAC_KEY', getenv('GAME_HMAC_KEY'));

define('DB_HOST', getenv('DB_HOST'));
define('DB_PORT', getenv('DB_PORT'));
define('DB_NAME', getenv('DB_NAME'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));

define('REDIS_HOST', getenv('REDIS_HOST'));
define('REDIS_PORT', getenv('REDIS_PORT'));
define('REDIS_PASS', getenv('REDIS_PASS'));
define('REDIS_DB', getenv('REDIS_DB'));
define('REDIS_SESSION_DB', getenv('REDIS_SESSION_DB'));

define('SMTP_HOST', getenv('SMTP_HOST'));
define('SMTP_PORT', getenv('SMTP_PORT'));
define('SMTP_USER', getenv('SMTP_USER'));
define('SMTP_PASS', getenv('SMTP_PASS'));
define('SMTP_FROM', getenv('SMTP_FROM'));

define('ADMIN_USERNAME', getenv('ADMIN_USERNAME'));
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH'));

define('GRID_RESET_DAY', getenv('GRID_RESET_DAY'));
define('GRID_PIXEL_COST', getenv('GRID_PIXEL_COST'));
define('GRID_SIZE', getenv('GRID_SIZE'));
define('GRID_CHUNK_SIZE', getenv('GRID_CHUNK_SIZE'));

define('APP_URL', getenv('APP_URL'));
define('APP_DOMAIN', getenv('APP_DOMAIN'));

// Application constants
define('SESSION_TIMEOUT', 86400); // 24 hours
define('BCRYPT_COST', 12);
define('MAX_SCORE_PER_SECOND_HARD', 200);
define('MAX_SCORE_SUSTAINED', 80);
define('PROJECT_ROOT', dirname(__DIR__));
define('LOG_DIR', PROJECT_ROOT . '/logs');

// Ensure log directory exists and is writable
if (!is_dir(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Error reporting
if (APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . '/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_DIR . '/error.log');
}

// Timezone
date_default_timezone_set('UTC');

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data:; font-src \'self\';');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

?>
