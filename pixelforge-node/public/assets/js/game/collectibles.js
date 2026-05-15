const COLLECTIBLE_TYPES = {
  power_cell: { value: 50, size: 20 },
  data_orb: { value: 75, size: 20 },
  canvas_boost: { value: 0, size: 24 }
};

class CollectibleManager {
  constructor(prng) {
    this.prng = prng;
    this.spawnDistance = 0;
    this.baseSpawnInterval = 150;
    this.types = ['power_cell', 'data_orb'];
  }

  update(level, speed) {
    this.spawnDistance += speed;
    
    if (level >= 3 && this.prng.nextFloat() < 0.01) {
      this.types.push('canvas_boost');
    }
  }

  shouldSpawn() {
    return this.spawnDistance >= this.baseSpawnInterval + this.prng.nextInt(-30, 30);
  }

  reset() {
    this.spawnDistance = 0;
    this.types = ['power_cell', 'data_orb'];
  }

  getCollectible() {
    const type = this.prng.pick(this.types);
    const config = COLLECTIBLE_TYPES[type];
    
    return {
      type,
      value: config.value,
      size: config.size,
      x: 900,
      y: this.prng.nextInt(100, 280),
      frame: 0
    };
  }
}

window.CollectibleManager = CollectibleManager;