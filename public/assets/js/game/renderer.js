const SPEED_TIERS = [
  { bps: 5.0, minScore: 0 },
  { bps: 6.5, minScore: 300 },
  { bps: 8.0, minScore: 800 },
  { bps: 10.0, minScore: 1800 },
  { bps: 12.0, minScore: 3500 },
  { bps: 14.0, minScore: 6000 },
  { bps: 15.5, minScore: 10000 },
];

const GROUND_Y = 300;

class GameRenderer {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.hue = 0;
  }

  clear() {
    this.ctx.fillStyle = '#0a0a1a';
    this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
  }

  drawBackground(elapsedMs) {
    this.ctx.strokeStyle = 'rgba(0, 245, 255, 0.05)';
    this.ctx.lineWidth = 1;
    for (let x = 0; x < this.canvas.width; x += 40) {
      this.ctx.beginPath();
      this.ctx.moveTo(x, 0);
      this.ctx.lineTo(x, this.canvas.height);
      this.ctx.stroke();
    }
    for (let y = 0; y < this.canvas.height; y += 40) {
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      this.ctx.lineTo(this.canvas.width, y);
      this.ctx.stroke();
    }
  }

  drawGround() {
    this.ctx.fillStyle = '#111122';
    this.ctx.fillRect(0, GROUND_Y, this.canvas.width, 60);
    this.ctx.fillStyle = '#00f5ff';
    this.ctx.fillRect(0, GROUND_Y, this.canvas.width, 2);
  }

  drawPlayer(pxlr, powerupColor, elapsedMs) {
    const ctx = this.ctx;
    const x = pxlr.x;
    const y = pxlr.y;

    if (pxlr.invincible && Math.floor(elapsedMs / 100) % 2 === 0) return;

    if (powerupColor) {
      ctx.shadowColor = powerupColor;
      ctx.shadowBlur = 10;
    }

    ctx.fillStyle = '#00f5ff';
    if (pxlr.isSliding) {
      ctx.fillRect(x, y + 12, 32, 20);
    } else {
      ctx.fillRect(x, y, 20, 32);
      ctx.fillStyle = '#fff';
      ctx.fillRect(x + 12, y + 6, 6, 6);
    }

    ctx.shadowBlur = 0;
  }

  drawObstacles(obstacles) {
    const ctx = this.ctx;
    obstacles.forEach(o => {
      ctx.fillStyle = o.type === 'CRAWL_BARRIER' ? '#ff6b6b' :
                      o.type === 'FIREWALL_BEAM' || o.type === 'HIGH_BEAM' ? '#ff3366' :
                      o.type === 'DATA_SPIKE' ? '#ff6600' :
                      '#9b59b6';
      ctx.shadowColor = ctx.fillStyle;
      ctx.shadowBlur = 8;
      ctx.fillRect(o.x, o.y, o.width, o.height);
      ctx.shadowBlur = 0;
    });
  }

  drawShards(shards) {
    const ctx = this.ctx;
    shards.forEach(s => {
      if (s.color === 'rainbow') {
        this.hue = (this.hue + 2) % 360;
        ctx.fillStyle = `hsl(${this.hue}, 100%, 60%)`;
      } else {
        ctx.fillStyle = s.color;
      }
      ctx.shadowColor = ctx.fillStyle;
      ctx.shadowBlur = 6;
      ctx.beginPath();
      ctx.arc(s.x, s.y, s.size / 2, 0, Math.PI * 2);
      ctx.fill();
      ctx.shadowBlur = 0;
    });
  }

  drawPowerCells(powerCells) {
    const ctx = this.ctx;
    powerCells.forEach(p => {
      ctx.fillStyle = '#fff';
      ctx.shadowColor = '#fff';
      ctx.shadowBlur = 12;
      ctx.beginPath();
      for (let i = 0; i < 6; i++) {
        const angle = (i / 6) * Math.PI * 2 - Math.PI / 2;
        const r = 8;
        if (i === 0) ctx.moveTo(p.x + Math.cos(angle) * r, p.y + Math.sin(angle) * r);
        else ctx.lineTo(p.x + Math.cos(angle) * r, p.y + Math.sin(angle) * r);
      }
      ctx.closePath();
      ctx.fill();
      ctx.shadowBlur = 0;
    });
  }

  drawParticles(particles) {
    const ctx = this.ctx;
    particles.forEach(p => {
      ctx.fillStyle = `rgba(0, 245, 255, ${p.alpha})`;
      ctx.fillRect(p.x, p.y, 2, 2);
    });
  }
}

export { GameRenderer, GROUND_Y, SPEED_TIERS };