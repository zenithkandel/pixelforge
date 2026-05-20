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

<div class="page-content" style="padding:40px;">
    <div class="page-header" style="text-align:center; margin-bottom:40px;">
        <h1>Interactive Canvas</h1>
        <p>Click to paint directly on the canvas. Cost: <span class="currency">5 💰</span> for unclaimed cells.</p>
    </div>

    <div style="display:flex;gap:24px;flex-wrap:wrap;justify-content:center;align-items:flex-start;">
        <div id="canvas-container" style="position:relative;border-radius:18px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.8);cursor:crosshair;">
            <canvas id="pixel-canvas" width="800" height="800" style="display:block;"></canvas>
            <div id="canvas-status" style="position:absolute;top:12px;right:12px;display:flex;align-items:center;gap:6px;font-size:12px;background:rgba(0,0,0,0.6);padding:6px 10px;border-radius:999px;backdrop-filter:blur(8px);">
                <span style="width:8px;height:8px;border-radius:50%;background:#22c55e;animation:pulse-dot 2s ease infinite;"></span> <span style="color:#fff;">Live</span>
            </div>
            <div id="zoom-indicator" style="position:absolute;bottom:12px;left:12px;font-size:12px;background:rgba(0,0,0,0.6);padding:6px 10px;border-radius:999px;backdrop-filter:blur(8px);color:#fff;">1x</div>
        </div>

        <div class="card" style="width:200px;align-self:flex-start;position:sticky;top:80px;background:#1a1a2e;border:1px solid rgba(255,255,255,0.06);border-radius:18px;padding:24px;">
            <h3 style="margin:0 0 16px;font-size:16px;font-weight:600;color:#f0f0ff;">Paint Tools</h3>

            <div style="margin-bottom:16px;">
                <label style="font-weight:500;display:block;margin-bottom:8px;color:#9090b0;">Current Color</label>
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">
                    <input type="color" id="paint-color" value="#7c3aed" style="width:48px;height:40px;padding:2px;border-radius:6px;cursor:pointer;border:1px solid rgba(255,255,255,0.1);background:#12121f;">
                    <input type="text" id="paint-color-hex" value="#7c3aed" style="flex:1;font-family:monospace;font-size:13px;margin:0;background:#12121f;border:1px solid rgba(255,255,255,0.1);color:#f0f0ff;">
                </div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <button class="color-preset" data-color="#7c3aed" style="width:24px;height:24px;background:#7c3aed;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#db2777" style="width:24px;height:24px;background:#db2777;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#0891b2" style="width:24px;height:24px;background:#0891b2;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#059669" style="width:24px;height:24px;background:#059669;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#d97706" style="width:24px;height:24px;background:#d97706;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#dc2626" style="width:24px;height:24px;background:#dc2626;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#4f46e5" style="width:24px;height:24px;background:#4f46e5;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#0d9488" style="width:24px;height:24px;background:#0d9488;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#ffffff" style="width:24px;height:24px;background:#ffffff;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                    <button class="color-preset" data-color="#1a1a1a" style="width:24px;height:24px;background:#1a1a1a;border:none;border-radius:4px;cursor:pointer;border:2px solid transparent;"></button>
                </div>
            </div>

            <div style="padding:8px;background:#161625;border-radius:12px;margin-top:16px;">
                <div style="font-size:13px;color:#50506a;margin-bottom:4px;">Balance</div>
                <div id="paint-balance" class="currency" style="font-size:18px;color:#fbbf24;"></div>
            </div>

            <div style="margin-top:16px;">
                <button id="toggle-territory" class="btn-secondary btn-sm" style="width:100%;margin-bottom:8px;background:transparent;border:1px solid rgba(255,255,255,0.1);color:#f0f0ff;padding:7px 14px;border-radius:999px;font-weight:500;cursor:pointer;">Territory View</button>
                <button id="toggle-mypixels" class="btn-secondary btn-sm" style="width:100%;background:transparent;border:1px solid rgba(255,255,255,0.1);color:#f0f0ff;padding:7px 14px;border-radius:999px;font-weight:500;cursor:pointer;">My Pixels</button>
            </div>
        </div>
    </div>

    <div id="paint-toast" style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#161625;border:1px solid #7c3aed;border-radius:18px;padding:12px 20px;box-shadow:0 8px 40px rgba(0,0,0,0.8);z-index:9999;display:none;align-items:center;gap:10px;color:#f0f0ff;">
        <span id="paint-toast-icon">🎨</span>
        <span id="paint-toast-text" style="font-size:14px;"></span>
    </div>
</div>

