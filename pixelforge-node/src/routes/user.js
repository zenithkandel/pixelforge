const express = require('express');
const router = express.Router();
const { authRequired } = require('../middleware/auth');
const { success, AppError } = require('../utils/response');

router.get('/me', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [users] = await pool.execute(
      `SELECT u.id, u.username, u.email, u.pxl_balance, u.total_pxl_earned, u.total_pxl_spent,
              u.games_played, u.total_score, u.high_score, u.created_at,
              (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)) as weekly_pixels
       FROM users u WHERE u.id = ?`,
      [req.user.userId]
    );
    
    if (users.length === 0) {
      throw new AppError('user_not_found', 404);
    }
    
    const user = users[0];
    
    const [achievements] = await pool.execute(
      `SELECT a.code, a.name, a.description, a.icon, ua.earned_at
       FROM user_achievements ua
       JOIN achievements a ON ua.achievement_id = a.id
       WHERE ua.user_id = ?
       ORDER BY ua.earned_at DESC`,
      [req.user.userId]
    );
    
    return success(res, {
      id: user.id,
      username: user.username,
      email: user.email,
      pxlBalance: user.pxl_balance,
      totalPxlEarned: user.total_pxl_earned,
      totalPxlSpent: user.total_pxl_spent,
      gamesPlayed: user.games_played,
      totalScore: user.total_score,
      highScore: user.high_score,
      weeklyPixels: user.weekly_pixels,
      achievements,
      joinedAt: user.created_at
    });
  } catch (err) {
    next(err);
  }
});

router.get('/:username', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const { username } = req.params;
    
    const [users] = await pool.execute(
      `SELECT u.id, u.username, u.pxl_balance, u.games_played, u.high_score, u.total_score, u.created_at,
              (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)) as weekly_pixels,
              (SELECT COUNT(*) FROM user_achievements WHERE user_id = u.id) as achievement_count
       FROM users u WHERE u.username = ?`,
      [username.toLowerCase()]
    );
    
    if (users.length === 0) {
      throw new AppError('user_not_found', 404);
    }
    
    const user = users[0];
    
    const [recentPixels] = await pool.execute(
      `SELECT p.x, p.y, p.color, p.purchased_at
       FROM pixels p
       JOIN grid_sessions gs ON p.grid_session_id = gs.id
       WHERE p.owner_id = ? AND gs.is_current = 1
       ORDER BY p.purchased_at DESC
       LIMIT 20`,
      [user.id]
    );
    
    return success(res, {
      username: user.username,
      pxlBalance: user.pxl_balance,
      gamesPlayed: user.games_played,
      highScore: user.high_score,
      totalScore: user.total_score,
      weeklyPixels: user.weekly_pixels,
      achievementCount: user.achievement_count,
      joinedAt: user.created_at,
      recentPixels
    });
  } catch (err) {
    next(err);
  }
});

router.get('/transactions/history', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const page = Math.max(1, parseInt(req.query.page) || 1);
    const limit = Math.min(50, Math.max(1, parseInt(req.query.limit) || 20));
    const offset = (page - 1) * limit;
    
    const [transactions] = await pool.execute(
      `SELECT id, amount, transaction_type, description, created_at
       FROM pxl_transactions
       WHERE user_id = ?
       ORDER BY created_at DESC
       LIMIT ? OFFSET ?`,
      [req.user.userId, limit, offset]
    );
    
    const [countResult] = await pool.execute(
      'SELECT COUNT(*) as total FROM pxl_transactions WHERE user_id = ?',
      [req.user.userId]
    );
    
    return success(res, {
      transactions,
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

router.get('/achievements/list', async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [achievements] = await pool.execute(
      'SELECT code, name, description, pxl_reward, icon FROM achievements ORDER BY pxl_reward ASC'
    );
    
    return success(res, { achievements });
  } catch (err) {
    next(err);
  }
});

module.exports = router;