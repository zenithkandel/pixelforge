const GROUND_Y = 300;
const GRAVITY = 2800;
const JUMP_VELOCITY = -900;
const DOUBLE_JUMP_VELOCITY = -750;
const SLIDE_DURATION = 0.4;

const OBSTACLE_TYPES = {
  GLITCH_BLOCK: { width: 40, height: 40, ground: true, minTier: 1 },
  DOUBLE_STACK: { width: 40, height: 80, ground: true, minTier: 1 },
  SPIKE_ARRAY: { width: 80, height: 40, ground: true, minTier: 1 },
  CRAWL_BARRIER: { width: 120, height: 20, ground: true, slide: true, minTier: 1 },
  TRIPLE_STACK: { width: 40, height: 120, ground: true, minTier: 1 },
  COMBO_BLOCK: { width: 40, height: 40, ground: true, minTier: 4 },
  FIREWALL_BEAM: { width: 200, height: 20, aerial: true, y: 260, slide: true, minTier: 2 },
  HIGH_BEAM: { width: 200, height: 20, aerial: true, y: 200, minTier: 2 },
  DATA_SPIKE: { width: 30, height: 60, aerial: true, minTier: 2 },
  DOUBLE_BEAM: { width: 80, height: 60, aerial: true, minTier: 2 },
};

class ObstacleManager {
  constructor(prng) {
    this.prng = prng;
    this.obstacles = [];
    this.spawnX = 960;
    this.lastSpawnTime = 0;
    this.minGap = 600;
    this.speedTier = 1;
  }

  setSpeedTier(tier) {
    this.speedTier = tier;
  }

  update(dt, speedBPS, elapsedMs) {
    const moveSpeed = speedBPS * 64 * dt;
    this.obstacles.forEach(o => { o.x -= moveSpeed; });
    this.obstacles = this.obstacles.filter(o => o.x > -200);

    const spawnInterval = Math.max(800, 1500 - this.speedTier * 100);
    if (elapsedMs - this.lastSpawnTime > spawnInterval) {
      this.maybeSpawn();
      this.lastSpawnTime = elapsedMs;
    }
  }

  maybeSpawn() {
    const groundObstacles = Object.entries(OBSTACLE_TYPES).filter(([, o]) => o.ground && o.minTier <= this.speedTier);
    const aerialObstacles = Object.entries(OBSTACLE_TYPES).filter(([, o]) => o.aerial && o.minTier <= this.speedTier);

    const prev = this.obstacles[this.obstacles.length - 1];
    if (prev && prev.aerial) {
      const go = this.prng.pick(groundObstacles);
      this.spawnObstacle(go);
      return;
    }

    const useAerial = this.prng.nextBool(0.4);
    if (useAerial && aerialObstacles.length > 0) {
      const ao = this.prng.pick(aerialObstacles);
      this.spawnObstacle(ao);
    } else if (groundObstacles.length > 0) {
      const go = this.prng.pick(groundObstacles);
      this.spawnObstacle(go);
    }
  }

  spawnObstacle([type, def]) {
    const x = this.spawnX + 960;
    const y = def.aerial ? (def.y || GROUND_Y - 60) : GROUND_Y - def.height;
    this.obstacles.push({
      type,
      x,
      y,
      width: def.width,
      height: def.height,
      ground: def.ground,
      aerial: def.aerial,
      slide: def.slide,
    });
  }

  checkCollision(pxlr, isSliding) {
    const px = pxlr.x;
    const py = pxlr.y;
    const pw = 32;
    const ph = isSliding ? 20 : 32;

    for (const o of this.obstacles) {
      if (px + pw > o.x && px < o.x + o.width && py + ph > o.y && py < o.y + o.height) {
        if (o.slide && isSliding) continue;
        return true;
      }
    }
    return false;
  }

  getActive() { return this.obstacles; }
}

export { ObstacleManager, OBSTACLE_TYPES, GROUND_Y, GRAVITY, JUMP_VELOCITY, DOUBLE_JUMP_VELOCITY, SLIDE_DURATION };