import { CANVAS_HEIGHT, CANVAS_WIDTH, GROUND_Y_OFFSET } from './engine.js';

const OBSTACLES = {
    ground: [
        { type: 'glitch_block', width: 16, height: 16, weight: 40 },
        { type: 'double_stack', width: 16, height: 32, weight: 20 },
        { type: 'spike', width: 24, height: 16, weight: 15 },
        { type: 'crawl_barrier', width: 64, height: 8, weight: 15 },
        { type: 'triple_stack', width: 16, height: 48, weight: 5 },
        { type: 'combo_block', width: 16, height: 16, weight: 5 }
    ],
    aerial: [
        { type: 'beam', width: 60, height: 4, yOffset: CANVAS_HEIGHT - GROUND_Y_OFFSET - 30, weight: 35 },
        { type: 'high_beam', width: 60, height: 4, yOffset: CANVAS_HEIGHT - GROUND_Y_OFFSET - 50, weight: 25 },
        { type: 'double_beam', width: 30, height: 4, yOffset: CANVAS_HEIGHT - GROUND_Y_OFFSET - 35, weight: 20 }
    ],
    special: [
        { type: 'glitch_zone', width: 100, height: CANVAS_HEIGHT, weight: 10 },
        { type: 'quantum_block', width: 16, height: 16, weight: 10 },
        { type: 'data_storm', width: 20, height: 20, weight: 10 }
    ]
};

const MIN_SPAWN_INTERVAL = 1.5;
const MIN_AERIAL_GAP = 2.0;

export class ObstacleManager {
    constructor(prng) {
        this.prng = prng;
        this.obstacles = [];
        this.lastSpawnTime = 0;
        this.lastObstacleType = null;
        this.aerialSinceGround = 0;
    }

    update(speedBPS, dt, speedTier) {
    }

    shouldSpawn(elapsedMs, speedTier) {
        const now = elapsedMs / 1000;
        const minInterval = MIN_SPAWN_INTERVAL * (60 / (speedTier * 5));

        if (now - this.lastSpawnTime < minInterval) {
            return false;
        }

        return true;
    }

    spawn(elapsedMs, speedTier) {
        this.lastSpawnTime = elapsedMs / 1000;

        let category;
        if (speedTier >= 3 && this.prng.next() < 0.3) {
            category = 'aerial';
        } else if (speedTier >= 3 && this.prng.next() < 0.1) {
            category = 'special';
        } else {
            category = 'ground';
        }

        if (category === 'aerial' && this.lastObstacleType === 'aerial') {
            this.aerialSinceGround++;
            if (this.aerialSinceGround >= 2) {
                category = 'ground';
                this.aerialSinceGround = 0;
            }
        } else {
            this.aerialSinceGround = 0;
        }

        const pool = OBSTACLES[category];
        const weights = pool.map(o => o.weight);
        const obstacle = this.prng.weightedPick(pool, weights);

        this.lastObstacleType = category;

        let x = CANVAS_WIDTH + 50;
        let y = CANVAS_HEIGHT - GROUND_Y_OFFSET - obstacle.height;

        if (category === 'aerial') {
            y = obstacle.yOffset || y;
        }

        return {
            type: obstacle.type,
            x: x,
            y: y,
            width: obstacle.width,
            height: obstacle.height,
            category: category
        };
    }
}