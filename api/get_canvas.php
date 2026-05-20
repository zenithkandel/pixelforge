<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';

header('Content-Type: application/json');

Database::query("DELETE FROM pixels WHERE expires_at < NOW()");

$pixels = Database::fetchAll("
    SELECT p.x, p.y, p.color, p.owner_id, p.placed_at, p.expires_at, u.username, u.level as owner_level
    FROM pixels p
    LEFT JOIN users u ON p.owner_id = u.id
    WHERE p.owner_id IS NOT NULL
");

$now = time();
$week_ago = strtotime('-7 days');
$two_weeks_ago = strtotime('-14 days');

$result = [];
foreach ($pixels as $p) {
    $placed = strtotime($p['placed_at']);
    $opacity = 1;
    if ($placed < $week_ago && $placed >= $two_weeks_ago) {
        $opacity = 0.7;
    } elseif ($placed < $two_weeks_ago) {
        continue;
    }

    $expires_at = $p['expires_at'] ? strtotime($p['expires_at']) : null;
    $days_left = $expires_at ? ceil(($expires_at - $now) / 86400) : null;

    $result[] = [
        'x' => $p['x'],
        'y' => $p['y'],
        'color' => $p['color'],
        'owner_id' => $p['owner_id'],
        'username' => $p['username'],
        'owner_level' => $p['owner_level'] ?? 1,
        'placed_at' => $p['placed_at'],
        'opacity' => $opacity,
        'days_left' => $days_left
    ];
}

echo json_encode([
    'pixels' => $result,
    'timestamp' => time()
]);