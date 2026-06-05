<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

try {
    $pdo = db();

    $tables = ['users', 'pixels', 'game_sessions', 'score_log', 'achievements', 'user_achievements', 'login_attempts', 'transactions'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    $schema = file_get_contents(__DIR__ . '/../database/schema.sql');
    if ($schema) {
        $pdo->exec($schema);
        echo json_encode(['success' => true, 'message' => 'Database reset: 8 tables recreated, 16 achievements seeded']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schema file not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Reset failed: ' . $e->getMessage()]);
}
