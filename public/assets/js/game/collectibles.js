import { CANVAS_HEIGHT, CANVAS_WIDTH, GROUND_Y_OFFSET } from './engine.js';

const SHARDS = [
    { subtype: 'gray', value: 1, weight: 50, color: '#888888' },
    { subtype: 'red', value: 5, weight: 25, color: '#FF3366' },
    { subtype: 'blue', value: 5, weight: 15, color: '#3366FF' },
    { subtype: 'green', value: 10, weight: 7, color: '#33FF66' },
    { subtype: 'rainbow', value: 50, weight: 3, color: '#FF00FF' }
];

const POWERUPS = [
    { type: 'shield', weight: 30 },
    { type: 'magnet', weight: 25 },
    { type: 'timewarp', weight: 20 },
    { type: 'score_surge', weight: 15 },
    { type: 'extra_life', weight: 7 },
    { type: 'pixel_bomb', weight: 3 }
];

export class CollectibleManager {
    constructor(prng) {
        this.prng = prng;
        this.collectibles = [];
        this.lastShardSpawn = 0;
        this.lastPowerCellSpawn = 0;
        this.shardChainLength = 0;
    }

    update(speedBPS, dt) {
    }

    shouldSpawnShard(elapsedMs) {
        const now = elapsedMs / 1000;
        if (now - this.lastShardSpawn < 0.5) return false;

        this.lastShardSpawn = now;
        return true;
    }

    shouldSpawnPowerCell(elapsedMs) {
        const now = elapsedMs / 1000;
        if (now - this.lastPowerCellSpawn < 25 + this.prng.next() * 15) return false;

        this.lastPowerCellSpawn = now;
        return true;
    }

    shouldSpawn(elapsedMs, speedTier, obstacles) {
        const spawnShard = this.shouldSpawnShard(elapsedMs);
        const spawnPowerCell = speedTier >= 1 && this.shouldSpawnPowerCell(elapsedMs);

        return spawnShard || spawnPowerCell;
    }

    spawn(elapsedMs, speedTier, obstacles) {
        if (this.prng.next() < 0.1 && speedTier >= 1 && this.shouldSpawnPowerCell(elapsedMs)) {
            return this.spawnPowerCell();
        }

        return this.spawnShardChain(obstacles);
    }

    spawnPowerCell() {
        return {
            type: 'power_cell',
            x: CANVAS_WIDTH + 50,
            y: CANVAS_HEIGHT - GROUND_Y_OFFSET - 30 - Math.random() * 40,
            radius: 6,
            powerup: this.prng.weightedPick(POWERUPS.map(p => p.type), POWERUPS.map(p => p.weight))
        };
    }

    spawnShardChain(obstacles) {
        if (this.shardChainLength === 0) {
            this.shardChainLength = this.prng.nextInt(3, 8);
        }

        this.shardChainLength--;

        const shard = this.prng.weightedPick(SHARDS, SHARDS.map(s => s.weight));

        let y = CANVAS_HEIGHT - GROUND_Y_OFFSET - 10;
        if (this.prng.next() < 0.3) {
            y = CANVAS_HEIGHT - GROUND_Y_OFFSET - 50 - Math.random() * 30;
        }

        const clearPositions = [50, 150, 300, 450];
        let x = this.prng.pick(clearPositions);

        for (const obs of obstacles) {
            if (Math.abs(obs.x - x) < 40) {
                x = CANVAS_WIDTH + 50;
                break;
            }
        }

        return {
            type: 'shard',
            subtype: shard.subtype,
            value: shard.value,
            x: x + CANVAS_WIDTH,
            y: y,
            radius: 4
        };
    }
}