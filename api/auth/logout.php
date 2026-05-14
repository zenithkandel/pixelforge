<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_auth();

$user_id = get_current_user_id();

log_audit('user_logout', $user_id);

logout_user();

respond_success([], 'Logged out successfully');

?>