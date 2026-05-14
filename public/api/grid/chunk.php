<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');

$cx = isset($_GET['cx']) ? (int)$_GET['cx'] : -1;
$cy = isset($_GET['cy']) ? (int)$_GET['cy'] : -1;
$v = isset($_GET['v']) ? (int)$_GET['v'] : null;

if (!validate_chunk_coord($cx) || !validate_chunk_coord($cy)) {
    respond_error('invalid_chunk', 'Chunk coordinates must be 0-31', 400);
}

if (!check_rate_limit('chunk:' . get_client_ip(), 200, 60)) {
    respond_error('rate_limited', 'Too many chunk requests', 429);
}

$redis = get_redis();

$version = (int)($redis->get("chunk_v:{$cx}:{$cy}") ?? 0);

if ($v !== null && $v >= $version) {
    http_response_code(304);
    exit;
}

$chunk_data = $redis->get("chunk:{$cx}:{$cy}");

if ($chunk_data === false) {
    $chunk_data = build_chunk_cache($cx, $cy);
    $version = (int)($redis->get("chunk_v:{$cx}:{$cy}") ?? 0);
}

header('Content-Type: application/octet-stream');
header('X-Chunk-Version: ' . $version);
header('X-Chunk-X: ' . $cx);
header('X-Chunk-Y: ' . $cy);
header('Cache-Control: no-cache');

echo $chunk_data;