<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Method not allowed', 405);
}

require_csrf();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
check_rate_limit('register', $ip, 3, 3600);

$json = json_decode(file_get_contents('php://input'), true);
$username = trim($json['username'] ?? '');
$email = trim($json['email'] ?? '');
$password = $json['password'] ?? '';

if (!validate_username($username)) {
    respond_error('invalid_username', 'Username must be 3-20 alphanumeric characters or underscores.');
}
if (!validate_email($email)) {
    respond_error('invalid_email', 'Invalid email address.');
}
if (!validate_password($password)) {
    respond_error('invalid_password', 'Password must be at least 8 characters, containing at least 1 letter and 1 number.');
}

$db = DB::getInstance();

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
$stmt->execute([$username, $email]);
if ($stmt->fetchColumn() > 0) {
    respond_error('user_exists', 'Username or email already in use.');
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$verify_token = bin2hex(random_bytes(32));
$verify_expires = date('Y-m-d H:i:s', time() + 86400);

$stmt = $db->prepare("INSERT INTO users (username, email, password_hash, email_verify_token, email_verify_expires) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$username, $email, $hash, $verify_token, $verify_expires]);

// Usually we'd send an email here. For now just log it or ignore since it's local.
error_log("Verification link: /api/auth/verify.php?token=" . $verify_token);

respond_success(['message' => 'Check your email to verify your account.']);
