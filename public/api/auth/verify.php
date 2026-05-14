<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Invalid token");
}

$db = DB::getInstance();
$stmt = $db->prepare("SELECT id FROM users WHERE email_verify_token = ? AND email_verify_expires > NOW() AND email_verified = 0");
$stmt->execute([$token]);
$user_id = $stmt->fetchColumn();

if (!$user_id) {
    die("Token invalid or expired.");
}

$stmt = $db->prepare("UPDATE users SET email_verified = 1, email_verify_token = NULL WHERE id = ?");
$stmt->execute([$user_id]);

// Redirect to login
header("Location: /index.php?verified=1");
exit;
