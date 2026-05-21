<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/logger.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/headers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/xp.php';
require_once __DIR__ . '/includes/achievements.php';

$username = trim($_GET['user'] ?? '');
if (empty($username)) {
    header('Location: ' . BASE_URL . '/');
    exit();
}

$db = get_db();
$profile = null;

try {
    $stmt = $db->prepare('
        SELECT u.*,
               (SELECT MAX(score) FROM score_log WHERE user_id = u.id) AS best_score,
               (SELECT COUNT(*) FROM pixels WHERE owner_id = u.id) AS pixels_owned,
               (SELECT COUNT(*) FROM user_achievements WHERE user_id = u.id) AS achievement_count,
               (SELECT COUNT(*) FROM pixel_placements WHERE user_id = u.id) AS total_pixels_placed
        FROM users u WHERE u.username = ?
    ');
    $stmt->execute([$username]);
    $profile = $stmt->fetch();
} catch (PDOException $e) {
    log_error('DB', 'Profile query error: ' . $e->getMessage(), ['code' => $e->getCode()]);
}

if (!$profile) {
    $page_title = 'Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="page-center"><div class="empty-state"><div class="empty-icon">🔍</div><h3>User Not Found</h3><p>The user "' . htmlspecialchars($username) . '" does not exist.</p></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$user_id = (int) $profile['id'];
$pixels = [];
$games = [];

try {
    $stmt = $db->prepare('SELECT x, y, color, placed_at FROM pixels WHERE owner_id = ? ORDER BY placed_at DESC');
    $stmt->execute([$user_id]);
    $pixels = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT score, multiplier, currency_earned, played_at FROM score_log WHERE user_id = ? ORDER BY played_at DESC LIMIT 10');
    $stmt->execute([$user_id]);
    $games = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error('DB', 'Profile detail query error: ' . $e->getMessage(), ['code' => $e->getCode()]);
}

$all_achievements = get_all_achievements($db);
$user_achievements = get_user_achievements($db, $user_id);
$user_ach_slugs = array_column($user_achievements, 'slug');

$page_title = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8') . ' — Profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="user-profile profile-grid">
    <!-- LEFT: User Info -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div class="section-card" style="text-align:center; padding:40px 20px;">
            <div
                style="width:120px; height:120px; border-radius:0; background:<?= htmlspecialchars($profile['avatar_color']) ?>; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; font-size:48px; font-weight:900; color:black; border:2px solid white; box-shadow: 4px 4px 0px rgba(0,0,0,0.5);">
                <?= strtoupper(substr($profile['username'], 0, 1)) ?>
            </div>
            <h1 style="font-size:32px; font-weight:900; margin:0; letter-spacing:-1px; text-transform:uppercase;">
                <?= htmlspecialchars($profile['username']) ?>
            </h1>
            <div
                style="color:var(--accent-blue); font-weight:800; font-size:14px; margin-top:5px; text-transform:uppercase; letter-spacing:2px; font-family:var(--font-game);">
                OPERATIVE LEVEL <?= (int) $profile['level'] ?>
            </div>

            <div style="margin-top:30px; display:flex; flex-direction:column; gap:12px;">
                <div
                    style="background:rgba(255,255,255,0.03); padding:15px; border-radius:0; border:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--text-muted); font-size:12px; font-weight:bold; letter-spacing:1px;">BEST
                        SCORE</span>
                    <span
                        style="font-family:var(--font-game); color:white; font-size:18px;"><?= number_format($profile['best_score'] ?? 0) ?></span>
                </div>
                <div
                    style="background:rgba(255,255,255,0.03); padding:15px; border-radius:0; border:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--text-muted); font-size:12px; font-weight:bold; letter-spacing:1px;">PIXELS
                        OWNED</span>
                    <span
                        style="font-family:var(--font-game); color:var(--accent-blue); font-size:18px;"><?= number_format($profile['pixels_owned'] ?? 0) ?></span>
                </div>
                <div
                    style="background:rgba(255,255,255,0.03); padding:15px; border-radius:0; border:1px solid var(--border-default); display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:var(--text-muted); font-size:12px; font-weight:bold; letter-spacing:1px;">TOTAL
                        XP</span>
                    <span
                        style="font-family:var(--font-game); color:var(--accent-yellow); font-size:18px;"><?= number_format((int) $profile['xp']) ?></span>
                </div>
            </div>
        </div>

        <div class="section-card" style="padding:20px;">
            <h3
                style="font-size:14px; color:var(--text-muted); font-weight:bold; margin:0 0 15px; display:flex; align-items:center; gap:8px; text-transform:uppercase; letter-spacing:1px;">
                <i class="fad fa-thin fa-medal" style="color:var(--accent-yellow);"></i> ACHIEVEMENTS
                <span
                    style="margin-left:auto; font-size:11px; color:var(--accent-blue); background:rgba(59,130,246,0.1); padding:2px 8px; border-radius:0; border:1px solid rgba(59,130,246,0.2);"><?= count($user_ach_slugs) ?>
                    / <?= count($all_achievements) ?></span>
            </h3>
            <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:8px;">
                <?php foreach ($all_achievements as $ach): ?>
                    <?php $earned = in_array($ach['slug'], $user_ach_slugs); ?>
                    <div title="<?= htmlspecialchars($ach['name']) ?>"
                        style="aspect-ratio:1; background:<?= $earned ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.02)' ?>; border:1px solid <?= $earned ? 'var(--accent-blue)' : 'var(--border-default)' ?>; border-radius:0; display:flex; align-items:center; justify-content:center; font-size:18px; opacity:<?= $earned ? '1' : '0.1' ?>; filter: <?= $earned ? 'none' : 'grayscale(1)' ?>;">
                        <?= $earned ? htmlspecialchars($ach['icon']) : '🔒' ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Activity -->
    <div style="display:flex; flex-direction:column; gap:20px;">
        <div class="section-card" style="padding:0;">
            <div
                style="padding:20px; border-bottom:1px solid var(--border-default); display:flex; align-items:center; justify-content:space-between;">
                <h3 style="margin:0; font-size:18px; text-transform:uppercase; letter-spacing:1px;"><i
                        class="fad fa-thin fa-history" style="opacity:0.5; margin-right:10px;"></i>Recent Matches</h3>
            </div>
            <div style="padding:10px;">
                <?php if (empty($games)): ?>
                    <div style="padding:40px; text-align:center; color:var(--text-muted);">No games played yet.</div>
                <?php else: ?>
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left; color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                                <th style="padding:15px; letter-spacing:1px;">DATE</th>
                                <th style="text-align:right; letter-spacing:1px;">SCORE</th>
                                <th style="text-align:right; letter-spacing:1px;">MULT</th>
                                <th style="text-align:right; padding-right:15px; letter-spacing:1px;">REWARD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($games as $game): ?>
                                <tr style="border-bottom:1px solid var(--border-default); font-size:14px;">
                                    <td style="padding:15px; color:var(--text-muted);">
                                        <?= date('M j, Y H:i', strtotime($game['played_at'])) ?>
                                    </td>
                                    <td style="text-align:right; color:white; font-family:var(--font-game);">
                                        <?= number_format($game['score']) ?>
                                    </td>
                                    <td style="text-align:right; color:var(--accent-blue);">
                                        <?= number_format($game['multiplier'], 1) ?>×
                                    </td>
                                    <td
                                        style="text-align:right; padding-right:15px; color:var(--accent-yellow); font-weight:bold;">
                                        +<?= number_format($game['currency_earned']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<div class="section-card" style="padding:0;">
    <div
        style="padding:20px; border-bottom:1px solid var(--border-default); display:flex; align-items:center; justify-content:space-between;">
        <h3 style="margin:0; font-size:18px; text-transform:uppercase; letter-spacing:1px;"><i
                class="fad fa-thin fa-grid-2" style="opacity:0.5; margin-right:10px;"></i>Captured
            Territory</h3>
    </div>
    <div style="padding:10px;">
        <?php if (empty($pixels)): ?>
            <div style="padding:40px; text-align:center; color:var(--text-muted);">No territory claimed.</div>
        <?php else: ?>
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="text-align:left; color:var(--text-muted); font-size:11px; text-transform:uppercase;">
                        <th style="padding:15px; letter-spacing:1px;">COORD</th>
                        <th style="letter-spacing:1px;">COLOR</th>
                        <th style="text-align:right; padding-right:15px; letter-spacing:1px;">PLACED</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $shown = array_slice($pixels, 0, 10);
                    foreach ($shown as $p): ?>
                        <tr style="border-bottom:1px solid var(--border-default); font-size:14px;">
                            <td style="padding:15px; font-family:var(--font-game); color:var(--accent-blue);">
                                <?= (int) $p['x'] ?>, <?= (int) $p['y'] ?>
                            </td>
                            <td>
                                <div
                                    style="width:16px; height:16px; border-radius:0; background:<?= htmlspecialchars($p['color']) ?>; border:1px solid rgba(255,255,255,0.1);">
                                </div>
                            </td>
                            <td style="text-align:right; padding-right:15px; color:var(--text-muted); font-size:12px;">
                                <?= date('M j, Y', strtotime($p['placed_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>