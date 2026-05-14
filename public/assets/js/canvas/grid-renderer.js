class GridRenderer {
  constructor(canvas, ctx) {
    this.canvas = canvas;
    this.ctx = ctx;
    this.CHUNK_SIZE = 64;
    this.GRID_SIZE = 800;
    this.viewX = 0;
    this.viewY = 0;
    this.zoom = 4;
    this.scale = 1;
  }

  resize(width, height) {
    this.canvas.width = width;
    this.canvas.height = height;
  }

  setZoom(zoom) {
    this.zoom = zoom;
    this.scale = zoom;
  }

  setView(x, y) {
    this.viewX = Math.max(0, Math.min(this.GRID_SIZE - this.canvas.width / this.scale, x));
    this.viewY = Math.max(0, Math.min(this.GRID_SIZE - this.canvas.height / this.scale, y));
  }

  worldToScreen(wx, wy) {
    return {
      x: (wx - this.viewX) * this.scale,
      y: (wy - this.viewY) * this.scale,
    };
  }

  screenToWorld(sx, sy) {
    return {
      x: sx / this.scale + this.viewX,
      y: sy / this.scale + this.viewY,
    };
  }

  getVisibleChunks() {
    const topLeft = this.screenToWorld(0, 0);
    const bottomRight = this.screenToWorld(this.canvas.width, this.canvas.height);

    const minCX = Math.max(0, Math.floor(topLeft.x / this.CHUNK_SIZE));
    const maxCX = Math.min(31, Math.ceil(bottomRight.x / this.CHUNK_SIZE));
    const minCY = Math.max(0, Math.floor(topLeft.y / this.CHUNK_SIZE));
    const maxCY = Math.min(31, Math.ceil(bottomRight.y / this.CHUNK_SIZE));

    const chunks = [];
    for (let cy = minCY; cy <= maxCY; cy++) {
      for (let cx = minCX; cx <= maxCX; cx++) {
        chunks.push({ cx, cy });
      }
    }
    return chunks;
  }

  renderChunk(chunkData, cx, cy) {
    const imgData = this.ctx.createImageData(this.CHUNK_SIZE, this.CHUNK_SIZE);
    const data = imgData.data;
    const bin = chunkData;

    for (let ly = 0; ly < this.CHUNK_SIZE; ly++) {
      for (let lx = 0; lx < this.CHUNK_SIZE; lx++) {
        const pixelIndex = (ly * this.CHUNK_SIZE + lx) * 3;
        const imgIndex = (ly * this.CHUNK_SIZE + lx) * 4;
        data[imgIndex] = bin[pixelIndex] || 255;
        data[imgIndex + 1] = bin[pixelIndex + 1] || 255;
        data[imgIndex + 2] = bin[pixelIndex + 2] || 255;
        data[imgIndex + 3] = 255;
      }
    }

    const sx = cx * this.CHUNK_SIZE;
    const sy = cy * this.CHUNK_SIZE;
    const screen = this.worldToScreen(sx, sy);
    const renderW = this.CHUNK_SIZE * this.scale;
    const renderH = this.CHUNK_SIZE * this.scale;

    const offscreen = document.createElement('canvas');
    offscreen.width = this.CHUNK_SIZE;
    offscreen.height = this.CHUNK_SIZE;
    const offCtx = offscreen.getContext('2d');
    offCtx.putImageData(imgData, 0, 0);

    this.ctx.imageSmoothingEnabled = false;
    this.ctx.drawImage(offscreen, 0, 0, this.CHUNK_SIZE, this.CHUNK_SIZE, screen.x, screen.y, renderW, renderH);
  }

  drawPendingPixel(x, y, color) {
    const screen = this.worldToScreen(x, y);
    const size = this.scale;
    this.ctx.fillStyle = color;
    this.ctx.globalAlpha = 0.6;
    this.ctx.fillRect(screen.x, screen.y, size, size);
    this.ctx.globalAlpha = 1;
    this.ctx.strokeStyle = '#5b4fff';
    this.ctx.lineWidth = 2;
    this.ctx.strokeRect(screen.x, screen.y, size, size);
  }

  drawCursorHighlight(wx, wy, color) {
    const screen = this.worldToScreen(wx, wy);
    const size = this.scale;
    this.ctx.strokeStyle = color;
    this.ctx.lineWidth = 2;
    this.ctx.strokeRect(screen.x - 1, screen.y - 1, size + 2, size + 2);
  }

  drawGridLines() {
    if (this.zoom < 4) return;
    this.ctx.strokeStyle = 'rgba(200,200,200,0.2)';
    this.ctx.lineWidth = 0.5;

    const topLeft = this.screenToWorld(0, 0);
    const bottomRight = this.screenToWorld(this.canvas.width, this.canvas.height);

    const startX = Math.floor(topLeft.x);
    const endX = Math.ceil(bottomRight.x);
    const startY = Math.floor(topLeft.y);
    const endY = Math.ceil(bottomRight.y);

    for (let x = startX; x <= endX; x++) {
      const sx = (x - this.viewX) * this.scale;
      this.ctx.beginPath();
      this.ctx.moveTo(sx, 0);
      this.ctx.lineTo(sx, this.canvas.height);
      this.ctx.stroke();
    }
    for (let y = startY; y <= endY; y++) {
      const sy = (y - this.viewY) * this.scale;
      this.ctx.beginPath();
      this.ctx.moveTo(0, sy);
      this.ctx.lineTo(this.canvas.width, sy);
      this.ctx.stroke();
    }
  }

  clear() {
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }
}

export { GridRenderer };