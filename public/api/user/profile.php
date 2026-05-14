<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');

$username = $_GET['username'] ?? '';

if (empty($username) || !validate_username($username)) {
    respond_error('invalid_username', 'Invalid username', 400);
}

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.total_pxl_earned, u.created_at, u.login_streak,
        COUNT(DISTINCT ph.id) as pixels_painted,
        MAX(s.score) as best_score,
        COUNT(DISTINCT s.id) as total_games
    FROM users u
    LEFT JOIN pixel_history ph ON ph.user_id = u.id
    LEFT JOIN scores s ON s.user_id = u.id
    WHERE u.username = ? AND u.is_banned = 0
    GROUP BY u.id
");
$stmt->execute([$username]);
$profile = $stmt->fetch();

if (!$profile) {
    respond_error('user_not_found', 'User not found', 404);
}

$stmt = $pdo->prepare("
    SELECT ua.earned_at, a.key_name, a.title, a.description, a.pxl_reward, a.icon_class
    FROM user_achievements ua
    JOIN achievements a ON a.id = ua.achievement_id
    WHERE ua.user_id = ?
    ORDER BY ua.earned_at DESC
");
$stmt->execute([$profile['id']]);
$achievements = $stmt->fetchAll();

respond_success([
    'username' => $profile['username'],
    'total_pxl_earned' => $profile['total_pxl_earned'],
    'pixels_painted' => (int)$profile['pixels_painted'],
    'best_score' => (int)$profile['best_score'],
    'total_games' => (int)$profile['total_games'],
    'login_streak' => $profile['login_streak'],
    'join_date' => $profile['created_at'],
    'achievements' => $achievements,
]);