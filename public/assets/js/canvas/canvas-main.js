import { GridRenderer } from './grid-renderer.js';
import { ChunkCache } from './chunk-cache.js';
import { SSEClient } from './sse-client.js';
import { PixelBuyer } from './pixel-buyer.js';
import { MiniMap } from './mini-map.js';

const GRID_SIZE = 800;
const CHUNK_SIZE = 64;
const CHUNKS_PER_ROW = GRID_SIZE / CHUNK_SIZE;

const COLOR_PALETTE = [
    '#000000', '#111111', '#333333', '#555555', '#777777', '#999999', '#BBBBBB', '#FFFFFF',
    '#FF0000', '#FF6600', '#FFCC00', '#00CC00', '#0066FF', '#6600CC', '#FF00FF', '#00FFCC',
    '#FFB3B3', '#FFD9B3', '#FFFFB3', '#B3FFB3', '#B3D9FF', '#D9B3FF', '#FFB3FF', '#B3FFFF',
    '#660000', '#663300', '#666600', '#006600', '#003366', '#330066', '#660066', '#006666'
];

const state = {
    viewX: 0,
    viewY: 0,
    zoom: 4,
    selectedColor: '#000000',
    userBalance: parseInt(document.getElementById('userBalance').textContent) || 0,
    isDragging: false,
    dragStart: { x: 0, y: 0 },
    viewStart: { x: 0, y: 0 },
    purchaseMode: false,
    isLoggedIn: !!document.querySelector('.sidebar-footer .user-tag')?.textContent?.includes('@'),
    pendingPixels: new Map(),
    lastMousePos: { x: 0, y: 0 }
};

const viewport = document.getElementById('canvasViewport');
const gridCanvas = document.getElementById('gridCanvas');
const overlayCanvas = document.getElementById('overlayCanvas');
const gridCtx = gridCanvas.getContext('2d');
const overlayCtx = overlayCanvas.getContext('2d');

const chunkCache = new ChunkCache(200, 30000);
const gridRenderer = new GridRenderer(gridCtx, CHUNK_SIZE);
const sseClient = new SSEClient();
const pixelBuyer = new PixelBuyer();
const miniMap = new MiniMap();

function initColorPalette() {
    const palette = document.getElementById('colorPalette');
    COLOR_PALETTE.forEach(color => {
        const swatch = document.createElement('div');
        swatch.className = 'color-swatch' + (color === state.selectedColor ? ' selected' : '');
        swatch.style.background = color;
        swatch.dataset.color = color;
        swatch.addEventListener('click', () => selectColor(color));
        palette.appendChild(swatch);
    });
}

function selectColor(color) {
    state.selectedColor = color;
    document.querySelectorAll('.color-swatch').forEach(s => {
        s.classList.toggle('selected', s.dataset.color === color);
    });
    document.getElementById('colorPreview').style.background = color;
    document.getElementById('customColor').value = color;
}

function updateCanvasSize() {
    const rect = viewport.getBoundingClientRect();
    const width = Math.floor(rect.width);
    const height = Math.floor(rect.height);

    gridCanvas.width = width;
    gridCanvas.height = height;
    overlayCanvas.width = width;
    overlayCanvas.height = height;

    render();
}

function render() {
    const viewportWidth = gridCanvas.width;
    const viewportHeight = gridCanvas.height;

    gridCtx.fillStyle = '#FFFFFF';
    gridCtx.fillRect(0, 0, viewportWidth, viewportHeight);

    const visibleMinX = state.viewX;
    const visibleMinY = state.viewY;
    const visibleMaxX = visibleMinX + (viewportWidth / state.zoom);
    const visibleMaxY = visibleMinY + (viewportHeight / state.zoom);

    const startChunkX = Math.floor(visibleMinX / CHUNK_SIZE);
    const startChunkY = Math.floor(visibleMinY / CHUNK_SIZE);
    const endChunkX = Math.floor((visibleMaxX - 1) / CHUNK_SIZE);
    const endChunkY = Math.floor((visibleMaxY - 1) / CHUNK_SIZE);

    for (let cy = startChunkY; cy <= endChunkY; cy++) {
        for (let cx = startChunkX; cx <= endChunkX; cx++) {
            if (cx < 0 || cx >= CHUNKS_PER_ROW || cy < 0 || cy >= CHUNKS_PER_ROW) continue;

            const chunkData = chunkCache.getChunk(cx, cy);
            if (chunkData) {
                const screenX = (cx * CHUNK_SIZE - state.viewX) * state.zoom;
                const screenY = (cy * CHUNK_SIZE - state.viewY) * state.zoom;

                gridRenderer.renderChunk(chunkData, screenX, screenY, state.zoom);
            }
        }
    }

    renderOverlay(viewportWidth, viewportHeight);
    miniMap.update(state.viewX, state.viewY, viewportWidth / state.zoom, viewportHeight / state.zoom);
}

