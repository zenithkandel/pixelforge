/**
 * API Client — Fetch wrapper for PixelForge
 * Handles CSRF, auth, error formatting
 */
const API = (() => {
  const BASE = '/codes/pixelforge/api';

  function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.content;
    const input = document.querySelector('input[name="csrf_token"]');
    if (input) return input.value;
    return null;
  }

  async function request(endpoint, options = {}) {
    const url = `${BASE}/${endpoint}`;
    const headers = {
      'Content-Type': 'application/json',
      ...options.headers,
    };

    const csrfToken = getCSRFToken();
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken;
    }

    const config = {
      credentials: 'same-origin',
      ...options,
      headers,
    };

    try {
      const response = await fetch(url, config);
      const data = await response.json();

      if (!response.ok) {
        const error = new Error(data.message || `HTTP ${response.status}`);
        error.status = response.status;
        error.data = data;
        throw error;
      }

      return data;
    } catch (err) {
      if (err.status) throw err;
      const error = new Error('Network error. Please try again.');
      error.status = 0;
      throw error;
    }
  }

  return {
    get(endpoint) {
      return request(endpoint, { method: 'GET' });
    },

    post(endpoint, body) {
      return request(endpoint, {
        method: 'POST',
        body: JSON.stringify(body),
      });
    },

    put(endpoint, body) {
      return request(endpoint, {
        method: 'PUT',
        body: JSON.stringify(body),
      });
    },

    delete(endpoint) {
      return request(endpoint, { method: 'DELETE' });
    },
  };
})();
