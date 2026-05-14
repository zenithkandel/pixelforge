<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_error('method_not_allowed', 'Only POST requests are allowed', 405);
}

require_verified();

$user_id = get_current_user_id();
$data = get_request_json();

$x = intval($data['x'] ?? -1);
$y = intval($data['y'] ?? -1);
$color = sanitize_string($data['color'] ?? '');

// Validate inputs
if (!validate_coordinate($x) || !validate_coordinate($y)) {
    respond_error('invalid_coordinates', 'Invalid pixel coordinates');
}

if (!validate_color($color)) {
    respond_error('invalid_color', 'Invalid color format');
}

// Rate limit pixel purchases
if (!RateLimit::check("buy:$user_id", 10, 60)) {
    respond_error('rate_limited', 'You are buying pixels too fast', 429);
}

// Get current grid session
$grid_session = Database::fetch('SELECT id FROM grid_sessions WHERE is_current = 1');
if (!$grid_session) {
    respond_error('no_active_grid', 'No active grid session', 404);
}

try {
    Database::beginTransaction();

    // Check if pixel already exists
    $existing_pixel = Database::fetch(
        'SELECT id, user_id FROM pixels WHERE grid_session_id = ? AND x = ? AND y = ?',
        [$grid_session['id'], $x, $y]
    );

    if ($existing_pixel) {
        // Pixel was just purchased by someone else
        Database::rollback();
        respond_error('pixel_taken', 'That pixel was just purchased!', 409);
    }

    // Check user balance
    $user = Database::fetch('SELECT pxl_balance FROM users WHERE id = ?', [$user_id]);
    if (!$user || $user['pxl_balance'] < GRID_PIXEL_COST) {
        Database::rollback();
        respond_error('insufficient_pxl', 'Insufficient PXL balance', 402);
    }

    // Debit PXL
    if (!debit_pxl($user_id, GRID_PIXEL_COST, 'pixel_spend', null, "Painted pixel at ($x, $y)")) {
        Database::rollback();
        respond_error('transaction_failed', 'Failed to process transaction', 500);
    }

    // Insert pixel
    Database::execute(
        'INSERT INTO pixels (grid_session_id, x, y, color, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
        [$grid_session['id'], $x, $y, $color, $user_id]
    );

    // Record in history
    Database::execute(
        'INSERT INTO pixel_history (grid_session_id, user_id, x, y, color, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
        [$grid_session['id'], $user_id, $x, $y, $color]
    );

    // Check achievements
    $total_pixels = Database::fetch(
        'SELECT COUNT(*) as count FROM pixel_history WHERE user_id = ? AND grid_session_id = ?',
        [$user_id, $grid_session['id']]
    );

    check_and_grant_achievements($user_id, 'pixel_buy', ['total_pixels' => $total_pixels['count']]);

    // Increment chunk version
    $cx = intdiv($x, GRID_CHUNK_SIZE);
    $cy = intdiv($y, GRID_CHUNK_SIZE);

    $chunk = Database::fetch(
        'SELECT id, version FROM chunks WHERE grid_session_id = ? AND chunk_x = ? AND chunk_y = ?',
        [$grid_session['id'], $cx, $cy]
    );

    if ($chunk) {
        Database::execute(
            'UPDATE chunks SET version = version + 1, updated_at = NOW() WHERE id = ?',
            [$chunk['id']]
        );
    } else {
        Database::execute(
            'INSERT INTO chunks (grid_session_id, chunk_x, chunk_y, version) VALUES (?, ?, ?, 1)',
            [$grid_session['id'], $cx, $cy]
        );
    }

    // Clear Redis cache for this chunk
    Redis::del("chunk:{$grid_session['id']}:{$cx}:{$cy}");
    Redis::del("chunk_v:{$grid_session['id']}:{$cx}:{$cy}");

    // Broadcast update via Redis pub/sub
    Redis::publish("grid_updates", json_encode([
        'type' => 'pixel_painted',
        'x' => $x,
        'y' => $y,
        'color' => $color,
        'user_id' => $user_id,
        'timestamp' => time()
    ]));

    Database::commit();

    // Get new balance
    $new_balance = get_pxl_balance($user_id);

    respond_success(
        ['new_balance' => $new_balance],
        'Pixel purchased successfully'
    );

} catch (Exception $e) {
    Database::rollback();
    log_error('Pixel purchase failed', ['error' => $e->getMessage(), 'user_id' => $user_id]);
    respond_error('purchase_failed', 'An error occurred during purchase', 500);
}

?>