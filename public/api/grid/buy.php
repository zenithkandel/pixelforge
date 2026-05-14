<?php
require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';

require_verified();
require_csrf();

$user = get_current_user_data();
check_rate_limit('buy', $user['id'], 10, 60);

$json = json_decode(file_get_contents('php://input'), true);
$x = isset($json['x']) ? (int)$json['x'] : -1;
$y = isset($json['y']) ? (int)$json['y'] : -1;
$color = $json['color'] ?? '';

if (!validate_coord($x, $y)) {
    respond_error('invalid_coord', 'Invalid coordinates.');
}
if (!validate_color($color)) {
    respond_error('invalid_color', 'Invalid color hex.');
}

if ($user['pxl_balance'] < GRID_PIXEL_COST) {
    respond_error('insufficient_pxl', 'Not enough PXL.');
}

$redis = RedisClient::getInstance();
$lock_key = "pixel_lock:$x:$y";
if (!$redis->setnx($lock_key, 1)) {
    respond_error('concurrent_conflict', 'Pixel is being modified by someone else.', 409);
}
$redis->expire($lock_key, 5);

$db = DB::getInstance();

try {
    $db->beginTransaction();
    
    // Check if pixel is the same color already
    $stmt = $db->prepare("SELECT color FROM pixels WHERE x = ? AND y = ? FOR UPDATE");
    $stmt->execute([$x, $y]);
    $current = $stmt->fetch();
    
    if ($current && $current['color'] === $color) {
        $db->rollBack();
        $redis->del($lock_key);
        respond_error('already_color', 'Pixel is already this color.');
    }
    
    // Get current grid session
    $stmt = $db->prepare("SELECT id FROM grid_sessions WHERE is_current = 1");
    $stmt->execute();
    $grid_session_id = $stmt->fetchColumn();
    
    if (!$grid_session_id) {
        throw new Exception("No active grid session");
    }
    
    // Deduct PXL
    $new_balance = pxl_debit($user['id'], GRID_PIXEL_COST, 'pixel_spend', "pixel_{$x}_{$y}", "Painted pixel at $x, $y");
    if ($new_balance === false) {
        $db->rollBack();
        $redis->del($lock_key);
        respond_error('insufficient_pxl', 'Not enough PXL.');
    }
    
    // Insert/Update Pixel
    $stmt = $db->prepare("INSERT INTO pixels (x, y, color, owner_id, grid_session_id, purchased_at) VALUES (?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE color = VALUES(color), owner_id = VALUES(owner_id), purchased_at = NOW()");
    $stmt->execute([$x, $y, $color, $user['id'], $grid_session_id]);
    
    // Insert History
    $stmt = $db->prepare("INSERT INTO pixel_history (user_id, x, y, color, pxl_cost, grid_session_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user['id'], $x, $y, $color, GRID_PIXEL_COST, $grid_session_id]);
    
    // Update chunk version
    $cx = floor($x / 64);
    $cy = floor($y / 64);
    $stmt = $db->prepare("UPDATE chunks SET version = version + 1 WHERE chunk_x = ? AND chunk_y = ?");
    $stmt->execute([$cx, $cy]);
    
    $stmt = $db->prepare("SELECT version FROM chunks WHERE chunk_x = ? AND chunk_y = ?");
    $stmt->execute([$cx, $cy]);
    $new_version = $stmt->fetchColumn();
    
    $db->commit();
    
    $redis->del("chunk:$cx:$cy");
    $redis->setex("chunk_v:$cx:$cy", 300, $new_version);
    
    $event = json_encode([
        'type' => 'pixel',
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'username' => $user['username'],
        'cx' => $cx,
        'cy' => $cy,
        'chunk_version' => $new_version
    ]);
    
    $redis->publish('sse_channel', $event);
    $redis->del($lock_key);
    
    // Achievements
    $stmt = $db->prepare("SELECT COUNT(*) FROM pixel_history WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $pixels_painted = $stmt->fetchColumn();
    
    if ($pixels_painted == 1) check_and_grant_achievement($user['id'], 'first_pixel');
    if ($pixels_painted == 50) check_and_grant_achievement($user['id'], 'pixels_50');
    if ($pixels_painted == 250) check_and_grant_achievement($user['id'], 'pixels_250');
    if ($pixels_painted == 1000) check_and_grant_achievement($user['id'], 'pixels_1000');
    
    respond_success([
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'new_balance' => $new_balance,
        'chunk_version' => $new_version
    ]);
    
} catch (Exception $e) {
    $db->rollBack();
    $redis->del($lock_key);
    error_log("Pixel Buy Error: " . $e->getMessage());
    respond_error('server_error', 'An error occurred while processing your purchase.', 500);
}
