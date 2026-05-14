<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

try {
    $pdo = get_db();
    $cutoff = date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt = $pdo->prepare("DELETE FROM checkpoints WHERE created_at < ?");
    $stmt->execute([$cutoff]);

    $stmt = $pdo->prepare("DELETE FROM game_sessions WHERE started_at < ? AND ended_at IS NULL");
    $stmt->execute([$cutoff]);

    error_log("[Cleanup] Removed old game sessions and checkpoints older than 7 days");
} catch (Exception $e) {
    error_log("[Cleanup] ERROR: " . $e->getMessage());
    exit(1);
}