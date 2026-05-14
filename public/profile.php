<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$target = isset($_GET['username']) ? trim($_GET['username']) : null;

if ($target) {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT id, username, pxl_balance, total_pxl_earned, total_pxl_spent, login_streak, created_at FROM users WHERE username = ? AND is_banned = 0");
    $stmt->execute([$target]);
    $profile = $stmt->fetch();
} else {
    require_auth();
    $profile = get_current_user_data();
}

if (!$profile) {
    if ($target) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>User Not Found</title></head><body style="font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><h1>404 — User Not Found</h1><p><a href="canvas.php">Go back</a></p></body></html>';
        exit;
    }
}

$csrf_token = $_SESSION['csrf_token'] ?? '';
$is_own = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profile['id'];

$pdo = get_db();

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM pixels WHERE owner_id = ?");
$stmt->execute([$profile['id']]);
$pixels_placed = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM scores WHERE user_id = ?");
$stmt->execute([$profile['id']]);
$games_played = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT MAX(score) as best FROM scores WHERE user_id = ?");
$stmt->execute([$profile['id']]);
$best_score = $stmt->fetch()['best'] ?? 0;

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_achievements WHERE user_id = ?");
$stmt->execute([$profile['id']]);
$ach_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("
    SELECT a.*, ua.earned_at, ua.pxl_claimed
    FROM user_achievements ua
    JOIN achievements a ON a.id = ua.achievement_id
    WHERE ua.user_id = ?
    ORDER BY ua.earned_at DESC
    LIMIT 10
");
$stmt->execute([$profile['id']]);
$achievements = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT s.*, gs.prng_seed, gs.started_at
    FROM scores s
    JOIN game_sessions gs ON gs.id = s.game_session_id
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 10
");
$stmt->execute([$profile['id']]);
$recent_games = $stmt->fetchAll();

$user_sidebar = isset($_SESSION['user_id']) ? get_current_user_data() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= h($csrf_token) ?>" />
    <title><?= h($profile['username']) ?> — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/main.css" />
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">PF</div>
                <div class="brand-text">
                    <span class="brand-name">PixelForge</span>
                    <span class="brand-tagline">Paint the World</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= BASE_URL ?>canvas.php" class="nav-item">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="<?= BASE_URL ?>game.php" class="nav-item">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="<?= BASE_URL ?>leaderboard.php" class="nav-item">
                    <span class="nav-icon">&#9672;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="<?= BASE_URL ?>profile.php" class="nav-item <?= $is_own ? 'active' : '' ?>">
                    <span class="nav-icon">&#9678;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <?php if ($user_sidebar): ?>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?= h((string)$user_sidebar['pxl_balance']) ?> PXL</span>
                </div>
                <div class="user-tag">@<?= h($user_sidebar['username']) ?></div>
            </div>
            <?php endif; ?>
        </aside>

        <main class="main-content">
            <div class="profile-header">
                <div class="profile-avatar"><?= strtoupper(substr($profile['username'], 0, 2)) ?></div>
                <div class="profile-info">
                    <h1 class="profile-username"><?= h($profile['username']) ?></h1>
                    <div class="profile-meta">
                        Joined <?= date('M Y', strtotime($profile['created_at'])) ?>
                        <?php if (($profile['login_streak'] ?? 0) > 0): ?>
                            &middot; <span class="streak-badge">&#9733; <?= $profile['login_streak'] ?>-day streak</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($is_own): ?>
                <div class="profile-actions">
                    <a href="api/auth/logout.php" class="btn btn-secondary btn-sm" data-api-logout>Sign Out</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="profile-stats">
                <div class="stat-card">
                    <span class="stat-card-value mono pxl-text"><?= h((string)($profile['pxl_balance'] ?? 0)) ?></span>
                    <span class="stat-card-label">PXL Balance</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card-value mono"><?= number_format($profile['total_pxl_earned'] ?? 0) ?></span>
                    <span class="stat-card-label">Total Earned</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card-value mono"><?= number_format($profile['total_pxl_spent'] ?? 0) ?></span>
                    <span class="stat-card-label">Total Spent</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card-value mono"><?= number_format($pixels_placed) ?></span>
                    <span class="stat-card-label">Pixels Placed</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card-value mono"><?= number_format($best_score) ?></span>
                    <span class="stat-card-label">Best Score</span>
                </div>
                <div class="stat-card">
                    <span class="stat-card-value mono"><?= $ach_count ?>/20</span>
                    <span class="stat-card-label">Achievements</span>
                </div>
            </div>

            <div class="profile-tabs">
                <button class="tab-btn active" data-tab="achievements">Achievements</button>
                <button class="tab-btn" data-tab="games">Recent Games</button>
            </div>

            <div class="tab-content" id="tab-achievements">
                <?php if ($achievements): ?>
                <div class="achievement-grid">
                    <?php foreach ($achievements as $ach): ?>
                    <div class="achievement-card <?= $ach['pxl_claimed'] ? 'claimed' : 'unclaimed' ?>">
                        <div class="ach-icon">&#9733;</div>
                        <div class="ach-info">
                            <div class="ach-title"><?= h($ach['title']) ?></div>
                            <div class="ach-desc"><?= h($ach['description']) ?></div>
                            <div class="ach-reward pxl-text">+<?= $ach['pxl_reward'] ?> PXL</div>
                        </div>
                        <?php if ($is_own && !$ach['pxl_claimed']): ?>
                        <button class="btn btn-sm btn-primary ach-claim-btn" data-ach-id="<?= $ach['id'] ?>">Claim</button>
                        <?php elseif ($ach['pxl_claimed']): ?>
                        <span class="ach-claimed-badge">Claimed</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>No achievements yet. Play some games to earn some!</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="tab-content" id="tab-games" hidden>
                <?php if ($recent_games): ?>
                <div class="games-list">
                    <?php foreach ($recent_games as $g): ?>
                    <div class="game-row">
                        <div class="game-row-score mono"><?= number_format($g['score']) ?></div>
                        <div class="game-row-info">
                            <span>+<?= $g['pxl_earned'] ?> PXL</span>
                            <span class="text-muted"> &middot; <?= $g['duration_seconds'] ?>s &middot; Tier <?= $g['max_speed_tier'] ?></span>
                        </div>
                        <div class="game-row-date text-muted"><?= date('M j, Y', strtotime($g['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>No games played yet. <a href="<?= BASE_URL ?>game.php" class="link">Play a game!</a></p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script type="module" src="<?= BASE_URL ?>assets/js/ui.js"></script>
    <?php if ($is_own): ?>
    <script type="module">
        import { claimAchievement } from '<?= BASE_URL ?>assets/js/api.js';
        document.querySelectorAll('.ach-claim-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.achId;
                const res = await fetch('<?= BASE_URL ?>api/user/claim-achievement.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ achievement_id: id })
                });
                const data = await res.json();
                if (data.ok) {
                    btn.replaceWith(createSpan('Claimed'));
                    location.reload();
                }
            });
        });

        function createSpan(text) {
            const s = document.createElement('span');
            s.textContent = text;
            s.className = 'ach-claimed-badge';
            return s;
        }

        document.querySelectorAll('[data-api-logout]').forEach(a => {
            a.addEventListener('click', async e => {
                e.preventDefault();
                await fetch(a.href, { method: 'POST', credentials: 'same-origin' });
                window.location.href = '<?= BASE_URL ?>index.php';
            });
        });
    </script>
    <?php endif; ?>
    <script type="module">
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.hidden = true);
                btn.classList.add('active');
                document.getElementById('tab-' + btn.dataset.tab).hidden = false;
            });
        });
    </script>
</body>
</html>