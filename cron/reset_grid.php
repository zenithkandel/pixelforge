<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

error_log('[Grid Reset] Starting grid reset...');

try {
    $pdo = get_db();

    $pdo->exec("SET autocommit = 0");
    $pdo->exec("START TRANSACTION");

    $stmt = $pdo->prepare("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1");
    $stmt->execute();
    $current = $stmt->fetch();
    if (!$current) throw new Exception("No current grid session found");
    $currentId = $current['id'];

    $stmt = $pdo->prepare("INSERT INTO grid_sessions (is_current) VALUES (0)");
    $stmt->execute();
    $newId = $pdo->lastInsertId();

    $pdo->prepare("UPDATE grid_sessions SET ended_at = NOW(), is_current = 0 WHERE id = ?")->execute([$currentId]);

    $pdo->prepare("UPDATE pixels SET grid_session_id = ? WHERE grid_session_id = ?")->execute([$newId, $currentId]);

    $pdo->prepare("UPDATE chunks SET version = version + 1")->execute();

    $redis = get_redis();
    for ($cx = 0; $cx < 32; $cx++) {
        for ($cy = 0; $cy < 32; $cy++) {
            $redis->del("chunk:{$cx}:{$cy}");
            $redis->incr("chunk_v:{$cx}:{$cy}");
        }
    }

    $pdo->exec("COMMIT");
    $pdo->exec("SET autocommit = 1");

    $redis->publish('sse_channel', json_encode([
        'type' => 'grid_reset',
        'message' => 'The Forge has been reset! A new cycle begins.',
    ]));

    error_log("[Grid Reset] Grid reset complete. New session ID: $newId");

} catch (Exception $e) {
    $pdo->exec("ROLLBACK");
    $pdo->exec("SET autocommit = 1");
    error_log("[Grid Reset] ERROR: " . $e->getMessage());
    exit(1);
}