<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_method('POST');

$user = require_verified();

if (!check_rate_limit("game_start:{$user['id']}", 20, 3600)) {
    respond_error('rate_limited', 'Too many game starts. Try again later.', 429);
}

$pdo = get_db();
$redis = get_redis();

$existing = $redis->get("game_active:{$user['id']}");
if ($existing) {
    $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid=0, invalidation_reason='new_session_started' WHERE id=? AND user_id=?");
    $stmt->execute([$existing, $user['id']]);
}

$session_id = bin2hex(random_bytes(32));
$seed = random_int(0, PHP_INT_MAX);

$ip = get_client_ip();
$stmt = $pdo->prepare("INSERT INTO game_sessions (id, user_id, prng_seed, ip_address) VALUES (?, ?, ?, ?)");
$stmt->execute([$session_id, $user['id'], (string)$seed, $ip]);

$hmac = hash_hmac('sha256', $session_id . ':' . $seed . ':' . $user['id'], GAME_HMAC_KEY);

$redis->setex("game_active:{$user['id']}", 7200, $session_id);

respond_success([
    'session_id' => $session_id,
    'seed' => $seed,
    'hmac' => $hmac,
    'server_time' => (int)(microtime(true) * 1000),
]);