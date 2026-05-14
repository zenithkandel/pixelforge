<?php require_once __DIR__ . '/includes/bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PixelForge</title>
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
                <li><a href="/leaderboard.php">Leaderboard</a></li>
                <li class="active"><a href="/profile.php">Profile</a></li>
            </ul>
            <div class="sidebar-footer">
                <div class="pxl-balance"><span id="pxl-display">0</span> PXL</div>
                <div class="username" id="username-display">-</div>
                <button id="logout-btn" class="btn btn-sm btn-secondary">Logout</button>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <h2>Profile</h2>
            </header>

            <div class="profile-container">
                <div class="profile-card">
                    <div class="avatar" id="avatar">👤</div>
                    <h3 id="profile-username">-</h3>
                    <p id="profile-joined">Joined -</p>
                    <div class="streak-badge" id="streak-badge" style="display:none;">
                        <span id="streak-days">0</span> day streak 🔥
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value" id="total-pxl">0</div>
                        <div class="stat-label">Total PXL Earned</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" id="total-pixels">0</div>
                        <div class="stat-label">Pixels Painted</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" id="best-score">0</div>
                        <div class="stat-label">Best Score</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" id="games-played">0</div>
                        <div class="stat-label">Games Played</div>
                    </div>
                </div>

                <div class="achievements-section">
                    <h3>Achievements</h3>
                    <div id="achievements-grid" class="achievements-grid"></div>
                </div>

                <div class="history-section">
                    <h3>Recent Activity</h3>
                    <div class="tabs">
                        <button class="tab-btn active" data-tab="recent-pixels">Recent Pixels</button>
                        <button class="tab-btn" data-tab="recent-scores">Recent Scores</button>
                        <button class="tab-btn" data-tab="pxl-transactions">PXL Transactions</button>
                    </div>

                    <div id="recent-pixels" class="tab-content active">
                        <!-- Populated by JS -->
                    </div>
                    <div id="recent-scores" class="tab-content" style="display:none;">
                        <!-- Populated by JS -->
                    </div>
                    <div id="pxl-transactions" class="tab-content" style="display:none;">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>
    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/ui.js"></script>
    <script type="module" src="/assets/js/api.js"></script>
    <script type="module" src="/assets/js/profile.js"></script>
</body>

</html>