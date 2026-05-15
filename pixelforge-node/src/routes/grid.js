const express = require('express');
const router = express.Router();
const { authRequired, authOptional } = require('../middleware/auth');
const { validateCoordinate, validateColor } = require('../middleware/validate');
const { success, error, AppError } = require('../utils/response');
const chunkService = require('../services/chunkService');
const pxlService = require('../services/pxlService');
const sseManager = require('../services/sseManager');
const achievementService = require('../services/achievementService');

router.get('/chunk/:cx/:cy', authOptional, async (req, res, next) => {
  try {
    const cx = parseInt(req.params.cx, 10);
    const cy = parseInt(req.params.cy, 10);
    
    if (isNaN(cx) || isNaN(cy) || cx < 0 || cx >= 13 || cy < 0 || cy >= 13) {
      throw new AppError('invalid_chunk', 400, 'Invalid chunk coordinates');
    }
    
    const pool = req.app.get('db');
    const result = await chunkService.getChunk(cx, cy, pool);
    
    res.set({
      'Content-Type': 'application/octet-stream',
      'Content-Length': result.buffer.length,
      'X-Chunk-Version': result.version
    });
    
    return res.send(result.buffer);
  } catch (err) {
    next(err);
  }
});

router.post('/buy', authRequired, async (req, res, next) => {
  const pool = req.app.get('db');
  const conn = await pool.getConnection();
  
  try {
    let { x, y, color } = req.body;
    
    x = validateCoordinate(x, 'X');
    y = validateCoordinate(y, 'Y');
    color = validateColor(color);
    
    await conn.beginTransaction();
    
    const [userRows] = await conn.execute(
      'SELECT id, pxl_balance, username, email, is_verified FROM users WHERE id = ? FOR UPDATE',
      [req.user.userId]
    );
    
    if (userRows.length === 0) {
      throw new AppError('user_not_found', 404);
    }
    
    const user = userRows[0];
    
    if (!user.is_verified) {
      throw new AppError('email_not_verified', 403, 'Please verify your email before purchasing pixels');
    }
    
    const [gemRows] = await conn.execute(
      'SELECT id FROM canvas_gems WHERE x = ? AND y = ? AND expires_at > NOW()',
      [x, y]
    );
    const isGem = gemRows.length > 0;
    
    const [freePixels] = await conn.execute(
      'SELECT COUNT(*) as cnt FROM pixel_history WHERE owner_id = ? AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1) AND purchased_at > DATE_SUB(NOW(), INTERVAL 1 DAY) AND action_type = "purchase"',
      [req.user.userId]
    );
    
    let cost = 1;
    let bonus = 0;
    
    if (isGem) {
      bonus = 3;
      await conn.execute('DELETE FROM canvas_gems WHERE x = ? AND y = ?', [x, y]);
    }
    
    if (user.pxl_balance < cost) {
      throw new AppError('insufficient_pxl', 400, 'Not enough PXL balance');
    }
    
    const [sessionRows] = await conn.execute(
      'SELECT id FROM grid_sessions WHERE is_current = 1'
    );
    
    if (sessionRows.length === 0) {
      throw new AppError('no_active_session', 500, 'No active grid session');
    }
    
    const sessionId = sessionRows[0].id;
    
    const [existingPixel] = await conn.execute(
      'SELECT owner_id, color FROM pixels WHERE x = ? AND y = ? AND grid_session_id = ?',
      [x, y, sessionId]
    );
    
    const actionType = existingPixel.length > 0 && existingPixel[0].owner_id !== req.user.userId ? 'overwrite' : 'purchase';
    
    if (existingPixel.length > 0) {
      await conn.execute(
        'UPDATE pixels SET color = ?, owner_id = ?, purchased_at = NOW() WHERE x = ? AND y = ? AND grid_session_id = ?',
        [color, req.user.userId, x, y, sessionId]
      );
    } else {
      await conn.execute(
        'INSERT INTO pixels (x, y, color, owner_id, grid_session_id) VALUES (?, ?, ?, ?, ?)',
        [x, y, color, req.user.userId, sessionId]
      );
    }
    
    await conn.execute(
      'UPDATE users SET pxl_balance = pxl_balance - ?, total_pxl_spent = total_pxl_spent + ? WHERE id = ?',
      [cost, cost, req.user.userId]
    );
    
    await conn.execute(
      'INSERT INTO pixel_history (x, y, color, owner_id, grid_session_id, action_type) VALUES (?, ?, ?, ?, ?, ?)',
      [x, y, color, req.user.userId, sessionId, actionType]
    );
    
    await conn.execute(
      'INSERT INTO pxl_transactions (user_id, amount, transaction_type, description, related_id) VALUES (?, ?, ?, ?, ?)',
      [req.user.userId, -cost, 'spend', `Pixel purchase at (${x}, ${y})`, sessionId]
    );
    
    if (bonus > 0) {
      await conn.execute(
        'UPDATE users SET pxl_balance = pxl_balance + ? WHERE id = ?',
        [bonus, req.user.userId]
      );
      await conn.execute(
        'INSERT INTO pxl_transactions (user_id, amount, transaction_type, description) VALUES (?, ?, ?, ?)',
        [req.user.userId, bonus, 'bonus', 'Found a hidden gem!']
      );
    }
    
    const cx = Math.floor(x / 64);
    const cy = Math.floor(y / 64);
    
    await conn.execute(
      'INSERT INTO chunks (chunk_x, chunk_y, version, grid_session_id) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE version = version + 1',
      [cx, cy, sessionId]
    );
    
    await conn.commit();
    
    const gemBonus = bonus > 0 ? bonus : null;
    
    sseManager.broadcast('pixel', {
      x, y, color,
      username: req.user.username,
      cx, cy,
      gemBonus
    });
    
    achievementService.checkAchievements(req.user.userId, pool);
    
    const [updatedUser] = await pool.execute(
      'SELECT pxl_balance FROM users WHERE id = ?',
      [req.user.userId]
    );
    
    return success(res, {
      x, y, color,
      cost,
      newBalance: updatedUser[0].pxl_balance,
      isGem: gemRows.length > 0,
      gemBonus
    });
  } catch (err) {
    await conn.rollback();
    next(err);
  } finally {
    conn.release();
  }
});

