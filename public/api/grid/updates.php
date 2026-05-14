<?php
declare(strict_types=1);
declare(strict_globals=1);
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

$chunks_param = $_GET['chunks'] ?? '';
$subscribed_chunks = [];
if ($chunks_param !== '') {
    $pairs = explode(',', $chunks_param);
    foreach ($pairs as $pair) {
        $parts = explode(':', $pair);
        if (count($parts) === 2) {
            $subscribed_chunks[] = [(int)$parts[0], (int)$parts[1]];
        }
    }
}

if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

$last_heartbeat = time();
$redis = get_redis();

while (!connection_aborted()) {
    $result = $redis->subscribe(['sse_channel'], function ($ch, $msg) use (&$subscribed_chunks, &$last_heartbeat) {
        echo "data: {$msg}\n\n";
        if (function_exists('flush')) {
            flush();
        }
    });

    if ((time() - $last_heartbeat) >= 25) {
        $last_heartbeat = time();
        echo ": heartbeat\n\n";
        if (function_exists('flush')) {
            flush();
        }
    }

    usleep(100000);
}