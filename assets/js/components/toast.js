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
      success: '<i class="fa-duotone fa-light fa-circle-check"></i>',
      error: '<i class="fa-duotone fa-light fa-circle-xmark"></i>',
      warning: '<i class="fa-duotone fa-light fa-triangle-exclamation"></i>',
      info: '<i class="fa-duotone fa-light fa-circle-info"></i>',
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
        <i class="fa-duotone fa-light fa-xmark"></i>
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
