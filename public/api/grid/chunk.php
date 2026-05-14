<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

$cx = isset($_GET['cx']) ? (int)$_GET['cx'] : -1;
$cy = isset($_GET['cy']) ? (int)$_GET['cy'] : -1;
$client_v = isset($_GET['v']) ? (int)$_GET['v'] : -1;

if ($cx < 0 || $cx > 31 || $cy < 0 || $cy > 31) {
    http_response_code(400);
    die("Invalid chunk coordinates");
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
check_rate_limit('chunk', $ip, 200, 60);

$redis = RedisClient::getInstance();

$version_key = "chunk_v:$cx:$cy";
$current_v = (int)$redis->get($version_key);
if (!$current_v) {
    $db = DB::getInstance();
    $stmt = $db->prepare("SELECT version FROM chunks WHERE chunk_x = ? AND chunk_y = ?");
    $stmt->execute([$cx, $cy]);
    $current_v = (int)$stmt->fetchColumn();
    $redis->setex($version_key, 300, $current_v);
}

if ($client_v === $current_v) {
    header("HTTP/1.1 304 Not Modified");
    exit;
}

$chunk_key = "chunk:$cx:$cy";
$binary_data = $redis->get($chunk_key);

if (!$binary_data) {
    $db = DB::getInstance();
    $min_x = $cx * 64;
    $max_x = $min_x + 63;
    $min_y = $cy * 64;
    $max_y = $min_y + 63;
    
    $stmt = $db->prepare("SELECT x, y, color FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ?");
    $stmt->execute([$min_x, $max_x, $min_y, $max_y]);
    $pixels = $stmt->fetchAll();
    
    // Create 12288 bytes filled with 255 (white)
    $binary_data = str_repeat(chr(255), 12288);
    
    foreach ($pixels as $p) {
        $lx = $p['x'] - $min_x;
        $ly = $p['y'] - $min_y;
        $idx = ($ly * 64 + $lx) * 3;
        
        $hex = ltrim($p['color'], '#');
        if (strlen($hex) == 6) {
            $binary_data[$idx] = chr(hexdec(substr($hex, 0, 2)));
            $binary_data[$idx+1] = chr(hexdec(substr($hex, 2, 2)));
            $binary_data[$idx+2] = chr(hexdec(substr($hex, 4, 2)));
        }
    }
    
    $redis->setex($chunk_key, 300, $binary_data);
}

header('Content-Type: application/octet-stream');
header("X-Chunk-Version: $current_v");
header("X-Chunk-X: $cx");
header("X-Chunk-Y: $cy");
header('Cache-Control: no-cache');

echo $binary_data;
