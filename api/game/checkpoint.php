<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_verified();

$user_id = get_current_user_id();
$data = get_request_json();

$session_token = sanitize_string($data['session_token'] ?? '');
$score = intval($data['score'] ?? 0);
$lives_remaining = intval($data['lives_remaining'] ?? 0);
$speed_tier = intval($data['speed_tier'] ?? 1);
$timestamp = intval($data['timestamp'] ?? time());
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
$checkpoint_data = [
    'session_token' => $session_token,
    'score' => $score,
    'timestamp' => $timestamp
];

if (!validate_game_hmac($checkpoint_data, $hmac)) {
    log_warning('Invalid HMAC on checkpoint', ['user_id' => $user_id, 'session_token' => $session_token]);
    respond_error('invalid_hmac', 'Invalid checkpoint signature', 401);
}

// Validate score plausibility
$time_elapsed = $timestamp - $session['started_at'];
if (!validate_game_score($score, $time_elapsed)) {
    log_warning('Suspicious score on checkpoint', ['user_id' => $user_id, 'score' => $score, 'time' => $time_elapsed]);
    respond_error('score_invalid', 'Score appears invalid', 400);
}

try {
    // Store checkpoint in database
    // (In a real system, we'd store this to validate the final submission)
    
    respond_success([], 'Checkpoint recorded');

} catch (Exception $e) {
    log_error('Checkpoint failed', ['error' => $e->getMessage()]);
    respond_error('checkpoint_failed', 'Failed to record checkpoint', 500);
}

?>
