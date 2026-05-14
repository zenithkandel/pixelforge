<?php
declare(strict_types=1);
error_reporting(0);
ignore_user_abort(true);
set_time_limit(0);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

if (!check_rate_limit('sse:' . get_client_ip(), 2, 0)) {
    http_response_code(429);
    exit;
}

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

$last_heartbeat = time();

while (!connection_aborted()) {
    if ((time() - $last_heartbeat) >= 25) {
        $last_heartbeat = time();
        echo ": heartbeat\n\n";
        if (function_exists('flush')) {
            flush();
        }
    }

    usleep(100000);
}