<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

$username = $_GET['user'] ?? '';
$profile_user = Database::fetch("SELECT * FROM users WHERE username = ?", [$username]);

if (!$profile_user) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>404 - User not found</h1><p><a href="' . APP_URL . '/index.php">Go Home</a></p></body></html>';
    exit;
}

$total_score = Database::fetch("SELECT MAX(score) as best FROM score_log WHERE user_id = ?", [$profile_user['id']]);
$pixels_owned = Database::fetch("SELECT COUNT(*) as cnt FROM pixels WHERE owner_id = ?", [$profile_user['id']]);
$achievements = get_user_achievements($profile_user['id']);

$recent_scores = Database::fetchAll("
    SELECT score, multiplier, currency_earned, played_at FROM score_log
    WHERE user_id = ? ORDER BY played_at DESC LIMIT 10
", [$profile_user['id']]);

$recent_pixels = Database::fetchAll("
    SELECT x, y, color, placed_at FROM pixels
    WHERE owner_id = ? ORDER BY placed_at DESC LIMIT 10
", [$profile_user['id']]);

$user = get_current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($profile_user['username']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/canvas.css">
    <style>
        .profile-header { text-align: center; padding: 2rem; }
        .profile-avatar {
            width: 100px; height: 100px; border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 3rem; color: white; font-weight: bold;
            border: 4px solid <?php echo $profile_user['level'] >= 20 ? '#f59e0b' : '#222'; ?>;
        }
        .profile-username { font-size: 2rem; margin: 1rem 0 0.5rem; }
        .level-badge { background: #7c3aed; padding: 4px 12px; border-radius: 12px; font-size: 0.9rem; }
        .stats-row { display: flex; justify-content: center; gap: 2rem; margin: 1.5rem 0; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 1.5rem; font-weight: bold; color: #f59e0b; }
        .stat-label { color: #9ca3af; font-size: 0.85rem; }
        .mini-canvas-container { margin: 2rem auto; max-width: 200px; }
        .achievement-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; margin: 1rem 0; }
        .achievement-slot {
            width: 48px; height: 48px; border-radius: 8px; background: #222;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
            position: relative; cursor: pointer;
        }
        .achievement-slot.earned { background: #333; }
        .achievement-slot.locked { opacity: 0.4; }
        .achievement-slot:hover .tooltip { display: block; }
        .tooltip {
            display: none; position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #111; border: 1px solid #333; padding: 0.5rem; border-radius: 4px;
            font-size: 0.75rem; white-space: nowrap; z-index: 10;
        }
        .section { margin: 2rem 0; padding: 1rem; background: #111; border-radius: 8px; }
        .section h2 { margin-top: 0; color: #7c3aed; }
        .recent-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .recent-item { display: flex; align-items: center; gap: 1rem; padding: 0.5rem; background: #1a1a1a; border-radius: 4px; }
        .color-swatch { width: 20px; height: 20px; border-radius: 3px; border: 1px solid #333; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="profile-page">
        <div class="profile-header">
            <div class="profile-avatar" style="background: <?php echo htmlspecialchars($profile_user['avatar_color']); ?>">
                <?php echo strtoupper($profile_user['username'][0]); ?>
            </div>
            <h1 class="profile-username">
                <?php echo htmlspecialchars($profile_user['username']); ?>
                <?php if ($profile_user['level'] >= 10): ?>
                <span class="gold-badge" style="color: #f59e0b;">★</span>
                <?php endif; ?>
            </h1>
            <span class="level-badge">Level <?php echo $profile_user['level']; ?></span>

            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-value"><?php echo $total_score['best'] ?? 0; ?></div>
                    <div class="stat-label">Best Score</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $pixels_owned['cnt']; ?></div>
                    <div class="stat-label">Pixels</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($profile_user['xp']); ?></div>
                    <div class="stat-label">XP</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo date('M j, Y', strtotime($profile_user['created_at'])); ?></div>
                    <div class="stat-label">Joined</div>
                </div>
            </div>
        </div>

        <div class="mini-canvas-container">
            <canvas id="mini-canvas" width="200" height="200"></canvas>
        </div>

        <div class="section">
            <h2>Achievements</h2>
            <div class="achievement-grid">
                <?php foreach ($achievements as $a): ?>
                <div class="achievement-slot <?php echo $a['earned_at'] ? 'earned' : 'locked'; ?>" title="<?php echo htmlspecialchars($a['name']); ?>">
                    <?php echo $a['icon']; ?>
                    <div class="tooltip">
                        <strong><?php echo htmlspecialchars($a['name']); ?></strong><br>
                        <?php echo htmlspecialchars($a['description']); ?><br>
                        <?php if ($a['earned_at']): ?>
                        Earned: <?php echo date('M j, Y', strtotime($a['earned_at'])); ?>
                        <?php else: ?>
                        Locked
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($recent_scores)): ?>
        <div class="section">
            <h2>Recent Games</h2>
            <div class="recent-list">
                <?php foreach ($recent_scores as $s): ?>
                <div class="recent-item">
                    <span>Score: <strong><?php echo $s['score']; ?></strong></span>
                    <span>×<?php echo $s['multiplier']; ?></span>
                    <span style="color: #f59e0b;">+<?php echo $s['currency_earned']; ?></span>
                    <span style="color: #9ca3af; font-size: 0.85rem;"><?php echo date('M j, g:i A', strtotime($s['played_at'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($recent_pixels)): ?>
        <div class="section">
            <h2>Recent Pixels</h2>
            <div class="recent-list">
                <?php foreach ($recent_pixels as $p): ?>
                <div class="recent-item">
                    <div class="color-swatch" style="background: <?php echo htmlspecialchars($p['color']); ?>;"></div>
                    <span>(<?php echo $p['x']; ?>, <?php echo $p['y']; ?>)</span>
                    <span style="color: #9ca3af; font-size: 0.85rem;"><?php echo date('M j, g:i A', strtotime($p['placed_at'])); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
        const PROFILE_USER_ID = <?php echo $profile_user['id']; ?>;
        const AVATAR_COLOR = '<?php echo $profile_user['avatar_color']; ?>';

        const miniCanvas = document.getElementById('mini-canvas');
        const miniCtx = miniCanvas.getContext('2d');
        miniCtx.fillStyle = '#1a1a1a';
        miniCtx.fillRect(0, 0, 200, 200);

        fetch(APP_URL + '/api/get_canvas.php')
            .then(r => r.json())
            .then(data => {
                data.pixels.forEach(p => {
                    if (p.owner_id === PROFILE_USER_ID) {
                        miniCtx.fillStyle = AVATAR_COLOR;
                        miniCtx.fillRect(p.x * 2, p.y * 2, 2, 2);
                    }
                });
            });
    </script>
</body>
</html>