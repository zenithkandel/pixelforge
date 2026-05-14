<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

$admin_user = $_SESSION['admin_id'] ?? null;
if (!$admin_user) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
            header('Location: /admin/index.php');
            exit;
        }
        $error = 'Invalid credentials';
    }
    echo '<!DOCTYPE html><html><head><title>Admin Login</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">';
    echo '<style>body{font-family:Outfit,sans-serif;background:#f7f7f8;display:flex;align-items:center;justify-content:center;height:100vh;margin:0}.card{background:white;padding:40px;border-radius:12px;box-shadow:0 4px 6px rgba(0,0,0,0.08);width:360px}input{width:100%;padding:10px 12px;margin:8px 0 16px;border:1px solid #e5e7eb;border-radius:6px;box-sizing:border-box;font-size:14px}button{width:100%;padding:10px;background:#5b4fff;color:white;border:none;border-radius:6px;font-size:14px;font-weight:500;cursor:pointer}.error{color:#ef4444;font-size:13px;margin-bottom:8px}h2{margin:0 0 24px;text-align:center}</style></head><body>';
    echo '<div class="card"><h2>Admin Login</h2>';
    if (!empty($error)) echo "<p class='error'>$error</p>";
    echo '<form method="post"><input name="username" placeholder="Username" required><input name="password" type="password" placeholder="Password" required><button type="submit">Sign In</button></form></div></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard — PixelForge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/main.css" />
    <style>
        .admin-body { background: var(--bg-primary); }
        .admin-header { background: var(--bg-sidebar); color: white; padding: 16px 32px; display: flex; align-items: center; justify-content: space-between; }
        .admin-header h1 { font-size: 18px; margin: 0; }
        .admin-nav { display: flex; gap: 16px; }
        .admin-nav a { color: var(--text-sidebar-muted); text-decoration: none; font-size: 14px; }
        .admin-nav a:hover { color: white; }
        .admin-content { padding: 32px; }
        .admin-card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: var(--shadow-sm); }
        .admin-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-box { background: white; border: 1px solid var(--border-color); border-radius: 10px; padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
        .stat-label { font-size: 12px; color: var(--text-secondary); margin-top: 4px; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; font-size: 13px; border-bottom: 1px solid var(--border-color); }
        th { background: var(--bg-primary); font-weight: 600; color: var(--text-secondary); }
        tr:hover { background: var(--bg-primary); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fef2f2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body class="admin-body">
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
    <div class="admin-content">
        <?php
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: /admin/index.php');
            exit;
        }

        $pdo = get_db();

        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalPixels = $pdo->query("SELECT COUNT(*) FROM pixels")->fetchColumn();
        $totalGames = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE ended_at IS NOT NULL")->fetchColumn();
        $flaggedGames = $pdo->query("SELECT COUNT(*) FROM game_sessions WHERE is_valid = 0")->fetchColumn();
        ?>
        <div class="admin-stats">
            <div class="stat-box"><div class="stat-value"><?= number_format($totalUsers) ?></div><div class="stat-label">Total Users</div></div>
            <div class="stat-box"><div class="stat-value"><?= number_format($totalPixels) ?></div><div class="stat-label">Pixels Placed</div></div>
            <div class="stat-box"><div class="stat-value"><?= number_format($totalGames) ?></div><div class="stat-label">Games Played</div></div>
            <div class="stat-box"><div class="stat-value"><?= number_format($flaggedGames) ?></div><div class="stat-label">Flagged Sessions</div></div>
        </div>

        <div class="admin-card">
            <h3 style="margin:0 0 16px">Recent Invalid Game Sessions</h3>
            <?php
            $stmt = $pdo->query("SELECT gs.*, u.username, gs.invalidation_reason FROM game_sessions gs JOIN users u ON u.id = gs.user_id WHERE gs.is_valid = 0 ORDER BY gs.started_at DESC LIMIT 10");
            $flagged = $stmt->fetchAll();
            if ($flagged): ?>
            <div class="table-wrapper">
                <table>
                    <thead><tr><th>User</th><th>Score</th><th>Reason</th><th>IP</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($flagged as $g): ?>
                        <tr>
                            <td><?= h($g['username']) ?></td>
                            <td style="font-family:'JetBrains Mono'"><?= number_format($g['final_score'] ?? 0) ?></td>
                            <td><span class="badge badge-error"><?= h($g['invalidation_reason']) ?></span></td>
                            <td><?= h($g['ip_address']) ?></td>
                            <td><?= date('M j, Y', strtotime($g['started_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p style="color:var(--text-secondary)">No flagged sessions.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>