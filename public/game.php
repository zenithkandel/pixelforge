<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$user = get_current_user_data();
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= h($csrf_token) ?>" />
    <title>PIXEL DASH — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/game.css" />
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
                <a href="<?= BASE_URL ?>canvas.php" class="nav-item">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="<?= BASE_URL ?>game.php" class="nav-item active">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="<?= BASE_URL ?>leaderboard.php" class="nav-item">
                    <span class="nav-icon">&#9672;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="<?= BASE_URL ?>profile.php" class="nav-item">
                    <span class="nav-icon">&#9678;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?= h((string)($user['pxl_balance'] ?? 0)) ?> PXL</span>
                </div>
                <div class="user-tag">@<?= h($user['username']) ?></div>
            </div>
        </aside>

        <main class="main-content">
            <div class="game-container" id="game-container">
                <div class="game-start-screen" id="game-start-screen">
                    <div class="game-logo-area">
                        <div class="game-logo-text">PIXEL DASH</div>
                        <div class="game-tagline">Race through the data stream. Collect shards. Earn PXL.</div>
                    </div>
                    <button class="btn btn-primary btn-xl" id="play-btn">&#9654; PLAY</button>
                    <div class="controls-hint">
                        <div class="control-item"><kbd>SPACE</kbd> / <kbd>W</kbd> Jump</div>
                        <div class="control-item"><kbd>&#8595;</kbd> / <kbd>S</kbd> Slide</div>
                        <div class="control-item"><kbd>ESC</kbd> Pause</div>
                    </div>
                </div>

                <canvas id="game-canvas" width="960" height="360" hidden></canvas>

                <div class="game-over-screen" id="game-over-screen" hidden>
                    <div class="game-over-title">GAME OVER</div>
                    <div class="game-over-score">
                        <span class="score-label">SCORE</span>
                        <span class="score-value mono" id="go-score">0</span>
                    </div>
                    <div class="game-over-pxl" id="go-pxl-area" hidden>
                        <span class="pxl-earned">+<span id="go-pxl">0</span></span>
                        <span class="pxl-label pxl-text">PXL</span>
                    </div>
                    <div class="game-over-best" id="go-best" hidden>
                        <span class="best-badge">NEW BEST!</span>
                    </div>
                    <div class="game-over-stats">
                        <div class="go-stat">
                            <span class="go-stat-label">Distance</span>
                            <span class="go-stat-value mono" id="go-distance">0m</span>
                        </div>
                        <div class="go-stat">
                            <span class="go-stat-label">Max Tier</span>
                            <span class="go-stat-value mono" id="go-tier">1</span>
                        </div>
                    </div>
                    <button class="btn btn-primary btn-xl" id="replay-btn">&#8635; PLAY AGAIN</button>
                    <a href="<?= BASE_URL ?>canvas.php" class="btn btn-secondary">&#9632; The Forge</a>
                </div>

                <div class="game-pause-overlay" id="game-pause-overlay" hidden>
                    <div class="pause-box">
                        <div class="pause-title">PAUSED</div>
                        <button class="btn btn-primary" id="resume-btn">&#9654; Resume</button>
                        <button class="btn btn-secondary" id="quit-btn">Quit</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script type="module" src="<?= BASE_URL ?>assets/js/game/game-main.js"></script>
</body>
</html>