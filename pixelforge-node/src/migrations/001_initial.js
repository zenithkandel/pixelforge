const mysql = require('mysql2/promise');
const bcrypt = require('bcrypt');
const config = require('../config');

const MIGRATION_SQL = `
-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(20) UNIQUE NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  pxl_balance INT UNSIGNED DEFAULT 100,
  total_pxl_earned INT UNSIGNED DEFAULT 0,
  total_pxl_spent INT UNSIGNED DEFAULT 0,
  games_played INT UNSIGNED DEFAULT 0,
  total_score BIGINT UNSIGNED DEFAULT 0,
  high_score INT UNSIGNED DEFAULT 0,
  is_admin TINYINT(1) DEFAULT 0,
  is_verified TINYINT(1) DEFAULT 0,
  verification_token VARCHAR(64),
  reset_token VARCHAR(64),
  reset_token_expires DATETIME,
  active_session VARCHAR(64),
  last_login DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_pxl_balance (pxl_balance),
  INDEX idx_high_score (high_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Achievements table
CREATE TABLE IF NOT EXISTS achievements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  pxl_reward INT UNSIGNED DEFAULT 0,
  icon VARCHAR(50),
  requirement_type VARCHAR(20),
  requirement_value INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User achievements table
CREATE TABLE IF NOT EXISTS user_achievements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  achievement_id INT UNSIGNED NOT NULL,
  earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_achievement (user_id, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grid sessions table
CREATE TABLE IF NOT EXISTS grid_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_start_date DATE NOT NULL,
  theme_name VARCHAR(100),
  theme_description TEXT,
  is_current TINYINT(1) DEFAULT 0,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME,
  UNIQUE KEY unique_week (week_start_date),
  INDEX idx_is_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chunks table
CREATE TABLE IF NOT EXISTS chunks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chunk_x INT NOT NULL,
  chunk_y INT NOT NULL,
  version INT UNSIGNED DEFAULT 1,
  grid_session_id INT UNSIGNED NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (grid_session_id) REFERENCES grid_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_chunk (chunk_x, chunk_y, grid_session_id),
  INDEX idx_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pixels table
CREATE TABLE IF NOT EXISTS pixels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  x SMALLINT UNSIGNED NOT NULL,
  y SMALLINT UNSIGNED NOT NULL,
  color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
  owner_id INT UNSIGNED NOT NULL,
  grid_session_id INT UNSIGNED NOT NULL,
  purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (grid_session_id) REFERENCES grid_sessions(id) ON DELETE CASCADE,
  UNIQUE KEY unique_pixel (x, y, grid_session_id),
  INDEX idx_owner (owner_id),
  INDEX idx_position (x, y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pixel history table
CREATE TABLE IF NOT EXISTS pixel_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  x SMALLINT UNSIGNED NOT NULL,
  y SMALLINT UNSIGNED NOT NULL,
  color VARCHAR(7) NOT NULL,
  owner_id INT UNSIGNED NOT NULL,
  grid_session_id INT UNSIGNED NOT NULL,
  action_type ENUM('purchase', 'overwrite', 'erase') DEFAULT 'purchase',
  purchased_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_session_time (grid_session_id, purchased_at),
  INDEX idx_owner_session (owner_id, grid_session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PXL transactions ledger
CREATE TABLE IF NOT EXISTS pxl_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  amount INT NOT NULL,
  transaction_type ENUM('earn', 'spend', 'bonus', 'refund', 'achievement') NOT NULL,
  description VARCHAR(255),
  related_id INT UNSIGNED,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_type (user_id, transaction_type),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Game sessions table
CREATE TABLE IF NOT EXISTS game_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_token VARCHAR(64) UNIQUE NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  end_time DATETIME,
  start_seed INT UNSIGNED NOT NULL,
  checkpoints_json TEXT,
  final_score INT UNSIGNED DEFAULT 0,
  score_hmac VARCHAR(128),
  is_valid TINYINT(1) DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_token (session_token),
  INDEX idx_time (start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly themes table
CREATE TABLE IF NOT EXISTS weekly_themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  week_start_date DATE NOT NULL,
  theme_name VARCHAR(100) NOT NULL,
  description TEXT,
  voting_start DATE,
  voting_end DATE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_week_theme (week_start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theme votes table
CREATE TABLE IF NOT EXISTS theme_votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  week_start_date DATE NOT NULL,
  area_x INT UNSIGNED NOT NULL,
  area_y INT UNSIGNED NOT NULL,
  voted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_vote (user_id, week_start_date),
  INDEX idx_week_area (week_start_date, area_x, area_y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canvas gems table
CREATE TABLE IF NOT EXISTS canvas_gems (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  x SMALLINT UNSIGNED NOT NULL,
  y SMALLINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_gem (x, y)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Power hour events table
CREATE TABLE IF NOT EXISTS events_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(50) NOT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME,
  extra_data JSON,
  INDEX idx_type_time (event_type, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limits table
CREATE TABLE IF NOT EXISTS rate_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) NOT NULL,
  window_start BIGINT NOT NULL,
  request_time DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_key_window (key_name, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Token blacklist table
CREATE TABLE IF NOT EXISTS token_blacklist (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash VARCHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
`;

