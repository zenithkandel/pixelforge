window.pixelforge = window.pixelforge || {};

window.pixelforge.ui = {
  showToast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${
        type === 'success' ? '✓' : 
        type === 'error' ? '✗' : 
        type === 'warning' ? '⚠' : 'ℹ'
      }</span>
      <span class="toast-message">${this.escapeHtml(message)}</span>
    `;
    
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100px)';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },

  escapeHtml(str) {
    if (typeof str !== 'string') return str;
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  showModal(title, content, actions = []) {
    let modalOverlay = document.getElementById('modalOverlay');
    
    if (!modalOverlay) {
      modalOverlay = document.createElement('div');
      modalOverlay.id = 'modalOverlay';
      modalOverlay.className = 'modal-overlay';
      modalOverlay.innerHTML = `
        <div class="modal">
          <div class="modal-header">
            <h3 class="modal-title"></h3>
            <span class="modal-close">&times;</span>
          </div>
          <div class="modal-body"></div>
          <div class="modal-actions"></div>
        </div>
      `;
      document.body.appendChild(modalOverlay);

      modalOverlay.querySelector('.modal-close').addEventListener('click', () => this.hideModal());
      modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) this.hideModal();
      });
    }

    modalOverlay.querySelector('.modal-title').textContent = title;
    modalOverlay.querySelector('.modal-body').innerHTML = content;
    
    const actionsContainer = modalOverlay.querySelector('.modal-actions');
    actionsContainer.innerHTML = '';
    actions.forEach(action => {
      const btn = document.createElement('button');
      btn.className = `btn ${action.class || 'btn-outline'}`;
      btn.textContent = action.label;
      btn.addEventListener('click', () => {
        if (action.onClick) action.onClick();
        if (action.closeOnClick !== false) this.hideModal();
      });
      actionsContainer.appendChild(btn);
    });

    modalOverlay.classList.add('active');
  },

  hideModal() {
    const modal = document.getElementById('modalOverlay');
    if (modal) modal.classList.remove('active');
  },

  formatTime(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
  },

  formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toLocaleString();
  },

  debounce(fn, delay) {
    let timeout;
    return (...args) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => fn(...args), delay);
    };
  },

  throttle(fn, limit) {
    let inThrottle;
    return (...args) => {
      if (!inThrottle) {
        fn(...args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }
};

window.showToast = (msg, type) => window.pixelforge.ui.showToast(msg, type);
window.escapeHtml = (str) => window.pixelforge.ui.escapeHtml(str);