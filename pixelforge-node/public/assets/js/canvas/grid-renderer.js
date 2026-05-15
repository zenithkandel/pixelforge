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
    this.minimapChunkCanvases = new Map();
    this.needsFullRender = true;
    this.renderQueued = false;

    this.setupCanvas();
    this.initMinimap();
  }

  setupCanvas() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const displaySize = GRID_SIZE * this.pixelSize;

    this.canvas.width = GRID_SIZE * dpr;
    this.canvas.height = GRID_SIZE * dpr;
    this.canvas.style.width = `${displaySize}px`;
    this.canvas.style.height = `${displaySize}px`;

    this.ctx.scale(dpr, dpr);
    this.ctx.imageSmoothingEnabled = false;
  }

  initMinimap() {
    this.minimapCtx.imageSmoothingEnabled = false;
    
    const minimapDpr = 1;
    this.minimapCanvas.width = 160 * minimapDpr;
    this.minimapCanvas.height = 160 * minimapDpr;
  }

  setZoom(pixelSize) {
    this.pixelSize = Math.max(0.5, Math.min(4, pixelSize));
    this.setupCanvas();
    this.needsFullRender = true;
    this.queueRender();
  }

  screenToGrid(screenX, screenY) {
    const rect = this.canvas.getBoundingClientRect();
    const x = Math.floor((screenX - rect.left) / this.pixelSize);
    const y = Math.floor((screenY - rect.top) / this.pixelSize);
    return { x, y };
  }

  pan(dx, dy) {
    this.needsFullRender = true;
  }

  panTo(x, y) {
    this.needsFullRender = true;
    this.queueRender();
  }

  setHoverFromCoords(clientX, clientY) {
    const { x, y } = this.screenToGrid(clientX, clientY);
    this.setHover(x, y);
  }

  queueRender() {
    if (this.renderQueued) return;
    this.renderQueued = true;
    requestAnimationFrame(() => {
      this.renderQueued = false;
      this.render();
    });
  }

  render() {
    if (!this.needsFullRender) return;

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
          
          const scale = this.pixelSize;
          this.ctx.drawImage(
            chunkCanvas,
            cx * CHUNK_SIZE * scale,
            cy * CHUNK_SIZE * scale,
            CHUNK_SIZE * scale,
            CHUNK_SIZE * scale
          );
        }
      }
    }

    this.renderChunkBorders();
    
    if (this.hoverX >= 0 && this.hoverY >= 0) {
      this.ctx.strokeStyle = 'rgba(0, 170, 255, 0.8)';
      this.ctx.lineWidth = 2 / this.pixelSize;
      this.ctx.strokeRect(
        this.hoverX * this.pixelSize,
        this.hoverY * this.pixelSize,
        this.pixelSize,
        this.pixelSize
      );
    }

    this.renderMinimap();
    this.needsFullRender = false;
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
        const offset = (py * CHUNK_SIZE + px) * 3;
        const pixelOffset = (py * CHUNK_SIZE + px) * 4;
        data[pixelOffset] = buffer[offset];
        data[pixelOffset + 1] = buffer[offset + 1];
        data[pixelOffset + 2] = buffer[offset + 2];
        data[pixelOffset + 3] = 255;
      }
    }

    tempCtx.putImageData(imageData, 0, 0);
    return tempCanvas;
  }

  renderChunkBorders() {
    if (this.pixelSize < 3) return;
    
    this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
    this.ctx.lineWidth = 1;

    for (let i = 0; i <= NUM_CHUNKS; i++) {
      const pos = i * CHUNK_SIZE * this.pixelSize;
      this.ctx.beginPath();
      this.ctx.moveTo(pos, 0);
      this.ctx.lineTo(pos, GRID_SIZE * this.pixelSize);
      this.ctx.stroke();

      this.ctx.beginPath();
      this.ctx.moveTo(0, pos);
      this.ctx.lineTo(GRID_SIZE * this.pixelSize, pos);
      this.ctx.stroke();
    }
  }

  renderMinimap() {
    const scale = 160 / GRID_SIZE;

    this.minimapCtx.fillStyle = '#1a1a26';
    this.minimapCtx.fillRect(0, 0, 160, 160);

    for (let cy = 0; cy < NUM_CHUNKS; cy++) {
      for (let cx = 0; cx < NUM_CHUNKS; cx++) {
        const chunk = this.cache.get(cx, cy);
        if (chunk && chunk.buffer) {
          let mmCanvas = this.minimapChunkCanvases.get(`${cx}_${cy}`);
          
          if (!mmCanvas) {
            mmCanvas = this.createMinimapChunkCanvas(cx, cy, chunk.buffer);
            this.minimapChunkCanvases.set(`${cx}_${cy}`, mmCanvas);
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

  createMinimapChunkCanvas(cx, cy, buffer) {
    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = CHUNK_SIZE;
    tempCanvas.height = CHUNK_SIZE;
    const tempCtx = tempCanvas.getContext('2d');

    const imageData = tempCtx.createImageData(CHUNK_SIZE, CHUNK_SIZE);
    const data = imageData.data;

    for (let py = 0; py < CHUNK_SIZE; py++) {
      for (let px = 0; px < CHUNK_SIZE; px++) {
        const offset = (py * CHUNK_SIZE + px) * 3;
        const pixelOffset = (py * CHUNK_SIZE + px) * 4;
        data[pixelOffset] = buffer[offset];
        data[pixelOffset + 1] = buffer[offset + 1];
        data[pixelOffset + 2] = buffer[offset + 2];
        data[pixelOffset + 3] = 255;
      }
    }

    tempCtx.putImageData(imageData, 0, 0);
    return tempCanvas;
  }

  setHover(x, y) {
    this.hoverX = x;
    this.hoverY = y;
    this.queueRender();
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
      this.minimapChunkCanvases.delete(`${cx}_${cy}`);
      
      this.needsFullRender = true;
      this.queueRender();
    }
  }

  invalidateChunk(cx, cy) {
    this.chunkCanvases.delete(`${cx}_${cy}`);
    this.minimapChunkCanvases.delete(`${cx}_${cy}`);
    this.needsFullRender = true;
  }

  invalidateAll() {
    this.chunkCanvases.clear();
    this.minimapChunkCanvases.clear();
    this.needsFullRender = true;
  }
}

window.GridRenderer = GridRenderer;