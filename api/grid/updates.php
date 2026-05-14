<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

// This is a Server-Sent Events endpoint for real-time grid updates
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Disable buffering
if (ob_get_level())
    ob_end_clean();
ob_implicit_flush(true);

// Rate limit SSE connections
$ip = get_client_ip();
if (!RateLimit::check("sse:$ip", 2, 0)) {
    http_response_code(429);
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Too many connections']) . "\n\n";
    exit();
}

// Send heartbeat every 25 seconds
$last_heartbeat = time();
$heartbeat_interval = 25;

// Subscribe to grid updates
try {
    $redis = Redis::getInstance()->getConnection();
    $redis->setOption(\Redis::OPT_READ_TIMEOUT, -1);

    // Send initial connection message
    echo "event: connected\n";
    echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
    flush();

    // Simple polling for Redis pub/sub (since stream reading can be complex)
    // In production, use a proper message queue or dedicated SSE server
    while (true) {
        // Send heartbeat
        if (time() - $last_heartbeat >= $heartbeat_interval) {
            echo "event: heartbeat\n";
            echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
            flush();
            $last_heartbeat = time();
        }

        // Check for updates in Redis (simplified approach)
        $update = Redis::get('grid_update_buffer');
        if ($update) {
            echo "event: grid_update\n";
            echo "data: $update\n\n";
            flush();
            Redis::del('grid_update_buffer');
        }

        // Check for client disconnect
        if (connection_aborted()) {
            break;
        }

        usleep(100000); // 100ms
    }
} catch (Exception $e) {
    log_error('SSE connection failed', ['error' => $e->getMessage()]);
    echo "event: error\n";
    echo "data: " . json_encode(['message' => 'Connection failed']) . "\n\n";
    exit();
}

?>