<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

$user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/canvas.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main>
        <?php if (!$user): ?>
        <div class="login-banner">
            <a href="<?php echo APP_URL; ?>/login.php">Log in</a> to draw on the canvas
        </div>
        <?php endif; ?>

        <div class="canvas-container">
            <canvas id="canvas" width="800" height="800"></canvas>
            <div class="canvas-controls">
                <button id="territory-toggle">Territory View</button>
                <span id="zoom-level">1×</span>
            </div>
            <div class="live-indicator">
                <span class="live-dot"></span> Live
            </div>
            <div id="minimap-container">
                <canvas id="minimap" width="100" height="100"></canvas>
                <div id="minimap-viewport"></div>
            </div>
        </div>
    </main>

    <script src="<?php echo APP_URL; ?>/assets/js/canvas.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/territory.js"></script>
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const USER_LOGGED_IN = <?php echo $user ? 'true' : 'false'; ?>;
        initCanvas(false);
    </script>
</body>
</html>