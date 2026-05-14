<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_method('GET');

$user = require_auth();

$pdo = get_db();

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) as total_pixels FROM pixel_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$pixels_painted = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total_games, MAX(score) as best_score FROM scores WHERE user_id = ?");
$stmt->execute([$user['id']]);
$game_stats = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT ua.earned_at, a.key_name, a.title, a.description, a.pxl_reward, a.icon_class, ua.pxl_claimed
    FROM user_achievements ua
    JOIN achievements a ON a.id = ua.achievement_id
    WHERE ua.user_id = ?
    ORDER BY ua.earned_at DESC
");
$stmt->execute([$user['id']]);
$achievements = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, amount, type, description, balance_after, created_at FROM pxl_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$user['id']]);
$transactions = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT x, y, color, purchased_at FROM pixel_history WHERE user_id = ? ORDER BY purchased_at DESC LIMIT 20");
$stmt->execute([$user['id']]);
$recent_pixels = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT score, pxl_earned, duration_seconds, max_speed_tier, created_at FROM scores WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['id']]);
$score_history = $stmt->fetchAll();

respond_success([
    'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'pxl_balance' => $user['pxl_balance'],
        'total_pxl_earned' => $user['total_pxl_earned'],
        'total_pxl_spent' => $user['total_pxl_spent'],
        'login_streak' => $user['login_streak'],
        'email_verified' => (bool)$user['email_verified'],
        'created_at' => $user['created_at'],
    ],
    'stats' => [
        'pixels_painted' => $pixels_painted,
        'total_games' => (int)$game_stats['total_games'],
        'best_score' => (int)$game_stats['best_score'],
    ],
    'achievements' => $achievements,
    'transactions' => $transactions,
    'recent_pixels' => $recent_pixels,
    'score_history' => $score_history,
]);