/**
 * Toast Component — Notification system
 */
const Toast = (() => {
  let container;

  function init() {
    container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
  }

  function show(options) {
    if (!container) init();

    const { type = 'info', title = '', message = '', duration = 3000 } = options;

    const icons = {
      success: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M6 10l2.5 2.5L14 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
      error: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M7 7l6 6M13 7l-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
      warning: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 3L1 18h18L10 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M10 9v4M10 15h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
      info: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="2"/><path d="M10 9v5M10 6h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
    };

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <div class="toast-content">
        ${title ? `<div class="toast-title">${title}</div>` : ''}
        ${message ? `<div class="toast-message">${message}</div>` : ''}
      </div>
      <button class="toast-close" aria-label="Dismiss">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M3 3l8 8M11 3l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
      </button>
    `;

    const closeBtn = el.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => remove(el));

    container.appendChild(el);

    if (duration > 0) {
      setTimeout(() => remove(el), duration);
    }

    return el;
  }

  function remove(el) {
    if (!el || el.classList.contains('removing')) return;
    el.classList.add('removing');
    setTimeout(() => el.remove(), 200);
  }

  return {
    init,
    show,
    success: (title, message) => show({ type: 'success', title, message }),
    error: (title, message) => show({ type: 'error', title, message }),
    warning: (title, message) => show({ type: 'warning', title, message }),
    info: (title, message) => show({ type: 'info', title, message }),
  };
})();
