<?php session_start(); ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelForge - Pixel Canvas & Arcade Game</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="app-container">
        <nav class="sidebar">
            <div class="sidebar-brand">
                <h1>🎮 PixelForge</h1>
                <p class="tagline">Paint. Play. Create.</p>
            </div>
            <ul class="nav-items">
                <li><a href="/canvas.php">The Forge</a></li>
                <li><a href="/game.php">Pixel Dash</a></li>
                <li><a href="/leaderboard.php">Leaderboard</a></li>
                <li id="nav-profile" style="display:none;"><a href="/profile.php">Profile</a></li>
            </ul>
            <div class="sidebar-footer" id="user-info" style="display:none;">
                <div class="pxl-balance"><span id="pxl-display">0</span> PXL</div>
                <div class="username" id="username-display">-</div>
                <button id="logout-btn" class="btn btn-sm btn-secondary">Logout</button>
            </div>
        </nav>

        <main class="main-content">
            <header class="header">
                <h2 id="page-title">PixelForge</h2>
            </header>

            <div class="content" id="app-content">
                <div id="auth-container" style="display:none;">
                    <div class="auth-split">
                        <div class="auth-panel login-panel">
                            <h3>Login</h3>
                            <form id="login-form">
                                <input type="text" id="login-username" placeholder="Username or Email" required>
                                <input type="password" id="login-password" placeholder="Password" required>
                                <button type="submit" class="btn btn-primary">Login</button>
                            </form>
                            <p class="text-center"><a href="#" id="toggle-register">Don't have an account? Register</a></p>
                        </div>

                        <div class="auth-panel register-panel" style="display:none;">
                            <h3>Register</h3>
                            <form id="register-form">
                                <input type="text" id="register-username" placeholder="Username (3-20 chars)" required>
                                <input type="email" id="register-email" placeholder="Email" required>
                                <input type="password" id="register-password" placeholder="Password (min 8, 1 letter, 1 number)" required>
                                <input type="password" id="register-password-confirm" placeholder="Confirm Password" required>
                                <button type="submit" class="btn btn-primary">Register</button>
                            </form>
                            <p class="text-center"><a href="#" id="toggle-login">Already have an account? Login</a></p>
                        </div>
                    </div>
                </div>

                <div id="welcome-container" style="text-align:center;">
                    <h2>Welcome to PixelForge</h2>
                    <p>A communal pixel canvas + arcade game platform</p>
                    <p style="margin-top:20px; color:#999;">Login or register to get started</p>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>

    <script type="module" src="/assets/js/utils.js"></script>
    <script type="module" src="/assets/js/ui.js"></script>
    <script type="module" src="/assets/js/api.js"></script>
    <script type="module" src="/assets/js/index.js"></script>
</body>
</html>
