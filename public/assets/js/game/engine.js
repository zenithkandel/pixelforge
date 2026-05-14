import { SeededPRNG } from './prng.js';
import { ObstacleManager, GROUND_Y, GRAVITY, JUMP_VELOCITY, DOUBLE_JUMP_VELOCITY, SLIDE_DURATION } from './obstacles.js';
import { CollectibleManager } from './collectibles.js';
const POWERUP_DURATIONS = { SHIELD: 8000, MAGNET: 12000, TIMEWARP: 6000, SCORE_SURGE: 15000 };
import { AudioManager } from './audio.js';
import { HUD } from './hud.js';
import { GameRenderer, SPEED_TIERS } from './renderer.js';

class GameEngine {
  constructor(canvas, seed, userId) {
    this.canvas = canvas;
    this.prng = new SeededPRNG(seed);
    this.renderer = new GameRenderer(canvas);
    this.obstacles = new ObstacleManager(this.prng);
    this.collectibles = new CollectibleManager(this.prng);
    this.audio = new AudioManager();
    this.hud = new HUD();
    this.userId = userId;

    this.state = {
      running: false,
      paused: false,
      score: 0,
      lives: 3,
      combo: 0,
      comboTimer: 0,
      speedBPS: 5.0,
      speedTier: 1,
      elapsedMs: 0,
      lastCheckpointMs: 0,
      activePowerup: null,
      powerupExpiresAt: null,
      pxlr: { x: 100, y: GROUND_Y - 32, vy: 0, isSliding: false, canDoubleJump: true, invincible: false, invincibleUntil: 0 },
      powerupColor: null,
      particles: [],
    };

    this.sessionId = null;
    this.hmac = null;
    this.startTimestamp = null;
    this.checkpoints = [];
    this.animFrame = null;
    this.lastTimestamp = 0;

    this.registerInputs();
  }

  registerInputs() {
    this.keys = {};
    window.addEventListener('keydown', e => {
      if (['Space', 'ArrowUp', 'KeyW', 'ArrowDown', 'KeyS', 'Escape', 'KeyP'].includes(e.code)) {
        e.preventDefault();
      }
      this.keys[e.code] = true;
      if (['Space', 'ArrowUp', 'KeyW'].includes(e.code)) this.onJump();
      if (['ArrowDown', 'KeyS'].includes(e.code)) this.onSlide();
      if (['Escape', 'KeyP'].includes(e.code)) this.togglePause();
    });
    window.addEventListener('keyup', e => { this.keys[e.code] = false; });

    window.addEventListener('touchstart', e => {
      const x = e.touches[0].clientX;
      if (x < window.innerWidth / 2) this.onJump();
      else this.onSlide();
    });
  }

  onJump() {
    if (!this.state.running || this.state.paused) return;
    const p = this.state.pxlr;
    if (p.y >= GROUND_Y - 32 - 2) {
      p.vy = JUMP_VELOCITY;
      p.canDoubleJump = true;
      this.audio.jump();
    } else if (p.canDoubleJump) {
      p.vy = DOUBLE_JUMP_VELOCITY;
      p.canDoubleJump = false;
      this.audio.jump();
    }
  }

  onSlide() {
    if (!this.state.running || this.state.paused) return;
    const p = this.state.pxlr;
    if (p.y >= GROUND_Y - 32 - 2) {
      p.isSliding = true;
      p.slideUntil = this.state.elapsedMs + SLIDE_DURATION * 1000;
    }
  }

