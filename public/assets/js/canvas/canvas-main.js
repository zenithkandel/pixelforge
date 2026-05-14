import { ChunkCache } from './chunk-cache.js';
import { apiPost } from '../api.js';

const gridCanvas = document.getElementById('gridCanvas');
const ctx = gridCanvas.getContext('2d');
const overlayCanvas = document.getElementById('overlayCanvas');
const overlayCtx = overlayCanvas.getContext('2d');

const cache = new ChunkCache();
let zoom = 1;
let viewX = 0;
let viewY = 0;

let isDragging = false;
let startDragX = 0;
let startDragY = 0;

async function renderVisibleChunks() {
    ctx.clearRect(0, 0, gridCanvas.width, gridCanvas.height);
    
    // Calculate visible chunks
    const cw = gridCanvas.width / zoom;
    const ch = gridCanvas.height / zoom;
    
    const startCx = Math.max(0, Math.floor(viewX / 64));
    const startCy = Math.max(0, Math.floor(viewY / 64));
    const endCx = Math.min(31, Math.floor((viewX + cw) / 64));
    const endCy = Math.min(31, Math.floor((viewY + ch) / 64));
    
    for (let cy = startCy; cy <= endCy; cy++) {
        for (let cx = startCx; cx <= endCx; cx++) {
            renderChunk(cx, cy);
        }
    }
}

async function renderChunk(cx, cy) {
    try {
        const data = await cache.getChunk(cx, cy);
        const imgData = new ImageData(64, 64);
        
        for (let i = 0; i < 64*64; i++) {
            imgData.data[i*4] = data[i*3];
            imgData.data[i*4+1] = data[i*3+1];
            imgData.data[i*4+2] = data[i*3+2];
            imgData.data[i*4+3] = 255;
        }
        
        const c = document.createElement('canvas');
        c.width = 64; c.height = 64;
        c.getContext('2d').putImageData(imgData, 0, 0);
        
        const dx = (cx * 64 - viewX) * zoom;
        const dy = (cy * 64 - viewY) * zoom;
        const dw = 64 * zoom;
        const dh = 64 * zoom;
        
        // Disable smoothing for crisp pixels
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(c, dx, dy, dw, dh);
    } catch (e) {
        console.error("Error rendering chunk", cx, cy, e);
    }
}

// Controls
overlayCanvas.addEventListener('mousedown', e => {
    isDragging = true;
    startDragX = e.clientX;
    startDragY = e.clientY;
});

overlayCanvas.addEventListener('mousemove', e => {
    if (isDragging) {
        const dx = (e.clientX - startDragX) / zoom;
        const dy = (e.clientY - startDragY) / zoom;
        viewX = Math.max(0, Math.min(800 - gridCanvas.width/zoom, viewX - dx));
        viewY = Math.max(0, Math.min(800 - gridCanvas.height/zoom, viewY - dy));
        startDragX = e.clientX;
        startDragY = e.clientY;
        renderVisibleChunks();
    }
});

overlayCanvas.addEventListener('mouseup', e => {
    isDragging = false;
});

// Zoom
document.getElementById('zoom-in').addEventListener('click', () => {
    zoom = Math.min(16, zoom * 2);
    document.getElementById('zoom-level').innerText = zoom + 'x';
    renderVisibleChunks();
});
document.getElementById('zoom-out').addEventListener('click', () => {
    zoom = Math.max(1, zoom / 2);
    document.getElementById('zoom-level').innerText = zoom + 'x';
    renderVisibleChunks();
});

// Click to paint
overlayCanvas.addEventListener('click', async e => {
    if (zoom < 4) {
        alert("Zoom in to at least 4x to paint!");
        return;
    }
    
    if (!window.IS_LOGGED_IN) {
        alert("Please login to paint.");
        return;
    }
    
    const rect = overlayCanvas.getBoundingClientRect();
    const x = Math.floor(viewX + (e.clientX - rect.left) / zoom);
    const y = Math.floor(viewY + (e.clientY - rect.top) / zoom);
    
    const color = document.getElementById('current-color').value;
    
    if (confirm(`Paint pixel at (${x}, ${y}) with ${color}? Cost: 1 PXL`)) {
        const res = await apiPost('api/grid/buy.php', { x, y, color });
        if (res.ok) {
            cache.updatePixel(Math.floor(x/64), Math.floor(y/64), x%64, y%64, color);
            renderVisibleChunks();
            
            // update balance UI
            const bal = document.getElementById('pxl-balance');
            if (bal) bal.innerText = 'PXL: ' + res.data.new_balance;
        } else {
            alert("Error: " + res.message);
        }
    }
});

// Initial render
renderVisibleChunks();

// SSE Setup
const evtSource = new EventSource('api/grid/updates.php?chunks=0,0'); // simplified for prototype
evtSource.onmessage = (e) => {
    const data = JSON.parse(e.data);
    if (data.type === 'pixel') {
        cache.updatePixel(data.cx, data.cy, data.x % 64, data.y % 64, data.color);
        renderVisibleChunks();
    }
};
