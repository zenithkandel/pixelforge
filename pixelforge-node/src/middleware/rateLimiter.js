const config = require('../config');

async function rateLimiter(req, res, next) {
  const pool = req.app.get('db');
  const now = Date.now();
  const windowMs = config.rateLimit.windowMs;
  const windowStart = now - windowMs;
  
  let limitKey = req.ip;
  let maxRequests = config.rateLimit.maxRequests.default;
  
  if (req.user) {
    limitKey = `user:${req.user.userId}`;
    maxRequests = config.rateLimit.maxRequests.default;
  }
  
  if (req.path.includes('/auth/')) {
    maxRequests = config.rateLimit.maxRequests.auth;
    limitKey = `auth:${req.ip}`;
  }
  
  if (req.path.includes('/grid/buy')) {
    maxRequests = config.rateLimit.maxRequests.pixel;
    limitKey = req.user ? `pixel:${req.user.userId}` : `pixel:${req.ip}`;
  }
  
  if (req.path.includes('/game/')) {
    maxRequests = config.rateLimit.maxRequests.game;
    limitKey = req.user ? `game:${req.user.userId}` : `game:${req.ip}`;
  }
  
  try {
    await pool.execute(
      'DELETE FROM rate_limits WHERE window_start < ?',
      [windowStart]
    );
    
    const [rows] = await pool.execute(
      'SELECT COUNT(*) as cnt FROM rate_limits WHERE key_name = ? AND window_start = ?',
      [limitKey, windowStart]
    );
    
    if (rows[0].cnt >= maxRequests) {
      return res.status(429).json({
        ok: false,
        error: 'rate_limited',
        retryAfter: Math.ceil(windowMs / 1000)
      });
    }
    
    await pool.execute(
      'INSERT INTO rate_limits (key_name, window_start) VALUES (?, ?)',
      [limitKey, windowStart]
    );
    
    next();
  } catch (err) {
    console.error('Rate limiter error:', err);
    next();
  }
}

module.exports = rateLimiter;