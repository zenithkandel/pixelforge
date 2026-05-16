class PixelBuyer {
  constructor(api, renderer) {
    this.api = api;
    this.renderer = renderer;
    this.cooldown = false;
  }

  async purchasePixel(x, y, color) {
    if (this.cooldown) {
      window.pixelforge.showToast('Please wait...', 'warning');
      return false;
    }
    
    if (!window.pixelforge.auth?.isLoggedIn) {
      window.pixelforge.showToast('Please login to paint', 'warning');
      return false;
    }

    this.cooldown = true;
    setTimeout(() => this.cooldown = false, 200);

    try {
      const cx = Math.floor(x / 64);
      const cy = Math.floor(y / 64);
      
      if (!this.renderer.cache.get(cx, cy)) {
        await window.canvasApp.chunkCache.loadChunk(cx, cy, this.api);
      }
      
      this.renderer.applyPixelUpdate(x, y, color);
      
      const response = await this.api.post('/grid/buy', { x, y, color });

      if (response.ok) {
        if (response.data?.isGem) {
          window.pixelforge.showToast(`🎉 Hidden gem! +${response.data.gemBonus} PXL`, 'success');
        }
        return true;
      } else {
        this.renderer.applyPixelUpdate(x, y, '#FFFFFF');
        window.pixelforge.showToast(response.error || 'Failed to place pixel', 'error');
        return false;
      }
    } catch (err) {
      this.renderer.applyPixelUpdate(x, y, '#FFFFFF');
      window.pixelforge.showToast(err.message || 'Connection error', 'error');
      return false;
    }
  }

  eyedrop(x, y) {
    const cx = Math.floor(x / 64);
    const cy = Math.floor(y / 64);
    const chunk = window.canvasApp?.chunkCache.get(cx, cy);
    
    if (!chunk?.buffer) return '#FFFFFF';

    const lx = x % 64;
    const ly = y % 64;
    const offset = (ly * 64 + lx) * 3;
    
    const r = (chunk.buffer[offset] || 255).toString(16).padStart(2, '0');
    const g = (chunk.buffer[offset + 1] || 255).toString(16).padStart(2, '0');
    const b = (chunk.buffer[offset + 2] || 255).toString(16).padStart(2, '0');
    
    return `#${r}${g}${b}`.toUpperCase();
  }
}

window.PixelBuyer = PixelBuyer;