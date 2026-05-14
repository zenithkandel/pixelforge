import { api } from './api.js';
import { showToast, showError, showSuccess } from './ui.js';

// Initialize page
async function initPage() {
    await loadUserData();
    setupEventListeners();
}

async function loadUserData() {
    const user = await api.get('/api/auth/me.php');
    if (user) {
        document.getElementById('username-display').textContent = user.username;
        document.getElementById('pxl-display').textContent = user.pxl_balance;
        document.getElementById('user-info').style.display = 'block';
        document.getElementById('nav-profile').style.display = 'block';
        document.getElementById('auth-container').style.display = 'none';
        document.getElementById('welcome-container').style.display = 'none';
    } else {
        document.getElementById('auth-container').style.display = 'block';
        document.getElementById('welcome-container').style.display = 'block';
        document.getElementById('user-info').style.display = 'none';
        document.getElementById('nav-profile').style.display = 'none';
    }
}

function setupEventListeners() {
    // Auth form toggles
    document.getElementById('toggle-register')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.login-panel').style.display = 'none';
        document.querySelector('.register-panel').style.display = 'block';
    });

    document.getElementById('toggle-login')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.login-panel').style.display = 'block';
        document.querySelector('.register-panel').style.display = 'none';
    });

    // Login form
    document.getElementById('login-form')?.addEventListener('submit', handleLogin);

    // Register form
    document.getElementById('register-form')?.addEventListener('submit', handleRegister);

    // Logout button
    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
}

async function handleLogin(e) {
    e.preventDefault();
    
    const username_or_email = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;

    const result = await api.post('/api/auth/login.php', {
        username_or_email,
        password
    });

    if (result) {
        showSuccess('Login successful!');
        setTimeout(() => window.location.href = '/canvas.php', 500);
    }
}

async function handleRegister(e) {
    e.preventDefault();

    const username = document.getElementById('register-username').value;
    const email = document.getElementById('register-email').value;
    const password = document.getElementById('register-password').value;
    const password_confirm = document.getElementById('register-password-confirm').value;

    const result = await api.post('/api/auth/register.php', {
        username,
        email,
        password,
        password_confirm
    });

    if (result) {
        showSuccess('Registration successful! Please verify your email.');
        document.getElementById('register-form').reset();
        setTimeout(() => {
            document.querySelector('.login-panel').style.display = 'block';
            document.querySelector('.register-panel').style.display = 'none';
        }, 1000);
    }
}

async function handleLogout() {
    await api.post('/api/auth/logout.php', {});
    showSuccess('Logged out');
    setTimeout(() => window.location.href = '/index.php', 500);
}

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
} else {
    initPage();
}
