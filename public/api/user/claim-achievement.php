<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_method('POST');

$user = require_auth();

$data = get_json_body();

if (!isset($data['achievement_key'])) {
    respond_error('missing_fields', 'achievement_key required', 400);
}

$key = $data['achievement_key'];

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id, pxl_reward FROM achievements WHERE key_name = ?");
$stmt->execute([$key]);
$ach = $stmt->fetch();

if (!$ach) {
    respond_error('not_found', 'Achievement not found', 404);
}

$stmt = $pdo->prepare("SELECT pxl_claimed FROM user_achievements WHERE user_id = ? AND achievement_id = ?");
$stmt->execute([$user['id'], $ach['id']]);
$ua = $stmt->fetch();

if (!$ua) {
    respond_error('not_earned', 'Achievement not yet earned', 400);
}

if ($ua['pxl_claimed']) {
    respond_error('already_claimed', 'Achievement already claimed', 400);
}

$pdo->beginTransaction();
$stmt = $pdo->prepare("UPDATE user_achievements SET pxl_claimed = 1 WHERE user_id = ? AND achievement_id = ?");
$stmt->execute([$user['id'], $ach['id']]);

$new_balance = credit_pxl($pdo, $user['id'], $ach['pxl_reward'], 'achievement', $ach['id'], "Achievement: {$key}");

log_audit('achievement_claimed', $user['id'], "achievement={$key} pxl={$ach['pxl_reward']}");

$pdo->commit();

respond_success([
    'pxl_credited' => $ach['pxl_reward'],
    'new_balance' => $new_balance,
]);