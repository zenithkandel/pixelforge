<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

$page_title = 'Canvas';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="text-align:center;">
        <h1>Live Pixel Canvas</h1>
        <p>Watch the community build in real-time. 100×100 grid.</p>
        <?php if (!is_logged_in()): ?>
            <p style="margin-top:12px;"><a href="<?= BASE_URL ?>/login.php" class="btn-primary">Log in to draw on the canvas</a></p>
        <?php endif; ?>
    </div>

    <div id="canvas-container" style="position:relative;display:flex;justify-content:center;">
        <canvas id="pixel-canvas" width="800" height="800"></canvas>
        <div id="canvas-status" style="position:absolute;top:8px;right:12px;display:flex;align-items:center;gap:6px;font-size:12px;color:var(--green);">
            <span style="width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse-dot 2s ease infinite;"></span> Live
        </div>
        <div id="zoom-indicator" style="position:absolute;bottom:8px;left:12px;font-size:12px;color:var(--text-muted);">1×</div>
    </div>

    <?php if (is_logged_in()): ?>
        <div style="display:flex;justify-content:center;gap:12px;margin-top:16px;">
            <button id="toggle-territory" class="btn-secondary btn-sm">Territory View</button>
            <button id="toggle-mypixels" class="btn-secondary btn-sm" style="display:none;">My Pixels</button>
        </div>
    <?php endif; ?>
</div>

<script>
const CANVAS = {
    pollingInterval: null,
    pixels: [],
    zoom: 1,
    offsetX: 0,
    offsetY: 0,
    isDragging: false,
    dragStartX: 0,
    dragStartY: 0,
    dragOffsetStartX: 0,
    dragOffsetStartY: 0,
    gridSize: 100,
    cellSize: 8,
    territoryMode: false,
    myPixelsMode: false,
    currentUserId: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null' ?>,
};
</script>
<script src="<?= BASE_URL ?>/assets/js/index-canvas.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
