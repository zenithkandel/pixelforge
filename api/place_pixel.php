<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp.php';
require_once __DIR__ . '/../includes/achievements.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$x = (int)($_POST['x'] ?? -1);
$y = (int)($_POST['y'] ?? -1);
$color = $_POST['color'] ?? '';
$csrf = $_POST['csrf_token'] ?? '';

if (!csrf_verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($x < 0 || $x > 99 || $y < 0 || $y > 99) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid color format']);
    exit;
}

Database::query("DELETE FROM pixel_placements WHERE placed_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$recent = Database::fetch(
    "SELECT COUNT(*) as cnt FROM pixel_placements WHERE user_id = ? AND placed_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
    [$_SESSION['user_id']]
);
if ($recent['cnt'] >= RATE_LIMIT_PIXEL_PLACEMENTS) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many pixel placements. Please wait.']);
    exit;
}

$existing = Database::fetch("SELECT * FROM pixels WHERE x = ? AND y = ?", [$x, $y]);

if ($existing) {
    if ($existing['owner_id'] === $_SESSION['user_id']) {
        $xp_gain = XP_PIXEL_REPAINT;
    } else {
        http_response_code(403);
        echo json_encode(['error' => 'This pixel is owned by ' . ($existing['username'] ?? 'another user')]);
        exit;
    }
} else {
    $user = get_logged_in_user();
    if ($user['balance'] < PIXEL_COST) {
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient balance']);
        exit;
    }
    deduct_balance($_SESSION['user_id'], PIXEL_COST);
    $xp_gain = XP_PIXEL_PLACED;
}

$expires = date('Y-m-d H:i:s', strtotime('+14 days'));

if ($existing && $existing['owner_id'] === $_SESSION['user_id']) {
    Database::query(
        "UPDATE pixels SET color = ?, placed_at = NOW(), expires_at = ? WHERE x = ? AND y = ?",
        [$color, $expires, $x, $y]
    );
} else {
    try {
        Database::query(
            "INSERT INTO pixels (x, y, color, owner_id, placed_at, expires_at) VALUES (?, ?, ?, ?, NOW(), ?)",
            [$x, $y, $color, $_SESSION['user_id'], $expires]
        );
    } catch (Exception $e) {
        http_response_code(409);
        echo json_encode(['error' => 'Pixel just claimed by another user — refresh and try again']);
        exit;
    }
}

Database::query("INSERT INTO pixel_placements (user_id) VALUES (?)", [$_SESSION['user_id']]);

$xp_result = add_xp($_SESSION['user_id'], $xp_gain);
$user = get_logged_in_user();

$new_achievements = check_pixel_achievements($_SESSION['user_id']);

$achievements_data = array_map(function($a) {
    return [
        'slug' => $a['slug'],
        'name' => $a['name'],
        'icon' => $a['icon'],
        'reward' => $a['reward']
    ];
}, $new_achievements);

echo json_encode([
    'success' => true,
    'x' => $x,
    'y' => $y,
    'color' => $color,
    'new_balance' => $user['balance'],
    'new_xp' => $user['xp'],
    'new_level' => $user['level'],
    'level_up' => $xp_result['level_up'] ?? false,
    'new_achievements' => $achievements_data
]);