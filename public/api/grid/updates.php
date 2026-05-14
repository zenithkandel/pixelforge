<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$ip = get_client_ip();
$redis = get_redis();

if (!$redis) {
    http_response_code(503);
    echo "Service unavailable";
    exit;
}

$redis->select(REDIS_DB);

$redis_key = "sse_connections";
$current_connections = (int)$redis->get($redis_key);

if ($current_connections >= 100) {
    http_response_code(429);
    echo "Too many connections";
    exit;
}

$redis->incr($redis_key);
$redis->expire($redis_key, 60);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');
header('Transfer-Encoding: chunked');

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}

ini_set('output_buffering', 0);
ini_set('implicit_flush', 1);
ob_end_clean();

set_time_limit(0);
ignore_user_abort(false);

$last_heartbeat = time();
$client_subscribed_chunks = [];

if (isset($_GET['chunks'])) {
    $chunks = explode(',', $_GET['chunks']);
    foreach ($chunks as $chunk) {
        $parts = explode('_', trim($chunk));
        if (count($parts) === 2) {
            $client_subscribed_chunks[] = ['cx' => (int)$parts[0], 'cy' => (int)$parts[1]];
        }
    }
}

function send_event($data) {
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

$ctx = $redis->pubSubLoop(['sse_channel']);

foreach ($ctx as $message) {
    if ($message['type'] === 'message') {
        $event = json_decode($message['payload'], true);

        if (!empty($client_subscribed_chunks)) {
            $event_cx = $event['cx'] ?? -1;
            $event_cy = $event['cy'] ?? -1;
            $subscribed = false;

            foreach ($client_subscribed_chunks as $sub) {
                if ($sub['cx'] === $event_cx && $sub['cy'] === $event_cy) {
                    $subscribed = true;
                    break;
                }
            }

            if (!$subscribed) {
                continue;
            }
        }

        send_event($event);
    }

    if (connection_aborted()) {
        break;
    }

    if (time() - $last_heartbeat >= 25) {
        send_event(['type' => 'heartbeat', 'server_time' => time()]);
        $last_heartbeat = time();
    }
}

$redis->decr($redis_key);