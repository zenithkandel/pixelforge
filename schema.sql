CREATE DATABASE IF NOT EXISTS pixelforge
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pixelforge;

-- ============================================================
-- GRID SESSIONS (must be created before pixels)
-- ============================================================
CREATE TABLE grid_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    snapshot_filename VARCHAR(255) DEFAULT NULL COMMENT 'PNG snapshot saved on reset',
    total_pixels_painted INT UNSIGNED DEFAULT 0,
    unique_painters INT UNSIGNED DEFAULT 0,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_current (is_current)
) ENGINE=InnoDB;

INSERT INTO grid_sessions (is_current) VALUES (1);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    pxl_balance INT UNSIGNED NOT NULL DEFAULT 0,
    total_pxl_earned INT UNSIGNED NOT NULL DEFAULT 0,
    total_pxl_spent INT UNSIGNED NOT NULL DEFAULT 0,
    login_streak INT UNSIGNED NOT NULL DEFAULT 0,
    last_login_date DATE DEFAULT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verify_token VARCHAR(64) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL DEFAULT NULL,
    password_reset_token VARCHAR(64) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL DEFAULT NULL,
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    ban_reason VARCHAR(255) DEFAULT NULL,
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    lockout_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    INDEX idx_email_verify (email_verify_token),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- PXL TRANSACTIONS (ledger, append-only)
-- ============================================================
CREATE TABLE pxl_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount INT NOT NULL COMMENT 'Positive = credit, negative = debit',
    type ENUM(
        'game_earn',
        'pixel_spend',
        'achievement',
        'daily_bonus',
        'streak_bonus',
        'combo_bonus',
        'daily_highscore_bonus',
        'admin_credit',
        'admin_debit'
    ) NOT NULL,
    reference_id VARCHAR(64) DEFAULT NULL COMMENT 'game_session_id, achievement_key, etc.',
    balance_after INT UNSIGNED NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_pxl_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB;

-- ============================================================
-- PIXELS (Current State)
-- ============================================================
CREATE TABLE pixels (
    x SMALLINT UNSIGNED NOT NULL,
    y SMALLINT UNSIGNED NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#FFFFFF',
    owner_id INT UNSIGNED NOT NULL,
    grid_session_id INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (x, y),
    FOREIGN KEY fk_pixel_owner (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_pixel_session (grid_session_id) REFERENCES grid_sessions(id),
    INDEX idx_owner (owner_id),
    INDEX idx_session (grid_session_id),
    INDEX idx_purchased (purchased_at)
) ENGINE=InnoDB;

-- ============================================================
-- CHUNKS (Cache Version Tracking)
-- ============================================================
CREATE TABLE chunks (
    chunk_x TINYINT UNSIGNED NOT NULL,
    chunk_y TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chunk_x, chunk_y)
) ENGINE=InnoDB;

INSERT INTO chunks (chunk_x, chunk_y)
SELECT a.n, b.n FROM
  (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 UNION SELECT 31) a,
  (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 UNION SELECT 31) b;

-- ============================================================
-- GAME SESSIONS
-- ============================================================
CREATE TABLE game_sessions (
    id VARCHAR(64) PRIMARY KEY COMMENT 'Secure random hex token',
    user_id INT UNSIGNED NOT NULL,
    prng_seed VARCHAR(64) NOT NULL COMMENT 'Seed issued by server for obstacle generation',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checkpoint_at TIMESTAMP NULL DEFAULT NULL,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    final_score INT UNSIGNED DEFAULT NULL,
    duration_seconds INT UNSIGNED DEFAULT NULL,
    pxl_earned INT UNSIGNED DEFAULT 0,
    lives_at_end TINYINT UNSIGNED DEFAULT NULL,
    max_speed_tier TINYINT UNSIGNED DEFAULT NULL,
    checkpoints_json JSON DEFAULT NULL COMMENT 'Array of checkpoint payloads',
    is_valid TINYINT(1) NOT NULL DEFAULT 1,
    invalidation_reason VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    FOREIGN KEY fk_gs_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_started (started_at),
    INDEX idx_valid (is_valid)
) ENGINE=InnoDB;

