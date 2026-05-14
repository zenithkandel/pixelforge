<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$type = $_GET['type'] ?? 'daily';
if (!in_array($type, ['daily', 'weekly', 'alltime'])) {
    $type = 'daily';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
check_rate_limit('leaderboard', $ip, 60, 60);

$redis = RedisClient::getInstance();
$cache_key = "leaderboard:$type:$page:$limit";
$cached = $redis->get($cache_key);

if ($cached) {
    echo $cached;
    exit;
}

$db = DB::getInstance();

$where = "s.is_valid = 1"; // Wait, scores table doesn't have is_valid. game_sessions has it, but we only insert into scores if valid.
$where = "1=1";
$params = [];

if ($type === 'daily') {
    $where .= " AND s.created_at >= ?";
    $params[] = date('Y-m-d 00:00:00');
    $ttl = 60;
} elseif ($type === 'weekly') {
    $where .= " AND s.created_at >= ?";
    // Sunday 00:00 UTC
    $sunday = new DateTime('last sunday', new DateTimeZone('UTC'));
    $params[] = $sunday->format('Y-m-d H:i:s');
    $ttl = 300;
} else {
    $ttl = 600;
}

// Ensure we get limits correctly
$sql = "
    SELECT s.id, s.score, s.pxl_earned, s.duration_seconds, s.created_at, u.username
    FROM scores s
    JOIN users u ON s.user_id = u.id
    WHERE $where
    ORDER BY s.score DESC, s.created_at ASC
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$scores = $stmt->fetchAll();

$response = json_encode(['ok' => true, 'data' => $scores]);
$redis->setex($cache_key, $ttl, $response);

header('Content-Type: application/json');
echo $response;
