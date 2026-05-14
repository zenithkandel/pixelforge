<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

$type = sanitize_string($_GET['type'] ?? 'daily');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, intval($_GET['limit'] ?? 20));

// Validate type
if (!in_array($type, ['daily', 'weekly', 'alltime'])) {
    respond_error('invalid_type', 'Invalid leaderboard type');
}

// Rate limit
$ip = get_client_ip();
if (!RateLimit::check("leaderboard:$ip", 60, 60)) {
    respond_error('rate_limited', 'Rate limited', 429);
}

$offset = ($page - 1) * $limit;

try {
    // Build date filter
    $date_filter = '';
    if ($type === 'daily') {
        $date_filter = 'AND DATE(s.created_at) = CURDATE()';
    } elseif ($type === 'weekly') {
        $date_filter = 'AND WEEK(s.created_at) = WEEK(NOW()) AND YEAR(s.created_at) = YEAR(NOW())';
    }

    // Check cache first
    $cache_key = "leaderboard:$type:$page";
    $cached = Redis::get($cache_key);

    if ($cached) {
        respond_success(json_decode($cached, true));
    }

    // Fetch scores
    $sql = "
        SELECT 
            ROW_NUMBER() OVER (ORDER BY s.score DESC) as rank,
            u.username,
            s.score,
            s.pxl_earned,
            s.duration_seconds,
            s.created_at
        FROM scores s
        JOIN users u ON s.user_id = u.id
        WHERE u.is_banned = 0 $date_filter
        ORDER BY s.score DESC
        LIMIT ? OFFSET ?
    ";

    $scores = Database::fetchAll($sql, [$limit, $offset]);

    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM scores s JOIN users u ON s.user_id = u.id WHERE u.is_banned = 0 $date_filter";
    $count = Database::fetch($count_sql);

    $result = [
        'scores' => $scores,
        'total' => (int) $count['total'],
        'page' => $page,
        'limit' => $limit
    ];

    // Cache for appropriate duration
    $ttl = $type === 'daily' ? 60 : ($type === 'weekly' ? 300 : 600);
    Redis::set($cache_key, json_encode($result), $ttl);

    respond_success($result);

} catch (Exception $e) {
    log_error('Leaderboard fetch failed', ['error' => $e->getMessage()]);
    respond_error('fetch_failed', 'Failed to fetch leaderboard', 500);
}

?>