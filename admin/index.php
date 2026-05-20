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

$stats = [];
try {
    $stats['users'] = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['pixels'] = (int)$db->query('SELECT COUNT(*) FROM pixels')->fetchColumn();
    $stats['currency'] = (int)$db->query('SELECT SUM(balance) FROM users')->fetchColumn() ?? 0;
    $stats['active'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE last_login_date >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();
    $stats['fill_pct'] = $stats['pixels'] > 0 ? round($stats['pixels'] / 100, 2) : 0;

    $stmt = $db->query('SELECT a.*, u.username FROM admin_log a JOIN users u ON a.admin_id = u.id ORDER BY a.performed_at DESC LIMIT 20');
    $admin_logs = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error('DB', 'Admin dashboard error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    $admin_logs = [];
}

$page_title = 'Admin Dashboard';
require_once __DIR__ . '/header.php';
?>

</div>
    <div class="page-header">
        <h1>Admin Dashboard</h1>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:var(--space-xl);">
        <div class="card" style="text-align:center;">
            <div style="font-size:32px;font-weight:700;"><?= number_format($stats['users']) ?></div>
            <div style="color:var(--text-muted);font-size:13px;">Total Users</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:32px;font-weight:700;"><?= number_format($stats['pixels']) ?></div>
            <div style="color:var(--text-muted);font-size:13px;">Claimed Pixels</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:32px;font-weight:700;"><?= $stats['fill_pct'] ?>%</div>
            <div style="color:var(--text-muted);font-size:13px;">Canvas Full</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:32px;font-weight:700;" class="currency"><?= number_format($stats['currency']) ?></div>
            <div style="color:var(--text-muted);font-size:13px;">Currency in Circulation</div>
        </div>
        <div class="card" style="text-align:center;">
            <div style="font-size:32px;font-weight:700;"><?= number_format($stats['active']) ?></div>
            <div style="color:var(--text-muted);font-size:13px;">Active Today</div>
        </div>
    </div>

    <h3>Recent Admin Actions</h3>
    <table>
        <thead>
            <tr><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>Date</th></tr>
        </thead>
        <tbody>
            <?php if (empty($admin_logs)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">No admin actions recorded</td></tr>
            <?php else: ?>
                <?php foreach ($admin_logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['username']) ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['target_type'] ?? '—') ?> #<?= $log['target_id'] ?? '—' ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars(substr($log['details'] ?? '', 0, 80)) ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= date('M j, H:i', strtotime($log['performed_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</main>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
