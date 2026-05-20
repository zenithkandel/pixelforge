<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

function log_admin_action($admin_id, $action, $target_type = null, $target_id = null, $details = null) {
    Database::query(
        "INSERT INTO admin_log (admin_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)",
        [$admin_id, $action, $target_type, $target_id, $details]
    );
}

switch ($action) {
    case 'erase_pixel':
        $x = (int)$_POST['x'];
        $y = (int)$_POST['y'];
        Database::query("DELETE FROM pixels WHERE x = ? AND y = ?", [$x, $y]);
        log_admin_action($_SESSION['user_id'], 'pixel_erase', 'pixel', null, "Erased pixel at ($x, $y)");
        echo json_encode(['success' => true]);
        break;

    case 'erase_pixels':
        $pixels = json_decode($_POST['pixels'] ?? '[]', true);
        if (empty($pixels)) {
            echo json_encode(['error' => 'No pixels selected']);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($pixels), '(?, ?)'));
        $params = [];
        foreach ($pixels as $p) {
            $params[] = $p['x'];
            $params[] = $p['y'];
        }
        Database::query("DELETE FROM pixels WHERE (x, y) IN (SELECT * FROM (SELECT 0, 0) AS t)"); // simplified
        foreach ($pixels as $p) {
            Database::query("DELETE FROM pixels WHERE x = ? AND y = ?", [$p['x'], $p['y']]);
        }
        log_admin_action($_SESSION['user_id'], 'pixels_erase', null, count($pixels), "Bulk erased " . count($pixels) . " pixels");
        echo json_encode(['success' => true, 'count' => count($pixels)]);
        break;

    case 'reset_canvas':
        Database::query("TRUNCATE TABLE pixels");
        log_admin_action($_SESSION['user_id'], 'canvas_reset', null, null, 'Full canvas reset');
        echo json_encode(['success' => true]);
        break;

    case 'fill_canvas':
        $color = $_POST['color'] ?? '#7c3aed';
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            echo json_encode(['error' => 'Invalid color']);
            exit;
        }
        $existing = Database::fetchAll("SELECT x, y FROM pixels");
        $existing_coords = [];
        foreach ($existing as $e) {
            $existing_coords[$e['x'] . ',' . $e['y']] = true;
        }
        $count = 0;
        $expires = date('Y-m-d H:i:s', strtotime('+14 days'));
        for ($x = 0; $x < 100; $x++) {
            for ($y = 0; $y < 100; $y++) {
                if (!isset($existing_coords["$x,$y"])) {
                    Database::query(
                        "INSERT INTO pixels (x, y, color, placed_at, expires_at) VALUES (?, ?, ?, NOW(), ?)",
                        [$x, $y, $color, $expires]
                    );
                    $count++;
                }
            }
        }
        log_admin_action($_SESSION['user_id'], 'canvas_fill', null, $count, "Filled $count unclaimed pixels with $color");
        echo json_encode(['success' => true, 'filled' => $count]);
        break;

    case 'update_balance':
        $user_id = (int)$_POST['user_id'];
        $balance = (int)$_POST['balance'];
        if ($balance < 0) {
            echo json_encode(['error' => 'Balance cannot be negative']);
            exit;
        }
        Database::query("UPDATE users SET balance = ? WHERE id = ?", [$balance, $user_id]);
        log_admin_action($_SESSION['user_id'], 'balance_update', 'user', $user_id, "Set balance to $balance");
        echo json_encode(['success' => true]);
        break;

    case 'add_balance':
        $user_id = (int)$_POST['user_id'];
        $delta = (int)$_POST['delta'];
        Database::query("UPDATE users SET balance = balance + ? WHERE id = ?", [$delta, $user_id]);
        $user = Database::fetch("SELECT balance FROM users WHERE id = ?", [$user_id]);
        log_admin_action($_SESSION['user_id'], 'balance_add', 'user', $user_id, "Added $delta (new balance: {$user['balance']})");
        echo json_encode(['success' => true, 'new_balance' => $user['balance']]);
        break;

    case 'change_role':
        $user_id = (int)$_POST['user_id'];
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['error' => 'Cannot change your own role']);
            exit;
        }
        $admin_count = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
        $user = Database::fetch("SELECT role FROM users WHERE id = ?", [$user_id]);
        if ($user['role'] === 'admin' && $admin_count['cnt'] <= 1) {
            echo json_encode(['error' => 'You are the only admin. Promote another user first.']);
            exit;
        }
        $new_role = $user['role'] === 'admin' ? 'user' : 'admin';
        Database::query("UPDATE users SET role = ? WHERE id = ?", [$new_role, $user_id]);
        log_admin_action($_SESSION['user_id'], 'role_change', 'user', $user_id, "Changed role to $new_role");
        echo json_encode(['success' => true, 'new_role' => $new_role]);
        break;

    case 'delete_user':
        $user_id = (int)$_POST['user_id'];
        if ($user_id == $_SESSION['user_id']) {
            echo json_encode(['error' => 'Cannot delete yourself']);
            exit;
        }
        Database::query("UPDATE pixels SET owner_id = NULL, expires_at = NOW() WHERE owner_id = ?", [$user_id]);
        Database::query("DELETE FROM users WHERE id = ?", [$user_id]);
        log_admin_action($_SESSION['user_id'], 'user_delete', 'user', $user_id, "Deleted user and released their pixels");
        echo json_encode(['success' => true]);
        break;

    case 'reset_streak':
        $user_id = (int)$_POST['user_id'];
        Database::query("UPDATE users SET streak_days = 0 WHERE id = ?", [$user_id]);
        log_admin_action($_SESSION['user_id'], 'streak_reset', 'user', $user_id, "Reset streak to 0");
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}