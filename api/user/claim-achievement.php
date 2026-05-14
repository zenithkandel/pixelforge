<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_auth();

$user_id = get_current_user_id();
$data = get_request_json();

$achievement_key = sanitize_string($data['achievement_key'] ?? '');

if (empty($achievement_key)) {
    respond_error('missing_achievement', 'Achievement key is required');
}

try {
    // Rate limit claims
    if (!RateLimit::check("claim:$user_id", 50, 3600)) {
        respond_error('rate_limited', 'Too many claims', 429);
    }
    
    if (claim_achievement($user_id, $achievement_key)) {
        respond_success([], 'Achievement claimed successfully');
    } else {
        respond_error('claim_failed', 'Failed to claim achievement', 400);
    }

} catch (Exception $e) {
    log_error('Achievement claim failed', ['error' => $e->getMessage()]);
    respond_error('claim_error', 'An error occurred', 500);
}

?>
