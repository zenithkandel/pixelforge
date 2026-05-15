<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HUD - PixelForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/main.css">
  <style>
    .hud { position: absolute; top: 0; left: 0; right: 0; padding: 15px 20px; display: flex; justify-content: space-between; pointer-events: none; }
    .hud-left, .hud-right { display: flex; flex-direction: column; gap: 8px; }
    .hud-center { position: absolute; left: 50%; transform: translateX(-50%); text-align: center; }
    .hud-item { background: rgba(10, 10, 15, 0.8); border: 1px solid var(--border-primary); border-radius: var(--radius-md); padding: 8px 14px; }
    .hud-label { font-family: var(--font-display); font-size: 8px; color: var(--text-muted); margin-bottom: 4px; }
    .hud-value { font-family: var(--font-display); font-size: 14px; color: var(--accent-game); }
    .hud-value.score { color: var(--accent-primary); text-shadow: 0 0 10px var(--accent-glow); }
    .hud-value.combo { color: var(--accent-gold); }
    .combo-popup { position: absolute; font-family: var(--font-display); font-size: 24px; color: var(--accent-gold); text-shadow: 0 0 20px rgba(255, 215, 0, 0.8); animation: comboPop 0.5s ease-out forwards; pointer-events: none; }
    @keyframes comboPop { 0% { transform: scale(0.5) translateY(0); opacity: 0; } 50% { transform: scale(1.2) translateY(-20px); opacity: 1; } 100% { transform: scale(1) translateY(-40px); opacity: 0; } }
    .power-up-indicator { display: flex; gap: 8px; margin-top: 8px; }
    .power-icon { width: 28px; height: 28px; border-radius: var(--radius-sm); background: var(--bg-tertiary); border: 2px solid var(--border-primary); display: flex; align-items: center; justify-content: center; font-size: 14px; opacity: 0.3; transition: all 0.2s; }
    .power-icon.active { opacity: 1; border-color: var(--accent-primary); box-shadow: 0 0 10px var(--accent-glow); }
    .lives-display { display: flex; gap: 4px; }
    .life-icon { width: 20px; height: 20px; border-radius: 50%; background: var(--accent-error); box-shadow: 0 0 8px rgba(255, 71, 87, 0.5); }
    .life-icon.lost { background: var(--bg-tertiary); box-shadow: none; }
    .distance-bar { width: 200px; height: 8px; background: var(--bg-tertiary); border-radius: 4px; overflow: hidden; margin-top: 8px; }
    .distance-fill { height: 100%; background: linear-gradient(90deg, var(--accent-game), var(--accent-primary)); border-radius: 4px; transition: width 0.1s; }
  </style>
</head>
<body>
  <div class="hud" id="gameHud">
    <div class="hud-left">
      <div class="hud-item">
        <div class="hud-label">SCORE</div>
        <div class="hud-value score" id="hudScore">0</div>
      </div>
      <div class="hud-item">
        <div class="hud-label">COMBO</div>
        <div class="hud-value combo" id="hudCombo">x1</div>
      </div>
    </div>
    
    <div class="hud-center">
      <div class="hud-item">
        <div class="hud-label">LEVEL</div>
        <div class="hud-value" id="hudLevel">1</div>
      </div>
      <div class="lives-display" id="livesDisplay">
        <div class="life-icon"></div>
        <div class="life-icon"></div>
        <div class="life-icon"></div>
      </div>
      <div class="distance-bar">
        <div class="distance-fill" id="distanceFill" style="width: 0%"></div>
      </div>
    </div>
    
    <div class="hud-right">
      <div class="hud-item">
        <div class="hud-label">HIGH</div>
        <div class="hud-value" id="hudHigh">0</div>
      </div>
      <div class="power-up-indicator">
        <div class="power-icon" id="shieldIcon" title="Shield">🛡️</div>
        <div class="power-icon" id="magnetIcon" title="Magnet">🧲</div>
        <div class="power-icon" id="slowmoIcon" title="Slow Motion">⏱️</div>
      </div>
    </div>
  </div>

  <script>
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
        this.scoreEl.textContent = score.toLocaleString();
      }

      updateCombo(combo) {
        this.comboEl.textContent = `x${Math.max(1, combo)}`;
      }

      updateLevel(level) {
        this.levelEl.textContent = level;
      }

      updateHigh(score) {
        if (score > this.highScore) {
          this.highScore = score;
          localStorage.setItem('highScore', score);
        }
        this.highEl.textContent = this.highScore.toLocaleString();
      }

      updateLives(lives, maxLives = 3) {
        const icons = this.livesEl.querySelectorAll('.life-icon');
        icons.forEach((icon, i) => {
          icon.classList.toggle('lost', i >= lives);
        });
      }

      updateDistance(distance, level) {
        const progress = (distance % 1000) / 1000 * 100;
        this.distanceEl.style.width = `${progress}%`;
      }

      updatePowerUps(shield, magnet, slowmo) {
        this.shieldIcon.classList.toggle('active', shield);
        this.magnetIcon.classList.toggle('active', magnet);
        this.slowmoIcon.classList.toggle('active', slowmo);
      }

      showComboPopup(x, y, combo) {
        const popup = document.createElement('div');
        popup.className = 'combo-popup';
        popup.textContent = `x${combo}!`;
        popup.style.left = `${x}px`;
        popup.style.top = `${y}px`;
        document.body.appendChild(popup);
        
        setTimeout(() => popup.remove(), 500);
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
  </script>
</body>
</html>