<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

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
if (!csrf_verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF']);
    exit;
}
$score = (int) ($_POST['score'] ?? 0);
$currency = max(0, $score * 2);
Database::query('UPDATE users SET balance = balance + ? WHERE id = ?', [$currency, $_SESSION['user_id']]);
$user = get_logged_in_user();
echo json_encode(['success' => true, 'currency_earned' => $currency, 'new_balance' => $user['balance']]);
?>