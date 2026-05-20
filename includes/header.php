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
    <script>var BASE_URL = '<?= BASE_URL ?>';</script>
</body>
</html>