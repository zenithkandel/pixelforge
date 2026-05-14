<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

$x = intval($_GET['x'] ?? -1);
$y = intval($_GET['y'] ?? -1);

if (!validate_coordinate($x) || !validate_coordinate($y)) {
    respond_error('invalid_coordinates', 'Invalid pixel coordinates');
}

// Rate limit
$ip = get_client_ip();
if (!RateLimit::check("pixel_info:$ip", 100, 60)) {
    respond_error('rate_limited', 'Rate limited', 429);
}

// Get current grid session
$grid_session = Database::fetch('SELECT id FROM grid_sessions WHERE is_current = 1');
if (!$grid_session) {
    respond_error('no_active_grid', 'No active grid session', 404);
}

try {
    $pixel = Database::fetch(
        'SELECT p.color, p.user_id, p.created_at, u.username FROM pixels p LEFT JOIN users u ON p.user_id = u.id WHERE p.grid_session_id = ? AND p.x = ? AND p.y = ?',
        [$grid_session['id'], $x, $y]
    );
    
    if (!$pixel) {
        respond_success(['color' => '#FFFFFF', 'owner' => null], 'Pixel is empty');
    }
    
    respond_success([
        'color' => $pixel['color'],
        'owner' => $pixel['username'],
        'created_at' => $pixel['created_at']
    ], 'Pixel info retrieved');

} catch (Exception $e) {
    log_error('Pixel info fetch failed', ['error' => $e->getMessage()]);
    respond_error('fetch_failed', 'Failed to fetch pixel info', 500);
}

?>
