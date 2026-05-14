<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_once dirname(__DIR__, 3) . '/includes/game_validator.php';
require_method('POST');

$user = require_auth();

if (!check_rate_limit("checkpoint:{$user['id']}", 4, 60)) {
    respond_error('rate_limited', 'Checkpoint rate limited', 429);
}

$data = get_json_body();

if (!isset($data['session_id'], $data['score'], $data['lives'], $data['speed_tier'], $data['elapsed_ms'], $data['hmac'])) {
    respond_error('missing_fields', 'All checkpoint fields required', 400);
}

$redis = get_redis();
$pdo = get_db();

if ($redis && $redis->get("game_active:{$user['id']}") !== $data['session_id']) {
    respond_error('invalid_session', 'Game session not active', 400);
}

$stmt = $pdo->prepare("SELECT prng_seed, checkpoints_json FROM game_sessions WHERE id = ? AND user_id = ?");
$stmt->execute([$data['session_id'], $user['id']]);
$session = $stmt->fetch();

if (!$session) {
    respond_error('invalid_session', 'Game session not found', 400);
}

$hmac_data = $data['score'] . ':' . $data['elapsed_ms'];
if (APP_ENV !== 'local' && !verify_game_hmac($data['session_id'], $hmac_data, $data['hmac'])) {
    respond_error('invalid_hmac', 'Invalid checkpoint signature', 400);
}

if ($data['score'] / ($data['elapsed_ms'] / 1000) > MAX_SCORE_PER_SECOND_HARD) {
    $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid=0, invalidation_reason='implausible_score' WHERE id=?");
    $stmt->execute([$data['session_id']]);
    if ($redis) $redis->del("game_active:{$user['id']}");
    respond_error('cheat_detected', 'Score exceeds plausible limit', 400);
}

$checkpoints = json_decode($session['checkpoints_json'] ?? '[]', true) ?: [];
$checkpoints[] = [
    'score' => $data['score'],
    'lives' => $data['lives'],
    'speed_tier' => $data['speed_tier'],
    'elapsed_ms' => $data['elapsed_ms'],
];

$stmt = $pdo->prepare("UPDATE game_sessions SET checkpoints_json = ?, last_checkpoint_at = NOW() WHERE id = ?");
$stmt->execute([json_encode($checkpoints), $data['session_id']]);

respond_success(['checkpoint_received' => true]);