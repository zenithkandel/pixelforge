<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_method('GET');

$token = $_GET['token'] ?? '';

if (empty($token) || strlen($token) !== 64) {
    header('Location: ../index.php?verify_error=invalid_token');
    exit;
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id, email_verify_expires FROM users WHERE email_verify_token = ? AND email_verified = 0");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user || strtotime($user['email_verify_expires']) < time()) {
    header('Location: ../index.php?verify_error=expired');
    exit;
}

$stmt = $pdo->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL, email_verify_expires = NULL WHERE id = ?");
$stmt->execute([$user['id']]);

log_audit('email_verified', $user['id']);

header('Location: ../index.php?verify_success=1');