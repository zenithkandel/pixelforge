<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = pf_get_current_user();
$csrf_token = $_SESSION['csrf_token'] ?? '';
$user_balance = $user ? $user['pxl_balance'] : 0;
$is_logged_in = is_authenticated();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>The Forge - PixelForge</title>
    <meta name="csrf-token" content="<?php echo h($csrf_token); ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/canvas.css">
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
                <a href="/canvas.php" class="nav-item active">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="/game.php" class="nav-item">
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
                    <span class="balance-amount mono"><?php echo h($user_balance); ?> PXL</span>
                </div>
                <div class="user-tag">@<?php echo h($user ? $user['username'] : 'guest'); ?></div>
                <?php if ($is_logged_in): ?>
                    <a href="/api/auth/logout.php" class="btn btn-sm" style="margin-top: 8px; color: var(--text-sidebar-muted);">Logout</a>
                <?php endif; ?>
            </div>
        </aside>

        <main class="main-content">
            <div class="canvas-container">
                <div class="canvas-toolbar">
                    <div class="color-palette" id="colorPalette"></div>
                    <div class="color-custom">
                        <input type="text" id="customColor" placeholder="#000000" maxlength="7">
                        <div class="color-preview" id="colorPreview" style="background: #000000;"></div>
                    </div>
                    <div class="zoom-controls">
                        <button id="zoomOut">-</button>
                        <span class="zoom-level" id="zoomLevel">4x</span>
                        <button id="zoomIn">+</button>
                    </div>
                    <div class="mode-toggle">
                        <button class="active" id="panMode">Pan</button>
                        <button id="paintMode">Paint</button>
                    </div>
                    <div class="coordinate-search">
                        <input type="number" id="gotoX" placeholder="X" min="0" max="799">
                        <span>y</span>
                        <input type="number" id="gotoY" placeholder="Y" min="0" max="799">
                        <button id="gotoBtn">Go</button>
                    </div>
                    <div class="balance-info">
                        <span>&#9670;</span>
                        <span id="userBalance"><?php echo $user_balance; ?></span>
                    </div>
                </div>

                <div class="canvas-viewport" id="canvasViewport">
                    <canvas id="gridCanvas"></canvas>
                    <canvas id="overlayCanvas"></canvas>
                </div>

                <div class="mini-map" id="miniMap">
                    <canvas id="miniMapCanvas"></canvas>
                    <div class="mini-map-viewport" id="miniMapViewport"></div>
                </div>

                <div class="canvas-status">
                    <div class="status-left">
                        <span>X: <span id="coordX">0</span></span>
                        <span>Y: <span id="coordY">0</span></span>
                    </div>
                    <div class="status-right">
                        <span>Zoom: <span id="statusZoom">4x</span></span>
                        <span id="resetCountdown"></span>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="pixel-tooltip" id="pixelTooltip">
        <div class="coord">(0, 0)</div>
        <div class="owner"></div>
    </div>

    <div class="purchase-popover" id="purchasePopover">
        <div class="preview">
            <div class="preview-color"></div>
            <div class="preview-info">
                <div class="preview-coord">(0, 0)</div>
                <div class="preview-cost">Cost: 1 PXL</div>
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-sm btn-secondary btn-cancel" id="cancelPurchase">Cancel</button>
            <button class="btn btn-sm btn-primary" id="confirmPurchase">Paint</button>
        </div>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script type="module" src="/assets/js/canvas/canvas-main.js"></script>
</body>
</html>