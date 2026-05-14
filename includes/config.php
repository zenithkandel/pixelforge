<?php
// includes/config.php

// Load .env file
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Define Constants
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_SECRET', $_ENV['APP_SECRET'] ?? '');
define('GAME_HMAC_KEY', $_ENV['GAME_HMAC_KEY'] ?? '');

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'pixelforge');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('REDIS_HOST', $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT', $_ENV['REDIS_PORT'] ?? '6379');
define('REDIS_PASS', $_ENV['REDIS_PASS'] ?? '');
define('REDIS_DB', (int)($_ENV['REDIS_DB'] ?? 0));
define('REDIS_SESSION_DB', (int)($_ENV['REDIS_SESSION_DB'] ?? 1));

define('GRID_RESET_DAY', (int)($_ENV['GRID_RESET_DAY'] ?? 0)); // 0 = Sunday
define('GRID_PIXEL_COST', (int)($_ENV['GRID_PIXEL_COST'] ?? 1));
