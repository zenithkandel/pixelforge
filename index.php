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

<div class="dashboard-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
    <div class="dash-hero" style="grid-column:span 2; background:linear-gradient(135deg, var(--purple), var(--cyan)); padding:40px; border-radius:12px; display:flex; justify-content:space-between; align-items:center; color:white; overflow:hidden; position:relative;">
        <div style="z-index:1;">
            <h1 style="font-size:36px; font-weight:900; line-height:1.2; margin-bottom:10px;">WELCOME TO<br>PIXEL FLAP</h1>
            <p style="opacity:0.9; margin-bottom:20px;">The community-driven world where every flight builds a masterpiece.</p>
            <div style="display:flex; gap:10px;">
                <a href="<?= BASE_URL ?>/game.php" class="btn-pixel" style="background:white; color:var(--purple); box-shadow:0 4px 0 #ddd;">PLAY NOW</a>
                <a href="<?= BASE_URL ?>/canvas.php" class="btn-pixel" style="background:rgba(0,0,0,0.2); color:white; box-shadow:0 4px 0 rgba(0,0,0,0.4);">START DRAWING</a>
            </div>
        </div>
        <div class="hero-bird" style="font-size:120px; opacity:0.3; transform:rotate(-15deg); user-select:none;">🐦</div>
    </div>

    <div class="dash-card widget">
        <div class="widget-title">Live Preview</div>
        <div id="canvas-container" style="position:relative; width:100%; aspect-ratio:1; background:#111; border-radius:8px; overflow:hidden; border:1px solid var(--border-dim);">
            <canvas id="pixel-canvas" width="800" height="800" style="width:100%; height:100%; display:block;"></canvas>
            <div id="canvas-status" style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.7); padding:4px 10px; border-radius:20px; font-size:10px; color:var(--green); font-weight:bold; display:flex; align-items:center; gap:5px;">
                <div style="width:6px; height:6px; background:var(--green); border-radius:50%; animation:pulse 1s infinite;"></div> LIVE
            </div>
            <div id="zoom-indicator" style="display:none;"></div>
        </div>
    </div>

    <div class="dash-card widget">
        <div class="widget-title">Recent Activity</div>
        <div class="recent-list" style="display:flex; flex-direction:column; gap:12px;">
            <?php if ($nav_user): ?>
                <div class="activity-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg-input); border-radius:8px;">
                    <div style="width:36px; height:36px; background:var(--purple); border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fas fa-play"></i></div>
                    <div>
                        <div style="font-size:14px; font-weight:bold;">Ready for flight?</div>
                        <div style="font-size:11px; color:var(--text-muted);">Your last score was <b><?= number_format($nav_user['best_score'] ?? 0) ?></b></div>
                    </div>
                </div>
                <div class="activity-item" style="display:flex; align-items:center; gap:12px; padding:12px; background:var(--bg-input); border-radius:8px;">
                    <div style="width:36px; height:36px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fas fa-coins"></i></div>
                    <div>
                        <div style="font-size:14px; font-weight:bold;">Bank Balance</div>
                        <div style="font-size:11px; color:var(--text-muted);">You currently hold <b><?= number_format($nav_user['balance']) ?></b> credits</div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:40px; color:var(--text-muted);">
                    <i class="fas fa-lock" style="font-size:24px; margin-bottom:10px;"></i>
                    <p>LogIn to see your stats</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (is_logged_in()): ?>
        <div style="display:none;">
            <button id="toggle-territory"></button>
            <button id="toggle-mypixels"></button>
        </div>
    <?php endif; ?>
</div>

<style>
@keyframes pulse { 0% { opacity: 0.5; } 50% { opacity: 1; } 100% { opacity: 0.5; } }
</style>


<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
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

const canvas = document.getElementById('pixel-canvas');
if (!canvas) throw new Error('Canvas not found');
const ctx = canvas.getContext('2d');
if (!ctx) throw new Error('2D context not found');

const minimapCanvas = document.createElement('canvas');
minimapCanvas.width = 100;
minimapCanvas.height = 100;
const minimapCtx = minimapCanvas.getContext('2d');

