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
    <div class="leaderboard-app" style="display:flex; flex-direction:column; gap:20px;">
        <div class="section-card" style="padding:30px;">
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px; border-bottom:1px solid var(--border-default); padding-bottom:20px;">
                <h1
                    style="font-size:32px; font-weight:900; letter-spacing:1px; color:white; text-transform:uppercase; font-family:var(--font-game);">
                    <i class="fad fa-thin fa-list-ol" style="color:var(--accent-blue); margin-right:15px;"></i>Global
                    Rankings</h1>
                <div class="tabs"
                    style="display:flex; gap:5px; background:rgba(0,0,0,0.3); padding:4px; border-radius:0; border:1px solid var(--border-default);">
                    <button class="tab-btn <?= $period === 'all' ? 'active' : '' ?>" data-lb="all"
                        style="background:none; border:none; color:var(--text-secondary); padding:8px 20px; border-radius:0; font-weight:800; cursor:pointer; font-family:var(--font-game); text-transform:uppercase; letter-spacing:1px;">All-Time</button>
                    <button class="tab-btn <?= $period === 'week' ? 'active' : '' ?>" data-lb="week"
                        style="background:none; border:none; color:var(--text-secondary); padding:8px 20px; border-radius:0; font-weight:800; cursor:pointer; font-family:var(--font-game); text-transform:uppercase; letter-spacing:1px;">Weekly</button>
                    <button class="tab-btn <?= $period === 'today' ? 'active' : '' ?>" data-lb="today"
                        style="background:none; border:none; color:var(--text-secondary); padding:8px 20px; border-radius:0; font-weight:800; cursor:pointer; font-family:var(--font-game); text-transform:uppercase; letter-spacing:1px;">Today</button>
                    <button class="tab-btn <?= $period === 'pixels' ? 'active' : '' ?>" data-lb="pixels"
                        style="background:none; border:none; color:var(--text-secondary); padding:8px 20px; border-radius:0; font-weight:800; cursor:pointer; font-family:var(--font-game); text-transform:uppercase; letter-spacing:1px;">Pixels</button>
                    <button class="tab-btn <?= $period === 'xp' ? 'active' : '' ?>" data-lb="xp"
                        style="background:none; border:none; color:var(--text-secondary); padding:8px 20px; border-radius:0; font-weight:800; cursor:pointer; font-family:var(--font-game); text-transform:uppercase; letter-spacing:1px;">XP</button>
                </div>
            </div>

            <table style="width:100%; border-collapse:collapse; font-size:15px;">
                <thead>
                    <tr
                        style="text-align:left; color:var(--text-muted); border-bottom:1px solid var(--border-default); text-transform:uppercase; font-size:11px; font-weight:800; letter-spacing:1px;">
                        <th style="padding:15px; width:80px;">RANK</th>
                        <th>OPERATIVE</th>
                        <th style="width:100px;">LEVEL</th>
                        <th id="lb-col-score" style="text-align:right; width:120px;">SCORE</th>
                        <th id="lb-col-mult" style="text-align:right; width:80px;">MULT</th>
                        <th id="lb-col-earned" style="text-align:right; width:120px;">CREDITS</th>
                        <th style="width:140px; text-align:right; padding-right:15px;">DATE</th>
                    </tr>
                </thead>
                <tbody id="lb-body" style="color:var(--text-secondary);">

                    <?php
}

$db = get_db();
$current_user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

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
    $user_id = (int) $row['id'];
    $is_me = $current_user_id === $user_id;
    $rank_icon = '';
    if ($rank === 1)
        $rank_icon = '🥇';
    elseif ($rank === 2)
        $rank_icon = '🥈';
    elseif ($rank === 3)
        $rank_icon = '🥉';

    $rank_style = '';
    if ($rank === 1)
        $rank_style = 'color:var(--gold);';
    elseif ($rank === 2)
        $rank_style = 'color:#e2e8f0;';
    elseif ($rank === 3)
        $rank_style = 'color:#fb923c;';
    ?>
                    <tr
                        style="<?= $is_me ? 'background:var(--bg-active); border:1px solid var(--accent);' : '' ?> border-bottom:1px solid var(--border-dim);">
                        <td style="<?= $rank_style ?> font-weight:bold; padding:18px 15px; font-size:18px;">
                            <?= $rank_icon ?: $rank ?>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div
                                    style="width:32px; height:32px; border-radius:var(--radius-sm); background:<?= htmlspecialchars($row['avatar_color']) ?>; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold; color:black; border:1px solid rgba(255,255,255,0.2);">
                                    <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                </div>
                                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($row['username']) ?>"
                                    style="color:var(--text-secondary); text-decoration:none; font-weight:<?= $is_me ? 'bold' : 'normal' ?>;"><?= htmlspecialchars($row['username']) ?></a>
                            </div>
                        </td>
                        <td style="color:var(--text-muted);">Lv<?= (int) $row['level'] ?></td>
                        <?php if ($period === 'pixels'): ?>
                            <td style="text-align:right; font-family:var(--font-game); color:white;">
                                <?= (int) ($row['pixel_count'] ?? 0) ?>
                            </td>
                            <td style="text-align:right; color:var(--text-muted);"><?= (int) ($row['total_placed'] ?? 0) ?></td>
                            <td style="text-align:right; color:var(--gold); font-weight:bold;">
                                <?= (int) ($row['ach_count'] ?? 0) ?> <i class="fad fa-thin fa-medal"></i>
                            </td>
                            <td style="text-align:right; padding-right:15px; font-size:12px; color:var(--text-muted);">—</td>
                        <?php elseif ($period === 'xp'): ?>
                            <td style="text-align:right; font-family:var(--font-game); color:white;">
                                <?= number_format((int) $row['xp'] ?? 0) ?>
                            </td>
                            <td style="text-align:right; color:var(--text-muted);">—</td>
                            <td style="text-align:right; color:var(--gold); font-weight:bold;">
                                <?= (int) ($row['ach_count'] ?? 0) ?> <i class="fad fa-thin fa-medal"></i>
                            </td>
                            <td style="text-align:right; padding-right:15px; font-size:12px; color:var(--text-muted);">—</td>
                        <?php else: ?>
                            <td style="text-align:right; font-family:var(--font-game); color:white;">
                                <?= number_format((int) $row['score']) ?>
                            </td>
                            <td style="text-align:right; color:var(--text-muted); font-size:12px;">
                                <?= number_format((float) $row['multiplier'], 1) ?>×
                            </td>
                            <td style="text-align:right; color:var(--gold); font-weight:bold;">
                                +<?= number_format((int) $row['currency_earned']) ?></td>
                            <td style="text-align:right; padding-right:15px; font-size:12px; color:var(--text-muted);">
                                <?= date('M j, H:i', strtotime($row['played_at'])) ?>
                            </td>
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
        document.querySelectorAll('.tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                window.location.href = 'leaderboard.php?period=' + encodeURIComponent(this.dataset.lb);
            });
        });
    </script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
}

