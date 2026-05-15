const GRID_SIZE = 800;
const CHUNK_SIZE = 64;
const NUM_CHUNKS = GRID_SIZE / CHUNK_SIZE;
const OBSTACLE_TYPES = ['virus', 'firewall', 'malware'];

class ObstacleManager {
  constructor(prng) {
    this.prng = prng;
    this.spawnDistance = 0;
    this.baseSpawnInterval = 300;
    this.minSpawnInterval = 100;
  }

  update(level, speed) {
    this.spawnDistance += speed;
  }

  shouldSpawn(level) {
    const interval = Math.max(
      this.minSpawnInterval,
      this.baseSpawnInterval - level * 20
    );
    return this.spawnDistance >= interval + this.prng.nextInt(-50, 50);
  }

  reset() {
    this.spawnDistance = 0;
  }

  getObstacle(level) {
    const types = [...OBSTACLE_TYPES];
    
    if (level >= 5) types.push('virus_swarm');
    if (level >= 6) types.push('datawall');

    const type = this.prng.pick(types);

    switch (type) {
      case 'virus':
        return { type, width: 30, height: 30, y: 288 };
      case 'firewall':
        return {
          type,
          width: 25,
          height: this.prng.nextInt(40, 80),
          y: 320 - this.prng.nextInt(40, 80)
        };
      case 'malware':
        return { type, width: 35, height: 40, y: 280 };
      case 'virus_swarm':
        return {
          type,
          width: 30,
          height: 30,
          y: this.prng.nextInt(150, 250),
          phase: this.prng.nextFloat(0, Math.PI * 2)
        };
      case 'datawall':
        return {
          type,
          width: 20,
          height: 320,
          y: 0,
          gapY: 160,
          gapHeight: 80,
          gapSpeed: this.prng.nextFloat(-2, 2)
        };
      default:
        return { type: 'virus', width: 30, height: 30, y: 288 };
    }
  }
}

window.ObstacleManager = ObstacleManager;