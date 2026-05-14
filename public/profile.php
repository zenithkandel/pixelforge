<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_auth();
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PixelForge</title>
    <meta name="csrf-token" content="<?php echo h($csrf_token); ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .profile-header {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
            align-items: center;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--accent);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
            color: white;
            font-family: 'JetBrains Mono', monospace;
        }
        .profile-info h1 {
            font-size: 28px;
            margin-bottom: 4px;
        }
        .profile-info .join-date {
            color: var(--text-secondary);
            font-size: 14px;
        }
        .streak-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--color-warning);
            color: #000;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 12px;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .profile-stat {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 20px;
            text-align: center;
        }
        .profile-stat-value {
            font-size: 24px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }
        .profile-stat-value.pxl { color: var(--color-pxl); }
        .profile-stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 32px;
        }
        .achievement-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            padding: 16px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .achievement-card.earned {
            border-color: var(--accent);
            background: var(--accent-light);
        }
        .achievement-card.locked {
            opacity: 0.5;
            filter: grayscale(1);
        }
        .achievement-icon {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .achievement-info {
            flex: 1;
        }
        .achievement-title {
            font-size: 14px;
            font-weight: 600;
        }
        .achievement-desc {
            font-size: 12px;
            color: var(--text-secondary);
        }
        .achievement-reward {
            font-size: 12px;
            color: var(--color-pxl);
            font-weight: 500;
        }
        .claim-btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        .recent-pixels {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
        }
        .pixel-preview {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        .transactions-list {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
        }
        .transaction-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-amount {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
        }
        .transaction-amount.positive { color: var(--color-success); }
        .transaction-amount.negative { color: var(--color-error); }
    </style>
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
                <a href="/canvas.php" class="nav-item">
                    <span class="nav-icon">&#9634;</span>
                    <span class="nav-label">The Forge</span>
                </a>
                <a href="/game.php" class="nav-item">
                    <span class="nav-icon">&#9654;</span>
                    <span class="nav-label">Pixel Dash</span>
                </a>
                <a href="/leaderboard.php" class="nav-item">
                    <span class="nav-icon">&#9670;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="/profile.php" class="nav-item active">
                    <span class="nav-icon">&#9673;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono" id="sidebarBalance"><?php echo h($user['pxl_balance']); ?></span>
                </div>
                <div class="user-tag">@<?php echo h($user['username']); ?></div>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-area">
                <div class="profile-header">
                    <div class="profile-avatar" id="avatar"><?php echo strtoupper(substr($user['username'], 0, 2)); ?></div>
                    <div class="profile-info">
                        <h1>
                            <?php echo h($user['username']); ?>
                            <?php if ($user['login_streak'] >= 3): ?>
                                <span class="streak-badge">&#128293; <?php echo $user['login_streak']; ?> day streak</span>
                            <?php endif; ?>
                        </h1>
                        <div class="join-date">Joined <?php echo date('F j, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="profile-stat">
                        <div class="profile-stat-value pxl" id="statPxl"><?php echo h($user['pxl_balance']); ?></div>
                        <div class="profile-stat-label">PXL Balance</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-value" id="statEarned"><?php echo h($user['total_pxl_earned']); ?></div>
                        <div class="profile-stat-label">Total Earned</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-value" id="statPixels">0</div>
                        <div class="profile-stat-label">Pixels Painted</div>
                    </div>
                    <div class="profile-stat">
                        <div class="profile-stat-value" id="statScore">0</div>
                        <div class="profile-stat-label">Best Score</div>
                    </div>
                </div>

                <h2 class="section-title">Achievements</h2>
                <div class="achievements-grid" id="achievementsGrid">
                    <div style="color: var(--text-secondary);">Loading...</div>
                </div>

                <h2 class="section-title">Recent Pixels</h2>
                <div class="recent-pixels" id="recentPixels">
                    <div style="color: var(--text-secondary);">Loading...</div>
                </div>

                <h2 class="section-title">Transaction History</h2>
                <div class="transactions-list" id="transactionsList">
                    <div style="padding: 16px; color: var(--text-secondary);">Loading...</div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function loadProfile() {
            try {
                const response = await fetch('/api/user/me.php', {
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                const result = await response.json();

                if (!result.ok) throw new Error(result.message);

                const data = result.data;

                document.getElementById('statPxl').textContent = data.pxl_balance;
                document.getElementById('statEarned').textContent = data.total_pxl_earned;
                document.getElementById('statPixels').textContent = data.pixels_painted;
                document.getElementById('statScore').textContent = data.best_score.toLocaleString();
                document.getElementById('sidebarBalance').textContent = data.pxl_balance;

                renderAchievements(data.achievements);
                renderRecentPixels(data.recent_pixels);
                renderTransactions(data.transactions);

            } catch (err) {
                console.error('Failed to load profile:', err);
            }
        }

        function renderAchievements(achievements) {
            const grid = document.getElementById('achievementsGrid');

            if (!achievements || achievements.length === 0) {
                grid.innerHTML = '<div style="color: var(--text-secondary);">No achievements yet</div>';
                return;
            }

            grid.innerHTML = achievements.map(a => `
                <div class="achievement-card ${a.earned ? 'earned' : 'locked'}">
                    <div class="achievement-icon">${a.earned ? '&#127942;' : '&#128274;'}</div>
                    <div class="achievement-info">
                        <div class="achievement-title">${escapeHtml(a.title)}</div>
                        <div class="achievement-desc">${escapeHtml(a.description)}</div>
                        <div class="achievement-reward">+${a.pxl_reward} PXL</div>
                    </div>
                    ${a.earned && !a.pxl_claimed ? `<button class="btn btn-sm btn-primary claim-btn" data-key="${a.key_name}">Claim</button>` : ''}
                </div>
            `).join('');

            grid.querySelectorAll('.claim-btn').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const key = btn.dataset.key;
                    try {
                        const res = await fetch('/api/user/claim-achievement.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({ achievement_key: key, csrf_token: csrfToken })
                        });
                        const result = await res.json();
                        if (result.ok) {
                            document.getElementById('statPxl').textContent = result.data.new_balance;
                            document.getElementById('sidebarBalance').textContent = result.data.new_balance;
                            btn.remove();
                            showToast('Achievement claimed! +' + result.data.pxl_awarded + ' PXL', 'success');
                        } else {
                            showToast(result.message, 'error');
                        }
                    } catch (e) {
                        showToast('Failed to claim', 'error');
                    }
                });
            });
        }

        function renderRecentPixels(pixels) {
            const container = document.getElementById('recentPixels');

            if (!pixels || pixels.length === 0) {
                container.innerHTML = '<div style="color: var(--text-secondary);">No pixels painted yet</div>';
                return;
            }

            container.innerHTML = pixels.map(p =>
                `<div class="pixel-preview" style="background: ${p.color};" title="(${p.x}, ${p.y})"></div>`
            ).join('');
        }

        function renderTransactions(transactions) {
            const list = document.getElementById('transactionsList');

            if (!transactions || transactions.length === 0) {
                list.innerHTML = '<div style="padding: 16px; color: var(--text-secondary);">No transactions yet</div>';
                return;
            }

            list.innerHTML = transactions.map(t => `
                <div class="transaction-item">
                    <div>
                        <div style="font-weight: 500;">${t.type.replace('_', ' ')}</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">${new Date(t.created_at).toLocaleDateString()}</div>
                    </div>
                    <div class="transaction-amount ${t.amount >= 0 ? 'positive' : 'negative'}">
                        ${t.amount >= 0 ? '+' : ''}${t.amount} PXL
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        loadProfile();
    </script>
</body>
</html>