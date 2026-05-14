<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();
require_csrf();

$user = get_current_user_data();

$json = json_decode(file_get_contents('php://input'), true);
$session_id = $json['session_id'] ?? '';
$final_score = isset($json['final_score']) ? (int)$json['final_score'] : 0;
$duration_ms = isset($json['duration_ms']) ? (int)$json['duration_ms'] : 0;
$lives_remaining = isset($json['lives_remaining']) ? (int)$json['lives_remaining'] : 0;
$max_speed_tier = isset($json['max_speed_tier']) ? (int)$json['max_speed_tier'] : 1;
$max_combo = isset($json['max_combo']) ? (int)$json['max_combo'] : 0;
$prisms_collected = isset($json['prisms_collected']) ? (int)$json['prisms_collected'] : 0;
$bomb_used = !empty($json['bomb_used']);
$hmac = $json['hmac'] ?? '';

$redis = RedisClient::getInstance();
$active_session = $redis->get("game_active:{$user['id']}");
if ($active_session !== $session_id) {
    respond_error('invalid_session', 'Session is not active or already submitted.');
}
$redis->del("game_active:{$user['id']}");

$db = DB::getInstance();

if (!validate_game_hmac($session_id, $final_score, $duration_ms, $hmac)) {
    $stmt = $db->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'invalid_hmac_submit' WHERE id = ?");
    $stmt->execute([$session_id]);
    respond_error('invalid_hmac', 'Checksum verification failed.', 403);
}

if (!validate_score_plausibility($final_score, $duration_ms)) {
    $stmt = $db->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'implausible_score_submit' WHERE id = ?");
    $stmt->execute([$session_id]);
    respond_error('implausible_score', 'Score plausibility check failed.', 403);
}

$pxl_earned = floor($final_score / 200);
$duration_sec = floor($duration_ms / 1000);

// Daily First Game Bonus
$today_start = date('Y-m-d 00:00:00');
$stmt = $db->prepare("SELECT COUNT(*) FROM game_sessions WHERE user_id = ? AND ended_at >= ? AND is_valid = 1");
$stmt->execute([$user['id'], $today_start]);
$is_first_game = ($stmt->fetchColumn() == 0);

$daily_bonus = 0;
if ($is_first_game) {
    $daily_bonus = $pxl_earned; // 2x multiplier
    $pxl_earned += $daily_bonus;
}

// Daily High Score Bonus
$stmt = $db->prepare("SELECT MAX(final_score) FROM game_sessions WHERE user_id = ? AND ended_at >= ? AND is_valid = 1");
$stmt->execute([$user['id'], $today_start]);
$daily_best = (int)$stmt->fetchColumn();

$highscore_bonus = 0;
if ($final_score > $daily_best && $daily_best > 0) {
    $highscore_bonus = 5;
    $pxl_earned += $highscore_bonus;
}

// Close session
$stmt = $db->prepare("UPDATE game_sessions SET ended_at = NOW(), final_score = ?, duration_seconds = ?, pxl_earned = ?, lives_at_end = ?, max_speed_tier = ? WHERE id = ?");
$stmt->execute([$final_score, $duration_sec, $pxl_earned, $lives_remaining, $max_speed_tier, $session_id]);

// Credit PXL
$new_balance = pxl_credit($user['id'], $pxl_earned, 'game_earn', $session_id, "Earned from game (Score: $final_score)");

// Insert Score
$stmt = $db->prepare("INSERT INTO scores (user_id, game_session_id, score, pxl_earned, duration_seconds, max_speed_tier) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user['id'], $session_id, $final_score, $pxl_earned, $duration_sec, $max_speed_tier]);

// Achievements
if ($is_first_game && check_and_grant_achievement($user['id'], 'first_game')) {
    // We don't auto-credit achievements per requirements (must be claimed)
}

if ($max_speed_tier >= 3) check_and_grant_achievement($user['id'], 'speed_tier_3');
if ($max_speed_tier >= 5) check_and_grant_achievement($user['id'], 'speed_tier_5');
if ($max_speed_tier >= 7) check_and_grant_achievement($user['id'], 'speed_tier_7');

if ($final_score >= 500) check_and_grant_achievement($user['id'], 'score_500');
if ($final_score >= 2000) check_and_grant_achievement($user['id'], 'score_2000');
if ($final_score >= 5000) check_and_grant_achievement($user['id'], 'score_5000');
if ($final_score >= 10000) check_and_grant_achievement($user['id'], 'score_10000');

if ($max_combo >= 15) check_and_grant_achievement($user['id'], 'combo_15');
if ($max_combo >= 35) check_and_grant_achievement($user['id'], 'combo_35');

if ($prisms_collected >= 5) check_and_grant_achievement($user['id'], 'rainbow_5');
if ($bomb_used) check_and_grant_achievement($user['id'], 'bomb_used');

// Total PXL check
$stmt = $db->prepare("SELECT total_pxl_earned FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
if ($stmt->fetchColumn() >= 100) check_and_grant_achievement($user['id'], 'total_earned_100');

// Calculate Daily Rank
$stmt = $db->prepare("SELECT COUNT(*) + 1 FROM scores WHERE created_at >= ? AND score > ?");
$stmt->execute([$today_start, $final_score]);
$daily_rank = $stmt->fetchColumn();

// Fetch newly unlocked achievements to return to client
$stmt = $db->prepare("
    SELECT a.key_name, a.title, a.pxl_reward 
    FROM user_achievements ua 
    JOIN achievements a ON ua.achievement_id = a.id 
    WHERE ua.user_id = ? AND ua.earned_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND ua.pxl_claimed = 0
");
$stmt->execute([$user['id']]);
$unlocked = $stmt->fetchAll();

respond_success([
    'pxl_earned' => $pxl_earned,
    'daily_bonus' => $daily_bonus,
    'highscore_bonus' => $highscore_bonus,
    'new_balance' => $new_balance,
    'personal_best' => ($final_score > $daily_best),
    'daily_rank' => $daily_rank,
    'achievements_unlocked' => $unlocked
]);
