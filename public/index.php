<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /game.php');
    exit;
}

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= h($csrf_token) ?>" />
    <title>PixelForge — Where Pixels Come to Life</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/assets/css/main.css" />
</head>
<body class="landing-body">
    <div class="landing-wrapper">
        <header class="landing-header">
            <div class="landing-logo">
                <div class="brand-logo">PF</div>
                <span class="brand-name">PixelForge</span>
            </div>
            <nav class="landing-nav">
                <a href="/canvas.php" class="nav-link">The Forge</a>
                <a href="/leaderboard.php" class="nav-link">Leaderboard</a>
            </nav>
        </header>

        <main class="landing-main">
            <div class="landing-hero">
                <div class="hero-badge">Community Pixel Canvas</div>
                <h1 class="hero-title">Paint the World,<br /><span class="hero-accent">One Pixel at a Time</span></h1>
                <p class="hero-subtitle">
                    Earn <span class="pxl-text">PXL</span> by playing <strong>PIXEL DASH</strong>, then spend them painting
                    the communal 800×800 canvas. Collaborate, compete, and create.
                </p>
                <div class="hero-actions">
                    <a href="/game.php" class="btn btn-primary btn-lg">Play Pixel Dash</a>
                    <a href="/canvas.php" class="btn btn-secondary btn-lg">View The Forge</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-value mono">800×800</span>
                        <span class="stat-label">Canvas Size</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-value mono">1 PXL</span>
                        <span class="stat-label">Per Pixel</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-value mono">200:1</span>
                        <span class="stat-label">Score to PXL</span>
                    </div>
                </div>
            </div>

            <div class="auth-card" id="login-card">
                <div class="auth-tabs">
                    <button class="auth-tab active" data-tab="login">Sign In</button>
                    <button class="auth-tab" data-tab="register">Create Account</button>
                </div>

                <form id="login-form" class="auth-form" data-tab="login">
                    <div class="form-group">
                        <label for="login-username">Username or Email</label>
                        <input type="text" id="login-username" name="username" autocomplete="username" required />
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" autocomplete="current-password" required />
                    </div>
                    <div class="form-row form-row-between">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" /> Remember me
                        </label>
                        <a href="#forgot" class="link" id="show-forgot">Forgot password?</a>
                    </div>
                    <div class="form-error" id="login-error" hidden></div>
                    <button type="submit" class="btn btn-primary btn-full" id="login-btn">Sign In</button>
                </form>

                <form id="register-form" class="auth-form" data-tab="register" hidden>
                    <div class="form-group">
                        <label for="reg-username">Username</label>
                        <input type="text" id="reg-username" name="username" minlength="3" maxlength="20" autocomplete="username" required />
                        <span class="form-hint">3–20 characters, letters and numbers only</span>
                    </div>
                    <div class="form-group">
                        <label for="reg-email">Email</label>
                        <input type="email" id="reg-email" name="email" autocomplete="email" required />
                    </div>
                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <input type="password" id="reg-password" name="password" minlength="8" autocomplete="new-password" required />
                        <span class="form-hint">Minimum 8 characters</span>
                    </div>
                    <div class="form-group">
                        <label for="reg-confirm">Confirm Password</label>
                        <input type="password" id="reg-confirm" name="password_confirm" autocomplete="new-password" required />
                    </div>
                    <div class="form-error" id="register-error" hidden></div>
                    <button type="submit" class="btn btn-primary btn-full" id="register-btn">Create Account</button>
                </form>

                <form id="forgot-form" class="auth-form" hidden>
                    <div class="form-group">
                        <label for="forgot-email">Email</label>
                        <input type="email" id="forgot-email" name="email" autocomplete="email" required />
                    </div>
                    <div class="form-error" id="forgot-error" hidden></div>
                    <div class="form-success" id="forgot-success" hidden></div>
                    <button type="submit" class="btn btn-primary btn-full" id="forgot-btn">Send Reset Link</button>
                    <a href="#back" class="link" id="back-to-login">Back to sign in</a>
                </form>
            </div>
        </main>

        <footer class="landing-footer">
            <p>&copy; <?= date('Y') ?> PixelForge. Play hard, paint bigger.</p>
        </footer>
    </div>

    <script type="module">
        import { initAuth } from '/assets/js/auth.js';
        initAuth();
    </script>
</body>
</html>