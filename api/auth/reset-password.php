<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

$data = get_request_json();
$token = sanitize_string($data['token'] ?? '');
$password = $data['password'] ?? '';
$password_confirm = $data['password_confirm'] ?? '';

if (empty($token)) {
    respond_error('missing_token', 'Reset token is required');
}

if (!validate_password($password)) {
    respond_error('invalid_password', 'Password must be at least 8 characters and contain at least one letter and one number');
}

if ($password !== $password_confirm) {
    respond_error('password_mismatch', 'Passwords do not match');
}

try {
    // Get user ID from token
    $user_id = Redis::get("reset_token:$token");

    if (!$user_id) {
        respond_error('invalid_token', 'Reset token is invalid or expired', 401);
    }

    // Hash new password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    // Update password
    Database::execute(
        'UPDATE users SET password_hash = ? WHERE id = ?',
        [$password_hash, $user_id]
    );

    // Delete token
    Redis::del("reset_token:$token");

    log_audit('password_reset', $user_id);

    respond_success([], 'Password reset successfully. You can now log in with your new password.');

} catch (Exception $e) {
    log_error('Password reset failed', ['error' => $e->getMessage()]);
    respond_error('reset_failed', 'An error occurred during password reset', 500);
}

?>