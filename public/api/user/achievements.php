<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_auth();

$user = get_current_user_data();
$db = DB::getInstance();

$stmt = $db->prepare("
    SELECT a.*, ua.earned_at, ua.pxl_claimed 
    FROM achievements a 
    LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
    ORDER BY a.id ASC
");
$stmt->execute([$user['id']]);
$achievements = $stmt->fetchAll();

respond_success(['achievements' => $achievements]);
