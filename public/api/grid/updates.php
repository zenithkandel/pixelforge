<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

set_time_limit(0);
ignore_user_abort(false);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
// In a real app we might want to track connections to limit 2 per IP, but for now we skip strict enforcing as it requires a registry.

$chunks = isset($_GET['chunks']) ? explode(',', $_GET['chunks']) : [];
$subscribed = [];
for ($i = 0; $i < count($chunks); $i += 2) {
    if (isset($chunks[$i]) && isset($chunks[$i+1])) {
        $subscribed[] = $chunks[$i] . '_' . $chunks[$i+1];
    }
}

if (empty($subscribed)) {
    echo "data: {\"type\":\"error\",\"message\":\"No chunks subscribed\"}\n\n";
    flush();
    exit;
}

$redis = RedisClient::getInstance();
if (get_class($redis) === 'MockRedis') {
    // If Redis is not available, just keep the connection alive but do nothing
    while (!connection_aborted()) {
        echo "data: {\"type\":\"heartbeat\",\"server_time\":" . (time() * 1000) . "}\n\n";
        ob_flush();
        flush();
        sleep(25);
    }
    exit;
}

// Subscribe using Redis Pub/Sub
try {
    $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
    
    $last_heartbeat = time();
    
    $redis->subscribe(['sse_channel'], function($redis, $chan, $msg) use (&$subscribed, &$last_heartbeat) {
        if (connection_aborted()) {
            exit;
        }
        
        $event = json_decode($msg, true);
        if ($event && isset($event['cx']) && isset($event['cy'])) {
            $chunk_id = $event['cx'] . '_' . $event['cy'];
            if (in_array($chunk_id, $subscribed)) {
                echo "data: " . $msg . "\n\n";
                ob_flush();
                flush();
            }
        }
        
        // Heartbeat
        if (time() - $last_heartbeat >= 25) {
            echo "data: {\"type\":\"heartbeat\",\"server_time\":" . (time() * 1000) . "}\n\n";
            ob_flush();
            flush();
            $last_heartbeat = time();
        }
    });
} catch (Exception $e) {
    exit;
}
