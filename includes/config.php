<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($line, '=')) {
            continue;
        }
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
define('DB_USER', $_ENV['DB_USER'] ?? 'pixelforge_user');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', (int)($_ENV['REDIS_PORT'] ?? 6379));
define('REDIS_PASS', $_ENV['REDIS_PASS'] ?? '');
define('REDIS_DB', (int)($_ENV['REDIS_DB'] ?? 0));
define('REDIS_SESSION_DB', (int)($_ENV['REDIS_SESSION_DB'] ?? 1));

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.example.com');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', $_ENV['SMTP_FROM'] ?? 'noreply@pixelforge.example');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'PixelForge');

define('ADMIN_USERNAME', $_ENV['ADMIN_USERNAME'] ?? 'admin');
define('ADMIN_PASSWORD_HASH', $_ENV['ADMIN_PASSWORD_HASH'] ?? '');

define('GRID_RESET_DAY', (int)($_ENV['GRID_RESET_DAY'] ?? 0));
define('GRID_PIXEL_COST', (int)($_ENV['GRID_PIXEL_COST'] ?? 1));

define('MAX_SCORE_PER_SECOND_HARD', 200);
define('MAX_SCORE_PER_SECOND_SUSTAINED', 80);
define('GRID_SIZE', 800);
define('CHUNK_SIZE', 64);
define('CHUNKS_PER_SIDE', GRID_SIZE / CHUNK_SIZE);
define('PIXEL_COST_PXL', 1);
define('PXL_PER_200_SCORE', 1);

define('SESSION_NAME', 'PXLSESS');
define('SESSION_TTL', 86400);

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', dirname(__DIR__) . '/logs/errors.log');
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}