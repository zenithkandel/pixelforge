<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$user = require_auth();

$user_id = $user['id'];
$ip = get_client_ip();

if (!check_rate_limit("game_start:{$user_id}", 20, 3600)) {
    respond_error('rate_limited', 'Too many game starts', 429);
}

$redis = get_redis();

$existing_session_id = null;
if ($redis) {
    $existing_session_id = $redis->get("game_active:{$user_id}");
}

try {
    $pdo = get_db();

    if ($existing_session_id) {
        $stmt = $pdo->prepare("UPDATE game_sessions SET ended_at = NOW(), invalidation_reason = 'new_session_started' WHERE id = ? AND user_id = ?");
        $stmt->execute([$existing_session_id, $user_id]);
    }

    $session_id = bin2hex(random_bytes(32));
    $prng_seed = random_int(0, PHP_INT_MAX);
    $client_key = derive_client_key($session_id);

    $stmt = $pdo->prepare("INSERT INTO game_sessions (id, user_id, prng_seed, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$session_id, $user_id, (string)$prng_seed, $ip]);

    if ($redis) {
        $redis->setex("game_active:{$user_id}", GAME_SESSION_TIMEOUT, $session_id);
    }

    log_game_event('session_start', $user_id, ['session_id' => $session_id, 'seed' => $prng_seed]);

    $today = date('Y-m-d');
    $is_first_game_today = true;
    if ($redis && has_daily_game($redis, $user_id, $today)) {
        $is_first_game_today = false;
    }

    $hmac = hash_hmac('sha256', "{$session_id}:{$prng_seed}:{$user_id}", GAME_HMAC_KEY);

    respond_success([
        'session_id' => $session_id,
        'seed' => $prng_seed,
        'client_key' => $client_key,
        'hmac' => $hmac,
        'server_time' => time() * 1000,
        'first_game_today' => $is_first_game_today
    ]);

} catch (Exception $e) {
    log_error('Game start failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to start game', 500);
}