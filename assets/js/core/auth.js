/**
 * Auth Module — Session management for PixelForge
 */
const Auth = (() => {
  let currentUser = null;

  async function check() {
    try {
      const data = await API.get('auth.php?action=me');
      if (data.success && data.user) {
        currentUser = data.user;
        return currentUser;
      }
    } catch (e) {
      currentUser = null;
    }
    return null;
  }

  function getUser() {
    return currentUser;
  }

  function isLoggedIn() {
    return currentUser !== null;
  }

  function requireAuth() {
    if (!isLoggedIn()) {
      window.location.href = '/codes/pixelforge/';
      return false;
    }
    return true;
  }

  async function login(identifier, password) {
    const data = await API.post('auth.php?action=login', { identifier, password });
    if (data.success) {
      currentUser = data.user;
    }
    return data;
  }

  async function register(username, email, password) {
    const data = await API.post('auth.php?action=register', { username, email, password });
    if (data.success) {
      currentUser = data.user;
    }
    return data;
  }

  async function logout() {
    try {
      await API.get('auth.php?action=logout');
    } catch (e) {
      // ignore
    }
    currentUser = null;
    window.location.href = '/codes/pixelforge/';
  }

  function getRedirect() {
    return '/codes/pixelforge/home/';
  }

  return { check, getUser, isLoggedIn, requireAuth, login, register, logout, getRedirect };
})();
