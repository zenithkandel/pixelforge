<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('POST');

$data = get_json_body();

if (!isset($data['token']) || !isset($data['new_password'])) {
    respond_error('missing_fields', 'Token and new password are required', 400);
}

if (!validate_password($data['new_password'])) {
    respond_error('invalid_password', 'Password must be at least 8 chars with 1 letter and 1 number', 400);
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id, username, password_reset_expires FROM users WHERE password_reset_token = ?");
$stmt->execute([$data['token']]);
$user = $stmt->fetch();

if (!$user || strtotime($user['password_reset_expires']) < time()) {
    respond_error('invalid_token', 'Reset token expired or invalid', 400);
}

$hash = password_hash($data['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);

$stmt = $pdo->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
$stmt->execute([$hash, $user['id']]);

log_audit('password_reset', $user['id']);

respond_success(['message' => 'Password updated successfully.']);