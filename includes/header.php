<?php
require_once __DIR__ . '/xp.php';
if (!isset($page_title)) $page_title = APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= htmlspecialchars($extra_css, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
    (function() {
        var stored = localStorage.getItem('theme');
        var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = stored || (prefersDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
</head>
<body>
    <nav>
        <a href="<?= BASE_URL ?>/" class="nav-brand">🎨 <?= APP_NAME ?></a>
        <div class="nav-center">
            <a href="<?= BASE_URL ?>/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">Canvas</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?= BASE_URL ?>/game.php" class="<?= basename($_SERVER['PHP_SELF']) === 'game.php' ? 'active' : '' ?>">Game</a>
                <a href="<?= BASE_URL ?>/canvas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'canvas.php' ? 'active' : '' ?>">Draw</a>
                <a href="<?= BASE_URL ?>/leaderboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'leaderboard.php' ? 'active' : '' ?>">Scores</a>
            <?php endif; ?>
        </div>
        <div class="nav-right nav-right-desktop">
            <button class="theme-toggle" id="theme-toggle" title="Toggle theme">
                <span class="theme-icon">🌙</span>
            </button>
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
        <button class="hamburger" id="mobile-menu-toggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </nav>

    <div class="mobile-menu" id="mobile-menu">
        <button class="theme-toggle" id="mobile-theme-toggle" style="margin-bottom: 8px; width: 100%; justify-content: center;">
            <span class="theme-icon">🌙</span>
        </button>
        <a href="<?= BASE_URL ?>/">Canvas</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="<?= BASE_URL ?>/game.php">Game</a>
            <a href="<?= BASE_URL ?>/canvas.php">Draw</a>
            <a href="<?= BASE_URL ?>/leaderboard.php">Scores</a>
            <?php if (isset($nav_user)): ?>
                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($nav_user['username']) ?>">Profile</a>
                <?php if ($nav_user['role'] === 'admin'): ?>
                    <a href="<?= BASE_URL ?>/admin/">Admin</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/logout.php" style="color: var(--red);">Logout</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/login.php">Login</a>
            <a href="<?= BASE_URL ?>/register.php">Register</a>
        <?php endif; ?>
    </div>

    <script nonce="<?= $GLOBALS['csp_nonce'] ?? '' ?>">
    (function() {
        var toggle = document.getElementById('theme-toggle');
        var mobileToggle = document.getElementById('mobile-theme-toggle');
        var mobileMenu = document.getElementById('mobile-menu');
        var mobileMenuToggle = document.getElementById('mobile-menu-toggle');

        function updateThemeIcon() {
            var theme = document.documentElement.getAttribute('data-theme');
            var icon = theme === 'light' ? '☀️' : '🌙';
            if (toggle) toggle.querySelector('.theme-icon').textContent = icon;
            if (mobileToggle) mobileToggle.querySelector('.theme-icon').textContent = icon;
        }

        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateThemeIcon();
        }

        function toggleTheme() {
            var current = document.documentElement.getAttribute('data-theme');
            setTheme(current === 'dark' ? 'light' : 'dark');
        }

        if (toggle) toggle.addEventListener('click', toggleTheme);
        if (mobileToggle) mobileToggle.addEventListener('click', toggleTheme);

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('open');
            });
        }

        document.addEventListener('click', function(e) {
            if (mobileMenu && !mobileMenu.contains(e.target) && !mobileMenuToggle.contains(e.target)) {
                mobileMenu.classList.remove('open');
            }
        });

        updateThemeIcon();
    })();
    </script>
