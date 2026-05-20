<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';

header('Content-Type: application/json');

$users = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, COUNT(p.id) as pixel_count
    FROM users u
    LEFT JOIN pixels p ON p.owner_id = u.id AND (p.expires_at IS NULL OR p.expires_at > NOW())
    GROUP BY u.id
    HAVING pixel_count > 0
    ORDER BY pixel_count DESC
    LIMIT 10
");

echo json_encode(['territory' => $users]);