const ACHIEVEMENTS_SEED = [
  ['first_pixel', 'First Mark', 'Place your first pixel on the canvas', 10, '🎨', 'pixels_placed', 1],
  ['pixel_master', 'Pixel Master', 'Place 100 pixels on the canvas', 50, '🖼️', 'pixels_placed', 100],
  ['pixel_legend', 'Pixel Legend', 'Place 1000 pixels on the canvas', 200, '👑', 'pixels_placed', 1000],
  ['dash_beginner', 'Dash Beginner', 'Complete your first game of PIXEL DASH', 15, '⚡', 'games_completed', 1],
  ['dash_pro', 'Dash Pro', 'Score 5000 points in a single game', 50, '🚀', 'high_score', 5000],
  ['dash_legend', 'Dash Legend', 'Score 15000 points in a single game', 150, '🏆', 'high_score', 15000],
  ['social_butterfly', 'Social Butterfly', 'Place 50 pixels in the same week as others', 30, '🦋', 'weekly_collab', 50],
  ['collector', 'Collector', 'Find 10 hidden gems', 40, '💎', 'gems_found', 10],
  ['high roller', 'High Roller', 'Earn 500 PXL in a single session', 50, '💰', 'pxl_earned', 500],
  ['veteran', 'Veteran', 'Play 100 games', 100, '🎖️', 'games_completed', 100]
];

const THEMES_SEED = [
  ['Space Odyssey', 'Paint the cosmos - rockets, planets, aliens, stars!'],
  ['Underwater World', 'Create ocean life, coral reefs, and underwater scenes.'],
  ['Fantasy Kingdom', 'Dragons, castles, knights, and magical creatures.'],
  ['Cyberpunk City', 'Neon lights, futuristic buildings, and tech.'],
  ['Wild West', 'Cowboys, deserts, cacti, and old west towns.'],
  ['Enchanted Forest', 'Mystical woods, fairies, mushrooms, and woodland creatures.']
];

async function runMigration(pool) {
  console.log('Running database migration...');
  
  const statements = MIGRATION_SQL.split(';').filter(s => s.trim());
  
  for (const statement of statements) {
    if (statement.trim()) {
      await pool.execute(statement);
    }
  }
  
  console.log('Database tables created successfully.');
  
  const [existingAchievements] = await pool.execute('SELECT COUNT(*) as count FROM achievements');
  if (existingAchievements[0].count === 0) {
    for (const ach of ACHIEVEMENTS_SEED) {
      await pool.execute(
        'INSERT INTO achievements (code, name, description, pxl_reward, icon, requirement_type, requirement_value) VALUES (?, ?, ?, ?, ?, ?, ?)',
        ach
      );
    }
    console.log('Achievements seeded.');
  }
  
  const [adminExists] = await pool.execute('SELECT COUNT(*) as count FROM users WHERE username = ?', ['admin']);
  if (adminExists[0].count === 0) {
    const tempPassword = 'admin' + Math.random().toString(36).substring(2, 10);
    const passwordHash = await bcrypt.hash(tempPassword, 12);
    
    await pool.execute(
      'INSERT INTO users (username, email, password_hash, is_admin, is_verified, pxl_balance) VALUES (?, ?, ?, 1, 1, 10000)',
      ['admin', 'admin@pixelforge.local', passwordHash]
    );
    
    console.log('\n========================================');
    console.log('ADMIN ACCOUNT CREATED');
    console.log('========================================');
    console.log('Username: admin');
    console.log('Password: ' + tempPassword);
    console.log('========================================');
    console.log('PLEASE CHANGE THIS PASSWORD IMMEDIATELY!');
    console.log('========================================\n');
  }
  
  const [sessionExists] = await pool.execute('SELECT COUNT(*) as count FROM grid_sessions');
  if (sessionExists[0].count === 0) {
    const today = new Date();
    const dayOfWeek = today.getDay();
    const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1);
    const monday = new Date(today.setDate(diff));
    const weekStart = monday.toISOString().split('T')[0];
    
    await pool.execute(
      'INSERT INTO grid_sessions (week_start_date, theme_name, theme_description, is_current) VALUES (?, ?, ?, 1)',
      [weekStart, 'Welcome to PixelForge', 'Start your creative journey!']
    );
    console.log('Initial grid session created.');
  }
  
  console.log('Migration completed successfully!');
  return true;
}

module.exports = { runMigration, MIGRATION_SQL };