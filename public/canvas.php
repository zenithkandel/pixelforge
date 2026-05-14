<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$user = null;
if (isset($_SESSION['user_id'])) {
    $user = get_current_user_data();
}
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= h($csrf_token) ?>" />
    <title>The Forge — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/main.css" />
    <link rel="stylesheet" href="/assets/css/canvas.css" />
</head>
<body>
    <div class="app-shell">
        <?php if ($user): ?>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">PF</div>
                <div class="brand-text">
                    <span class="brand-name">PixelForge</span>
                    <span class="brand-tagline">Paint the World</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="/canvas.php" class="nav-item active">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="/game.php" class="nav-item">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="/leaderboard.php" class="nav-item">
                    <span class="nav-icon">&#9672;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="/profile.php" class="nav-item">
                    <span class="nav-icon">&#9678;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?= h($user['pxl_balance']) ?> PXL</span>
                </div>
                <div class="user-tag">@<?= h($user['username']) ?></div>
            </div>
        </aside>
        <?php endif; ?>

        <main class="main-content <?= $user ? '' : 'no-sidebar' ?>">
            <div class="canvas-toolbar" id="canvas-toolbar">
                <?php if ($user): ?>
                <div class="toolbar-section">
                    <div class="color-palette" id="color-palette">
                        <button class="color-btn active" data-color="#000000" style="background:#000000" title="Black"></button>
                        <button class="color-btn" data-color="#FFFFFF" style="background:#FFFFFF;border:1px solid #ccc" title="White"></button>
                        <button class="color-btn" data-color="#FF0000" style="background:#FF0000" title="Red"></button>
                        <button class="color-btn" data-color="#FF8000" style="background:#FF8000" title="Orange"></button>
                        <button class="color-btn" data-color="#FFFF00" style="background:#FFFF00" title="Yellow"></button>
                        <button class="color-btn" data-color="#00FF00" style="background:#00FF00" title="Green"></button>
                        <button class="color-btn" data-color="#00FFFF" style="background:#00FFFF" title="Cyan"></button>
                        <button class="color-btn" data-color="#0000FF" style="background:#0000FF" title="Blue"></button>
                        <button class="color-btn" data-color="#8000FF" style="background:#8000FF" title="Purple"></button>
                        <button class="color-btn" data-color="#FF00FF" style="background:#FF00FF" title="Magenta"></button>
                        <button class="color-btn" data-color="#FF69B4" style="background:#FF69B4" title="Pink"></button>
                        <button class="color-btn" data-color="#8B4513" style="background:#8B4513" title="Brown"></button>
                        <div class="color-custom">
                            <input type="color" id="custom-color" value="#000000" />
                        </div>
                    </div>
                </div>
                <div class="toolbar-section">
                    <div class="mode-toggle">
                        <button class="mode-btn active" data-mode="pan" id="btn-pan" title="Pan (default)">&#9994; Pan</button>
                        <button class="mode-btn" data-mode="buy" id="btn-buy" title="Buy pixels (costs 1 PXL)">&#9670; Buy (1 PXL)</button>
                    </div>
                </div>
                <div class="toolbar-section toolbar-zoom">
                    <button class="zoom-btn" id="zoom-out" title="Zoom out">-</button>
                    <span class="zoom-level mono" id="zoom-level">4×</span>
                    <button class="zoom-btn" id="zoom-in" title="Zoom in">+</button>
                    <button class="zoom-btn" id="zoom-fit" title="Fit to view">&#9632;</button>
                </div>
                <div class="toolbar-section toolbar-coords">
                    <span class="coord-display mono" id="coord-display">X: 0 Y: 0</span>
                </div>
                <?php else: ?>
                <div class="toolbar-section">
                    <a href="/" class="btn btn-primary">Sign In to Paint</a>
                    <span class="guest-note">Viewing as guest &mdash; <a href="/canvas.php?signup=1" class="link">Create account</a></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="canvas-wrapper" id="canvas-wrapper">
                <canvas id="forge-canvas"></canvas>
                <div class="canvas-loading" id="canvas-loading">
                    <div class="loading-spinner"></div>
                    <span>Loading chunks...</span>
                </div>
            </div>

            <div class="canvas-minimap-container">
                <canvas id="minimap-canvas" width="160" height="160"></canvas>
            </div>

            <div class="canvas-toast-container" id="toast-container"></div>
        </main>
    </div>

    <script type="module" src="/assets/js/canvas/canvas-main.js"></script>
</body>
</html>