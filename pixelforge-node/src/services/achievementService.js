async function checkAchievements(userId, pool) {
  try {
    const [userRows] = await pool.execute(
      `SELECT u.games_played, u.high_score, u.total_score,
              (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)) as weekly_pixels,
              (SELECT COUNT(*) FROM canvas_gems WHERE x IN (SELECT x FROM pixels WHERE owner_id = u.id)) as gems_found
       FROM users u WHERE u.id = ?`,
      [userId]
    );

    if (userRows.length === 0) return;

    const user = userRows[0];

    const [allAchievements] = await pool.execute(
      'SELECT * FROM achievements'
    );

    const [earnedAchievements] = await pool.execute(
      'SELECT achievement_id FROM user_achievements WHERE user_id = ?',
      [userId]
    );

    const earnedIds = new Set(earnedAchievements.map(a => a.achievement_id));

    for (const achievement of allAchievements) {
      if (earnedIds.has(achievement.id)) continue;

      let earned = false;

      switch (achievement.requirement_type) {
        case 'games_completed':
          earned = user.games_played >= achievement.requirement_value;
          break;
        case 'high_score':
          earned = user.high_score >= achievement.requirement_value;
          break;
        case 'pixels_placed':
          earned = user.weekly_pixels >= achievement.requirement_value;
          break;
        case 'gems_found':
          earned = user.gems_found >= achievement.requirement_value;
          break;
      }

      if (earned) {
        await grantAchievement(userId, achievement, pool);
      }
    }
  } catch (err) {
    console.error('Error checking achievements:', err);
  }
}

async function grantAchievement(userId, achievement, pool) {
  const conn = await pool.getConnection();

  try {
    await conn.beginTransaction();

    await conn.execute(
      'INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)',
      [userId, achievement.id]
    );

    if (achievement.pxl_reward > 0) {
      await conn.execute(
        'UPDATE users SET pxl_balance = pxl_balance + ? WHERE id = ?',
        [achievement.pxl_reward, userId]
      );

      await conn.execute(
        'INSERT INTO pxl_transactions (user_id, amount, transaction_type, description) VALUES (?, ?, ?, ?)',
        [userId, achievement.pxl_reward, 'achievement', `Achievement: ${achievement.name}`]
      );
    }

    await conn.commit();

    const sseManager = require('./sseManager');
    sseManager.sendToUser(
      userId.toString(),
      'achievement',
      {
        code: achievement.code,
        name: achievement.name,
        description: achievement.description,
        icon: achievement.icon,
        reward: achievement.pxl_reward
      }
    );

    return true;
  } catch (err) {
    await conn.rollback();
    throw err;
  } finally {
    conn.release();
  }
}

async function getUserAchievements(userId, pool) {
  const [achievements] = await pool.execute(
    `SELECT a.code, a.name, a.description, a.icon, a.pxl_reward, ua.earned_at
     FROM user_achievements ua
     JOIN achievements a ON ua.achievement_id = a.id
     WHERE ua.user_id = ?
     ORDER BY ua.earned_at DESC`,
    [userId]
  );

  return achievements;
}

async function getAllAchievements(pool) {
  const [achievements] = await pool.execute(
    'SELECT code, name, description, icon, pxl_reward, requirement_type, requirement_value FROM achievements'
  );

  return achievements;
}

module.exports = {
  checkAchievements,
  grantAchievement,
  getUserAchievements,
  getAllAchievements
};