<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

$user = get_logged_in_user();

$scores_all = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, u.level, s.score, s.multiplier, s.currency_earned, s.played_at
    FROM score_log s
    JOIN users u ON s.user_id = u.id
    ORDER BY s.score DESC
    LIMIT 50
");

$scores_week = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, u.level, s.score, s.multiplier, s.currency_earned, s.played_at
    FROM score_log s
    JOIN users u ON s.user_id = u.id
    WHERE s.played_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY s.score DESC
    LIMIT 50
");

$scores_today = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, u.level, s.score, s.multiplier, s.currency_earned, s.played_at
    FROM score_log s
    JOIN users u ON s.user_id = u.id
    WHERE DATE(s.played_at) = CURDATE()
    ORDER BY s.score DESC
    LIMIT 50
");

$pixels_leaderboard = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, u.level, COUNT(p.id) as pixel_count,
           (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id) as total_placed,
           u.created_at
    FROM users u
    LEFT JOIN pixels p ON p.owner_id = u.id
    GROUP BY u.id
    HAVING pixel_count > 0
    ORDER BY pixel_count DESC
    LIMIT 50
");

$xp_leaderboard = Database::fetchAll("
    SELECT u.id, u.username, u.avatar_color, u.level, u.xp,
           (SELECT COUNT(*) FROM user_achievements WHERE user_id = u.id) as achievement_count
    FROM users u
    ORDER BY u.xp DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .leaderboard-page { max-width: 900px; margin: 0 auto; padding: 1rem; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .tab {
            padding: 0.75rem 1.5rem; background: #111; border: 1px solid #222; border-radius: 6px;
            cursor: pointer; color: #9ca3af; transition: all 0.2s;
        }
        .tab:hover { background: #1a1a1a; }
        .tab.active { background: #7c3aed; color: white; border-color: #7c3aed; }
        .tab-section { display: none; }
        .tab-section.active { display: block; }
        .leaderboard-table { width: 100%; border-collapse: collapse; }
        .leaderboard-table th, .leaderboard-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #222; }
        .leaderboard-table th { background: #111; color: #7c3aed; font-weight: 600; }
        .leaderboard-table tr:hover { background: #111; }
        .leaderboard-table tr.highlight { border: 1px solid #7c3aed; background: rgba(124, 58, 237, 0.1); }
        .player-cell { display: flex; align-items: center; gap: 0.5rem; }
        .avatar {
            width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: bold; color: white; font-size: 0.85rem;
        }
        .rank-cell { font-weight: bold; color: #f59e0b; }
        .rank-1 { color: #ffd700; font-size: 1.2rem; }
        .rank-2 { color: #c0c0c0; font-size: 1.1rem; }
        .rank-3 { color: #cd7f32; }
        .your-rank { margin-top: 1rem; padding: 1rem; background: #111; border-radius: 8px; text-align: center; color: #f59e0b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="leaderboard-page">
        <h1>Leaderboard</h1>

        <div class="tabs">
            <button class="tab active" data-target="scores">Top Scores</button>
            <button class="tab" data-target="pixels">Most Pixels</button>
            <button class="tab" data-target="xp">Most XP</button>
        </div>

        <div id="scores" class="tab-section active">
            <div class="sub-tabs">
                <button class="tab active" data-sub="all">All-time</button>
                <button class="tab" data-sub="week">This Week</button>
                <button class="tab" data-sub="today">Today</button>
            </div>

            <div id="scores-all" class="sub-section active">
                <table class="leaderboard-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Score</th><th>Multiplier</th><th>Currency</th></tr></thead>
                    <tbody>
                    <?php foreach ($scores_all as $i => $row): ?>
                    <tr class="<?php echo $user && $row['id'] == $user['id'] ? 'highlight' : ''; ?>">
                        <td class="rank-cell <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></td>
                        <td>
                            <div class="player-cell">
                                <div class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>"><?php echo strtoupper($row['username'][0]); ?></div>
                                <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($row['username']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($row['username']); ?></a>
                                <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                            </div>
                        </td>
                        <td><?php echo $row['score']; ?></td>
                        <td>×<?php echo $row['multiplier']; ?></td>
                        <td style="color: #f59e0b;">+<?php echo $row['currency_earned']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="scores-week" class="sub-section" style="display:none;">
                <table class="leaderboard-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Score</th><th>Multiplier</th><th>Currency</th></tr></thead>
                    <tbody>
                    <?php foreach ($scores_week as $i => $row): ?>
                    <tr class="<?php echo $user && $row['id'] == $user['id'] ? 'highlight' : ''; ?>">
                        <td class="rank-cell <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></td>
                        <td>
                            <div class="player-cell">
                                <div class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>"><?php echo strtoupper($row['username'][0]); ?></div>
                                <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($row['username']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($row['username']); ?></a>
                                <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                            </div>
                        </td>
                        <td><?php echo $row['score']; ?></td>
                        <td>×<?php echo $row['multiplier']; ?></td>
                        <td style="color: #f59e0b;">+<?php echo $row['currency_earned']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="scores-today" class="sub-section" style="display:none;">
                <table class="leaderboard-table">
                    <thead><tr><th>Rank</th><th>Player</th><th>Score</th><th>Multiplier</th><th>Currency</th></tr></thead>
                    <tbody>
                    <?php foreach ($scores_today as $i => $row): ?>
                    <tr class="<?php echo $user && $row['id'] == $user['id'] ? 'highlight' : ''; ?>">
                        <td class="rank-cell <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></td>
                        <td>
                            <div class="player-cell">
                                <div class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>"><?php echo strtoupper($row['username'][0]); ?></div>
                                <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($row['username']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($row['username']); ?></a>
                                <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                            </div>
                        </td>
                        <td><?php echo $row['score']; ?></td>
                        <td>×<?php echo $row['multiplier']; ?></td>
                        <td style="color: #f59e0b;">+<?php echo $row['currency_earned']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="pixels" class="tab-section">
            <table class="leaderboard-table">
                <thead><tr><th>Rank</th><th>Player</th><th>Pixels Owned</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($pixels_leaderboard as $i => $row): ?>
                <tr class="<?php echo $user && $row['id'] == $user['id'] ? 'highlight' : ''; ?>">
                    <td class="rank-cell <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>"><?php echo strtoupper($row['username'][0]); ?></div>
                            <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($row['username']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($row['username']); ?></a>
                            <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                        </div>
                    </td>
                    <td><?php echo $row['pixel_count']; ?></td>
                    <td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="xp" class="tab-section">
            <table class="leaderboard-table">
                <thead><tr><th>Rank</th><th>Player</th><th>Level</th><th>Total XP</th><th>Achievements</th></tr></thead>
                <tbody>
                <?php foreach ($xp_leaderboard as $i => $row): ?>
                <tr class="<?php echo $user && $row['id'] == $user['id'] ? 'highlight' : ''; ?>">
                    <td class="rank-cell <?php echo $i < 3 ? 'rank-' . ($i + 1) : ''; ?>"><?php echo $i + 1; ?></td>
                    <td>
                        <div class="player-cell">
                            <div class="avatar" style="background: <?php echo htmlspecialchars($row['avatar_color']); ?>"><?php echo strtoupper($row['username'][0]); ?></div>
                            <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($row['username']); ?>" style="color: inherit; text-decoration: none;"><?php echo htmlspecialchars($row['username']); ?></a>
                            <span class="level-badge small">Lv.<?php echo $row['level']; ?></span>
                        </div>
                    </td>
                    <td><?php echo $row['level']; ?></td>
                    <td><?php echo number_format($row['xp']); ?></td>
                    <td><?php echo $row['achievement_count']; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-section').forEach(s => s.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.target).classList.add('active');
            });
        });

        document.querySelectorAll('[data-sub]').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('[data-sub]').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.sub-section').forEach(s => s.style.display = 'none');
                tab.classList.add('active');
                const target = tab.dataset.sub === 'all' ? 'scores-all' : (tab.dataset.sub === 'week' ? 'scores-week' : 'scores-today');
                document.getElementById(target).style.display = 'table';
            });
        });
    </script>
</body>
</html>