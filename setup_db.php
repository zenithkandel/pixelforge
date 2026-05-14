<?php

require_once dirname(__DIR__) . '/includes/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
        ]
    );

    // Create database
    echo "Creating database '{$DB_NAME}'...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$DB_NAME}`");

    // 1. Grid sessions table
    echo "Creating grid_sessions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grid_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            is_current TINYINT(1) DEFAULT 0,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            total_pixels_painted BIGINT UNSIGNED DEFAULT 0,
            total_users BIGINT UNSIGNED DEFAULT 0,
            snapshot_url VARCHAR(255) NULL,
            INDEX idx_is_current (is_current),
            INDEX idx_started_at (started_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 2. Users table
    echo "Creating users table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(20) UNIQUE NOT NULL,
            email VARCHAR(254) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            pxl_balance BIGINT UNSIGNED DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_email_verified TINYINT(1) DEFAULT 0,
            email_verified_at DATETIME NULL,
            last_login_at DATETIME NULL,
            last_game_played_at DATETIME NULL,
            login_streak INT UNSIGNED DEFAULT 0,
            last_login_date DATE NULL,
            is_banned TINYINT(1) DEFAULT 0,
            ban_reason VARCHAR(255) NULL,
            banned_at DATETIME NULL,
            UNIQUE KEY unique_username_lower (LOWER(username)),
            UNIQUE KEY unique_email_lower (LOWER(email)),
            INDEX idx_created_at (created_at),
            INDEX idx_last_login_at (last_login_at),
            INDEX idx_is_banned (is_banned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 3. PXL transactions table
    echo "Creating pxl_transactions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pxl_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            amount BIGINT,
            type ENUM('game_earn', 'pixel_spend', 'achievement', 'daily_bonus', 'streak_bonus', 'combo_bonus', 'daily_highscore_bonus', 'admin_credit', 'admin_debit') NOT NULL,
            related_id BIGINT UNSIGNED NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_type (type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 4. Pixels table (current state of the canvas)
    echo "Creating pixels table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pixels (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_session_id BIGINT UNSIGNED NOT NULL,
            x INT UNSIGNED NOT NULL,
            y INT UNSIGNED NOT NULL,
            color VARCHAR(7) NOT NULL DEFAULT '#FFFFFF',
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (grid_session_id) REFERENCES grid_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_pixel_pos (grid_session_id, x, y),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 5. Chunks table (version tracking)
    echo "Creating chunks table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chunks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_session_id BIGINT UNSIGNED NOT NULL,
            chunk_x INT UNSIGNED NOT NULL,
            chunk_y INT UNSIGNED NOT NULL,
            version INT UNSIGNED DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (grid_session_id) REFERENCES grid_sessions(id) ON DELETE CASCADE,
            UNIQUE KEY unique_chunk_pos (grid_session_id, chunk_x, chunk_y),
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 6. Game sessions table
    echo "Creating game_sessions table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS game_sessions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            session_token VARCHAR(64) UNIQUE NOT NULL,
            seed INT UNSIGNED NOT NULL,
            hmac_key VARCHAR(255) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_is_active (is_active),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 7. Scores table (leaderboard data)
    echo "Creating scores table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS scores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            game_session_id BIGINT UNSIGNED NOT NULL,
            score INT UNSIGNED NOT NULL,
            duration_seconds INT UNSIGNED NOT NULL,
            pxl_earned BIGINT UNSIGNED NOT NULL,
            final_speed_tier INT UNSIGNED NOT NULL,
            lives_at_end INT UNSIGNED NOT NULL,
            highest_combo INT UNSIGNED NOT NULL DEFAULT 0,
            total_shards_collected INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_score (score),
            INDEX idx_created_at (created_at),
            INDEX idx_pxl_earned (pxl_earned)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 8. Pixel history table (permanent record)
    echo "Creating pixel_history table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pixel_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            grid_session_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            x INT UNSIGNED NOT NULL,
            y INT UNSIGNED NOT NULL,
            color VARCHAR(7) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (grid_session_id) REFERENCES grid_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_created_at (created_at),
            INDEX idx_grid_session_id (grid_session_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 9. Achievements table
    echo "Creating achievements table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS achievements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            achievement_key VARCHAR(50) UNIQUE NOT NULL,
            title VARCHAR(100) NOT NULL,
            description TEXT,
            pxl_reward INT UNSIGNED NOT NULL,
            category ENUM('game', 'canvas', 'overall', 'streak') NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 10. User achievements table
    echo "Creating user_achievements table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_achievements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            achievement_id INT UNSIGNED NOT NULL,
            achievement_key VARCHAR(50) NOT NULL,
            is_claimed TINYINT(1) DEFAULT 0,
            claimed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_achievement (user_id, achievement_id),
            INDEX idx_user_id (user_id),
            INDEX idx_is_claimed (is_claimed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 11. Login attempts table
    echo "Creating login_attempts table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NULL,
            ip_address VARCHAR(45) NOT NULL,
            email_or_username VARCHAR(254) NOT NULL,
            is_successful TINYINT(1) DEFAULT 0,
            failed_login_count INT UNSIGNED DEFAULT 0,
            lockout_until DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_ip_address (ip_address),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // 12. Admins table
    echo "Creating admins table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED UNIQUE NOT NULL,
            role ENUM('moderator', 'admin', 'super_admin') DEFAULT 'moderator',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed initial data
    echo "Seeding initial data...\n";

    // Create first grid session
    $pdo->exec("
        INSERT INTO grid_sessions (is_current, started_at) VALUES (1, NOW())
    ");

    // Insert achievement definitions
    $achievements = [
        ['first_game', 'First Blood', 'Complete your first game', 5, 'game'],
        ['speed_tier_3', 'Getting Fast', 'Reach Speed Tier 3', 8, 'game'],
        ['speed_tier_5', 'Blazing', 'Reach Speed Tier 5', 15, 'game'],
        ['speed_tier_7', 'Unstoppable', 'Reach Speed Tier 7', 25, 'game'],
        ['score_500', 'Decent Run', 'Score 500 in one game', 5, 'game'],
        ['score_2000', 'Impressive', 'Score 2,000 in one game', 15, 'game'],
        ['score_5000', 'Legend', 'Score 5,000 in one game', 30, 'game'],
        ['score_10000', 'Mythic', 'Score 10,000 in one game', 60, 'game'],
        ['combo_15', 'Chain Reaction', 'Reach 15x combo', 10, 'game'],
        ['combo_35', 'MAX COMBO', 'Reach MAX COMBO', 25, 'game'],
        ['first_pixel', "Painter's Debut", 'Place your first pixel', 10, 'canvas'],
        ['pixels_50', 'Contributor', 'Place 50 pixels', 20, 'canvas'],
        ['pixels_250', 'Artist', 'Place 250 pixels', 40, 'canvas'],
        ['pixels_1000', 'Master Painter', 'Place 1,000 pixels', 80, 'canvas'],
        ['rainbow_5', 'Prism Hunter', 'Collect 5 Rainbow Prisms', 15, 'game'],
        ['bomb_used', 'Demolition', 'Trigger a Pixel Bomb', 8, 'game'],
        ['total_earned_100', 'Century', 'Earn 100 PXL total', 20, 'overall'],
        ['streak_3', 'Regular', '3-day login streak', 10, 'streak'],
        ['streak_7', 'Dedicated', '7-day login streak', 20, 'streak'],
        ['streak_30', 'Devotee', '30-day login streak', 60, 'streak'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO achievements (achievement_key, title, description, pxl_reward, category)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($achievements as $achievement) {
        $stmt->execute($achievement);
    }

    echo "Database setup completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

?>