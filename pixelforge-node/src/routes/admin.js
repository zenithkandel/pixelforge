const express = require('express');
const router = express.Router();
const bcrypt = require('bcrypt');
const { authRequired, adminRequired } = require('../middleware/auth');
const { validateUsername, validateEmail } = require('../middleware/validate');
const { success, AppError } = require('../utils/response');

router.use(authRequired);
router.use(adminRequired);

router.get('/users', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(100, Math.max(1, parseInt(req.query.limit) || 20));
    const offset = (page - 1) * limit;
    
    const [users] = await pool.execute(
      `SELECT id, username, email, pxl_balance, games_played, high_score, is_admin, is_verified, created_at, last_login
       FROM users
       ORDER BY created_at DESC
       LIMIT ? OFFSET ?`,
      [limit, offset]
    );
    
    const [countResult] = await pool.execute('SELECT COUNT(*) as total FROM users');
    
    return success(res, {
      users,
      pagination: {
        page,
        limit,
        total: countResult[0].total,
        pages: Math.ceil(countResult[0].total / limit)
      }
    });
  } catch (err) {
    next(err);
  }
});

router.patch('/users/:id', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const userId = parseInt(req.params.id, 10);
    
    if (userId === req.user.userId) {
      throw new AppError('cannot_modify_self', 400);
    }
    
    const { pxl_balance, is_admin, is_verified } = req.body;
    
    const updates = [];
    const values = [];
    
    if (typeof pxl_balance === 'number' && pxl_balance >= 0) {
      updates.push('pxl_balance = ?');
      values.push(pxl_balance);
    }
    
    if (typeof is_admin === 'boolean') {
      updates.push('is_admin = ?');
      values.push(is_admin ? 1 : 0);
    }
    
    if (typeof is_verified === 'boolean') {
      updates.push('is_verified = ?');
      values.push(is_verified ? 1 : 0);
    }
    
    if (updates.length === 0) {
      throw new AppError('no_updates', 400);
    }
    
    values.push(userId);
    
    await pool.execute(
      `UPDATE users SET ${updates.join(', ')} WHERE id = ?`,
      values
    );
    
    return success(res, { message: 'User updated successfully' });
  } catch (err) {
    next(err);
  }
});

router.delete('/users/:id', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const userId = parseInt(req.params.id, 10);
    
    if (userId === req.user.userId) {
      throw new AppError('cannot_delete_self', 400);
    }
    
    await pool.execute('DELETE FROM users WHERE id = ?', [userId]);
    
    return success(res, { message: 'User deleted successfully' });
  } catch (err) {
    next(err);
  }
});

router.post('/theme', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const { themeName, description, weekStartDate } = req.body;
    
    if (!themeName || !weekStartDate) {
      throw new AppError('missing_fields', 400, 'Theme name and week start date required');
    }
    
    const [existing] = await pool.execute(
      'SELECT id FROM weekly_themes WHERE week_start_date = ?',
      [weekStartDate]
    );
    
    if (existing.length > 0) {
      await pool.execute(
        'UPDATE weekly_themes SET theme_name = ?, description = ? WHERE week_start_date = ?',
        [themeName, description || null, weekStartDate]
      );
    } else {
      await pool.execute(
        'INSERT INTO weekly_themes (week_start_date, theme_name, description) VALUES (?, ?, ?)',
        [weekStartDate, themeName, description || null]
      );
    }
    
    await pool.execute(
      'UPDATE grid_sessions SET theme_name = ?, theme_description = ? WHERE week_start_date = ?',
      [themeName, description || null, weekStartDate]
    );
    
    return success(res, { message: 'Theme set successfully' });
  } catch (err) {
    next(err);
  }
});

router.get('/stats', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [userCount] = await pool.execute('SELECT COUNT(*) as count FROM users');
    const [gameCount] = await pool.execute('SELECT COUNT(*) as count FROM game_sessions');
    const [pixelCount] = await pool.execute('SELECT COUNT(*) as count FROM pixels WHERE grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)');
    const [gemCount] = await pool.execute('SELECT COUNT(*) as count FROM canvas_gems WHERE expires_at > NOW()');
    
    return success(res, {
      totalUsers: userCount[0].count,
      totalGames: gameCount[0].count,
      activePixels: pixelCount[0].count,
      activeGems: gemCount[0].count
    });
  } catch (err) {
    next(err);
  }
});

router.post('/reset-grid', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const scheduling = require('../services/scheduling');
    
    await scheduling.resetGrid(pool);
    
    return success(res, { message: 'Grid reset initiated' });
  } catch (err) {
    next(err);
  }
});

router.get('/grid-snapshot', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [pixels] = await pool.execute(
      `SELECT x, y, color, owner_id 
       FROM pixels 
       WHERE grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)`
    );
    
    return success(res, {
      pixels,
      count: pixels.length
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;