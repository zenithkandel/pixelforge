<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

csrf_verify();

$action = $_POST['action'] ?? '';
$admin_id = (int)$_SESSION['user_id'];
$db = get_db();

try {
    if ($action === 'erase_pixel') {
        $x = (int)($_POST['x'] ?? -1);
        $y = (int)($_POST['y'] ?? -1);
        if ($x < 0 || $x > 99 || $y < 0 || $y > 99) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid coordinates']);
            exit();
        }
        $db->prepare('DELETE FROM pixels WHERE x = ? AND y = ?')->execute([$x, $y]);
        $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
           ->execute([$admin_id, 'erase_pixel', 'pixel', "Erased pixel ($x, $y)"]);
        log_admin('ADMIN', 'Admin erased pixel', ['x' => $x, 'y' => $y]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'reset_canvas') {
        $db->exec('DELETE FROM pixels');
        $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, details) VALUES (?, ?, ?, ?)')
           ->execute([$admin_id, 'canvas_reset', 'canvas', 'Full canvas reset']);
        log_admin('ADMIN', 'Canvas fully reset');
        echo json_encode(['success' => true]);
    } elseif ($action === 'set_balance') {
        $target_id = (int)($_POST['user_id'] ?? 0);
        $amount = max(0, (int)($_POST['amount'] ?? 0));
        $db->prepare('UPDATE users SET balance = ? WHERE id = ?')->execute([$amount, $target_id]);
        $db->prepare('INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)')
           ->execute([$admin_id, 'set_balance', 'user', $target_id, "Set balance to $amount"]);
        log_admin('ADMIN', 'Admin set user balance', ['user_id' => $target_id, 'amount' => $amount]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    log_error('DB', 'Admin action error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    http_response_code(500);
    echo json_encode(['error' => 'Action failed']);
}
