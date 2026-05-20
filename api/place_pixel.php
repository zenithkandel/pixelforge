<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

csrf_verify();

$x = (int)($_POST['x'] ?? -1);
$y = (int)($_POST['y'] ?? -1);
$color = trim($_POST['color'] ?? '');

if ($x < 0 || $x > 99 || $y < 0 || $y > 99) {
    log_sec('PIXEL', 'Pixel rejected — out of bounds', ['x' => $x, 'y' => $y]);
    http_response_code(400);
    echo json_encode(['error' => 'Out of bounds']);
    exit();
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    log_sec('PIXEL', 'Pixel rejected — invalid color', ['color' => $color]);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid color format']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$db = get_db();

try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM pixel_placements WHERE user_id = ? AND placed_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
    $stmt->execute([$user_id]);
    $placements = (int)$stmt->fetchColumn();

    if ($placements >= 10) {
        log_sec('PIXEL', 'Pixel rejected — rate limit', ['count' => $placements]);
        http_response_code(429);
        echo json_encode(['error' => 'Too many placements — wait a moment']);
        exit();
    }

    $db->beginTransaction();

    $stmt = $db->prepare('SELECT * FROM pixels WHERE x = ? AND y = ? FOR UPDATE');
    $stmt->execute([$x, $y]);
    $existing = $stmt->fetch();

    log_info('PIXEL', 'Pixel placement request', ['x' => $x, 'y' => $y, 'color' => $color]);

    if ($existing) {
        if ((int)$existing['owner_id'] === $user_id) {
            $stmt = $db->prepare('UPDATE pixels SET color = ?, placed_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL 14 DAY) WHERE x = ? AND y = ? AND owner_id = ?');
            $stmt->execute([$color, $x, $y, $user_id]);

            $db->prepare('INSERT INTO pixel_placements (user_id) VALUES (?)')->execute([$user_id]);

            $xp_result = add_xp($db, $user_id, 1, true);

            log_info('PIXEL', 'Own pixel repainted', ['x' => $x, 'y' => $y, 'color' => $color]);
        } else {
            $db->rollBack();
            log_sec('PIXEL', 'Pixel rejected — owned by other', ['x' => $x, 'y' => $y, 'owner' => $existing['owner_id']]);
            http_response_code(403);
            echo json_encode(['error' => 'Owned by ' . ($existing['owner_id'] ?? 'another user')]);
            exit();
        }
    } else {
        $stmt = $db->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || (int)$user['balance'] < 5) {
            $db->rollBack();
            log_warn('PIXEL', 'Pixel rejected — insufficient balance', ['balance' => $user['balance'] ?? 0]);
            http_response_code(402);
            echo json_encode(['error' => 'Insufficient balance']);
            exit();
        }

        $stmt = $db->prepare('INSERT INTO pixels (x, y, color, owner_id, placed_at, expires_at) VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 14 DAY))');
        $stmt->execute([$x, $y, $color, $user_id]);

        $stmt = $db->prepare('UPDATE users SET balance = balance - 5 WHERE id = ?');
        $stmt->execute([$user_id]);

        $db->prepare('INSERT INTO pixel_placements (user_id) VALUES (?)')->execute([$user_id]);

        $xp_result = add_xp($db, $user_id, 5, true);

        log_info('PIXEL', 'Pixel claimed', ['x' => $x, 'y' => $y, 'color' => $color, 'new_balance' => (int)$user['balance'] - 5]);
    }

    $db->commit();

    $new_user = current_user();
    $achievements = check_achievements($db, $user_id);

    echo json_encode([
        'success'          => true,
        'new_balance'      => $new_user ? (int)$new_user['balance'] : 0,
        'new_xp'           => $xp_result['new_xp'] ?? 0,
        'new_level'        => $xp_result['new_level'] ?? 1,
        'leveled_up'       => $xp_result['leveled_up'] ?? false,
        'new_achievements' => $achievements,
    ]);
} catch (PDOException $e) {
    $db->rollBack();
    log_error('DB', 'Pixel placement error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to place pixel']);
}
