<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Game - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <h1>Game</h1>
    <input type="hidden" id="game-token" value="">
    <input type="hidden" id="csrf-token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <div id="game-root"></div>
    <script>const APP_URL = '<?php echo APP_URL; ?>';</script>
    <script src="assets/js/game.js"></script>
</body>

</html>