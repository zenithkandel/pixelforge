<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

$x = isset($_GET['x']) ? (int)$_GET['x'] : -1;
$y = isset($_GET['y']) ? (int)$_GET['y'] : -1;

if (!validate_coord($x, $y)) {
    respond_error('invalid_coord', 'Invalid coordinates.');
}

$db = DB::getInstance();
$stmt = $db->prepare("SELECT p.x, p.y, p.color, p.purchased_at, u.username as owner FROM pixels p JOIN users u ON p.owner_id = u.id WHERE p.x = ? AND p.y = ?");
$stmt->execute([$x, $y]);
$pixel = $stmt->fetch();

if (!$pixel) {
    respond_success([
        'x' => $x,
        'y' => $y,
        'color' => '#FFFFFF',
        'is_owned' => false
    ]);
} else {
    $pixel['is_owned'] = true;
    respond_success($pixel);
}
