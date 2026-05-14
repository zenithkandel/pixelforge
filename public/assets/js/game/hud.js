class HUD {
  constructor() {
    this.livesEl = document.getElementById('hud-lives');
    this.scoreEl = document.getElementById('hud-score');
    this.comboEl = document.getElementById('hud-combo');
    this.tierEl = document.getElementById('hud-tier');
    this.pxlEl = document.getElementById('hud-pxl');
  }

  update(state) {
    if (this.livesEl) this.livesEl.textContent = '\u2764\uFE0F'.repeat(state.lives);
    if (this.scoreEl) this.scoreEl.textContent = Number(state.score).toLocaleString();
    if (this.comboEl) {
      this.comboEl.textContent = `x${state.combo}`;
      if (state.combo >= 35) this.comboEl.style.color = '#fff';
      else if (state.combo >= 20) this.comboEl.style.color = '#ef4444';
      else if (state.combo >= 10) this.comboEl.style.color = '#f97316';
      else if (state.combo >= 5) this.comboEl.style.color = '#eab308';
      else this.comboEl.style.color = '#e5e7eb';
    }
    if (this.tierEl) this.tierEl.textContent = `T${state.speedTier}`;
    if (this.pxlEl) this.pxlEl.textContent = state.pxlBalance;
  }

  setBalance(balance) {
    if (this.pxlEl) this.pxlEl.textContent = balance;
  }
}

export { HUD };