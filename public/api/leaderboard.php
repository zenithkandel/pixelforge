<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$type = $_GET['type'] ?? 'daily';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;

$ip = get_client_ip();
if (!check_rate_limit("lb:{$ip}", 60, 60)) {
    respond_error('rate_limited', 'Too many requests', 429);
}

$valid_types = ['daily', 'weekly', 'alltime'];
if (!in_array($type, $valid_types)) {
    $type = 'daily';
}

$cache_keys = [
    'daily' => 'lb:daily',
    'weekly' => 'lb:weekly',
    'alltime' => 'lb:alltime'
];

$cache_ttl = [
    'daily' => 60,
    'weekly' => 300,
    'alltime' => 600
];

$redis = get_redis();

if ($redis) {
    $cached = $redis->get($cache_keys[$type]);
    if ($cached) {
        $result = json_decode($cached, true);
        respond_success($result);
    }
}

try {
    $pdo = get_db();

    $where_clause = "1=1";
    if ($type === 'daily') {
        $where_clause = "DATE(s.created_at) = CURDATE()";
    } elseif ($type === 'weekly') {
        $where_clause = "s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    }

    $offset = ($page - 1) * $limit;

    $stmt = $pdo->prepare("
        SELECT s.*, u.username,
            ROW_NUMBER() OVER (ORDER BY s.score DESC) as rank
        FROM scores s
        JOIN users u ON s.user_id = u.id
        WHERE {$where_clause}
        ORDER BY s.score DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $scores = $stmt->fetchAll();

    $count_where_clause = "1=1";
    if ($type === 'daily') {
        $count_where_clause = "DATE(created_at) = CURDATE()";
    } elseif ($type === 'weekly') {
        $count_where_clause = "created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    }
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) as total_players
        FROM scores
        WHERE {$count_where_clause}
    ");
    $total_players = (int)($stmt->fetch()['total_players'] ?? 0);

    $user_id = is_authenticated() ? $_SESSION['user_id'] : null;
    $user_rank = null;
    if ($user_id) {
        if ($type === 'daily') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) + 1 as rank FROM (
                    SELECT user_id, MAX(score) as max_score
                    FROM scores
                    WHERE DATE(created_at) = CURDATE()
                    GROUP BY user_id
                    HAVING max_score > (
                        SELECT COALESCE(MAX(score), 0) FROM scores WHERE user_id = ? AND DATE(created_at) = CURDATE()
                    )
                ) sub
            ");
            $stmt->execute([$user_id]);
        } elseif ($type === 'weekly') {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) + 1 as rank FROM (
                    SELECT user_id, MAX(score) as max_score
                    FROM scores
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    GROUP BY user_id
                    HAVING max_score > (
                        SELECT COALESCE(MAX(score), 0) FROM scores WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    )
                ) sub
            ");
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as rank FROM scores WHERE score > (SELECT COALESCE(MAX(score), 0) FROM scores WHERE user_id = ?)");
            $stmt->execute([$user_id]);
        }
        $user_rank = (int)($stmt->fetch()['rank'] ?? 0);
    }

    $response = [
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'total_players' => $total_players,
        'user_rank' => $user_rank,
        'scores' => array_map(function($s) {
            return [
                'rank' => (int)$s['rank'],
                'user_id' => (int)$s['user_id'],
                'username' => $s['username'],
                'score' => (int)$s['score'],
                'pxl_earned' => (int)$s['pxl_earned'],
                'duration_seconds' => (int)$s['duration_seconds'],
                'created_at' => $s['created_at']
            ];
        }, $scores)
    ];

    if ($redis) {
        $redis->setex($cache_keys[$type], $cache_ttl[$type], json_encode($response));
    }

    respond_success($response);

} catch (Exception $e) {
    log_error('Leaderboard fetch failed', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    respond_error('server_error', 'Failed to fetch leaderboard', 500);
}