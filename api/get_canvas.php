<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';

header('Content-Type: application/json');

try {
    $db = get_db();

    $stmt = $db->prepare('DELETE FROM pixels WHERE expires_at < NOW()');
    $stmt->execute();
    $purged = $stmt->rowCount();
    if ($purged > 0) {
        log_info('CANVAS', 'Expired pixels purged', ['count' => $purged]);
    }

    $stmt = $db->prepare('
        SELECT p.x, p.y, p.color, p.owner_id, p.placed_at, p.expires_at,
               u.username, u.level
        FROM pixels p
        LEFT JOIN users u ON p.owner_id = u.id
    ');
    $stmt->execute();
    $pixels = $stmt->fetchAll();

    $result = [];
    foreach ($pixels as $p) {
        $result[] = [
            'x'          => (int)$p['x'],
            'y'          => (int)$p['y'],
            'color'      => $p['color'],
            'owner_id'   => $p['owner_id'] ? (int)$p['owner_id'] : null,
            'username'   => $p['username'] ?? null,
            'level'      => $p['level'] ? (int)$p['level'] : null,
            'placed_at'  => $p['placed_at'],
            'expires_at' => $p['expires_at'],
        ];
    }

    $stmt = $db->query('SELECT COUNT(*) FROM pixels');
    $total = (int)$stmt->fetchColumn();

    log_debug('CANVAS', 'Canvas data served', ['pixel_count' => $total]);

    echo json_encode([
        'pixels'        => $result,
        'total_claimed' => $total,
        'timestamp'     => date('c'),
    ]);
} catch (PDOException $e) {
    log_error('DB', 'Canvas fetch error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch canvas']);
}
