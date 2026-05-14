<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_method('GET');

$user = require_auth();

try {
    $pdo = get_db();

    $stmt = $pdo->prepare("
        SELECT u.*,
            (SELECT COUNT(*) FROM pixel_history WHERE user_id = u.id) as pixels_painted,
            (SELECT COUNT(*) FROM game_sessions WHERE user_id = u.id AND ended_at IS NOT NULL) as games_played,
            (SELECT MAX(score) FROM scores WHERE user_id = u.id) as best_score
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user['id']]);
    $full_user = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT * FROM pxl_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user['id']]);
    $transactions = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT ph.* FROM pixel_history ph
        WHERE ph.user_id = ?
        ORDER BY ph.purchased_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user['id']]);
    $recent_pixels = $stmt->fetchAll();

    respond_success([
        'id' => $full_user['id'],
        'username' => $full_user['username'],
        'email' => $full_user['email'],
        'pxl_balance' => (int)$full_user['pxl_balance'],
        'total_pxl_earned' => (int)$full_user['total_pxl_earned'],
        'total_pxl_spent' => (int)$full_user['total_pxl_spent'],
        'login_streak' => (int)$full_user['login_streak'],
        'last_login_date' => $full_user['last_login_date'],
        'email_verified' => (bool)$full_user['email_verified'],
        'created_at' => $full_user['created_at'],
        'pixels_painted' => (int)$full_user['pixels_painted'],
        'games_played' => (int)$full_user['games_played'],
        'best_score' => (int)($full_user['best_score'] ?? 0),
        'transactions' => array_map(function($t) {
            return [
                'id' => (int)$t['id'],
                'amount' => (int)$t['amount'],
                'type' => $t['type'],
                'description' => $t['description'],
                'balance_after' => (int)$t['balance_after'],
                'created_at' => $t['created_at']
            ];
        }, $transactions),
        'recent_pixels' => array_map(function($p) {
            return [
                'x' => (int)$p['x'],
                'y' => (int)$p['y'],
                'color' => $p['color'],
                'purchased_at' => $p['purchased_at']
            ];
        }, $recent_pixels)
    ]);

} catch (Exception $e) {
    log_error('User me fetch failed', ['exception' => $e->getMessage()]);
    respond_error('server_error', 'Failed to fetch user data', 500);
}