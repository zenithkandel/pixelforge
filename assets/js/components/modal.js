/**
 * Modal Component — Dialog management with focus trap
 */
const Modal = (() => {
  let activeModal = null;
  let previousFocus = null;

  function open(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;

    previousFocus = document.activeElement;
    activeModal = overlay;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Focus first focusable element
    const focusable = overlay.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusable.length) {
      setTimeout(() => focusable[0].focus(), 100);
    }

    // Close on overlay click
    overlay.addEventListener('click', handleOverlayClick);

    // Close on escape
    document.addEventListener('keydown', handleEscape);

    // Trap focus
    overlay.addEventListener('keydown', handleTab);
  }

  function close(id) {
    const overlay = id ? document.getElementById(id) : activeModal;
    if (!overlay) return;

    overlay.classList.remove('active');
    document.body.style.overflow = '';
    overlay.removeEventListener('click', handleOverlayClick);
    document.removeEventListener('keydown', handleEscape);
    overlay.removeEventListener('keydown', handleTab);

    if (previousFocus) {
      previousFocus.focus();
      previousFocus = null;
    }

    activeModal = null;
  }

  function handleOverlayClick(e) {
    if (e.target.classList.contains('modal-overlay')) {
      close();
    }
  }

  function handleEscape(e) {
    if (e.key === 'Escape' && activeModal) {
      close();
    }
  }

  function handleTab(e) {
    if (e.key !== 'Tab' || !activeModal) return;

    const focusable = activeModal.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (e.shiftKey) {
      if (document.activeElement === first) {
        last.focus();
        e.preventDefault();
      }
    } else {
      if (document.activeElement === last) {
        first.focus();
        e.preventDefault();
      }
    }
  }

  return { open, close };
})();
