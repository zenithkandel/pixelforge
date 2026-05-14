<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

// Get current grid session
$grid_session = Database::fetch('SELECT id FROM grid_sessions WHERE is_current = 1');
if (!$grid_session) {
    respond_error('no_active_grid', 'No active grid session', 404);
}

$cx = intval($_GET['cx'] ?? -1);
$cy = intval($_GET['cy'] ?? -1);

if (!validate_chunk_coordinate($cx) || !validate_chunk_coordinate($cy)) {
    respond_error('invalid_coordinates', 'Invalid chunk coordinates');
}

// Rate limit chunk requests
$ip = get_client_ip();
if (!RateLimit::check("chunk:$ip", 200, 60)) {
    respond_error('rate_limited', 'Rate limited', 429);
}

try {
    // Check if chunk has been cached in Redis
    $chunk_key = "chunk:{$grid_session['id']}:{$cx}:{$cy}";
    $version_key = "chunk_v:{$grid_session['id']}:{$cx}:{$cy}";

    $cached_chunk = Redis::get($chunk_key);
    $cached_version = Redis::get($version_key) ?? 0;

    // Get current version from DB
    $chunk_record = Database::fetch(
        'SELECT version FROM chunks WHERE grid_session_id = ? AND chunk_x = ? AND chunk_y = ?',
        [$grid_session['id'], $cx, $cy]
    );

    $current_version = $chunk_record ? $chunk_record['version'] : 0;

    // If client has same version and we have cached data, return 304
    $client_version = intval($_GET['v'] ?? -1);
    if ($client_version === $current_version && $cached_chunk) {
        http_response_code(304);
        exit();
    }

    // Build chunk from database
    if (!$cached_chunk) {
        $pixels = Database::fetchAll(
            'SELECT x, y, color FROM pixels WHERE grid_session_id = ? AND x >= ? AND x < ? AND y >= ? AND y < ?',
            [$grid_session['id'], $cx * GRID_CHUNK_SIZE, ($cx + 1) * GRID_CHUNK_SIZE, $cy * GRID_CHUNK_SIZE, ($cy + 1) * GRID_CHUNK_SIZE]
        );

        // Create binary chunk data (64x64 pixels, 3 bytes each = 12,288 bytes)
        $chunk_data = str_repeat("\xFF\xFF\xFF", GRID_CHUNK_SIZE * GRID_CHUNK_SIZE); // Default white

        foreach ($pixels as $pixel) {
            $lx = $pixel['x'] % GRID_CHUNK_SIZE;
            $ly = $pixel['y'] % GRID_CHUNK_SIZE;
            $offset = ($ly * GRID_CHUNK_SIZE + $lx) * 3;

            $color = $pixel['color'];
            $r = hexdec(substr($color, 1, 2));
            $g = hexdec(substr($color, 3, 2));
            $b = hexdec(substr($color, 5, 2));

            $chunk_data[$offset] = chr($r);
            $chunk_data[$offset + 1] = chr($g);
            $chunk_data[$offset + 2] = chr($b);
        }

        // Cache in Redis with 5 min TTL
        Redis::set($chunk_key, $chunk_data, 300);
        Redis::set($version_key, $current_version, 300);
    } else {
        $chunk_data = $cached_chunk;
    }

    // Return binary chunk data
    header('Content-Type: application/octet-stream');
    header('X-Chunk-Version: ' . $current_version);
    header('Content-Length: ' . strlen($chunk_data));
    echo $chunk_data;
    exit();

} catch (Exception $e) {
    log_error('Chunk fetch failed', ['error' => $e->getMessage()]);
    respond_error('fetch_failed', 'Failed to fetch chunk', 500);
}

?>