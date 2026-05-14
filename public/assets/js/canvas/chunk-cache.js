class ChunkCache {
  constructor(maxChunks = 200) {
    this.cache = new Map();
    this.maxChunks = maxChunks;
  }

  get(cx, cy) {
    const key = `${cx}_${cy}`;
    const entry = this.cache.get(key);
    if (entry) {
      entry.lastAccess = performance.now();
      this.cache.delete(key);
      this.cache.set(key, entry);
    }
    return entry || null;
  }

  set(cx, cy, imageData, version) {
    const key = `${cx}_${cy}`;
    if (this.cache.size >= this.maxChunks) {
      const oldest = this.cache.keys().next().value;
      this.cache.delete(oldest);
    }
    this.cache.set(key, { imageData, version, lastAccess: performance.now() });
  }

  has(cx, cy) {
    return this.cache.has(`${cx}_${cy}`);
  }

  getVersion(cx, cy) {
    const entry = this.cache.get(`${cx}_${cy}`);
    return entry ? entry.version : null;
  }

  clear() {
    this.cache.clear();
  }

  get size() { return this.cache.size; }
}

export { ChunkCache };