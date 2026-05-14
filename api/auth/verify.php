<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

$token = sanitize_string($_GET['token'] ?? '');

if (empty($token)) {
    respond_error('missing_token', 'Verification token is required');
}

// Get user ID from token
$user_id = Redis::get("verify_token:$token");

if (!$user_id) {
    respond_error('invalid_token', 'Verification token is invalid or expired', 401);
}

try {
    // Mark email as verified
    Database::execute(
        'UPDATE users SET is_email_verified = 1, email_verified_at = NOW() WHERE id = ?',
        [$user_id]
    );

    // Delete token
    Redis::del("verify_token:$token");

    // Check for first_game achievement eligibility (will be granted after first game)
    log_audit('email_verified', $user_id);

    respond_success([], 'Email verified successfully');

} catch (Exception $e) {
    log_error('Email verification failed', ['error' => $e->getMessage()]);
    respond_error('verification_failed', 'An error occurred during verification', 500);
}

?>