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
$per_page = 25;
$offset = ($page - 1) * $per_page;

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

$action_options = [
    'set_balance' => 'Set Balance',
    'adjust_balance' => 'Adjust Balance',
    'change_role' => 'Change Role',
    'delete_user' => 'Delete User',
    'reset_streak' => 'Reset Streak',
    'erase_pixel' => 'Erase Pixel',
    'canvas_reset' => 'Canvas Reset',
    'fill_unclaimed' => 'Fill Unclaimed',
];

$page_title = 'Logs';
require_once __DIR__ . '/header.php';
?>

<div class="admin-section">
    <div class="admin-section-header">
        <div>
            <h2 class="admin-section-title">Activity Logs</h2>
            <p style="color:var(--text-muted);margin:var(--space-xs) 0 0;font-size:14px;"><?= number_format($total) ?> actions recorded</p>
        </div>
        <form method="GET" style="display:flex;gap:var(--space-sm);">
            <select name="action_filter" style="width:180px;">
                <option value="">All Actions</option>
                <?php foreach ($action_options as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filter_action === $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Filter</button>
            <?php if ($filter_action): ?>
                <a href="logs.php" class="btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th style="width:140px;">Timestamp</th>
                    <th style="width:120px;">Admin</th>
                    <th style="width:140px;">Action</th>
                    <th style="width:120px;">Target</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:var(--text-muted);padding:var(--space-xl);">
                        No log entries found
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="font-size:12px;color:var(--text-muted);font-family:var(--font-mono);">
                            <?= date('M j, Y H:i', strtotime($log['performed_at'])) ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:var(--space-sm);">
                                <span class="avatar-circle sm" style="background:var(--purple-core);font-size:10px;">
                                    <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                </span>
                                <span style="font-weight:500;font-size:13px;"><?= htmlspecialchars($log['username']) ?></span>
                            </div>
                        </td>
                        <td>
                            <?php
                            $action_badge = '';
                            $action_class = 'badge-purple';
                            if (in_array($log['action'], ['delete_user', 'canvas_reset'])) {
                                $action_class = 'badge-red';
                            } elseif (in_array($log['action'], ['set_balance', 'adjust_balance'])) {
                                $action_class = 'badge-gold';
                            } elseif (in_array($log['action'], ['erase_pixel', 'fill_unclaimed'])) {
                                $action_class = 'badge-green';
                            }
                            ?>
                            <span class="badge <?= $action_class ?>"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td style="font-size:13px;color:var(--text-muted);">
                            <?= htmlspecialchars($log['target_type'] ?? '—') ?>
                            <?= $log['target_id'] ? ' #' . (int)$log['target_id'] : '' ?>
                        </td>
                        <td style="font-size:13px;color:var(--text-secondary);max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['details'] ?? '') ?>">
                            <?= htmlspecialchars($log['details'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<div style="display:flex;justify-content:center;align-items:center;gap:var(--space-sm);margin-top:var(--space-lg);">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($filter_action) ?>" class="btn-secondary btn-sm">Previous</a>
    <?php endif; ?>

    <div style="display:flex;gap:var(--space-xs);">
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        if ($start_page > 1) {
            echo '<a href="?page=1&action_filter=' . urlencode($filter_action) . '" class="btn-secondary btn-sm">1</a>';
            if ($start_page > 2) echo '<span style="color:var(--text-muted);padding:0 4px;">...</span>';
        }
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <a href="?page=<?= $i ?>&action_filter=<?= urlencode($filter_action) ?>" class="btn-secondary btn-sm" style="<?= $i === $page ? 'border-color:var(--purple-core);color:var(--purple-bright);' : '' ?>">
                <?= $i ?>
            </a>
        <?php
        endfor;
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) echo '<span style="color:var(--text-muted);padding:0 4px;">...</span>';
            echo '<a href="?page=' . $total_pages . '&action_filter=' . urlencode($filter_action) . '" class="btn-secondary btn-sm">' . $total_pages . '</a>';
        }
        ?>
    </div>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($filter_action) ?>" class="btn-secondary btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>