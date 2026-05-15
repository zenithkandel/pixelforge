const GRID_SIZE = 800;
const CHUNK_SIZE = 64;
const NUM_CHUNKS = GRID_SIZE / CHUNK_SIZE;

class ChunkCache {
  constructor() {
    this.chunks = new Map();
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

    try {
      const response = await fetch(`/api/grid/chunk/${cx}/${cy}`, {
        headers: api.accessToken ? { 'Authorization': `Bearer ${api.accessToken}` } : {}
      });
      
      if (response.ok) {
        const buffer = await response.arrayBuffer();
        const uint8Array = new Uint8Array(buffer);
        const version = response.headers.get('X-Chunk-Version') || '0';
        const result = { buffer: uint8Array, version: parseInt(version) };
        this.set(cx, cy, result);
        return result;
      }
    } catch (err) {
      console.error(`Failed to load chunk ${cx},${cy}:`, err);
    }
    return null;
  }

  invalidateAll() {
    this.chunks.clear();
  }

  async preloadChunks(centerCx, centerCy, radius, api) {
    const promises = [];

    for (let cy = 0; cy < NUM_CHUNKS; cy++) {
      for (let cx = 0; cx < NUM_CHUNKS; cx++) {
        if (!this.get(cx, cy)) {
          promises.push(this.loadChunk(cx, cy, api));
        }
      }
    }

    await Promise.all(promises);
    console.log(`Loaded ${this.chunks.size} chunks`);
  }
}

window.ChunkCache = ChunkCache;