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

$user_id = (int)$profile['id'];
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

<div class="page-content" style="max-width:700px;">
    <div class="card" style="text-align:center;margin-bottom:var(--space-lg);">
        <div class="avatar-circle lg" style="background:<?= htmlspecialchars($profile['avatar_color']) ?>;margin:0 auto;"><?= strtoupper(substr($profile['username'], 0, 1)) ?></div>
        <h2 style="margin:12px 0 4px;"><?= htmlspecialchars($profile['username']) ?></h2>
        <span class="level-badge">Level <?= (int)$profile['level'] ?></span>
        <?php if ((int)$profile['level'] >= 10): ?>
            <span style="color:var(--gold-bright);font-size:12px;margin-left:8px;">⭐ Gold Name</span>
        <?php endif; ?>
        <div style="display:flex;justify-content:center;gap:24px;margin-top:16px;flex-wrap:wrap;">
            <div><div style="font-size:20px;font-weight:700;"><?= number_format($profile['best_score'] ?? 0) ?></div><div style="font-size:11px;color:var(--text-muted);">Best Score</div></div>
            <div><div style="font-size:20px;font-weight:700;"><?= number_format($profile['pixels_owned'] ?? 0) ?></div><div style="font-size:11px;color:var(--text-muted);">Pixels</div></div>
            <div><div style="font-size:20px;font-weight:700;"><?= number_format((int)$profile['xp']) ?></div><div style="font-size:11px;color:var(--text-muted);">XP</div></div>
            <div><div style="font-size:20px;font-weight:700;"><?= date('M Y', strtotime($profile['created_at'])) ?></div><div style="font-size:11px;color:var(--text-muted);">Joined</div></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-lg);">
        <h3 style="margin-top:0;">Achievements</h3>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($all_achievements as $ach): ?>
                <?php $earned = in_array($ach['slug'], $user_ach_slugs); ?>
                <div style="background:<?= $earned ? 'var(--bg-elevated)' : 'rgba(255,255,255,0.03)' ?>;border:1px solid <?= $earned ? 'var(--purple-core)' : 'var(--border-subtle)' ?>;border-radius:var(--radius-md);padding:8px 12px;text-align:center;width:calc(25% - 6px);min-width:70px;opacity:<?= $earned ? '1' : '0.4' ?>">
                    <div style="font-size:22px;"><?= $earned ? htmlspecialchars($ach['icon']) : '🔒' ?></div>
                    <div style="font-size:10px;font-weight:600;margin-top:2px;"><?= htmlspecialchars($ach['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display:flex;gap:var(--space-lg);flex-wrap:wrap;">
        <div class="card" style="flex:1;min-width:250px;">
            <h3 style="margin-top:0;">Recent Games</h3>
            <?php if (empty($games)): ?>
                <div class="empty-state" style="padding:var(--space-lg) var(--space-md);">
                    <div class="empty-icon">🎮</div>
                    <h3>No games yet</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Score</th><th>Multiplier</th><th>Earned</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                            <tr>
                                <td style="font-weight:600;"><?= number_format((int)$g['score']) ?></td>
                                <td><?= number_format((float)$g['multiplier'], 1) ?>×</td>
                                <td class="currency">+<?= number_format((int)$g['currency_earned']) ?></td>
                                <td style="font-size:12px;color:var(--text-muted);"><?= date('M j', strtotime($g['played_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card" style="flex:1;min-width:250px;">
            <h3 style="margin-top:0;">Recent Pixels</h3>
            <?php if (empty($pixels)): ?>
                <div class="empty-state" style="padding:var(--space-lg) var(--space-md);">
                    <div class="empty-icon">🎨</div>
                    <h3>No pixels yet</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Position</th><th>Color</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $shown = array_slice($pixels, 0, 10); foreach ($shown as $p): ?>
                            <tr>
                                <td style="font-family:var(--font-mono);font-size:13px;">(<?= (int)$p['x'] ?>, <?= (int)$p['y'] ?>)</td>
                                <td><span style="display:inline-block;width:18px;height:18px;border-radius:4px;background:<?= htmlspecialchars($p['color']) ?>;border:1px solid var(--border-default);vertical-align:middle;"></span> <?= htmlspecialchars($p['color']) ?></td>
                                <td style="font-size:12px;color:var(--text-muted);"><?= date('M j', strtotime($p['placed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
