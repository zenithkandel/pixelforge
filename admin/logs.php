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
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$action_options = [
    'SET_BALANCE' => 'Set Balance',
    'BAN_USER' => 'Ban User',
    'DELETE_USER' => 'Delete User',
    'PIXEL_PLACE' => 'Pixel Impact',
    'XP_GAIN' => 'XP Reward'
];

try {
    $search_q = $filter_action ? "WHERE action = ?" : "";
    $sql = "SELECT a.*, u.username FROM admin_log a JOIN users u ON a.admin_id = u.id $search_q ORDER BY performed_at DESC LIMIT " . (int) $per_page . " OFFSET " . (int) $offset;
    $stmt = $db->prepare($sql);
    if ($filter_action)
        $stmt->execute([$filter_action]);
    else
        $stmt->execute();
    $logs = $stmt->fetchAll();

    $stmt_count = $db->prepare("SELECT COUNT(*) FROM admin_log " . ($filter_action ? "WHERE action = ?" : ""));
    if ($filter_action)
        $stmt_count->execute([$filter_action]);
    else
        $stmt_count->execute();
    $total = $stmt_count->fetchColumn();
    $total_pages = ceil($total / $per_page);
} catch (PDOException $e) {
    die("Audit stream offline.");
}

$page_title = 'Audit Logs';
require_once __DIR__ . '/header.php';
?>

<div class="section-card">
    <div class="section-header">
        <h2 class="section-title">Operational Audit Stream</h2>
        <form method="GET" style="display:flex; gap:10px;">
            <select name="action_filter" class="input-pixel" style="background:#050508; width: 200px;">
                <option value="">All Vectors</option>
                <?php foreach ($action_options as $k => $v): ?>
                    <option value="<?= $k ?>" <?= $filter_action == $k ? 'selected' : '' ?>><?= $v ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-pixel">Search</button>
        </form>
    </div>

    <div class="pro-table-wrapper">
        <table class="pro-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Operator</th>
                    <th>Intercepted Vector</th>
                    <th>Subject</th>
                    <th>Raw Trace</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td style="color:var(--text-muted); font-family: monospace; font-size: 13px;">
                            <?= $log['performed_at'] ?>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div
                                    style="width:30px; height:30px; border-radius:8px; background:var(--purple); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:900;">
                                    <?= strtoupper(substr($log['username'], 0, 1)) ?>
                                </div>
                                <span style="font-weight:700; color:white;"><?= htmlspecialchars($log['username']) ?></span>
                            </div>
                        </td>
                        <td><span class="tag-xp" style="padding: 4px 12px;"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td>
                            <span style="font-weight: 700; color:var(--text-secondary);">
                                <?= $log['target_type'] ?>:<?= $log['target_id'] ?>
                            </span>
                        </td>
                        <td>
                            <div
                                style="background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); padding:8px 15px; border-radius:8px; font-size:12px; color:var(--text-secondary);">
                                <?= htmlspecialchars($log['details']) ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:var(--space-sm);margin-top:var(--space-lg);">
        <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>&action_filter=<?= urlencode($filter_action) ?>"
                class="btn-secondary btn-sm">Previous</a>
        <?php endif; ?>

        <div style="display:flex;gap:var(--space-xs);">
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            if ($start_page > 1) {
                echo '<a href="?page=1&action_filter=' . urlencode($filter_action) . '" class="btn-secondary btn-sm">1</a>';
                if ($start_page > 2)
                    echo '<span style="color:var(--text-muted);padding:0 4px;">...</span>';
            }
            for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <a href="?page=<?= $i ?>&action_filter=<?= urlencode($filter_action) ?>" class="btn-secondary btn-sm"
                    style="<?= $i === $page ? 'border-color:var(--purple-core);color:var(--purple-bright);' : '' ?>">
                    <?= $i ?>
                </a>
                <?php
            endfor;
            if ($end_page < $total_pages) {
                if ($end_page < $total_pages - 1)
                    echo '<span style="color:var(--text-muted);padding:0 4px;">...</span>';
                echo '<a href="?page=' . $total_pages . '&action_filter=' . urlencode($filter_action) . '" class="btn-secondary btn-sm">' . $total_pages . '</a>';
            }
            ?>
        </div>

        <?php if ($page < $total_pages): ?>
            <a href="?page=<?= $page + 1 ?>&action_filter=<?= urlencode($filter_action) ?>"
                class="btn-secondary btn-sm">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>