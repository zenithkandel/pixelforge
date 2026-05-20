<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = get_db();
$filter_action = trim($_GET['action_filter'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;

$page_title = 'Logs';
require_once __DIR__ . '/header.php';
?>

try {
    $count_sql = 'SELECT COUNT(*) FROM admin_log';
    $logs_sql = 'SELECT a.*, u.username FROM admin_log a JOIN users u ON a.admin_id = u.id';
    $params = [];

    if (!empty($filter_action)) {
        $count_sql .= ' WHERE a.action = ?';
        $logs_sql .= ' WHERE a.action = ?';
        $params[] = $filter_action;
    }

    $logs_sql .= ' ORDER BY a.performed_at DESC LIMIT ' . $per_page . ' OFFSET ' . $offset;

    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmt = $db->prepare($logs_sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $total_pages = ceil($total / $per_page);
} catch (PDOException $e) {
    log_error('DB', 'Admin logs query error: ' . $e->getMessage(), ['code' => $e->getCode()]);
    $logs = [];
    $total = 0;
    $total_pages = 0;
}

$page_title = 'Logs';
require_once __DIR__ . '/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display:flex;justify-content:space-between;align-items:center;">
        <div>
            <h1>Admin Log</h1>
            <p><?= number_format($total) ?> actions recorded</p>
        </div>
        <form method="GET" style="display:flex;gap:8px;">
            <select name="action_filter" style="width:200px;">
                <option value="">All Actions</option>
                <option value="set_balance" <?= $filter_action === 'set_balance' ? 'selected' : '' ?>>Set Balance</option>
                <option value="adjust_balance" <?= $filter_action === 'adjust_balance' ? 'selected' : '' ?>>Adjust Balance</option>
                <option value="change_role" <?= $filter_action === 'change_role' ? 'selected' : '' ?>>Change Role</option>
                <option value="delete_user" <?= $filter_action === 'delete_user' ? 'selected' : '' ?>>Delete User</option>
                <option value="reset_streak" <?= $filter_action === 'reset_streak' ? 'selected' : '' ?>>Reset Streak</option>
                <option value="erase_pixel" <?= $filter_action === 'erase_pixel' ? 'selected' : '' ?>>Erase Pixel</option>
                <option value="canvas_reset" <?= $filter_action === 'canvas_reset' ? 'selected' : '' ?>>Canvas Reset</option>
                <option value="fill_unclaimed" <?= $filter_action === 'fill_unclaimed' ? 'selected' : '' ?>>Fill Unclaimed</option>
            </select>
            <button type="submit" class="btn-secondary btn-sm">Filter</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>Date</th></tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:30px;">No log entries found</td></tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="font-weight:500;"><?= htmlspecialchars($log['username']) ?></td>
                    <td><span style="font-size:12px;font-weight:600;color:var(--purple-bright);"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td><?= htmlspecialchars($log['target_type'] ?? '—') ?> #<?= $log['target_id'] ?? '—' ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($log['performed_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div style="display:flex;gap:8px;justify-content:center;margin-top:var(--space-lg);">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>&action_filter=<?= urlencode($filter_action) ?>" class="btn-secondary btn-sm" style="<?= $i === $page ? 'border-color:var(--purple-core);color:var(--purple-bright);' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
</main>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
