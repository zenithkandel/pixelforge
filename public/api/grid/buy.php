<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('POST');

$user = require_verified();

$user_id = $user['id'];
$ip = get_client_ip();

if (!check_rate_limit("buy:{$user_id}", 10, 60)) {
    respond_error('rate_limited', 'Too many purchase attempts', 429, ['retry_after' => 60]);
}

$data = get_json_body();

$x = isset($data['x']) ? (int)$data['x'] : -1;
$y = isset($data['y']) ? (int)$data['y'] : -1;
$color = $data['color'] ?? '';
$csrf_token = $data['csrf_token'] ?? '';

if (!verify_csrf($csrf_token)) {
    respond_error('invalid_csrf', 'Invalid CSRF token', 403);
}

if (!validate_coord($x) || !validate_coord($y)) {
    respond_error('invalid_coords', 'Coordinates must be between 0 and 2047', 400);
}

if (!validate_color($color)) {
    respond_error('invalid_color', 'Invalid color format', 400);
}

$redis = get_redis();
if (!$redis) {
    respond_error('server_error', 'Service temporarily unavailable', 503);
}

$lock_key = "pixel_lock:{$x}:{$y}";
$lock_token = bin2hex(random_bytes(16));

$locked = $redis->set($lock_key, $lock_token, ['NX', 'PX' => 5000]);

if (!$locked) {
    respond_error('concurrent_conflict', 'This pixel is being purchased', 409, ['retry_after' => 1]);
}

try {
    $pdo = get_db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    if (!$user_data || $user_data['pxl_balance'] < 1) {
        $pdo->rollBack();
        $redis->del($lock_key);
        respond_error('insufficient_pxl', 'Not enough PXL', 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1");
    $stmt->execute();
    $grid_session = $stmt->fetch();

    if (!$grid_session) {
        $pdo->rollBack();
        $redis->del($lock_key);
        respond_error('server_error', 'No active grid session', 500);
    }

    $new_balance = $user_data['pxl_balance'] - 1;

    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = ?, total_pxl_spent = total_pxl_spent + 1 WHERE id = ?");
    $stmt->execute([$new_balance, $user_id]);

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?, -1, 'pixel_spend', ?, ?, ?)");
    $stmt->execute([$user_id, "{$x},{$y}", $new_balance, "Pixel ({$x},{$y}) set to {$color}"]);

    $stmt = $pdo->prepare("
        INSERT INTO pixels (x, y, color, owner_id, grid_session_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE color = VALUES(color), owner_id = VALUES(owner_id), purchased_at = NOW()
    ");
    $stmt->execute([$x, $y, $color, $user_id, $grid_session['id']]);

    $stmt = $pdo->prepare("INSERT INTO pixel_history (user_id, x, y, color, pxl_cost, grid_session_id) VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->execute([$user_id, $x, $y, $color, $grid_session['id']]);

    $chunk_x = intdiv($x, 64);
    $chunk_y = intdiv($y, 64);

    $stmt = $pdo->prepare("UPDATE chunks SET version = version + 1 WHERE chunk_x = ? AND chunk_y = ?");
    $stmt->execute([$chunk_x, $chunk_y]);

    $pdo->commit();

    $redis->del("chunk:{$chunk_x}:{$chunk_y}");
    $new_version = $redis->incr("chunk_v:{$chunk_x}:{$chunk_y}");

    $redis->publish('sse_channel', json_encode([
        'type' => 'pixel',
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'username' => $user['username'],
        'cx' => $chunk_x,
        'cy' => $chunk_y,
        'chunk_version' => $new_version
    ]));

    $redis->del($lock_key);

    log_audit('pixel_purchase', $user_id, ['x' => $x, 'y' => $y, 'color' => $color]);

    check_and_grant_achievements($pdo, $user_id, 'pixel_buy', []);

    respond_success([
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'new_balance' => $new_balance,
        'chunk_version' => $new_version
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (isset($redis)) {
        $redis->del($lock_key);
    }

    log_error('Pixel purchase failed', ['exception' => $e->getMessage(), 'x' => $x, 'y' => $y]);
    respond_error('server_error', 'An error occurred', 500);
}