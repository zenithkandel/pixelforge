<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$total_users = Database::fetch("SELECT COUNT(*) as cnt FROM users");
$total_pixels = Database::fetch("SELECT COUNT(*) as cnt FROM pixels WHERE owner_id IS NOT NULL");
$claimed_pixels = Database::fetch("SELECT COUNT(*) as cnt FROM pixels WHERE expires_at > NOW() OR expires_at IS NULL");
$fill_percent = round(($claimed_pixels['cnt'] / 10000) * 100, 1);
$total_currency = Database::fetch("SELECT SUM(balance) as total FROM users");
$active_today = Database::fetch("SELECT COUNT(*) as cnt FROM users WHERE last_login_date = CURDATE()");

$recent_logs = Database::fetchAll("
    SELECT al.*, u.username as admin_username
    FROM admin_log al
    JOIN users u ON al.admin_id = u.id
    ORDER BY al.performed_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="admin-page">
        <h1>Admin Dashboard</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $total_users['cnt']; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $claimed_pixels['cnt']; ?></div>
                <div class="stat-label">Pixels Claimed (<?php echo $fill_percent; ?>%)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_currency['total'] ?? 0); ?></div>
                <div class="stat-label">Total Currency</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_today['cnt']; ?></div>
                <div class="stat-label">Active Today</div>
            </div>
        </div>

        <div class="admin-nav">
            <a href="<?php echo APP_URL; ?>/admin/canvas.php" class="btn">Canvas Management</a>
            <a href="<?php echo APP_URL; ?>/admin/users.php" class="btn">User Management</a>
            <a href="<?php echo APP_URL; ?>/admin/logs.php" class="btn">Admin Logs</a>
        </div>

        <div class="section">
            <h2>Recent Admin Actions</h2>
            <table class="admin-table">
                <thead><tr><th>Time</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
                <tbody>
                <?php foreach ($recent_logs as $log): ?>
                <tr>
                    <td><?php echo date('M j, g:i A', strtotime($log['performed_at'])); ?></td>
                    <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                    <td><span class="action-tag"><?php echo htmlspecialchars($log['action']); ?></span></td>
                    <td><?php echo $log['target_type'] ? htmlspecialchars($log['target_type']) . ' #' . $log['target_id'] : '-'; ?></td>
                    <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>