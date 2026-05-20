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

<div class="page-content" style="max-width:800px;">
    <div class="card" style="text-align:center;margin-bottom:var(--space-lg);padding: var(--space-xl);">
        <div class="avatar-circle lg" style="background:<?= htmlspecialchars($profile['avatar_color']) ?>;margin:0 auto var(--space-md);"><?= strtoupper(substr($profile['username'], 0, 1)) ?></div>
        <h2 style="margin:0 0 var(--space-sm);font-size:28px;"><?= htmlspecialchars($profile['username']) ?></h2>
        <div style="display:flex;align-items:center;justify-content:center;gap:var(--space-sm);flex-wrap:wrap;">
            <span class="level-badge">Level <?= (int)$profile['level'] ?></span>
            <?php if ((int)$profile['level'] >= 10): ?>
                <span class="badge badge-gold">⭐ Gold</span>
            <?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:var(--space-md);margin-top:var(--space-lg);padding-top:var(--space-lg);border-top:1px solid var(--border-subtle);">
            <div><div class="stat-value" style="font-size:22px;"><?= number_format($profile['best_score'] ?? 0) ?></div><div class="stat-label">Best Score</div></div>
            <div><div class="stat-value" style="font-size:22px;"><?= number_format($profile['pixels_owned'] ?? 0) ?></div><div class="stat-label">Pixels</div></div>
            <div><div class="stat-value" style="font-size:22px;"><?= number_format((int)$profile['xp']) ?></div><div class="stat-label">Total XP</div></div>
            <div><div class="stat-value" style="font-size:22px;"><?= date('M Y', strtotime($profile['created_at'])) ?></div><div class="stat-label">Joined</div></div>
        </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-lg);">
        <div class="card-header">
            <h3 class="card-title" style="margin:0;">Achievements</h3>
            <span class="badge badge-purple"><?= count($user_achievements) ?> / <?= count($all_achievements) ?></span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(90px,1fr));gap:var(--space-sm);margin-top:var(--space-md);">
            <?php foreach ($all_achievements as $ach): ?>
                <?php $earned = in_array($ach['slug'], $user_ach_slugs); ?>
                <div style="background:<?= $earned ? 'var(--bg-elevated)' : 'var(--bg-surface)' ?>;border:1px solid <?= $earned ? 'var(--purple-core)' : 'var(--border-subtle)' ?>;border-radius:var(--radius-md);padding:var(--space-sm) var(--space-md);text-align:center;transition:all var(--transition-fast);opacity:<?= $earned ? '1' : '0.5' ?>">
                    <div style="font-size:24px;"><?= $earned ? htmlspecialchars($ach['icon']) : '🔒' ?></div>
                    <div style="font-size:10px;font-weight:600;margin-top:4px;color:var(--text-secondary);"><?= htmlspecialchars($ach['name']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--space-lg);">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="margin:0;">Recent Games</h3>
                <span class="badge badge-purple"><?= count($games) ?></span>
            </div>
            <?php if (empty($games)): ?>
                <div class="empty-state" style="padding:var(--space-lg);">
                    <div class="empty-icon">🎮</div>
                    <h3>No games yet</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Score</th><th>Mult</th><th>Earned</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($games as $g): ?>
                            <tr>
                                <td style="font-weight:600;"><?= number_format((int)$g['score']) ?></td>
                                <td><?= number_format((float)$g['multiplier'], 1) ?>×</td>
                                <td><span class="currency">+<?= number_format((int)$g['currency_earned']) ?></span></td>
                                <td style="color:var(--text-muted);font-size:12px;"><?= date('M j', strtotime($g['played_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="margin:0;">Recent Pixels</h3>
                <span class="badge badge-purple"><?= count($pixels) ?></span>
            </div>
            <?php if (empty($pixels)): ?>
                <div class="empty-state" style="padding:var(--space-lg);">
                    <div class="empty-icon">🎨</div>
                    <h3>No pixels yet</h3>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>Position</th><th>Color</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php $shown = array_slice($pixels, 0, 10); foreach ($shown as $p): ?>
                            <tr>
                                <td style="font-family:var(--font-mono);font-size:12px;">(<?= (int)$p['x'] ?>, <?= (int)$p['y'] ?>)</td>
                                <td><span class="color-swatch" style="background:<?= htmlspecialchars($p['color']) ?>;"></span></td>
                                <td style="color:var(--text-muted);font-size:12px;"><?= date('M j', strtotime($p['placed_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
