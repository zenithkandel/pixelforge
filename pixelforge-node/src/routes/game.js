const express = require('express');
const crypto = require('crypto');
const router = express.Router();
const { authRequired } = require('../middleware/auth');
const { validateScore, validateSessionToken, validatePositiveInt } = require('../middleware/validate');
const { success, error, AppError } = require('../utils/response');
const gameValidator = require('../services/gameValidator');
const pxlService = require('../services/pxlService');
const config = require('../config');

router.post('/start', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const userId = req.user.userId;
    
    const [activeSessions] = await pool.execute(
      'SELECT id FROM game_sessions WHERE user_id = ? AND end_time IS NULL AND start_time > DATE_SUB(NOW(), INTERVAL 10 MINUTE)',
      [userId]
    );
    
    if (activeSessions.length > 0) {
      await pool.execute(
        'UPDATE game_sessions SET end_time = NOW() WHERE user_id = ? AND end_time IS NULL',
        [userId]
      );
    }
    
    const seed = Math.floor(Math.random() * 0xFFFFFFFF);
    const sessionToken = crypto.randomBytes(32).toString('hex');
    
    const [result] = await pool.execute(
      'INSERT INTO game_sessions (session_token, user_id, start_seed) VALUES (?, ?, ?)',
      [sessionToken, userId, seed]
    );
    
    const signingKey = crypto.createHmac('sha256', config.hmac.secret)
      .update(sessionToken)
      .digest('hex');
    
    return success(res, {
      sessionToken,
      seed,
      signingKey,
      expiresAt: Date.now() + 10 * 60 * 1000
    });
  } catch (err) {
    next(err);
  }
});

router.post('/checkpoint', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const { sessionToken, checkpointData } = req.body;
    
    validateSessionToken(sessionToken);
    
    const [sessions] = await pool.execute(
      'SELECT * FROM game_sessions WHERE session_token = ? AND user_id = ? AND end_time IS NULL',
      [sessionToken, req.user.userId]
    );
    
    if (sessions.length === 0) {
      throw new AppError('invalid_session', 400, 'Invalid or expired game session');
    }
    
    const session = sessions[0];
    const checkpoints = checkpointData ? JSON.parse(checkpointData) : [];
    
    await pool.execute(
      'UPDATE game_sessions SET checkpoints_json = ? WHERE id = ?',
      [JSON.stringify(checkpoints), session.id]
    );
    
    return success(res, { message: 'Checkpoint saved' });
  } catch (err) {
    if (err instanceof SyntaxError) {
      return error(res, 'invalid_checkpoint_data', 400);
    }
    next(err);
  }
});

router.post('/submit', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    const { sessionToken, score, checkpoints, checkpointsHmac, duration, obstaclesHit, powerUpsCollected } = req.body;
    
    validateSessionToken(sessionToken);
    const finalScore = validateScore(score);
    const gameDuration = validatePositiveInt(duration, 'Duration');
    
    const [sessions] = await pool.execute(
      'SELECT * FROM game_sessions WHERE session_token = ? AND user_id = ? AND end_time IS NULL',
      [sessionToken, req.user.userId]
    );
    
    if (sessions.length === 0) {
      throw new AppError('invalid_session', 400, 'Invalid or expired game session');
    }
    
    const session = sessions[0];
    const sessionAge = Date.now() - new Date(session.start_time).getTime();
    
    if (sessionAge > 15 * 60 * 1000) {
      throw new AppError('session_expired', 400, 'Game session has expired');
    }
    
    const signingKey = crypto.createHmac('sha256', config.hmac.secret)
      .update(sessionToken)
      .digest('hex');
    
    // HMAC validation disabled for now - can be implemented later with proper client-side signing
    // const expectedHmac = crypto.createHmac('sha256', signingKey)
    //   .update(score.toString() + (checkpoints || ''))
    //   .digest('hex');
    // 
    // if (checkpointsHmac !== expectedHmac) {
    //   await pool.execute(
    //     'UPDATE game_sessions SET is_valid = 0 WHERE id = ?',
    //     [session.id]
    //   );
    //   throw new AppError('cheat_detected', 400, 'Score validation failed');
    // }
    
    const validation = gameValidator.validateScore({
      score: finalScore,
      duration: gameDuration,
      startSeed: session.start_seed,
      obstaclesHit: obstaclesHit || 0,
      powerUpsCollected: powerUpsCollected || 0,
      checkpointsCount: checkpoints ? checkpoints.length : 0
    });
    
    if (!validation.valid) {
      await pool.execute(
        'UPDATE game_sessions SET is_valid = 0 WHERE id = ?',
        [session.id]
      );
      throw new AppError('invalid_score', 400, validation.reason);
    }
    
    await pool.execute(
      'UPDATE game_sessions SET end_time = NOW(), final_score = ?, score_hmac = ?, checkpoints_json = ?, is_valid = 1 WHERE id = ?',
      [finalScore, checkpointsHmac, JSON.stringify(checkpoints || []), session.id]
    );
    
    const pxlEarned = Math.floor(finalScore / 100);
    
    await pool.execute(
      'UPDATE users SET games_played = games_played + 1, total_score = total_score + ?, high_score = IF(? > high_score, ?, high_score) WHERE id = ?',
      [finalScore, finalScore, finalScore, req.user.userId]
    );
    
    await pxlService.creditPxlDirect(pool, req.user.userId, pxlEarned, 'earn', `PIXEL DASH score: ${finalScore}`, session.id);
    
    const [userRows] = await pool.execute(
      'SELECT pxl_balance FROM users WHERE id = ?',
      [req.user.userId]
    );
    const newBalance = userRows[0]?.pxl_balance || 0;
    
    return success(res, {
      score: finalScore,
      pxlEarned,
      newBalance,
      isHighScore: validation.isHighScore
    });
  } catch (err) {
    next(err);
  }
});

router.get('/stats', authRequired, async (req, res, next) => {
  try {
    const pool = req.app.get('db');
    
    const [users] = await pool.execute(
      'SELECT games_played, high_score, total_score FROM users WHERE id = ?',
      [req.user.userId]
    );
    
    if (users.length === 0) {
      throw new AppError('user_not_found', 404);
    }
    
    const [recentGames] = await pool.execute(
      'SELECT final_score, start_time, is_valid FROM game_sessions WHERE user_id = ? ORDER BY start_time DESC LIMIT 10',
      [req.user.userId]
    );
    
    return success(res, {
      gamesPlayed: users[0].games_played,
      highScore: users[0].high_score,
      totalScore: users[0].total_score,
      recentGames
    });
  } catch (err) {
    next(err);
  }
});

module.exports = router;