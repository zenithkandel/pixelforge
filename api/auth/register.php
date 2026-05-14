<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

$data = get_request_json();
$username = sanitize_string($data['username'] ?? '');
$email = sanitize_string($data['email'] ?? '');
$password = $data['password'] ?? '';
$password_confirm = $data['password_confirm'] ?? '';

// Validate inputs
if (!validate_username($username)) {
    respond_error('invalid_username', 'Username must be 3-20 characters and contain only letters, numbers, and underscores');
}

if (!validate_email($email)) {
    respond_error('invalid_email', 'Invalid email address');
}

if (!validate_password($password)) {
    respond_error('invalid_password', 'Password must be at least 8 characters and contain at least one letter and one number');
}

if ($password !== $password_confirm) {
    respond_error('password_mismatch', 'Passwords do not match');
}

// Rate limit registration
$ip = get_client_ip();
if (!RateLimit::check("register:$ip", 3, 3600)) {
    respond_error('rate_limited', 'Too many registration attempts. Please try again later', 429);
}

// Check if username or email already exists
$existing_user = Database::fetch(
    'SELECT id FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)',
    [$username, $email]
);

if ($existing_user) {
    respond_error('user_exists', 'Username or email already registered');
}

try {
    // Hash password
    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    // Create user
    Database::execute(
        'INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)',
        [$username, $email, $password_hash]
    );

    $user_id = Database::lastInsertId();

    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', time() + 86400); // 24 hours

    // Store token in Redis
    Redis::set("verify_token:$verification_token", $user_id, 86400);

    // Send verification email (simplified)
    $verification_link = APP_URL . '/verify.php?token=' . urlencode($verification_token);

    // TODO: Send actual email here
    log_info("User registered", ['user_id' => $user_id, 'username' => $username, 'email' => $email]);
    log_audit('user_register', $user_id, ['username' => $username, 'email' => $email]);

    respond_success(
        ['user_id' => $user_id],
        'Registration successful. Please verify your email.',
        201
    );

} catch (Exception $e) {
    log_error('Registration failed', ['error' => $e->getMessage()]);
    respond_error('registration_failed', 'An error occurred during registration', 500);
}

?>
