<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$user = get_current_user();
$csrf_token = $_SESSION['csrf_token'] ?? '';
$is_logged_in = is_authenticated();
$user_balance = $user ? $user['pxl_balance'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - PixelForge</title>
    <meta name="csrf-token" content="<?php echo h($csrf_token); ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .leaderboard-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .leaderboard-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }
        .leaderboard-tabs button {
            padding: 10px 24px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
        }
        .leaderboard-tabs button.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        .leaderboard-tabs button:hover:not(.active) {
            background: var(--border-color);
        }
        .leaderboard-table {
            width: 100%;
            background: var(--bg-secondary);
            border-radius: var(--border-radius-md);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .leaderboard-table th {
            text-align: left;
            padding: 16px;
            background: var(--bg-primary);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .leaderboard-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }
        .leaderboard-table tr:last-child td {
            border-bottom: none;
        }
        .leaderboard-table tr.gold { background: linear-gradient(90deg, rgba(255,215,0,0.1), transparent); }
        .leaderboard-table tr.silver { background: linear-gradient(90deg, rgba(192,192,192,0.1), transparent); }
        .leaderboard-table tr.bronze { background: linear-gradient(90deg, rgba(205,127,50,0.1), transparent); }
        .leaderboard-table tr.user-row {
            background: var(--accent-light);
            border-left: 3px solid var(--accent);
        }
        .rank-cell {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            font-size: 16px;
        }
        .rank-1 { color: #FFD700; }
        .rank-2 { color: #C0C0C0; }
        .rank-3 { color: #CD7F32; }
        .username-cell {
            font-weight: 500;
        }
        .score-cell {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--text-primary);
        }
        .pxl-cell {
            font-family: 'JetBrains Mono', monospace;
            color: var(--color-pxl);
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
        }
        .pagination button {
            padding: 8px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
        }
        .pagination button:hover:not(:disabled) {
            background: var(--border-color);
        }
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .your-rank {
            background: var(--accent-light);
            padding: 16px;
            border-radius: var(--border-radius-md);
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .your-rank-label {
            font-weight: 500;
            color: var(--text-secondary);
        }
        .your-rank-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 20px;
            font-weight: 700;
            color: var(--accent);
        }
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
                <a href="/leaderboard.php" class="nav-item active">
                    <span class="nav-icon">&#9670;</span>
                    <span class="nav-label">Leaderboard</span>
                </a>
                <a href="/profile.php" class="nav-item">
                    <span class="nav-icon">&#9673;</span>
                    <span class="nav-label">Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="balance-display">
                    <span class="balance-icon">&#9670;</span>
                    <span class="balance-amount mono"><?php echo h($user_balance); ?> PXL</span>
                </div>
                <div class="user-tag">@<?php echo h($user ? $user['username'] : 'guest'); ?></div>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-area">
                <div class="leaderboard-container">
                    <h1 class="page-title" style="margin-bottom: 24px;">Leaderboard</h1>

                    <div id="yourRankContainer" style="display: none;">
                        <div class="your-rank">
                            <span class="your-rank-label">Your Position</span>
                            <span class="your-rank-value" id="yourRankValue">#--</span>
                        </div>
                    </div>

                    <div class="leaderboard-tabs">
                        <button class="active" data-type="daily">Daily</button>
                        <button data-type="weekly">Weekly</button>
                        <button data-type="alltime">All-Time</button>
                    </div>

                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>PXL Earned</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardBody">
                            <tr><td colspan="5" style="text-align: center; color: var(--text-secondary);">Loading...</td></tr>
                        </tbody>
                    </table>

                    <div class="pagination">
                        <button id="prevBtn" disabled>Previous</button>
                        <span id="pageInfo" style="display: flex; align-items: center; padding: 0 16px; color: var(--text-secondary);"></span>
                        <button id="nextBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        const tabs = document.querySelectorAll('.leaderboard-tabs button');
        let currentType = 'daily';
        let currentPage = 1;
        let totalPages = 1;
        let userRank = null;

        async function loadLeaderboard(type, page) {
            currentType = type;
            currentPage = page;

            try {
                const response = await fetch(`/api/leaderboard.php?type=${type}&page=${page}&limit=20`);
                const result = await response.json();

                if (!result.ok) {
                    throw new Error(result.message);
                }

                const data = result.data;
                totalPages = Math.ceil(data.total_players / data.limit) || 1;
                userRank = data.user_rank;

                if (userRank) {
                    document.getElementById('yourRankContainer').style.display = 'block';
                    document.getElementById('yourRankValue').textContent = '#' + userRank;
                }

                renderTable(data.scores, data.user_rank);
                updatePagination();
            } catch (err) {
                document.getElementById('leaderboardBody').innerHTML =
                    '<tr><td colspan="5" style="text-align: center; color: var(--color-error);">Failed to load</td></tr>';
            }
        }

        function renderTable(scores, userRank) {
            const tbody = document.getElementById('leaderboardBody');

            if (scores.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-secondary);">No scores yet</td></tr>';
                return;
            }

            tbody.innerHTML = scores.map(s => {
                let rowClass = '';
                if (s.rank === 1) rowClass = 'gold';
                else if (s.rank === 2) rowClass = 'silver';
                else if (s.rank === 3) rowClass = 'bronze';

                if (userRank && s.rank === userRank) rowClass += ' user-row';

                const minutes = Math.floor(s.duration_seconds / 60);
                const seconds = s.duration_seconds % 60;

                return `
                    <tr class="${rowClass}">
                        <td class="rank-cell rank-${s.rank}">#${s.rank}</td>
                        <td class="username-cell">${escapeHtml(s.username)}</td>
                        <td class="score-cell">${s.score.toLocaleString()}</td>
                        <td class="pxl-cell">+${s.pxl_earned}</td>
                        <td>${minutes}:${seconds.toString().padStart(2, '0')}</td>
                    </tr>
                `;
            }).join('');
        }

        function updatePagination() {
            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
            document.getElementById('pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                loadLeaderboard(tab.dataset.type, 1);
            });
        });

        document.getElementById('prevBtn').addEventListener('click', () => {
            if (currentPage > 1) loadLeaderboard(currentType, currentPage - 1);
        });

        document.getElementById('nextBtn').addEventListener('click', () => {
            if (currentPage < totalPages) loadLeaderboard(currentType, currentPage + 1);
        });

        loadLeaderboard('daily', 1);
    </script>
</body>
</html>