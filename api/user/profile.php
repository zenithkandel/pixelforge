<?php

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_error('method_not_allowed', 'Only GET requests are allowed', 405);
}

$username = sanitize_string($_GET['username'] ?? '');

if (empty($username)) {
    respond_error('missing_username', 'Username is required');
}

// Rate limit profile requests
$ip = get_client_ip();
if (!RateLimit::check("profile:$ip", 100, 60)) {
    respond_error('rate_limited', 'Rate limited', 429);
}

try {
    $user = Database::fetch(
        'SELECT id, username, created_at, login_streak FROM users WHERE username = ? AND is_banned = 0',
        [$username]
    );
    
    if (!$user) {
        respond_error('user_not_found', 'User not found', 404);
    }
    
    // Get stats
    $total_pxl = Database::fetch(
        'SELECT COALESCE(SUM(amount), 0) as total FROM pxl_transactions WHERE user_id = ? AND type = "game_earn"',
        [$user['id']]
    );
    
    $total_pixels = Database::fetch(
        'SELECT COUNT(*) as count FROM pixel_history WHERE user_id = ?',
        [$user['id']]
    );
    
    $best_score = Database::fetch(
        'SELECT MAX(score) as best FROM scores WHERE user_id = ?',
        [$user['id']]
    );
    
    $games_played = Database::fetch(
        'SELECT COUNT(*) as count FROM scores WHERE user_id = ?',
        [$user['id']]
    );
    
    respond_success([
        'username' => $user['username'],
        'created_at' => $user['created_at'],
        'login_streak' => $user['login_streak'],
        'total_pxl_earned' => (int)$total_pxl['total'],
        'total_pixels' => (int)$total_pixels['count'],
        'best_score' => (int)($best_score['best'] ?? 0),
        'games_played' => (int)$games_played['count']
    ]);

} catch (Exception $e) {
    log_error('Profile fetch failed', ['error' => $e->getMessage()]);
    respond_error('fetch_failed', 'Failed to fetch profile', 500);
}

?>
