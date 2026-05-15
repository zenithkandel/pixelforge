const auth = {
  user: null,
  isLoggedIn: false,

  async init() {
    const token = localStorage.getItem('accessToken');
    if (token) {
      pixelforge.api.setAccessToken(token);
      await this.loadUser();
    }
    this.updateUI();
  },

  async loadUser() {
    try {
      const response = await pixelforge.api.getUserMe();
      if (response.ok && response.data) {
        this.user = response.data;
        this.isLoggedIn = true;
      } else {
        this.logoutLocal();
      }
    } catch (err) {
      console.error('Failed to load user:', err);
      this.logoutLocal();
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
      if (navBalance) navBalance.textContent = this.user.pxlBalance || 0;
      if (navAvatar) navAvatar.textContent = this.user.username.charAt(0).toUpperCase();
    } else {
      navAuth?.classList.remove('hidden');
      navUser?.classList.add('hidden');
    }
  },

  async login(username, password) {
    try {
      const response = await pixelforge.api.login(username, password);
      
      if (response.ok && response.data?.accessToken) {
        localStorage.setItem('accessToken', response.data.accessToken);
        pixelforge.api.setAccessToken(response.data.accessToken);
        await this.loadUser();
        this.updateUI();
        return { success: true };
      } else {
        return { success: false, error: response.error || 'Login failed' };
      }
    } catch (err) {
      return { success: false, error: err.message };
    }
  },

  async register(username, email, password) {
    try {
      const response = await pixelforge.api.register(username, email, password);
      
      if (response.ok && response.data) {
        return { success: true, needsVerification: response.data.needsVerification };
      } else {
        return { success: false, error: response.error || 'Registration failed' };
      }
    } catch (err) {
      return { success: false, error: err.message };
    }
  },

  async logout() {
    try {
      await pixelforge.api.logout();
    } catch (e) {}
    this.logoutLocal();
  },

  logoutLocal() {
    localStorage.removeItem('accessToken');
    pixelforge.api.clearTokens();
    this.user = null;
    this.isLoggedIn = false;
    this.updateUI();
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