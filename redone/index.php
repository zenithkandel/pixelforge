<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <?php if ($user): ?>
        <h1>Welcome, <?php echo htmlspecialchars($user['username']); ?></h1>
        <p>Balance: <?php echo (int) $user['balance']; ?></p>
        <a href="game.php">Play game</a> | <a href="logout.php">Logout</a>
    <?php else: ?>
        <h1>Welcome</h1>
        <a href="login.php">Login</a> | <a href="register.php">Register</a>
    <?php endif; ?>
</body>

</html>