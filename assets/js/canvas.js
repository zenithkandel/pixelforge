import { api } from './api.js';
import { showError, showSuccess } from './ui.js';

// Grid Renderer - handles canvas rendering
export class GridRenderer {
    constructor(canvas, overlayCanvas) {
        this.canvas = canvas;
        this.overlayCanvas = overlayCanvas;
        this.ctx = canvas.getContext('2d', { alpha: true });
        this.overlayCtx = overlayCanvas.getContext('2d', { alpha: true });
        this.viewX = 0;
        this.viewY = 0;
        this.zoom = 1;
        this.chunkCache = new Map();
        this.selectedColor = '#000000';
    }

    setZoom(zoom) {
        this.zoom = Math.max(1, Math.min(16, zoom));
        this.render();
    }

    pan(dx, dy) {
        this.viewX += dx / this.zoom;
        this.viewY += dy / this.zoom;
        this.clampView();
        this.render();
    }

    clampView() {
        this.viewX = Math.max(0, Math.min(this.viewX, 800 - this.canvas.width / this.zoom));
        this.viewY = Math.max(0, Math.min(this.viewY, 800 - this.canvas.height / this.zoom));
    }

    render() {
        // Clear canvases
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Calculate visible pixel range
        const pixelsPerScreenX = this.canvas.width / this.zoom;
        const pixelsPerScreenY = this.canvas.height / this.zoom;

        // Draw pixels
        for (let px = Math.floor(this.viewX); px < this.viewX + pixelsPerScreenX; px++) {
            for (let py = Math.floor(this.viewY); py < this.viewY + pixelsPerScreenY; py++) {
                if (px >= 0 && px < 800 && py >= 0 && py < 800) {
                    const screenX = (px - this.viewX) * this.zoom;
                    const screenY = (py - this.viewY) * this.zoom;

                    // In real implementation, fetch pixel color from cache
                    this.ctx.fillStyle = '#FFFFFF';
                    this.ctx.fillRect(screenX, screenY, this.zoom, this.zoom);
                }
            }
        }

        // Draw grid lines if zoomed in enough
        if (this.zoom >= 4) {
            this.drawGrid();
        }

        // Draw overlay (cursor, highlights, etc)
        this.drawOverlay();
    }

    drawGrid() {
        this.overlayCtx.strokeStyle = 'rgba(200,200,200,0.3)';
        this.overlayCtx.lineWidth = 1;

        const pixelsPerScreenX = this.canvas.width / this.zoom;
        const pixelsPerScreenY = this.canvas.height / this.zoom;

        for (let px = Math.ceil(this.viewX); px < this.viewX + pixelsPerScreenX; px++) {
            const screenX = (px - this.viewX) * this.zoom;
            this.overlayCtx.beginPath();
            this.overlayCtx.moveTo(screenX, 0);
            this.overlayCtx.lineTo(screenX, this.overlayCanvas.height);
            this.overlayCtx.stroke();
        }

        for (let py = Math.ceil(this.viewY); py < this.viewY + pixelsPerScreenY; py++) {
            const screenY = (py - this.viewY) * this.zoom;
            this.overlayCtx.beginPath();
            this.overlayCtx.moveTo(0, screenY);
            this.overlayCtx.lineTo(this.overlayCanvas.width, screenY);
            this.overlayCtx.stroke();
        }
    }

    drawOverlay() {
        this.overlayCtx.clearRect(0, 0, this.overlayCanvas.width, this.overlayCanvas.height);
        // Draw cursor highlight, selection, etc.
    }

    async buyPixel(pixelX, pixelY, color) {
        const result = await api.post('/api/grid/buy.php', {
            x: pixelX,
            y: pixelY,
            color: color
        });

        if (result) {
            showSuccess('Pixel purchased!');
            this.render();
            return true;
        }
        return false;
    }
}

// Initialize canvas viewer
async function initCanvas() {
    const canvas = document.getElementById('gridCanvas');
    const overlayCanvas = document.getElementById('overlayCanvas');

    const renderer = new GridRenderer(canvas, overlayCanvas);

    // Setup event listeners
    setupCanvasControls(renderer);
    setupColorPalette(renderer);

    // Load user data
    const user = await api.get('/api/auth/me.php');
    if (user) {
        document.getElementById('username-display').textContent = user.username;
        document.getElementById('pxl-display').textContent = user.pxl_balance;
    }

    // Initial render
    renderer.render();
}

function setupCanvasControls(renderer) {
    // Zoom controls
    document.getElementById('zoom-in')?.addEventListener('click', () => {
        renderer.setZoom(renderer.zoom * 2);
        updateZoomDisplay(renderer.zoom);
    });

    document.getElementById('zoom-out')?.addEventListener('click', () => {
        renderer.setZoom(renderer.zoom / 2);
        updateZoomDisplay(renderer.zoom);
    });

    // Panning
    let isPanning = false;
    let panStartX, panStartY;

    document.getElementById('overlayCanvas')?.addEventListener('mousedown', (e) => {
        isPanning = true;
        panStartX = e.clientX;
        panStartY = e.clientY;
    });

    document.addEventListener('mousemove', (e) => {
        if (isPanning) {
            const dx = panStartX - e.clientX;
            const dy = panStartY - e.clientY;
            renderer.pan(dx, dy);
            panStartX = e.clientX;
            panStartY = e.clientY;
        }
    });

    document.addEventListener('mouseup', () => {
        isPanning = false;
    });

    // Logout
    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
}

function setupColorPalette(renderer) {
    const colors = ['#000000', '#FFFFFF', '#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF'];
    const container = document.getElementById('quick-colors');

    colors.forEach(color => {
        const div = document.createElement('div');
        div.className = 'quick-color';
        div.style.backgroundColor = color;
        div.onclick = () => {
            renderer.selectedColor = color;
            document.getElementById('color-input').value = color;
        };
        container.appendChild(div);
    });

    document.getElementById('color-input')?.addEventListener('change', (e) => {
        renderer.selectedColor = e.target.value;
    });
}

function updateZoomDisplay(zoom) {
    const level = zoom === 1 ? '1×' : `${zoom}×`;
    document.getElementById('zoom-level').textContent = level;
}

async function handleLogout() {
    await api.post('/api/auth/logout.php', {});
    showSuccess('Logged out');
    setTimeout(() => window.location.href = '/index.php', 500);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCanvas);
} else {
    initCanvas();
}
