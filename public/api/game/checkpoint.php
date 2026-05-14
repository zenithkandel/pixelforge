<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();
require_csrf();

$user = get_current_user_data();
check_rate_limit('game_checkpoint', $user['id'], 4, 60);

$json = json_decode(file_get_contents('php://input'), true);
$session_id = $json['session_id'] ?? '';
$score = isset($json['score']) ? (int)$json['score'] : 0;
$lives = isset($json['lives']) ? (int)$json['lives'] : 3;
$speed_tier = isset($json['speed_tier']) ? (int)$json['speed_tier'] : 1;
$elapsed_ms = isset($json['elapsed_ms']) ? (int)$json['elapsed_ms'] : 0;
$hmac = $json['hmac'] ?? '';

if (empty($session_id) || empty($hmac)) {
    respond_error('invalid_input', 'Missing parameters.');
}

$redis = RedisClient::getInstance();
$active_session = $redis->get("game_active:{$user['id']}");

if ($active_session !== $session_id) {
    respond_error('invalid_session', 'Session is not active.');
}

if (!validate_game_hmac($session_id, $score, $elapsed_ms, $hmac)) {
    $db = DB::getInstance();
    $stmt = $db->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'invalid_hmac' WHERE id = ?");
    $stmt->execute([$session_id]);
    $redis->del("game_active:{$user['id']}");
    respond_error('invalid_hmac', 'Checksum verification failed.', 403);
}

if (!validate_score_plausibility($score, $elapsed_ms)) {
    $db = DB::getInstance();
    $stmt = $db->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'implausible_score' WHERE id = ?");
    $stmt->execute([$session_id]);
    $redis->del("game_active:{$user['id']}");
    respond_error('implausible_score', 'Score plausibility check failed.', 403);
}

$db = DB::getInstance();
$stmt = $db->prepare("SELECT checkpoints_json FROM game_sessions WHERE id = ? AND user_id = ? AND is_valid = 1");
$stmt->execute([$session_id, $user['id']]);
$row = $stmt->fetch();

if (!$row) {
    respond_error('invalid_session', 'Session not found or invalid.');
}

$checkpoints = $row['checkpoints_json'] ? json_decode($row['checkpoints_json'], true) : [];
$checkpoints[] = [
    'score' => $score,
    'lives' => $lives,
    'speed_tier' => $speed_tier,
    'elapsed_ms' => $elapsed_ms,
    'time' => time()
];

$stmt = $db->prepare("UPDATE game_sessions SET checkpoints_json = ?, last_checkpoint_at = NOW() WHERE id = ?");
$stmt->execute([json_encode($checkpoints), $session_id]);

respond_success();
