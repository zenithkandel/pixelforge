<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xp.php';
require_admin();

$db = get_db();

try {
    $stats = [];
    $stats['users'] = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['pixels'] = (int) $db->query('SELECT COUNT(*) FROM pixels')->fetchColumn();
    $stats['currency'] = (int) $db->query('SELECT SUM(balance) FROM users')->fetchColumn() ?? 0;
    $stats['active'] = (int) $db->query("SELECT COUNT(*) FROM users WHERE last_login_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
    $stats['total_xp'] = (int) $db->query('SELECT SUM(xp) FROM users')->fetchColumn() ?? 0;

    $stmt = $db->query('SELECT a.*, u.username FROM admin_log a JOIN users u ON a.admin_id = u.id ORDER BY a.performed_at DESC LIMIT 8');
    $admin_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error('DB', 'Admin dashboard error: ' . $e->getMessage());
    die('Dashboard Error');
}

$page_title = 'System Overview';
require_once __DIR__ . '/header.php';
?>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fad fa-thin fa-users"></i></div>
        <span class="stat-value"><?= number_format($stats['users']) ?></span>
        <span class="stat-label">Verified Citizens</span>
        <div class="stat-trend trend-up"><i class="fad fa-thin fa-caret-up"></i> Active: <?= $stats['active'] ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fad fa-thin fa-paint-brush"></i></div>
        <span class="stat-value"><?= number_format($stats['pixels']) ?></span>
        <span class="stat-label">Pixels Placed</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fad fa-thin fa-star"></i></div>
        <span class="stat-value"><?= number_format($stats['total_xp'] / 1000, 1) ?>k</span>
        <span class="stat-label">Global XP Bloom</span>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fad fa-thin fa-coins"></i></div>
        <span class="stat-value"><?= number_format($stats['currency']) ?></span>
        <span class="stat-label">Forge Credits</span>
    </div>
</div>

<div class="section-card">
    <div class="section-header">
        <h2 class="section-title">Critical Operations Log</h2>
        <a href="logs.php" class="btn-pixel" style="padding: 10px 20px; font-size: 13px;">View All Audit</a>
    </div>

    <div class="pro-table-wrapper">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Operator</th>
                    <th>Intercepted Action</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admin_logs as $log): ?>
                    <tr>
                        <td style="color:var(--text-muted); font-size: 13px; font-family: monospace;">
                            <?= $log['performed_at'] ?>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div
                                    style="width:28px; height:28px; border-radius:var(--radius-sm); background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:900;">
                                    <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                </div>
                                <strong><?= htmlspecialchars($log['username']) ?></strong>
                            </div>
                        </td>
                        <td><span class="tag-xp" style="padding: 4px 12px;"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td><span style="color:var(--green); font-weight: 700;"><i class="fad fa-thin fa-shield-alt"></i>
                                SUCCESS</span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>