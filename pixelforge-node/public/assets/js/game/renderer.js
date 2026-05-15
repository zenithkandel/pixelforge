const CANVAS_WIDTH = 900;
const CANVAS_HEIGHT = 400;
const GROUND_Y = 320;

class GameRenderer {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.canvas.width = CANVAS_WIDTH;
    this.canvas.height = CANVAS_HEIGHT;
    this.stars = this.createStars(50);
  }

  createStars(count) {
    const stars = [];
    for (let i = 0; i < count; i++) {
      stars.push({
        x: Math.random() * CANVAS_WIDTH,
        y: Math.random() * (GROUND_Y - 50),
        size: Math.random() * 2 + 0.5,
        speed: Math.random() * 2 + 1,
        brightness: Math.random()
      });
    }
    return stars;
  }

  clear() {
    this.ctx.fillStyle = '#0a0a0f';
    this.ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
  }

  renderBackground(speed = 3) {
    for (const star of this.stars) {
      star.x -= star.speed * (speed / 5);
      if (star.x < 0) {
        star.x = CANVAS_WIDTH;
        star.y = Math.random() * (GROUND_Y - 50);
      }
      
      const flicker = 0.5 + Math.sin(Date.now() * 0.005 + star.brightness * 10) * 0.5;
      this.ctx.fillStyle = `rgba(255, 255, 255, ${0.3 + flicker * 0.7})`;
      this.ctx.beginPath();
      this.ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
      this.ctx.fill();
    }

    this.ctx.fillStyle = '#1a1a26';
    this.ctx.fillRect(0, GROUND_Y, CANVAS_WIDTH, CANVAS_HEIGHT - GROUND_Y);
    
    this.ctx.strokeStyle = '#00ff88';
    this.ctx.lineWidth = 3;
    this.ctx.beginPath();
    this.ctx.moveTo(0, GROUND_Y);
    this.ctx.lineTo(CANVAS_WIDTH, GROUND_Y);
    this.ctx.stroke();
  }

  render() {
    this.clear();
    this.renderBackground(3);
  }

  renderPlayer(player) {
    const ctx = this.ctx;
    const { x, y, width, height, state, frame } = player;
    
    ctx.save();
    ctx.translate(x + width / 2, y + height / 2);

    if (state === 'sliding') {
      ctx.scale(1.3, 0.6);
    }
    if (state === 'jumping') {
      ctx.rotate(-0.05);
    }

    const glowColor = player.hasShield ? '#00aaff' : '#00ff88';
    const bodyColor = player.hasShield ? '#0088cc' : '#00dd77';
    
    ctx.shadowColor = glowColor;
    ctx.shadowBlur = 10;

    ctx.fillStyle = bodyColor;
    ctx.fillRect(-width/2, -height/2, width, height);

    ctx.shadowBlur = 0;

    ctx.fillStyle = '#ffffff';
    ctx.fillRect(-width/2 + 4, -height/2 + 4, 6, 6);
    ctx.fillRect(width/2 - 10, -height/2 + 4, 6, 6);

    ctx.fillStyle = '#001a0d';
    const mouthY = -height/2 + 14;
    ctx.fillRect(-6, mouthY, 12, 4);

    ctx.fillStyle = bodyColor;
    ctx.fillRect(-width/2 - 4, -height/2 + 6, 4, 12);
    ctx.fillRect(width/2, -height/2 + 6, 4, 12);

    if (player.hasShield) {
      ctx.strokeStyle = '#00aaff';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(0, 0, width * 0.9, 0, Math.PI * 2);
      ctx.stroke();
    }

    if (state === 'grounded') {
      const legOffset = Math.sin(frame * 0.3) * 3;
      ctx.fillStyle = bodyColor;
      ctx.fillRect(-6, height/2 - 2, 5, 4 + legOffset);
      ctx.fillRect(1, height/2 - 2, 5, 4 - legOffset);
    }

    ctx.restore();
  }

  renderObstacle(obs) {
    const ctx = this.ctx;
    const { x, y, width, height, type, color } = obs;
    
    ctx.save();
    
    if (type === 'virus') {
      const pulse = 1 + Math.sin(Date.now() * 0.01) * 0.1;
      ctx.translate(x + width/2, y + height/2);
      ctx.scale(pulse, pulse);
      ctx.translate(-width/2, -height/2);
      
      ctx.fillStyle = color;
      ctx.shadowColor = '#ff0000';
      ctx.shadowBlur = 10;
      ctx.beginPath();
      ctx.arc(width/2, height/2, width/2, 0, Math.PI * 2);
      ctx.fill();
      
      ctx.fillStyle = '#ffffff';
      ctx.beginPath();
      ctx.arc(width/2 - 3, height/2 - 2, 3, 0, Math.PI * 2);
      ctx.arc(width/2 + 3, height/2 - 2, 3, 0, Math.PI * 2);
      ctx.fill();
    } else if (type === 'firewall') {
      ctx.fillStyle = color;
      ctx.shadowColor = '#ff3300';
      ctx.shadowBlur = 8;
      ctx.fillRect(0, 0, width, height);
      
      ctx.fillStyle = 'rgba(255, 200, 0, 0.6)';
      for (let i = 0; i < height; i += 8) {
        ctx.fillRect(0, i, width, 3);
      }
    } else if (type === 'malware') {
      ctx.fillStyle = color;
      ctx.shadowColor = '#6600cc';
      ctx.shadowBlur = 8;
      
      ctx.beginPath();
      ctx.moveTo(width/2, 0);
      ctx.lineTo(width, height);
      ctx.lineTo(0, height);
      ctx.closePath();
      ctx.fill();
      
      ctx.fillStyle = '#ffffff';
      ctx.font = '12px monospace';
      ctx.fillText('!', width/2 - 3, height - 6);
    } else {
      ctx.fillStyle = color;
      ctx.shadowColor = '#ff0000';
      ctx.shadowBlur = 8;
      ctx.fillRect(0, 0, width, height);
    }

    ctx.restore();
  }

  renderCollectible(col) {
    const ctx = this.ctx;
    const { x, y, size, type, frame } = col;
    
    ctx.save();
    ctx.translate(x + size/2, y + size/2);
    
    const bob = Math.sin(Date.now() * 0.005 + frame) * 3;
    ctx.translate(0, bob);
    
    const pulse = 1 + Math.sin(Date.now() * 0.008) * 0.15;
    ctx.scale(pulse, pulse);

    if (type === 'power_cell') {
      ctx.fillStyle = '#ffd700';
      ctx.shadowColor = '#ffaa00';
      ctx.shadowBlur = 12;
      ctx.beginPath();
      ctx.arc(0, 0, size/2, 0, Math.PI * 2);
      ctx.fill();
      
      ctx.fillStyle = '#ffffff';
      ctx.font = '10px Arial';
      ctx.textAlign = 'center';
      ctx.fillText('⚡', 0, 4);
    } else if (type === 'data_orb') {
      ctx.fillStyle = '#00ffff';
      ctx.shadowColor = '#00aaff';
      ctx.shadowBlur = 10;
      ctx.fillRect(-size/2, -size/2, size, size);
      
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 2;
      ctx.strokeRect(-size/2 + 2, -size/2 + 2, size - 4, size - 4);
    }

    ctx.restore();
  }

  renderParticle(p) {
    const ctx = this.ctx;
    ctx.save();
    ctx.globalAlpha = p.alpha;
    ctx.fillStyle = p.color;
    ctx.shadowColor = p.color;
    ctx.shadowBlur = 5;
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
  }
}

window.GameRenderer = GameRenderer;