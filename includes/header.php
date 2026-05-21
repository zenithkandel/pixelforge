<?php
require_once __DIR__ . '/xp.php';
require_once __DIR__ . '/auth.php';
if (!isset($page_title))
    $page_title = APP_NAME;
$nav_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') ?> — <?= APP_NAME ?></title>
    <script src="https://zenithkandel.com.np/fontawesome/zenith-icons.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if (isset($extra_css)): ?>
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/<?= htmlspecialchars($extra_css, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>

<body data-theme="dark">
    <div class="app-container">
        <header class="app-header">
            <div class="user-summary">
                <?php if ($nav_user):
                    $nav_level = (int) $nav_user['level'];
                    $nav_xp = (int) $nav_user['xp'];
                    $nav_balance = (int) $nav_user['balance'];
                    $nav_progress = xp_progress_in_level($nav_xp);
                    $nav_initial = strtoupper(substr($nav_user['username'], 0, 1));
                    ?>
                    <div class="avatar-mini" style="background:<?= htmlspecialchars($nav_user['avatar_color']) ?>">
                        <?= $nav_initial ?>
                    </div>
                    <div class="user-stats-minimal">
                        <span class="name"><?= htmlspecialchars($nav_user['username']) ?></span>
                        <div class="xp-progress-compact" title="Level <?= $nav_level ?> - <?= number_format($nav_xp) ?> XP">
                            <div class="xp-bar-compact" style="width: <?= round($nav_progress * 100) ?>%"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/" class="nav-brand"
                        style="color:var(--purple-bright); font-weight:800; text-decoration:none;">PIXEL FLAP</a>
                <?php endif; ?>
            </div>

            <div class="resource-bar">
                <?php if ($nav_user): ?>
                    <div class="resource-item gold" title="Credits">
                        <i class="fad fa-thin fa-coins"></i>
                        <span class="currency" id="nav-currency"><?= number_format($nav_balance) ?></span>
                    </div>
                    <div class="resource-item xp" title="Level">
                        <i class="fad fa-thin fa-star"></i>
                        <span>Lv <?= $nav_level ?></span>
                    </div>
                    <div class="resource-item streak" title="Streak">
                        <i class="fad fa-thin fa-fire"></i>
                        <span><?= (int) $nav_user['streak_days'] ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="header-actions" style="display:flex; gap:12px; align-items:center;">
                <button class="btn-icon"
                    style="background:none; border:none; color:var(--text-secondary); font-size:18px; cursor:pointer;"><i
                        class="fad fa-thin fa-bell"></i></button>
                <button class="btn-icon"
                    style="background:none; border:none; color:var(--text-secondary); font-size:18px; cursor:pointer;"><i
                        class="fad fa-thin fa-envelope"></i></button>
                <button class="btn-icon" id="theme-toggle"
                    style="background:none; border:none; color:var(--text-secondary); font-size:18px; cursor:pointer;"><i
                        class="fad fa-thin fa-moon"></i></button>
            </div>
        </header>

        <aside class="app-sidebar">
            <a href="<?= BASE_URL ?>/index.php"
                class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                <i class="fad fa-thin fa-home"></i>
                <span>Home</span>
            </a>
            <?php if ($nav_user): ?>
                <a href="<?= BASE_URL ?>/game.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'game.php' ? 'active' : '' ?>">
                    <i class="fad fa-thin fa-gamepad"></i>
                    <span>Play Game</span>
                </a>
                <a href="<?= BASE_URL ?>/canvas.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'canvas.php' ? 'active' : '' ?>">
                    <i class="fad fa-thin fa-palette"></i>
                    <span>Pixel Canvas</span>
                </a>
                <a href="<?= BASE_URL ?>/leaderboard.php"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'leaderboard.php' ? 'active' : '' ?>">
                    <i class="fad fa-thin fa-trophy"></i>
                    <span>Leaderboard</span>
                </a>
                <a href="<?= BASE_URL ?>/profile.php?user=<?= urlencode($nav_user['username']) ?>"
                    class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                    <i class="fad fa-thin fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="nav-link">
                    <i class="fad fa-thin fa-sign-in-alt"></i>
                    <span>Login</span>
                </a>
                <a href="<?= BASE_URL ?>/register.php" class="nav-link">
                    <i class="fad fa-thin fa-user-plus"></i>
                    <span>Register</span>
                </a>
            <?php endif; ?>

            <div style="margin-top:auto; padding-top:16px; border-top:1px solid var(--border-dim);">
                <?php if ($nav_user): ?>
                    <?php if ($nav_user['role'] === 'admin'): ?>
                        <a href="<?= BASE_URL ?>/admin/" class="nav-link">
                            <i class="fad fa-thin fa-user-shield"></i>
                            <span>Admin</span>
                        </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/logout.php" class="nav-link" style="color:var(--red);">
                        <i class="fad fa-thin fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                <?php endif; ?>
            </div>
        </aside>

        <main class="app-main">