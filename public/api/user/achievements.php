<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_method('GET');

$user = require_auth();

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT a.key_name, a.title, a.description, a.pxl_reward, a.icon_class,
        ua.earned_at, ua.pxl_claimed
    FROM achievements a
    LEFT JOIN user_achievements ua ON ua.achievement_id = a.id AND ua.user_id = ?
    ORDER BY a.id ASC
");
$stmt->execute([$user['id']]);
$all = $stmt->fetchAll();

$result = [];
foreach ($all as $row) {
    $result[] = [
        'key_name' => $row['key_name'],
        'title' => $row['title'],
        'description' => $row['description'],
        'pxl_reward' => $row['pxl_reward'],
        'icon_class' => $row['icon_class'],
        'earned' => $row['earned_at'] !== null,
        'earned_at' => $row['earned_at'],
        'claimed' => (bool)$row['pxl_claimed'],
    ];
}

respond_success(['achievements' => $result]);