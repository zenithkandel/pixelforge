<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

$username = $_GET['username'] ?? '';
if (empty($username)) {
    respond_error('invalid_input', 'Username required.');
}

$db = DB::getInstance();

$stmt = $db->prepare("SELECT id, username, total_pxl_earned, login_streak, created_at FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    respond_error('not_found', 'User not found.', 404);
}

$stmt = $db->prepare("SELECT COUNT(*) FROM pixel_history WHERE user_id = ?");
$stmt->execute([$user['id']]);
$pixels_painted = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT MAX(score) FROM scores WHERE user_id = ?");
$stmt->execute([$user['id']]);
$best_score = $stmt->fetchColumn() ?: 0;

$stmt = $db->prepare("SELECT COUNT(*) FROM game_sessions WHERE user_id = ? AND is_valid = 1 AND ended_at IS NOT NULL");
$stmt->execute([$user['id']]);
$games_played = $stmt->fetchColumn();

// Recent pixels
$stmt = $db->prepare("SELECT x, y, color, purchased_at FROM pixel_history WHERE user_id = ? ORDER BY purchased_at DESC LIMIT 20");
$stmt->execute([$user['id']]);
$recent_pixels = $stmt->fetchAll();

respond_success([
    'username' => $user['username'],
    'join_date' => $user['created_at'],
    'total_pxl_earned' => $user['total_pxl_earned'],
    'login_streak' => $user['login_streak'],
    'pixels_painted' => $pixels_painted,
    'best_score' => $best_score,
    'games_played' => $games_played,
    'recent_pixels' => $recent_pixels
]);
