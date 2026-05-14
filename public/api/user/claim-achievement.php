<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();
require_csrf();

$user = get_current_user_data();

$json = json_decode(file_get_contents('php://input'), true);
$key_name = $json['achievement_key'] ?? '';

if (empty($key_name)) {
    respond_error('invalid_input', 'Achievement key required.');
}

$db = DB::getInstance();

try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        SELECT a.id, a.pxl_reward, ua.pxl_claimed 
        FROM achievements a
        JOIN user_achievements ua ON a.id = ua.achievement_id
        WHERE ua.user_id = ? AND a.key_name = ?
        FOR UPDATE
    ");
    $stmt->execute([$user['id'], $key_name]);
    $ach = $stmt->fetch();
    
    if (!$ach) {
        $db->rollBack();
        respond_error('not_earned', 'You have not earned this achievement yet.');
    }
    if ($ach['pxl_claimed']) {
        $db->rollBack();
        respond_error('already_claimed', 'Achievement reward already claimed.');
    }
    
    $stmt = $db->prepare("UPDATE user_achievements SET pxl_claimed = 1 WHERE user_id = ? AND achievement_id = ?");
    $stmt->execute([$user['id'], $ach['id']]);
    
    $new_balance = pxl_credit($user['id'], $ach['pxl_reward'], 'achievement', $key_name, "Claimed achievement: $key_name");
    
    $db->commit();
    respond_success(['new_balance' => $new_balance]);
    
} catch (Exception $e) {
    $db->rollBack();
    respond_error('server_error', 'An error occurred.', 500);
}
