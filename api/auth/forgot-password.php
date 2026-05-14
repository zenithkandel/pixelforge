<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

$data = get_request_json();
$email = sanitize_string($data['email'] ?? '');

if (!validate_email($email)) {
    respond_error('invalid_email', 'Invalid email address');
}

// Rate limit password reset requests
$ip = get_client_ip();
if (!RateLimit::check("forgot_password:$ip", 3, 3600)) {
    respond_error('rate_limited', 'Too many password reset requests. Please try again later', 429);
}

try {
    // Find user by email
    $user = Database::fetch(
        'SELECT id, username FROM users WHERE LOWER(email) = LOWER(?)',
        [$email]
    );

    // Always return success for security (don't leak if email exists)
    if ($user) {
        $reset_token = bin2hex(random_bytes(32));
        Redis::set("reset_token:$reset_token", $user['id'], 3600); // 1 hour

        // TODO: Send reset email with token
        log_audit('password_reset_requested', $user['id'], ['email' => $email]);
    }

    respond_success(
        [],
        'If an account exists with that email, you will receive a password reset link shortly'
    );

} catch (Exception $e) {
    log_error('Forgot password failed', ['error' => $e->getMessage()]);
    respond_error('request_failed', 'An error occurred', 500);
}

?>
