<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$pdo = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'reset') {
    $stmt = $pdo->query("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1");
    $current = $stmt->fetch();
    $pdo->exec("SET autocommit = 0");
    $pdo->exec("START TRANSACTION");
    $pdo->prepare("INSERT INTO grid_sessions (is_current) VALUES (0)")->execute();
    $newId = $pdo->lastInsertId();
    $pdo->prepare("UPDATE grid_sessions SET ended_at = NOW(), is_current = 0 WHERE id = ?")->execute([$current['id']]);
    $pdo->prepare("UPDATE chunks SET version = version + 1")->execute();
    $pdo->exec("COMMIT");
    $pdo->exec("SET autocommit = 1");
    $redis = get_redis();
    for ($cx = 0; $cx < 32; $cx++) {
        for ($cy = 0; $cy < 32; $cy++) {
            $redis->del("chunk:{$cx}:{$cy}");
            $redis->incr("chunk_v:{$cx}:{$cy}");
        }
    }
    $redis->publish('sse_channel', json_encode(['type' => 'grid_reset', 'message' => 'The Forge has been reset!']));
    header('Location: /admin/grid.php');
    exit;
}

$sessions = $pdo->query("SELECT * FROM grid_sessions ORDER BY started_at DESC LIMIT 10")->fetchAll();
$stats = $pdo->query("
    SELECT gs.id, gs.started_at, gs.total_pixels_painted, gs.unique_painters,
           (SELECT COUNT(*) FROM pixels WHERE grid_session_id = gs.id) as actual_pixels
    FROM grid_sessions gs ORDER BY gs.started_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Grid — Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f7f7f8; margin: 0; }
        .admin-header { background: #111318; color: white; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .admin-header h1 { font-size: 18px; margin: 0; }
        .admin-nav a { color: #9ca3af; text-decoration: none; margin-left: 16px; font-size: 14px; }
        .admin-nav a:hover { color: white; }
        .content { padding: 32px; max-width: 900px; }
        .card { background: white; border-radius: 10px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
        .card h3 { margin: 0 0 16px; }
        .danger { background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .danger:hover { background: #dc2626; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; font-weight: 600; }
        .mono { font-family: 'JetBrains Mono', monospace; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #166534; }
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
            <h3>Grid Control</h3>
            <form method="post" onsubmit="return confirm('Are you sure you want to reset the grid? All pixels will be cleared.')">
                <input type="hidden" name="action" value="reset" />
                <p style="color:#6b7280;margin-bottom:16px">Reset the grid. This clears all pixels and starts a new grid session. Current session data is archived.</p>
                <button type="submit" class="danger">Reset Grid Now</button>
            </form>
        </div>

        <div class="card">
            <h3>Recent Grid Sessions</h3>
            <table>
                <thead><tr><th>ID</th><th>Started</th><th>Ended</th><th>Pixels</th><th>Painters</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($stats as $s): ?>
                    <tr>
                        <td class="mono"><?= $s['id'] ?></td>
                        <td><?= date('M j, Y H:i', strtotime($s['started_at'])) ?></td>
                        <td><?= $s['ended_at'] ? date('M j, Y H:i', strtotime($s['ended_at'])) : '-' ?></td>
                        <td class="mono"><?= number_format($s['actual_pixels']) ?></td>
                        <td class="mono"><?= $s['unique_painters'] ?></td>
                        <td><?= !$s['ended_at'] ? '<span class="badge">Current</span>' : 'Archived' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>