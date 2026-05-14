// Toast notifications
export function showToast(message, type = 'info', duration = 3000) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

export function showError(message, duration = 4000) {
    showToast(message, 'error', duration);
}

export function showSuccess(message, duration = 3000) {
    showToast(message, 'success', duration);
}

// Modal
export function showModal(title, content, buttons = []) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <h3>${title}</h3>
            <div class="modal-body">${content}</div>
            <div class="modal-buttons"></div>
        </div>
    `;

    const buttonsDiv = modal.querySelector('.modal-buttons');
    for (const btn of buttons) {
        const button = document.createElement('button');
        button.className = 'btn ' + (btn.primary ? 'btn-primary' : 'btn-secondary');
        button.textContent = btn.text;
        button.onclick = () => {
            btn.onClick?.();
            modal.remove();
        };
        buttonsDiv.appendChild(button);
    }

    document.body.appendChild(modal);
    return modal;
}

// Loader
export function showLoader() {
    const overlay = document.createElement('div');
    overlay.className = 'loader-overlay';
    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);
    return overlay;
}

export function hideLoader(overlay) {
    overlay?.remove();
}
