const cron = require('node-cron');
const chunkService = require('./chunkService');
const sseManager = require('./sseManager');

let scheduledTasks = [];

function initScheduling(pool) {
  console.log('Initializing scheduled tasks...');

  const weeklyReset = cron.schedule('0 0 * * 0', async () => {
    console.log('Running weekly grid reset...');
    await resetGrid(pool);
  }, {
    scheduled: true,
    timezone: 'UTC'
  });
  scheduledTasks.push(weeklyReset);

  const gemReset = cron.schedule('0 * * * *', async () => {
    console.log('Spawning hidden gems...');
    await spawnGems(pool);
  }, {
    scheduled: true,
    timezone: 'UTC'
  });
  scheduledTasks.push(gemReset);

  const cleanup = cron.schedule('0 3 * * *', async () => {
    console.log('Running cleanup tasks...');
    await cleanupTasks(pool);
  }, {
    scheduled: true,
    timezone: 'UTC'
  });
  scheduledTasks.push(cleanup);

  const powerHour = cron.schedule(`${Math.floor(Math.random() * 60)} ${Math.floor(Math.random() * 24)} * * *`, async () => {
    console.log('Starting Power Hour event...');
    await startPowerHour(pool);
  }, {
    scheduled: true,
    timezone: 'UTC'
  });
  scheduledTasks.push(powerHour);

  console.log('Scheduled tasks initialized.');
}

async function resetGrid(pool) {
  const conn = await pool.getConnection();
  
  try {
    await conn.beginTransaction();

    await conn.execute(
      `INSERT INTO grid_sessions (week_start_date, is_current) 
       SELECT DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), 0 
       WHERE NOT EXISTS (SELECT 1 FROM grid_sessions WHERE week_start_date = DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))`
    );

    const [sessions] = await conn.execute(
      'SELECT id, week_start_date FROM grid_sessions WHERE is_current = 1'
    );

    if (sessions.length > 0) {
      await conn.execute(
        'UPDATE grid_sessions SET is_current = 0, ended_at = NOW() WHERE id = ?',
        [sessions[0].id]
      );
    }

    const today = new Date();
    const dayOfWeek = today.getDay();
    const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
    const monday = new Date(today.setDate(diff));
    const weekStart = monday.toISOString().split('T')[0];

    const themes = [
      { name: 'Space Odyssey', desc: 'Paint the cosmos - rockets, planets, aliens!' },
      { name: 'Underwater World', desc: 'Create ocean life, coral reefs!' },
      { name: 'Fantasy Kingdom', desc: 'Dragons, castles, knights!' },
      { name: 'Cyberpunk City', desc: 'Neon lights, futuristic buildings!' },
      { name: 'Wild West', desc: 'Cowboys, deserts, cacti!' },
      { name: 'Enchanted Forest', desc: 'Mystical woods, fairies!' }
    ];
    const theme = themes[Math.floor(Math.random() * themes.length)];

    await conn.execute(
      `INSERT INTO grid_sessions (week_start_date, theme_name, theme_description, is_current) VALUES (?, ?, ?, 1)
       ON DUPLICATE KEY UPDATE theme_name = VALUES(theme_name), theme_description = VALUES(theme_description), is_current = 1, ended_at = NULL`,
      [weekStart, theme.name, theme.desc]
    );

    const [newSession] = await conn.execute(
      'SELECT id FROM grid_sessions WHERE week_start_date = ? AND is_current = 1',
      [weekStart]
    );

    if (newSession.length > 0) {
      await conn.execute('DELETE FROM pixels WHERE grid_session_id = ?', [newSession[0].id]);
      await conn.execute('DELETE FROM chunks WHERE grid_session_id = ?', [newSession[0].id]);
      await conn.execute('DELETE FROM canvas_gems');
    }

    await conn.commit();

    chunkService.invalidateAll();

    sseManager.broadcast('grid_reset', {
      message: 'The canvas has been reset! New week begins.',
      theme: theme.name,
      weekStart
    });

    console.log('Grid reset completed successfully.');
    return true;
  } catch (err) {
    await conn.rollback();
    console.error('Grid reset failed:', err);
    throw err;
  } finally {
    conn.release();
  }
}

async function spawnGems(pool) {
  try {
    await pool.execute('DELETE FROM canvas_gems WHERE expires_at < NOW()');

    const [whitePixels] = await pool.execute(
      `SELECT x, y FROM pixels p
       JOIN grid_sessions gs ON p.grid_session_id = gs.id
       WHERE gs.is_current = 1 AND p.color = '#FFFFFF'
       ORDER BY RAND() LIMIT 5`
    );

    if (whitePixels.length === 0) {
      const x = Math.floor(Math.random() * 800);
      const y = Math.floor(Math.random() * 800);
      const expiresAt = new Date(Date.now() + 60 * 60 * 1000);

      await pool.execute(
        'INSERT IGNORE INTO canvas_gems (x, y, expires_at) VALUES (?, ?, ?)',
        [x, y, expiresAt]
      );
    } else {
      for (const pixel of whitePixels) {
        const expiresAt = new Date(Date.now() + 60 * 60 * 1000);
        await pool.execute(
          'INSERT IGNORE INTO canvas_gems (x, y, expires_at) VALUES (?, ?, ?)',
          [pixel.x, pixel.y, expiresAt]
        );
      }
    }

    console.log('Hidden gems spawned successfully.');
  } catch (err) {
    console.error('Failed to spawn gems:', err);
  }
}

async function cleanupTasks(pool) {
  try {
    await pool.execute('DELETE FROM rate_limits WHERE window_start < ?', [Date.now() - 60 * 60 * 1000]);

    await pool.execute('DELETE FROM token_blacklist WHERE expires_at < NOW()');

    const [expiredSessions] = await pool.execute(
      'SELECT session_token FROM game_sessions WHERE end_time IS NULL AND start_time < DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
    );

    for (const session of expiredSessions) {
      await pool.execute(
        'UPDATE game_sessions SET end_time = NOW(), is_valid = 0 WHERE session_token = ?',
        [session.session_token]
      );
    }

    console.log('Cleanup completed successfully.');
  } catch (err) {
    console.error('Cleanup failed:', err);
  }
}

async function startPowerHour(pool) {
  try {
    const eventId = `power_hour_${Date.now()}`;
    
    await pool.execute(
      'INSERT INTO events_log (event_type, extra_data) VALUES (?, ?)',
      ['power_hour', JSON.stringify({ active: true })]
    );

    sseManager.broadcast('power_hour_start', {
      message: 'Power Hour has begun! Free pixels for 10 minutes!',
      bonusMultiplier: 0,
      maxFreePixels: 5
    });

    setTimeout(async () => {
      await pool.execute(
        'UPDATE events_log SET ended_at = NOW() WHERE event_type = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1',
        ['power_hour']
      );

      sseManager.broadcast('power_hour_end', {
        message: 'Power Hour has ended. See you next time!'
      });

      console.log('Power Hour completed.');
    }, 10 * 60 * 1000);

    console.log('Power Hour event started.');
  } catch (err) {
    console.error('Power Hour failed:', err);
  }
}

function stopAll() {
  for (const task of scheduledTasks) {
    task.stop();
  }
  scheduledTasks = [];
  console.log('All scheduled tasks stopped.');
}

module.exports = {
  initScheduling,
  resetGrid,
  spawnGems,
  cleanupTasks,
  startPowerHour,
  stopAll
};