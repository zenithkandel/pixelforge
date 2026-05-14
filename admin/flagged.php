<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'validate') {
    $sessionId = $_POST['session_id'];
    $pdo->prepare("UPDATE game_sessions SET is_valid = 0, invalidation_reason = ? WHERE id = ?")->execute([$_POST['reason'], $sessionId]);
    header('Location: /admin/flagged.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'approve') {
    $sessionId = $_POST['session_id'];
    $pdo->prepare("UPDATE game_sessions SET is_valid = 1, invalidation_reason = NULL WHERE id = ?")->execute([$sessionId]);
    header('Location: /admin/flagged.php');
    exit;
}

$flagged = $pdo->query("
    SELECT gs.*, u.username, u.email,
           (SELECT username FROM users WHERE id = (
               SELECT user_id FROM game_sessions WHERE id = gs.id
           )) as player
    FROM game_sessions gs
    JOIN users u ON u.id = gs.user_id
    WHERE gs.is_valid = 0
    ORDER BY gs.started_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flagged Sessions — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f7f7f8; margin: 0; }
        .admin-header { background: #111318; color: white; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .admin-header h1 { font-size: 18px; margin: 0; }
        .admin-nav a { color: #9ca3af; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .admin-nav a:hover { color: white; }
        .content { padding: 32px; max-width: 1200px; }
        .card { background: white; border-radius: 10px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-weight: 600; background: #f7f7f8; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .btn-validate { background: #fee2e2; color: #991b1b; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .btn-approve { background: #dcfce7; color: #166534; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .reason { color: #ef4444; font-size: 12px; }
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
        <div class="card">
            <h3 style="margin-top:0">Flagged Game Sessions (<?= count($flagged) ?>)</h3>
            <?php if ($flagged): ?>
            <table>
                <thead><tr><th>ID</th><th>User</th><th>Score</th><th>Duration</th><th>IP</th><th>Reason</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($flagged as $g): ?>
                    <tr>
                        <td class="mono"><?= h(substr($g['id'], 0, 8)) ?>...</td>
                        <td><a href="/admin/users.php?search=<?= urlencode($g['username']) ?>"><?= h($g['username']) ?></a></td>
                        <td class="mono"><?= number_format($g['final_score'] ?? 0) ?></td>
                        <td class="mono"><?= $g['duration_seconds'] ?? 0 ?>s</td>
                        <td><?= h($g['ip_address']) ?></td>
                        <td><span class="reason"><?= h($g['invalidation_reason']) ?></span></td>
                        <td><?= date('M j, Y H:i', strtotime($g['started_at'])) ?></td>
                        <td>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="approve" />
                                <input type="hidden" name="session_id" value="<?= h($g['id']) ?>" />
                                <button class="btn-approve">Approve</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="color:#6b7280">No flagged sessions.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>