  async start(sessionId, seed, hmac) {
    this.sessionId = sessionId;
    this.hmac = hmac;
    this.audio.init();

    this.state.running = true;
    this.state.paused = false;
    this.state.score = 0;
    this.state.lives = 3;
    this.state.combo = 0;
    this.state.elapsedMs = 0;
    this.state.speedTier = 1;
    this.state.speedBPS = 5.0;
    this.state.activePowerup = null;
    this.state.pxlr = { x: 100, y: GROUND_Y - 32, vy: 0, isSliding: false, canDoubleJump: true, invincible: false, invincibleUntil: 0 };
    this.state.particles = [];
    this.obstacles.obstacles = [];
    this.collectibles.shards = [];
    this.collectibles.powerCells = [];

    this.startTimestamp = performance.now();
    this.lastTimestamp = this.startTimestamp;
    this.animFrame = requestAnimationFrame(this.gameLoop.bind(this));
  }

  togglePause() {
    if (!this.state.running) return;
    this.state.paused = !this.state.paused;
    if (!this.state.paused) {
      this.lastTimestamp = performance.now();
      this.animFrame = requestAnimationFrame(this.gameLoop.bind(this));
    }
  }

  gameLoop(timestamp) {
    if (this.state.paused) return;

    const dt = Math.min((timestamp - this.lastTimestamp) / 1000, 0.05);
    this.lastTimestamp = timestamp;
    this.state.elapsedMs += dt * 1000;

    this.update(dt);
    this.render();

    this.maybeSendCheckpoint();

    if (this.state.running) {
      this.animFrame = requestAnimationFrame(this.gameLoop.bind(this));
    }
  }

  update(dt) {
    const s = this.state;
    const p = s.pxlr;

    p.vy += GRAVITY * dt;
    p.y += p.vy * dt;

    if (p.y >= GROUND_Y - 32) {
      p.y = GROUND_Y - 32;
      p.vy = 0;
      p.canDoubleJump = true;
    }
    if (p.isSliding && s.elapsedMs >= p.slideUntil) {
      p.isSliding = false;
    }

    if (p.invincible && s.elapsedMs >= p.invincibleUntil) {
      p.invincible = false;
    }

    this.updateSpeed();
    this.obstacles.setSpeedTier(s.speedTier);
    this.obstacles.update(dt, s.speedBPS, s.elapsedMs);
    this.collectibles.update(dt, s.elapsedMs, s.speedBPS);

    if (s.activePowerup === 'SHIELD' && s.elapsedMs >= s.powerupExpiresAt) {
      s.activePowerup = null;
      s.powerupColor = null;
    }

    const shard = this.collectibles.checkShardCollision(p);
    if (shard) {
      let value = shard.value;
      if (s.activePowerup === 'SCORE_SURGE') value *= 3;
      s.score += Math.floor(value * this.getComboMultiplier());
      s.combo++;
      s.comboTimer = 400;
      this.audio.collect();

      if (shard.type === 'RAINBOW') {
        // track rainbow shards for achievement
      }
    }

    if (s.comboTimer > 0) {
      s.comboTimer -= dt * 1000;
    } else if (s.combo > 0) {
      s.combo = 0;
    }

    if (this.collectibles.checkPowerupCollision(p)) {
      const type = this.collectibles.getRandomPowerup();
      if (type === 'EXTRA_LIFE') {
        s.lives = Math.min(3, s.lives + 1);
      } else if (type === 'PIXEL_BOMB') {
        this.explodeObstacles();
      } else {
        s.activePowerup = type;
        s.powerupExpiresAt = s.elapsedMs + (CollectibleManager.POWERUP_DURATIONS || { SHIELD: 8000, MAGNET: 12000, TIMEWARP: 6000, SCORE_SURGE: 15000 })[type];
        const colors = { SHIELD: '#3b82f6', MAGNET: '#eab308', TIMEWARP: '#a855f7', SCORE_SURGE: '#f97316' };
        s.powerupColor = colors[type];
      }
      this.audio.powerup();
    }

    if (this.obstacles.checkCollision(p, p.isSliding)) {
      this.onHit();
    }

    this.updateParticles(dt);
    this.hud.update(s);
  }

  explodeObstacles() {
    const obs = this.obstacles.obstacles;
    obs.forEach(o => {
      this.state.score += 1;
      this.state.particles.push({ x: o.x + o.width / 2, y: o.y + o.height / 2, vx: (Math.random() - 0.5) * 200, vy: -Math.random() * 200, alpha: 1 });
    });
    this.obstacles.obstacles = [];
  }

