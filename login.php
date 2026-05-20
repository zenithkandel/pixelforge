<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
$streak_bonus = $_SESSION['streak_bonus'] ?? 0;
$streak_days = $_SESSION['streak_days'] ?? 0;
unset($_SESSION['streak_bonus'], $_SESSION['streak_days']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $result = process_login($username, $password);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            header('Location: ' . APP_URL . '/game.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <main class="auth-page">
        <div class="auth-card">
            <h1>Login</h1>
            <form method="post" id="login-form">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <button type="submit" class="btn primary">Login</button>
            </form>
            <p class="auth-link">Don't have an account? <a href="<?php echo APP_URL; ?>/register.php">Register</a></p>
        </div>
    </main>

    <?php if ($streak_bonus > 0): ?>
    <div id="streak-toast" class="toast" data-bonus="<?php echo $streak_bonus; ?>" data-days="<?php echo $streak_days; ?>">
        <div class="toast-content">
            <span class="toast-icon">🔥</span>
            <div>
                <strong>Streak Bonus!</strong>
                <span>+<?php echo $streak_bonus; ?> currency for <?php echo $streak_days; ?>-day streak!</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        document.getElementById('login-form').addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Logging in...';
        });

        const toast = document.getElementById('streak-toast');
        if (toast) {
            setTimeout(() => toast.classList.add('show'), 500);
            setTimeout(() => toast.classList.remove('show'), 5000);
        }
    </script>
</body>
</html>