<?php
// includes/rate_limit.php

function check_rate_limit($action, $ip, $max_requests, $window_seconds) {
    $redis = RedisClient::getInstance();
    $key = "rl:$action:$ip";
    
    $current = $redis->incr($key);
    if ($current == 1) {
        $redis->expire($key, $window_seconds);
    }
    
    if ($current > $max_requests) {
        require_once __DIR__ . '/response.php';
        respond_error('rate_limited', 'Too many requests. Please try again later.', 429);
    }
}
