<?php
require_once __DIR__ . '/../includes/xp.php';
if (!isset($page_title)) $page_title = 'Admin — ' . APP_NAME;
$current_page = basename($_SERVER['PHP_SELF']);
$nav_user = isset($_SESSION['user_id']) ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
    (function() {
        var stored = localStorage.getItem('theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = stored || (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <style>
        :root {
            --admin-sidebar-width: 240px;
            --admin-header-height: 60px;
        }

        .admin-layout {
            display: flex;
            min-height: 100vh;
            background: var(--bg-base);
        }

        .admin-sidebar {
            width: 240px;
            background: #0f0f1a;
            border-right: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
        }

        .admin-brand {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .admin-brand a {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #a78bfa;
            text-decoration: none;
        }

        .admin-nav-section {
            padding: 16px 0;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .admin-nav-section:last-of-type {
            border-bottom: none;
        }

        .admin-nav-label {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #50506a;
            padding: 0 20px;
            margin-bottom: 8px;
        }

        .admin-nav {
            display: flex;
            flex-direction: column;
            gap: 2px;
            padding: 0 12px;
        }

        .admin-nav a {
            padding: 10px 16px;
            color: #9090b0;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: #f0f0ff;
        }

        .admin-nav a.active {
            background: #161625;
            color: #a78bfa;
            border-left: 3px solid #7c3aed;
        }

        .admin-nav a .nav-icon {
            font-size: 16px;
            width: 20px;
            text-align: left;
            flex-shrink: 0;
        }

        .admin-sidebar-footer {
            margin-top: auto;
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.06);
            background: #0f0f1a;
        }

        .admin-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            background: #161625;
        }

        .admin-user-info .avatar-circle {
            width: 32px;
            height: 32px;
            font-size: 13px;
            flex-shrink: 0;
        }

        .admin-user-info .user-details {
            flex: 1;
            min-width: 0;
        }

        .admin-user-info .username {
            font-size: 13px;
            font-weight: 600;
            color: #f0f0ff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .admin-user-info .role {
            font-size: 11px;
            color: #50506a;
        }

        .admin-main {
            flex: 1;
            margin-left: 240px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #080810;
        }

        .admin-header {
            height: 60px;
            background: #0f0f1a;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(12px);
        }

        .admin-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-page-title {
            font-size: 18px;
            font-weight: 600;
            color: #f0f0ff;
            margin: 0;
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .admin-content {
            padding: 40px;
        }

        .admin-section {
            margin-bottom: var(--space-xl);
        }

        .admin-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-md);
        }

        .admin-section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .admin-breadcrumb {
            font-size: 13px;
            color: var(--text-muted);
        }

        .admin-breadcrumb a {
            color: var(--text-secondary);
        }

        .admin-breadcrumb a:hover {
            color: var(--purple-bright);
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
            .admin-content {
                padding: var(--space-md);
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <a href="<?= BASE_URL ?>/">
                    <span style="font-size:20px;">🎨</span>
                    <span><?= APP_NAME ?></span>
                </a>
            </div>

            <div class="admin-nav-section">
                <div class="admin-nav-label">Dashboard</div>
                <nav class="admin-nav">
                    <a href="<?= BASE_URL ?>/admin/" class="<?= $current_page === 'index.php' ? 'active' : '' ?>">
                        <span class="nav-icon">📊</span> Overview
                    </a>
                    <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
                        <span class="nav-icon">👥</span> Users
                    </a>
                    <a href="<?= BASE_URL ?>/admin/canvas.php" class="<?= $current_page === 'canvas.php' ? 'active' : '' ?>">
                        <span class="nav-icon">🎨</span> Canvas
                    </a>
                    <a href="<?= BASE_URL ?>/admin/logs.php" class="<?= $current_page === 'logs.php' ? 'active' : '' ?>">
                        <span class="nav-icon">📋</span> Logs
                    </a>
                </nav>
            </div>

            <div class="admin-nav-section">
                <div class="admin-nav-label">Quick Links</div>
                <nav class="admin-nav">
                    <a href="<?= BASE_URL ?>/">
                        <span class="nav-icon">🌐</span> Main Site
                    </a>
                    <a href="<?= BASE_URL ?>/game.php">
                        <span class="nav-icon">🎮</span> Game
                    </a>
                    <a href="<?= BASE_URL ?>/leaderboard.php">
                        <span class="nav-icon">🏆</span> Leaderboard
                    </a>
                </nav>
            </div>

            <?php if ($nav_user): ?>
            <div class="admin-sidebar-footer">
                <div class="admin-user-info">
                    <span class="avatar-circle" style="background:<?= htmlspecialchars($nav_user['avatar_color']) ?>"><?= strtoupper(substr($nav_user['username'], 0, 1)) ?></span>
                    <div class="user-details">
                        <div class="username"><?= htmlspecialchars($nav_user['username']) ?></div>
                        <div class="role">Admin</div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-secondary btn-sm" style="margin-top:var(--space-sm);width:100%;justify-content:center;">Logout</a>
            </div>
            <?php endif; ?>
        </aside>

        <main class="admin-main">
            <header class="admin-header">
                <div class="admin-header-left">
                    <button class="btn-icon mobile-menu-toggle" id="sidebar-toggle" style="display:none;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 12h18M3 6h18M3 18h18"/>
                        </svg>
                    </button>
                    <h1 class="admin-page-title"><?= htmlspecialchars($page_title) ?></h1>
                </div>
                <div class="admin-header-right">
                    <button class="theme-toggle" id="admin-theme-toggle" title="Toggle theme">
                        <span class="theme-icon">🌙</span>
                    </button>
                </div>
            </header>

            <div class="admin-content">
</div>

<script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
(function() {
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
    if (toggle) toggle.addEventListener('click', function() {
        var current = document.documentElement.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark');
    });

    var sidebarToggle = document.getElementById('sidebar-toggle');
    var sidebar = document.querySelector('.admin-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    updateThemeIcon();
})();
</script>