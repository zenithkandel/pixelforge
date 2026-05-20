<?php
// Minimal config for redone app
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelforge');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'PixelFlap (Redone)');
define('APP_URL', 'http://localhost/codes/pixelforge/redone');

session_start();
require_once __DIR__ . '/includes/db.php';
?>