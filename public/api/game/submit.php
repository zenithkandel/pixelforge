<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$user = require_auth();
$user_id = $user['id'];

$data = get_json_body();

$session_id = $data['session_id'] ?? '';
$final_score = isset($data['final_score']) ? (int)$data['final_score'] : 0;
$duration_ms = isset($data['duration_ms']) ? (int)$data['duration_ms'] : 0;
$lives_remaining = isset($data['lives_remaining']) ? (int)$data['lives_remaining'] : 0;
$max_speed_tier = isset($data['max_speed_tier']) ? (int)$data['max_speed_tier'] : 1;
$max_combo = isset($data['max_combo']) ? (int)$data['max_combo'] : 0;
$prisms_collected = isset($data['prisms_collected']) ? (int)$data['prisms_collected'] : 0;
$bomb_used = isset($data['bomb_used']) ? (bool)$data['bomb_used'] : false;
$hmac = $data['hmac'] ?? '';

if (empty($session_id) || empty($hmac)) {
    respond_error('invalid_request', 'Missing required fields', 400);
}

if ($duration_ms < 1000) {
    respond_error('invalid_duration', 'Game too short', 400);
}

$redis = get_redis();
if ($redis) {
    $active_session = $redis->get("game_active:{$user_id}");
    if ($active_session !== $session_id) {
        respond_error('invalid_session', 'Game session not active', 400);
    }
}

if (!verify_game_hmac($session_id, $final_score, $duration_ms, $hmac)) {
    respond_error('invalid_hmac', 'Invalid submission signature', 400);
}

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT * FROM game_sessions WHERE id = ? AND user_id = ? AND ended_at IS NULL");
    $stmt->execute([$session_id, $user_id]);
    $session = $stmt->fetch();

    if (!$session) {
        respond_error('invalid_session', 'Game session not found', 400);
    }

    $checkpoints = $session['checkpoints_json'] ? json_decode($session['checkpoints_json'], true) : [];

    if (!validate_score_plausibility($final_score, $duration_ms, $checkpoints)) {
        $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'implausible_score' WHERE id = ?");
        $stmt->execute([$session_id]);

        log_security('implausible_score', get_client_ip(), [
            'user_id' => $user_id,
            'session_id' => $session_id,
            'score' => $final_score,
            'duration_ms' => $duration_ms
        ]);

        respond_error('suspicious_score', 'Score exceeds plausible limits', 400);
    }

    $today = date('Y-m-d');
    $is_first_game_today = true;
    $is_daily_highscore = false;

    if ($redis) {
        if (has_daily_game($redis, $user_id, $today)) {
            $is_first_game_today = false;
        } else {
            set_daily_game($redis, $user_id, $today);
        }

        $is_daily_highscore = is_daily_highscore($pdo, $user_id, $final_score);
    }

    $pxl_earned = calculate_pxl_earned($final_score, $is_first_game_today, $is_daily_highscore, $max_combo);

    $pdo->beginTransaction();

    $new_balance = credit_pxl($pdo, $user_id, $pxl_earned, 'game_earn', $session_id, "PIXEL DASH score: {$final_score}");

    $duration_seconds = (int)($duration_ms / 1000);

    $stmt = $pdo->prepare("UPDATE game_sessions SET ended_at = NOW(), final_score = ?, duration_seconds = ?, pxl_earned = ?, lives_at_end = ?, max_speed_tier = ? WHERE id = ?");
    $stmt->execute([$final_score, $duration_seconds, $pxl_earned, $lives_remaining, $max_speed_tier, $session_id]);

    $stmt = $pdo->prepare("INSERT INTO scores (user_id, game_session_id, score, pxl_earned, duration_seconds, max_speed_tier) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $session_id, $final_score, $pxl_earned, $duration_seconds, $max_speed_tier]);

    $pdo->commit();

    if ($redis) {
        $redis->del("game_active:{$user_id}");
    }

    $achievements_unlocked = check_and_grant_achievements($pdo, $user_id, 'game_submit', [
        'final_score' => $final_score,
        'max_speed_tier' => $max_speed_tier,
        'max_combo' => $max_combo,
        'prisms_collected' => $prisms_collected,
        'bomb_used' => $bomb_used
    ]);

    $daily_rank = get_user_daily_rank($pdo, $user_id);

    log_game_event('session_end', $user_id, [
        'session_id' => $session_id,
        'score' => $final_score,
        'pxl_earned' => $pxl_earned
    ]);

    respond_success([
        'pxl_earned' => $pxl_earned,
        'daily_bonus' => $is_first_game_today ? $pxl_earned : 0,
        'highscore_bonus' => $is_daily_highscore ? 5 : 0,
        'new_balance' => $new_balance,
        'personal_best' => $is_daily_highscore,
        'daily_rank' => $daily_rank,
        'achievements_unlocked' => $achievements_unlocked
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    log_error('Game submit failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to submit score', 500);
}