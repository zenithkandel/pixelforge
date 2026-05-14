export class ChunkCache {
    constructor(maxChunks, ttl) {
        this.maxChunks = maxChunks;
        this.ttl = ttl;
        this.cache = new Map();
    }

    async loadChunk(cx, cy) {
        const key = `${cx}_${cy}`;

        if (this.cache.has(key)) {
            const entry = this.cache.get(key);
            entry.lastAccessed = Date.now();
            return entry.data;
        }

        try {
            const response = await fetch(`/api/grid/chunk.php?cx=${cx}&cy=${cy}`);
            if (!response.ok) {
                return null;
            }

            const version = parseInt(response.headers.get('X-Chunk-Version') || '0');
            const buffer = await response.arrayBuffer();
            const data = new Uint8Array(buffer);

            if (this.cache.size >= this.maxChunks) {
                this.evictLRU();
            }

            this.cache.set(key, {
                data: data,
                version: version,
                lastAccessed: Date.now()
            });

            return data;
        } catch (err) {
            console.error('Failed to load chunk:', cx, cy, err);
            return null;
        }
    }

    getChunk(cx, cy) {
        const key = `${cx}_${cy}`;
        const entry = this.cache.get(key);
        return entry ? entry.data : null;
    }

    getChunkVersion(cx, cy) {
        const key = `${cx}_${cy}`;
        const entry = this.cache.get(key);
        return entry ? entry.version : 0;
    }

    invalidateChunk(cx, cy) {
        const key = `${cx}_${cy}`;
        this.cache.delete(key);
    }

    clear() {
        this.cache.clear();
    }

    evictLRU() {
        let oldestKey = null;
        let oldestTime = Date.now();

        for (const [key, entry] of this.cache.entries()) {
            if (entry.lastAccessed < oldestTime) {
                oldestTime = entry.lastAccessed;
                oldestKey = key;
            }
        }

        if (oldestKey) {
            this.cache.delete(oldestKey);
        }
    }
}