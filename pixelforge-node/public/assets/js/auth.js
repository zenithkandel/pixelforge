window.pixelforge = window.pixelforge || {};

const auth = {
  user: null,
  isLoggedIn: false,

  async init() {
    const token = localStorage.getItem('accessToken');
    if (token) {
      window.pixelforge.api.setAccessToken(token);
      await this.loadUser();
    }
    this.updateUI();
  },

  async loadUser() {
    try {
      const response = await window.pixelforge.api.get('/user/me');
      if (response.ok && response.data) {
        this.user = response.data;
        this.isLoggedIn = true;
      } else {
        this.logout();
      }
    } catch (err) {
      console.error('Failed to load user:', err);
    }
  },

  updateUI() {
    const navAuth = document.getElementById('navAuth');
    const navUser = document.getElementById('navUser');
    const navBalance = document.getElementById('navBalance');
    const navAvatar = document.getElementById('navAvatar');

    if (this.isLoggedIn && this.user) {
      navAuth?.classList.add('hidden');
      navUser?.classList.remove('hidden');
      if (navBalance) navBalance.textContent = this.user.pxlBalance;
      if (navAvatar) navAvatar.textContent = this.user.username.charAt(0).toUpperCase();
    } else {
      navAuth?.classList.remove('hidden');
      navUser?.classList.add('hidden');
    }
  },

  async login(username, password) {
    try {
      const response = await window.pixelforge.api.post('/auth/login', { username, password });
      
      if (response.ok && response.data.accessToken) {
        localStorage.setItem('accessToken', response.data.accessToken);
        window.pixelforge.api.setAccessToken(response.data.accessToken);
        await this.loadUser();
        this.updateUI();
        return { success: true };
      } else if (response.error) {
        return { success: false, error: response.error };
      } else {
        return { success: false, error: 'Login failed' };
      }
    } catch (err) {
      return { success: false, error: err.message };
    }
  },

  async register(username, email, password) {
    try {
      const response = await window.pixelforge.api.post('/auth/register', { username, email, password });
      
      if (response.ok && response.data) {
        return { success: true, needsVerification: response.data.needsVerification };
      } else if (response.error) {
        return { success: false, error: response.error };
      } else {
        return { success: false, error: 'Registration failed' };
      }
    } catch (err) {
      return { success: false, error: err.message };
    }
  },

  async logout() {
    try {
      await window.pixelforge.api.post('/auth/logout', {});
    } catch (e) {
      // ignore
    }
    localStorage.removeItem('accessToken');
    this.user = null;
    this.isLoggedIn = false;
    this.updateUI();
    if (window.location.pathname !== '/') {
      window.location.href = '/';
    }
  },

  getBalance() {
    return this.user?.pxlBalance || 0;
  },

  isAdmin() {
    return this.user?.isAdmin || false;
  }
};

window.pixelforge.auth = auth;
document.addEventListener('DOMContentLoaded', () => auth.init());