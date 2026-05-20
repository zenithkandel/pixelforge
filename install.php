<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/achievements.php';

function run_install() {
    $pdo = Database::getInstance();

    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            balance INT NOT NULL DEFAULT 0,
            xp INT NOT NULL DEFAULT 0,
            level INT NOT NULL DEFAULT 1,
            role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            streak_days INT NOT NULL DEFAULT 0,
            last_login_date DATE DEFAULT NULL,
            avatar_color VARCHAR(7) NOT NULL DEFAULT '#7c3aed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS pixels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            x INT NOT NULL,
            y INT NOT NULL,
            color VARCHAR(7) NOT NULL,
            owner_id INT DEFAULT NULL,
            placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP DEFAULT NULL,
            UNIQUE KEY uq_pixel (x, y),
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS score_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            score INT NOT NULL,
            multiplier DECIMAL(3,1) NOT NULL DEFAULT 1.0,
            currency_earned INT NOT NULL,
            xp_earned INT NOT NULL,
            played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS game_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            used TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            description VARCHAR(255) NOT NULL,
            icon VARCHAR(50) NOT NULL,
            reward INT NOT NULL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS user_achievements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            achievement_id INT NOT NULL,
            earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_achievement (user_id, achievement_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS pixel_placements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS canvas_snapshots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            snapshot LONGTEXT NOT NULL,
            captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS admin_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) DEFAULT NULL,
            target_id INT DEFAULT NULL,
            details TEXT DEFAULT NULL,
            performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    $existing = Database::fetch("SELECT id FROM achievements LIMIT 1");
    if (!$existing) {
        $achievements = get_achievements();
        $stmt = $pdo->prepare("INSERT INTO achievements (slug, name, description, icon, reward) VALUES (?, ?, ?, ?, ?)");
        foreach ($achievements as $a) {
            $stmt->execute([$a['slug'], $a['name'], $a['description'], $a['icon'], $a['reward']]);
        }
    }

    $existing_admin = Database::fetch("SELECT id FROM users WHERE username = 'admin'");
    if (!$existing_admin) {
        $password_hash = password_hash('Admin1234!', PASSWORD_BCRYPT);
        Database::query(
            "INSERT INTO users (username, email, password_hash, balance, xp, level, role, avatar_color) VALUES (?, ?, ?, 100, 0, 1, 'admin', '#ef4444')",
            ['admin', 'admin@pixelflap.local', $password_hash]
        );
    }

    unlink(__FILE__);
    echo "Installation complete!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    run_install();
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Install PixelFlap</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #0a0a0a; color: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
            .container { background: #111111; border: 1px solid #222222; padding: 2rem; border-radius: 8px; max-width: 400px; }
            h1 { color: #7c3aed; margin-top: 0; }
            p { color: #9ca3af; line-height: 1.6; }
            button { background: #7c3aed; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 1rem; width: 100%; }
            button:hover { background: #6d28d9; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Install PixelFlap</h1>
            <p>This will create all database tables, seed achievements, and create the default admin account.</p>
            <p>Admin credentials: <strong>admin</strong> / <strong>Admin1234!</strong></p>
            <form method="post">
                <button type="submit" name="confirm" value="1">Install</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}