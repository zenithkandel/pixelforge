<?php

require_once dirname(__DIR__) . '/includes/config.php';

// This script resets the canvas every 7 days (Sunday 00:00 UTC)

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

    echo "Starting grid reset...\n";

    // Get current grid session
    $stmt = $pdo->prepare('SELECT id FROM grid_sessions WHERE is_current = 1');
    $stmt->execute();
    $current_grid = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_grid) {
        echo "ERROR: No current grid session found\n";
        exit(1);
    }

    // Mark current grid as completed
    $stmt = $pdo->prepare('UPDATE grid_sessions SET is_current = 0, ended_at = NOW() WHERE id = ?');
    $stmt->execute([$current_grid['id']]);

    // Get grid stats
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM pixels WHERE grid_session_id = ?');
    $stmt->execute([$current_grid['id']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Create new grid session
    $stmt = $pdo->prepare('INSERT INTO grid_sessions (is_current, started_at) VALUES (1, NOW())');
    $stmt->execute();
    $new_grid_id = $pdo->lastInsertId();

    // Clear all pixels and chunks from current tables
    $stmt = $pdo->prepare('DELETE FROM pixels WHERE grid_session_id = ?');
    $stmt->execute([$current_grid['id']]);

    $stmt = $pdo->prepare('DELETE FROM chunks WHERE grid_session_id = ?');
    $stmt->execute([$current_grid['id']]);

    // Clear Redis caches
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    $redis->select(0);

    // Clear all chunk caches
    $pattern = 'chunk:' . $current_grid['id'] . ':*';
    $keys = $redis->keys($pattern);
    foreach ($keys as $key) {
        $redis->del($key);
    }

    echo "Grid reset completed successfully\n";
    echo "Previous grid: {$stats['count']} pixels\n";
    echo "New grid session ID: $new_grid_id\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>