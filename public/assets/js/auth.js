import api from './api.js';

let currentTab = 'login';

document.querySelectorAll('.auth-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const target = tab.dataset.tab;
    document.querySelectorAll('.auth-form').forEach(f => {
      f.hidden = f.dataset.tab !== target;
    });
    currentTab = target;
  });
});

document.getElementById('show-forgot')?.addEventListener('click', e => {
  e.preventDefault();
  document.getElementById('login-form').hidden = true;
  document.getElementById('register-form').hidden = true;
  document.getElementById('forgot-form').hidden = false;
});

document.getElementById('back-to-login')?.addEventListener('click', e => {
  e.preventDefault();
  document.getElementById('forgot-form').hidden = true;
  document.getElementById('login-form').hidden = false;
});

document.getElementById('login-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('login-btn');
  const errEl = document.getElementById('login-error');
  errEl.hidden = true;
  btn.disabled = true;
  btn.textContent = 'Signing in...';

  try {
    const fd = new FormData(e.target);
    const res = await fetch('/api/auth/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': api.csrfToken },
      body: JSON.stringify({ username: fd.get('username'), password: fd.get('password'), remember: fd.get('remember') || false }),
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = data.data.redirect || '/game.php';
    } else {
      errEl.textContent = data.message || 'Login failed. Check your credentials.';
      errEl.hidden = false;
    }
  } catch (e) {
    errEl.textContent = 'An error occurred. Please try again.';
    errEl.hidden = false;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Sign In';
  }
});

document.getElementById('register-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('register-btn');
  const errEl = document.getElementById('register-error');
  errEl.hidden = true;
  btn.disabled = true;
  btn.textContent = 'Creating account...';

  const password = document.getElementById('reg-password').value;
  const confirm = document.getElementById('reg-confirm').value;
  if (password !== confirm) {
    errEl.textContent = 'Passwords do not match.';
    errEl.hidden = false;
    btn.disabled = false;
    btn.textContent = 'Create Account';
    return;
  }

  try {
    const fd = new FormData(e.target);
    const res = await fetch('/api/auth/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': api.csrfToken },
      body: JSON.stringify({
        username: fd.get('username'),
        email: fd.get('email'),
        password: fd.get('password'),
      }),
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = '/game.php';
    } else {
      errEl.textContent = data.message || 'Registration failed.';
      errEl.hidden = false;
    }
  } catch (e) {
    errEl.textContent = 'An error occurred. Please try again.';
    errEl.hidden = false;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create Account';
  }
});

document.getElementById('forgot-form')?.addEventListener('submit', async e => {
  e.preventDefault();
  const btn = document.getElementById('forgot-btn');
  const errEl = document.getElementById('forgot-error');
  const sucEl = document.getElementById('forgot-success');
  errEl.hidden = true;
  sucEl.hidden = true;
  btn.disabled = true;
  btn.textContent = 'Sending...';

  try {
    const fd = new FormData(e.target);
    const res = await fetch('/api/auth/forgot-password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': api.csrfToken },
      body: JSON.stringify({ email: fd.get('email') }),
      credentials: 'same-origin',
    });
    const data = await res.json();
    if (data.ok) {
      sucEl.textContent = 'Password reset email sent! Check your inbox.';
      sucEl.hidden = false;
      e.target.reset();
    } else {
      errEl.textContent = data.message || 'Failed to send reset email.';
      errEl.hidden = false;
    }
  } catch (e) {
    errEl.textContent = 'An error occurred.';
    errEl.hidden = false;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Send Reset Link';
  }
});

export function initAuth() {}