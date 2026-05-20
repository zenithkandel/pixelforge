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
            $attempts = (int)$stmt->fetchColumn();

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
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    $today = date('Y-m-d');
                    $last = $user['last_login_date'];
                    $streak = (int)$user['streak_days'];
                    $bonus = 0;

                    if ($last !== $today) {
                        if ($last === date('Y-m-d', strtotime('-1 day'))) {
                            $streak++;
                        } else {
                            $streak = 1;
                        }

                        $streak_bonuses = [1 => 10, 2 => 20, 3 => 35, 5 => 60, 7 => 150, 14 => 400, 30 => 800];
                        foreach ($streak_bonuses as $day => $b) {
                            if ($streak >= $day) $bonus = $b;
                        }

                        if ($bonus > 0) {
                            $db->prepare('UPDATE users SET balance = balance + ? WHERE id = ?')->execute([$bonus, (int)$user['id']]);
                            $_SESSION['streak_bonus'] = $bonus;
                            log_info('AUTH', 'Daily streak bonus awarded', ['streak' => $streak, 'bonus' => $bonus]);
                        }

                        $db->prepare('UPDATE users SET last_login_date = ?, streak_days = ? WHERE id = ?')->execute([$today, $streak, (int)$user['id']]);
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

$page_title = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-center">
    <div class="card auth-card">
        <div style="text-align:center;margin-bottom:var(--space-lg);">
            <div style="font-size:48px;margin-bottom:var(--space-sm);">🎨</div>
            <h2>Welcome Back</h2>
            <p style="color:var(--text-secondary);margin:0;">Log in to your <?= APP_NAME ?> account.</p>
        </div>

        <?php if ($success): ?>
            <div class="form-success" style="background:rgba(34,197,94,0.1);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:var(--space-md);text-align:center;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);padding:12px 16px;border-radius:var(--radius-md);margin-bottom:var(--space-md);">
                <?php foreach ($errors as $err): ?>
                    <div style="color:var(--red);font-size:14px;"><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label for="login">Username or Email</label>
                <input type="text" id="login" name="login" required placeholder="Enter username or email" value="<?= htmlspecialchars($login ?? '') ?>" autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter password" autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;margin-top:var(--space-sm);">Sign In</button>
        </form>
        <p style="text-align:center;margin-top:var(--space-lg);margin-bottom:0;color:var(--text-muted);font-size:14px;">
            Don't have an account? <a href="<?= BASE_URL ?>/register.php" style="font-weight:600;">Create one</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
