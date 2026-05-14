// Obstacle generation and management
export class ObstacleManager {
    constructor(prng) {
        this.prng = prng;
        this.obstacles = [];
        this.nextSpawnDistance = 0;
    }

    update(playerDistance) {
        // Remove off-screen obstacles
        this.obstacles = this.obstacles.filter(obs => obs.x < playerDistance + 800);

        // Generate new obstacles
        if (playerDistance > this.nextSpawnDistance) {
            this.spawn(playerDistance);
        }
    }

    spawn(playerDistance) {
        const speedTier = this.getSpeedTier(playerDistance);
        const types = ['glitch_block', 'double_stack', 'spike_array', 'crawl_barrier'];
        const type = types[Math.floor(this.prng.next() * types.length)];

        this.obstacles.push({
            x: playerDistance + 400,
            type: type,
            width: 16,
            height: 16,
            active: true
        });

        // Next spawn at minimum gap distance
        this.nextSpawnDistance = playerDistance + 600;
    }

    getSpeedTier(playerDistance) {
        if (playerDistance > 6000) return 7;
        if (playerDistance > 3500) return 6;
        if (playerDistance > 1800) return 5;
        if (playerDistance > 800) return 4;
        if (playerDistance > 300) return 3;
        if (playerDistance > 0) return 2;
        return 1;
    }

    checkCollision(playerX, playerY, playerWidth, playerHeight) {
        for (let obs of this.obstacles) {
            if (obs.x < playerX + playerWidth &&
                obs.x + obs.width > playerX &&
                obs.x > playerY + playerHeight &&
                obs.x + obs.height > playerY) {
                return obs;
            }
        }
        return null;
    }
}
