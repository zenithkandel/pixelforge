/**
 * Layout — Shared sidebar & mobile nav HTML generators
 */
const Layout = (() => {
  const BASE = '/codes/pixelforge';

  function sidebar(activePage) {
    const items = [
      { href: `${BASE}/home/`, icon: 'home', label: 'Home', key: 'home' },
      { href: `${BASE}/game/`, icon: 'play', label: 'Play', key: 'game' },
      { href: `${BASE}/world/`, icon: 'world', label: 'World', key: 'world' },
      { href: `${BASE}/rankings/`, icon: 'rankings', label: 'Rankings', key: 'rankings' },
      { href: `${BASE}/profile/`, icon: 'profile', label: 'Profile', key: 'profile' },
    ];

    const icons = {
      home: '<i class="fa-duotone fa-light fa-house"></i>',
      play: '<i class="fa-duotone fa-light fa-play"></i>',
      world: '<i class="fa-duotone fa-light fa-globe"></i>',
      rankings: '<i class="fa-duotone fa-light fa-ranking-star"></i>',
      profile: '<i class="fa-duotone fa-light fa-user"></i>',
    };

    const navItems = items.map(item => `
      <a href="${item.href}" class="sidebar-nav-item${item.key === activePage ? ' active' : ''}" aria-current="${item.key === activePage ? 'page' : 'false'}">
        <span class="sidebar-nav-icon">${icons[item.icon]}</span>
        <span class="sidebar-nav-label">${item.label}</span>
      </a>
    `).join('');

    return `
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
          <a href="${BASE}/home/" class="sidebar-logo">
            <div class="sidebar-logo-icon" aria-hidden="true">P</div>
            <span class="sidebar-logo-text">PixelForge</span>
          </a>
        </div>
        <nav class="sidebar-nav" aria-label="Main navigation">
          ${navItems}
        </nav>
        <div class="sidebar-divider"></div>
        <div class="sidebar-footer">
          <div class="sidebar-user" id="sidebarUser">
            <div class="avatar avatar-sm" id="sidebarAvatar" style="background-color: var(--accent);">?</div>
            <div class="sidebar-user-info">
              <div class="sidebar-user-name" id="sidebarUsername">Loading...</div>
              <div class="sidebar-user-level" id="sidebarLevel">Level —</div>
            </div>
          </div>
          <div class="mt-3">
            <button class="btn btn-ghost btn-sm" style="width: 100%; justify-content: flex-start;" id="logoutBtn">
              <i class="fa-duotone fa-light fa-right-from-bracket"></i>
              Log out
            </button>
          </div>
        </div>
      </aside>
      <div class="sidebar-overlay" id="sidebarOverlay"></div>
    `;
  }

  function mobileNav(activePage) {
    const items = [
      { href: `${BASE}/home/`, icon: 'home', label: 'Home', key: 'home' },
      { href: `${BASE}/game/`, icon: 'play', label: 'Play', key: 'game' },
      { href: `${BASE}/world/`, icon: 'world', label: 'World', key: 'world' },
      { href: `${BASE}/rankings/`, icon: 'rankings', label: 'Rankings', key: 'rankings' },
      { href: `${BASE}/profile/`, icon: 'profile', label: 'Profile', key: 'profile' },
    ];

    const icons = {
      home: '<i class="fa-duotone fa-light fa-house"></i>',
      play: '<i class="fa-duotone fa-light fa-play"></i>',
      world: '<i class="fa-duotone fa-light fa-globe"></i>',
      rankings: '<i class="fa-duotone fa-light fa-ranking-star"></i>',
      profile: '<i class="fa-duotone fa-light fa-user"></i>',
    };

    const navItems = items.map(item => `
      <a href="${item.href}" class="mobile-nav-item${item.key === activePage ? ' active' : ''}" aria-current="${item.key === activePage ? 'page' : 'false'}">
        <span class="mobile-nav-icon">${icons[item.icon]}</span>
        <span class="mobile-nav-label">${item.label}</span>
      </a>
    `).join('');

    return `
      <nav class="mobile-nav" id="mobileNav" aria-label="Mobile navigation">
        <div class="mobile-nav-inner">
          ${navItems}
        </div>
      </nav>
    `;
  }

  function topbar(title, subtitle, leftAction) {
    return `
      <header class="page-header">
        <div class="page-header-inner">
          <div class="flex items-center gap-4">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu">
              <i class="fa-duotone fa-light fa-bars"></i>
            </button>
            <div>
              <h1 class="page-title">${title}</h1>
              ${subtitle ? `<p class="page-subtitle">${subtitle}</p>` : ''}
            </div>
          </div>
        </div>
      </header>
    `;
  }

  function initUser(user) {
    const avatar = document.getElementById('sidebarAvatar');
    const username = document.getElementById('sidebarUsername');
    const level = document.getElementById('sidebarLevel');
    const logoutBtn = document.getElementById('logoutBtn');

    if (avatar) {
      avatar.textContent = user.username.charAt(0).toUpperCase();
      avatar.style.backgroundColor = user.avatar_color || 'var(--accent)';
    }
    if (username) username.textContent = user.username;
    if (level) level.textContent = `Level ${user.level}`;
    if (logoutBtn) logoutBtn.addEventListener('click', () => Auth.logout());
  }

  function init(activePage) {
    // Inject sidebar and mobile nav
    document.body.insertAdjacentHTML('afterbegin', sidebar(activePage));
    document.body.insertAdjacentHTML('beforeend', mobileNav(activePage));

    // Init sidebar behavior
    Sidebar.init();

    // Load user data
    Auth.check().then(user => {
      if (!user) {
        window.location.href = `${BASE}/`;
        return;
      }
      initUser(user);
      Events.emit('user:loaded', user);
    });

    // Logout
    document.getElementById('logoutBtn')?.addEventListener('click', () => Auth.logout());
  }

  return { init, topbar, initUser };
})();
