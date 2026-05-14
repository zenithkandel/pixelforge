export function showToast(message, type = 'info', duration = 3000) {
    let container = document.querySelector('.toast-container');

    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toast-out 0.25s ease forwards';
        setTimeout(() => toast.remove(), 250);
    }, duration);
}

export function showModal(title, content, buttons = []) {
    let modal = document.querySelector('.modal-overlay');

    if (!modal) {
        modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title"></h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-content"></div>
                <div class="modal-actions"></div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.querySelector('.modal-close').addEventListener('click', () => hideModal());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) hideModal();
        });
    }

    modal.querySelector('.modal-title').textContent = title;
    modal.querySelector('.modal-content').innerHTML = content;

    const actionsContainer = modal.querySelector('.modal-actions');
    actionsContainer.innerHTML = '';
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.className = `btn ${btn.primary ? 'btn-primary' : 'btn-secondary'}`;
        button.textContent = btn.text;
        button.addEventListener('click', () => {
            btn.onClick();
            if (btn.closeOnClick !== false) hideModal();
        });
        actionsContainer.appendChild(button);
    });

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    return {
        close: hideModal
    };
}

export function hideModal() {
    const modal = document.querySelector('.modal-overlay');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

export function showTooltip(element, content, position = 'top') {
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = content;
    tooltip.style.position = 'absolute';

    document.body.appendChild(tooltip);

    const rect = element.getBoundingClientRect();
    const tooltipRect = tooltip.getBoundingClientRect();

    if (position === 'top') {
        tooltip.style.top = (rect.top - tooltipRect.height - 8) + 'px';
        tooltip.style.left = (rect.left + rect.width / 2 - tooltipRect.width / 2) + 'px';
    } else if (position === 'bottom') {
        tooltip.style.top = (rect.bottom + 8) + 'px';
        tooltip.style.left = (rect.left + rect.width / 2 - tooltipRect.width / 2) + 'px';
    }

    element.addEventListener('mouseleave', () => tooltip.remove(), { once: true });

    return tooltip;
}

export function setLoading(element, loading = true) {
    if (loading) {
        element.dataset.originalText = element.textContent;
        element.textContent = 'Loading...';
        element.disabled = true;
    } else {
        element.textContent = element.dataset.originalText || '';
        element.disabled = false;
    }
}

export function highlightCode(code) {
    return code
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/(".*?")/g, '<span class="code-string">$1</span>')
        .replace(/(\/\/.*)/g, '<span class="code-comment">$1</span>');
}

if (!document.querySelector('#ui-styles')) {
    const style = document.createElement('style');
    style.id = 'ui-styles';
    style.textContent = `
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal {
            background: var(--bg-secondary);
            border-radius: var(--border-radius-md);
            padding: 24px;
            min-width: 320px;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
        }
        .modal-content {
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .tooltip {
            background: var(--bg-sidebar);
            color: var(--text-sidebar);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            pointer-events: none;
            z-index: 1001;
        }
        @keyframes toast-out {
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}