function renderOverlay(width, height) {
    overlayCtx.clearRect(0, 0, width, height);

    if (state.zoom >= 4) {
        overlayCtx.strokeStyle = 'rgba(200, 200, 200, 0.3)';
        overlayCtx.lineWidth = 1;

        const startX = -((state.viewX % 1) * state.zoom);
        const startY = -((state.viewY % 1) * state.zoom);

        for (let x = startX; x < width; x += state.zoom) {
            overlayCtx.beginPath();
            overlayCtx.moveTo(x, 0);
            overlayCtx.lineTo(x, height);
            overlayCtx.stroke();
        }

        for (let y = startY; y < height; y += state.zoom) {
            overlayCtx.beginPath();
            overlayCtx.moveTo(0, y);
            overlayCtx.lineTo(width, y);
            overlayCtx.stroke();
        }

        if (state.zoom >= 8) {
            overlayCtx.strokeStyle = 'rgba(100, 100, 255, 0.4)';
            overlayCtx.lineWidth = 2;

            for (let cx = 0; cx < CHUNKS_PER_ROW; cx++) {
                const x = (cx * CHUNK_SIZE - state.viewX) * state.zoom;
                if (x >= -CHUNK_SIZE * state.zoom && x <= width) {
                    overlayCtx.beginPath();
                    overlayCtx.moveTo(x, 0);
                    overlayCtx.lineTo(x, height);
                    overlayCtx.stroke();
                }
            }

            for (let cy = 0; cy < CHUNKS_PER_ROW; cy++) {
                const y = (cy * CHUNK_SIZE - state.viewY) * state.zoom;
                if (y >= -CHUNK_SIZE * state.zoom && y <= height) {
                    overlayCtx.beginPath();
                    overlayCtx.moveTo(0, y);
                    overlayCtx.lineTo(width, y);
                    overlayCtx.stroke();
                }
            }
        }
    }
}

function screenToGrid(screenX, screenY) {
    const gridX = Math.floor(state.viewX + (screenX / state.zoom));
    const gridY = Math.floor(state.viewY + (screenY / state.zoom));
    return { x: gridX, y: gridY };
}

function handleMouseDown(e) {
    if (e.button !== 0) return;

    const rect = viewport.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    if (state.purchaseMode && state.isLoggedIn) {
        const grid = screenToGrid(x, y);
        if (grid.x >= 0 && grid.x < GRID_SIZE && grid.y >= 0 && grid.y < GRID_SIZE) {
            showPurchasePopover(grid.x, grid.y, x, y);
        }
    } else {
        state.isDragging = true;
        state.dragStart = { x: e.clientX, y: e.clientY };
        state.viewStart = { x: state.viewX, y: state.viewY };
        viewport.style.cursor = 'grabbing';
    }
}

function handleMouseMove(e) {
    const rect = viewport.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    const grid = screenToGrid(x, y);
    document.getElementById('coordX').textContent = Math.max(0, Math.min(GRID_SIZE - 1, grid.x));
    document.getElementById('coordY').textContent = Math.max(0, Math.min(GRID_SIZE - 1, grid.y));

    if (state.isDragging) {
        const dx = (state.dragStart.x - e.clientX) / state.zoom;
        const dy = (state.dragStart.y - e.clientY) / state.zoom;

        state.viewX = Math.max(0, Math.min(GRID_SIZE - (gridCanvas.width / state.zoom), state.viewStart.x + dx));
        state.viewY = Math.max(0, Math.min(GRID_SIZE - (gridCanvas.height / state.zoom), state.viewStart.y + dy));

        render();
    }

    state.lastMousePos = { x, y };
}

