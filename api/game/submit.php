<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_verified();

$user_id = get_current_user_id();
$data = get_request_json();

$session_token = sanitize_string($data['session_token'] ?? '');
$final_score = intval($data['final_score'] ?? 0);
$duration = intval($data['duration'] ?? 0);
$highest_combo = intval($data['highest_combo'] ?? 0);
$total_shards = intval($data['total_shards'] ?? 0);
$final_speed_tier = intval($data['final_speed_tier'] ?? 1);
$lives_at_end = intval($data['lives_at_end'] ?? 0);
$hmac = sanitize_string($data['hmac'] ?? '');

// Validate session
$session_data = Redis::get("game_session:$session_token");
if (!$session_data) {
    respond_error('invalid_session', 'Invalid or expired game session', 401);
}

$session = json_decode($session_data, true);
if ($session['user_id'] != $user_id) {
    respond_error('session_mismatch', 'Session user mismatch', 401);
}

// Validate HMAC
$submit_data = [
    'session_token' => $session_token,
    'final_score' => $final_score,
    'duration' => $duration
];

if (!validate_game_hmac($submit_data, $hmac)) {
    log_warning('Invalid HMAC on submit', ['user_id' => $user_id, 'session_token' => $session_token]);
    respond_error('invalid_hmac', 'Invalid submission signature', 401);
}

// Validate score plausibility
if (!validate_game_score($final_score, $duration)) {
    log_warning('Suspicious final score', ['user_id' => $user_id, 'score' => $final_score, 'duration' => $duration]);
    respond_error('score_invalid', 'Score appears invalid', 400);
}

try {
    Database::beginTransaction();

    // Get game session record
    $game_session = Database::fetch(
        'SELECT id FROM game_sessions WHERE session_token = ?',
        [$session_token]
    );

    // Mark session as complete
    Database::execute(
        'UPDATE game_sessions SET is_active = 0, ended_at = NOW() WHERE session_token = ?',
        [$session_token]
    );

    // Calculate PXL earned
    $base_pxl = intdiv($final_score, 200); // 200 score = 1 PXL

    // Check for daily first game bonus
    $today = date('Y-m-d');
    $first_game_today = !Database::fetch(
        'SELECT id FROM scores WHERE user_id = ? AND DATE(created_at) = ?',
        [$user_id, $today]
    );

    $total_pxl = $base_pxl;
    if ($first_game_today) {
        $total_pxl *= 2; // 2× multiplier for first game
    }

    // Check for daily high score bonus
    $daily_best = Database::fetch(
        'SELECT MAX(score) as best FROM scores WHERE user_id = ? AND DATE(created_at) = ?',
        [$user_id, $today]
    );

    $is_daily_best = !$daily_best || $final_score > $daily_best['best'];
    if ($is_daily_best && !$first_game_today) {
        $total_pxl += 5;
    }

    // Credit PXL
    credit_pxl($user_id, $total_pxl, 'game_earn', $game_session['id'], "Game score: $final_score");

    // Store score
    Database::execute(
        'INSERT INTO scores (user_id, game_session_id, score, duration_seconds, pxl_earned, final_speed_tier, lives_at_end, highest_combo, total_shards_collected, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        [$user_id, $game_session['id'], $final_score, $duration, $total_pxl, $final_speed_tier, $lives_at_end, $highest_combo, $total_shards]
    );

    // Check and grant achievements
    $granted = check_and_grant_achievements($user_id, 'game_submit', [
        'score' => $final_score,
        'speed_tier' => $final_speed_tier
    ]);

    // Also check combo achievements
    check_and_grant_achievements($user_id, 'combo', ['combo' => $highest_combo]);

    Database::commit();

    // Clear session from Redis
    Redis::del("game_session:$session_token");

    // Get new balance
    $new_balance = get_pxl_balance($user_id);

    // Get daily rank
    $rank = Database::fetch(
        'SELECT COUNT(*) + 1 as rank FROM scores WHERE DATE(created_at) = ? AND score > ?',
        [$today, $final_score]
    );

    log_audit('game_submitted', $user_id, ['score' => $final_score, 'pxl_earned' => $total_pxl]);

    respond_success([
        'pxl_earned' => $total_pxl,
        'new_balance' => $new_balance,
        'daily_rank' => $rank['rank'],
        'achievements_granted' => $granted
    ], 'Game score submitted');

} catch (Exception $e) {
    Database::rollback();
    log_error('Game submit failed', ['error' => $e->getMessage(), 'user_id' => $user_id]);
    respond_error('submit_failed', 'Failed to submit game score', 500);
}

?>