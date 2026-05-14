<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (empty($username)) {
    respond_error('invalid_request', 'Username required', 400);
}

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.created_at, u.pxl_balance, u.total_pxl_earned,
            u.login_streak, u.best_score,
            (SELECT COUNT(*) FROM pixel_history WHERE user_id = u.id) as pixels_painted,
            (SELECT COUNT(*) FROM game_sessions WHERE user_id = u.id AND ended_at IS NOT NULL) as games_played
        FROM users u
        WHERE u.username = ? AND u.is_banned = 0
    ");
    $stmt->execute([$username]);
    $profile = $stmt->fetch();

    if (!$profile) {
        respond_error('user_not_found', 'User not found', 404);
    }

    $stmt = $pdo->prepare("
        SELECT a.*, ua.earned_at, ua.pxl_claimed
        FROM achievements a
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
        ORDER BY a.id
    ");
    $stmt->execute([$profile['id']]);
    $all_achievements = $stmt->fetchAll();

    $achievements = array_map(function($a) use ($profile) {
        return [
            'id' => (int)$a['id'],
            'key_name' => $a['key_name'],
            'title' => $a['title'],
            'description' => $a['description'],
            'pxl_reward' => (int)$a['pxl_reward'],
            'earned' => !empty($a['earned_at']),
            'earned_at' => $a['earned_at']
        ];
    }, $all_achievements);

    respond_success([
        'username' => $profile['username'],
        'created_at' => $profile['created_at'],
        'pxl_balance' => (int)$profile['pxl_balance'],
        'total_pxl_earned' => (int)$profile['total_pxl_earned'],
        'login_streak' => (int)$profile['login_streak'],
        'best_score' => (int)($profile['best_score'] ?? 0),
        'pixels_painted' => (int)$profile['pixels_painted'],
        'games_played' => (int)$profile['games_played'],
        'achievements' => $achievements
    ]);

} catch (Exception $e) {
    log_error('Profile fetch failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to fetch profile', 500);
}