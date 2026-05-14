<?php

require_once dirname(__DIR__) . '/includes/config.php';

// Clean up old game sessions (older than 30 days)

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

    echo "Cleaning up old game sessions...\n";

    // Delete sessions older than 30 days
    $stmt = $pdo->prepare('DELETE FROM game_sessions WHERE ended_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $count = $stmt->execute();

    echo "Deleted " . $stmt->rowCount() . " old game sessions\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>