router.get('/pixel-info/:x/:y', authOptional, async (req, res, next) => {
  try {
    const x = validateCoordinate(req.params.x, 'X');
    const y = validateCoordinate(req.params.y, 'Y');
    
    const pool = req.app.get('db');
    
    const [pixels] = await pool.execute(
      `SELECT p.x, p.y, p.color, u.username, p.purchased_at 
       FROM pixels p 
       JOIN users u ON p.owner_id = u.id 
       WHERE p.x = ? AND p.y = ? AND p.grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)`,
      [x, y]
    );
    
    if (pixels.length === 0) {
      return success(res, { x, y, color: '#FFFFFF', owner: null, purchasedAt: null });
    }
    
    return success(res, {
      x: pixels[0].x,
      y: pixels[0].y,
      color: pixels[0].color,
      owner: pixels[0].username,
      purchasedAt: pixels[0].purchased_at
    });
  } catch (err) {
    next(err);
  }
});

router.get('/updates', authOptional, (req, res, next) => {
  const userId = req.user ? req.user.username : req.ip;
  const subscribedChunks = req.query.chunks ? req.query.chunks.split(',').map(c => c.trim()) : [];
  
  sseManager.addConnection(userId, res, subscribedChunks);
  
  req.on('close', () => {
    sseManager.removeConnection(userId);
  });
});

router.get('/session', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [sessions] = await pool.execute(
      `SELECT gs.*, 
        (SELECT COUNT(*) FROM pixels WHERE grid_session_id = gs.id) as total_pixels
       FROM grid_sessions gs 
       WHERE gs.is_current = 1`
    );
    
    if (sessions.length === 0) {
      throw new AppError('no_active_session', 500);
    }
    
    const session = sessions[0];
    
    const [themes] = await pool.execute(
      'SELECT * FROM weekly_themes WHERE week_start_date = ?',
      [session.week_start_date]
    );
    
    const isVotingPhase = new Date() >= new Date(session.week_start_date) && 
                          new Date().getDay() >= 5;
    
    return success(res, {
      id: session.id,
      weekStart: session.week_start_date,
      theme: themes.length > 0 ? themes[0].theme_name : 'Free Paint',
      description: themes.length > 0 ? themes[0].description : null,
      totalPixels: session.total_pixels,
      isVotingPhase,
      daysUntilReset: 7 - new Date().getDay()
    });
  } catch (err) {
    next(err);
  }
});

router.get('/gems', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [gems] = await pool.execute(
      'SELECT x, y, expires_at FROM canvas_gems WHERE expires_at > NOW()'
    );
    
    return success(res, {
      count: gems.length,
      expiresAt: gems.length > 0 ? gems[0].expires_at : null
    });
  } catch (err) {
    next(err);
  }
});

router.get('/leaderboard/week', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const limit = Math.min(parseInt(req.query.limit) || 10, 50);
    
    const [leaders] = await pool.execute(
      `SELECT u.username, u.id, COUNT(p.id) as pixel_count
       FROM users u
       JOIN pixels p ON u.id = p.owner_id
       JOIN grid_sessions gs ON p.grid_session_id = gs.id
       WHERE gs.is_current = 1
       GROUP BY u.id, u.username
       ORDER BY pixel_count DESC
       LIMIT ?`,
      [limit]
    );
    
    return success(res, { leaders });
  } catch (err) {
    next(err);
  }
});

module.exports = router;