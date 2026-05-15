const API_BASE = '/api';

class PixelForgeAPI {
  constructor() {
    this.accessToken = null;
    this.refreshPromise = null;
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

    try {
      const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'include'
      });

      const contentType = response.headers.get('content-type');
      
      if (contentType && contentType.includes('application/octet-stream')) {
        const buffer = await response.arrayBuffer();
        return {
          ok: true,
          buffer: new Uint8Array(buffer),
          version: response.headers.get('X-Chunk-Version')
        };
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || data.message || 'Request failed');
      }

      return data;
    } catch (err) {
      throw err;
    }
  }

  setAccessToken(token) {
    this.accessToken = token;
  }

  clearTokens() {
    this.accessToken = null;
  }

  get(endpoint) {
    return this.request(endpoint, { method: 'GET' });
  }

  post(endpoint, body) {
    return this.request(endpoint, {
      method: 'POST',
      body: JSON.stringify(body)
    });
  }

  async login(username, password) {
    return this.post('/auth/login', { username, password });
  }

  async register(username, email, password) {
    return this.post('/auth/register', { username, email, password });
  }

  async logout() {
    try {
      await this.post('/auth/logout', {});
    } catch (e) {}
    this.clearTokens();
  }

  async getSession() {
    try {
      return await this.get('/grid/session');
    } catch (e) {
      return { ok: false, data: null };
    }
  }

  async getUserMe() {
    return this.get('/user/me');
  }
}

window.pixelforge = {
  api: new PixelForgeAPI(),

  showToast(message, type = 'info', duration = 3000) {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
      document.body.appendChild(container);
    }
    
    const icons = { success: '✓', error: '✗', warning: '⚠', info: 'ℹ' };
    const toast = document.createElement('div');
    toast.style.cssText = 'background:#1a1a26;border:1px solid #2a2a3a;border-radius:8px;padding:12px 20px;display:flex;align-items:center;gap:10px;animation:slideIn 0.3s ease;font-family:Rajdhani,sans-serif;font-size:14px;color:#e0e0e0;';
    if (type === 'success') toast.style.borderColor = '#00ff88';
    if (type === 'error') toast.style.borderColor = '#ff4757';
    toast.innerHTML = `<span style="font-size:18px;">${icons[type] || icons.info}</span><span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transition = 'opacity 0.3s';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  escapeHtml(str) {
    if (typeof str !== 'string') return str;
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
};

window.showToast = window.pixelforge.showToast;