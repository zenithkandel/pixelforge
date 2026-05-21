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

<div class="game-view-container" style="display:flex; flex-direction:column; gap:20px; height:100%;">
    <div class="game-window" style="flex:1; min-height:600px; position:relative; background:#111; border:4px solid var(--bg-panel); box-shadow:0 0 40px rgba(0,0,0,0.5);">
        <div id="game-container" style="width:100%; height:100%; position:relative; overflow:hidden;">
            <canvas id="game-canvas" width="480" height="640" style="width:100%; height:100%; object-fit:contain; display:block; margin:0 auto;"></canvas>

            <!-- HUD Overlay -->
            <div class="game-hud" style="position:absolute; inset:0; pointer-events:none; padding:24px;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div id="game-hud-score" style="font-family:var(--font-game); font-size:48px; font-weight:900; color:white; text-shadow:3px 3px 0 #000, 0 0 20px rgba(0,0,0,0.5);">0</div>
                    <div id="game-hud-multiplier" style="background:var(--gold); color:black; font-weight:900; font-size:14px; padding:6px 14px; border-radius:4px; display:none; box-shadow:3px 3px 0 #000;">1.0×</div>
                </div>

                <div id="game-hud-powerup" style="position:absolute; bottom:30px; left:50%; transform:translateX(-50%); width:200px; background:rgba(0,0,0,0.7); padding:10px; border-radius:8px; display:none; flex-direction:column; gap:5px; border:2px solid var(--purple);">
                    <div style="display:flex; justify-content:space-between; font-size:10px; font-weight:bold; color:var(--purple-bright);">
                        <span>POWER UP ACTIVE</span>
                        <span id="powerup-icon"></span>
                    </div>
                    <div style="width:100%; height:6px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden;">
                        <div id="powerup-bar" style="height:100%; background:var(--purple-bright); width:100%;"></div>
                    </div>
                </div>
            </div>

            <!-- Game Over Screen -->
            <div id="game-overlay" style="display:none; position:absolute; inset:0; background:rgba(0,0,0,0.9); backdrop-filter:blur(8px); flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:40px; border:4px solid var(--purple); margin:20px; border-radius:12px;">
                <div style="font-size:12px; font-weight:bold; color:var(--text-muted); text-transform:uppercase; letter-spacing:3px;">Simulation Terminated</div>
                <div id="go-score" style="font-size:80px; font-weight:900; color:white; line-height:1; margin:10px 0;">0</div>
                <div id="go-best" style="font-size:14px; color:var(--green); font-weight:bold; margin-bottom:20px;"></div>
                
                <div class="go-stats" style="display:grid; grid-template-columns:1fr 1fr; gap:15px; width:100%; max-width:300px; margin-bottom:30px;">
                    <div class="go-stat-box" style="background:var(--bg-input); padding:15px; border-radius:8px; border:1px solid var(--border-dim);">
                        <div style="font-size:11px; color:var(--text-muted);">EARNED</div>
                        <div id="go-currency" style="font-size:20px; font-weight:bold; color:var(--gold);">+0</div>
                    </div>
                    <div class="go-stat-box" style="background:var(--bg-input); padding:15px; border-radius:8px; border:1px solid var(--border-dim);">
                        <div style="font-size:11px; color:var(--text-muted);">XP Gained</div>
                        <div id="go-xp" style="font-size:20px; font-weight:bold; color:var(--cyan);">+0</div>
                    </div>
                </div>
                
                <div style="display:flex; gap:15px;">
                    <button id="btn-play-again" class="btn-pixel" style="min-width:160px;">REDEPLOY</button>
                    <a href="<?= BASE_URL ?>/leaderboard.php" class="btn-pixel" style="background:var(--bg-card); min-width:160px;">RANKINGS</a>
                </div>
                
                <div id="go-multiplier" style="margin-top:20px; font-size:12px; color:var(--text-muted);"></div>
                <div id="go-coins" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- Leaderboard Preview -->
    <div id="leaderboard-section" class="widget" style="background:var(--bg-panel); border-radius:var(--radius); padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0; font-size:18px;"><i class="fas fa-trophy" style="color:var(--gold); margin-right:10px;"></i>Top Pilots</h3>
            <div class="tabs" style="display:flex; gap:5px; background:var(--bg-input); padding:4px; border-radius:6px;">
                <button class="tab-btn active" data-lb="all" style="background:none; border:none; color:var(--text-muted); padding:6px 12px; border-radius:4px; font-size:12px; cursor:pointer;">All-Time</button>
                <button class="tab-btn" data-lb="week" style="background:none; border:none; color:var(--text-muted); padding:6px 12px; border-radius:4px; font-size:12px; cursor:pointer;">Weekly</button>
                <button class="tab-btn" data-lb="today" style="background:none; border:none; color:var(--text-muted); padding:6px 12px; border-radius:4px; font-size:12px; cursor:pointer;">Today</button>
            </div>
        </div>
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="text-align:left; color:var(--text-muted); border-bottom:1px solid var(--border-dim);">
                    <th style="padding:10px;">#</th>
                    <th>PLAYER</th>
                    <th style="text-align:right;">SCORE</th>
                    <th style="text-align:right;">EARNED</th>
                </tr>
            </thead>
            <tbody id="lb-body"></tbody>
        </table>
        <div id="lb-your-rank" style="margin-top:15px; padding-top:15px; border-top:1px solid var(--border-dim); text-align:center; color:var(--purple-bright); font-weight:bold; font-size:13px;"></div>
    </div>
</div>


<script nonce="<?= $GLOBALS['csp_nonce'] ?>">
var GAME_TOKEN = '<?= $game_token ?>';
var CSRF_TOKEN = '<?= csrf_token() ?>';
var BASE_URL = '<?= BASE_URL ?>';
var CURRENT_USER = { id: <?= (int)$_SESSION['user_id'] ?>, username: '<?= htmlspecialchars($_SESSION['username']) ?>' };
</script>
<script src="<?= BASE_URL ?>/assets/js/game.js"></script>
<script src="<?= BASE_URL ?>/assets/js/achievements.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
