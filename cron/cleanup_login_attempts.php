<?php

require_once dirname(__DIR__) . '/includes/config.php';

// Clean up old login attempts (older than 7 days)

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]
    );

    echo "Cleaning up old login attempts...\n";

    // Delete attempts older than 7 days
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    $stmt->execute();

    echo "Deleted " . $stmt->rowCount() . " old login attempts\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>