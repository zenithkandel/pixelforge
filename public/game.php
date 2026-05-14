<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_auth();
$csrf_token = $_SESSION['csrf_token'] ?? '';

$pdo = get_db();
$stmt = $pdo->prepare("SELECT MAX(score) as best_score FROM scores WHERE user_id = ?");
$stmt->execute([$user['id']]);
$best_score = (int)($stmt->fetch()['best_score'] ?? 0);

$stmt = $pdo->prepare("SELECT COUNT(*) as games_played FROM game_sessions WHERE user_id = ? AND ended_at IS NOT NULL");
$stmt->execute([$user['id']]);
$games_played = (int)($stmt->fetch()['games_played'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PIXEL DASH - PixelForge</title>
    <meta name="csrf-token" content="<?php echo h($csrf_token); ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/game.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">PF</div>
                <div class="brand-text">
                    <span class="brand-name">PixelForge</span>
                    <span class="brand-tagline">Paint the World</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="/canvas.php" class="nav-item">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="/game.php" class="nav-item active">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="/leaderboard.php" class="nav-item">
                    <span class="nav-icon">&#9670;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="/profile.php" class="nav-item">
                    <span class="nav-icon">&#9673;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?php echo h($user['pxl_balance']); ?> PXL</span>
                </div>
                <div class="user-tag">@<?php echo h($user['username']); ?></div>
            </div>
        </aside>

        <main class="main-content">
            <div class="game-container">
                <div class="game-lobby" id="lobby">
                    <div class="lobby-header">
                        <h1 class="lobby-title">PIXEL DASH</h1>
                        <p class="lobby-subtitle">Race through the glitch mainframe, collect color shards, earn PXL!</p>
                    </div>

                    <div class="stats-row">
                        <div class="stat-card">
                            <div class="stat-value"><?php echo number_format($best_score); ?></div>
                            <div class="stat-label">Best Score</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value pxl"><?php echo h($user['pxl_balance']); ?></div>
                            <div class="stat-label">PXL Balance</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?php echo $games_played; ?></div>
                            <div class="stat-label">Games Played</div>
                        </div>
                    </div>

                    <button class="btn btn-primary btn-lg play-button" id="playBtn">PLAY</button>

                    <div class="instructions">
                        <div class="instructions-title">How to Play</div                        >
                        <div class="instructions-content">
                            <table class="controls-table">
                                <tr><td>Jump</td><td>Space / Arrow Up / W</td></tr>
                                <tr><td>Double Jump</td><td>Space again while airborne</td></tr>
                                <tr><td>Slide</td><td>Arrow Down / S</td></tr>
                                <tr><td>Pause</td><td>Escape / P</td></tr>
                                <tr><td>Mobile</td><td>Tap left = Jump, Tap right = Slide</td></tr>
                            </table>
                        </div>
                    </div>

                    <div class="leaderboard-preview">
                        <div class="leaderboard-preview-title">Today's Top</div>
                        <table>
                            <thead><tr><th>Rank</th><th>Player</th><th>Score</th></tr></thead>
                            <tbody id="leaderboardBody">
                                <tr><td colspan="3">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="game-area" id="gameArea" style="display: none;">
                    <div class="game-canvas-wrapper">
                        <canvas id="gameCanvas"></canvas>
                        <div class="game-hud">
                            <div class="hud-left">
                                <div class="hud-item hud-lives" id="hudLives"></div>
                                <div class="hud-item hud-score" id="hudScore">0</div>
                                <div class="hud-item hud-combo" id="hudCombo">x1</div>
                            </div>
                            <div class="hud-right">
                                <div class="hud-item hud-pxl">◆ <span id="hudPxl"><?php echo h($user['pxl_balance']); ?></span></div>
                                <div class="hud-controls">
                                    <button id="muteBtn" title="Mute">🔊</button>
                                    <button id="pauseBtn" title="Pause">⏸</button>
                                </div>
                            </div>
                        </div>
                        <div class="powerup-bar" id="powerupBar" style="display: none;">
                            <div class="powerup-bar-fill" id="powerupBarFill"></div>
                        </div>
                        <div class="pause-overlay hidden" id="pauseOverlay">
                            <div class="pause-title">PAUSED</div>
                            <div class="pause-buttons">
                                <button class="btn btn-primary" id="resumeBtn">RESUME</button>
                                <button class="btn btn-secondary" id="quitBtn">QUIT</button>
                            </div>
                        </div>
                        <div class="game-over-overlay hidden" id="gameOverOverlay">
                            <div class="game-over-title">GAME OVER</div>
                            <div class="game-over-score" id="finalScore">0</div>
                            <div class="game-over-pxl" id="pxlEarned">+0 PXL</div>
                            <div class="game-over-stats" id="gameOverStats"></div>
                            <div class="game-over-buttons">
                                <button class="btn btn-primary btn-lg" id="playAgainBtn">PLAY AGAIN</button>
                                <button class="btn btn-secondary" id="goToForgeBtn">GO TO FORGE</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script type="module" src="/assets/js/game/game-main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            fetch('/api/leaderboard.php?type=daily&limit=5')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('leaderboardBody');
                    if (data.ok && data.data.scores.length > 0) {
                        tbody.innerHTML = data.data.scores.map((s, i) =>
                            `<tr><td>${i + 1}</td><td>${s.username}</td><td class="mono">${s.score.toLocaleString()}</td></tr>`
                        ).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3">No scores yet</td></tr>';
                    }
                });
        });
    </script>
</body>
</html>