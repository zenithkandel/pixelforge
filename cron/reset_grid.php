<?php
/**
 * Grid Reset Script - Run every Sunday at 00:00 UTC
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/bootstrap.php';

try {
    $pdo = get_db();
    $redis = get_redis();

    $pdo->beginTransaction();

    $stmt = $pdo->query("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1");
    $current_session = $stmt->fetch();

    if (!$current_session) {
        throw new Exception('No current grid session found');
    }

    $current_session_id = $current_session['id'];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_pixels, COUNT(DISTINCT owner_id) as unique_painters
        FROM pixels WHERE grid_session_id = ?
    ");
    $stmt->execute([$current_session_id]);
    $stats = $stmt->fetch();

    $stmt = $pdo->prepare("UPDATE grid_sessions SET is_current = 0, ended_at = NOW(), total_pixels_painted = ?, unique_painters = ? WHERE id = ?");
    $stmt->execute([$stats['total_pixels'], $stats['unique_painters'], $current_session_id]);

    $stmt = $pdo->prepare("INSERT INTO grid_sessions (is_current) VALUES (1)");
    $stmt->execute();
    $new_session_id = $pdo->lastInsertId();

    $pdo->exec("DELETE FROM pixels");

    $pdo->exec("UPDATE chunks SET version = 0, last_updated = NOW()");

    $pdo->commit();

    if ($redis) {
        $keys = $redis->keys('chunk:*');
        if (!empty($keys)) {
            $redis->del($keys);
        }

        $version_keys = $redis->keys('chunk_v:*');
        if (!empty($version_keys)) {
            $redis->del($version_keys);
        }

        $redis->publish('sse_channel', json_encode([
            'type' => 'grid_reset',
            'new_session_id' => $new_session_id
        ]));
    }

    $log_file = APP_ROOT . '/logs/cron.log';
    $message = sprintf("[%s] Grid reset completed. Session %d had %d pixels from %d painters. New session: %d\n",
        date('Y-m-d H:i:s'),
        $current_session_id,
        $stats['total_pixels'],
        $stats['unique_painters'],
        $new_session_id
    );
    @file_put_contents($log_file, $message, FILE_APPEND);

    echo "Grid reset completed successfully.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $log_file = APP_ROOT . '/logs/errors.log';
    @file_put_contents($log_file, sprintf("[%s] Grid reset failed: %s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);

    echo "Grid reset failed: " . $e->getMessage() . "\n";
    exit(1);
}