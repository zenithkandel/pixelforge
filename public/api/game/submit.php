<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/game_validator.php';
require_method('POST');

$user = require_auth();

$data = get_json_body();

if (!isset($data['session_id'], $data['final_score'], $data['duration_ms'], $data['hmac'])) {
    respond_error('missing_fields', 'All submit fields required', 400);
}

$redis = get_redis();
$pdo = get_db();

if ($redis->get("game_active:{$user['id']}") !== $data['session_id']) {
    respond_error('invalid_session', 'Game session not active or already submitted', 400);
}

$stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL");
$stmt->execute([$data['session_id'], $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    respond_error('invalid_session', 'Game session not found or already ended', 400);
}

if ($session['started_at'] && (time() - strtotime($session['started_at'])) > 7200) {
    $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid=0, invalidation_reason='session_expired' WHERE id=?");
    $stmt->execute([$data['session_id']]);
    $redis->del("game_active:{$user['id']}");
    respond_error('session_expired', 'Game session expired', 400);
}

$hmac_data = $data['final_score'] . ':' . $data['duration_ms'];
if (!verify_game_hmac($data['session_id'], $hmac_data, $data['hmac'])) {
    respond_error('invalid_hmac', 'Invalid submit signature', 400);
}

$checkpoints = json_decode($session['checkpoints_json'] ?? '[]', true) ?: [];

if (!validate_score_plausibility($data['final_score'], $data['duration_ms'], $checkpoints)) {
    $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid=0, invalidation_reason='implausible_score' WHERE id=?");
    $stmt->execute([$data['session_id']]);
    $redis->del("game_active:{$user['id']}");
    respond_error('cheat_detected', 'Score exceeds plausible limits', 400);
}

$pxl_base = (int)floor($data['final_score'] / 200);
$daily_bonus = 0;
$highscore_bonus = 0;
$combo_bonuses = 0;

$today = date('Y-m-d');
$daily_key = "daily_game:{$user['id']}:{$today}";

$pdo->beginTransaction();

if (!$redis->exists($daily_key)) {
    $pxl_base *= 2;
    $daily_bonus = $pxl_base;
    $redis->setex($daily_key, 86400, '1');
}

$stmt = $pdo->prepare("SELECT MAX(score) FROM scores WHERE user_id = ? AND DATE(created_at) = ?");
$stmt->execute([$user['id'], $today]);
$best_today = (int)$stmt->fetchColumn();

if ($data['final_score'] > $best_today) {
    $highscore_bonus = 5;
    credit_pxl($pdo, $user['id'], 5, 'daily_highscore_bonus', $data['session_id'], 'Daily high score bonus');
}

$max_combo = $data['max_combo'] ?? 0;
$combo_tier_bonuses = [15 => 1, 35 => 10];
$stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ? AND achievement_id IN (SELECT id FROM achievements WHERE key_name IN ('combo_15','combo_35'))");
$stmt->execute([$user['id']]);
$already_earned = array_column($stmt->fetchAll(), 'achievement_id');

$stmt = $pdo->prepare("SELECT id, key_name, pxl_reward FROM achievements WHERE key_name IN ('combo_15','combo_35')");
$stmt->execute();
$combo_achs = $stmt->fetchAll(PDO::FETCH_UNIQUE);

foreach ([15, 35] as $combo_thresh) {
    if ($max_combo >= $combo_thresh) {
        $ach_key = 'combo_' . $combo_thresh;
        $stmt = $pdo->prepare("SELECT id FROM achievements WHERE key_name = ?");
        $stmt->execute([$ach_key]);
        $ach_id = $stmt->fetchColumn();
        if ($ach_id && !in_array($ach_id, $already_earned)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)");
            $stmt->execute([$user['id'], $ach_id]);
            $bonus = $combo_tier_bonuses[$combo_thresh] ?? 0;
            credit_pxl($pdo, $user['id'], $bonus, 'combo_bonus', $ach_key, "Combo {$combo_thresh}x bonus");
            $combo_bonuses += $bonus;
        }
    }
}

$total_pxl = $pxl_base + $combo_bonuses;
if ($total_pxl > 0) {
    credit_pxl($pdo, $user['id'], $total_pxl, 'game_earn', $data['session_id'], "Game score {$data['final_score']}");
}

$duration_sec = (int)($data['duration_ms'] / 1000);

$stmt = $pdo->prepare("INSERT INTO scores (user_id, game_session_id, score, pxl_earned, duration_seconds, max_speed_tier) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$user['id'], $data['session_id'], $data['final_score'], $total_pxl, $duration_sec, $data['max_speed_tier'] ?? 1]);

$stmt = $pdo->prepare("UPDATE game_sessions SET ended_at=NOW(), final_score=?, duration_seconds=?, pxl_earned=?, lives_at_end=?, max_speed_tier=?, is_valid=1 WHERE id=?");
$stmt->execute([$data['final_score'], $duration_sec, $total_pxl, $data['lives_remaining'] ?? 0, $data['max_speed_tier'] ?? 1, $data['session_id']]);

$pdo->commit();

$redis->del("game_active:{$user['id']}");

$unlocked = check_and_grant_achievements($pdo, $user['id'], 'game_submit', [
    'final_score' => $data['final_score'],
    'max_speed_tier' => $data['max_speed_tier'] ?? 1,
    'max_combo' => $max_combo,
    'prisms_collected' => $data['prisms_collected'] ?? 0,
    'bomb_used' => $data['bomb_used'] ?? false,
]);

$stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$new_balance = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) + 1 FROM scores WHERE score > ? AND DATE(created_at) = ?");
$stmt->execute([$data['final_score'], $today]);
$daily_rank = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT MAX(score) FROM scores WHERE user_id = ?");
$stmt->execute([$user['id']]);
$all_time_best = (int)$stmt->fetchColumn();

respond_success([
    'pxl_earned' => $total_pxl,
    'daily_bonus' => $daily_bonus,
    'highscore_bonus' => $highscore_bonus,
    'new_balance' => $new_balance,
    'personal_best' => $data['final_score'] >= $all_time_best,
    'daily_rank' => $daily_rank,
    'achievements_unlocked' => $unlocked,
]);