<?php
/**
 * Clean up old login attempts - Run daily at 04:00 UTC
 */

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/includes/bootstrap.php';

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $deleted = $stmt->rowCount();

    $log_file = APP_ROOT . '/logs/cron.log';
    $message = sprintf(
        "[%s] Login attempts cleanup: deleted %d old records\n",
        date('Y-m-d H:i:s'),
        $deleted
    );
    @file_put_contents($log_file, $message, FILE_APPEND);

    echo "Login attempts cleanup completed. Deleted: {$deleted}\n";

} catch (Exception $e) {
    $log_file = APP_ROOT . '/logs/errors.log';
    @file_put_contents($log_file, sprintf("[%s] Login attempts cleanup failed: %s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
    echo "Cleanup failed: " . $e->getMessage() . "\n";
    exit(1);
}