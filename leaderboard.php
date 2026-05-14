<?php require_once __DIR__ . '/includes/bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - PixelForge</title>
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
                <li><a href="/game.php">Pixel Dash</a></li>
                <li class="active"><a href="/leaderboard.php">Leaderboard</a></li>
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
                <h2>Leaderboard</h2>
                <div class="leaderboard-tabs">
                    <button class="tab-btn active" data-tab="daily">Daily</button>
                    <button class="tab-btn" data-tab="weekly">Weekly</button>
                    <button class="tab-btn" data-tab="alltime">All Time</button>
                </div>
            </header>

            <div class="leaderboard-container">
                <div id="daily" class="leaderboard-tab active">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>PXL Earned</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody id="daily-scores"></tbody>
                    </table>
                </div>

                <div id="weekly" class="leaderboard-tab" style="display:none;">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>PXL Earned</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody id="weekly-scores"></tbody>
                    </table>
                </div>

                <div id="alltime" class="leaderboard-tab" style="display:none;">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>PXL Earned</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody id="alltime-scores"></tbody>
                    </table>
                </div>
            </div>

            <div class="pagination">
                <button id="prev-page" class="btn btn-sm">← Previous</button>
                <span id="page-info">Page 1</span>
                <button id="next-page" class="btn btn-sm">Next →</button>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/ui.js"></script>
    <script type="module" src="/assets/js/api.js"></script>
    <script type="module" src="/assets/js/leaderboard.js"></script>
</body>
</html>
