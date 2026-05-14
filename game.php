<?php require_once __DIR__ . '/includes/bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pixel Dash - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h1>🎮 PixelForge</h1>
            </div>
            <ul class="nav-items">
                <li><a href="/canvas.php">The Forge</a></li>
                <li class="active"><a href="/game.php">Pixel Dash</a></li>
                <li><a href="/leaderboard.php">Leaderboard</a></li>
                <li><a href="/profile.php">Profile</a></li>
            </ul>
            <div class="sidebar-footer">
                <div class="pxl-balance"><span id="pxl-display">0</span> PXL</div>
                <div class="username" id="username-display">-</div>
                <button id="logout-btn" class="btn btn-sm btn-secondary">Logout</button>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <h2>Pixel Dash</h2>
            </header>

            <div class="game-container">
                <div id="game-menu" class="game-menu">
                    <h3>Pixel Dash</h3>
                    <p>A fast-paced endless runner where you earn PXL to paint The Forge canvas</p>
                    <div class="game-stats">
                        <div class="stat"><strong id="best-score">0</strong> <span>Best Score</span></div>
                        <div class="stat"><strong id="total-earned">0</strong> <span>PXL Earned</span></div>
                        <div class="stat"><strong id="games-played">0</strong> <span>Games Played</span></div>
                    </div>
                    <button id="play-btn" class="btn btn-primary btn-large">PLAY</button>
                </div>

                <div id="game-viewport" class="game-viewport" style="display:none;">
                    <canvas id="gameCanvas"></canvas>
                    <div id="game-hud" class="game-hud">
                        <div class="lives"><span id="lives-display">❤️❤️❤️</span></div>
                        <div class="score">Score: <span id="score-display">0</span></div>
                        <div class="combo">x<span id="combo-display">1</span></div>
                        <div class="pxl">PXL: <span id="game-pxl-display">0</span></div>
                        <button id="pause-btn" class="btn btn-sm">⏸ Pause</button>
                        <button id="mute-btn" class="btn btn-sm">🔊</button>
                    </div>
                </div>

                <div id="game-over" class="game-over" style="display:none;">
                    <h3>Game Over</h3>
                    <div class="final-stats">
                        <div class="stat"><strong id="final-score">0</strong> <span>Score</span></div>
                        <div class="stat"><strong id="final-pxl">0</strong> <span>PXL Earned</span></div>
                        <div class="stat"><strong id="final-rank">-</strong> <span>Daily Rank</span></div>
                    </div>
                    <div id="achievements-earned" style="margin:20px 0;"></div>
                    <div class="buttons">
                        <button id="play-again-btn" class="btn btn-primary">Play Again</button>
                        <button id="forge-btn" class="btn btn-secondary">Go to Forge</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/ui.js"></script>
    <script type="module" src="/assets/js/api.js"></script>
    <script type="module" src="/assets/js/game/prng.js"></script>
    <script type="module" src="/assets/js/game/engine.js"></script>
    <script type="module" src="/assets/js/game/renderer.js"></script>
    <script type="module" src="/assets/js/game/obstacles.js"></script>
    <script type="module" src="/assets/js/game/collectibles.js"></script>
    <script type="module" src="/assets/js/game/audio.js"></script>
    <script type="module" src="/assets/js/game/hud.js"></script>
    <script type="module" src="/assets/js/game/game-main.js"></script>
</body>
</html>
