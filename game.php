<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

require_login();
$user = get_current_user();
if (!is_array($user) || !isset($user['id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

$existing_token = Database::fetch("SELECT token FROM game_tokens WHERE user_id = ? AND used = 0 AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)", [$user['id']]);
if ($existing_token) {
    Database::query("DELETE FROM game_tokens WHERE user_id = ?", [$user['id']]);
}

$game_token = bin2hex(random_bytes(32));
Database::query("INSERT INTO game_tokens (user_id, token) VALUES (?, ?)", [$user['id'], $game_token]);
$_SESSION['game_token'] = $game_token;

$leaderboard = Database::fetchAll("
    SELECT u.username, u.avatar_color, u.level, s.score, s.multiplier, s.currency_earned, s.played_at
    FROM score_log s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.score DESC
    LIMIT 10
");
$user_rank = Database::fetch("
    SELECT COUNT(*) + 1 as rank FROM score_log WHERE score > (
        SELECT COALESCE(MAX(score), 0) FROM score_log WHERE user_id = ?
    )
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play Game - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/game.css">
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="game-page">
        <div class="game-container">
            <canvas id="game-canvas" width="480" height="640"></canvas>

            <div id="hud" class="hud">
                <div id="score">0</div>
                <div id="multiplier" class="multiplier-badge hidden">×1.0</div>
                <button id="pause-btn" class="pause-btn">⏸</button>
            </div>

            <div id="powerup-bar" class="powerup-bar hidden">
                <div id="powerup-icon"></div>
                <div id="powerup-timer"></div>
            </div>

            <div id="game-over" class="game-over overlay hidden">
                <h2>Game Over</h2>
                <div class="final-score">
                    <span class="label">Score</span>
                    <span id="final-score-value">0</span>
                </div>
                <div class="multiplier-result">
                    <span class="label">Multiplier</span>
                    <span id="multiplier-result-value">×1.0</span>
                </div>
                <div class="currency-earned">
                    <span class="label">Currency</span>
                    <span id="currency-value">+0</span>
                </div>
                <div class="xp-earned">
                    <span class="label">XP</span>
                    <span id="xp-value">+0</span>
                </div>
                <div class="personal-best hidden" id="personal-best">
                    🎉 New Personal Best!
                </div>
                <button id="play-again" class="btn primary">Play Again</button>
                <a href="<?php echo APP_URL; ?>/canvas.php" class="btn">Go to Canvas</a>
            </div>

            <div id="start-screen" class="start-screen overlay">
                <h1>Flappy Bird</h1>
                <p>Click or press Space to flap</p>
                <button id="start-btn" class="btn primary">Start Game</button>
            </div>
        </div>

        <div class="leaderboard-section">
            <div class="leaderboard-tabs">
                <button class="tab active" data-tab="all">All-time</button>
                <button class="tab" data-tab="week">This Week</button>
                <button class="tab" data-tab="today">Today</button>
            </div>
            <table class="leaderboard-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Score</th>
                        <th>Multiplier</th>
                        <th>Currency</th>
                    </tr>
                </thead>
                <tbody id="leaderboard-body">
                    <?php foreach ($leaderboard as $i => $row): ?>
                    <tr class="<?php echo $row['username'] === $user['username'] ? 'highlight' : ''; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <div class="player-cell">
                                <span class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>">
                                    <?php echo strtoupper($row['username'][0]); ?>
                                </span>
                                <span class="player-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                            </div>
                        </td>
                        <td><?php echo $row['score']; ?></td>
                        <td>×<?php echo $row['multiplier']; ?></td>
                        <td>+<?php echo $row['currency_earned']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($user_rank['rank'] > 10): ?>
            <div class="your-rank">Your rank: #<?php echo $user_rank['rank']; ?></div>
            <?php endif; ?>
        </div>
    </main>

    <input type="hidden" id="game-token" value="<?php echo $game_token; ?>">
    <input type="hidden" id="csrf-token" value="<?php echo csrf_token(); ?>">
    <input type="hidden" id="user-balance" value="<?php echo $user['balance']; ?>">

    <script src="<?php echo APP_URL; ?>/assets/js/game.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/achievements.js"></script>
</body>
</html>