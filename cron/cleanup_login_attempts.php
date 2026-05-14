<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

try {
    $pdo = get_db();
    $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
    $stmt->execute([$cutoff]);
    error_log("[Cleanup] Removed login attempts older than 30 days");
} catch (Exception $e) {
    error_log("[Cleanup] ERROR: " . $e->getMessage());
    exit(1);
}