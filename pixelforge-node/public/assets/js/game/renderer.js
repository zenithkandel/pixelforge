const GRID_SIZE = 800;
const CANVAS_WIDTH = 900;
const CANVAS_HEIGHT = 400;
const GROUND_Y = 320;
const PLAYER_SIZE = 32;
const PLAYER_X = 80;

class GameRenderer {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');
    this.canvas.width = CANVAS_WIDTH;
    this.canvas.height = CANVAS_HEIGHT;
    
    this.groundGradient = this.createGroundGradient();
    this.stars = this.createStars(100);
    this.buildings = this.createBuildings(15);
  }

  createGroundGradient() {
    const gradient = this.ctx.createLinearGradient(0, GROUND_Y, 0, CANVAS_HEIGHT);
    gradient.addColorStop(0, '#1a1a26');
    gradient.addColorStop(0.3, '#12121a');
    gradient.addColorStop(1, '#0a0a0f');
    return gradient;
  }

  createStars(count) {
    const stars = [];
    for (let i = 0; i < count; i++) {
      stars.push({
        x: Math.random() * CANVAS_WIDTH,
        y: Math.random() * (GROUND_Y - 50),
        size: Math.random() * 2 + 0.5,
        speed: Math.random() * 2 + 0.5,
        brightness: Math.random()
      });
    }
    return stars;
  }

  createBuildings(count) {
    const buildings = [];
    for (let i = 0; i < count; i++) {
      buildings.push({
        x: i * 80 + Math.random() * 40,
        width: 40 + Math.random() * 60,
        height: 80 + Math.random() * 120,
        windows: Math.floor(Math.random() * 4) + 2,
        color: `hsl(${200 + Math.random() * 40}, 20%, ${8 + Math.random() * 8}%)`
      });
    }
    return buildings;
  }

  clear() {
    this.ctx.fillStyle = '#0a0a0f';
    this.ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);
  }

  renderBackground(speed) {
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

    for (const building of this.buildings) {
      building.x -= speed * 0.3;
      if (building.x + building.width < 0) {
        building.x = CANVAS_WIDTH + Math.random() * 100;
        building.height = 80 + Math.random() * 120;
      }

      this.ctx.fillStyle = building.color;
      this.ctx.fillRect(building.x, GROUND_Y - building.height, building.width, building.height);

      const windowSize = 4;
      const windowGap = 12;
      for (let row = 0; row < Math.floor(building.height / windowGap) - 1; row++) {
        for (let col = 0; col < building.windows; col++) {
          const wx = building.x + 8 + col * windowGap;
          const wy = GROUND_Y - building.height + 10 + row * windowGap;
          
          if (Math.random() > 0.3) {
            this.ctx.fillStyle = `rgba(255, 200, 100, ${0.3 + Math.random() * 0.4})`;
            this.ctx.fillRect(wx, wy, windowSize, windowSize);
          }
        }
      }
    }
  }

  renderGround() {
    this.ctx.fillStyle = this.groundGradient;
    this.ctx.fillRect(0, GROUND_Y, CANVAS_WIDTH, CANVAS_HEIGHT - GROUND_Y);
    
    this.ctx.strokeStyle = '#00ff88';
    this.ctx.lineWidth = 3;
    this.ctx.beginPath();
    this.ctx.moveTo(0, GROUND_Y);
    this.ctx.lineTo(CANVAS_WIDTH, GROUND_Y);
    this.ctx.stroke();

    this.ctx.strokeStyle = 'rgba(0, 255, 136, 0.3)';
    this.ctx.lineWidth = 1;
    for (let i = 0; i < 20; i++) {
      const y = GROUND_Y + 10 + i * 15;
      this.ctx.beginPath();
      this.ctx.moveTo(0, y);
      this.ctx.lineTo(CANVAS_WIDTH, y);
      this.ctx.stroke();
    }
  }

  renderPlayer(player) {
    const { x, y, width, height, state, frame } = player;
    
    this.ctx.save();
    this.ctx.translate(x + width / 2, y + height / 2);

    if (state === 'ducking') {
      this.ctx.scale(1.3, 0.7);
    }
    if (state === 'jumping' && player.velocityY < 0) {
      this.ctx.rotate(-0.1);
    }

    const glowColor = player.hasShield ? '#00aaff' : '#00ff88';
    const bodyColor = player.hasShield ? '#0088cc' : '#00dd77';
    
    this.ctx.shadowColor = glowColor;
    this.ctx.shadowBlur = 15;

    this.ctx.fillStyle = bodyColor;
    this.ctx.fillRect(-width/2, -height/2, width, height);

    this.ctx.shadowBlur = 0;

    this.ctx.fillStyle = '#00ff88';
    this.ctx.fillRect(-width/2 + 4, -height/2 + 4, 8, 8);
    this.ctx.fillRect(width/2 - 12, -height/2 + 4, 8, 8);

    this.ctx.fillStyle = '#001a0d';
    const mouthY = -height/2 + 14;
    const mouthWidth = 12;
    this.ctx.fillRect(-mouthWidth/2, mouthY, mouthWidth, 4);

    this.ctx.fillStyle = bodyColor;
    this.ctx.fillRect(-width/2 - 6, -height/2 + 8, 6, 16);
    this.ctx.fillRect(width/2, -height/2 + 8, 6, 16);

    if (player.hasShield) {
      this.ctx.strokeStyle = '#00aaff';
      this.ctx.lineWidth = 3;
      this.ctx.beginPath();
      this.ctx.arc(0, 0, width * 0.8, 0, Math.PI * 2);
      this.ctx.stroke();
    }

    if (state === 'running') {
      const legOffset = Math.sin(frame * 0.3) * 4;
      this.ctx.fillStyle = bodyColor;
      this.ctx.fillRect(-8, height/2 - 2, 6, 4 + legOffset);
      this.ctx.fillRect(2, height/2 - 2, 6, 4 - legOffset);
    }

    this.ctx.restore();
  }

  renderObstacle(obstacle) {
    const { x, y, width, height, type } = obstacle;
    
    this.ctx.save();
    
    if (type === 'virus') {
      const pulse = Math.sin(Date.now() * 0.01) * 0.1 + 1;
      this.ctx.translate(x + width/2, y + height/2);
      this.ctx.scale(pulse, pulse);
      this.ctx.translate(-width/2, -height/2);
      
      this.ctx.fillStyle = '#ff4444';
      this.ctx.shadowColor = '#ff0000';
      this.ctx.shadowBlur = 10;
      this.ctx.beginPath();
      this.ctx.arc(width/2, height/2, width/2, 0, Math.PI * 2);
      this.ctx.fill();
      
      this.ctx.fillStyle = '#ffffff';
      this.ctx.beginPath();
      this.ctx.arc(width/2 - 4, height/2 - 2, 3, 0, Math.PI * 2);
      this.ctx.arc(width/2 + 4, height/2 - 2, 3, 0, Math.PI * 2);
      this.ctx.fill();
    } else if (type === 'firewall') {
      this.ctx.fillStyle = '#ff6600';
      this.ctx.shadowColor = '#ff3300';
      this.ctx.shadowBlur = 15;
      this.ctx.fillRect(0, 0, width, height);
      
      for (let i = 0; i < height; i += 8) {
        const flicker = Math.random() * 0.5 + 0.5;
        this.ctx.fillStyle = `rgba(255, 200, 0, ${flicker})`;
        this.ctx.fillRect(0, i, width, 4);
      }
    } else if (type === 'malware') {
      this.ctx.fillStyle = '#9933ff';
      this.ctx.shadowColor = '#6600cc';
      this.ctx.shadowBlur = 10;
      
      this.ctx.beginPath();
      this.ctx.moveTo(width/2, 0);
      this.ctx.lineTo(width, height);
      this.ctx.lineTo(0, height);
      this.ctx.closePath();
      this.ctx.fill();
      
      this.ctx.fillStyle = '#ffffff';
      this.ctx.font = '10px monospace';
      this.ctx.fillText('!', width/2 - 3, height - 6);
    } else if (type === 'datawall') {
      this.ctx.fillStyle = '#0066cc';
      this.ctx.shadowColor = '#0044aa';
      this.ctx.shadowBlur = 8;
      this.ctx.fillRect(0, 0, width, height);
      
      this.ctx.strokeStyle = '#00aaff';
      this.ctx.lineWidth = 2;
      for (let i = 0; i < height; i += 12) {
        this.ctx.beginPath();
        this.ctx.moveTo(0, i);
        this.ctx.lineTo(width, i);
        this.ctx.stroke();
      }
    } else {
      this.ctx.fillStyle = '#ff4444';
      this.ctx.shadowColor = '#ff0000';
      this.ctx.shadowBlur = 10;
      this.ctx.fillRect(0, 0, width, height);
    }

    this.ctx.restore();
  }

  renderCollectible(collectible) {
    const { x, y, size, type, frame } = collectible;
    
    this.ctx.save();
    this.ctx.translate(x + size/2, y + size/2);
    
    const bob = Math.sin(Date.now() * 0.005 + frame) * 3;
    this.ctx.translate(0, bob);
    
    const pulse = 1 + Math.sin(Date.now() * 0.01) * 0.1;
    this.ctx.scale(pulse, pulse);

    if (type === 'power_cell') {
      this.ctx.fillStyle = '#ffd700';
      this.ctx.shadowColor = '#ffaa00';
      this.ctx.shadowBlur = 15;
      
      this.ctx.beginPath();
      this.ctx.arc(0, 0, size/2, 0, Math.PI * 2);
      this.ctx.fill();
      
      this.ctx.fillStyle = '#ffffff';
      this.ctx.font = '10px Arial';
      this.ctx.textAlign = 'center';
      this.ctx.fillText('⚡', 0, 4);
    } else if (type === 'data_orb') {
      this.ctx.fillStyle = '#00ffff';
      this.ctx.shadowColor = '#00aaff';
      this.ctx.shadowBlur = 12;
      
      this.ctx.fillRect(-size/2, -size/2, size, size);
      
      this.ctx.strokeStyle = '#ffffff';
      this.ctx.lineWidth = 2;
      this.ctx.strokeRect(-size/2 + 2, -size/2 + 2, size - 4, size - 4);
    } else if (type === 'canvas_boost') {
      this.ctx.fillStyle = '#ff00ff';
      this.ctx.shadowColor = '#ff66ff';
      this.ctx.shadowBlur = 20;
      
      this.ctx.beginPath();
      this.ctx.arc(0, 0, size/2, 0, Math.PI * 2);
      this.ctx.fill();
      
      this.ctx.strokeStyle = '#ffffff';
      this.ctx.lineWidth = 2;
      this.ctx.beginPath();
      this.ctx.arc(0, 0, size/2 - 3, 0, Math.PI * 2);
      this.ctx.stroke();
    }

    this.ctx.restore();
  }

  renderParticle(particle) {
    const { x, y, size, color, alpha } = particle;
    
    this.ctx.save();
    this.ctx.globalAlpha = alpha;
    this.ctx.fillStyle = color;
    this.ctx.shadowColor = color;
    this.ctx.shadowBlur = 5;
    this.ctx.beginPath();
    this.ctx.arc(x, y, size, 0, Math.PI * 2);
    this.ctx.fill();
    this.ctx.restore();
  }

  render() {
    this.clear();
    this.renderBackground(3);
    this.renderGround();
  }
}

window.GameRenderer = GameRenderer;