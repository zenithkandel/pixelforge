<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelforge');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'PixelFlap');
define('APP_URL', 'http://localhost/codes/pixelforge');

define('PIXEL_COST', 5);
define('PIXEL_EXPIRY_DAYS', 14);
define('PIXEL_DECAY_WARNING_DAYS', 3);

define('CURRENCY_PER_SCORE', 2);
define('XP_PER_SCORE', 1);
define('XP_PIXEL_PLACED', 5);
define('XP_PIXEL_REPAINT', 1);
define('XP_ACHIEVEMENT', 20);
define('XP_STREAK_BONUS', 10);

define('MAX_COINS_PER_GAME', 50);
define('MAX_SCORE', 500);
define('GAME_TOKEN_EXPIRY_MINUTES', 10);

define('RATE_LIMIT_LOGIN_ATTEMPTS', 5);
define('RATE_LIMIT_LOGIN_WINDOW', 900);
define('RATE_LIMIT_PIXEL_PLACEMENTS', 10);
define('RATE_LIMIT_PIXEL_WINDOW', 60);
define('RATE_LIMIT_REGISTER', 3);
define('RATE_LIMIT_REGISTER_WINDOW', 3600);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 7200);

session_start();