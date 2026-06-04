<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');
start_safe_session();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'me':
        handleMe();
        break;
    case 'logout':
        handleLogout();
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

function handleLogin()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $identifier = trim($input['identifier'] ?? '');
    $password = $input['password'] ?? '';

    if ($identifier === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Identifier and password are required'], 400);
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    try {
        $pdo = db();

        $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        $stmt->execute([$ipAddress]);

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as attempt_count FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmt->execute([$ipAddress]);
        $attempts = $stmt->fetch();

        if ((int) $attempts['attempt_count'] > 5) {
            jsonResponse(['success' => false, 'message' => 'Too many login attempts. Please try again in 15 minutes.'], 429);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            jsonResponse(['success' => false, 'message' => 'Invalid username or password'], 401);
        }

        login_user($user['id'], $user['username']);

        $today = (new DateTime())->format('Y-m-d');
        $lastLogin = $user['last_login_date'];

        if ($lastLogin === null) {
            $newStreak = 1;
        } elseif ($lastLogin === $today) {
            $newStreak = $user['streak_days'];
        } else {
            $lastDate = new DateTime($lastLogin);
            $now = new DateTime($today);
            $diff = $lastDate->diff($now)->days;
            if ($diff === 1) {
                $newStreak = $user['streak_days'] + 1;
            } else {
                $newStreak = 1;
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET streak_days = ?, last_login_date = ? WHERE id = ?");
        $stmt->execute([$newStreak, $today, $user['id']]);

        $streakAchievements = ['streak_3' => 3, 'streak_7' => 7];
        foreach ($streakAchievements as $achKey => $requiredDays) {
            if ($newStreak >= $requiredDays) {
                $stmt = $pdo->prepare(
                    "INSERT IGNORE INTO user_achievements (user_id, achievement_key) VALUES (?, ?)"
                );
                $stmt->execute([$user['id'], $achKey]);
            }
        }

        $level = $user['level'] ?? 1;

        jsonResponse([
            'success' => true,
            'redirect' => '/pixelforge/',
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'balance' => (int) $user['balance'],
                'level' => (int) $level,
                'avatar_color' => $user['avatar_color']
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Login failed'], 500);
    }
}

function handleRegister()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'POST request required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
    }

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        jsonResponse(['success' => false, 'message' => 'Username, email, and password are required'], 400);
    }

    if (strlen($username) < 3 || strlen($username) > 30) {
        jsonResponse(['success' => false, 'message' => 'Username must be between 3 and 30 characters'], 400);
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        jsonResponse(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'message' => 'Invalid email format'], 400);
    }

    if (strlen($password) < 8) {
        jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters'], 400);
    }

    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        jsonResponse(['success' => false, 'message' => 'Password must contain at least one letter and one number'], 400);
    }

    $avatarColors = ['#E74C3C', '#3498DB', '#2ECC71', '#F39C12', '#9B59B6', '#1ABC9C', '#E67E22', '#34495E', '#E91E63', '#00BCD4'];
    $avatarColor = $avatarColors[array_rand($avatarColors)];

    try {
        $pdo = db();

        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Username is already taken'], 409);
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            jsonResponse(['success' => false, 'message' => 'Email is already registered'], 409);
        }

        $hashedPassword = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "INSERT INTO users (username, email, password, balance, avatar_color) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $email, $hashedPassword, STARTING_BALANCE, $avatarColor]);
        $userId = $pdo->lastInsertId();

        login_user($userId, $username);

        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO user_achievements (user_id, achievement_key) VALUES (?, 'first_match')"
        );
        $stmt->execute([$userId]);

        jsonResponse([
            'success' => true,
            'redirect' => '/pixelforge/',
            'user' => [
                'id' => (int) $userId,
                'username' => $username,
                'balance' => STARTING_BALANCE,
                'level' => 1
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Registration failed'], 500);
    }
}

function handleMe()
{
    $userId = requireAuth();

    try {
        $stmt = db()->prepare(
            "SELECT id, username, email, balance, xp, level, avatar_color, total_pixels_placed, total_games_played, created_at FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            jsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        jsonResponse([
            'success' => true,
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'balance' => (int) $user['balance'],
                'xp' => (int) $user['xp'],
                'level' => (int) $user['level'],
                'avatar_color' => $user['avatar_color'],
                'total_pixels_placed' => (int) $user['total_pixels_placed'],
                'total_games_played' => (int) $user['total_games_played'],
                'created_at' => $user['created_at']
            ]
        ]);
    } catch (PDOException $e) {
        jsonResponse(['success' => false, 'message' => 'Failed to load user data'], 500);
    }
}

function handleLogout()
{
    session_destroy();
    $_SESSION = [];

    jsonResponse([
        'success' => true,
        'redirect' => '/'
    ]);
}
