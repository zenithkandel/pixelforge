import { GridRenderer } from './grid-renderer.js';
import { ChunkCache } from './chunk-cache.js';
import { SSEClient } from './sse-client.js';
import { PixelBuyer } from './pixel-buyer.js';
import { MiniMap } from './mini-map.js';
import { api, getChunk } from '../api.js';
import { showToast, debounce, throttle } from '../ui.js';

const state = {
  viewX: 0,
  viewY: 0,
  zoom: 4,
  selectedColor: '#000000',
  userBalance: 0,
  isDragging: false,
  purchaseMode: false,
  pendingPixels: new Map(),
  chunks: new Map(),
  hoveredPixel: null,
};

const wrapper = document.getElementById('canvas-wrapper');
const mainCanvas = document.getElementById('forge-canvas');
const ctx = mainCanvas.getContext('2d');
const renderer = new GridRenderer(mainCanvas, ctx);
const chunkCache = new ChunkCache(200);
const miniMap = new MiniMap(document.getElementById('minimap-canvas'));
const buyer = new PixelBuyer(renderer, state);

let dragStart = null;
let lastMousePos = { x: 0, y: 0 };

const isLoggedIn = document.querySelector('.sidebar') !== null;

function initCanvas() {
  const w = wrapper.clientWidth;
  const h = wrapper.clientHeight;
  mainCanvas.width = w;
  mainCanvas.height = h;
  renderer.resize(w, h);
  renderer.setZoom(state.zoom);
  renderer.setView(state.viewX, state.viewY);
  renderer.clear();
  loadVisibleChunks();
}

function loadVisibleChunks() {
  const chunks = renderer.getVisibleChunks();
  chunks.forEach(({ cx, cy }) => loadChunk(cx, cy));
}

async function loadChunk(cx, cy) {
  const key = `${cx}_${cy}`;
  if (chunkCache.has(cx, cy)) {
    const cached = chunkCache.get(cx, cy);
    renderer.renderChunk(cached.imageData, cx, cy);
    miniMap.renderChunk(cx, cy, cached.imageData);
    return;
  }

  try {
    const { data: bin, version } = await getChunk(cx, cy);
    const imgData = decodeChunkBinary(bin);
    chunkCache.set(cx, cy, imgData, version);
    renderer.renderChunk(imgData, cx, cy);
    miniMap.renderChunk(cx, cy, imgData);
  } catch (e) {
    ctx.fillStyle = 'rgba(255,0,0,0.1)';
    const s = renderer.worldToScreen(cx * 64, cy * 64);
    ctx.fillRect(s.x, s.y, 64 * state.zoom, 64 * state.zoom);
  }
}

function decodeChunkBinary(bin) {
  const imgData = new ImageData(64, 64);
  for (let i = 0; i < 64 * 64; i++) {
    const si = i * 3;
    const di = i * 4;
    imgData.data[di] = bin[si] || 255;
    imgData.data[di + 1] = bin[si + 1] || 255;
    imgData.data[di + 2] = bin[si + 2] || 255;
    imgData.data[di + 3] = 255;
  }
  return imgData;
}

let base = '';
const scripts = document.querySelectorAll('script[src]');
for (const s of scripts) {
  const m = s.src.match(/\/assets\/js\/canvas\/canvas-main\.js$/);
  if (m) { base = s.src.replace(m[0], ''); break; }
}

const sseClient = new SSEClient(base + 'api/grid/updates.php');
sseClient.onPixelUpdate = (data) => {
  if (data.type === 'pixel') {
    const { cx, cy } = data;
    if (chunkCache.has(cx, cy)) {
      chunkCache.clear();
    }
    refreshChunk(cx, cy);
    showToast(`@${data.username} placed a pixel!`, 'info', 2000);
  }
};
sseClient.connect();

function refreshChunk(cx, cy) {
  chunkCache.cache.delete(`${cx}_${cy}`);
  loadChunk(cx, cy);
  redraw();
}

function redraw() {
  renderer.clear();
  const chunks = renderer.getVisibleChunks();
  chunks.forEach(({ cx, cy }) => {
    const cached = chunkCache.get(cx, cy);
    if (cached) renderer.renderChunk(cached.imageData, cx, cy);
  });
  state.pendingPixels.forEach((p, key) => {
    const [x, y] = key.split(',').map(Number);
    renderer.drawPendingPixel(x, y, p.color);
  });
  renderer.drawGridLines();
  updateMiniMap();
}

