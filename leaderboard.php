<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';

$period = trim($_GET['period'] ?? 'all');
$is_ajax = isset($_GET['ajax']);

if (!$is_ajax) {
    $page_title = 'Leaderboard';
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="page-content">
        <div class="page-header" style="text-align:center;">
            <h1>Leaderboard</h1>
            <p>Top players of <?= APP_NAME ?></p>
        </div>

        <div class="card">
            <div class="tabs" style="margin:0 0 var(--space-md) 0;border:none;">
                <button class="tab-btn <?= $period === 'all' ? 'active' : '' ?>" data-lb="all">All-Time</button>
                <button class="tab-btn <?= $period === 'week' ? 'active' : '' ?>" data-lb="week">This Week</button>
                <button class="tab-btn <?= $period === 'today' ? 'active' : '' ?>" data-lb="today">Today</button>
                <button class="tab-btn <?= $period === 'pixels' ? 'active' : '' ?>" data-lb="pixels">Most Pixels</button>
                <button class="tab-btn <?= $period === 'xp' ? 'active' : '' ?>" data-lb="xp">Most XP</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width:60px;">Rank</th>
                        <th>Player</th>
                        <th style="width:80px;">Level</th>
                        <th id="lb-col-score" style="text-align:right;">Score</th>
                        <th id="lb-col-mult" style="text-align:right;">Mult</th>
                        <th id="lb-col-earned" style="text-align:right;">Earned</th>
                        <th style="width:100px;">Date</th>
                    </tr>
                </thead>
                <tbody id="lb-body">
    <?php
}

$db = get_db();
$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$rows_data = [];

try {
    if ($period === 'pixels') {
        $stmt = $db->query('
            SELECT u.id, u.username, u.avatar_color, u.level, u.xp,
                   COUNT(p.id) AS pixel_count,
                   (SELECT COUNT(*) FROM pixel_placements pp WHERE pp.user_id = u.id) AS total_placed,
                   (SELECT COUNT(*) FROM user_achievements ua WHERE ua.user_id = u.id) AS ach_count
            FROM users u
            LEFT JOIN pixels p ON u.id = p.owner_id
            GROUP BY u.id
            ORDER BY pixel_count DESC, u.xp DESC
            LIMIT 50
        ');
        $rows_data = $stmt->fetchAll();
    } elseif ($period === 'xp') {
        $stmt = $db->query('
            SELECT u.id, u.username, u.avatar_color, u.level, u.xp,
                   (SELECT COUNT(*) FROM user_achievements ua WHERE ua.user_id = u.id) AS ach_count
            FROM users u
            ORDER BY u.xp DESC
            LIMIT 50
        ');
        $rows_data = $stmt->fetchAll();
    } else {
        $where = '';
        if ($period === 'week') {
            $where = ' WHERE s.played_at > DATE_SUB(NOW(), INTERVAL 1 WEEK)';
        } elseif ($period === 'today') {
            $where = ' WHERE DATE(s.played_at) = CURDATE()';
        }
        $sql = "
            SELECT u.id, u.username, u.avatar_color, u.level, s.score, s.multiplier, s.currency_earned, s.played_at, s.xp_earned
            FROM score_log s
            JOIN users u ON s.user_id = u.id
            $where
            ORDER BY s.score DESC, s.played_at DESC
            LIMIT 50
        ";
        $stmt = $db->query($sql);
        $rows_data = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    log_error('DB', 'Leaderboard query error: ' . $e->getMessage(), ['code' => $e->getCode()]);
}

$rank = 1;
foreach ($rows_data as $row) {
    $user_id = (int)$row['id'];
    $is_me = $current_user_id === $user_id;
    $rank_icon = '';
    if ($rank === 1) $rank_icon = '🥇 ';
    elseif ($rank === 2) $rank_icon = '🥈 ';
    elseif ($rank === 3) $rank_icon = '🥉 ';

    $rank_style = '';
    if ($rank === 1) $rank_style = 'color:var(--gold-bright);';
    elseif ($rank === 2) $rank_style = 'color:#c0c0c0;';
    elseif ($rank === 3) $rank_style = 'color:#cd7f32;';
    ?>
    <tr class="<?= $is_me ? 'highlighted' : '' ?>">
        <td style="<?= $rank_style ?>font-weight:700;"><?= $rank_icon . $rank ?></td>
        <td>
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="avatar-circle" style="background:<?= htmlspecialchars($row['avatar_color']) ?>;width:32px;height:32px;font-size:13px;"><?= strtoupper(substr($row['username'], 0, 1)) ?></span>
                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($row['username']) ?>" style="color:var(--text-primary);text-decoration:none;font-weight:600;"><?= htmlspecialchars($row['username']) ?></a>
            </div>
        </td>
        <td><span class="level-badge">Lv<?= (int)$row['level'] ?></span></td>
        <?php if ($period === 'pixels'): ?>
            <td><?= (int)($row['pixel_count'] ?? 0) ?> owned</td>
            <td><?= (int)($row['total_placed'] ?? 0) ?> placed</td>
            <td><span class="currency"><?= (int)($row['ach_count'] ?? 0) ?> 🏆</span></td>
            <td>—</td>
        <?php elseif ($period === 'xp'): ?>
            <td><?= number_format((int)$row['xp']) ?> XP</td>
            <td>—</td>
            <td><span class="currency"><?= (int)($row['ach_count'] ?? 0) ?> 🏆</span></td>
            <td>—</td>
        <?php else: ?>
            <td style="font-weight:600;"><?= number_format((int)$row['score']) ?></td>
            <td><?= number_format((float)$row['multiplier'], 1) ?>×</td>
            <td><span class="currency">+<?= number_format((int)$row['currency_earned']) ?> 💰</span></td>
            <td style="color:var(--text-muted);font-size:13px;"><?= date('M j, Y', strtotime($row['played_at'])) ?></td>
        <?php endif; ?>
    </tr>
    <?php
    $rank++;
}

if (!$is_ajax) {
    ?>
                </tbody>
            </table>
        </div>
    </div>
    <script nonce="<?= $GLOBALS['csp_nonce'] ?>">
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            window.location.href = 'leaderboard.php?period=' + encodeURIComponent(this.dataset.lb);
        });
    });
    </script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
}
