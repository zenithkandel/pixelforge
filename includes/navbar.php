<?php
$user = get_logged_in_user();
$is_logged_in = is_array($user) && isset($user['id']);
$xp_progress_val = $is_logged_in ? xp_progress($user['xp']) : 0;
?>
<nav class="navbar">
    <div class="nav-left">
        <a href="<?php echo APP_URL; ?>/index.php" class="logo">
            <span class="logo-icon">🎮</span>
            <span class="logo-text"><?php echo APP_NAME; ?></span>
        </a>
    </div>

    <div class="nav-center">
        <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">Home Canvas</a>
        <a href="<?php echo APP_URL; ?>/game.php" class="nav-link">Play Game</a>
        <a href="<?php echo APP_URL; ?>/leaderboard.php" class="nav-link">Leaderboard</a>
    </div>

    <div class="nav-right">
        <?php if ($is_logged_in): ?>
        <div class="xp-bar-container">
            <div class="xp-bar" style="width: <?php echo ($xp_progress_val * 100); ?>%"></div>
        </div>
        <span class="level-badge">Lv.<?php echo htmlspecialchars($user['level']); ?></span>
        <span class="balance">💰<?php echo htmlspecialchars($user['balance']); ?></span>
        <a href="<?php echo APP_URL; ?>/profile.php?user=<?php echo urlencode($user['username']); ?>" class="username">
            <?php echo htmlspecialchars($user['username']); ?>
        </a>
        <a href="<?php echo APP_URL; ?>/logout.php" class="logout-btn">Logout</a>
        <?php else: ?>
        <a href="<?php echo APP_URL; ?>/login.php" class="nav-btn">Login</a>
        <a href="<?php echo APP_URL; ?>/register.php" class="nav-btn primary">Register</a>
        <?php endif; ?>
    </div>
</nav>