-- ============================================================
-- SCORES (Leaderboard)
-- ============================================================
CREATE TABLE scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_session_id VARCHAR(64) NOT NULL,
    score INT UNSIGNED NOT NULL,
    pxl_earned INT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL,
    max_speed_tier TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_score_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_score_session (game_session_id) REFERENCES game_sessions(id),
    INDEX idx_score_desc (score DESC),
    INDEX idx_user_score (user_id, score DESC),
    INDEX idx_daily (created_at, score DESC)
) ENGINE=InnoDB;

-- ============================================================
-- PIXEL PURCHASE HISTORY (permanent record)
-- ============================================================
CREATE TABLE pixel_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    x SMALLINT UNSIGNED NOT NULL,
    y SMALLINT UNSIGNED NOT NULL,
    color CHAR(7) NOT NULL,
    pxl_cost INT UNSIGNED NOT NULL DEFAULT 1,
    grid_session_id INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_ph_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_ph_session (grid_session_id) REFERENCES grid_sessions(id),
    INDEX idx_user (user_id),
    INDEX idx_coords (x, y),
    INDEX idx_session (grid_session_id),
    INDEX idx_time (purchased_at)
) ENGINE=InnoDB;

-- ============================================================
-- ACHIEVEMENTS DEFINITIONS
-- ============================================================
CREATE TABLE achievements (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    pxl_reward INT UNSIGNED NOT NULL,
    icon_class VARCHAR(50) DEFAULT NULL,
    UNIQUE KEY uq_key (key_name)
) ENGINE=InnoDB;

INSERT INTO achievements (key_name, title, description, pxl_reward) VALUES
('first_game', 'First Blood', 'Complete your first game', 5),
('speed_tier_3', 'Getting Fast', 'Reach Speed Tier 3', 8),
('speed_tier_5', 'Blazing', 'Reach Speed Tier 5', 15),
('speed_tier_7', 'Unstoppable', 'Reach Speed Tier 7', 25),
('score_500', 'Decent Run', 'Score 500 in one game', 5),
('score_2000', 'Impressive', 'Score 2,000 in one game', 15),
('score_5000', 'Legend', 'Score 5,000 in one game', 30),
('score_10000', 'Mythic', 'Score 10,000 in one game', 60),
('combo_15', 'Chain Reaction', 'Reach a 15x combo', 10),
('combo_35', 'MAX COMBO', 'Reach MAX COMBO (35x)', 25),
('first_pixel', "Painter's Debut", 'Place your first pixel', 10),
('pixels_50', 'Contributor', 'Place 50 pixels on the canvas', 20),
('pixels_250', 'Artist', 'Place 250 pixels on the canvas', 40),
('pixels_1000', 'Master Painter', 'Place 1,000 pixels on the canvas', 80),
('rainbow_5', 'Prism Hunter', 'Collect 5 Rainbow Prisms in games', 15),
('bomb_used', 'Demolition', 'Trigger a Pixel Bomb power-up', 8),
('total_earned_100', 'Century', 'Earn 100 PXL total (lifetime)', 20),
('streak_3', 'Regular', 'Maintain a 3-day login streak', 10),
('streak_7', 'Dedicated', 'Maintain a 7-day login streak', 20),
('streak_30', 'Devotee', 'Maintain a 30-day login streak', 60);

-- ============================================================
-- USER ACHIEVEMENTS (Junction)
-- ============================================================
CREATE TABLE user_achievements (
    user_id INT UNSIGNED NOT NULL,
    achievement_id TINYINT UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pxl_claimed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY fk_ua_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_ua_ach (achievement_id) REFERENCES achievements(id)
) ENGINE=InnoDB;

-- ============================================================
-- LOGIN ATTEMPTS (Security)
-- ============================================================
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username_attempted VARCHAR(100) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_cleanup (attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- ADMIN USERS
-- ============================================================
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;