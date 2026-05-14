export class SeededPRNG {
    constructor(seed) {
        this.state = (seed >>> 0);
    }

    next() {
        this.state |= 0;
        this.state = (this.state + 0x6d2b79f5) | 0;
        let z = Math.imul(this.state ^ (this.state >>> 15), 1 | this.state);
        z = (z + Math.imul(z ^ (z >>> 7), 61 | z)) ^ z;
        return ((z ^ (z >>> 14)) >>> 0) / 4294967296;
    }

    nextInt(min, max) {
        return Math.floor(this.next() * (max - min + 1)) + min;
    }

    nextBool(probability = 0.5) {
        return this.next() < probability;
    }

    pick(array) {
        return array[Math.floor(this.next() * array.length)];
    }

    weightedPick(items, weights) {
        const totalWeight = weights.reduce((a, b) => a + b, 0);
        let r = this.next() * totalWeight;
        for (let i = 0; i < items.length; i++) {
            r -= weights[i];
            if (r <= 0) return items[i];
        }
        return items[items.length - 1];
    }

    shuffle(array) {
        const result = [...array];
        for (let i = result.length - 1; i > 0; i--) {
            const j = this.nextInt(0, i);
            [result[i], result[j]] = [result[j], result[i]];
        }
        return result;
    }
}