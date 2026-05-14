<?php
/**
 * Clean up old game sessions - Run daily at 03:00 UTC
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/bootstrap.php';

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("
        DELETE FROM game_sessions
        WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND ended_at IS NOT NULL
    ");
    $stmt->execute();
    $deleted_old = $stmt->rowCount();

    $stmt = $pdo->prepare("
        UPDATE game_sessions
        SET ended_at = NOW(), invalidation_reason = 'timeout'
        WHERE started_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)
        AND ended_at IS NULL
    ");
    $stmt->execute();
    $marked_timeout = $stmt->rowCount();

    $log_file = APP_ROOT . '/logs/cron.log';
    $message = sprintf(
        "[%s] Session cleanup: deleted %d old sessions, marked %d timed-out sessions\n",
        date('Y-m-d H:i:s'),
        $deleted_old,
        $marked_timeout
    );
    @file_put_contents($log_file, $message, FILE_APPEND);

    echo "Cleanup completed. Deleted: {$deleted_old}, Marked timeout: {$marked_timeout}\n";

} catch (Exception $e) {
    $log_file = APP_ROOT . '/logs/errors.log';
    @file_put_contents($log_file, sprintf("[%s] Session cleanup failed: %s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}