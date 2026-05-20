<?php
require_once __DIR__ . '/../includes/xp.php';
if (!isset($page_title)) $page_title = 'Admin — ' . APP_NAME;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .admin-layout { display: flex; min-height: calc(100vh - 60px); }
        .admin-sidebar {
            width: 220px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            padding: 20px 0;
            flex-shrink: 0;
        }
        .admin-sidebar h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-muted);
            padding: 0 16px 12px;
        }
        .admin-sidebar nav {
            display: flex;
            flex-direction: column;
        }
        .admin-sidebar a {
            padding: 10px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-sidebar a:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .admin-sidebar a.active { background: var(--purple-dim); color: var(--purple-bright); }
        .admin-content { flex: 1; padding: 32px; overflow-x: auto; }
    </style>
</head>
<body>
    <nav>
        <a href="<?= BASE_URL ?>/" class="nav-brand">🎨 <?= APP_NAME ?></a>
        <div class="nav-center">
            <a href="<?= BASE_URL ?>/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Canvas</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= BASE_URL ?>/game.php">Game</a>
                <a href="<?= BASE_URL ?>/canvas.php">Draw</a>
                <a href="<?= BASE_URL ?>/leaderboard.php">Scores</a>
            <?php endif; ?>
        </div>
        <div class="nav-right">
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                $nav_user = current_user();
                if ($nav_user):
                    $nav_level = (int)$nav_user['level'];
                    $nav_xp = (int)$nav_user['xp'];
                    $nav_balance = (int)$nav_user['balance'];
                    $nav_progress = xp_progress_in_level($nav_xp);
                    $nav_initial = strtoupper(substr($nav_user['username'], 0, 1));
                ?>
                <div class="xp-bar-wrap">
                    <div class="xp-bar-fill" style="width: <?= round($nav_progress * 100) ?>%"></div>
                </div>
                <span class="level-badge">Lv<?= $nav_level ?></span>
                <span class="currency"><?= number_format($nav_balance) ?> 💰</span>
                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($nav_user['username']) ?>" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:var(--text-primary);">
                    <span class="avatar-circle" style="background:<?= htmlspecialchars($nav_user['avatar_color']) ?>"><?= $nav_initial ?></span>
                </a>
                <?php if ($nav_user['role'] === 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/" class="level-badge" style="text-decoration:none;">Admin</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-secondary btn-sm">Logout</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn-secondary btn-sm">Login</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn-primary btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </nav>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <h3>Admin</h3>
            <nav>
                <a href="<?= BASE_URL ?>/admin/" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">📊 Dashboard</a>
                <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">👥 Users</a>
                <a href="<?= BASE_URL ?>/admin/canvas.php" class="<?= $current_page === 'canvas.php' ? 'active' : '' ?>">🎨 Canvas</a>
                <a href="<?= BASE_URL ?>/admin/logs.php" class="<?= $current_page === 'logs.php' ? 'active' : '' ?>">📋 Logs</a>
            </nav>
        </aside>
        <main class="admin-content">
</main>
</div>