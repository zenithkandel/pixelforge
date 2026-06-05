<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');
start_safe_session();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'start_game':
        handleStartGame();
        break;
    case 'submit_score':
        handleSubmitScore();
        break;
    case 'get_boosters':
        handleGetBoosters();
        break;
    case 'buy_booster':
        handleBuyBooster();
        break;
    case 'use_booster':
        handleUseBooster();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function requireAuth()
{
    if (!is_logged_in()) {
        jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
    }
    return current_user_id();
}

function requireCsrf()
{
    if (!csrf_header_verify()) {
        jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }
}

function generateBoard()
{
    $board = [];
    for ($r = 0; $r < 8; $r++) {
        $board[$r] = [];
        for ($c = 0; $c < 8; $c++) {
            do {
                $type = rand(0, 5);
            } while (wouldMatch($board, $r, $c, $type));
            $board[$r][$c] = ['type' => $type, 'special' => null];
        }
    }
    return $board;
}

function wouldMatch($board, $r, $c, $type)
{
    if ($c >= 2 && isset($board[$r][$c - 1], $board[$r][$c - 2])) {
        if ($board[$r][$c - 1]['type'] === $type && $board[$r][$c - 2]['type'] === $type) {
            return true;
        }
    }
    if ($r >= 2 && isset($board[$r - 1][$c], $board[$r - 2][$c])) {
        if ($board[$r - 1][$c]['type'] === $type && $board[$r - 2][$c]['type'] === $type) {
            return true;
        }
    }
    return false;
}

