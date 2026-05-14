<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$cx = isset($_GET['cx']) ? (int)$_GET['cx'] : -1;
$cy = isset($_GET['cy']) ? (int)$_GET['cy'] : -1;
$client_version = isset($_GET['v']) ? (int)$_GET['v'] : 0;

$ip = get_client_ip();
if (!check_rate_limit("chunk:{$ip}", 200, 60)) {
    respond_error('rate_limited', 'Too many requests', 429);
}

if (!validate_chunk_coord($cx) || !validate_chunk_coord($cy)) {
    respond_error('invalid_coords', 'Invalid chunk coordinates', 400);
}

try {
    $pdo = get_db();
    $redis = get_redis();

    $stmt = $pdo->prepare("SELECT version FROM chunks WHERE chunk_x = ? AND chunk_y = ?");
    $stmt->execute([$cx, $cy]);
    $chunk_info = $stmt->fetch();
    $current_version = (int)($chunk_info['version'] ?? 0);

    if ($client_version > 0 && $client_version >= $current_version) {
        http_response_code(304);
        exit;
    }

    $cache_key = "chunk:{$cx}:{$cy}";

    $chunk_data = null;
    if ($redis) {
        $chunk_data = $redis->get($cache_key);
    }

    if (!$chunk_data) {
        $x_min = $cx * 64;
        $x_max = $x_min + 63;
        $y_min = $cy * 64;
        $y_max = $y_min + 63;

        $stmt = $pdo->prepare("
            SELECT x, y, color FROM pixels
            WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ?
            AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1)
        ");
        $stmt->execute([$x_min, $x_max, $y_min, $y_max]);
        $pixels = $stmt->fetchAll();

        $chunk_data = str_repeat("\xFF\xFF\xFF", 64 * 64);

        foreach ($pixels as $pixel) {
            $lx = $pixel['x'] - $x_min;
            $ly = $pixel['y'] - $y_min;
            $offset = ($ly * 64 + $lx) * 3;
            $color = $pixel['color'];
            $chunk_data[$offset] = chr(hexdec(substr($color, 1, 2)));
            $chunk_data[$offset + 1] = chr(hexdec(substr($color, 3, 2)));
            $chunk_data[$offset + 2] = chr(hexdec(substr($color, 5, 2)));
        }

        if ($redis) {
            $redis->setex($cache_key, 300, $chunk_data);
        }
    }

    header('Content-Type: application/octet-stream');
    header('X-Chunk-Version: ' . $current_version);
    header('X-Chunk-X: ' . $cx);
    header('X-Chunk-Y: ' . $cy);
    header('Cache-Control: no-cache');

    echo $chunk_data;

} catch (Exception $e) {
    log_error('Chunk fetch failed', ['exception' => $e->getMessage(), 'cx' => $cx, 'cy' => $cy]);
    respond_error('server_error', 'Failed to fetch chunk', 500);
}