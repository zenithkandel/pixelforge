class PixelBuyer {
  constructor(api, renderer) {
    this.api = api;
    this.renderer = renderer;
    this.pixelsPlaced = 0;
    this.pendingPurchases = new Map();
  }

  async purchasePixel(x, y, color) {
    if (this.pendingPurchases.has(`${x},${y}`)) {
      return false;
    }

    this.pendingPurchases.set(`${x},${y}`, true);

    try {
      const response = await this.api.post('/grid/buy', { x, y, color });

      if (response.ok) {
        this.pixelsPlaced++;
        this.renderer.applyPixelUpdate(x, y, color);

        if (response.data.isGem) {
          window.pixelforge.showToast(
            `🎉 Hidden gem found! +${response.data.gemBonus} PXL`,
            'success'
          );
        }

        return true;
      } else {
        window.pixelforge.showToast(response.error, 'error');
        return false;
      }
    } catch (err) {
      window.pixelforge.showToast(err.message, 'error');
      return false;
    } finally {
      this.pendingPurchases.delete(`${x},${y}`);
    }
  }

  async fillArea(startX, startY, color) {
    const session = await this.api.get('/grid/session');
    if (!session?.data) return;

    const targetColor = this.getPixelColor(startX, startY);
    
    if (this.colorsMatch(targetColor, color)) {
      return;
    }

    const stack = [[startX, startY]];
    const visited = new Set();
    let filled = 0;
    const maxFill = 500;

    while (stack.length > 0 && filled < maxFill) {
      const [x, y] = stack.pop();
      const key = `${x},${y}`;

      if (visited.has(key)) continue;
      if (x < 0 || x >= 800 || y < 0 || y >= 800) continue;

      const pixelColor = this.getPixelColor(x, y);
      if (!this.colorsMatch(pixelColor, targetColor)) continue;

      visited.add(key);

      const success = await this.purchasePixel(x, y, color);
      if (success) {
        filled++;
        stack.push([x + 1, y], [x - 1, y], [x, y + 1], [x, y - 1]);
        
        if (filled % 10 === 0) {
          await new Promise(r => setTimeout(r, 50));
        }
      }
    }

    window.pixelforge.showToast(`Filled ${filled} pixels`, 'info');
  }

  getPixelColor(x, y) {
    const cx = Math.floor(x / 64);
    const cy = Math.floor(y / 64);
    const chunk = window.chunkCache?.get(cx, cy);
    
    if (!chunk?.buffer) return { r: 255, g: 255, b: 255 };

    const lx = x % 64;
    const ly = y % 64;
    const offset = (ly * 64 + lx) * 3;

    return {
      r: chunk.buffer[offset],
      g: chunk.buffer[offset + 1],
      b: chunk.buffer[offset + 2]
    };
  }

  colorsMatch(c1, c2) {
    if (typeof c2 === 'string') {
      const r = parseInt(c2.slice(1, 3), 16);
      const g = parseInt(c2.slice(3, 5), 16);
      const b = parseInt(c2.slice(5, 7), 16);
      c2 = { r, g, b };
    }

    return c1.r === c2.r && c1.g === c2.g && c1.b === c2.b;
  }

  eyedrop(x, y) {
    const color = this.getPixelColor(x, y);
    const hex = '#' + 
      color.r.toString(16).padStart(2, '0') +
      color.g.toString(16).padStart(2, '0') +
      color.b.toString(16).padStart(2, '0');
    
    this.renderer.selectedColor = hex.toUpperCase();
    
    document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
    const customInput = document.getElementById('customColor');
    if (customInput) customInput.value = hex.toUpperCase();
    
    return hex.toUpperCase();
  }
}

window.PixelBuyer = PixelBuyer;