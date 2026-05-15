const CANVAS_WIDTH = 900;
const CANVAS_HEIGHT = 400;
const GROUND_Y = 320;
const PLAYER_SIZE = 32;
const PLAYER_X = 80;
const BASE_SPEED = 3;
const SPEED_INCREMENT = 0.5;
const MAX_SPEED = 12;
const GRAVITY = 0.8;
const JUMP_FORCE = -15;
const SLIDE_DURATION = 500;

class Game {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    this.renderer = new GameRenderer(this.canvas);
    this.prng = null;
    
    this.player = {
      x: PLAYER_X,
      y: GROUND_Y - PLAYER_SIZE,
      width: PLAYER_SIZE,
      height: PLAYER_SIZE,
      velocityY: 0,
      state: 'running',
      frame: 0,
      hasShield: false,
      hasMagnet: false,
      hasSlowMo: false,
      slideTimer: 0,
      lives: 3
    };
    
    this.obstacles = [];
    this.collectibles = [];
    this.particles = [];
    this.foregroundParticles = [];
    
    this.score = 0;
    this.highScore = parseInt(localStorage.getItem('highScore')) || 0;
    this.combo = 0;
    this.maxCombo = 5;
    this.level = 1;
    this.speed = BASE_SPEED;
    this.distance = 0;
    
    this.gameState = 'menu';
    this.sessionToken = null;
    this.signingKey = null;
    this.checkpoints = [];
    
    this.keys = { jump: false, slide: false };
    this.lastObstacleSpawn = 0;
    this.lastCollectibleSpawn = 0;
    this.lastFrameTime = 0;
    this.deltaTime = 0;
    
    this.audio = new GameAudio();
    
