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
