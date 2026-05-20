<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

$action_filter = $_GET['action'] ?? '';
$where = '';
$params = [];
if ($action_filter) {
    $where = "WHERE al.action = ?";
    $params = [$action_filter];
}

$total = Database::fetch("SELECT COUNT(*) as cnt FROM admin_log al $where", $params);
$logs = Database::fetchAll("
    SELECT al.*, u.username as admin_username
    FROM admin_log al
    JOIN users u ON al.admin_id = u.id
    $where
    ORDER BY al.performed_at DESC
    LIMIT $per_page OFFSET $offset
", $params);

$total_pages = ceil($total['cnt'] / $per_page);

$actions = Database::fetchAll("SELECT DISTINCT action FROM admin_log ORDER BY action");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Logs - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <main class="admin-page">
        <h1>Admin Logs</h1>

        <div class="admin-nav">
            <a href="<?php echo APP_URL; ?>/admin/index.php" class="btn">← Back to Dashboard</a>
            <a href="<?php echo APP_URL; ?>/admin/canvas.php" class="btn">Canvas Management</a>
            <a href="<?php echo APP_URL; ?>/admin/users.php" class="btn">User Management</a>
        </div>

        <div class="filter-bar">
            <label>Filter by action:</label>
            <select id="action-filter">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $action_filter === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <table class="admin-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Target Type</th>
                    <th>Target ID</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?php echo date('M j, Y g:i:s A', strtotime($log['performed_at'])); ?></td>
                <td><?php echo htmlspecialchars($log['admin_username']); ?></td>
                <td><span class="action-tag"><?php echo htmlspecialchars($log['action']); ?></span></td>
                <td><?php echo $log['target_type'] ? htmlspecialchars($log['target_type']) : '-'; ?></td>
                <td><?php echo $log['target_id'] ?? '-'; ?></td>
                <td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?php echo $i; ?>&action=<?php echo urlencode($action_filter); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </main>

    <script>
        document.getElementById('action-filter').addEventListener('change', function() {
            window.location.href = '?action=' + encodeURIComponent(this.value);
        });
    </script>
</body>
</html>