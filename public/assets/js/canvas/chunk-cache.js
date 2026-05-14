export class ChunkCache {
    constructor() {
        this.cache = new Map(); // cx_cy -> { data: Uint8Array, version: int }
    }
    
    async getChunk(cx, cy, currentVersion = -1) {
        const key = `${cx}_${cy}`;
        const cached = this.cache.get(key);
        
        const url = `/api/grid/chunk.php?cx=${cx}&cy=${cy}&v=${cached ? cached.version : currentVersion}`;
        const res = await fetch(url);
        
        if (res.status === 304) {
            return cached.data;
        }
        
        if (!res.ok) throw new Error("Failed to load chunk");
        
        const buffer = await res.arrayBuffer();
        const data = new Uint8Array(buffer);
        const newVersion = parseInt(res.headers.get('X-Chunk-Version'), 10);
        
        this.cache.set(key, { data, version: newVersion });
        return data;
    }
    
    updatePixel(cx, cy, localX, localY, hexColor) {
        const key = `${cx}_${cy}`;
        const cached = this.cache.get(key);
        if (!cached) return;
        
        const idx = (localY * 64 + localX) * 3;
        const hex = hexColor.replace('#', '');
        cached.data[idx] = parseInt(hex.substring(0,2), 16);
        cached.data[idx+1] = parseInt(hex.substring(2,4), 16);
        cached.data[idx+2] = parseInt(hex.substring(4,6), 16);
    }
}
