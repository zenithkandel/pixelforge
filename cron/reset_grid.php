<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    die("CLI only.");
}

$db = DB::getInstance();
$redis = RedisClient::getInstance();

try {
    $db->beginTransaction();
    
    // Get current grid_session_id
    $stmt = $db->prepare("SELECT id FROM grid_sessions WHERE is_current = 1 FOR UPDATE");
    $stmt->execute();
    $current_id = $stmt->fetchColumn();
    
    if (!$current_id) {
        die("No active grid session found.\n");
    }
    
    // Take snapshot (skip actual PNG rendering for now)
    $snapshot_filename = "grid_snapshot_{$current_id}_" . time() . ".png";
    
    $stmt = $db->prepare("SELECT COUNT(*), COUNT(DISTINCT owner_id) FROM pixels WHERE grid_session_id = ?");
    $stmt->execute([$current_id]);
    list($total_pixels, $unique_painters) = $stmt->fetch(\PDO::FETCH_NUM);
    
    // End current
    $stmt = $db->prepare("UPDATE grid_sessions SET is_current = 0, ended_at = NOW(), snapshot_filename = ?, total_pixels_painted = ?, unique_painters = ? WHERE id = ?");
    $stmt->execute([$snapshot_filename, $total_pixels, $unique_painters, $current_id]);
    
    // Start new
    $stmt = $db->prepare("INSERT INTO grid_sessions (is_current) VALUES (1)");
    $stmt->execute();
    
    // Truncate pixels
    $db->exec("DELETE FROM pixels"); // Truncate might cause implicit commit, use DELETE
    
    // Reset chunk versions
    $db->exec("UPDATE chunks SET version = 0");
    
    $db->commit();
    
    // Clear Redis caches
    for ($cx = 0; $cx < 32; $cx++) {
        for ($cy = 0; $cy < 32; $cy++) {
            $redis->del("chunk:$cx:$cy");
            $redis->setex("chunk_v:$cx:$cy", 300, 0);
        }
    }
    
    // Broadcast
    $redis->publish('sse_channel', json_encode(['type' => 'grid_reset']));
    
    echo "Grid reset successfully.\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "Grid reset failed: " . $e->getMessage() . "\n";
}
