let canvas, ctx, minimap, minimapCtx;
let pixels = {};
let zoom = 1;
let panX = 0, panY = 0;
let isDragging = false;
let dragStart = { x: 0, y: 0 };
let isPanning = false;
let selectedPixel = null;
let canDraw = false;
let isAdmin = false;
let myPixels = [];
let showMyPixels = false;

const GRID_SIZE = 100;
const CELL_SIZE = 8;
const CANVAS_SIZE = GRID_SIZE * CELL_SIZE;

function initCanvas(drawMode, adminMode = false) {
    canvas = document.getElementById('canvas');
    ctx = canvas.getContext('2d');
    minimap = document.getElementById('minimap');
    minimapCtx = minimap.getContext('2d');

    canDraw = drawMode;
    isAdmin = adminMode;

    resizeCanvas();
    setupEvents();
    loadPixels();
    startPolling();
}

function resizeCanvas() {
    canvas.width = CANVAS_SIZE;
    canvas.height = CANVAS_SIZE;
    render();
}

function setupEvents() {
    canvas.addEventListener('wheel', handleZoom);
    canvas.addEventListener('mousedown', handleMouseDown);
    canvas.addEventListener('mousemove', handleMouseMove);
    canvas.addEventListener('mouseup', handleMouseUp);
    canvas.addEventListener('click', handleClick);

    if (minimap) {
        minimap.addEventListener('click', handleMinimapClick);
    }

    document.getElementById('territory-toggle')?.addEventListener('click', toggleTerritory);
    document.getElementById('my-pixels-btn')?.addEventListener('click', toggleMyPixels);
    document.getElementById('close-panel')?.addEventListener('click', closePixelPanel);
    document.getElementById('place-pixel-btn')?.addEventListener('click', placePixel);
    document.getElementById('pixel-color')?.addEventListener('input', syncColorInput);
    document.getElementById('pixel-color-hex')?.addEventListener('input', syncColorInput);
}

function handleZoom(e) {
    e.preventDefault();
    const rect = canvas.getBoundingClientRect();
    const mouseX = (e.clientX - rect.left) / zoom - panX / zoom;
    const mouseY = (e.clientY - rect.top) / zoom - panY / zoom;

    const delta = e.deltaY > 0 ? 0.9 : 1.1;
    zoom = Math.max(1, Math.min(6, zoom * delta));

    const newPanX = mouseX * zoom - (e.clientX - rect.left);
    const newPanY = mouseY * zoom - (e.clientY - rect.top);

    panX = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), newPanX));
    panY = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), newPanY));

    updateZoomLevel();
    updateMinimap();
    render();
}

function handleMouseDown(e) {
    if (e.button === 0) {
        isDragging = true;
        dragStart = { x: e.clientX - panX, y: e.clientY - panY };
    }
}

function handleMouseMove(e) {
    if (isDragging && zoom > 1) {
        panX = e.clientX - dragStart.x;
        panY = e.clientY - dragStart.y;

        panX = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), panX));
        panY = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), panY));

        updateMinimap();
        render();
    }

    showTooltip(e);
}

function handleMouseUp() {
    isDragging = false;
}

function handleClick(e) {
    if (canDraw) {
        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        const x = Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE);
        const y = Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE);

        if (x >= 0 && x < GRID_SIZE && y >= 0 && y < GRID_SIZE) {
            selectPixel(x, y);
        }
    } else if (isAdmin) {
        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        const x = Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE);
        const y = Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE);

        if (x >= 0 && x < GRID_SIZE && y >= 0 && y < GRID_SIZE) {
            handlePixelClick(x, y);
        }
    }
}

function handleMinimapClick(e) {
    const rect = minimap.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    panX = -(x * 2 - canvas.clientWidth / 2 / zoom) * CELL_SIZE;
    panY = -(y * 2 - canvas.clientHeight / 2 / zoom) * CELL_SIZE;

    panX = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), panX));
    panY = Math.min(0, Math.max(-CANVAS_SIZE * (zoom - 1), panY));

    updateMinimap();
    render();
}

