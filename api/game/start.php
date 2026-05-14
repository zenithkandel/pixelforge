<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_verified();

$user_id = get_current_user_id();

// Check if user already has active game session
$active_session = Database::fetch(
    'SELECT id FROM game_sessions WHERE user_id = ? AND is_active = 1',
    [$user_id]
);

if ($active_session) {
    respond_error('active_session_exists', 'You already have an active game session', 409);
}

// Rate limit game starts
if (!RateLimit::check("game_start:$user_id", 20, 3600)) {
    respond_error('rate_limited', 'You are starting too many games', 429);
}

try {
    // Generate session token and seed
    $session_token = generate_game_session_token();
    $seed = generate_game_seed();

    // Create HMAC for this session
    $session_data = [
        'user_id' => $user_id,
        'seed' => $seed,
        'timestamp' => time()
    ];
    $hmac = hash_hmac('sha256', json_encode($session_data), GAME_HMAC_KEY);

    // Store session
    Database::execute(
        'INSERT INTO game_sessions (user_id, session_token, seed, hmac_key, is_active, started_at) VALUES (?, ?, ?, ?, 1, NOW())',
        [$user_id, $session_token, $seed, $hmac]
    );

    $game_session_id = Database::lastInsertId();

    // Cache session in Redis with 2-hour timeout
    Redis::set("game_session:$session_token", json_encode([
        'user_id' => $user_id,
        'game_session_id' => $game_session_id,
        'seed' => $seed,
        'started_at' => time()
    ]), 7200);

    log_audit('game_started', $user_id, ['session_token' => $session_token, 'seed' => $seed]);

    respond_success([
        'session_token' => $session_token,
        'seed' => $seed,
        'hmac_key' => $hmac
    ], 'Game session started', 201);

} catch (Exception $e) {
    log_error('Game start failed', ['error' => $e->getMessage(), 'user_id' => $user_id]);
    respond_error('start_failed', 'Failed to start game', 500);
}

?>