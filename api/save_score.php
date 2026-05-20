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

$game_token = trim($_POST['game_token'] ?? '');
$score = (int)($_POST['score'] ?? 0);
$multiplier = (float)($_POST['multiplier'] ?? 1.0);
$coins = (int)($_POST['coins_collected'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

$db = get_db();

try {
    $stmt = $db->prepare('SELECT * FROM game_tokens WHERE token = ? AND user_id = ? AND used = 0');
    $stmt->execute([$game_token, $user_id]);
    $token = $stmt->fetch();

    if (!$token) {
        log_sec('GAME', 'Score rejected — invalid/used token', ['token' => substr($game_token, 0, 8)]);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid game session. Start a new game.']);
        exit();
    }

    $token_age = time() - strtotime($token['created_at']);
    if ($token_age > 600) {
        $db->prepare('UPDATE game_tokens SET used = 1 WHERE id = ?')->execute([$token['id']]);
        log_warn('GAME', 'Score rejected — token expired');
        http_response_code(400);
        echo json_encode(['error' => 'Game session expired. Start a new game.']);
        exit();
    }

    if ($score < 0 || $score > 500) {
        log_sec('GAME', 'Score rejected — cap exceeded', ['submitted' => $score]);
        http_response_code(400);
        echo json_encode(['error' => 'Score rejected.']);
        exit();
    }

    if ($coins < 0 || $coins > 50) {
        log_sec('GAME', 'Score rejected — invalid coins', ['coins' => $coins]);
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coins count.']);
        exit();
    }

    $expected_multiplier = 1.0;
    if ($score >= 10) $expected_multiplier = 1.5;
    if ($score >= 20) $expected_multiplier = 2.0;
    if ($score >= 30) $expected_multiplier = 2.5;
    if ($score >= 40) $expected_multiplier = 3.0;

    if (abs($multiplier - $expected_multiplier) > 0.01) {
        $db->prepare('UPDATE game_tokens SET used = 1 WHERE id = ?')->execute([$token['id']]);
        log_sec('GAME', 'Score rejected — multiplier tamper', ['submitted' => $multiplier, 'expected' => $expected_multiplier]);
        http_response_code(400);
        echo json_encode(['error' => 'Score rejected.']);
        exit();
    }

    $multiplier = $expected_multiplier;

    log_info('GAME', 'Score submission received', ['score' => $score, 'multiplier' => $multiplier, 'coins' => $coins]);

    $currency_earned = (int)round($score * $multiplier * 2) + ($coins * 5);
    $xp_earned = $score + ($coins * 2);

    $db->beginTransaction();

    $db->prepare('UPDATE game_tokens SET used = 1 WHERE id = ?')->execute([$token['id']]);

    $stmt = $db->prepare('INSERT INTO score_log (user_id, score, multiplier, currency_earned, xp_earned) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$user_id, $score, $multiplier, $currency_earned, $xp_earned]);

    $stmt = $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
    $stmt->execute([$currency_earned, $user_id]);

    $xp_result = add_xp($db, $user_id, $xp_earned);

    $db->commit();

    $new_user = get_current_user();
    $achievements = check_achievements($db, $user_id);

    log_info('GAME', 'Score saved', [
        'score'    => $score,
        'currency' => $currency_earned,
        'xp'       => $xp_earned,
        'balance'  => $new_user ? (int)$new_user['balance'] : 0,
    ]);

    echo json_encode([
        'success'          => true,
        'currency_earned'  => $currency_earned,
        'xp_earned'        => $xp_earned,
        'new_balance'      => $new_user ? (int)$new_user['balance'] : 0,
        'new_xp'           => $xp_result['new_xp'] ?? 0,
        'new_level'        => $xp_result['new_level'] ?? 1,
        'leveled_up'       => $xp_result['leveled_up'] ?? false,
        'new_achievements' => $achievements,
    ]);
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    log_error('DB', 'Score save error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save score']);
}
