const GRID_SIZE = 800;
const CHUNK_SIZE = 64;
const NUM_CHUNKS = GRID_SIZE / CHUNK_SIZE;

class GridRenderer {
  constructor(canvas, minimapCanvas, cache) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.minimapCanvas = minimapCanvas;
    this.minimapCtx = minimapCanvas.getContext('2d');
    this.cache = cache;

    this.pixelSize = 1;
    this.selectedColor = '#FF0000';
    this.hoverX = -1;
    this.hoverY = -1;

    this.chunkCanvases = new Map();
    this.minimapCanvases = new Map();
    this.pendingChunk = null;
    this.isRendering = false;

    this.setupCanvas();
    this.initMinimap();
  }

  setupCanvas() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.dpr = dpr;
    
    this.canvas.width = GRID_SIZE * dpr;
    this.canvas.height = GRID_SIZE * dpr;
    this.canvas.style.width = `${GRID_SIZE}px`;
    this.canvas.style.height = `${GRID_SIZE}px`;

    this.ctx.scale(dpr, dpr);
    this.ctx.imageSmoothingEnabled = false;
  }

  initMinimap() {
    this.minimapCtx.imageSmoothingEnabled = false;
  }

  setZoom(pixelSize) {
    this.pixelSize = Math.max(0.5, Math.min(4, pixelSize));
    this.setupCanvas();
    this.scheduleRender();
  }

  screenToGrid(screenX, screenY) {
    const rect = this.canvas.getBoundingClientRect();
    const x = Math.floor((screenX - rect.left) / this.pixelSize);
    const y = Math.floor((screenY - rect.top) / this.pixelSize);
    return { x, y };
  }

  scheduleRender() {
    if (this.isRendering) return;
    this.isRendering = true;
    requestAnimationFrame(() => {
      this.render();
      this.isRendering = false;
    });
  }

  render() {
    this.ctx.fillStyle = '#FFFFFF';
    this.ctx.fillRect(0, 0, GRID_SIZE, GRID_SIZE);

    for (let cy = 0; cy < NUM_CHUNKS; cy++) {
      for (let cx = 0; cx < NUM_CHUNKS; cx++) {
        const chunk = this.cache.get(cx, cy);
        if (chunk && chunk.buffer) {
          let chunkCanvas = this.chunkCanvases.get(`${cx}_${cy}`);
          
          if (!chunkCanvas) {
            chunkCanvas = this.createChunkCanvas(cx, cy, chunk.buffer);
            this.chunkCanvases.set(`${cx}_${cy}`, chunkCanvas);
          }
          
          this.ctx.drawImage(chunkCanvas, 0, 0, GRID_SIZE, GRID_SIZE);
        }
      }
    }

    if (this.hoverX >= 0 && this.hoverY >= 0) {
      this.ctx.strokeStyle = 'rgba(0, 170, 255, 0.8)';
      this.ctx.lineWidth = 2;
      this.ctx.strokeRect(this.hoverX, this.hoverY, 1, 1);
    }

    this.renderMinimap();
  }

  createChunkCanvas(cx, cy, buffer) {
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = CHUNK_SIZE;
    tempCanvas.height = CHUNK_SIZE;
    const tempCtx = tempCanvas.getContext('2d');

    const imageData = tempCtx.createImageData(CHUNK_SIZE, CHUNK_SIZE);
    const data = imageData.data;

    for (let py = 0; py < CHUNK_SIZE; py++) {
      for (let px = 0; px < CHUNK_SIZE; px++) {
        const srcOffset = (py * CHUNK_SIZE + px) * 3;
        const dstOffset = (py * CHUNK_SIZE + px) * 4;
        data[dstOffset] = buffer[srcOffset] || 255;
        data[dstOffset + 1] = buffer[srcOffset + 1] || 255;
        data[dstOffset + 2] = buffer[srcOffset + 2] || 255;
        data[dstOffset + 3] = 255;
      }
    }

    tempCtx.putImageData(imageData, 0, 0);
    return tempCanvas;
  }

  renderMinimap() {
    const scale = 160 / GRID_SIZE;

    this.minimapCtx.fillStyle = '#1a1a26';
    this.minimapCtx.fillRect(0, 0, 160, 160);

    for (let cy = 0; cy < NUM_CHUNKS; cy++) {
      for (let cx = 0; cx < NUM_CHUNKS; cx++) {
        const chunk = this.cache.get(cx, cy);
        if (chunk && chunk.buffer) {
          let mmCanvas = this.minimapCanvases.get(`${cx}_${cy}`);
          
          if (!mmCanvas) {
            mmCanvas = this.createChunkCanvas(cx, cy, chunk.buffer);
            this.minimapCanvases.set(`${cx}_${cy}`, mmCanvas);
          }
          
          this.minimapCtx.drawImage(mmCanvas, cx * CHUNK_SIZE * scale, cy * CHUNK_SIZE * scale, CHUNK_SIZE * scale, CHUNK_SIZE * scale);
        }
      }
    }

    if (this.hoverX >= 0 && this.hoverY >= 0) {
      this.minimapCtx.strokeStyle = '#00aaff';
      this.minimapCtx.lineWidth = 1;
      this.minimapCtx.strokeRect(this.hoverX * scale, this.hoverY * scale, scale, scale);
    }
  }

  setHover(x, y) {
    this.hoverX = x;
    this.hoverY = y;
    this.scheduleRender();
  }

  applyPixelUpdate(x, y, color) {
    const cx = Math.floor(x / CHUNK_SIZE);
    const cy = Math.floor(y / CHUNK_SIZE);
    const chunk = this.cache.get(cx, cy);

    if (chunk && chunk.buffer) {
      const lx = x % CHUNK_SIZE;
      const ly = y % CHUNK_SIZE;
      const offset = (ly * CHUNK_SIZE + lx) * 3;

      const r = parseInt(color.slice(1, 3), 16);
      const g = parseInt(color.slice(3, 5), 16);
      const b = parseInt(color.slice(5, 7), 16);

      chunk.buffer[offset] = r;
      chunk.buffer[offset + 1] = g;
      chunk.buffer[offset + 2] = b;

      this.chunkCanvases.delete(`${cx}_${cy}`);
      this.minimapCanvases.delete(`${cx}_${cy}`);
      
      this.scheduleRender();
    }
  }

  invalidateAll() {
    this.chunkCanvases.clear();
    this.minimapCanvases.clear();
    this.scheduleRender();
  }
}

window.GridRenderer = GridRenderer;