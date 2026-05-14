<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header("Location: /canvas.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelForge</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="auth-container">
        <h1>PixelForge</h1>
        <p>A Communal Pixel Canvas + Arcade Game Platform</p>
        
        <div class="forms">
            <form id="login-form">
                <h2>Login</h2>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
            
            <form id="register-form">
                <h2>Register</h2>
                <input type="text" name="username" placeholder="Username (3-20 chars)" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password (min 8 chars)" required>
                <button type="submit">Register</button>
            </form>
        </div>
    </div>
    
    <script type="module">
        import { getCsrfToken, apiPost } from '/assets/js/api.js';
        
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            const res = await apiPost('/api/auth/login.php', data);
            if (res.ok) {
                window.location.href = '/canvas.php';
            } else {
                alert(res.message);
            }
        });

        document.getElementById('register-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(e.target);
            const data = Object.fromEntries(fd.entries());
            const res = await apiPost('/api/auth/register.php', data);
            if (res.ok) {
                alert(res.data.message);
            } else {
                alert(res.message);
            }
        });
    </script>
</body>
</html>
