<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/');
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $errors[] = 'All fields are required.';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $errors[] = 'Username must be 3–30 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one letter and one number.';
    } elseif ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    } else {
        $db = get_db();

        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            $stmt->execute([$_SERVER['REMOTE_ADDR']]);
            $reg_attempts = (int)$stmt->fetchColumn();

            if ($reg_attempts >= 3) {
                log_sec('AUTH', 'Registration rate limit — IP blocked', ['ip' => $_SERVER['REMOTE_ADDR']]);
                $errors[] = 'Too many registration attempts. Please try again later.';
            } else {
                $stmt = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$username, $email]);

                if ($stmt->fetch()) {
                    log_warn('AUTH', 'Registration failed — already exists', ['username' => $username]);
                    $errors[] = 'Username or email already in use.';
                } else {
                    $palette = ['#7c3aed','#db2777','#0891b2','#059669','#d97706','#dc2626','#4f46e5','#0d9488','#65a30d'];
                    $avatar = $palette[array_rand($palette)];

                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, avatar_color) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$username, $email, $hash, $avatar]);

                    $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$_SERVER['REMOTE_ADDR']]);
                    log_info('AUTH', 'New user registered', ['username' => $username, 'email' => $email]);

                    $_SESSION['user_id'] = (int)$db->lastInsertId();
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';

                    header('Location: ' . BASE_URL . '/');
                    exit();
                }
            }
        } catch (PDOException $e) {
            log_error('DB', 'Database error during registration: ' . $e->getMessage(), ['code' => $e->getCode()]);
            $errors[] = 'An error occurred. Please try again.';
        }
    }
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join — PixelForge</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: radial-gradient(circle at center, #1a1a2e 0%, #0a0a0c 100%); display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
        .auth-card { background: #11111a; width: 100%; max-width: 450px; padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 20px 50px rgba(0,0,0,0.5); position: relative; overflow: hidden; }
        .auth-card::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(to right, #7c3aed, #fbbf24); }
        .auth-logo { font-family: var(--font-game); font-size: 32px; color: white; text-align: center; margin-bottom: 30px; letter-spacing: -1px; }
        .auth-logo span { color: #7c3aed; }
        .auth-btn { width: 100%; padding: 15px; background: #7c3aed; border: none; border-radius: 10px; color: white; font-weight: 900; font-size: 16px; cursor: pointer; transition: all 0.2s; margin-top: 10px; font-family: var(--font-game); letter-spacing: 1px; }
        .auth-btn:hover { background: #8b5cf6; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(124,58,237,0.4); }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; color: rgba(255,255,255,0.4); font-size: 11px; font-weight: bold; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        .input-group input { width: 100%; padding: 12px 15px; background: #0a0a0f; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-size: 15px; outline: none; transition: border-color 0.2s; box-sizing: border-box; }
        .input-group input:focus { border-color: #7c3aed; }
        .alert { padding: 12px 15px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 500; background: rgba(220,38,38,0.1); border: 1px solid #dc2626; color: #f87171; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="auth-logo">PIXEL<span>FORGE</span></div>

    <?php if (!empty($errors)): ?>
        <div class="alert">
            <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>
            <?= htmlspecialchars($errors[0]) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required autofocus placeholder="Choose a unique name">
        </div>

        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required placeholder="you@example.com">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <div class="input-group">
                <label>Confirm</label>
                <input type="password" name="confirm_password" required placeholder="••••••••">
            </div>
        </div>

        <button type="submit" class="auth-btn">CREATE YOUR ACCOUNT</button>
    </form>

    <div style="text-align:center; margin-top:30px; font-size:14px; color:rgba(255,255,255,0.3);">
        Already a builder? 
        <a href="<?= BASE_URL ?>/login.php" style="color: #a78bfa; text-decoration:none; font-weight:bold;">Sign In</a>
    </div>
</div>

</body>
</html>
<?php exit(); ?>

