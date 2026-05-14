<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_method('POST');

$ip = get_client_ip();

if (!check_rate_limit("register:{$ip}", 3, 3600)) {
    respond_error('rate_limited', 'Too many registration attempts. Please try again later.', 429);
}

$data = get_json_body();

$username = $data['username'] ?? '';
$email = $data['email'] ?? '';
$password = $data['password'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

if (!verify_csrf($csrf_token)) {
    respond_error('invalid_csrf', 'Invalid CSRF token', 403);
}

if (!validate_username($username)) {
    respond_error('invalid_username', 'Username must be 3-20 characters, alphanumeric and underscores only', 400);
}

if (!validate_email($email)) {
    respond_error('invalid_email', 'Please provide a valid email address', 400);
}

if (!validate_password($password)) {
    respond_error('invalid_password', 'Password must be at least 8 characters with at least one letter and one number', 400);
}

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        respond_error('username_taken', 'This username is already taken', 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        respond_error('email_taken', 'This email is already registered', 400);
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $verify_token = bin2hex(random_bytes(32));
    $verify_expires = time() + 86400;

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, email_verify_token, email_verify_expires) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))");
    $stmt->execute([$username, $email, $password_hash, $verify_token, $verify_expires]);

    $user_id = (int)$pdo->lastInsertId();

    log_audit('user_register', $user_id, ['username' => $username, 'email' => $email]);

    $verify_link = get_base_url() . '/verify.php?token=' . $verify_token;

    $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username_attempted, success) VALUES (?, ?, 1)");
    $stmt->execute([$ip, $username]);

    respond_success(['message' => 'Account created! Check your email to verify your account.']);

} catch (Exception $e) {
    log_error('Registration failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'An error occurred during registration', 500);
}