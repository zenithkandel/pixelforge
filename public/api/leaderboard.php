<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');

$type = $_GET['type'] ?? 'daily';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

if (!in_array($type, ['daily', 'weekly', 'alltime'], true)) {
    respond_error('invalid_type', 'Type must be daily, weekly, or alltime', 400);
}

if (!check_rate_limit('lb:' . get_client_ip(), 60, 60)) {
    respond_error('rate_limited', 'Too many leaderboard requests', 429);
}

$redis = get_redis();

$cache_key = "lb:{$type}";
$ttl = match($type) {
    'daily' => 60,
    'weekly' => 300,
    'alltime' => 600,
};

$cached = $redis->get($cache_key);
if ($cached !== false) {
    $data = json_decode($cached, true);
    $total = $data['total'] ?? 0;
    $entries = array_slice($data['entries'], $offset, $limit);
    respond_success(['type' => $type, 'page' => $page, 'limit' => $limit, 'total' => $total, 'entries' => $entries]);
}

$pdo = get_db();

$date_filter = match($type) {
    'daily' => 'DATE(s.created_at) = CURDATE()',
    'weekly' => 's.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
    'alltime' => '1=1',
};

$stmt = $pdo->prepare("
    SELECT u.username, s.score, s.pxl_earned, s.duration_seconds, s.max_speed_tier, s.created_at
    FROM scores s
    JOIN users u ON u.id = s.user_id
    WHERE {$date_filter}
    ORDER BY s.score DESC
    LIMIT 100
");
$stmt->execute();
$raw = $stmt->fetchAll();

$total = count($raw);

$entries = [];
$rank = 1;
foreach ($raw as $row) {
    $entries[] = [
        'rank' => $rank++,
        'username' => $row['username'],
        'score' => (int)$row['score'],
        'pxl_earned' => (int)$row['pxl_earned'],
        'duration_seconds' => (int)$row['duration_seconds'],
        'max_speed_tier' => (int)$row['max_speed_tier'],
        'date' => $row['created_at'],
    ];
}

$redis->setex($cache_key, $ttl, json_encode(['entries' => $entries, 'total' => $total]));

respond_success([
    'type' => $type,
    'page' => $page,
    'limit' => $limit,
    'total' => $total,
    'entries' => array_slice($entries, $offset, $limit),
]);