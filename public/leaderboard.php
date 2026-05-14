<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$period = $_GET['period'] ?? 'all';
if (!in_array($period, ['daily', 'weekly', 'all'])) $period = 'all';

$user = null;
if (isset($_SESSION['user_id'])) {
    $user = get_current_user_data();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Leaderboard — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/main.css" />
</head>
<body>
    <div class="app-shell">
        <?php if ($user): ?>
        <aside class="sidebar">
            <div class="sidebar-brand">
                <div class="brand-logo">PF</div>
                <div class="brand-text">
                    <span class="brand-name">PixelForge</span>
                    <span class="brand-tagline">Paint the World</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="/canvas.php" class="nav-item">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="/game.php" class="nav-item">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="/leaderboard.php" class="nav-item active">
                    <span class="nav-icon">&#9672;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="/profile.php" class="nav-item">
                    <span class="nav-icon">&#9678;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?= h($user['pxl_balance']) ?> PXL</span>
                </div>
                <div class="user-tag">@<?= h($user['username']) ?></div>
            </div>
        </aside>
        <?php endif; ?>

        <main class="main-content <?= $user ? '' : 'no-sidebar' ?>">
            <div class="page-header">
                <h1 class="page-title">Leaderboard</h1>
                <div class="period-tabs">
                    <a href="?period=daily" class="tab-link <?= $period === 'daily' ? 'active' : '' ?>">Today</a>
                    <a href="?period=weekly" class="tab-link <?= $period === 'weekly' ? 'active' : '' ?>">This Week</a>
                    <a href="?period=all" class="tab-link <?= $period === 'all' ? 'active' : '' ?>">All Time</a>
                </div>
            </div>

            <div class="leaderboard-container" id="leaderboard-container">
                <div class="lb-loading">
                    <div class="loading-spinner"></div>
                    Loading scores...
                </div>
            </div>
        </main>
    </div>

    <script type="module">
        const period = new URLSearchParams(window.location.search).get('period') || 'all';
        const container = document.getElementById('leaderboard-container');

        async function loadLeaderboard() {
            try {
                const res = await fetch(`/api/leaderboard.php?period=${period}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) throw new Error(data.message || 'Failed to load');

                const scores = data.data;
                if (scores.length === 0) {
                    container.innerHTML = '<div class="empty-state"><p>No scores yet. Be the first!</p></div>';
                    return;
                }

                let html = '<div class="leaderboard-table">';
                html += '<div class="lb-header"><span class="lb-rank">#</span><span class="lb-player">Player</span><span class="lb-score">Score</span><span class="lb-pxl pxl-text">PXL</span><span class="lb-time">When</span></div>';

                scores.forEach((row, i) => {
                    const rank = i + 1;
                    const rankClass = rank <= 3 ? `rank-${rank}` : '';
                    const isMe = row.user_id == <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>;
                    html += `<div class="lb-row ${rankClass} ${isMe ? 'lb-me' : ''}">
                        <span class="lb-rank">${rank <= 3 ? ['&#9813;','&#9812;','&#9811;'][rank-1] : rank}</span>
                        <a class="lb-player" href="/profile.php?username=${encodeURIComponent(row.username)}">${escapeHtml(row.username)}</a>
                        <span class="lb-score mono">${Number(row.score).toLocaleString()}</span>
                        <span class="lb-pxl mono">+${row.pxl_earned}</span>
                        <span class="lb-time">${row.when || ''}</span>
                    </div>`;
                });
                html += '</div>';
                container.innerHTML = html;
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><p>Failed to load leaderboard.</p></div>';
            }
        }

        function escapeHtml(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        loadLeaderboard();
    </script>
</body>
</html>