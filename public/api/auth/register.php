<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_method('POST');

$data = get_json_body();

if (!isset($data['csrf_token']) || !isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
    respond_error('missing_fields', 'All fields are required', 400);
}

require_csrf($data['csrf_token']);

if (!check_rate_limit('register:' . get_client_ip(), 3, 3600)) {
    respond_error('rate_limited', 'Too many registrations. Try again later.', 429);
}

if (!validate_username($data['username'])) {
    respond_error('invalid_username', 'Username must be 3-20 chars, alphanumeric + underscore only', 400);
}
if (!validate_email($data['email'])) {
    respond_error('invalid_email', 'Invalid email address', 400);
}
if (!validate_password($data['password'])) {
    respond_error('invalid_password', 'Password must be at least 8 chars with 1 letter and 1 number', 400);
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$data['username']]);
if ($stmt->fetch()) {
    respond_error('username_taken', 'Username already exists', 400);
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$data['email']]);
if ($stmt->fetch()) {
    respond_error('email_taken', 'Email already exists', 400);
}

$password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
$verify_token = bin2hex(random_bytes(32));
$verify_expires = date('Y-m-d H:i:s', time() + 86400);

$stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, email_verify_token, email_verify_expires, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->execute([$data['username'], $data['email'], $password_hash, $verify_token, $verify_expires]);

send_verification_email($data['email'], $data['username'], $verify_token);

respond_success(['message' => 'Check your email to verify your account.']);