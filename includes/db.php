<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logger.php';

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            log_error('DB', 'Database connection failed: ' . $e->getMessage(), ['code' => $e->getCode()]);
            throw $e;
        }
    }
    return $pdo;
}