function selectPixel(x, y) {
    selectedPixel = { x, y };
    const pixel = pixels[`${x},${y}`];

    document.getElementById('pixel-coord').textContent = `(${x}, ${y})`;

    const ownerEl = document.getElementById('pixel-owner');
    const costEl = document.getElementById('pixel-cost');
    const warningEl = document.getElementById('decay-warning');

    if (pixel) {
        ownerEl.textContent = pixel.username ? `${pixel.username} · Lv.${pixel.owner_level}` : 'Unclaimed';

        if (pixel.username && pixel.days_left !== null && pixel.days_left <= 3) {
            warningEl.classList.remove('hidden');
            document.getElementById('decay-text').textContent = `Expires in ${pixel.days_left} days`;
        } else {
            warningEl.classList.add('hidden');
        }

        const isOwn = pixel.owner_id === parseInt(document.getElementById('user-id')?.value || '0');
        costEl.textContent = isOwn ? 'Free — your pixel' : '5 currency';
    } else {
        ownerEl.textContent = 'Unclaimed';
        costEl.textContent = '5 currency';
        warningEl.classList.add('hidden');
    }

    document.getElementById('pixel-panel').classList.remove('hidden');
}

function placePixel() {
    const x = selectedPixel.x;
    const y = selectedPixel.y;
    const color = document.getElementById('pixel-color').value;
    const csrf = document.getElementById('csrf-token').value;
    const balance = parseInt(document.getElementById('user-balance').value);

    const pixel = pixels[`${x},${y}`];
    const isOwn = pixel && pixel.owner_id === parseInt(document.getElementById('user-id').value || '0');

    if (!isOwn && balance < 5) {
        showError('Insufficient balance');
        return;
    }

    fetch(APP_URL + '/api/place_pixel.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `x=${x}&y=${y}&color=${encodeURIComponent(color)}&csrf_token=${encodeURIComponent(csrf)}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            pixels[`${x},${y}`] = {
                x, y, color,
                owner_id: parseInt(document.getElementById('user-id').value),
                username: document.querySelector('.username')?.textContent || 'You',
                owner_level: parseInt(document.getElementById('user-level').value || '1'),
                placed_at: new Date().toISOString(),
                opacity: 1,
                days_left: 14
            };
            document.getElementById('user-balance').value = data.new_balance;
            document.querySelector('.balance').textContent = `💰${data.new_balance}`;

            if (data.new_achievements?.length) {
                window.showAchievements(data.new_achievements);
            }
            if (data.level_up) {
                window.showToast(`Level up! You're now level ${data.new_level}`, 'success');
            }

            render();
            closePixelPanel();
        } else {
            showError(data.error);
        }
    })
    .catch(() => showError('Failed to place pixel'));
}

function showError(msg) {
    const el = document.getElementById('pixel-error');
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

function closePixelPanel() {
    document.getElementById('pixel-panel').classList.add('hidden');
    selectedPixel = null;
}

function syncColorInput(e) {
    const colorInput = document.getElementById('pixel-color');
    const hexInput = document.getElementById('pixel-color-hex');

    if (e.target === colorInput) {
        hexInput.value = e.target.value;
    } else {
        if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
            colorInput.value = e.target.value;
        }
    }
}

function loadPixels() {
    fetch(APP_URL + '/api/get_canvas.php')
        .then(r => r.json())
        .then(data => {
            pixels = {};
            data.pixels.forEach(p => {
                pixels[`${p.x},${p.y}`] = p;
            });
            if (canDraw) {
                const userId = document.getElementById('user-id')?.value;
                myPixels = data.pixels.filter(p => p.owner_id == userId).map(p => `${p.x},${p.y}`);
            }
            render();
            updateMinimap();
        });
}

function startPolling() {
    setInterval(loadPixels, 5000);
}

