(function() {
    var canvas = document.getElementById('pixel-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var CANVAS_STATE = {
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
        currentUserId: window.AppConfig && window.AppConfig.userId ? window.AppConfig.userId : null,
        selectedCell: null,
    };

    var BASE_URL = window.AppConfig && window.AppConfig.baseUrl ? window.AppConfig.baseUrl : '';
    var CSRF_TOKEN = window.AppConfig && window.AppConfig.csrfToken ? window.AppConfig.csrfToken : '';

    function renderCanvas() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 800, 800);

        var size = CANVAS_STATE.cellSize * CANVAS_STATE.zoom;
        var startX = CANVAS_STATE.offsetX % size;
        var startY = CANVAS_STATE.offsetY % size;

        ctx.save();
        ctx.beginPath();
        ctx.rect(0, 0, 800, 800);
        ctx.clip();

        var startCol = Math.max(0, Math.floor(-CANVAS_STATE.offsetX / size));
        var startRow = Math.max(0, Math.floor(-CANVAS_STATE.offsetY / size));
        var endCol = Math.min(CANVAS_STATE.gridSize, Math.ceil((800 - CANVAS_STATE.offsetX) / size));
        var endRow = Math.min(CANVAS_STATE.gridSize, Math.ceil((800 - CANVAS_STATE.offsetY) / size));

        var pixelMap = {};
        CANVAS_STATE.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = startRow; row < endRow; row++) {
            for (var col = startCol; col < endCol; col++) {
                var px = CANVAS_STATE.offsetX + col * size;
                var py = CANVAS_STATE.offsetY + row * size;
                var p = pixelMap[col + ',' + row];
                ctx.fillStyle = p ? p.color : '#1a1a1a';
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

        ctx.restore();
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
        var zoomEl = document.getElementById('zoom-indicator');
        if (zoomEl) zoomEl.textContent = Math.round(CANVAS_STATE.zoom) + '\u00d7';
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
        var panel = document.getElementById('pixel-panel');
        if (!panel) return;
        panel.style.display = 'block';
        var panelCoords = document.getElementById('panel-coords');
        var panelOwner = document.getElementById('panel-owner');
        var panelCost = document.getElementById('panel-cost');
        var placeBtn = document.getElementById('place-pixel-btn');
        var panelError = document.getElementById('panel-error');

        if (panelCoords) panelCoords.textContent = '(' + cell.col + ', ' + cell.row + ')';

        var pixelMap = {};
        CANVAS_STATE.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });
        var existing = pixelMap[cell.col + ',' + cell.row];

        if (existing) {
            if (existing.owner_id === CANVAS_STATE.currentUserId) {
                if (panelOwner) panelOwner.innerHTML = '<span style="color:var(--green);">Your pixel</span>';
                if (panelCost) panelCost.textContent = 'Free \u2014 your pixel';
                if (placeBtn) { placeBtn.disabled = false; placeBtn.textContent = 'Repaint Pixel'; }
            } else {
                if (panelOwner) panelOwner.innerHTML = 'Owned by <strong>' + (existing.username || 'Unknown') + '</strong>';
                if (panelCost) panelCost.textContent = 'Not claimable';
                if (placeBtn) { placeBtn.disabled = true; }
            }
        } else {
            if (panelOwner) panelOwner.textContent = 'Unclaimed';
            if (panelCost) panelCost.textContent = '5 \ud83d\udcb0';
            if (placeBtn) { placeBtn.disabled = false; placeBtn.textContent = 'Place Pixel'; }
        }
        if (panelError) panelError.textContent = '';
        renderCanvas();
    }

    var placeBtn = document.getElementById('place-pixel-btn');
    if (placeBtn) {
        placeBtn.addEventListener('click', function() {
            if (!CANVAS_STATE.selectedCell) return;
            placeBtn.disabled = true;
            placeBtn.innerHTML = '<span class="spinner"></span> Placing...';

            var cell = CANVAS_STATE.selectedCell;
            var formData = new URLSearchParams();
            formData.append('x', cell.col);
            formData.append('y', cell.row);
            formData.append('color', document.getElementById('pixel-color').value);
            formData.append('csrf_token', CSRF_TOKEN);

            fetch(BASE_URL + '/api/place_pixel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(result) {
                var panelError = document.getElementById('panel-error');
                if (result.ok && result.data.success) {
                    var pixelMap = {};
                    CANVAS_STATE.pixels.forEach(function(p) {
                        if (!(p.x === cell.col && p.y === cell.row)) pixelMap[p.x + ',' + p.y] = p;
                    });
                    CANVAS_STATE.pixels = Object.values(pixelMap);
                    CANVAS_STATE.pixels.push({
                        x: cell.col, y: cell.row,
                        color: document.getElementById('pixel-color').value,
                        owner_id: CANVAS_STATE.currentUserId,
                        username: window.AppConfig && window.AppConfig.username ? window.AppConfig.username : '',
                        level: null, placed_at: new Date().toISOString(), expires_at: null
                    });
                    if (panelError) panelError.textContent = '';
                    renderCanvas();
                    var placeBtnIn = document.getElementById('place-pixel-btn');
                    if (placeBtnIn) { placeBtnIn.disabled = false; placeBtnIn.textContent = 'Place Pixel'; }
                } else {
                    if (panelError) panelError.textContent = (result.data && result.data.error) || 'Failed to place pixel';
                    if (placeBtn) { placeBtn.disabled = false; placeBtn.textContent = 'Place Pixel'; }
                }
            })
            .catch(function() {
                var panelError = document.getElementById('panel-error');
                if (panelError) panelError.textContent = 'Network error. Try again.';
                if (placeBtn) { placeBtn.disabled = false; placeBtn.textContent = 'Place Pixel'; }
            });
        });
    }

    var territoryBtn = document.getElementById('toggle-territory');
    var mypixelsBtn = document.getElementById('toggle-mypixels');

    window.toggleCanvasTerritory = function() {
        CANVAS_STATE.territoryMode = !CANVAS_STATE.territoryMode;
        if (territoryBtn) territoryBtn.textContent = CANVAS_STATE.territoryMode ? 'Art View' : 'Territory View';
        renderCanvas();
    };

    window.toggleCanvasMyPixels = function() {
        CANVAS_STATE.myPixelsMode = !CANVAS_STATE.myPixelsMode;
        if (mypixelsBtn) mypixelsBtn.textContent = CANVAS_STATE.myPixelsMode ? 'Hide My Pixels' : 'My Pixels';
        renderCanvas();
    };

    if (territoryBtn) territoryBtn.addEventListener('click', window.toggleCanvasTerritory);
    if (mypixelsBtn) mypixelsBtn.addEventListener('click', window.toggleCanvasMyPixels);

    function fetchCanvas() {
        fetch(BASE_URL + '/api/get_canvas.php')
            .then(function(res) { if (!res.ok) throw new Error('Network error'); return res.json(); })
            .then(function(data) { CANVAS_STATE.pixels = data.pixels; renderCanvas(); })
            .catch(function(err) { console.error('Canvas poll failed:', err); });
    }

    window.CANVAS_STATE = CANVAS_STATE;
    window.CANVAS_fetchCanvas = fetchCanvas;

    fetchCanvas();
    if (window.CANVAS_pollingInterval) clearInterval(window.CANVAS_pollingInterval);
    window.CANVAS_pollingInterval = setInterval(fetchCanvas, 5000);
})();