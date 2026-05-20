<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';
require_login();

$page_title = 'Draw';
$extra_css = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="text-align:center;">
        <h1>Interactive Canvas</h1>
        <p>Click a cell to place or repaint a pixel. Cost: 5 💰 for unclaimed cells.</p>
    </div>

    <div style="display:flex;gap:24px;flex-wrap:wrap;justify-content:center;">
        <div id="canvas-container" style="position:relative;">
            <canvas id="pixel-canvas" width="800" height="800"></canvas>
            <div id="canvas-status" style="position:absolute;top:8px;right:12px;display:flex;align-items:center;gap:6px;font-size:12px;color:var(--green);">
                <span style="width:8px;height:8px;border-radius:50%;background:var(--green);animation:pulse-dot 2s ease infinite;"></span> Live
            </div>
            <div id="zoom-indicator" style="position:absolute;bottom:8px;left:12px;font-size:12px;color:var(--text-muted);">1×</div>
        </div>

        <div id="pixel-panel" class="card" style="width:280px;align-self:flex-start;display:none;">
            <h3 style="margin-top:0;font-size:16px;">Pixel <span id="panel-coords"></span></h3>
            <div id="panel-owner" class="form-group" style="font-size:13px;color:var(--text-muted);"></div>
            <div class="form-group">
                <label for="pixel-color">Color</label>
                <input type="color" id="pixel-color" value="#7c3aed">
                <input type="text" id="pixel-color-hex" value="#7c3aed" style="margin-top:4px;font-family:var(--font-mono);font-size:13px;">
            </div>
            <div class="form-group" style="font-size:14px;">
                Cost: <span id="panel-cost" class="currency">5 💰</span>
            </div>
            <button id="place-pixel-btn" class="btn-primary" style="width:100%;">Place Pixel</button>
            <div id="panel-error" class="form-error"></div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;gap:12px;margin-top:16px;">
        <button id="toggle-territory" class="btn-secondary btn-sm">Territory View</button>
        <button id="toggle-mypixels" class="btn-secondary btn-sm">My Pixels</button>
    </div>
</div>

<script>
window.CANVAS_CONFIG = {
    currentUserId: <?= (int)$_SESSION['user_id'] ?>,
    csrfToken: '<?= csrf_token() ?>',
    username: '<?= htmlspecialchars($_SESSION['username']) ?>',
};
</script>
<script src="<?= BASE_URL ?>/assets/js/canvas.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
