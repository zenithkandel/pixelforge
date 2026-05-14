import { ChunkCache } from './chunk-cache.js';

// Advanced Grid Renderer with chunk loading
export class AdvancedGridRenderer {
    constructor(canvas, overlayCanvas) {
        this.canvas = canvas;
        this.overlayCanvas = overlayCanvas;
        this.ctx = canvas.getContext('2d');
        this.overlayCtx = overlayCanvas.getContext('2d');

        this.chunkCache = new ChunkCache(200);
        this.chunkSize = 64;
        this.pixelSize = 1;
        this.viewX = 0;
        this.viewY = 0;
    }

    async loadChunk(cx, cy) {
        const key = `${cx}:${cy}`;
        if (this.chunkCache.has(key)) {
            return this.chunkCache.get(key);
        }

        try {
            const response = await fetch(`/api/grid/chunk.php?cx=${cx}&cy=${cy}`);
            if (response.ok) {
                const data = await response.arrayBuffer();
                this.chunkCache.set(key, data);
                return data;
            }
        } catch (e) {
            console.error('Failed to load chunk:', e);
        }
        return null;
    }

    render() {
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        // Calculate visible chunk range
        const chunkX1 = Math.floor(this.viewX / this.chunkSize);
        const chunkY1 = Math.floor(this.viewY / this.chunkSize);
        const chunkX2 = Math.ceil((this.viewX + this.canvas.width / this.pixelSize) / this.chunkSize);
        const chunkY2 = Math.ceil((this.viewY + this.canvas.height / this.pixelSize) / this.chunkSize);

        for (let cy = Math.max(0, chunkY1); cy < Math.min(800 / this.chunkSize, chunkY2 + 1); cy++) {
            for (let cx = Math.max(0, chunkX1); cx < Math.min(800 / this.chunkSize, chunkX2 + 1); cx++) {
                this.renderChunk(cx, cy);
            }
        }

        if (this.pixelSize >= 4) {
            this.drawGrid();
        }
    }

    renderChunk(cx, cy) {
        const key = `${cx}:${cy}`;
        const chunkData = this.chunkCache.get(key);

        if (!chunkData) {
            // Placeholder: render empty chunk
            return;
        }

        // Render chunk pixels
        const view = new Uint8Array(chunkData);
        for (let py = 0; py < this.chunkSize; py++) {
            for (let px = 0; px < this.chunkSize; px++) {
                const offset = (py * this.chunkSize + px) * 3;
                const r = view[offset];
                const g = view[offset + 1];
                const b = view[offset + 2];

                this.ctx.fillStyle = `rgb(${r},${g},${b})`;
                const screenX = (cx * this.chunkSize + px - this.viewX) * this.pixelSize;
                const screenY = (cy * this.chunkSize + py - this.viewY) * this.pixelSize;
                this.ctx.fillRect(screenX, screenY, this.pixelSize, this.pixelSize);
            }
        }
    }

    drawGrid() {
        this.overlayCtx.strokeStyle = 'rgba(200, 200, 200, 0.3)';
        this.overlayCtx.lineWidth = 1;

        const startX = Math.floor(this.viewX / this.chunkSize) * this.chunkSize;
        const startY = Math.floor(this.viewY / this.chunkSize) * this.chunkSize;

        for (let x = startX; x < this.viewX + this.canvas.width / this.pixelSize; x += this.chunkSize) {
            const screenX = (x - this.viewX) * this.pixelSize;
            this.overlayCtx.beginPath();
            this.overlayCtx.moveTo(screenX, 0);
            this.overlayCtx.lineTo(screenX, this.overlayCanvas.height);
            this.overlayCtx.stroke();
        }

        for (let y = startY; y < this.viewY + this.canvas.height / this.pixelSize; y += this.chunkSize) {
            const screenY = (y - this.viewY) * this.pixelSize;
            this.overlayCtx.beginPath();
            this.overlayCtx.moveTo(0, screenY);
            this.overlayCtx.lineTo(this.overlayCanvas.width, screenY);
            this.overlayCtx.stroke();
        }
    }

    setZoom(zoom) {
        this.pixelSize = zoom;
        this.render();
    }

    pan(dx, dy) {
        this.viewX += dx / this.pixelSize;
        this.viewY += dy / this.pixelSize;
        this.clampView();
        this.render();
    }

    clampView() {
        this.viewX = Math.max(0, Math.min(this.viewX, 800 - this.canvas.width / this.pixelSize));
        this.viewY = Math.max(0, Math.min(this.viewY, 800 - this.canvas.height / this.pixelSize));
    }
}
