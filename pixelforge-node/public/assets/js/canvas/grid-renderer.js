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
    this.offsetX = 0;
    this.offsetY = 0;

    this.selectedColor = '#FF0000';
    this.hoverX = -1;
    this.hoverY = -1;

    this.setupCanvas();
  }

  setupCanvas() {
    const dpr = window.devicePixelRatio || 1;
    const displaySize = GRID_SIZE * this.pixelSize;

    this.canvas.width = GRID_SIZE * dpr;
    this.canvas.height = GRID_SIZE * dpr;
    this.canvas.style.width = `${displaySize}px`;
    this.canvas.style.height = `${displaySize}px`;

    this.ctx.scale(dpr, dpr);
    this.ctx.imageSmoothingEnabled = false;

    this.minimapCtx.imageSmoothingEnabled = false;
  }

  setZoom(pixelSize) {
    this.pixelSize = pixelSize;
    this.setupCanvas();
    this.render();
  }

  pan(dx, dy) {
    this.offsetX += dx;
    this.offsetY += dy;

    const maxOffset = GRID_SIZE * this.pixelSize;
    this.offsetX = Math.max(-maxOffset / 2, Math.min(maxOffset / 2, this.offsetX));
    this.offsetY = Math.max(-maxOffset / 2, Math.min(maxOffset / 2, this.offsetY));
  }

  setHover(x, y) {
    this.hoverX = x;
    this.hoverY = y;
  }

  clearHover() {
    this.hoverX = -1;
    this.hoverY = -1;
  }

  screenToGrid(screenX, screenY) {
    const rect = this.canvas.getBoundingClientRect();
    const x = Math.floor((screenX - rect.left) / this.pixelSize);
    const y = Math.floor((screenY - rect.top) / this.pixelSize);
    return { x, y };
  }

  render() {
    this.ctx.fillStyle = '#FFFFFF';
    this.ctx.fillRect(0, 0, GRID_SIZE, GRID_SIZE);

    this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.1)';
    this.ctx.lineWidth = 0.5;

    const startChunkX = 0;
    const startChunkY = 0;
    const endChunkX = NUM_CHUNKS;
    const endChunkY = NUM_CHUNKS;

    for (let cy = startChunkY; cy < endChunkY; cy++) {
      for (let cx = startChunkX; cx < endChunkX; cx++) {
        const chunk = this.cache.get(cx, cy);
        if (chunk && chunk.buffer) {
          this.renderChunk(cx, cy, chunk.buffer);
        }
      }
    }

    if (this.hoverX >= 0 && this.hoverY >= 0) {
      this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.5)';
      this.ctx.lineWidth = 1;
      this.ctx.strokeRect(
        this.hoverX * this.pixelSize,
        this.hoverY * this.pixelSize,
        this.pixelSize,
        this.pixelSize
      );
    }

    this.renderChunkBorders();
    this.renderMinimap();
  }

  renderChunk(cx, cy, buffer) {
    const xOffset = cx * CHUNK_SIZE;
    const yOffset = cy * CHUNK_SIZE;
    const dpr = window.devicePixelRatio || 1;

    const imageData = this.ctx.createImageData(CHUNK_SIZE, CHUNK_SIZE);
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

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = CHUNK_SIZE;
    tempCanvas.height = CHUNK_SIZE;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(imageData, 0, 0);

    this.ctx.drawImage(
      tempCanvas,
      xOffset * this.pixelSize,
      yOffset * this.pixelSize,
      CHUNK_SIZE * this.pixelSize,
      CHUNK_SIZE * this.pixelSize
    );
  }

  renderChunkBorders() {
    this.ctx.strokeStyle = 'rgba(0, 0, 0, 0.3)';
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

    this.minimapCtx.fillStyle = '#333';
    this.minimapCtx.fillRect(0, 0, 160, 160);

    for (let cy = 0; cy < NUM_CHUNKS; cy++) {
      for (let cx = 0; cx < NUM_CHUNKS; cx++) {
        const chunk = this.cache.get(cx, cy);
        if (chunk && chunk.buffer) {
          this.renderMinimapChunk(cx, cy, chunk.buffer, scale);
        }
      }
    }

    if (this.hoverX >= 0 && this.hoverY >= 0) {
      this.minimapCtx.strokeStyle = 'rgba(0, 170, 255, 0.8)';
      this.minimapCtx.lineWidth = 1;
      this.minimapCtx.strokeRect(
        this.hoverX * scale,
        this.hoverY * scale,
        scale,
        scale
      );
    }
  }

  renderMinimapChunk(cx, cy, buffer, scale) {
    const xOffset = Math.floor(cx * CHUNK_SIZE * scale);
    const yOffset = Math.floor(cy * CHUNK_SIZE * scale);
    const size = Math.ceil(CHUNK_SIZE * scale);

    const imageData = this.minimapCtx.createImageData(CHUNK_SIZE, CHUNK_SIZE);
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

    const tempCanvas = document.createElement('canvas');
    tempCanvas.width = CHUNK_SIZE;
    tempCanvas.height = CHUNK_SIZE;
    const tempCtx = tempCanvas.getContext('2d');
    tempCtx.putImageData(imageData, 0, 0);

    this.minimapCtx.drawImage(tempCanvas, xOffset, yOffset, size, size);
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

      this.render();
    }
  }
}

window.GridRenderer = GridRenderer;