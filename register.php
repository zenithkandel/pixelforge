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

$page_title = 'Register';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-center">
    <div class="card auth-card">
        <h2>Join <?= APP_NAME ?></h2>
        <p style="color:var(--text-secondary);margin-bottom:24px;">Create your account and start building.</p>

        <?php foreach ($errors as $err): ?>
            <div style="background:rgba(239,68,68,0.1);color:var(--red);padding:10px 16px;border-radius:8px;margin-bottom:8px;"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required maxlength="30" value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">Register</button>
        </form>
        <p style="text-align:center;margin-top:20px;color:var(--text-muted);">
            Already have an account? <a href="<?= BASE_URL ?>/login.php" style="color:var(--purple-bright);">Login</a>
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
