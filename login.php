<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/');
    exit();
}

$errors = [];
$success = '';

if (!empty($_SESSION['streak_bonus'])) {
    $success = 'Daily streak bonus: +' . $_SESSION['streak_bonus'] . ' 💰';
    unset($_SESSION['streak_bonus']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $errors[] = 'All fields are required.';
    } else {
        $db = get_db();

        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
            $stmt->execute([$_SERVER['REMOTE_ADDR']]);
            $attempts = (int) $stmt->fetchColumn();

            if ($attempts >= 5) {
                log_sec('AUTH', 'Login rate limit — IP blocked', ['ip' => $_SERVER['REMOTE_ADDR'], 'attempts' => $attempts]);
                $errors[] = 'Too many login attempts. Please wait 15 minutes.';
            } else {
                $stmt = $db->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
                $stmt->execute([$login, $login]);
                $user = $stmt->fetch();

                if (!$user) {
                    $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$_SERVER['REMOTE_ADDR']]);
                    log_sec('AUTH', 'Failed login — user not found', ['input' => $login]);
                    $errors[] = 'Invalid login credentials.';
                } elseif (!password_verify($password, $user['password_hash'])) {
                    $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$_SERVER['REMOTE_ADDR']]);
                    log_sec('AUTH', 'Failed login — wrong password', ['input' => $login]);
                    $errors[] = 'Invalid login credentials.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    $today = date('Y-m-d');
                    $last = $user['last_login_date'];
                    $streak = (int) $user['streak_days'];
                    $bonus = 0;

                    if ($last !== $today) {
                        if ($last === date('Y-m-d', strtotime('-1 day'))) {
                            $streak++;
                        } else {
                            $streak = 1;
                        }

                        $streak_bonuses = [1 => 10, 2 => 20, 3 => 35, 5 => 60, 7 => 150, 14 => 400, 30 => 800];
                        foreach ($streak_bonuses as $day => $b) {
                            if ($streak >= $day)
                                $bonus = $b;
                        }

                        if ($bonus > 0) {
                            $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$bonus, (int) $user['id']]);
                            $_SESSION['streak_bonus'] = $bonus;
                            log_info('AUTH', 'Daily streak bonus awarded', ['streak' => $streak, 'bonus' => $bonus]);
                        }

                        $db->prepare('UPDATE users SET last_login_date = ?, streak_days = ? WHERE id = ?')->execute([$today, $streak, (int) $user['id']]);
                    }

                    log_info('AUTH', 'User logged in', ['username' => $user['username']]);
                    header('Location: ' . BASE_URL . '/');
                    exit();
                }
            }
        } catch (PDOException $e) {
            log_error('DB', 'Database error during login: ' . $e->getMessage(), ['code' => $e->getCode()]);
            $errors[] = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — PixelForge</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <style>
        body {
            background: var(--bg-base);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            font-family: var(--font);
        }

        .auth-card {
            background: var(--bg-panel);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            border: 1px solid var(--border-default);
            position: relative;
            overflow: hidden;
            border-top: 4px solid var(--accent);
        }

        .auth-logo {
            font-family: var(--font-game);
            font-size: 32px;
            color: white;
            text-align: center;
            margin-bottom: 30px;
            letter-spacing: -1px;
        }

        .auth-logo span {
            color: var(--accent);
        }

        .auth-btn {
            width: 100%;
            padding: 15px;
            background: var(--accent);
            border: none;
            color: white;
            font-weight: 900;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
            font-family: var(--font-game);
            letter-spacing: 1px;
        }

        .auth-btn:hover {
            background: var(--accent-bright);
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            color: var(--text-muted);
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 15px;
            background: var(--bg-input);
            border: 1px solid var(--border-default);
            color: white;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
            box-sizing: border-box;
        }

        .input-group input:focus {
            border-color: var(--accent);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid var(--red);
            color: var(--red);
        }

        .alert-success {
            background: rgba(5, 150, 105, 0.1);
            border: 1px solid var(--green);
            color: var(--green);
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <div class="auth-logo">PIXEL<span>FORGE</span></div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <i class="fad fa-thin fa-exclamation-circle" style="margin-right:8px;"></i>
                <?= htmlspecialchars($errors[0]) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fad fa-thin fa-check-circle" style="margin-right:8px;"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="input-group">
                <label>Username or Email</label>
                <input type="text" name="login" value="<?= htmlspecialchars($login ?? '') ?>" required autofocus
                    placeholder="Enter your credentials">
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="auth-btn">LOGIN TO DASHBOARD</button>
        </form>

        <div style="text-align:center; margin-top:30px; font-size:14px; color:var(--text-muted);">
            Don't have an account?
            <a href="<?= BASE_URL ?>/register.php"
                style="color: var(--accent); text-decoration:none; font-weight:bold;">Join
                the Forge</a>
        </div>
    </div>

</body>

</html>
<?php exit(); ?>