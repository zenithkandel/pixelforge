const config = require('../config');

const chunkCache = new Map();

async function getChunk(cx, cy, pool) {
  const key = `${cx}_${cy}`;
  const cached = chunkCache.get(key);

  if (cached && Date.now() - cached.lastAccess < config.grid.cacheTTL) {
    cached.lastAccess = Date.now();
    return cached;
  }

  const xMin = cx * 64;
  const xMax = xMin + 63;
  const yMin = cy * 64;
  const yMax = yMin + 63;

  const [pixels] = await pool.execute(
    `SELECT x, y, color FROM pixels 
     WHERE x >= ? AND x <= ? AND y >= ? AND y <= ? 
     AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)`,
    [xMin, xMax, yMin, yMax]
  );

  const buffer = Buffer.alloc(64 * 64 * 3, 255);

  for (const p of pixels) {
    const lx = p.x - xMin;
    const ly = p.y - yMin;
    const offset = (ly * 64 + lx) * 3;
    
    if (offset >= 0 && offset + 2 < buffer.length) {
      buffer[offset] = parseInt(p.color.slice(1, 3), 16);
      buffer[offset + 1] = parseInt(p.color.slice(3, 5), 16);
      buffer[offset + 2] = parseInt(p.color.slice(5, 7), 16);
    }
  }

  let version = 0;
  try {
    const [verRows] = await pool.execute(
      'SELECT version FROM chunks WHERE chunk_x = ? AND chunk_y = ? AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)',
      [cx, cy]
    );
    version = verRows[0]?.version || 0;
  } catch (err) {
    version = 1;
  }

  const entry = {
    buffer,
    version,
    lastAccess: Date.now()
  };

  if (chunkCache.size >= config.grid.cacheMaxSize) {
    let oldestKey = null;
    let oldestTime = Infinity;

    for (const [k, v] of chunkCache.entries()) {
      if (v.lastAccess < oldestTime) {
        oldestTime = v.lastAccess;
        oldestKey = k;
      }
    }

    if (oldestKey) {
      chunkCache.delete(oldestKey);
    }
  }

  chunkCache.set(key, entry);
  return entry;
}

function invalidateChunk(cx, cy) {
  const key = `${cx}_${cy}`;
  chunkCache.delete(key);
}

function invalidateAll() {
  chunkCache.clear();
}

function getCacheStats() {
  let oldestAccess = Infinity;
  let newestAccess = 0;
  
  for (const v of chunkCache.values()) {
    if (v.lastAccess < oldestAccess) oldestAccess = v.lastAccess;
    if (v.lastAccess > newestAccess) newestAccess = v.lastAccess;
  }

  return {
    size: chunkCache.size,
    maxSize: config.grid.cacheMaxSize,
    oldestAccess,
    newestAccess
  };
}

module.exports = {
  getChunk,
  invalidateChunk,
  invalidateAll,
  getCacheStats
};