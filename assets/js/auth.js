(function () {
  'use strict';

  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');
  const loginTab = document.getElementById('login-tab');
  const registerTab = document.getElementById('register-tab');
  const loginSubmit = document.getElementById('login-submit');
  const registerSubmit = document.getElementById('register-submit');

  function init() {
    setupTabs();
    setupForms();
    checkHash();
    setupPasswordToggles();
  }

  function setupTabs() {
    loginTab.addEventListener('click', function () {
      switchTab('login');
    });
    registerTab.addEventListener('click', function () {
      switchTab('register');
    });
  }

  function switchTab(tab) {
    const isLogin = tab === 'login';

    loginTab.classList.toggle('active', isLogin);
    registerTab.classList.toggle('active', !isLogin);

    loginForm.classList.toggle('hidden', !isLogin);
    registerForm.classList.toggle('hidden', isLogin);

    clearMessages('login');
    clearMessages('register');

    window.location.hash = tab;
  }

  function checkHash() {
    if (window.location.hash === '#register') {
      switchTab('register');
    }
  }

  function setupPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const input = btn.previousElementSibling;
        if (!input) return;

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';

        const icon = btn.querySelector('.icon');
        if (icon) {
          icon.classList.toggle('icon-eye', !isPassword);
          icon.classList.toggle('icon-eye-off', isPassword);
        }
      });
    });
  }

  function setupForms() {
    loginForm.addEventListener('submit', handleLogin);
    registerForm.addEventListener('submit', handleRegister);
  }

  async function handleLogin(e) {
    e.preventDefault();

    var identifier = loginForm.querySelector('[name="identifier"]').value.trim();
    var password = loginForm.querySelector('[name="password"]').value;

    if (!identifier) {
      return showError('login', 'Please enter your username or email');
    }
    if (!password) {
      return showError('login', 'Please enter your password');
    }

    setLoading(loginSubmit, true);

    try {
      var response = await fetch('/codes/pixelforge/api/auth.php?action=login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ identifier: identifier, password: password }),
      });

      var data = await response.json();

      if (data.success) {
        window.location.href = data.redirect || '/codes/pixelforge/';
      } else {
        showError('login', data.message || 'Login failed');
      }
    } catch (err) {
      showError('login', 'Network error. Please try again.');
    } finally {
      setLoading(loginSubmit, false);
    }
  }

  async function handleRegister(e) {
    e.preventDefault();

    var username = registerForm.querySelector('[name="username"]').value.trim();
    var email = registerForm.querySelector('[name="email"]').value.trim();
    var password = registerForm.querySelector('[name="password"]').value;
    var confirmPassword = registerForm.querySelector('[name="confirm_password"]').value;

    if (username.length < 3 || username.length > 30) {
      return showError('register', 'Username must be 3-30 characters');
    }
    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
      return showError('register', 'Username: letters, numbers, underscores only');
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return showError('register', 'Please enter a valid email');
    }
    if (password.length < 8) {
      return showError('register', 'Password must be at least 8 characters');
    }
    if (!/[a-zA-Z]/.test(password) || !/[0-9]/.test(password)) {
      return showError('register', 'Password must contain letters and numbers');
    }
    if (password !== confirmPassword) {
      return showError('register', 'Passwords do not match');
    }

    setLoading(registerSubmit, true);

    try {
      var response = await fetch('/codes/pixelforge/api/auth.php?action=register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': getCsrfToken(),
        },
        body: JSON.stringify({ username: username, email: email, password: password }),
      });

      var data = await response.json();

      if (data.success) {
        showSuccess('register', 'Account created! Redirecting...');
        setTimeout(function () {
          window.location.href = data.redirect || '/codes/pixelforge/';
        }, 1000);
      } else {
        showError('register', data.message || 'Registration failed');
      }
    } catch (err) {
      showError('register', 'Network error. Please try again.');
    } finally {
      setLoading(registerSubmit, false);
    }
  }

  function showError(form, message) {
    var errorEl = document.querySelector('#' + form + '-error');
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.style.display = 'block';
      errorEl.className = 'flash-error';
    }
  }

  function showSuccess(form, message) {
    var errorEl = document.querySelector('#' + form + '-error');
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.style.display = 'block';
      errorEl.className = 'flash-success';
    }
  }

  function clearMessages(form) {
    var errorEl = document.querySelector('#' + form + '-error');
    if (errorEl) {
      errorEl.textContent = '';
      errorEl.style.display = 'none';
      errorEl.className = '';
    }
  }

  function setLoading(btn, loading) {
    if (loading) {
      btn.classList.add('loading');
      btn.disabled = true;
      btn.dataset.originalText = btn.textContent;
      btn.textContent = 'Please wait...';
    } else {
      btn.classList.remove('loading');
      btn.disabled = false;
      btn.textContent = btn.dataset.originalText || btn.textContent;
    }
  }

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    var input = document.querySelector('[name="csrf_token"]');
    if (input) return input.value;
    return '';
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