function handleStartGame()
{
    $userId = requireAuth();

    try {
        $pdo = db();

        $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'abandoned' WHERE user_id = ? AND status = 'active'");
        $stmt->execute([$userId]);

        $board = generateBoard();
        $boardJson = json_encode($board);

        $stmt = $pdo->prepare("INSERT INTO game_sessions (user_id, board_state, moves_left, score, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$userId, $boardJson, MAX_MOVES, 0]);
        $sessionId = $pdo->lastInsertId();

        jsonResponse([
            'success' => true,
            'session_id' => (int) $sessionId,
            'board' => $board,
            'moves_left' => MAX_MOVES,
            'score' => 0
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to start game'], 500);
    }
}

function handleSubmitScore()
{
    $userId = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $sessionId = $input['session_id'] ?? null;
    $score = $input['score'] ?? null;
    $maxCombo = $input['max_combo'] ?? 0;
    $stats = $input['stats'] ?? [];

    if ($sessionId === null || $score === null) {
        jsonResponse(['success' => false, 'message' => 'session_id and score are required'], 400);
    }

    $sessionId = (int) $sessionId;
    $score = (int) $score;
    $maxCombo = (int) $maxCombo;

    if ($score < 0 || $score > 50000) {
        jsonResponse(['success' => false, 'message' => 'Score must be between 0 and 50000'], 400);
    }

    try {
        $pdo = db();

        $stmt = $pdo->prepare("SELECT id, user_id, status FROM game_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            jsonResponse(['success' => false, 'message' => 'Game session not found'], 404);
        }
        if ($session['user_id'] != $userId) {
            jsonResponse(['success' => false, 'message' => 'Not your game session'], 403);
        }
        if ($session['status'] !== 'active') {
            jsonResponse(['success' => false, 'message' => 'Game session already completed'], 400);
        }

        $stmt = $pdo->prepare("SELECT started_at FROM game_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $game = $stmt->fetch();
        $elapsed = time() - strtotime($game['started_at']);
        $isQuit = ($input['penalized'] ?? false) || $score === 0;
        if (!$isQuit && $elapsed < 5) {
            jsonResponse(['success' => false, 'message' => 'Please wait before submitting another score'], 429);
        }

        $currencyEarned = max(1, min(50, (int) floor($score / 100)));
        $xpEarned = min(500, (int) floor($score / 20));

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'completed', score = ?, combo_max = ?, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$score, $maxCombo, $sessionId]);

        $stmt = $pdo->prepare("SELECT balance, xp, total_games_played, total_score FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        $newBalance = $user['balance'] + $currencyEarned;
        $newXp = $user['xp'] + $xpEarned;
        $newLevel = (int) floor(1 + sqrt($newXp / 50));
        $newGamesPlayed = $user['total_games_played'] + 1;
        $newTotalScore = $user['total_score'] + $score;

        $stmt = $pdo->prepare("UPDATE users SET balance = ?, xp = ?, level = ?, total_games_played = ?, total_score = ? WHERE id = ?");
        $stmt->execute([$newBalance, $newXp, $newLevel, $newGamesPlayed, $newTotalScore, $userId]);

        $stmt = $pdo->prepare("INSERT INTO score_log (user_id, game_session_id, score, combo_max, currency_earned, xp_earned) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $sessionId, $score, $maxCombo, $currencyEarned, $xpEarned]);

        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'earn', ?)");
        $stmt->execute([$userId, $currencyEarned, "Game score reward (session #{$sessionId})"]);

        $pdo->commit();

        jsonResponse([
            'success' => true,
            'currency_earned' => $currencyEarned,
            'xp_earned' => $xpEarned,
            'new_balance' => $newBalance,
            'new_xp' => $newXp,
            'new_level' => $newLevel
        ]);
    } catch (PDOException $e) {
        try { db()->rollBack(); } catch (\Exception $ignore) {}
        jsonResponse(['success' => false, 'message' => 'Failed to submit score'], 500);
    }
}

function getUserBoosters($userId)
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT booster_type, quantity FROM user_boosters WHERE user_id = ?");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $boosters = [
        'hint' => 0, 'hammer' => 0, 'shuffle' => 0,
        'extraMoves' => 0, 'colorBurst' => 0, 'lightning' => 0
    ];
    foreach ($rows as $row) {
        $boosters[$row['booster_type']] = (int) $row['quantity'];
    }
    return $boosters;
}

function handleGetBoosters()
{
    $userId = requireAuth();

    try {
        $boosters = getUserBoosters($userId);
        jsonResponse(['success' => true, 'boosters' => $boosters]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load boosters'], 500);
    }
}

function handleBuyBooster()
{
    $userId = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $boosterType = $input['booster_type'] ?? null;

    $validBoosters = [
        'hint' => 10,
        'hammer' => 25,
        'shuffle' => 40,
        'extraMoves' => 60,
        'colorBurst' => 100,
        'lightning' => 150
    ];

    if (!$boosterType || !isset($validBoosters[$boosterType])) {
        jsonResponse(['success' => false, 'message' => 'Invalid booster type'], 400);
    }

    $cost = $validBoosters[$boosterType];

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        if ($user['balance'] < $cost) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'Insufficient balance'], 400);
        }

        $newBalance = $user['balance'] - $cost;

        $stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
        $stmt->execute([$newBalance, $userId]);

        $stmt = $pdo->prepare("INSERT INTO user_boosters (user_id, booster_type, quantity) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE quantity = quantity + 1");
        $stmt->execute([$userId, $boosterType]);

        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'spend', ?)");
        $stmt->execute([$userId, $cost, "Purchased {$boosterType} booster"]);

        $pdo->commit();

        $boosters = getUserBoosters($userId);
        jsonResponse([
            'success' => true,
            'new_balance' => $newBalance,
            'boosters' => $boosters
        ]);
    } catch (PDOException $e) {
        try { db()->rollBack(); } catch (\Exception $ignore) {}
        jsonResponse(['success' => false, 'message' => 'Failed to buy booster'], 500);
    }
}

function handleUseBooster()
{
    $userId = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $boosterType = $input['booster_type'] ?? null;

    $validBoosters = ['hint', 'hammer', 'shuffle', 'extraMoves', 'colorBurst', 'lightning'];

    if (!$boosterType || !in_array($boosterType, $validBoosters)) {
        jsonResponse(['success' => false, 'message' => 'Invalid booster type'], 400);
    }

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT quantity FROM user_boosters WHERE user_id = ? AND booster_type = ? FOR UPDATE");
        $stmt->execute([$userId, $boosterType]);
        $row = $stmt->fetch();

        if (!$row || $row['quantity'] <= 0) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'message' => 'No boosters of this type available'], 400);
        }

        $stmt = $pdo->prepare("UPDATE user_boosters SET quantity = quantity - 1 WHERE user_id = ? AND booster_type = ?");
        $stmt->execute([$userId, $boosterType]);

        $pdo->commit();

        $boosters = getUserBoosters($userId);
        jsonResponse(['success' => true, 'boosters' => $boosters]);
    } catch (PDOException $e) {
        try { db()->rollBack(); } catch (\Exception $ignore) {}
        jsonResponse(['success' => false, 'message' => 'Failed to use booster'], 500);
    }
}
