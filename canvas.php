<?php require_once __DIR__ . '/includes/bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Forge - PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>

<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h1>🎮 PixelForge</h1>
            </div>
            <ul class="nav-items">
                <li class="active"><a href="/canvas.php">The Forge</a></li>
                <li><a href="/game.php">Pixel Dash</a></li>
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
                <h2>The Forge</h2>
                <div class="toolbar">
                    <div class="color-palette">
                        <div class="color-picker">
                            <input type="color" id="color-input" value="#000000">
                        </div>
                        <div id="quick-colors" class="quick-colors"></div>
                    </div>
                    <div class="zoom-controls">
                        <button id="zoom-in" class="btn btn-sm">+</button>
                        <span id="zoom-level">1×</span>
                        <button id="zoom-out" class="btn btn-sm">-</button>
                    </div>
                    <input type="text" id="coordinates" placeholder="x,y" class="coordinate-input">
                </div>
            </header>

            <div class="canvas-container">
                <canvas id="gridCanvas" width="800" height="800"></canvas>
                <canvas id="overlayCanvas" width="800" height="800" class="overlay"></canvas>
                <div id="mini-map" class="mini-map">
                    <canvas id="mini-map-canvas" width="200" height="200"></canvas>
                    <div id="mini-map-viewport" class="mini-map-viewport"></div>
                </div>
            </div>

            <div class="info-bar">
                <span id="mouse-coords">x: 0, y: 0</span>
                <span id="pixel-owner">-</span>
                <span id="status-text">Ready</span>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/ui.js"></script>
    <script type="module" src="/assets/js/api.js"></script>
    <script type="module" src="/assets/js/canvas/grid-renderer.js"></script>
    <script type="module" src="/assets/js/canvas/chunk-cache.js"></script>
    <script type="module" src="/assets/js/canvas.js"></script>
</body>

</html>