class MiniMap {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.canvas.width = 160;
    this.canvas.height = 160;
    this.viewportRect = { x: 0, y: 0, w: 0, h: 0 };
  }

  clear() {
    this.ctx.fillStyle = '#ffffff';
    this.ctx.fillRect(0, 0, 160, 160);
    this.ctx.strokeStyle = '#333';
    this.ctx.lineWidth = 0.5;
    for (let i = 0; i <= 160; i += 20) {
      this.ctx.beginPath();
      this.ctx.moveTo(i, 0);
      this.ctx.lineTo(i, 160);
      this.ctx.stroke();
      this.ctx.beginPath();
      this.ctx.moveTo(0, i);
      this.ctx.lineTo(160, i);
      this.ctx.stroke();
    }
  }

  renderChunk(cx, cy, imageData) {
    const scale = 160 / 800;
    const x = cx * 64 * scale;
    const y = cy * 64 * scale;
    const w = 64 * scale;
    const h = 64 * scale;

    const offscreen = document.createElement('canvas');
    offscreen.width = 64;
    offscreen.height = 64;
    const offCtx = offscreen.getContext('2d');
    offCtx.putImageData(imageData, 0, 0);
    this.ctx.drawImage(offscreen, x, y, w, h);
  }

  updateViewport(viewX, viewY, viewW, viewH) {
    const scale = 160 / 800;
    this.viewportRect = {
      x: viewX * scale,
      y: viewY * scale,
      w: viewW * scale,
      h: viewH * scale,
    };

    this.ctx.strokeStyle = '#5b4fff';
    this.ctx.lineWidth = 2;
    this.ctx.strokeRect(this.viewportRect.x, this.viewportRect.y, this.viewportRect.w, this.viewportRect.h);
  }
}

export { MiniMap };