<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
(function() {
    const CANVAS_STATE = {
        pollingInterval: null,
        pixels: [],
        pendingPixels: new Set(),
        lastPaintTime: 0,
        paintCooldown: 250,
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
    };

    const colorInput = document.getElementById('paint-color');
    const colorHexInput = document.getElementById('paint-color-hex');
    const balanceDisplay = document.getElementById('paint-balance');
    const paintToast = document.getElementById('paint-toast');
    const toastIcon = document.getElementById('paint-toast-icon');
    const toastText = document.getElementById('paint-toast-text');

    var navCurrency = document.querySelector('nav .currency');
    if (navCurrency) balanceDisplay.textContent = navCurrency.textContent;

    colorInput.addEventListener('input', function() { colorHexInput.value = this.value; });
    colorHexInput.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) colorInput.value = this.value;
    });

    document.querySelectorAll('.color-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var c = this.dataset.color;
            colorInput.value = c;
            colorHexInput.value = c;
            document.querySelectorAll('.color-preset').forEach(function(b) { b.style.border = '2px solid transparent'; });
            this.style.border = '2px solid #fff';
        });
    });
    document.querySelector('.color-preset').style.border = '2px solid #fff';

    function showToast(msg, icon, duration) {
        toastText.textContent = msg;
        toastIcon.textContent = icon || '🎨';
        paintToast.style.display = 'flex';
        paintToast.style.animation = 'toastIn 0.3s ease';
        setTimeout(function() {
            paintToast.style.animation = 'toastOut 0.3s ease';
            setTimeout(function() { paintToast.style.display = 'none'; }, 300);
        }, duration || 2000);
    }

    function updateCurrencyDisplay(balance) {
        if (balance === null || balance === undefined) return;
        balanceDisplay.textContent = Math.round(balance).toLocaleString() + ' 💰';
        var navCurr = document.querySelectorAll('nav .currency');
        navCurr.forEach(function(el) { el.textContent = Math.round(balance).toLocaleString() + ' 💰'; });
    }

    function paintPixel(col, row) {
        var key = col + ',' + row;
        if (CANVAS_STATE.pendingPixels.has(key)) return;

        var now = Date.now();
        if (now - CANVAS_STATE.lastPaintTime < CANVAS_STATE.paintCooldown) return;
        CANVAS_STATE.lastPaintTime = now;

        var color = colorInput.value;
        var pixelMap = {};
        CANVAS_STATE.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });
        var existing = pixelMap[col + ',' + row];
        var isOwn = existing && existing.owner_id === CANVAS_STATE.currentUserId;

        CANVAS_STATE.pendingPixels.add(key);

        var localPixel = {
            x: col, y: row, color: color,
            owner_id: CANVAS_STATE.currentUserId,
            username: '<?= htmlspecialchars($_SESSION['username']) ?>',
            level: null, placed_at: new Date().toISOString(), expires_at: null
        };

        if (existing) {
            for (var i = 0; i < CANVAS_STATE.pixels.length; i++) {
                if (CANVAS_STATE.pixels[i].x === col && CANVAS_STATE.pixels[i].y === row) {
                    CANVAS_STATE.pixels[i] = localPixel;
                    break;
                }
            }
        } else {
            CANVAS_STATE.pixels.push(localPixel);
        }
        renderCanvas();

        var formData = new URLSearchParams();
        formData.append('x', col);
        formData.append('y', row);
        formData.append('color', color);
        formData.append('csrf_token', '<?= csrf_token() ?>');

        fetch('api/place_pixel.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                CANVAS_STATE.pendingPixels.delete(key);
                if (data.success) {
                    if (data.new_balance !== undefined) updateCurrencyDisplay(data.new_balance);
                    showToast('Painted!', '✅', 1200);
                } else {
                    showToast(data.error || 'Cannot place', '❌', 2000);
                    if (!isOwn) {
                        for (var i = 0; i < CANVAS_STATE.pixels.length; i++) {
                            if (CANVAS_STATE.pixels[i].x === col && CANVAS_STATE.pixels[i].y === row && CANVAS_STATE.pixels[i].owner_id === CANVAS_STATE.currentUserId) {
                                if (existing) {
                                    CANVAS_STATE.pixels[i] = existing;
                                } else {
                                    CANVAS_STATE.pixels.splice(i, 1);
                                }
                                break;
                            }
                        }
                        renderCanvas();
                    }
                }
            })
            .catch(function() {
                CANVAS_STATE.pendingPixels.delete(key);
                showToast('Network error', '❌', 2000);
            });
    }

    const canvas = document.getElementById('pixel-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

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

        if (CANVAS_STATE.zoom >= 3) {
            ctx.strokeStyle = '#252535';
            ctx.lineWidth = 0.5;
            for (var gx = startCol; gx <= endCol; gx++) {
                var x = CANVAS_STATE.offsetX + gx * size;
                ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, 800); ctx.stroke();
            }
            for (var gy = startRow; gy <= endRow; gy++) {
                var y = CANVAS_STATE.offsetY + gy * size;
                ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(800, y); ctx.stroke();
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

    var dragMoved = false;

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
        canvas.style.cursor = 'crosshair';
        if (dragMoved) return;
        var cell = getCell(e);
        if (!cell) return;
        paintPixel(cell.col, cell.row);
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
        paintPixel(cell.col, cell.row);
    });

    canvas.style.cursor = 'crosshair';

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
        fetch('api/get_canvas.php')
            .then(function(res) { if (!res.ok) throw new Error('Network error'); return res.json(); })
            .then(function(data) { CANVAS_STATE.pixels = data.pixels; renderCanvas(); })
            .catch(function(err) { console.error('Poll failed:', err); });
    }

    fetchCanvas();
    CANVAS_STATE.pollingInterval = setInterval(fetchCanvas, 5000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