function handleMouseUp() {
    state.isDragging = false;
    viewport.style.cursor = state.purchaseMode ? 'crosshair' : 'grab';
}

function handleWheel(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -1 : 1;
    const newZoom = Math.max(1, Math.min(16, state.zoom + delta));

    if (newZoom !== state.zoom) {
        const rect = viewport.getBoundingClientRect();
        const centerX = state.viewX + (rect.width / 2 / state.zoom);
        const centerY = state.viewY + (rect.height / 2 / state.zoom);

        state.zoom = newZoom;

        state.viewX = Math.max(0, Math.min(GRID_SIZE - (rect.width / state.zoom), centerX - (rect.width / 2 / state.zoom)));
        state.viewY = Math.max(0, Math.min(GRID_SIZE - (rect.height / state.zoom), centerY - (rect.height / 2 / state.zoom)));

        document.getElementById('zoomLevel').textContent = state.zoom + 'x';
        document.getElementById('statusZoom').textContent = state.zoom + 'x';

        render();
    }
}

function showPurchasePopover(gridX, gridY, screenX, screenY) {
    const popover = document.getElementById('purchasePopover');
    popover.querySelector('.preview-color').style.background = state.selectedColor;
    popover.querySelector('.preview-coord').textContent = `(${gridX}, ${gridY})`;
    popover.dataset.x = gridX;
    popover.dataset.y = gridY;

    const rect = viewport.getBoundingClientRect();
    let left = screenX + rect.left;
    let top = screenY + rect.top;

    if (left + 220 > window.innerWidth) left = screenX - 220;
    if (top + 100 > window.innerHeight) top = top - 100;

    popover.style.left = left + 'px';
    popover.style.top = top + 'px';
    popover.classList.add('visible');

    pixelBuyer.setPendingPixel(gridX, gridY, state.selectedColor);
}

function hidePurchasePopover() {
    document.getElementById('purchasePopover').classList.remove('visible');
    pixelBuyer.clearPendingPixel();
}

function toggleMode(mode) {
    state.purchaseMode = mode === 'paint';
    viewport.classList.toggle('paint-mode', state.purchaseMode);
    viewport.style.cursor = state.purchaseMode ? 'crosshair' : 'grab';

    document.getElementById('panMode').classList.toggle('active', !state.purchaseMode);
    document.getElementById('paintMode').classList.toggle('active', state.purchaseMode);
}

function zoomTo(level) {
    state.zoom = Math.max(1, Math.min(16, level));

    const rect = viewport.getBoundingClientRect();
    const centerX = state.viewX + (rect.width / 2 / state.zoom);
    const centerY = state.viewY + (rect.height / 2 / state.zoom);

    state.viewX = Math.max(0, Math.min(GRID_SIZE - (rect.width / state.zoom), centerX - (rect.width / 2 / state.zoom)));
    state.viewY = Math.max(0, Math.min(GRID_SIZE - (rect.height / state.zoom), centerY - (rect.height / 2 / state.zoom)));

    document.getElementById('zoomLevel').textContent = state.zoom + 'x';
    document.getElementById('statusZoom').textContent = state.zoom + 'x';

    render();
}

function gotoCoordinates() {
    const x = parseInt(document.getElementById('gotoX').value) || 0;
    const y = parseInt(document.getElementById('gotoY').value) || 0;

    const clampedX = Math.max(0, Math.min(GRID_SIZE - 1, x));
    const clampedY = Math.max(0, Math.min(GRID_SIZE - 1, y));

    const rect = viewport.getBoundingClientRect();
    state.viewX = clampedX - (rect.width / 2 / state.zoom);
    state.viewY = clampedY - (rect.height / 2 / state.zoom);

    state.viewX = Math.max(0, Math.min(GRID_SIZE - (rect.width / state.zoom), state.viewX));
    state.viewY = Math.max(0, Math.min(GRID_SIZE - (rect.height / state.zoom), state.viewY));

    render();
}

