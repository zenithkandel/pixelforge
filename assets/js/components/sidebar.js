/**
 * Sidebar Component — Navigation behavior
 */
const Sidebar = (() => {
  let sidebar, overlay, toggle;

  function init() {
    sidebar = document.getElementById('sidebar');
    overlay = document.getElementById('sidebarOverlay');
    toggle = document.getElementById('sidebarToggle');

    if (toggle) {
      toggle.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
      overlay.addEventListener('click', closeSidebar);
    }

    // Close on escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar?.classList.contains('open')) {
        closeSidebar();
      }
    });

    setActiveItem();
  }

  function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function setActiveItem() {
    const path = window.location.pathname;
    const items = document.querySelectorAll('.sidebar-nav-item');
    items.forEach(item => {
      const href = item.getAttribute('href');
      if (href && path.startsWith(href) && href !== '/codes/pixelforge/') {
        item.classList.add('active');
      } else if (href === '/codes/pixelforge/home/' && path === '/codes/pixelforge/home/') {
        item.classList.add('active');
      }
    });
  }

  return { init, closeSidebar };
})();
