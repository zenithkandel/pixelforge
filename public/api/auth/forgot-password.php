<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');

if (!check_rate_limit('pwreset:' . get_client_ip(), 3, 3600)) {
    respond_error('rate_limited', 'Too many requests. Try again later.', 429);
}

$data = get_json_body();

if (!isset($data['email']) || !validate_email($data['email'])) {
    respond_error('invalid_email', 'Invalid email address', 400);
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
$stmt->execute([$data['email']]);
$user = $stmt->fetch();

if ($user) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $user['id']]);

    send_password_reset_email($data['email'], $user['username'], $token);
}

respond_success(['message' => 'If that email exists, a reset link was sent.']);