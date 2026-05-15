const express = require('express');
const router = express.Router();
const { success, AppError } = require('../utils/response');

router.get('/pxl', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const limit = Math.min(50, Math.max(1, parseInt(req.query.limit) || 10));
    
    const [leaders] = await pool.execute(
      `SELECT id, username, pxl_balance, total_pxl_earned, total_pxl_spent
       FROM users
       ORDER BY pxl_balance DESC
       LIMIT ?`,
      [limit]
    );
    
    return success(res, { leaders });
  } catch (err) {
    next(err);
  }
});

router.get('/score', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const limit = Math.min(50, Math.max(1, parseInt(req.query.limit) || 10));
    
    const [leaders] = await pool.execute(
      `SELECT id, username, high_score, total_score, games_played
       FROM users
       ORDER BY high_score DESC
       LIMIT ?`,
      [limit]
    );
    
    return success(res, { leaders });
  } catch (err) {
    next(err);
  }
});

router.get('/weekly-pixels', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const limit = Math.min(50, Math.max(1, parseInt(req.query.limit) || 10));
    
    const [leaders] = await pool.execute(
      `SELECT u.id, u.username, COUNT(p.id) as pixel_count
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

router.get('/achievements', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const limit = Math.min(50, Math.max(1, parseInt(req.query.limit) || 10));
    
    const [leaders] = await pool.execute(
      `SELECT u.id, u.username, COUNT(ua.id) as achievement_count
       FROM users u
       LEFT JOIN user_achievements ua ON u.id = ua.user_id
       GROUP BY u.id, u.username
       HAVING achievement_count > 0
       ORDER BY achievement_count DESC, u.username ASC
       LIMIT ?`,
      [limit]
    );
    
    return success(res, { leaders });
  } catch (err) {
    next(err);
  }
});

module.exports = router;