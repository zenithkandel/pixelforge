<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $user = Database::fetch('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $username]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials';
        } else {
            login_user($user);
            header('Location: ' . APP_URL . '/game.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Login</title>
</head>

<body>
    <form method="post">
        <?php echo csrf_field(); ?>
        <label>Username or email <input name="username" required></label>
        <label>Password <input name="password" type="password" required></label>
        <?php if ($error)
            echo '<div style="color:red">' . htmlspecialchars($error) . '</div>'; ?>
        <button>Login</button>
    </form>
</body>

</html>