<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_method('POST');

$user = require_auth();
$user_id = $user['id'];

if (!check_rate_limit("checkpoint:{$user_id}", 4, 60)) {
    respond_error('rate_limited', 'Too many checkpoints', 429);
}

$data = get_json_body();

$session_id = $data['session_id'] ?? '';
$score = isset($data['score']) ? (int)$data['score'] : 0;
$lives = isset($data['lives']) ? (int)$data['lives'] : 3;
$speed_tier = isset($data['speed_tier']) ? (int)$data['speed_tier'] : 1;
$elapsed_ms = isset($data['elapsed_ms']) ? (int)$data['elapsed_ms'] : 0;
$hmac = $data['hmac'] ?? '';

if (empty($session_id) || empty($hmac)) {
    respond_error('invalid_request', 'Missing required fields', 400);
}

$redis = get_redis();
if ($redis) {
    $active_session = $redis->get("game_active:{$user_id}");
    if ($active_session !== $session_id) {
        respond_error('invalid_session', 'Game session not active', 400);
    }
}

if (!verify_checkpoint_hmac($session_id, $score, $elapsed_ms, $hmac)) {
    respond_error('invalid_hmac', 'Invalid checkpoint signature', 400);
}

if ($elapsed_ms < 0 || $score < 0 || $lives < 0 || $lives > 3) {
    respond_error('invalid_data', 'Invalid checkpoint data', 400);
}

$rate_limit_key = "checkpoint_score:{$user_id}";
if (!check_rate_limit($rate_limit_key, 1, 1)) {
    if ($score / ($elapsed_ms / 1000) > MAX_SCORE_PER_SECOND_HARD) {
        respond_error('suspicious_score', 'Score exceeds plausible rate', 400);
    }
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
    $checkpoints[] = [
        'score' => $score,
        'lives' => $lives,
        'speed_tier' => $speed_tier,
        'elapsed_ms' => $elapsed_ms,
        'timestamp' => time()
    ];

    $stmt = $pdo->prepare("UPDATE game_sessions SET last_checkpoint_at = NOW(), checkpoints_json = ? WHERE id = ?");
    $stmt->execute([json_encode($checkpoints), $session_id]);

    respond_success(['checkpoint_saved' => true, 'timestamp' => time()]);

} catch (Exception $e) {
    log_error('Checkpoint failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to save checkpoint', 500);
}