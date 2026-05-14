import { api, buyPixel } from '/assets/js/api.js';
import { showToast } from '/assets/js/ui.js';

class PixelBuyer {
  constructor(renderer, state) {
    this.renderer = renderer;
    this.state = state;
    this.pending = new Map();
  }

  async purchase(x, y) {
    const key = `${x},${y}`;
    if (this.pending.has(key)) return;
    if (this.state.userBalance < 1) {
      showToast('Not enough PXL! Play the game to earn more.', 'error');
      return false;
    }

    this.pending.set(key, this.state.selectedColor);
    this.state.pendingPixels.set(key, { color: this.state.selectedColor, status: 'pending' });
    this.renderer.drawPendingPixel(x, y, this.state.selectedColor);

    try {
      const res = await buyPixel(x, y, this.state.selectedColor);
      if (res.ok) {
        this.pending.delete(key);
        this.state.pendingPixels.delete(key);
        this.state.userBalance = res.data.new_balance;
        showToast('Pixel placed!', 'success');
        return true;
      } else {
        this.pending.delete(key);
        this.state.pendingPixels.delete(key);
        if (res.error === 'concurrent_conflict') {
          showToast('Someone else just bought that pixel!', 'warning');
        } else if (res.error === 'insufficient_pxl') {
          showToast('Not enough PXL!', 'error');
        } else if (res.error === 'rate_limited') {
          showToast('Slow down! Try again shortly.', 'warning');
        } else {
          showToast(res.message || 'Failed to place pixel', 'error');
        }
        return false;
      }
    } catch (e) {
      this.pending.delete(key);
      this.state.pendingPixels.delete(key);
      showToast('Network error. Please try again.', 'error');
      return false;
    }
  }

  isPending(x, y) {
    return this.pending.has(`${x},${y}`);
  }
}

export { PixelBuyer };