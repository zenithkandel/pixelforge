<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIXEL DASH - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/game.css">
</head>
<body>
    <?php include dirname(__DIR__) . '/public/includes/sidebar.php'; ?>
    
    <main class="content">
        <header>
            <h2>PIXEL DASH</h2>
        </header>
        
        <div id="game-container">
            <div id="hud">
                <div id="lives">❤️❤️❤️</div>
                <div id="score">SCORE: 0</div>
                <div id="combo">COMBO: x1</div>
                <button id="mute-btn">🔇</button>
            </div>
            <canvas id="gameCanvas" width="800" height="400"></canvas>
            <div id="game-overlay">
                <button id="play-btn">PLAY</button>
            </div>
        </div>
    </main>

    <script type="module" src="/assets/js/game/game-main.js"></script>
</body>
</html>