function updateBalance(newBalance) {
    state.userBalance = newBalance;
    document.getElementById('userBalance').textContent = newBalance;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

document.getElementById('customColor').addEventListener('change', (e) => {
    const color = e.target.value;
    if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
        selectColor(color);
    }
});

document.getElementById('zoomIn').addEventListener('click', () => zoomTo(state.zoom + 1));
document.getElementById('zoomOut').addEventListener('click', () => zoomTo(state.zoom - 1));
document.getElementById('panMode').addEventListener('click', () => toggleMode('pan'));
document.getElementById('paintMode').addEventListener('click', () => toggleMode('paint'));
document.getElementById('gotoBtn').addEventListener('click', gotoCoordinates);
document.getElementById('cancelPurchase').addEventListener('click', hidePurchasePopover);
document.getElementById('confirmPurchase').addEventListener('click', async () => {
    const x = parseInt(document.getElementById('purchasePopover').dataset.x);
    const y = parseInt(document.getElementById('purchasePopover').dataset.y);

    const result = await pixelBuyer.purchase(x, y, state.selectedColor);

    if (result.success) {
        updateBalance(result.newBalance);
        showToast('Pixel painted!', 'success');
        hidePurchasePopover();

        const chunkX = Math.floor(x / CHUNK_SIZE);
        const chunkY = Math.floor(y / CHUNK_SIZE);
        chunkCache.invalidateChunk(chunkX, chunkY);
        render();
    } else if (result.error === 'concurrent_conflict') {
        showToast('Someone just bought that pixel!', 'warning');
        hidePurchasePopover();
    } else if (result.error === 'insufficient_pxl') {
        showToast('Not enough PXL!', 'error');
        hidePurchasePopover();
    }
});

viewport.addEventListener('mousedown', handleMouseDown);
viewport.addEventListener('mousemove', handleMouseMove);
viewport.addEventListener('mouseup', handleMouseUp);
viewport.addEventListener('mouseleave', handleMouseUp);
viewport.addEventListener('wheel', handleWheel, { passive: false });

document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT') return;

    if (e.key === ' ') {
        e.preventDefault();
        toggleMode(state.purchaseMode ? 'pan' : 'paint');
    } else if (e.key === '+' || e.key === '=') {
        zoomTo(state.zoom + 1);
    } else if (e.key === '-') {
        zoomTo(state.zoom - 1);
    } else if (e.key === 'g' || e.key === 'G') {
        document.getElementById('gotoX').focus();
    }
});

window.addEventListener('resize', updateCanvasSize);

function init() {
    initColorPalette();
    selectColor(state.selectedColor);
    updateCanvasSize();

    const rect = viewport.getBoundingClientRect();
    state.viewX = (GRID_SIZE - (rect.width / state.zoom)) / 2;
    state.viewY = (GRID_SIZE - (rect.height / state.zoom)) / 2;

    const startChunkX = Math.floor(state.viewX / CHUNK_SIZE);
    const startChunkY = Math.floor(state.viewY / CHUNK_SIZE);
    const endChunkX = startChunkX + Math.ceil(rect.width / CHUNK_SIZE / state.zoom) + 1;
    const endChunkY = startChunkY + Math.ceil(rect.height / CHUNK_SIZE / state.zoom) + 1;

    for (let cy = startChunkY; cy < endChunkY; cy++) {
        for (let cx = startChunkX; cx < endChunkX; cx++) {
            if (cx >= 0 && cx < CHUNKS_PER_ROW && cy >= 0 && cy < CHUNKS_PER_ROW) {
                chunkCache.loadChunk(cx, cy);
            }
        }
    }

    render();
    miniMap.init(chunkCache);

    sseClient.connect((event) => {
        if (event.type === 'pixel') {
            const { cx, cy } = event;
            chunkCache.invalidateChunk(cx, cy);
            chunkCache.loadChunk(cx, cy);
            render();
        } else if (event.type === 'grid_reset') {
            chunkCache.clear();
            showToast('The Forge has been reset!', 'warning');
            render();
        }
    });
}

init();