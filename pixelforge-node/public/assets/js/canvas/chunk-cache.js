const GRID_SIZE = 800;
const CHUNK_SIZE = 64;
const NUM_CHUNKS = GRID_SIZE / CHUNK_SIZE;

class ChunkCache {
  constructor() {
    this.chunks = new Map();
    this.pending = new Map();
  }

  getChunkKey(cx, cy) {
    return `${cx}_${cy}`;
  }

  get(cx, cy) {
    return this.chunks.get(this.getChunkKey(cx, cy));
  }

  set(cx, cy, data) {
    this.chunks.set(this.getChunkKey(cx, cy), data);
  }

  async loadChunk(cx, cy, api) {
    const key = this.getChunkKey(cx, cy);
    
    if (this.chunks.has(key)) {
      return this.chunks.get(key);
    }

    if (this.pending.has(key)) {
      return this.pending.get(key);
    }

    const promise = (async () => {
      try {
        const result = await api.get(`/grid/chunk/${cx}/${cy}`);
        if (result && result.buffer) {
          this.set(cx, cy, result);
          return result;
        }
      } catch (err) {
        console.error(`Failed to load chunk ${cx},${cy}:`, err);
      }
      return null;
    })();

    this.pending.set(key, promise);
    const result = await promise;
    this.pending.delete(key);
    return result;
  }

  invalidate(cx, cy) {
    this.chunks.delete(this.getChunkKey(cx, cy));
  }

  invalidateAll() {
    this.chunks.clear();
  }

  async preloadChunks(centerCx, centerCy, radius, api) {
    const needed = [];
    const promises = [];

    for (let dy = -radius; dy <= radius; dy++) {
      for (let dx = -radius; dx <= radius; dx++) {
        const cx = centerCx + dx;
        const cy = centerCy + dy;
        if (cx >= 0 && cx < NUM_CHUNKS && cy >= 0 && cy < NUM_CHUNKS) {
          if (!this.get(cx, cy)) {
            needed.push({ cx, cy });
          }
        }
      }
    }

    for (const { cx, cy } of needed) {
      promises.push(this.loadChunk(cx, cy, api));
    }

    if (promises.length > 0) {
      await Promise.all(promises);
    }
  }
}

window.ChunkCache = ChunkCache;