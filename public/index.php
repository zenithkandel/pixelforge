<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_authenticated()) {
    header('Location: /canvas.php');
    exit;
}

$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PixelForge - Play & Paint</title>
    <meta name="csrf-token" content="<?php echo h($csrf_token); ?>">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="landing-container">
        <div class="landing-left">
            <div class="landing-brand">
                <div class="landing-logo">PF</div>
                <h1 class="landing-title">PixelForge</h1>
                <p class="landing-tagline">Play. Paint. Create.</p>
            </div>
        </div>
        <div class="landing-right">
            <form class="auth-form" id="authForm">
                <h2 class="auth-title" id="formTitle">Welcome Back</h2>
                <p class="auth-subtitle" id="formSubtitle">Sign in to continue</p>

                <div class="error-message" id="errorMessage"></div>

                <div class="auth-toggle">
                    <button type="button" class="auth-toggle-btn active" data-tab="login">Sign In</button>
                    <button type="button" class="auth-toggle-btn" data-tab="register">Register</button>
                </div>

                <div class="form-group" id="usernameGroup">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="input-field" id="username" name="username" required autocomplete="username">
                </div>

                <div class="form-group" id="emailGroup" style="display: none;">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" class="input-field" id="email" name="email" autocomplete="email">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-toggle">
                        <input type="password" class="input-field" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="toggle-btn" id="togglePassword">Show</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg auth-submit" id="submitBtn">Sign In</button>

                <p class="auth-footer" id="authFooter">
                    Don't have an account? <a href="#" id="toggleLink">Register</a>
                </p>
            </form>
        </div>
    </div>

    <script>
        const authForm = document.getElementById('authForm');
        const formTitle = document.getElementById('formTitle');
        const formSubtitle = document.getElementById('formSubtitle');
        const submitBtn = document.getElementById('submitBtn');
        const errorMessage = document.getElementById('errorMessage');
        const togglePassword = document.getElementById('togglePassword');
        const usernameGroup = document.getElementById('usernameGroup');
        const emailGroup = document.getElementById('emailGroup');
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        let isLogin = true;

        function showError(msg) {
            errorMessage.textContent = msg;
            errorMessage.classList.add('show');
        }

        function hideError() {
            errorMessage.classList.remove('show');
        }

        function toggleMode() {
            isLogin = !isLogin;
            hideError();

            document.querySelectorAll('.auth-toggle-btn').forEach(btn => {
                btn.classList.toggle('active', (btn.dataset.tab === (isLogin ? 'login' : 'register')));
            });

            if (isLogin) {
                formTitle.textContent = 'Welcome Back';
                formSubtitle.textContent = 'Sign in to continue';
                submitBtn.textContent = 'Sign In';
                authFooter.innerHTML = 'Don\'t have an account? <a href="#" id="toggleLink">Register</a>';
                usernameGroup.style.display = 'block';
                emailGroup.style.display = 'none';
                usernameInput.required = true;
                emailInput.required = false;
            } else {
                formTitle.textContent = 'Create Account';
                formSubtitle.textContent = 'Join the community';
                submitBtn.textContent = 'Register';
                authFooter.innerHTML = 'Already have an account? <a href="#" id="toggleLink">Sign In</a>';
                usernameGroup.style.display = 'block';
                emailGroup.style.display = 'block';
                usernameInput.required = true;
                emailInput.required = true;
            }

            document.getElementById('toggleLink').addEventListener('click', (e) => {
                e.preventDefault();
                toggleMode();
            });
        }

        document.querySelectorAll('.auth-toggle-btn').forEach(btn => {
            btn.addEventListener('click', toggleMode);
        });

        document.getElementById('toggleLink')?.addEventListener('click', (e) => {
            e.preventDefault();
            toggleMode();
        });

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            togglePassword.textContent = type === 'password' ? 'Show' : 'Hide';
        });

        authForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideError();

            const endpoint = isLogin ? '/api/auth/login.php' : '/api/auth/register.php';
            const data = {
                username: usernameInput.value,
                password: passwordInput.value,
                csrf_token: csrfToken
            };

            if (!isLogin) {
                data.email = emailInput.value;
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.ok) {
                    window.location.href = '/canvas.php';
                } else {
                    showError(result.message || 'An error occurred');
                }
            } catch (err) {
                showError('Network error. Please try again.');
            }
        });
    </script>
</body>
</html>