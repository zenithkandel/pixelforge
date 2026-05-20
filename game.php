<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';
require_login();

$game_token = bin2hex(random_bytes(32));
$db = get_db();
try {
    $stmt = $db->prepare('INSERT INTO game_tokens (user_id, token) VALUES (?, ?)');
    $stmt->execute([(int)$_SESSION['user_id'], $game_token]);
} catch (PDOException $e) {
    log_error('DB', 'Failed to generate game token: ' . $e->getMessage(), ['code' => $e->getCode()]);
}

$page_title = 'Flappy Bird';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content" style="display:flex;flex-direction:column;align-items:center;">
    <div class="page-header" style="text-align:center;">
        <h1 style="display:flex;align-items:center;justify-content:center;gap:12px;">
            <span>🐦 PixelFlap</span>
        </h1>
        <p>Tap, click, or press space to flap. Avoid the pipes!</p>
    </div>

    <div id="game-container" class="game-wrap" style="position:relative;">
        <canvas id="game-canvas" width="480" height="640"></canvas>

        <div id="game-hud-score" style="position:absolute;top:16px;left:16px;color:#fff;font-size:28px;font-weight:600;text-shadow:0 2px 8px rgba(0,0,0,0.6);">0</div>
        <div id="game-hud-multiplier" style="position:absolute;top:16px;right:16px;background:var(--gold-mid);color:#000;font-weight:700;font-size:13px;padding:4px 10px;border-radius:var(--radius-pill);display:none;"></div>
        <div id="game-hud-powerup" style="position:absolute;bottom:16px;left:50%;transform:translateX(-50%);font-size:12px;color:#fff;display:none;gap:8px;align-items:center;">
            <span id="powerup-icon"></span>
            <div style="width:100px;height:4px;background:rgba(255,255,255,0.2);border-radius:2px;overflow:hidden;">
                <div id="powerup-bar" style="height:100%;background:var(--purple-bright);width:100%;"></div>
            </div>
        </div>

        <div id="game-overlay" style="display:none;position:absolute;inset:0;background:rgba(0,0,0,0.85);flex-direction:column;align-items:center;justify-content:center;color:#fff;gap:8px;">
            <div style="font-size:14px;color:var(--text-muted);">Game Over</div>
            <div id="go-score" style="font-size:48px;font-weight:700;">0</div>
            <div id="go-multiplier" style="font-size:14px;color:var(--gold-bright);">1× multiplier</div>
            <div id="go-currency" style="font-size:20px;color:var(--gold-bright);">+0 💰</div>
            <div id="go-xp" style="font-size:14px;color:var(--purple-bright);">+0 XP</div>
            <div id="go-coins" style="font-size:14px;">0 coins</div>
            <div id="go-best" style="font-size:13px;color:var(--green);"></div>
            <div style="display:flex;gap:12px;margin-top:16px;">
                <button id="btn-play-again" class="btn-primary">Play Again</button>
                <a href="<?= BASE_URL ?>/canvas.php" class="btn-secondary">Canvas</a>
            </div>
        </div>
    </div>

    <div id="leaderboard-section" style="width:100%;max-width:520px;margin-top:var(--space-xl);">
        <div class="tabs">
            <button class="tab-btn active" data-lb="all">All-Time</button>
            <button class="tab-btn" data-lb="week">This Week</button>
            <button class="tab-btn" data-lb="today">Today</button>
        </div>
        <table id="lb-table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Player</th>
                    <th>Score</th>
                    <th>Multiplier</th>
                    <th>Earned</th>
                </tr>
            </thead>
            <tbody id="lb-body"></tbody>
        </table>
        <div id="lb-your-rank" style="text-align:center;margin-top:12px;color:var(--text-muted);"></div>
    </div>
</div>

<script>
var GAME_TOKEN = '<?= $game_token ?>';
var CSRF_TOKEN = '<?= csrf_token() ?>';
var BASE_URL = '<?= BASE_URL ?>';
var CURRENT_USER = { id: <?= (int)$_SESSION['user_id'] ?>, username: '<?= htmlspecialchars($_SESSION['username']) ?>' };
</script>
<script src="<?= BASE_URL ?>/assets/js/game.js"></script>
<script src="<?= BASE_URL ?>/assets/js/achievements.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
