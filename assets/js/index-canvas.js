(function() {
    var canvas = document.getElementById('pixel-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var CANVAS = {
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
        currentUserId: window.AppConfig && window.AppConfig.userId ? window.AppConfig.userId : null,
    };

    var BASE_URL = window.AppConfig && window.AppConfig.baseUrl ? window.AppConfig.baseUrl : '';

    function renderCanvas() {
        ctx.fillStyle = '#1a1a1a';
        ctx.fillRect(0, 0, 800, 800);

        var size = CANVAS.cellSize * CANVAS.zoom;
        var startX = CANVAS.offsetX % size;
        var startY = CANVAS.offsetY % size;

        ctx.save();
        ctx.beginPath();
        ctx.rect(0, 0, 800, 800);
        ctx.clip();

        var startCol = Math.max(0, Math.floor(-CANVAS.offsetX / size));
        var startRow = Math.max(0, Math.floor(-CANVAS.offsetY / size));
        var endCol = Math.min(CANVAS.gridSize, Math.ceil((800 - CANVAS.offsetX) / size));
        var endRow = Math.min(CANVAS.gridSize, Math.ceil((800 - CANVAS.offsetY) / size));

        var pixelMap = {};
        CANVAS.pixels.forEach(function(p) { pixelMap[p.x + ',' + p.y] = p; });

        for (var row = startRow; row < endRow; row++) {
            for (var col = startCol; col < endCol; col++) {
                var px = CANVAS.offsetX + col * size;
                var py = CANVAS.offsetY + row * size;

                if (CANVAS.myPixelsMode && CANVAS.currentUserId) {
                    var p = pixelMap[col + ',' + row];
                    if (p && p.owner_id === CANVAS.currentUserId) {
                        ctx.fillStyle = CANVAS.territoryMode ? (p.color || '#7c3aed') : p.color;
                    } else {
                        ctx.fillStyle = p ? CANVAS.territoryMode ? '#1a1a1a' : dimColor(p.color) : '#1a1a1a';
                    }
                } else if (CANVAS.territoryMode) {
                    var p2 = pixelMap[col + ',' + row];
                    ctx.fillStyle = p2 ? (p2.color || '#7c3aed') : '#1a1a1a';
                } else {
                    var p3 = pixelMap[col + ',' + row];
                    ctx.fillStyle = p3 ? p3.color : '#1a1a1a';
                }
                ctx.fillRect(px, py, size + 0.5, size + 0.5);
            }
        }

        ctx.restore();

        if (CANVAS.zoom >= 3) {
            ctx.strokeStyle = '#252535';
            ctx.lineWidth = 0.5;
            for (var col = startCol; col <= endCol; col++) {
                var x = CANVAS.offsetX + col * size;
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, 800);
                ctx.stroke();
            }
            for (var row = startRow; row <= endRow; row++) {
                var y = CANVAS.offsetY + row * size;
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(800, y);
                ctx.stroke();
            }
        }
    }

    function dimColor(hex) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);
        return 'rgb(' + Math.floor(r*0.5) + ',' + Math.floor(g*0.5) + ',' + Math.floor(b*0.5) + ')';
    }

    function getCellFromEvent(e) {
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);
        var size = CANVAS.cellSize * CANVAS.zoom;
        var col = Math.floor((mx - CANVAS.offsetX) / size);
        var row = Math.floor((my - CANVAS.offsetY) / size);
        if (col < 0 || col >= CANVAS.gridSize || row < 0 || row >= CANVAS.gridSize) return null;
        return { col: col, row: row };
    }

    canvas.addEventListener('wheel', function(e) {
        e.preventDefault();
        var rect = canvas.getBoundingClientRect();
        var mx = (e.clientX - rect.left) * (800 / rect.width);
        var my = (e.clientY - rect.top) * (800 / rect.height);

        var oldZoom = CANVAS.zoom;
        CANVAS.zoom = Math.max(1, Math.min(6, CANVAS.zoom - e.deltaY * 0.01));

        var fx = CANVAS.offsetX + mx;
        var fy = CANVAS.offsetY + my;
        var ratio = CANVAS.zoom / oldZoom;
        CANVAS.offsetX = mx - (mx - CANVAS.offsetX) * ratio;
        CANVAS.offsetY = my - (my - CANVAS.offsetY) * ratio;

        var zoomEl = document.getElementById('zoom-indicator');
        if (zoomEl) zoomEl.textContent = Math.round(CANVAS.zoom) + '\u00d7';
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
        var dx = e.clientX - CANVAS.dragStartX;
        var dy = e.clientY - CANVAS.dragStartY;
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
        var dx = e.touches[0].clientX - CANVAS.dragStartX;
        var dy = e.touches[0].clientY - CANVAS.dragStartY;
        CANVAS.offsetX = CANVAS.dragOffsetStartX + dx;
        CANVAS.offsetY = CANVAS.dragOffsetStartY + dy;
        renderCanvas();
    });

    canvas.addEventListener('touchend', function() { CANVAS.isDragging = false; });
    canvas.style.cursor = 'grab';

    function fetchCanvas() {
        fetch(BASE_URL + '/api/get_canvas.php')
            .then(function(res) { if (!res.ok) throw new Error('Network error'); return res.json(); })
            .then(function(data) {
                CANVAS.pixels = data.pixels;
                renderCanvas();
            })
            .catch(function(err) { console.error('Canvas poll failed:', err); });
    }

    var territoryBtn = document.getElementById('toggle-territory');
    var mypixelsBtn = document.getElementById('toggle-mypixels');

    if (territoryBtn) territoryBtn.addEventListener('click', function() {
        CANVAS.territoryMode = !CANVAS.territoryMode;
        this.textContent = CANVAS.territoryMode ? 'Art View' : 'Territory View';
        renderCanvas();
    });

    if (mypixelsBtn) mypixelsBtn.addEventListener('click', function() {
        CANVAS.myPixelsMode = !CANVAS.myPixelsMode;
        this.textContent = CANVAS.myPixelsMode ? 'Hide My Pixels' : 'My Pixels';
        renderCanvas();
    });

    window.CANVAS = CANVAS;
    window.CANVAS_fetchCanvas = fetchCanvas;

    fetchCanvas();
    if (window.CANVAS_pollingInterval) clearInterval(window.CANVAS_pollingInterval);
    CANVAS.pollingInterval = setInterval(fetchCanvas, 5000);
})();