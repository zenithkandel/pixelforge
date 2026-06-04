<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=5');

try {
    $stmt = db()->query("SELECT x, y, color, owner_id FROM pixels ORDER BY placed_at ASC");
    $pixels = $stmt->fetchAll();

    $countStmt = db()->query("SELECT COUNT(*) as total, COUNT(DISTINCT owner_id) as artists FROM pixels");
    $stats = $countStmt->fetch();

    echo json_encode([
        'success' => true,
        'pixels' => $pixels,
        'stats' => [
            'total_pixels' => (int)$stats['total'],
            'total_artists' => (int)$stats['artists']
        ],
        'grid_size' => 200,
        'timestamp' => time()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load canvas']);
}
