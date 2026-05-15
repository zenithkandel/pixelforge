class HUD {
  constructor() {
    this.scoreEl = document.getElementById('hudScore');
    this.comboEl = document.getElementById('hudCombo');
    this.levelEl = document.getElementById('hudLevel');
    this.highEl = document.getElementById('hudHigh');
    this.livesEl = document.getElementById('livesDisplay');
    this.distanceEl = document.getElementById('distanceFill');
    this.shieldIcon = document.getElementById('shieldIcon');
    this.magnetIcon = document.getElementById('magnetIcon');
    this.slowmoIcon = document.getElementById('slowmoIcon');
    
    this.highScore = parseInt(localStorage.getItem('highScore')) || 0;
    this.updateHigh(this.highScore);
  }

  updateScore(score) {
    if (this.scoreEl) this.scoreEl.textContent = score.toLocaleString();
  }

  updateCombo(combo) {
    if (this.comboEl) this.comboEl.textContent = `x${Math.max(1, combo)}`;
  }

  updateLevel(level) {
    if (this.levelEl) this.levelEl.textContent = level;
  }

  updateHigh(score) {
    if (score > this.highScore) {
      this.highScore = score;
      localStorage.setItem('highScore', score);
    }
    if (this.highEl) this.highEl.textContent = this.highScore.toLocaleString();
  }

  updateLives(lives, maxLives = 3) {
    if (!this.livesEl) return;
    const icons = this.livesEl.querySelectorAll('.life-icon');
    icons.forEach((icon, i) => {
      icon.classList.toggle('lost', i >= lives);
    });
  }

  updateDistance(distance, level) {
    if (!this.distanceEl) return;
    const progress = (distance % 1000) / 1000 * 100;
    this.distanceEl.style.width = `${progress}%`;
  }

  updatePowerUps(shield, magnet, slowmo) {
    if (this.shieldIcon) this.shieldIcon.classList.toggle('active', shield);
    if (this.magnetIcon) this.magnetIcon.classList.toggle('active', magnet);
    if (this.slowmoIcon) this.slowmoIcon.classList.toggle('active', slowmo);
  }

  reset() {
    this.updateScore(0);
    this.updateCombo(0);
    this.updateLevel(1);
    this.updateLives(3);
    this.updateDistance(0, 1);
    this.updatePowerUps(false, false, false);
  }
}

window.HUD = HUD;