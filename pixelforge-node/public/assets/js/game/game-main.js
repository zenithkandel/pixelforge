const CANVAS_WIDTH = 900;
const CANVAS_HEIGHT = 400;
const GROUND_Y = 320;
const PLAYER_SIZE = 32;
const PLAYER_X = 80;
const BASE_SPEED = 3;
const SPEED_INCREMENT = 0.5;
const MAX_SPEED = 8;
const GRAVITY = 0.5;
const JUMP_FORCE = -12;
const SLIDE_DURATION = 400;

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
      lives: 3,
      canDoubleJump: true,
      invincible: false,
      invincibleTimer: 0
    };
    
    this.obstacles = [];
    this.collectibles = [];
    this.particles = [];
    
    this.score = 0;
    this.highScore = parseInt(localStorage.getItem('highScore')) || 0;
    this.combo = 0;
    this.level = 1;
    this.speed = BASE_SPEED;
    this.distance = 0;
    
    this.gameState = 'menu';
    this.sessionToken = null;
    this.gameStartTime = 0;
    this.gameDuration = 0;
    
    this.lastObstacleSpawn = 0;
    this.lastCollectibleSpawn = 0;
    this.lastFrameTime = 0;
    this.deltaTime = 0;
    
    this.audio = new GameAudio();
    this.keys = { jump: false, slide: false };
    
    this.init();
  }

  init() {
    this.setupControls();
    this.updateUI();
    this.gameLoop(0);
  }

  setupControls() {
    document.addEventListener('keydown', (e) => {
      if (e.code === 'Space' || e.code === 'ArrowUp') {
        e.preventDefault();
        if (this.gameState === 'playing') this.jump();
      }
      if (e.code === 'ArrowDown') {
        e.preventDefault();
        if (this.gameState === 'playing') this.slide();
      }
    });

    document.getElementById('startBtn')?.addEventListener('click', () => this.startGame());
    document.getElementById('restartBtn')?.addEventListener('click', () => this.startGame());
    document.getElementById('homeBtn')?.addEventListener('click', () => window.location.href = '/');

    this.canvas.addEventListener('touchstart', (e) => {
      e.preventDefault();
      const rect = this.canvas.getBoundingClientRect();
      const y = e.touches[0].clientY - rect.top;
      if (y < rect.height / 2) this.jump();
      else this.slide();
    });
  }

  async startGame() {
    try {
      const response = await pixelforge.api.startGame();
      if (response.ok && response.data) {
        this.sessionToken = response.data.sessionToken;
        this.prng = new PRNG(response.data.seed);
      } else {
        this.prng = new PRNG(Math.floor(Math.random() * 0xFFFFFFFF));
      }
    } catch (err) {
      this.prng = new PRNG(Math.floor(Math.random() * 0xFFFFFFFF));
    }

    this.resetPlayer();
    this.obstacles = [];
    this.collectibles = [];
    this.particles = [];
    this.score = 0;
    this.combo = 0;
    this.level = 1;
    this.speed = BASE_SPEED;
    this.distance = 0;

    this.gameState = 'playing';
    this.gameStartTime = Date.now();
    document.getElementById('startScreen')?.classList.add('hidden');
    document.getElementById('gameOverScreen')?.classList.add('hidden');

    this.audio.play('start');
    this.updateUI();
  }

  resetPlayer() {
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
      lives: 3,
      canDoubleJump: true,
      invincible: false,
      invincibleTimer: 0
    };
  }

  jump() {
    if (this.player.state === 'grounded' || this.player.y >= GROUND_Y - PLAYER_SIZE) {
      this.player.velocityY = JUMP_FORCE;
      this.player.state = 'jumping';
      this.player.canDoubleJump = true;
      this.audio.play('jump');
      this.spawnJumpParticles();
    } else if (this.player.canDoubleJump && this.player.state === 'jumping') {
      this.player.velocityY = JUMP_FORCE * 0.8;
      this.player.canDoubleJump = false;
      this.audio.play('jump');
      this.spawnJumpParticles();
    }
  }

  slide() {
    if (this.player.state !== 'jumping' && this.player.state !== 'sliding') {
      this.player.state = 'sliding';
      this.player.slideTimer = SLIDE_DURATION;
      this.player.height = PLAYER_SIZE * 0.5;
    }
  }

  update(dt) {
    if (this.gameState !== 'playing') return;

    const effectiveSpeed = this.player.hasSlowMo ? this.speed * 0.5 : this.speed;
    const normalizedDt = dt / 16.67;

    this.distance += effectiveSpeed * normalizedDt;
    this.score += Math.floor(effectiveSpeed * normalizedDt * 0.5);

    this.level = Math.floor(this.distance / 800) + 1;
    this.speed = Math.min(BASE_SPEED + (this.level - 1) * SPEED_INCREMENT, MAX_SPEED);

    if (this.player.invincible) {
      this.player.invincibleTimer -= dt;
      if (this.player.invincibleTimer <= 0) {
        this.player.invincible = false;
      }
    }

    this.updatePlayer(normalizedDt);
    this.updateObstacles(effectiveSpeed, normalizedDt);
    this.updateCollectibles(effectiveSpeed, normalizedDt);
    this.updateParticles(normalizedDt);
    this.checkCollisions();
    this.updateUI();

    if (this.score > this.highScore) {
      this.highScore = this.score;
      localStorage.setItem('highScore', this.highScore);
    }
  }

  updatePlayer(dt) {
    this.player.velocityY += GRAVITY * dt;
    this.player.y += this.player.velocityY * dt;

    if (this.player.y >= GROUND_Y - PLAYER_SIZE) {
      this.player.y = GROUND_Y - PLAYER_SIZE;
      this.player.velocityY = 0;
      this.player.state = 'grounded';
      this.player.canDoubleJump = true;
    }

    if (this.player.slideTimer > 0) {
      this.player.slideTimer -= this.deltaTime;
      if (this.player.slideTimer <= 0) {
        this.player.state = 'grounded';
        this.player.height = PLAYER_SIZE;
      }
    } else if (this.player.state === 'sliding' && this.player.slideTimer <= 0) {
      this.player.height = PLAYER_SIZE;
    }

    this.player.frame += 0.15 * dt;
  }

  updateObstacles(speed, dt) {
    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      obs.x -= speed * dt;
      
      if (obs.type === 'virus' || obs.type === 'virus_swarm') {
        obs.y += Math.sin(Date.now() * 0.005 + obs.phase) * 1.5;
      }
      
      if (obs.x + obs.width < 0) {
        this.obstacles.splice(i, 1);
        this.combo = 0;
      }
    }

    const spawnInterval = Math.max(80, 200 - this.level * 15);
    if (this.distance - this.lastObstacleSpawn > spawnInterval) {
      this.spawnObstacle();
      this.lastObstacleSpawn = this.distance;
    }
  }

  spawnObstacle() {
    if (!this.prng) return;
    
    const types = ['virus', 'firewall', 'malware'];
    const type = this.prng.pick(types);
    
    const baseY = GROUND_Y - PLAYER_SIZE;
    
    const obs = { x: CANVAS_WIDTH + 50, type, frame: 0 };

    switch (type) {
      case 'virus':
        obs.y = baseY;
        obs.width = 25;
        obs.height = 25;
        obs.color = '#ff4444';
        break;
      case 'firewall':
        obs.y = baseY - this.prng.nextInt(20, 60);
        obs.width = 20;
        obs.height = 40 + this.prng.nextInt(20, 40);
        obs.color = '#ff6600';
        break;
      case 'malware':
        obs.y = baseY - 20;
        obs.width = 30;
        obs.height = 50;
        obs.color = '#9933ff';
        break;
    }

    this.obstacles.push(obs);
  }

  updateCollectibles(speed, dt) {
    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const col = this.collectibles[i];
      col.x -= speed * dt;
      col.frame += 0.1 * dt;
      if (col.x + col.size < 0) this.collectibles.splice(i, 1);
    }

    if (this.distance - this.lastCollectibleSpawn > 150 + Math.random() * 100) {
      this.spawnCollectible();
      this.lastCollectibleSpawn = this.distance;
    }
  }

  spawnCollectible() {
    if (!this.prng) return;
    
    const types = ['power_cell', 'data_orb'];
    const type = this.prng.pick(types);
    
    this.collectibles.push({
      x: CANVAS_WIDTH + 20,
      y: GROUND_Y - 60 - Math.random() * 80,
      size: 18,
      type,
      frame: 0
    });
  }

  updateParticles(dt) {
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      p.vy += 0.2 * dt;
      p.alpha -= 0.03 * dt;
      if (p.alpha <= 0) this.particles.splice(i, 1);
    }
  }

  checkCollisions() {
    const px = this.player.x;
    const py = this.player.y;
    const pw = this.player.width * 0.8;
    const ph = this.player.height * 0.8;

    for (let i = this.obstacles.length - 1; i >= 0; i--) {
      const obs = this.obstacles[i];
      
      if (this.intersects(px, py, pw, ph, obs.x + 5, obs.y + 5, obs.width - 10, obs.height - 10)) {
        if (this.player.invincible) continue;
        
        if (this.player.hasShield) {
          this.player.hasShield = false;
          this.obstacles.splice(i, 1);
          this.spawnExplosion(obs.x, obs.y);
          this.audio.play('shield_break');
          continue;
        }
        
        this.player.lives--;
        this.player.invincible = true;
        this.player.invincibleTimer = 1500;
        this.audio.play('hit');
        this.spawnHitParticles();
        
        if (this.player.lives <= 0) {
          this.gameOver();
          return;
        }
        break;
      }
    }

    for (let i = this.collectibles.length - 1; i >= 0; i--) {
      const col = this.collectibles[i];
      if (this.intersects(px, py, pw, ph, col.x, col.y, col.size, col.size)) {
        this.collectItem(col);
        this.collectibles.splice(i, 1);
      }
    }

    if (this.player.hasMagnet) {
      for (const col of this.collectibles) {
        const dx = (px + pw/2) - (col.x + col.size/2);
        const dy = (py + ph/2) - (col.y + col.size/2);
        const dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < 120) {
          col.x += dx * 0.08;
          col.y += dy * 0.08;
        }
      }
    }
  }

  intersects(x1, y1, w1, h1, x2, y2, w2, h2) {
    return x1 < x2 + w2 && x1 + w1 > x2 && y1 < y2 + h2 && y1 + h1 > y2;
  }

  collectItem(item) {
    this.combo = Math.min(this.combo + 1, 5);
    this.score += 50 * this.combo;
    this.audio.play('collect');
    this.spawnCollectParticles(item.x, item.y);

    switch (item.type) {
      case 'power_cell':
        this.score += 30;
        break;
      case 'data_orb':
        this.player.hasShield = true;
        break;
      case 'magnet':
        this.player.hasMagnet = true;
        break;
      case 'slowmo':
        this.player.hasSlowMo = true;
        break;
    }
  }

  spawnJumpParticles() {
    for (let i = 0; i < 6; i++) {
      this.particles.push({
        x: this.player.x + this.player.width/2,
        y: GROUND_Y,
        vx: (Math.random() - 0.5) * 3,
        vy: -Math.random() * 2,
        size: 3,
        color: '#00ff88',
        alpha: 1
      });
    }
  }

  spawnCollectParticles(x, y) {
    const color = '#ffd700';
    for (let i = 0; i < 8; i++) {
      const angle = (Math.PI * 2 / 8) * i;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * 3,
        vy: Math.sin(angle) * 3,
        size: 4,
        color,
        alpha: 1
      });
    }
  }

  spawnHitParticles() {
    for (let i = 0; i < 8; i++) {
      this.particles.push({
        x: this.player.x + this.player.width/2,
        y: this.player.y + this.player.height/2,
        vx: (Math.random() - 0.5) * 6,
        vy: (Math.random() - 0.5) * 6,
        size: 4,
        color: '#ff4444',
        alpha: 1
      });
    }
  }

  spawnExplosion(x, y) {
    for (let i = 0; i < 12; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 2 + Math.random() * 4;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        size: 4,
        color: i % 2 === 0 ? '#ff4444' : '#ffaa00',
        alpha: 1
      });
    }
  }

  async gameOver() {
    this.gameState = 'gameover';
    this.audio.play('gameover');

    const finalScore = this.score;
    const pxlEarned = Math.floor(finalScore / 100);
    this.gameDuration = this.gameStartTime > 0 ? Date.now() - this.gameStartTime : 0;

    document.getElementById('finalScore').textContent = finalScore.toLocaleString();
    document.getElementById('pxlEarned').textContent = pxlEarned;
    document.getElementById('gameOverScreen')?.classList.remove('hidden');

    if (this.sessionToken) {
      try {
        const response = await pixelforge.api.submitScore(this.sessionToken, finalScore, this.gameDuration);
        if (response.ok && response.data?.newBalance !== undefined) {
          const newBalance = response.data.newBalance;
          if (window.pixelforge.auth?.user) {
            window.pixelforge.auth.user.pxlBalance = newBalance;
          }
          const balanceEl = document.getElementById('navBalance');
          if (balanceEl) balanceEl.textContent = newBalance;
        }
      } catch (err) {
        console.error('Failed to submit score:', err);
      }
    }
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

    if (shieldEl) shieldEl.classList.toggle('active', this.player.hasShield);
    if (magnetEl) magnetEl.classList.toggle('active', this.player.hasMagnet);
    if (slowmoEl) slowmoEl.classList.toggle('active', this.player.hasSlowMo);
  }

  render() {
    this.renderer.render();

    for (const obs of this.obstacles) {
      obs.frame++;
      this.renderer.renderObstacle(obs);
    }

    if (!this.player.invincible || Math.floor(Date.now() / 100) % 2 === 0) {
      this.renderer.renderPlayer(this.player);
    }

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
      this.update(this.deltaTime);
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