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
      home: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 8l7-6 7 6v8a1 1 0 01-1 1H4a1 1 0 01-1-1V8z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M8 17v-6h4v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
      play: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><polygon points="7,4 16,10 7,16" fill="currentColor"/></svg>',
      world: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="2" y="2" width="16" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2 7h16M2 12h16M7 2v16M12 2v16" stroke="currentColor" stroke-width="1" opacity="0.4"/></svg>',
      rankings: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5 15V9M10 15V5M15 15V11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
      profile: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M4 17c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
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
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6 14H3a1 1 0 01-1-1V3a1 1 0 011-1h3M11 11l3-3-3-3M6 8h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
      home: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 10l8-7 8 7v9a1 1 0 01-1 1H5a1 1 0 01-1-1v-9z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>',
      play: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><polygon points="8,5 19,12 8,19" fill="currentColor"/></svg>',
      world: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18" stroke="currentColor" stroke-width="1" opacity="0.4"/></svg>',
      rankings: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M6 18V11M12 18V6M18 18V13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
      profile: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.5"/><path d="M5 20c0-3.9 3.1-7 7-7s7 3.1 7 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
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
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
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