    this.init();
  }

  init() {
    this.setupControls();
    this.updateUI();
    this.gameLoop(0);
  }

  setupControls() {
    const handleKeyDown = (e) => {
      if (e.code === 'Space' || e.code === 'ArrowUp') {
        e.preventDefault();
        this.keys.jump = true;
        if (this.gameState === 'playing') this.jump();
      }
      if (e.code === 'ArrowDown') {
        e.preventDefault();
        this.keys.slide = true;
        if (this.gameState === 'playing') this.slide();
      }
    };

    const handleKeyUp = (e) => {
      if (e.code === 'Space' || e.code === 'ArrowUp') this.keys.jump = false;
      if (e.code === 'ArrowDown') this.keys.slide = false;
    };

    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('keyup', handleKeyUp);

    this.canvas.addEventListener('touchstart', (e) => {
      e.preventDefault();
      const touch = e.touches[0];
      const rect = this.canvas.getBoundingClientRect();
      const y = touch.clientY - rect.top;
      
      if (y < rect.height / 2) {
        this.jump();
      } else {
        this.slide();
      }
    });

    document.getElementById('startBtn')?.addEventListener('click', () => this.startGame());
    document.getElementById('restartBtn')?.addEventListener('click', () => this.startGame());
    document.getElementById('homeBtn')?.addEventListener('click', () => window.location.href = '/');
  }

  async startGame() {
    try {
      const response = await window.pixelforge.api.post('/game/start', {});
      if (response.ok) {
        this.sessionToken = response.data.sessionToken;
        this.signingKey = response.data.signingKey;
        this.prng = new PRNG(response.data.seed);
      }
    } catch (err) {
      this.prng = new PRNG(Math.floor(Math.random() * 0xFFFFFFFF));
    }

    this.player = {
      x: PLAYER_X,
      y: GROUND_Y - PLAYER_SIZE,
      width: PLAYER_SIZE,
      height: PLAYER_SIZE,
      velocityY: 0,
      state: 'running',
      frame: 0,
      hasShield: false,
      hasMagnet: false,
      hasSlowMo: false,
      slideTimer: 0,
      lives: 3
    };

    this.obstacles = [];
    this.collectibles = [];
    this.particles = [];
    this.foregroundParticles = [];
    this.score = 0;
    this.combo = 0;
    this.level = 1;
    this.speed = BASE_SPEED;
    this.distance = 0;
    this.checkpoints = [];

    this.gameState = 'playing';
    document.getElementById('startScreen')?.classList.add('hidden');
    document.getElementById('gameOverScreen')?.classList.add('hidden');

    this.audio.play('start');
    this.updateUI();
  }

  jump() {
    if (this.player.state !== 'jumping' && this.player.y >= GROUND_Y - PLAYER_SIZE) {
      this.player.velocityY = JUMP_FORCE;
      this.player.state = 'jumping';
      this.audio.play('jump');
      this.spawnJumpParticles();
    }
  }

  slide() {
    if (this.player.state !== 'jumping') {
      this.player.state = 'ducking';
      this.player.slideTimer = SLIDE_DURATION;
    }
  }

  update() {
    if (this.gameState !== 'playing') return;

    const dt = this.deltaTime / 16.67;
    const effectiveSpeed = this.player.hasSlowMo ? this.speed * 0.5 : this.speed;

    this.distance += effectiveSpeed * dt;
    this.score += Math.floor(effectiveSpeed * dt * (1 + this.combo * 0.1));

    this.level = Math.floor(this.distance / 1000) + 1;
    this.speed = Math.min(BASE_SPEED + (this.level - 1) * SPEED_INCREMENT, MAX_SPEED);

    this.updatePlayer(dt);
    this.updateObstacles(effectiveSpeed, dt);
    this.updateCollectibles(effectiveSpeed, dt);
    this.updateParticles(dt);
    this.checkCollisions();

    if (this.score > this.highScore) {
      this.highScore = this.score;
      localStorage.setItem('highScore', this.highScore);
    }

    this.updateUI();
  }

  updatePlayer(dt) {
    this.player.velocityY += GRAVITY * dt;
    this.player.y += this.player.velocityY * dt;

    if (this.player.y >= GROUND_Y - PLAYER_SIZE) {
      this.player.y = GROUND_Y - PLAYER_SIZE;
      this.player.velocityY = 0;
      if (this.player.state === 'jumping') {
        this.player.state = 'running';
        this.spawnLandParticles();
      }
    }

    if (this.player.slideTimer > 0) {
      this.player.slideTimer -= this.deltaTime;
      if (this.player.slideTimer <= 0) {
        this.player.state = 'running';
      }
    }

    if (this.player.state === 'ducking') {
      this.player.height = PLAYER_SIZE * 0.6;
    } else {
      this.player.height = PLAYER_SIZE;
    }

    this.player.frame += 0.2 * dt;
  }

  updateObstacles(speed, dt) {
    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      obs.x -= speed * dt;
      
      if (obs.type === 'virus_swarm') {
        obs.y += Math.sin(Date.now() * 0.01 + obs.phase) * 2;
      }
      
      if (obs.type === 'datawall') {
        obs.gapY += obs.gapSpeed * dt;
        if (obs.gapY < 50 || obs.gapY > GROUND_Y - obs.gapHeight - 50) {
          obs.gapSpeed *= -1;
        }
      }

      if (obs.x + obs.width < 0) {
        this.obstacles.splice(i, 1);
        this.combo = 0;
      }
    }

    if (this.distance - this.lastObstacleSpawn > 200 + Math.random() * 300 - this.level * 20) {
      this.spawnObstacle();
      this.lastObstacleSpawn = this.distance;
    }
  }

  spawnObstacle() {
    const types = ['virus', 'firewall', 'malware'];
    
    if (this.level >= 5) types.push('virus_swarm');
    if (this.level >= 6) types.push('datawall');

    const type = this.prng ? this.prng.pick(types) : types[Math.floor(Math.random() * types.length)];
    
    let obstacle = { x: CANVAS_WIDTH, type };

    switch (type) {
      case 'virus':
        obstacle.y = GROUND_Y - 30;
        obstacle.width = 30;
        obstacle.height = 30;
        break;
      case 'firewall':
        obstacle.y = GROUND_Y - 60 - Math.random() * 40;
        obstacle.width = 25;
        obstacle.height = 60 + Math.random() * 40;
        break;
      case 'malware':
        obstacle.y = GROUND_Y - 40;
        obstacle.width = 35;
        obstacle.height = 40;
        break;
      case 'virus_swarm':
        obstacle.y = 150 + Math.random() * 100;
        obstacle.width = 30;
        obstacle.height = 30;
        obstacle.phase = Math.random() * Math.PI * 2;
        obstacle.gapSpeed = 0;
        break;
      case 'datawall':
        obstacle.y = 0;
        obstacle.width = 20;
        obstacle.height = GROUND_Y;
        obstacle.gapY = GROUND_Y / 2;
        obstacle.gapHeight = 80;
        obstacle.gapSpeed = (Math.random() - 0.5) * 4;
        break;
      default:
        obstacle.y = GROUND_Y - 40;
        obstacle.width = 30;
        obstacle.height = 40;
    }

    this.obstacles.push(obstacle);
  }

  updateCollectibles(speed, dt) {
    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const col = this.collectibles[i];
      col.x -= speed * dt;
      col.frame += 0.1 * dt;

      if (col.x + col.size < 0) {
        this.collectibles.splice(i, 1);
      }
    }

    if (this.distance - this.lastCollectibleSpawn > 100 + Math.random() * 200) {
      this.spawnCollectible();
      this.lastCollectibleSpawn = this.distance;
    }
  }

  spawnCollectible() {
    const types = ['power_cell', 'data_orb'];
    if (this.prng && Math.random() < 0.01) types.push('canvas_boost');

    const type = this.prng ? this.prng.pick(types) : types[Math.floor(Math.random() * types.length)];
    const size = 20;
    
    this.collectibles.push({
      x: CANVAS_WIDTH,
      y: GROUND_Y - 50 - Math.random() * 100,
      size,
      type,
      frame: 0
    });
  }

  updateParticles(dt) {
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      p.vy += 0.3 * dt;
      p.alpha -= 0.02 * dt;
      p.size *= 0.98;

      if (p.alpha <= 0) {
        this.particles.splice(i, 1);
      }
    }
  }

  checkCollisions() {
    const player = this.player;
    const px = player.x;
    const py = player.y;
    const pw = player.width * (player.state === 'ducking' ? 1.3 : 1);
    const ph = player.height;

    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      
      let obsY = obs.y;
      let obsH = obs.height;
      
      if (obs.type === 'datawall') {
        const inGap = py + ph > obs.gapY && py < obs.gapY + obs.gapHeight;
        if (inGap) continue;
      }

      if (this.intersects(px, py, pw, ph, obs.x, obsY, obs.width, obsH)) {
        if (player.hasShield) {
          player.hasShield = false;
          this.obstacles.splice(i, 1);
          this.spawnExplosionParticles(obs.x + obs.width/2, obs.y + obs.height/2);
          this.audio.play('shield_break');
          continue;
        }
        
        this.audio.play('hit');
        player.lives--;
        
        if (player.lives <= 0) {
          this.gameOver();
          return;
        }
        
        this.obstacles.splice(i, 1);
        this.spawnHitParticles();
      }
    }

    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const col = this.collectibles[i];
      
      if (this.intersects(px, py, pw, ph, col.x, col.y, col.size, col.size)) {
        this.collectItem(col);
        this.collectibles.splice(i, 1);
      }
    }

    if (player.hasMagnet) {
      for (const col of this.collectibles) {
        const dx = (px + pw/2) - (col.x + col.size/2);
        const dy = (py + ph/2) - (col.y + col.size/2);
        const dist = Math.sqrt(dx*dx + dy*dy);
        
        if (dist < 150) {
          col.x += dx * 0.1;
          col.y += dy * 0.1;
        }
      }
    }
  }

  intersects(x1, y1, w1, h1, x2, y2, w2, h2) {
    return x1 < x2 + w2 && x1 + w1 > x2 && y1 < y2 + h2 && y1 + h1 > y2;
  }

  collectItem(item) {
    this.combo = Math.min(this.combo + 1, this.maxCombo);
    this.score += 100 * this.combo;
    this.audio.play('collect');
    this.spawnCollectParticles(item.x + item.size/2, item.y + item.size/2, item.type);

    switch (item.type) {
      case 'power_cell':
        this.score += 50;
        break;
      case 'data_orb':
        this.score += 75;
        break;
      case 'shield':
        this.player.hasShield = true;
        break;
      case 'magnet':
        this.player.hasMagnet = true;
        break;
      case 'slowmo':
        this.player.hasSlowMo = true;
        break;
      case 'canvas_boost':
        window.pixelforge.showToast('Canvas Boost activated!', 'success');
        break;
    }
  }

  spawnJumpParticles() {
    for (let i = 0; i < 8; i++) {
      this.particles.push({
        x: this.player.x + this.player.width/2,
        y: GROUND_Y,
        vx: (Math.random() - 0.5) * 4,
        vy: -Math.random() * 3,
        size: 3 + Math.random() * 3,
        color: '#00ff88',
        alpha: 1
      });
    }
  }

  spawnLandParticles() {
    for (let i = 0; i < 6; i++) {
      this.particles.push({
        x: this.player.x + this.player.width/2,
        y: GROUND_Y,
        vx: (Math.random() - 0.5) * 6,
        vy: -Math.random() * 2,
        size: 2 + Math.random() * 2,
        color: '#00ff88',
        alpha: 1
      });
    }
  }

  spawnCollectParticles(x, y, type) {
    const color = type === 'power_cell' ? '#ffd700' : 
                  type === 'data_orb' ? '#00ffff' : '#ff00ff';
    
    for (let i = 0; i < 12; i++) {
      const angle = (Math.PI * 2 / 12) * i;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * 4,
        vy: Math.sin(angle) * 4,
        size: 4,
        color,
        alpha: 1
      });
    }
  }

  spawnExplosionParticles(x, y) {
    for (let i = 0; i < 20; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 2 + Math.random() * 5;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        size: 3 + Math.random() * 4,
        color: i % 2 === 0 ? '#ff4444' : '#ffaa00',
        alpha: 1
      });
    }
  }

  spawnHitParticles() {
    for (let i = 0; i < 10; i++) {
      this.particles.push({
        x: this.player.x + this.player.width/2,
        y: this.player.y + this.player.height/2,
        vx: (Math.random() - 0.5) * 8,
        vy: (Math.random() - 0.5) * 8,
        size: 4,
        color: '#ff4444',
        alpha: 1
      });
    }
  }

  async gameOver() {
    this.gameState = 'gameover';
    this.audio.play('gameover');
    
    const finalScore = this.score;
    const pxlEarned = Math.floor(finalScore / 100);

    document.getElementById('finalScore').textContent = finalScore.toLocaleString();
    document.getElementById('pxlEarned').textContent = pxlEarned;
    document.getElementById('gameOverScreen')?.classList.remove('hidden');

    if (this.sessionToken && this.signingKey) {
      try {
        const hmacData = finalScore.toString() + JSON.stringify(this.checkpoints);
        const hmac = await this.computeHmac(hmacData, this.signingKey);
        
        await window.pixelforge.api.post('/game/submit', {
          sessionToken: this.sessionToken,
          score: finalScore,
          checkpoints: this.checkpoints,
          checkpointsHmac: hmac,
          duration: Date.now() - this.startTime,
          obstaclesHit: 0,
          powerUpsCollected: 0
        });
      } catch (err) {
        console.error('Failed to submit score:', err);
      }
    }
  }

  async computeHmac(data, key) {
    const encoder = new TextEncoder();
    const keyData = encoder.encode(key);
    const cryptoKey = await crypto.subtle.importKey(
      'raw', keyData, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
    );
    const signature = await crypto.subtle.sign('HMAC', cryptoKey, encoder.encode(data));
    return Array.from(new Uint8Array(signature)).map(b => b.toString(16).padStart(2, '0')).join('');
  }

  updateUI() {
    const scoreEl = document.getElementById('score');
    const highScoreEl = document.getElementById('highScore');
    const levelEl = document.getElementById('level');
    const comboEl = document.getElementById('combo');
    
    if (scoreEl) scoreEl.textContent = this.score.toLocaleString();
    if (highScoreEl) highScoreEl.textContent = this.highScore.toLocaleString();
    if (levelEl) levelEl.textContent = this.level;
    if (comboEl) comboEl.textContent = `x${Math.max(1, this.combo)}`;

    const shieldEl = document.querySelector('[data-type="shield"]');
    const magnetEl = document.querySelector('[data-type="magnet"]');
    const slowmoEl = document.querySelector('[data-type="slowmo"]');

    shieldEl?.classList.toggle('active', this.player.hasShield);
    magnetEl?.classList.toggle('active', this.player.hasMagnet);
    slowmoEl?.classList.toggle('active', this.player.hasSlowMo);
  }

  render() {
    this.renderer.render();

    for (const obs of this.obstacles) {
      this.renderer.renderObstacle(obs);
    }

    this.renderer.renderPlayer(this.player);

    for (const col of this.collectibles) {
      this.renderer.renderCollectible(col);
    }

    for (const p of this.particles) {
      this.renderer.renderParticle(p);
    }
  }

  gameLoop(timestamp) {
    this.deltaTime = timestamp - this.lastFrameTime;
    if (this.deltaTime > 100) this.deltaTime = 16.67;
    this.lastFrameTime = timestamp;

    if (this.gameState === 'playing') {
      this.startTime = this.startTime || Date.now();
      this.update();
    }

    this.render();
    requestAnimationFrame((t) => this.gameLoop(t));
  }
}

window.Game = Game;

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('gameCanvas')) {
    window.game = new Game('gameCanvas');
  }
});