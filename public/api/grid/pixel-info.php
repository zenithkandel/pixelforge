<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$x = isset($_GET['x']) ? (int)$_GET['x'] : -1;
$y = isset($_GET['y']) ? (int)$_GET['y'] : -1;

if (!validate_coord($x) || !validate_coord($y)) {
    respond_error('invalid_coords', 'Invalid coordinates', 400);
}

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("
        SELECT p.*, u.username as owner_name
        FROM pixels p
        LEFT JOIN users u ON p.owner_id = u.id
        WHERE p.x = ? AND p.y = ?
        AND p.grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1)
    ");
    $stmt->execute([$x, $y]);
    $pixel = $stmt->fetch();

    if ($pixel) {
        respond_success([
            'x' => $pixel['x'],
            'y' => $pixel['y'],
            'color' => $pixel['color'],
            'owner' => $pixel['owner_name'],
            'owner_id' => $pixel['owner_id'],
            'purchased_at' => $pixel['purchased_at'],
            'is_owned' => true
        ]);
    } else {
        respond_success([
            'x' => $x,
            'y' => $y,
            'color' => '#FFFFFF',
            'owner' => null,
            'owner_id' => null,
            'purchased_at' => null,
            'is_owned' => false
        ]);
    }

} catch (Exception $e) {
    log_error('Pixel info failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to get pixel info', 500);
}