function render() {
    ctx.save();
    ctx.fillStyle = '#1a1a1a';
    ctx.fillRect(0, 0, CANVAS_SIZE, CANVAS_SIZE);

    for (let x = 0; x < GRID_SIZE; x++) {
        for (let y = 0; y < GRID_SIZE; y++) {
            if (x % 10 === 0 || y % 10 === 0) {
                ctx.fillStyle = '#2a2a2a';
                ctx.fillRect(x * CELL_SIZE, y * CELL_SIZE, CELL_SIZE, CELL_SIZE);
            }
        }
    }

    const territoryMode = window.territoryMode || false;

    Object.values(pixels).forEach(p => {
        let color;
        if (territoryMode && p.owner_id) {
            color = p.username ? getAvatarColor(p.username) : '#333';
        } else {
            color = p.color;
        }

        ctx.globalAlpha = p.opacity || 1;
        ctx.fillStyle = color;
        ctx.fillRect(p.x * CELL_SIZE, p.y * CELL_SIZE, CELL_SIZE, CELL_SIZE);

        if (showMyPixels && myPixels.includes(`${p.x},${p.y}`)) {
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 2;
            ctx.strokeRect(p.x * CELL_SIZE, p.y * CELL_SIZE, CELL_SIZE, CELL_SIZE);
        }
    });

    ctx.globalAlpha = 1;

    ctx.translate(panX, panY);
    ctx.scale(zoom, zoom);

    ctx.restore();
}

function getAvatarColor(username) {
    const colors = ['#7c3aed', '#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#ec4899', '#06b6d4', '#8b5cf6', '#f97316', '#14b8a6'];
    let hash = 0;
    for (let i = 0; i < username.length; i++) {
        hash = username.charCodeAt(i) + ((hash << 5) - hash);
    }
    return colors[Math.abs(hash) % colors.length];
}

function updateZoomLevel() {
    document.getElementById('zoom-level').textContent = `${zoom}×`;
}

function updateMinimap() {
    if (!minimap) return;

    minimapCtx.fillStyle = '#1a1a1a';
    minimapCtx.fillRect(0, 0, 100, 100);

    const viewportW = (canvas.clientWidth / zoom) / CELL_SIZE;
    const viewportH = (canvas.clientHeight / zoom) / CELL_SIZE;
    const viewportX = -panX / zoom / CELL_SIZE;
    const viewportY = -panY / zoom / CELL_SIZE;

    minimapCtx.fillStyle = '#7c3aed';
    Object.values(pixels).forEach(p => {
        minimapCtx.fillRect(p.x, p.y, 1, 1);
    });

    const vp = document.getElementById('minimap-viewport');
    vp.style.left = `${viewportX}px`;
    vp.style.top = `${viewportY}px`;
    vp.style.width = `${viewportW}px`;
    vp.style.height = `${viewportH}px`;
}

function showTooltip(e) {
    const rect = canvas.getBoundingClientRect();
    const x = Math.floor((e.clientX - rect.left - panX) / zoom / CELL_SIZE);
    const y = Math.floor((e.clientY - rect.top - panY) / zoom / CELL_SIZE);

    if (x < 0 || x >= GRID_SIZE || y < 0 || y >= GRID_SIZE) return;

    const pixel = pixels[`${x},${y}`];
    let text;

    if (pixel) {
        text = pixel.username ? `Owned by ${pixel.username} · Lv.${pixel.owner_level}` : 'Unclaimed';
    } else {
        text = 'Unclaimed — 5 currency to claim';
    }

    if (pixel && pixel.days_left !== null && pixel.days_left <= 3) {
        text += ` ⚠ Expires in ${pixel.days_left} days`;
    }
}

function toggleMyPixels() {
    showMyPixels = !showMyPixels;
    document.getElementById('my-pixels-btn').classList.toggle('active', showMyPixels);
    render();
}

function handlePixelClick(x, y) {
    const pixel = pixels[`${x},${y}`];
    if (pixel && confirm(`Delete pixel at (${x}, ${y})?`)) {
        const csrf = document.getElementById('csrf-token').value;
        fetch(APP_URL + '/api/admin_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=erase_pixel&x=${x}&y=${y}&csrf_token=${encodeURIComponent(csrf)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                delete pixels[`${x},${y}`];
                render();
                updateMinimap();
            }
        });
    }
}