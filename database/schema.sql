-- PixelForge Database Schema

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(30) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `balance` INT NOT NULL DEFAULT 0,
  `xp` INT NOT NULL DEFAULT 0,
  `level` INT NOT NULL DEFAULT 1,
  `role` ENUM('user','admin') NOT NULL DEFAULT 'user',
  `avatar_color` VARCHAR(7) NOT NULL DEFAULT '#7c3aed',
  `total_pixels_placed` INT NOT NULL DEFAULT 0,
  `total_games_played` INT NOT NULL DEFAULT 0,
  `total_score` BIGINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pixels` (
  `x` INT NOT NULL,
  `y` INT NOT NULL,
  `color` VARCHAR(7) NOT NULL,
  `owner_id` INT DEFAULT NULL,
  `placed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`x`, `y`),
  KEY `fk_pixels_owner` (`owner_id`),
  CONSTRAINT `fk_pixels_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `game_sessions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `board_state` JSON DEFAULT NULL,
  `moves_left` INT NOT NULL DEFAULT 30,
  `score` INT NOT NULL DEFAULT 0,
  `combo_max` INT NOT NULL DEFAULT 0,
  `status` ENUM('active','completed','abandoned') NOT NULL DEFAULT 'active',
  `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_game_sessions_user` (`user_id`),
  CONSTRAINT `fk_game_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `score_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `game_session_id` INT NOT NULL,
  `score` INT NOT NULL,
  `combo_max` INT NOT NULL DEFAULT 0,
  `currency_earned` INT NOT NULL,
  `xp_earned` INT NOT NULL,
  `played_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_score_log_user` (`user_id`),
  KEY `fk_score_log_session` (`game_session_id`),
  CONSTRAINT `fk_score_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_score_log_session` FOREIGN KEY (`game_session_id`) REFERENCES `game_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `achievements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(50) NOT NULL,
  `reward` INT NOT NULL DEFAULT 0,
  `category` ENUM('game','pixel','social') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_achievements_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_achievements` (
  `user_id` INT NOT NULL,
  `achievement_id` INT NOT NULL,
  `earned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `achievement_id`),
  KEY `fk_user_achievements_achievement` (`achievement_id`),
  CONSTRAINT `fk_user_achievements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_achievements_achievement` FOREIGN KEY (`achievement_id`) REFERENCES `achievements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `amount` INT NOT NULL,
  `type` ENUM('earn','spend') NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_transactions_user` (`user_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed achievements

INSERT INTO `achievements` (`slug`, `name`, `description`, `icon`, `reward`, `category`) VALUES
  ('first_match',    'First Spark',          'Complete your first match',    'spark',     10,  'game'),
  ('combo_5',        'Chain Reaction',       'Reach a 5x combo',            'chain',     50,  'game'),
  ('combo_10',       'Unstoppable',          'Reach a 10x combo',           'flame',    150,  'game'),
  ('score_1000',     'Thousand Points',      'Score 1000+ in a single game', 'trophy',   75,  'game'),
  ('score_5000',     'Gem Master',           'Score 5000+ in a single game', 'crown',   200,  'game'),
  ('score_10000',    'Legendary',            'Score 10000+ in a single game','diamond',  500,  'game'),
  ('games_10',       'Regular Player',       'Play 10 games',               'gamepad',   30,  'game'),
  ('games_100',      'Dedicated',            'Play 100 games',              'star',     200,  'game'),
  ('special_gem',    'Power Up!',            'Create your first special gem','lightning',  25,  'game'),
  ('first_pixel',    'First Stroke',         'Place your first pixel',      'brush',     10,  'pixel'),
  ('pixels_50',      'Painter',              'Place 50 pixels',             'palette',   40,  'pixel'),
  ('pixels_500',     'Artist',               'Place 500 pixels',            'art',      200,  'pixel'),
  ('pixels_2000',    'Masterpiece Creator',  'Place 2000 pixels',           'canvas',   500,  'pixel'),
  ('streak_3',       'On Fire',              'Login 3 days in a row',       'fire',      50,  'social'),
  ('streak_7',       'Weekly Warrior',       'Login 7 days in a row',       'calendar', 150,  'social'),
  ('level_5',        'Rising Star',          'Reach level 5',               'arrow-up',  75,  'social');
