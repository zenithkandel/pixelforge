const API_BASE = '/api';

class PixelForgeAPI {
  constructor() {
    this.accessToken = null;
    this.refreshPromise = null;
    this.csrfToken = null;
  }

  async request(endpoint, options = {}) {
    const url = `${API_BASE}${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    };

    if (this.accessToken) {
      headers['Authorization'] = `Bearer ${this.accessToken}`;
    }

    if (this.csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method?.toUpperCase())) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    const response = await fetch(url, {
      ...options,
      headers,
      credentials: 'include'
    });

    if (response.status === 401 && options.method !== 'POST') {
      const refreshed = await this.refreshToken();
      if (refreshed) {
        headers['Authorization'] = `Bearer ${this.accessToken}`;
        const retryResponse = await fetch(url, {
          ...options,
          headers,
          credentials: 'include'
        });
        return this.handleResponse(retryResponse);
      }
    }

    return this.handleResponse(response);
  }

  async handleResponse(response) {
    const contentType = response.headers.get('content-type');
    let data;

    if (contentType && contentType.includes('application/json')) {
      data = await response.json();
    } else if (contentType && contentType.includes('application/octet-stream')) {
      const buffer = await response.arrayBuffer();
      return {
        ok: true,
        data: buffer,
        version: response.headers.get('X-Chunk-Version')
      };
    } else {
      data = await response.text();
    }

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Request failed');
    }

    return data;
  }

  async refreshToken() {
    if (this.refreshPromise) {
      return this.refreshPromise;
    }

    this.refreshPromise = this.doRefresh();
    return this.refreshPromise;
  }

  async doRefresh() {
    try {
      const response = await fetch(`${API_BASE}/auth/refresh`, {
        method: 'POST',
        credentials: 'include'
      });

      if (response.ok) {
        const data = await response.json();
        this.accessToken = data.data.accessToken;
        return true;
      }
      return false;
    } catch (err) {
      return false;
    } finally {
      this.refreshPromise = null;
    }
  }

  setAccessToken(token) {
    this.accessToken = token;
  }

  setCsrfToken(token) {
    this.csrfToken = token;
  }

  clearTokens() {
    this.accessToken = null;
  }

  get(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'GET' });
  }

  post(endpoint, body, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  put(endpoint, body, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(body)
    });
  }

  delete(endpoint, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  }

  async login(username, password) {
    const response = await this.post('/auth/login', { username, password });
    if (response.ok && response.data.accessToken) {
      this.accessToken = response.data.accessToken;
    }
    return response;
  }

  async register(username, email, password) {
    return this.post('/auth/register', { username, email, password });
  }

  async logout() {
    try {
      await this.post('/auth/logout', {});
    } catch (e) {
      // ignore
    }
    this.clearTokens();
  }

  async getSession() {
    try {
      return await this.get('/grid/session');
    } catch (e) {
      return null;
    }
  }

  async getCurrentUser() {
    return this.get('/user/me');
  }
}

window.pixelforge = {
  api: new PixelForgeAPI(),

  async showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toastContainer') || document.body;
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</span>
      <span class="toast-message">${message}</span>
    `;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  escapeHtml(str) {
    if (typeof str !== 'string') return str;
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  }
};