  onHit() {
    const s = this.state;
    const p = s.pxlr;
    if (p.invincible) return;

    if (s.activePowerup === 'SHIELD') {
      s.activePowerup = null;
      s.powerupColor = null;
      p.invincible = true;
      p.invincibleUntil = s.elapsedMs + 2500;
      this.audio.hit();
      return;
    }

    s.lives--;
    this.audio.hit();

    if (s.lives <= 0) {
      this.gameOver();
    } else {
      p.invincible = true;
      p.invincibleUntil = s.elapsedMs + 2500;
    }
  }

  updateSpeed() {
    const s = this.state;
    for (let i = SPEED_TIERS.length - 1; i >= 0; i--) {
      if (s.score >= SPEED_TIERS[i].minScore) {
        if (i + 1 !== s.speedTier) {
          s.speedTier = i + 1;
        }
        const startBPS = i > 0 ? SPEED_TIERS[i - 1].bps : 5.0;
        const endBPS = SPEED_TIERS[i].bps;
        const progress = i > 0 ? (s.score - SPEED_TIERS[i].minScore) / (SPEED_TIERS[i].minScore - SPEED_TIERS[i - 1].minScore) : 1;
        s.speedBPS = startBPS + (endBPS - startBPS) * Math.min(1, progress);
        break;
      }
    }
  }

  getComboMultiplier() {
    if (this.state.combo >= 35) return 4;
    if (this.state.combo >= 20) return 3;
    if (this.state.combo >= 10) return 2;
    if (this.state.combo >= 5) return 1.5;
    return 1;
  }

  updateParticles(dt) {
    this.state.particles.forEach(p => {
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      p.vy += 300 * dt;
      p.alpha -= dt * 2;
    });
    this.state.particles = this.state.particles.filter(p => p.alpha > 0);
  }

  render() {
    this.renderer.clear();
    this.renderer.drawBackground(this.state.elapsedMs);
    this.renderer.drawGround();
    this.renderer.drawObstacles(this.obstacles.getActive());
    this.renderer.drawShards(this.collectibles.getActive().shards);
    this.renderer.drawPowerCells(this.collectibles.getActive().powerCells);
    this.renderer.drawPlayer(this.state.pxlr, this.state.powerupColor, this.state.elapsedMs);
    this.renderer.drawParticles(this.state.particles);
  }

  async maybeSendCheckpoint() {
    const s = this.state;
    if (s.elapsedMs - s.lastCheckpointMs >= 30000 && s.elapsedMs > 0) {
      try {
        await window._checkpointFn(this.sessionId, Math.floor(s.score), s.lives, s.speedTier, s.hmac);
        s.lastCheckpointMs = s.elapsedMs;
      } catch (e) { /* ignore checkpoint errors */ }
    }
  }

  async gameOver() {
    this.state.running = false;
    cancelAnimationFrame(this.animFrame);
    this.audio.death();

    const durationMs = this.state.elapsedMs;

    try {
      const res = await window._submitFn(this.sessionId, Math.floor(this.state.score), durationMs, this.state.hmac);
      if (res.ok) {
        window._showGameOver({
          score: Math.floor(this.state.score),
          pxl: res.data.pxl_earned,
          newBalance: res.data.new_balance,
          isBest: res.data.is_new_best,
          distance: Math.floor(this.state.elapsedMs / 1000 * this.state.speedBPS),
          tier: this.state.speedTier,
        });
      }
    } catch (e) {
      window._showGameOver({
        score: Math.floor(this.state.score),
        pxl: 0,
        isBest: false,
        distance: 0,
        tier: this.state.speedTier,
      });
    }
  }

  stop() {
    this.state.running = false;
    cancelAnimationFrame(this.animFrame);
  }
}

export { GameEngine };