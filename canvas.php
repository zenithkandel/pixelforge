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
(function() {
    const CANVAS_STATE = {
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
        currentUserId: <?= (int)$_SESSION['user_id'] ?>,
        selectedCell: null,
    };

    const canvas = document.getElementById('pixel-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const panel = document.getElementById('pixel-panel');
    const colorInput = document.getElementById('pixel-color');
    const colorHexInput = document.getElementById('pixel-color-hex');
    const panelCoords = document.getElementById('panel-coords');
    const panelOwner = document.getElementById('panel-owner');
    const panelCost = document.getElementById('panel-cost');
    const panelError = document.getElementById('panel-error');
    const placeBtn = document.getElementById('place-pixel-btn');

    colorInput.addEventListener('input', function() { colorHexInput.value = this.value; });
    colorHexInput.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value;
    });

    function renderCanvas() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 800, 800);

        const size = CANVAS_STATE.cellSize * CANVAS_STATE.zoom;
        const startCol = Math.max(0, Math.floor(-CANVAS_STATE.offsetX / size));
        const startRow = Math.max(0, Math.floor(-CANVAS_STATE.offsetY / size));
        const endCol = Math.min(CANVAS_STATE.gridSize, Math.ceil((800 - CANVAS_STATE.offsetX) / size));
        const endRow = Math.min(CANVAS_STATE.gridSize, Math.ceil((800 - CANVAS_STATE.offsetY) / size));

        const pixelMap = {};
        CANVAS_STATE.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = startRow; row < endRow; row++) {
            for (var col = startCol; col < endCol; col++) {
                var px = CANVAS_STATE.offsetX + col * size;
                var py = CANVAS_STATE.offsetY + row * size;
                var p = pixelMap[col + ',' + row];

                if (CANVAS_STATE.myPixelsMode) {
                    if (p && p.owner_id === CANVAS_STATE.currentUserId) {
                        ctx.fillStyle = p.color;
                    } else {
                        ctx.fillStyle = p ? 'rgba(26,26,26,0.7)' : '#1a1a1a';
                    }
                } else if (CANVAS_STATE.territoryMode) {
                    ctx.fillStyle = p ? (p.color || '#7c3aed') : '#1a1a1a';
                } else {
                    ctx.fillStyle = p ? p.color : '#1a1a1a';
                }
                ctx.fillRect(px, py, size + 0.5, size + 0.5);
            }
        }

        if (CANVAS_STATE.selectedCell) {
            var sx = CANVAS_STATE.offsetX + CANVAS_STATE.selectedCell.col * size;
            var sy = CANVAS_STATE.offsetY + CANVAS_STATE.selectedCell.row * size;
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.strokeRect(sx, sy, size, size);
        }

        if (CANVAS_STATE.zoom >= 3) {
            ctx.strokeStyle = '#252535';
            ctx.lineWidth = 0.5;
            for (var gx = startCol; gx <= endCol; gx++) {
                var x = CANVAS_STATE.offsetX + gx * size;
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, 800);
                ctx.stroke();
            }
            for (var gy = startRow; gy <= endRow; gy++) {
                var y = CANVAS_STATE.offsetY + gy * size;
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(800, y);
                ctx.stroke();
            }
        }
    }

    function getCell(e) {
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);
        var size = CANVAS_STATE.cellSize * CANVAS_STATE.zoom;
        var col = Math.floor((mx - CANVAS_STATE.offsetX) / size);
        var row = Math.floor((my - CANVAS_STATE.offsetY) / size);
        if (col < 0 || col >= CANVAS_STATE.gridSize || row < 0 || row >= CANVAS_STATE.gridSize) return null;
        return { col: col, row: row };
    }

    canvas.addEventListener('wheel', function(e) {
        e.preventDefault();
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);
        var oldZoom = CANVAS_STATE.zoom;
        CANVAS_STATE.zoom = Math.max(1, Math.min(6, CANVAS_STATE.zoom - e.deltaY * 0.01));
        CANVAS_STATE.offsetX = mx - (mx - CANVAS_STATE.offsetX) * CANVAS_STATE.zoom / oldZoom;
        CANVAS_STATE.offsetY = my - (my - CANVAS_STATE.offsetY) * CANVAS_STATE.zoom / oldZoom;
        document.getElementById('zoom-indicator').textContent = Math.round(CANVAS_STATE.zoom) + '×';
        renderCanvas();
    });

    var dragMoved = false;

    canvas.addEventListener('mousedown', function(e) {
        CANVAS_STATE.isDragging = true;
        dragMoved = false;
        CANVAS_STATE.dragStartX = e.clientX;
        CANVAS_STATE.dragStartY = e.clientY;
        CANVAS_STATE.dragOffsetStartX = CANVAS_STATE.offsetX;
        CANVAS_STATE.dragOffsetStartY = CANVAS_STATE.offsetY;
        canvas.style.cursor = 'grabbing';
    });

    window.addEventListener('mousemove', function(e) {
        if (!CANVAS_STATE.isDragging) return;
        var dx = e.clientX - CANVAS_STATE.dragStartX;
        var dy = e.clientY - CANVAS_STATE.dragStartY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;
        CANVAS_STATE.offsetX = CANVAS_STATE.dragOffsetStartX + dx;
        CANVAS_STATE.offsetY = CANVAS_STATE.dragOffsetStartY + dy;
        renderCanvas();
    });

    canvas.addEventListener('mouseup', function(e) {
        CANVAS_STATE.isDragging = false;
        canvas.style.cursor = 'grab';
        if (dragMoved) return;
        var cell = getCell(e);
        if (!cell) return;
        selectCell(cell);
    });

    canvas.addEventListener('touchstart', function(e) {
        if (e.touches.length === 1) {
            CANVAS_STATE.isDragging = true;
            dragMoved = false;
            CANVAS_STATE.dragStartX = e.touches[0].clientX;
            CANVAS_STATE.dragStartY = e.touches[0].clientY;
            CANVAS_STATE.dragOffsetStartX = CANVAS_STATE.offsetX;
            CANVAS_STATE.dragOffsetStartY = CANVAS_STATE.offsetY;
        }
    });

    canvas.addEventListener('touchmove', function(e) {
        if (!CANVAS_STATE.isDragging || e.touches.length !== 1) return;
        e.preventDefault();
        var dx = e.touches[0].clientX - CANVAS_STATE.dragStartX;
        var dy = e.touches[0].clientY - CANVAS_STATE.dragStartY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) dragMoved = true;
        CANVAS_STATE.offsetX = CANVAS_STATE.dragOffsetStartX + dx;
        CANVAS_STATE.offsetY = CANVAS_STATE.dragOffsetStartY + dy;
        renderCanvas();
    });

    canvas.addEventListener('touchend', function(e) {
        CANVAS_STATE.isDragging = false;
        if (dragMoved) return;
        var cell = getCell({ clientX: CANVAS_STATE.dragStartX, clientY: CANVAS_STATE.dragStartY });
        if (!cell) return;
        selectCell(cell);
    });

    canvas.style.cursor = 'grab';

    function selectCell(cell) {
        CANVAS_STATE.selectedCell = cell;
        panel.style.display = 'block';
        panelCoords.textContent = '(' + cell.col + ', ' + cell.row + ')';

        var pixelMap = {};
        CANVAS_STATE.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });
        var existing = pixelMap[cell.col + ',' + cell.row];

        if (existing) {
            if (existing.owner_id === CANVAS_STATE.currentUserId) {
                panelOwner.innerHTML = '<span style="color:var(--green);">Your pixel</span>';
                panelCost.textContent = 'Free — your pixel';
                placeBtn.disabled = false;
                placeBtn.textContent = 'Repaint Pixel';
            } else {
                panelOwner.innerHTML = 'Owned by <strong>' + (existing.username || 'Unknown') + '</strong>';
                panelCost.textContent = 'Not claimable';
                placeBtn.disabled = true;
            }
        } else {
            panelOwner.textContent = 'Unclaimed';
            panelCost.textContent = '5 💰';
            placeBtn.disabled = false;
            placeBtn.textContent = 'Place Pixel';
        }
        panelError.textContent = '';
        renderCanvas();
    }

    placeBtn.addEventListener('click', function() {
        if (!CANVAS_STATE.selectedCell) return;
        placeBtn.disabled = true;
        placeBtn.innerHTML = '<span class="spinner"></span> Placing...';

        var cell = CANVAS_STATE.selectedCell;
        var formData = new URLSearchParams();
        formData.append('x', cell.col);
        formData.append('y', cell.row);
        formData.append('color', colorInput.value);
        formData.append('csrf_token', '<?= csrf_token() ?>');

        fetch('<?= BASE_URL ?>/api/place_pixel.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(result) {
                if (result.ok && result.data.success) {
                    var pixelMap = {};
                    CANVAS_STATE.pixels.forEach(function(p, i) {
                        if (p.x === cell.col && p.y === cell.row) return;
                        pixelMap[p.x + ',' + p.y] = p;
                    });
                    CANVAS_STATE.pixels = Object.values(pixelMap);
                    CANVAS_STATE.pixels.push({
                        x: cell.col, y: cell.row, color: colorInput.value,
                        owner_id: CANVAS_STATE.currentUserId, username: '<?= htmlspecialchars($_SESSION['username']) ?>' ,
                        level: null, placed_at: new Date().toISOString(), expires_at: null
                    });
                    panelError.textContent = '';
                    renderCanvas();
                } else {
                    panelError.textContent = (result.data && result.data.error) || 'Failed to place pixel';
                    placeBtn.disabled = false;
                    placeBtn.textContent = 'Place Pixel';
                }
            })
            .catch(function() {
                panelError.textContent = 'Network error. Try again.';
                placeBtn.disabled = false;
                placeBtn.textContent = 'Place Pixel';
            });
    });

    document.getElementById('toggle-territory').addEventListener('click', function() {
        CANVAS_STATE.territoryMode = !CANVAS_STATE.territoryMode;
        this.textContent = CANVAS_STATE.territoryMode ? 'Art View' : 'Territory View';
        renderCanvas();
    });

    document.getElementById('toggle-mypixels').addEventListener('click', function() {
        CANVAS_STATE.myPixelsMode = !CANVAS_STATE.myPixelsMode;
        this.textContent = CANVAS_STATE.myPixelsMode ? 'Hide My Pixels' : 'My Pixels';
        renderCanvas();
    });

    function fetchCanvas() {
        fetch('<?= BASE_URL ?>/api/get_canvas.php')
            .then(function(res) { if (!res.ok) throw new Error('Network error'); return res.json(); })
            .then(function(data) { CANVAS_STATE.pixels = data.pixels; renderCanvas(); })
            .catch(function(err) { console.error('Poll failed:', err); });
    }

    fetchCanvas();
    CANVAS_STATE.pollingInterval = setInterval(fetchCanvas, 5000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
