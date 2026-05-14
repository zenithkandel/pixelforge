const SHARD_TYPES = {
  GRAY: { value: 1, color: '#888888', probability: 0.5 },
  RED: { value: 5, color: '#FF3366', probability: 0.25 },
  BLUE: { value: 5, color: '#3366FF', probability: 0.15 },
  GREEN: { value: 10, color: '#33FF66', probability: 0.07 },
  RAINBOW: { value: 50, color: 'rainbow', probability: 0.03 },
};

const POWERUP_TYPES = ['SHIELD', 'MAGNET', 'TIMEWARP', 'SCORE_SURGE', 'EXTRA_LIFE', 'PIXEL_BOMB'];
const POWERUP_WEIGHTS = [30, 25, 20, 15, 7, 3];
const POWERUP_DURATIONS = {
  SHIELD: 8000,
  MAGNET: 12000,
  TIMEWARP: 6000,
  SCORE_SURGE: 15000,
};

class CollectibleManager {
  constructor(prng) {
    this.prng = prng;
    this.shards = [];
    this.powerCells = [];
    this.lastShardSpawn = 0;
    this.lastPowerSpawn = 0;
    this.spawnX = 960;
  }

  update(dt, elapsedMs, speedBPS) {
    const moveSpeed = speedBPS * 64 * dt;
    this.shards.forEach(s => { s.x -= moveSpeed; });
    this.powerCells.forEach(p => { p.x -= moveSpeed; });
    this.shards = this.shards.filter(s => s.x > -50);
    this.powerCells = this.powerCells.filter(p => p.x > -50);

    const shardInterval = this.prng.nextInt(800, 2000);
    if (elapsedMs - this.lastShardSpawn > shardInterval) {
      this.spawnShard();
      this.lastShardSpawn = elapsedMs;
    }

    if (elapsedMs - this.lastPowerSpawn > this.prng.nextInt(25000, 40000)) {
      this.spawnPowerCell();
      this.lastPowerSpawn = elapsedMs;
    }
  }

  spawnShard() {
    const r = this.prng.next();
    let cumulative = 0;
    let type = 'GRAY';
    for (const [name, def] of Object.entries(SHARD_TYPES)) {
      cumulative += def.probability;
      if (r <= cumulative) { type = name; break; }
    }
    const def = SHARD_TYPES[type];
    this.shards.push({
      type,
      x: this.spawnX + 960,
      y: this.prng.nextInt(220, 280),
      value: def.value,
      color: def.color,
      size: type === 'RAINBOW' ? 12 : 8,
    });
  }

  spawnPowerCell() {
    this.powerCells.push({
      x: this.spawnX + 960,
      y: this.prng.nextInt(200, 280),
    });
  }

  getRandomPowerup() {
    return this.prng.weightedPick(POWERUP_TYPES, POWERUP_WEIGHTS);
  }

  checkShardCollision(pxlr) {
    const px = pxlr.x + 16;
    const py = pxlr.y + 16;
    for (let i = this.shards.length - 1; i >= 0; i--) {
      const s = this.shards[i];
      const dx = px - s.x;
      const dy = py - s.y;
      if (Math.sqrt(dx * dx + dy * dy) < 20) {
        this.shards.splice(i, 1);
        return s;
      }
    }
    return null;
  }

  checkPowerupCollision(pxlr) {
    const px = pxlr.x + 16;
    const py = pxlr.y + 16;
    for (let i = this.powerCells.length - 1; i >= 0; i--) {
      const p = this.powerCells[i];
      const dx = px - p.x;
      const dy = py - p.y;
      if (Math.sqrt(dx * dx + dy * dy) < 24) {
        this.powerCells.splice(i, 1);
        return true;
      }
    }
    return false;
  }

  getActive() { return { shards: this.shards, powerCells: this.powerCells }; }
}

export { CollectibleManager, SHARD_TYPES };