<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $snapshot = Database::fetch("SELECT snapshot, captured_at FROM canvas_snapshots WHERE id = ?", [$id]);
    if ($snapshot) {
        echo json_encode([
            'snapshot' => json_decode($snapshot['snapshot']),
            'captured_at' => $snapshot['captured_at']
        ]);
        exit;
    }
}

$snapshots = Database::fetchAll("SELECT id, captured_at FROM canvas_snapshots ORDER BY captured_at DESC LIMIT 24");
echo json_encode(['snapshots' => $snapshots]);