function renderCanvas() {
    ctx.fillStyle = '#1a1a1a';
    ctx.fillRect(0, 0, 800, 800);

    const size = CANVAS.cellSize * CANVAS.zoom;
    const startX = CANVAS.offsetX % size;
    const startY = CANVAS.offsetY % size;

    ctx.save();
    ctx.beginPath();
    ctx.rect(0, 0, 800, 800);
    ctx.clip();

    const startCol = Math.max(0, Math.floor(-CANVAS.offsetX / size));
    const startRow = Math.max(0, Math.floor(-CANVAS.offsetY / size));
    const endCol = Math.min(CANVAS.gridSize, Math.ceil((800 - CANVAS.offsetX) / size));
    const endRow = Math.min(CANVAS.gridSize, Math.ceil((800 - CANVAS.offsetY) / size));

    const pixelMap = {};
    CANVAS.pixels.forEach(p => { pixelMap[p.x + ',' + p.y] = p; });

    for (let row = startRow; row < endRow; row++) {
        for (let col = startCol; col < endCol; col++) {
            const px = CANVAS.offsetX + col * size;
            const py = CANVAS.offsetY + row * size;

            if (CANVAS.myPixelsMode && CANVAS.currentUserId) {
                const p = pixelMap[col + ',' + row];
                if (p && p.owner_id === CANVAS.currentUserId) {
                    ctx.fillStyle = CANVAS.territoryMode ? (p.color || '#7c3aed') : p.color;
                } else {
                    ctx.fillStyle = p ? CANVAS.territoryMode ? '#1a1a1a' : dimColor(p.color) : '#1a1a1a';
                }
            } else if (CANVAS.territoryMode) {
                const p = pixelMap[col + ',' + row];
                ctx.fillStyle = p ? (p.color || '#7c3aed') : '#1a1a1a';
            } else {
                const p = pixelMap[col + ',' + row];
                ctx.fillStyle = p ? p.color : '#1a1a1a';
            }
            ctx.fillRect(px, py, size + 0.5, size + 0.5);
        }
    }

    ctx.restore();

    if (CANVAS.zoom >= 3) {
        ctx.strokeStyle = '#252535';
        ctx.lineWidth = 0.5;
        for (let col = startCol; col <= endCol; col++) {
            const x = CANVAS.offsetX + col * size;
            ctx.beginPath();
            ctx.moveTo(x, 0);
            ctx.lineTo(x, 800);
            ctx.stroke();
        }
        for (let row = startRow; row <= endRow; row++) {
            const y = CANVAS.offsetY + row * size;
            ctx.beginPath();
            ctx.moveTo(0, y);
            ctx.lineTo(800, y);
            ctx.stroke();
        }
    }
}

function dimColor(hex) {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgb(${Math.floor(r*0.5)},${Math.floor(g*0.5)},${Math.floor(b*0.5)})`;
}

function getCellFromEvent(e) {
    const rect = canvas.getBoundingClientRect();
    const mx = (e.clientX - rect.left) * (800 / rect.width);
    const my = (e.clientY - rect.top) * (800 / rect.height);
    const size = CANVAS.cellSize * CANVAS.zoom;
    const col = Math.floor((mx - CANVAS.offsetX) / size);
    const row = Math.floor((my - CANVAS.offsetY) / size);
    if (col < 0 || col >= CANVAS.gridSize || row < 0 || row >= CANVAS.gridSize) return null;
    return { col, row };
}

canvas.addEventListener('wheel', function(e) {
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const mx = (e.clientX - rect.left) * (800 / rect.width);
    const my = (e.clientY - rect.top) * (800 / rect.height);

    const oldZoom = CANVAS.zoom;
    CANVAS.zoom = Math.max(1, Math.min(6, CANVAS.zoom - e.deltaY * 0.01));

    const fx = CANVAS.offsetX + mx;
    const fy = CANVAS.offsetY + my;
    const ratio = CANVAS.zoom / oldZoom;
    CANVAS.offsetX = mx - (mx - CANVAS.offsetX) * ratio;
    CANVAS.offsetY = my - (my - CANVAS.offsetY) * ratio;

    document.getElementById('zoom-indicator').textContent = Math.round(CANVAS.zoom) + '×';
    renderCanvas();
});

canvas.addEventListener('mousedown', function(e) {
    CANVAS.isDragging = true;
    CANVAS.dragStartX = e.clientX;
    CANVAS.dragStartY = e.clientY;
    CANVAS.dragOffsetStartX = CANVAS.offsetX;
    CANVAS.dragOffsetStartY = CANVAS.offsetY;
    canvas.style.cursor = 'grabbing';
});

window.addEventListener('mousemove', function(e) {
    if (!CANVAS.isDragging) return;
    const dx = e.clientX - CANVAS.dragStartX;
    const dy = e.clientY - CANVAS.dragStartY;
    CANVAS.offsetX = CANVAS.dragOffsetStartX + dx;
    CANVAS.offsetY = CANVAS.dragOffsetStartY + dy;
    renderCanvas();
});

window.addEventListener('mouseup', function() {
    CANVAS.isDragging = false;
    canvas.style.cursor = 'grab';
});

canvas.addEventListener('touchstart', function(e) {
    if (e.touches.length === 1) {
        CANVAS.isDragging = true;
        CANVAS.dragStartX = e.touches[0].clientX;
        CANVAS.dragStartY = e.touches[0].clientY;
        CANVAS.dragOffsetStartX = CANVAS.offsetX;
        CANVAS.dragOffsetStartY = CANVAS.offsetY;
    }
});

canvas.addEventListener('touchmove', function(e) {
    if (!CANVAS.isDragging || e.touches.length !== 1) return;
    e.preventDefault();
    const dx = e.touches[0].clientX - CANVAS.dragStartX;
    const dy = e.touches[0].clientY - CANVAS.dragStartY;
    CANVAS.offsetX = CANVAS.dragOffsetStartX + dx;
    CANVAS.offsetY = CANVAS.dragOffsetStartY + dy;
    renderCanvas();
});

canvas.addEventListener('touchend', function() { CANVAS.isDragging = false; });
canvas.style.cursor = 'grab';

function fetchCanvas() {
    fetch('api/get_canvas.php')
        .then(function(res) { if (!res.ok) throw new Error('Network error'); return res.json(); })
        .then(function(data) {
            CANVAS.pixels = data.pixels;
            renderCanvas();
        })
        .catch(function(err) { console.error('Canvas poll failed:', err); });
}

fetchCanvas();
CANVAS.pollingInterval = setInterval(fetchCanvas, 5000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
