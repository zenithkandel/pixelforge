<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';

$db = get_db();

$queries = [
    "CREATE TABLE IF NOT EXISTS users (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        username        VARCHAR(30)  NOT NULL UNIQUE,
        email           VARCHAR(100) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        balance         INT          NOT NULL DEFAULT 0,
        xp              INT          NOT NULL DEFAULT 0,
        level           INT          NOT NULL DEFAULT 1,
        role            ENUM('user','admin') NOT NULL DEFAULT 'user',
        streak_days     INT          NOT NULL DEFAULT 0,
        last_login_date DATE         DEFAULT NULL,
        avatar_color    VARCHAR(7)   NOT NULL DEFAULT '#7c3aed',
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS pixels (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        x           INT         NOT NULL,
        y           INT         NOT NULL,
        color       VARCHAR(7)  NOT NULL,
        owner_id    INT         DEFAULT NULL,
        placed_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        expires_at  TIMESTAMP   DEFAULT NULL,
        UNIQUE KEY uq_pixel (x, y),
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS score_log (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        user_id         INT            NOT NULL,
        score           INT            NOT NULL,
        multiplier      DECIMAL(3,1)   NOT NULL DEFAULT 1.0,
        currency_earned INT            NOT NULL,
        xp_earned       INT            NOT NULL,
        played_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS game_tokens (
        id         INT         AUTO_INCREMENT PRIMARY KEY,
        user_id    INT         NOT NULL,
        token      VARCHAR(64) NOT NULL UNIQUE,
        used       TINYINT     NOT NULL DEFAULT 0,
        created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS achievements (
        id          INT          AUTO_INCREMENT PRIMARY KEY,
        slug        VARCHAR(50)  NOT NULL UNIQUE,
        name        VARCHAR(100) NOT NULL,
        description VARCHAR(255) NOT NULL,
        icon        VARCHAR(50)  NOT NULL,
        reward      INT          NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS user_achievements (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        user_id        INT NOT NULL,
        achievement_id INT NOT NULL,
        earned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_achievement (user_id, achievement_id),
        FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
        FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        ip_address   VARCHAR(45) NOT NULL,
        attempted_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS pixel_placements (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        user_id   INT NOT NULL,
        placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS admin_log (
        id           INT          AUTO_INCREMENT PRIMARY KEY,
        admin_id     INT          NOT NULL,
        action       VARCHAR(100) NOT NULL,
        target_type  VARCHAR(50)  DEFAULT NULL,
        target_id    INT          DEFAULT NULL,
        details      TEXT         DEFAULT NULL,
        performed_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$errors = [];

foreach ($queries as $sql) {
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}

$achievement_seeds = [
    ['first_flight',   'First Flight',       'Complete your first game',           '🐦', 10],
    ['score_10',       'Getting Somewhere',  'Score 10 in a single run',           '🎯', 20],
    ['score_50',       'Flap Master',        'Score 50 in a single run',           '⭐', 75],
    ['score_100',      'Sky Ruler',          'Score 100 in a single run',          '👑', 200],
    ['first_pixel',    'Mark Your Territory','Place your first pixel',             '🎨', 15],
    ['pixel_5',        'Growing Empire',     'Own 5 pixels at once',               '🏘️', 30],
    ['pixel_25',       'Canvas Veteran',     'Own 25 pixels at once',              '🖼️', 100],
    ['pixel_100',      'Canvas Legend',      'Own 100 pixels at once',             '🏆', 500],
    ['streak_3',       'On a Roll',          'Log in 3 days in a row',             '🔥', 50],
    ['streak_7',       'Dedicated',          'Log in 7 days in a row',             '💪', 150],
    ['streak_30',      'Obsessed',           'Log in 30 days in a row',            '🌟', 1000],
    ['level_5',        'Rising Star',        'Reach level 5',                      '📈', 50],
    ['level_10',       'Veteran',            'Reach level 10',                     '🎖️', 150],
    ['level_20',       'Elite',              'Reach level 20',                     '💎', 500],
    ['multiplier_3x',  'Combo King',         'Hit 3× multiplier in one run',       '🔥', 100],
    ['broke_the_bank', 'Broke the Bank',     'Spend 500 currency on pixels total', '💸', 200],
];

try {
    $stmt = $db->prepare('INSERT IGNORE INTO achievements (slug, name, description, icon, reward) VALUES (?, ?, ?, ?, ?)');
    foreach ($achievement_seeds as $seed) {
        $stmt->execute($seed);
    }
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

try {
    $admin_pass = password_hash(ADMIN_DEFAULT_PASS, PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT IGNORE INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute(['admin', 'admin@pixelflap.local', $admin_pass, 'admin']);
} catch (PDOException $e) {
    $errors[] = $e->getMessage();
}

if (empty($errors)) {
    log_info('SYSTEM', 'Installation completed successfully');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelFlap — Installation</title>
    <style>
        :root { --bg: #080810; --surface: #0f0f1a; --purple: #7c3aed; --gold: #fbbf24; --text: #f0f0ff; --muted: #50506a; --green: #22c55e; --red: #ef4444; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .card { background: var(--surface); border: 1px solid rgba(255,255,255,0.1); border-radius: 18px; padding: 40px; max-width: 540px; width: 100%; text-align: center; }
        h1 { color: var(--purple); margin-bottom: 10px; }
        .status { display: inline-block; padding: 6px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin-bottom: 20px; }
        .status.success { background: rgba(34,197,94,0.15); color: var(--green); }
        .status.fail { background: rgba(239,68,68,0.15); color: var(--red); }
        .steps { text-align: left; background: rgba(255,255,255,0.03); border-radius: 12px; padding: 20px; margin: 20px 0; }
        .steps h3 { margin-bottom: 12px; color: var(--gold); }
        .steps ol { padding-left: 20px; line-height: 2; }
        .error-list { color: var(--red); text-align: left; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>PixelFlap</h1>
        <?php if (empty($errors)): ?>
            <div class="status success">Installation Complete</div>
            <p>Database tables created and default admin user added.</p>
            <div class="steps">
                <h3>Next Steps</h3>
                <ol>
                    <li>Delete <code>/install.php</code> — this file has already self-deleted.</li>
                    <li>Log in as <strong>admin</strong> with password <strong><?= ADMIN_DEFAULT_PASS ?></strong></li>
                    <li>Change the admin password immediately.</li>
                    <li>Set <code>logs/</code> to writable by the web server.</li>
                </ol>
            </div>
        <?php else: ?>
            <div class="status fail">Installation Failed</div>
            <div class="error-list">
                <?php foreach ($errors as $err): ?>
                    <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
if (empty($errors)) {
    unlink(__FILE__);
}
