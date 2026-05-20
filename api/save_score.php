<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = $_POST['game_token'] ?? '';
$score = (int)($_POST['score'] ?? 0);
$multiplier = (float)($_POST['multiplier'] ?? 1.0);
$coins_collected = (int)($_POST['coins_collected'] ?? 0);
$csrf = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($score < 0 || $score > MAX_SCORE) {
    http_response_code(400);
    echo json_encode(['error' => 'Score rejected — exceeds maximum allowed value']);
    exit;
}

$expected_multiplier = min(3.0, floor($score / 10) * 0.5 + 1.0);
$expected_multiplier = round($expected_multiplier, 1);
if (abs($multiplier - $expected_multiplier) > 0.1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid multiplier']);
    exit;
}

if ($coins_collected < 0 || $coins_collected > MAX_COINS_PER_GAME) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coins count']);
    exit;
}

$token_data = Database::fetch(
    "SELECT id, user_id, created_at FROM game_tokens WHERE token = ? AND used = 0",
    [$token]
);

if (!$token_data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or expired game session. Please start a new game.']);
    exit;
}

if ($token_data['user_id'] != $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token for this user']);
    exit;
}

$created = strtotime($token_data['created_at']);
if (time() - $created > GAME_TOKEN_EXPIRY_MINUTES * 60) {
    http_response_code(400);
    echo json_encode(['error' => 'Game token expired. Please start a new game.']);
    exit;
}

Database::query("UPDATE game_tokens SET used = 1 WHERE id = ?", [$token_data['id']]);

$currency_earned = $score * $multiplier * CURRENCY_PER_SCORE;
$currency_earned += $coins_collected * 5;
$xp_earned = $score * XP_PER_SCORE;

add_balance($_SESSION['user_id'], $currency_earned);
$xp_result = add_xp($_SESSION['user_id'], $xp_earned);

Database::query(
    "INSERT INTO score_log (user_id, score, multiplier, currency_earned, xp_earned) VALUES (?, ?, ?, ?, ?)",
    [$_SESSION['user_id'], $score, $multiplier, $currency_earned, $xp_earned]
);

$new_achievements = [];

$new_achievements = array_merge($new_achievements, check_score_achievements($_SESSION['user_id'], $score, $multiplier));

$user = get_current_user();
$new_achievements = array_merge($new_achievements, check_streak_achievements($_SESSION['user_id'], $user['streak_days']));
$new_achievements = array_merge($new_achievements, check_level_achievements($_SESSION['user_id'], $user['level']));

$achievements_data = array_map(function($a) {
    return [
        'slug' => $a['slug'],
        'name' => $a['name'],
        'icon' => $a['icon'],
        'reward' => $a['reward']
    ];
}, $new_achievements);

$user = get_current_user();
echo json_encode([
    'success' => true,
    'score' => $score,
    'currency_earned' => $currency_earned,
    'xp_earned' => $xp_earned,
    'new_balance' => $user['balance'],
    'new_xp' => $user['xp'],
    'new_level' => $user['level'],
    'level_up' => $xp_result['level_up'] ?? false,
    'new_achievements' => $achievements_data
]);