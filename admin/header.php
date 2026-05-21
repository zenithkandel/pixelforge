<?php
require_once __DIR__ . '/../includes/xp.php';
if (!isset($page_title))
    $page_title = 'Admin — ' . APP_NAME;
$current_page = basename($_SERVER['PHP_SELF']);
$nav_user = isset($_SESSION['user_id']) ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> | Forge Panel</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --admin-sidebar-w: 280px;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: #050508;
        }

        .admin-sidebar {
            width: var(--admin-sidebar-w);
            background: #0a0a0f;
            border-right: 1px solid rgba(255, 255, 255, 0.03);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
        }

        .admin-brand {
            padding: 40px 30px;
            margin-bottom: 10px;
        }

        .admin-brand a {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 24px;
            font-family: var(--font-game);
            font-weight: 900;
            color: white;
            text-decoration: none;
            letter-spacing: -1px;
        }

        .admin-brand span {
            color: var(--purple-bright);
        }

        .admin-nav-group {
            padding: 0 16px;
            margin-bottom: 30px;
        }

        .admin-nav-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            color: var(--text-muted);
            padding: 0 15px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .admin-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .admin-nav a {
            padding: 14px 18px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-radius: 14px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-nav a i {
            font-size: 18px;
            width: 20px;
            text-align: center;
            opacity: 0.4;
            transition: opacity 0.2s;
        }

        .admin-nav a:hover:not(.active) {
            background: rgba(255, 255, 255, 0.03);
            color: white;
        }

        .admin-nav a:hover:not(.active) i {
            opacity: 0.8;
        }

        .admin-nav a.active {
            background: linear-gradient(135deg, var(--purple) 0%, #6d28d9 100%);
            color: white;
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.3);
        }

        .admin-nav a.active i {
            opacity: 1;
        }

        .admin-main {
            flex: 1;
            margin-left: var(--admin-sidebar-w);
            padding: 40px 60px;
            background: radial-gradient(circle at top right, rgba(124, 58, 237, 0.03), transparent 600px);
        }

        .admin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 50px;
        }

        .admin-title-wrap h1 {
            font-size: 36px;
            margin: 0;
            font-weight: 900;
            color: white;
        }

        .admin-title-wrap p {
            color: var(--text-muted);
            margin: 8px 0 0 0;
            font-size: 15px;
            font-weight: 500;
        }

        .admin-user-pills {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #0f0f15;
            padding: 8px 20px 8px 10px;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .admin-user-pills .avatar-mini {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--purple);
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        /* Stats Component */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: #11111a;
            padding: 30px;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.04);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 255, 255, 0.1);
            background: #151520;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .stat-icon {
            width: 54px;
            height: 54px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
        }

        .stat-card:nth-child(1) .stat-icon {
            color: var(--purple-bright);
            background: rgba(124, 58, 237, 0.1);
        }

        .stat-card:nth-child(2) .stat-icon {
            color: var(--green);
            background: rgba(16, 185, 129, 0.1);
        }

        .stat-card:nth-child(3) .stat-icon {
            color: var(--gold);
            background: rgba(251, 191, 36, 0.1);
        }

        .stat-card:nth-child(4) .stat-icon {
            color: var(--blue);
            background: rgba(59, 130, 246, 0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 900;
            color: white;
            display: block;
            line-height: 1;
            margin-bottom: 8px;
            font-family: var(--font-game);
        }

        .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-trend {
            position: absolute;
            top: 30px;
            right: 30px;
            font-size: 13px;
            font-weight: 800;
        }

        .trend-up {
            color: var(--green);
        }
    </style>
</head>

<body>

    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <a href="<?= BASE_URL ?>/">
                    PIXEL<span>FORGE</span>
                </a>
            </div>

            <div class="admin-nav-group">
                <div class="admin-nav-label">Core Management</div>
                <nav class="admin-nav">
                    <a href="<?= BASE_URL ?>/admin/index.php"
                        class="<?= $current_page == 'index.php' ? 'active' : '' ?>">
                        <i class="fas fa-th-large"></i> Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/admin/users.php"
                        class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-shield"></i> User Control
                    </a>
                    <a href="<?= BASE_URL ?>/admin/canvas.php"
                        class="<?= $current_page == 'canvas.php' ? 'active' : '' ?>">
                        <i class="fas fa-layer-group"></i> World State
                    </a>
                </nav>
            </div>

            <div class="admin-nav-group">
                <div class="admin-nav-label">Auditing & Security</div>
                <nav class="admin-nav">
                    <a href="<?= BASE_URL ?>/admin/logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">
                        <i class="fas fa-fingerprint"></i> Admin Activity
                    </a>
                    <a href="<?= BASE_URL ?>/includes/logger.php?view=1" target="_blank">
                        <i class="fas fa-dna"></i> System Logs
                    </a>
                </nav>
            </div>

            <div style="margin-top:auto; padding:30px;">
                <a href="<?= BASE_URL ?>/logout.php"
                    style="display:flex; align-items:center; gap:12px; color:rgba(239, 68, 68, 0.8); text-decoration:none; font-weight:700; font-size:14px; padding:15px; border-radius:15px; background:rgba(239, 68, 68, 0.05); transition: background 0.2s;">
                    <i class="fas fa-power-off"></i> Terminate Session
                </a>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-title-wrap">
                    <h1><?= $page_title ?></h1>
                    <p>Monitoring system status and user parity.</p>
                </div>
                <div class="admin-user-pills">
                    <div class="avatar-mini">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div style="font-size:14px; font-weight:700; color:white;">
                        <?= htmlspecialchars($_SESSION['username']) ?>
                    </div>
                </div>
            </header>

    </div>

    <script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
        (function () {
            var toggle = document.getElementById('admin-theme-toggle');
            function updateThemeIcon() {
                var theme = document.documentElement.getAttribute('data-theme');
                if (toggle) toggle.querySelector('.theme-icon').textContent = theme === 'light' ? '☀️' : '🌙';
            }
            function setTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                updateThemeIcon();
            }
            if (toggle) toggle.addEventListener('click', function () {
                var current = document.documentElement.getAttribute('data-theme');
                setTheme(current === 'dark' ? 'light' : 'dark');
            });

            var sidebarToggle = document.getElementById('sidebar-toggle');
            var sidebar = document.querySelector('.admin-sidebar');
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('open');
                });
            }

            updateThemeIcon();
        })();
    </script>