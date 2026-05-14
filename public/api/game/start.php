<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_verified();
require_csrf();

$user = get_current_user_data();
check_rate_limit('game_start', $user['id'], 20, 3600);

$db = DB::getInstance();

// Invalidate any existing active game session
$stmt = $db->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = 'new_session_started' WHERE user_id = ? AND ended_at IS NULL AND is_valid = 1");
$stmt->execute([$user['id']]);

$session_id = bin2hex(random_bytes(32));
$seed = (string)random_int(0, PHP_INT_MAX);
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmt = $db->prepare("INSERT INTO game_sessions (id, user_id, prng_seed, ip_address) VALUES (?, ?, ?, ?)");
$stmt->execute([$session_id, $user['id'], $seed, $ip]);

$hmac = hash_hmac('sha256', $session_id . ':' . $seed . ':' . $user['id'], GAME_HMAC_KEY);

$redis = RedisClient::getInstance();
$redis->setex("game_active:{$user['id']}", 7200, $session_id);

respond_success([
    'session_id' => $session_id,
    'seed' => $seed,
    'hmac' => $hmac,
    'server_time' => time() * 1000
]);