function updateMiniMap() {
  miniMap.clear();
  chunkCache.cache.forEach((entry, key) => {
    const [cx, cy] = key.split('_').map(Number);
    miniMap.renderChunk(cx, cy, entry.imageData);
  });
  miniMap.updateViewport(state.viewX, state.viewY, mainCanvas.width / state.zoom, mainCanvas.height / state.zoom);
}

mainCanvas.addEventListener('mousedown', (e) => {
  if (!isLoggedIn) return;
  if (e.button === 0) {
    if (state.purchaseMode) {
      handlePixelClick(e);
    } else {
      state.isDragging = true;
      dragStart = { x: e.offsetX, y: e.offsetY, viewX: state.viewX, viewY: state.viewY };
    }
  }
});

mainCanvas.addEventListener('mousemove', (e) => {
  lastMousePos = { x: e.offsetX, y: e.offsetY };
  const world = renderer.screenToWorld(e.offsetX, e.offsetY);
  const px = Math.floor(world.x);
  const py = Math.floor(world.y);
  state.hoveredPixel = { x: px, y: py };

  const coordEl = document.getElementById('coord-display');
  if (coordEl) coordEl.textContent = `X: ${px} Y: ${py}`;

  if (state.isDragging && dragStart) {
    const dx = (e.offsetX - dragStart.x) / state.zoom;
    const dy = (e.offsetY - dragStart.y) / state.zoom;
    renderer.setView(dragStart.viewX - dx, dragStart.viewY - dy);
    redraw();
  } else if (!state.purchaseMode && state.hoveredPixel) {
    redraw();
    renderer.drawCursorHighlight(px, py, state.selectedColor);
  }
});

mainCanvas.addEventListener('mouseup', () => {
  state.isDragging = false;
  dragStart = null;
});

mainCanvas.addEventListener('mouseleave', () => {
  state.isDragging = false;
  dragStart = null;
});

mainCanvas.addEventListener('wheel', (e) => {
  e.preventDefault();
  if (e.deltaY < 0) {
    zoomIn();
  } else {
    zoomOut();
  }
});

function handlePixelClick(e) {
  if (state.zoom < 4) {
    showToast('Zoom in to at least 4x to place pixels', 'warning');
    return;
  }
  const world = renderer.screenToWorld(e.offsetX, e.offsetY);
  const x = Math.floor(world.x);
  const y = Math.floor(world.y);
  if (x < 0 || x >= 800 || y < 0 || y >= 800) return;
  buyer.purchase(x, y);
}

document.getElementById('zoom-in')?.addEventListener('click', zoomIn);
document.getElementById('zoom-out')?.addEventListener('click', zoomOut);
document.getElementById('zoom-fit')?.addEventListener('click', () => {
  state.zoom = 4;
  renderer.setZoom(state.zoom);
  centerView();
  redraw();
  document.getElementById('zoom-level').textContent = '4x';
});

function zoomIn() {
  if (state.zoom >= 16) return;
  const newZoom = Math.min(16, state.zoom * 2);
  zoomTo(newZoom);
}

function zoomOut() {
  if (state.zoom <= 1) return;
  const newZoom = Math.max(1, state.zoom / 2);
  zoomTo(newZoom);
}

function zoomTo(newZoom) {
  const world = renderer.screenToWorld(mainCanvas.width / 2, mainCanvas.height / 2);
  state.zoom = newZoom;
  renderer.setZoom(state.zoom);
  renderer.setView(
    world.x - mainCanvas.width / (2 * state.zoom),
    world.y - mainCanvas.height / (2 * state.zoom)
  );
  document.getElementById('zoom-level').textContent = state.zoom + 'x';
  mainCanvas.className = `zoom-cursor-${state.zoom}`;
  loadVisibleChunks();
  redraw();
}

function centerView() {
  renderer.setView(0, 0);
  loadVisibleChunks();
}

document.querySelectorAll('.color-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.color-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.selectedColor = btn.dataset.color;
  });
});

document.getElementById('custom-color')?.addEventListener('input', (e) => {
  state.selectedColor = e.target.value;
});

document.querySelectorAll('.mode-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    state.purchaseMode = btn.dataset.mode === 'buy';
    mainCanvas.className = state.purchaseMode ? 'purchase-mode' : '';
    showToast(state.purchaseMode ? 'Purchase mode: click a pixel to buy (1 PXL)' : 'Pan mode: drag to move', 'info', 2000);
  });
});

window.addEventListener('resize', debounce(() => {
  initCanvas();
}, 200));

document.getElementById('canvas-loading')?.remove();
initCanvas();