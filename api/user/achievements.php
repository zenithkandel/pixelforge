<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

require_auth();

$user_id = get_current_user_id();

try {
    $achievements = get_user_achievements($user_id);
    
    respond_success($achievements);

} catch (Exception $e) {
    log_error('Achievements fetch failed', ['error' => $e->getMessage()]);
    respond_error('fetch_failed', 'Failed to fetch achievements', 500);
}

?>
