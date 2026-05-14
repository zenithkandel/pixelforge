<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$pdo = get_db();
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute(["%$search%", "%$search%"]);
    $users = $stmt->fetchAll();
    $total = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ? OR email LIKE ?");
    $total->execute(["%$search%", "%$search%"]);
    $total = $total->fetchColumn();
} else {
    $users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();
    $total = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
}
$totalPages = ceil($total / $perPage);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $uid = intval($_POST['user_id']);
    if ($_POST['action'] === 'ban') {
        $pdo->prepare("UPDATE users SET is_banned = 1, ban_reason = ? WHERE id = ?")->execute([$_POST['reason'] ?? 'Admin ban', $uid]);
    } elseif ($_POST['action'] === 'unban') {
        $pdo->prepare("UPDATE users SET is_banned = 0, ban_reason = NULL WHERE id = ?")->execute([$uid]);
    } elseif ($_POST['action'] === 'credit') {
        $amt = intval($_POST['amount']);
        $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance + ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?")->execute([$amt, $amt, $uid]);
    }
    header("Location: /admin/users.php?page=$page" . ($search ? "&search=" . urlencode($search) : ""));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Users — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f7f7f8; margin: 0; }
        .admin-header { background: #111318; color: white; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .admin-header h1 { font-size: 18px; margin: 0; }
        .admin-nav a { color: #9ca3af; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .admin-nav a:hover { color: white; }
        .content { padding: 32px; max-width: 1400px; }
        .search-bar { margin-bottom: 24px; display: flex; gap: 8px; }
        .search-bar input { padding: 8px 12px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; flex: 1; }
        .search-bar button { padding: 8px 16px; background: #5b4fff; color: white; border: none; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; }
        th, td { padding: 12px; text-align: left; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        th { background: #f7f7f8; font-weight: 600; color: #6b7280; }
        tr:hover { background: #f9fafb; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-banned { background: #fef2f2; color: #991b1b; }
        .badge-active { background: #dcfce7; color: #166534; }
        .actions { display: flex; gap: 4px; }
        .actions button, .actions a { padding: 4px 8px; font-size: 11px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-ban { background: #fee2e2; color: #991b1b; }
        .btn-unban { background: #dcfce7; color: #166534; }
        .btn-credit { background: #dbeafe; color: #1e40af; }
        .pagination { display: flex; gap: 4px; margin-top: 16px; }
        .pagination a, .pagination span { padding: 6px 12px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 13px; }
        .pagination a { background: white; color: #111318; text-decoration: none; }
        .pagination span { background: #5b4fff; color: white; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .pxl { color: #f59e0b; font-weight: 600; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>PixelForge Admin</h1>
        <nav class="admin-nav">
            <a href="/admin/index.php">Dashboard</a>
            <a href="/admin/users.php">Users</a>
            <a href="/admin/grid.php">Grid</a>
            <a href="/admin/flagged.php">Flagged</a>
            <a href="/admin/index.php?logout=1">Logout</a>
        </nav>
    </div>
    <div class="content">
        <h2 style="margin-top:0">User Management</h2>
        <form class="search-bar" method="get">
            <input type="text" name="search" placeholder="Search by username or email..." value="<?= h($search) ?>" />
            <button type="submit">Search</button>
        </form>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>PXL Balance</th><th>Streak</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="mono"><?= $u['id'] ?></td>
                    <td><?= h($u['username']) ?></td>
                    <td><?= h($u['email']) ?></td>
                    <td class="pxl mono"><?= number_format($u['pxl_balance']) ?></td>
                    <td class="mono"><?= $u['login_streak'] ?></td>
                    <td>
                        <?php if ($u['is_banned']): ?>
                            <span class="badge badge-banned">Banned</span>
                        <?php else: ?>
                            <span class="badge badge-active">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                    <td class="actions">
                        <?php if ($u['is_banned']): ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="unban" />
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                                <button class="btn-unban">Unban</button>
                            </form>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="ban" />
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                                <input type="hidden" name="reason" value="Admin ban" />
                                <button class="btn-ban">Ban</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="action" value="credit" />
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                            <input type="number" name="amount" value="0" style="width:60px;padding:4px;font-size:11px" />
                            <button class="btn-credit">Credit PXL</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($p = 1; $p <= min($totalPages, 10); $p++): ?>
                <?php if ($p === $page): ?>
                    <span><?= $p ?></span>
                <?php else: ?>
                    <a href="?page=<?= $p ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>