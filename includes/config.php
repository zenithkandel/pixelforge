<?php
/**
 * PixelForge Configuration
 * Loads environment variables and defines constants
 */

define('APP_ROOT', dirname(__DIR__));

$env_file = APP_ROOT . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_SECRET', $_ENV['APP_SECRET'] ?? '');
define('GAME_HMAC_KEY', $_ENV['GAME_HMAC_KEY'] ?? '');

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_NAME', $_ENV['DB_NAME'] ?? 'pixelforge');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', (int)($_ENV['REDIS_PORT'] ?? 6379));
define('REDIS_PASS', $_ENV['REDIS_PASS'] ?? '');
define('REDIS_DB', (int)($_ENV['REDIS_DB'] ?? 0));
define('REDIS_SESSION_DB', (int)($_ENV['REDIS_SESSION_DB'] ?? 1));

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'noreply@pixelforge.local');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'PixelForge');

define('ADMIN_USERNAME', $_ENV['ADMIN_USERNAME'] ?? 'admin');
define('ADMIN_PASSWORD_HASH', $_ENV['ADMIN_PASSWORD_HASH'] ?? '');

define('GRID_RESET_DAY', (int)($_ENV['GRID_RESET_DAY'] ?? 0));
define('GRID_PIXEL_COST', (int)($_ENV['GRID_PIXEL_COST'] ?? 1));

define('GRID_SIZE', 800);
define('CHUNK_SIZE', 64);
define('CHUNKS_PER_ROW', GRID_SIZE / CHUNK_SIZE);
define('TOTAL_CHUNKS', CHUNKS_PER_ROW * CHUNKS_PER_ROW);
define('PIXELS_PER_CHUNK', CHUNK_SIZE * CHUNK_SIZE);
define('CHUNK_DATA_SIZE', PIXELS_PER_CHUNK * 3);
define('PIXEL_COST_PXL', 1);
define('PXL_PER_200_SCORE', 1);

define('MAX_SCORE_PER_SECOND_HARD', 200);
define('MAX_SCORE_PER_SECOND_SUSTAINED', 80);
define('GAME_SESSION_TIMEOUT', 7200);
define('SESSION_TTL', 86400);

define('DEFAULT_BG_COLOR', '#FFFFFF');
define('DEFAULT_BG_R', 255);
define('DEFAULT_BG_G', 255);
define('DEFAULT_BG_B', 255);

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', APP_ROOT . '/logs/errors.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

ini_set('open_basedir', APP_ROOT);
ini_set('allow_url_fopen', 0);
ini_set('expose_php', 0);

session_name('PXLSESS');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);
ini_set('session.gc_maxlifetime', SESSION_TTL);

function get_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

function get_web_root(): string {
    return dirname($_SERVER['SCRIPT_NAME'] ?? '');
}