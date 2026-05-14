let toastContainer = null;

export function showToast(message, type = 'success', duration = 3000) {
  if (!toastContainer) {
    toastContainer = document.getElementById('toast-container') || createContainer();
  }
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  toastContainer.appendChild(toast);
  setTimeout(() => toast.remove(), duration);
}

function createContainer() {
  const c = document.createElement('div');
  c.className = 'toast-container';
  c.id = 'toast-container';
  document.body.appendChild(c);
  return c;
}

export function formatNumber(n) {
  return Number(n).toLocaleString();
}

export function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

export function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

export function throttle(fn, limit) {
  let inThrottle;
  return (...args) => {
    if (!inThrottle) {
      fn(...args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
}