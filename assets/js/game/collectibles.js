// Collectible management
export class CollectibleManager {
    constructor(prng) {
        this.prng = prng;
        this.shards = [];
        this.powerCells = [];
    }

    update(playerDistance) {
        // Remove off-screen collectibles
        this.shards = this.shards.filter(s => s.x < playerDistance + 800);
        this.powerCells = this.powerCells.filter(p => p.x < playerDistance + 800);

        // Generate new collectibles (simplified)
        if (Math.random() > 0.95) {
            this.spawnShard(playerDistance);
        }
    }

    spawnShard(playerDistance) {
        const colors = [
            { color: '#888888', value: 1, weight: 50 },
            { color: '#FF3366', value: 5, weight: 25 },
            { color: '#3366FF', value: 5, weight: 15 },
            { color: '#33FF66', value: 10, weight: 7 },
            { color: '#FFAAFF', value: 50, weight: 3 }
        ];

        const rand = this.prng.next() * 100;
        let weight = 0;
        for (let c of colors) {
            weight += c.weight;
            if (rand <= weight) {
                this.shards.push({
                    x: playerDistance + 300,
                    y: Math.random() * 200,
                    color: c.color,
                    value: c.value,
                    collected: false
                });
                break;
            }
        }
    }

    checkCollectionAtPosition(x, y) {
        let collected = [];

        for (let i = this.shards.length - 1; i >= 0; i--) {
            const shard = this.shards[i];
            if (Math.abs(shard.x - x) < 20 && Math.abs(shard.y - y) < 20) {
                collected.push(shard);
                this.shards.splice(i, 1);
            }
        }

        return collected;
    }
}
