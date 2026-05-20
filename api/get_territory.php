<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';

header('Content-Type: application/json');

try {
    $db = get_db();

    $stmt = $db->prepare('
        SELECT p.x, p.y, u.username, u.avatar_color, u.level, COUNT(*) AS pixel_count
        FROM pixels p
        JOIN users u ON p.owner_id = u.id
        GROUP BY p.owner_id, p.x, p.y
    ');
    $stmt->execute();
    $all = $stmt->fetchAll();

    $pixels = [];
    $owners = [];

    foreach ($all as $p) {
        $pixels[] = [
            'x'            => (int)$p['x'],
            'y'            => (int)$p['y'],
            'owner_id'     => (int)$p['x'] + (int)$p['y'] * 100,
            'avatar_color' => $p['avatar_color'],
        ];
        $oid = (int)$p['x'];
        if (!isset($owners[$oid])) {
            $owners[$oid] = [
                'username'    => $p['username'],
                'avatar_color' => $p['avatar_color'],
                'level'       => (int)$p['level'],
                'pixel_count' => 0,
            ];
        }
        $owners[$oid]['pixel_count']++;
    }

    usort($owners, function($a, $b) { return $b['pixel_count'] - $a['pixel_count']; });
    $top_owners = array_slice($owners, 0, 5);

    echo json_encode([
        'pixels'     => $pixels,
        'top_owners' => $top_owners,
    ]);
} catch (PDOException $e) {
    log_error('DB', 'Territory fetch error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch territory']);
}
