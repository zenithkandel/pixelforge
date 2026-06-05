<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
start_safe_session();

if (!is_logged_in()) {
    jsonResponse(['success' => false, 'message' => 'Login required'], 401);
}

$userId = current_user_id();
$user = current_user();
if (!$user || ($user['role'] ?? '') !== 'admin') {
    jsonResponse(['success' => false, 'message' => 'Admin access required'], 403);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'dashboard':
        handleDashboard();
        break;
    case 'users':
        handleUsers();
        break;
    case 'user_update':
        handleUserUpdate();
        break;
    case 'user_delete':
        handleUserDelete();
        break;
    case 'pixels':
        handlePixels();
        break;
    case 'sessions':
        handleSessions();
        break;
    case 'transactions':
        handleTransactions();
        break;
    case 'achievements':
        handleAchievements();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function handleDashboard() {
    $pdo = db();

    $stats = [];
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_pixels'] = $pdo->query("SELECT COUNT(*) FROM pixels")->fetchColumn();
    $stats['total_games'] = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE status='completed'")->fetchColumn();
    $stats['total_score'] = $pdo->query("SELECT COALESCE(SUM(total_score),0) FROM users")->fetchColumn();
    $stats['total_balance'] = $pdo->query("SELECT COALESCE(SUM(balance),0) FROM users")->fetchColumn();

    $stats['users_today'] = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats['games_today'] = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE DATE(completed_at) = CURDATE() AND status='completed'")->fetchColumn();
    $stats['pixels_today'] = $pdo->query("SELECT COUNT(*) FROM pixels WHERE DATE(placed_at) = CURDATE()")->fetchColumn();

    $topPlayers = $pdo->query("SELECT id, username, avatar_color, total_score, balance, level FROM users ORDER BY total_score DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    $recentPixels = $pdo->query("SELECT p.x, p.y, p.color, p.placed_at, u.username FROM pixels p LEFT JOIN users u ON p.owner_id = u.id ORDER BY p.placed_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'stats' => $stats,
        'top_players' => $topPlayers,
        'recent_pixels' => $recentPixels
    ]);
}

function handleUsers() {
    $pdo = db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';

    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE username LIKE ? OR email LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $total = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $total->execute($params);
    $total = $total->fetchColumn();

    $stmt = $pdo->prepare("SELECT id, username, email, role, balance, xp, level, avatar_color, total_pixels_placed, total_games_played, total_score, created_at, last_login_date FROM users $where ORDER BY id ASC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'users' => $users,
        'total' => (int)$total,
        'page' => $page,
        'pages' => max(1, ceil($total / $limit))
    ]);
}

function handleUserUpdate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = (int)($input['id'] ?? 0);
    $username = $input['username'] ?? null;
    $email = $input['email'] ?? null;
    $role = $input['role'] ?? null;
    $balance = $input['balance'] ?? null;
    $xp = $input['xp'] ?? null;
    $level = $input['level'] ?? null;
    $avatarColor = $input['avatar_color'] ?? null;
    $streakDays = $input['streak_days'] ?? null;

    if (!$targetId) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
    }

    $pdo = db();
    $fields = [];
    $params = [];

    if ($username !== null && is_string($username) && trim($username) !== '') {
        $fields[] = "username = ?";
        $params[] = trim($username);
    }
    if ($email !== null && is_string($email) && trim($email) !== '') {
        $fields[] = "email = ?";
        $params[] = trim($email);
    }
    if ($role !== null && in_array($role, ['user', 'admin'])) {
        $fields[] = "role = ?";
        $params[] = $role;
    }
    if ($balance !== null) {
        $fields[] = "balance = ?";
        $params[] = max(0, (int)$balance);
    }
    if ($xp !== null) {
        $fields[] = "xp = ?";
        $params[] = max(0, (int)$xp);
    }
    if ($level !== null) {
        $fields[] = "level = ?";
        $params[] = max(1, (int)$level);
    }
    if ($avatarColor !== null && is_string($avatarColor)) {
        $fields[] = "avatar_color = ?";
        $params[] = $avatarColor;
    }
    if ($streakDays !== null) {
        $fields[] = "streak_days = ?";
        $params[] = max(0, (int)$streakDays);
    }

    if (empty($fields)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $targetId;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);

    jsonResponse(['success' => true, 'message' => 'User updated']);
}

function handleUserDelete() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $targetId = (int)($input['id'] ?? 0);

    if (!$targetId) {
        jsonResponse(['success' => false, 'message' => 'Invalid user ID'], 400);
    }

    if ($targetId == current_user_id()) {
        jsonResponse(['success' => false, 'message' => 'Cannot delete yourself'], 400);
    }

    $pdo = db();
    $pdo->beginTransaction();

    $pdo->prepare("DELETE FROM user_achievements WHERE user_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM transactions WHERE user_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM score_log WHERE user_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM game_sessions WHERE user_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM pixels WHERE owner_id = ?")->execute([$targetId]);
    $pdo->prepare("DELETE FROM login_attempts WHERE ip_address IN (SELECT ip_address FROM login_attempts WHERE user_id = ?)")->execute([$targetId]);
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);

    $pdo->commit();

    jsonResponse(['success' => true, 'message' => 'User deleted']);
}

function handlePixels() {
    $pdo = db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $total = $pdo->query("SELECT COUNT(*) FROM pixels")->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.x, p.y, p.color, p.placed_at, u.username FROM pixels p LEFT JOIN users u ON p.owner_id = u.id ORDER BY p.placed_at DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $pixels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'pixels' => $pixels,
        'total' => (int)$total,
        'page' => $page,
        'pages' => max(1, ceil($total / $limit))
    ]);
}

function handleSessions() {
    $pdo = db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 30;
    $offset = ($page - 1) * $limit;

    $total = $pdo->query("SELECT COUNT(*) FROM game_sessions")->fetchColumn();

    $stmt = $pdo->prepare("SELECT gs.id, gs.user_id, u.username, gs.score, gs.combo_max, gs.moves_left, gs.status, gs.started_at, gs.completed_at FROM game_sessions gs LEFT JOIN users u ON gs.user_id = u.id ORDER BY gs.id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'sessions' => $sessions,
        'total' => (int)$total,
        'page' => $page,
        'pages' => max(1, ceil($total / $limit))
    ]);
}

function handleTransactions() {
    $pdo = db();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 50;
    $offset = ($page - 1) * $limit;

    $total = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();

    $stmt = $pdo->prepare("SELECT t.id, t.user_id, u.username, t.amount, t.type, t.description, t.created_at FROM transactions t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'transactions' => $transactions,
        'total' => (int)$total,
        'page' => $page,
        'pages' => max(1, ceil($total / $limit))
    ]);
}

function handleAchievements() {
    $pdo = db();

    $stmt = $pdo->query("SELECT a.id, a.slug, a.name, a.description, a.icon, a.reward, COUNT(ua.user_id) as earned_count FROM achievements a LEFT JOIN user_achievements ua ON a.id = ua.achievement_id GROUP BY a.id ORDER BY a.id");
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'achievements' => $achievements
    ]);
}
