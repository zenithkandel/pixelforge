<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
$is_logged_in = !empty($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Forge - PixelForge</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/canvas.css">
    <script>window.IS_LOGGED_IN = <?= $is_logged_in ? 'true' : 'false' ?>;</script>
</head>
<body>
    <?php include dirname(__DIR__) . '/public/includes/sidebar.php'; ?>
    
    <main class="content">
        <header>
            <h2>The Forge</h2>
            <div id="coords">X: 0, Y: 0</div>
            <div class="zoom-controls">
                <button id="zoom-out">-</button>
                <span id="zoom-level">1x</span>
                <button id="zoom-in">+</button>
            </div>
            <?php if ($is_logged_in): ?>
                <div class="color-picker">
                    <input type="color" id="current-color" value="#FF0000">
                </div>
            <?php endif; ?>
        </header>
        
        <div id="canvas-container">
            <canvas id="gridCanvas" width="800" height="800"></canvas>
            <canvas id="overlayCanvas" width="800" height="800"></canvas>
        </div>
    </main>

    <script type="module" src="assets/js/canvas/canvas-main.js"></script>
</body>
</html>
