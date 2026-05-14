<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/auth.php';
require_method('POST');

$user = require_verified();

if (!check_rate_limit("buy:{$user['id']}", 10, 60)) {
    respond_error('rate_limited', 'Too many pixel purchases. Slow down.', 429);
}

$data = get_json_body();

if (!isset($data['csrf_token'], $data['x'], $data['y'], $data['color'])) {
    respond_error('missing_fields', 'x, y, color, and csrf_token required', 400);
}

require_csrf($data['csrf_token']);

if (!validate_coord($data['x']) || !validate_coord($data['y'])) {
    respond_error('invalid_coord', 'Coordinates must be 0-799', 400);
}

if (!validate_color($data['color'])) {
    respond_error('invalid_color', 'Color must be valid hex e.g. #FF3366', 400);
}

$x = (int)$data['x'];
$y = (int)$data['y'];
$color = strtoupper($data['color']);

$redis = get_redis();
$pdo = get_db();

$lock_key = "pixel_lock:{$x}:{$y}";
$lock_token = bin2hex(random_bytes(16));

if ($redis) {
    $locked = $redis->set($lock_key, $lock_token, ['NX', 'PX' => 5000]);
    if (!$locked) {
        respond_error('concurrent_conflict', 'That pixel was just bought! Try another.', 409, ['retry_after' => 1]);
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user['id']]);
    $user_data = $stmt->fetch();

    if (!$user_data || $user_data['pxl_balance'] < 1) {
        $pdo->rollBack();
        respond_error('insufficient_pxl', 'Not enough PXL! Play the game to earn more.', 400);
    }

    $stmt = $pdo->prepare("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1 FOR UPDATE");
    $stmt->execute();
    $grid_session = $stmt->fetch();

    if (!$grid_session) {
        $pdo->rollBack();
        respond_error('no_active_grid', 'No active grid session', 500);
    }

    $new_balance = $user_data['pxl_balance'] - 1;

    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = ?, total_pxl_spent = total_pxl_spent + 1 WHERE id = ?");
    $stmt->execute([$new_balance, $user['id']]);

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?, -1, 'pixel_spend', ?, ?, ?)");
    $stmt->execute([$user['id'], "{$x},{$y}", $new_balance, "Pixel ({$x},{$y}) set to {$color}"]);

    $stmt = $pdo->prepare("INSERT INTO pixels (x, y, color, owner_id, grid_session_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE color=VALUES(color), owner_id=VALUES(owner_id), purchased_at=NOW()");
    $stmt->execute([$x, $y, $color, $user['id'], $grid_session['id']]);

    $stmt = $pdo->prepare("INSERT INTO pixel_history (user_id, x, y, color, pxl_cost, grid_session_id) VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->execute([$user['id'], $x, $y, $color, $grid_session['id']]);

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
        'chunk_version' => $new_version,
    ]));

    check_and_grant_achievements($pdo, $user['id'], 'pixel_buy');

    respond_success([
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'new_balance' => $new_balance,
        'chunk_version' => $new_version,
    ]);
} catch (Throwable $e) {
    $pdo->rollBack();
    log_error($e);
    respond_error('server_error', 'An error occurred while purchasing the pixel', 500);
} finally {
    $current = $redis->get($lock_key);
    if ($current === $lock_token) {
        $redis->del($lock_key);
    }
}