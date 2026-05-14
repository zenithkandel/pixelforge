<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_method('GET');

$x = isset($_GET['x']) ? (int)$_GET['x'] : -1;
$y = isset($_GET['y']) ? (int)$_GET['y'] : -1;

if (!validate_coord($x) || !validate_coord($y)) {
    respond_error('invalid_coord', 'Coordinates must be 0-799', 400);
}

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT p.x, p.y, p.color, p.owner_id, p.purchased_at, u.username
    FROM pixels p
    JOIN users u ON u.id = p.owner_id
    JOIN grid_sessions gs ON gs.id = p.grid_session_id AND gs.is_current = 1
    WHERE p.x = ? AND p.y = ?
");
$stmt->execute([$x, $y]);
$pixel = $stmt->fetch();

if ($pixel) {
    respond_success([
        'x' => $pixel['x'],
        'y' => $pixel['y'],
        'color' => $pixel['color'],
        'owner' => $pixel['username'],
        'purchased_at' => $pixel['purchased_at'],
        'is_owned' => true,
    ]);
}

respond_success([
    'x' => $x,
    'y' => $y,
    'color' => '#FFFFFF',
    'owner' => null,
    'purchased_at' => null,
    'is_owned' => false,
]);