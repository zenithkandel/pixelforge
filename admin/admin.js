/**
 * PixelForge Admin Panel — SPA Controller
 * Hash-based routing, no dependencies.
 */
(function() {
  'use strict';

  var API_BASE = '/codes/pixelforge/admin/api.php';

  var state = {
    currentSection: 'dashboard',
    pages: { users: 1, pixels: 1, sessions: 1, transactions: 1 },
    searchTimeout: null,
    authChecked: false,
    isAdmin: false
  };

  // --- API helper ---
  function api(action, opts) {
    opts = opts || {};
    var url = API_BASE + '?action=' + encodeURIComponent(action);
    var config = { credentials: 'same-origin' };

    if (opts.params) {
      Object.keys(opts.params).forEach(function(k) {
        url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(opts.params[k]);
      });
    }

    if (opts.body) {
      config.method = 'POST';
      config.headers = { 'Content-Type': 'application/json' };
      config.body = JSON.stringify(opts.body);
    }

    return fetch(url, config).then(function(r) {
      var contentType = r.headers.get('content-type') || '';
      if (contentType.indexOf('application/json') === -1) {
        throw new Error('Server returned non-JSON response (status ' + r.status + ')');
      }
      return r.json().then(function(data) {
        if (r.status === 401) {
          showAuthState('login');
          throw new Error(data.message || 'Not logged in');
        }
        if (r.status === 403) {
          showAuthState('admin');
          throw new Error(data.message || 'Admin access required');
        }
        return data;
      });
    });
  }

  // --- Auth state display ---
  function showAuthState(reason) {
    var main = document.querySelector('.admin-main');
    if (!main) return;

    var msg = reason === 'admin'
      ? 'You need admin access to view this panel. Log in with an admin account.'
      : 'Please log in to access the admin panel.';

    var loginUrl = '/codes/pixelforge/';

    main.innerHTML =
      '<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;padding:var(--space-8);">' +
        '<div class="card" style="max-width:420px;width:100%;text-align:center;">' +
          '<div class="card-body" style="padding:var(--space-8);">' +
            '<div style="width:56px;height:56px;border-radius:50%;background-color:var(--accent-light,#fff3ed);color:var(--accent,#E17B47);display:flex;align-items:center;justify-content:center;margin:0 auto var(--space-5);">' +
              '<i class="fa-duotone fa-light fa-circle-user"></i>' +
            '</div>' +
            '<h2 class="h3 mb-2" style="color:var(--text-primary);">' + (reason === 'admin' ? 'Admin Access Required' : 'Not Logged In') + '</h2>' +
            '<p style="color:var(--text-secondary);margin-bottom:var(--space-6);">' + msg + '</p>' +
            '<a href="' + loginUrl + '" class="btn btn-primary">Go to Login</a>' +
          '</div>' +
        '</div>' +
      '</div>';
  }

  // --- Toast ---
  function toast(message, type) {
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var el = document.createElement('div');
    el.className = 'toast toast-' + (type || 'info');

    var icons = {
      success: '<i class="fa-duotone fa-light fa-circle-check"></i>',
      error: '<i class="fa-duotone fa-light fa-circle-xmark"></i>',
      info: '<i class="fa-duotone fa-light fa-circle-info"></i>'
    };

    var colorVar = type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info';
    el.innerHTML =
      '<span class="toast-icon" style="color:var(--' + colorVar + ');">' + (icons[type] || icons.info) + '</span>' +
      '<div class="toast-content"><div class="toast-message">' + esc(message) + '</div></div>';

    container.appendChild(el);
    setTimeout(function() {
      el.style.opacity = '0';
      el.style.transform = 'translateX(100%)';
      el.style.transition = 'all 0.2s ease';
      setTimeout(function() { el.remove(); }, 200);
    }, 4000);
  }

  // --- Confirm dialog ---
  function confirm(title, message) {
    return new Promise(function(resolve) {
      var overlay = document.getElementById('confirmOverlay');
      document.getElementById('confirmTitle').textContent = title;
      document.getElementById('confirmMessage').textContent = message;
      overlay.classList.add('active');

      function close(result) {
        overlay.classList.remove('active');
        cleanup();
        resolve(result);
      }

      function cleanup() {
        document.getElementById('confirmDeleteBtn').removeEventListener('click', onYes);
        document.getElementById('confirmCancelBtn').removeEventListener('click', onNo);
        overlay.removeEventListener('click', onOverlay);
      }

      function onYes() { close(true); }
      function onNo() { close(false); }
      function onOverlay(e) { if (e.target === overlay) close(false); }

      document.getElementById('confirmDeleteBtn').addEventListener('click', onYes);
      document.getElementById('confirmCancelBtn').addEventListener('click', onNo);
      overlay.addEventListener('click', onOverlay);
    });
  }

  // --- Navigation ---
  function initNav() {
    document.querySelectorAll('[data-section]').forEach(function(el) {
      el.addEventListener('click', function(e) {
        e.preventDefault();
        navigateTo(el.dataset.section);
      });
    });

    window.addEventListener('hashchange', function() {
      var hash = location.hash.replace('#', '') || 'dashboard';
      if (hash !== state.currentSection) {
        navigateTo(hash, false);
      }
    });

    var initial = location.hash.replace('#', '') || 'dashboard';
    navigateTo(initial, false);
  }

  function navigateTo(name, pushHash) {
    if (pushHash !== false) location.hash = '#' + name;
    state.currentSection = name;

    document.querySelectorAll('.admin-section').forEach(function(s) { s.classList.remove('active'); });
    document.querySelectorAll('.admin-nav-item').forEach(function(n) { n.classList.remove('active'); });

    var section = document.getElementById('section-' + name);
    var nav = document.querySelector('[data-section="' + name + '"]');
    if (section) section.classList.add('active');
    if (nav) nav.classList.add('active');

    closeMobileSidebar();

    var loaders = {
      dashboard: loadDashboard,
      users: function() { loadUsers(1); },
      pixels: function() { loadPixels(1); },
      sessions: function() { loadSessions(1); },
      transactions: function() { loadTransactions(1); },
      achievements: loadAchievements
    };
    if (loaders[name]) loaders[name]();
  }

  // --- Mobile ---
  function initMobile() {
    var hamburger = document.getElementById('hamburgerBtn');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (hamburger && sidebar && overlay) {
      hamburger.addEventListener('click', function() {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('open');
      });
      overlay.addEventListener('click', closeMobileSidebar);
    }
  }

  function closeMobileSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
  }

  // --- Dashboard ---
  async function loadDashboard() {
    try {
      var data = await api('dashboard');
      if (!data.success) {
        toast(data.message || 'Failed to load dashboard', 'error');
        return;
      }

      var stats = data.stats;
      var cards = [
        { label: 'Total Users', value: stats.total_users, color: 'accent', icon: '<i class="fa-duotone fa-light fa-users"></i>' },
        { label: 'Total Pixels', value: stats.total_pixels, color: 'success', icon: '<i class="fa-duotone fa-light fa-puzzle-piece"></i>' },
        { label: 'Games Played', value: stats.total_games, color: 'info', icon: '<i class="fa-duotone fa-light fa-gamepad"></i>' },
        { label: 'Total Score', value: stats.total_score.toLocaleString(), color: 'warning', icon: '<i class="fa-duotone fa-light fa-star"></i>' },
        { label: 'Gems in Circulation', value: stats.total_balance.toLocaleString(), color: 'info', icon: '<i class="fa-duotone fa-light fa-gem"></i>' },
        { label: 'Users Today', value: stats.users_today, color: 'accent', icon: '<i class="fa-duotone fa-light fa-user-plus"></i>' },
        { label: 'Games Today', value: stats.games_today, color: 'info', icon: '<i class="fa-duotone fa-light fa-gamepad"></i>' },
        { label: 'Pixels Today', value: stats.pixels_today, color: 'success', icon: '<i class="fa-duotone fa-light fa-puzzle-piece"></i>' }
      ];

      document.getElementById('dashboardStats').innerHTML = cards.map(function(c) {
        return '<div class="admin-stat-card ' + c.color + '">' +
          '<div class="admin-stat-icon ' + c.color + '">' + c.icon + '</div>' +
          '<div class="admin-stat-label">' + c.label + '</div>' +
          '<div class="admin-stat-value">' + c.value + '</div>' +
        '</div>';
      }).join('');

      // Top players
      var topHtml = '<table class="admin-table"><thead><tr><th>#</th><th>Player</th><th>Score</th><th>Level</th><th>Gems</th></tr></thead><tbody>';
      if (data.top_players.length === 0) {
        topHtml += '<tr><td colspan="5" class="admin-table-empty">No players yet</td></tr>';
      } else {
        data.top_players.forEach(function(p, i) {
          topHtml += '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td><div class="admin-user-cell"><div class="avatar avatar-sm" style="background-color:' + (p.avatar_color || 'var(--accent)') + ';">' + esc(p.username.charAt(0).toUpperCase()) + '</div><span class="admin-user-name">' + esc(p.username) + '</span></div></td>' +
            '<td class="admin-score">' + p.total_score.toLocaleString() + '</td>' +
            '<td><span class="badge badge-default">' + p.level + '</span></td>' +
            '<td><span class="admin-gem-positive">' + p.balance.toLocaleString() + '</span></td>' +
          '</tr>';
        });
      }
      topHtml += '</tbody></table>';
      document.getElementById('topPlayers').innerHTML = topHtml;

      // Recent pixels
      var pixHtml = '<table class="admin-table"><thead><tr><th>Position</th><th>Color</th><th>By</th><th>When</th></tr></thead><tbody>';
      if (data.recent_pixels.length === 0) {
        pixHtml += '<tr><td colspan="4" class="admin-table-empty">No pixels placed yet</td></tr>';
      } else {
        data.recent_pixels.forEach(function(p) {
          pixHtml += '<tr>' +
            '<td><code style="font-family:var(--font-mono,monospace);font-size:var(--text-xs);">(' + p.x + ', ' + p.y + ')</code></td>' +
            '<td><span class="admin-color-swatch" style="background-color:' + esc(p.color) + ';"></span>' + esc(p.color) + '</td>' +
            '<td>' + esc(p.username || 'Unknown') + '</td>' +
            '<td style="color:var(--text-muted);font-size:var(--text-xs);">' + timeAgo(p.placed_at) + '</td>' +
          '</tr>';
        });
      }
      pixHtml += '</tbody></table>';
      document.getElementById('recentPixels').innerHTML = pixHtml;

    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load dashboard', 'error');
    }
  }

  // --- Users ---
  async function loadUsers(page) {
    state.pages.users = page;
    try {
      var search = (document.getElementById('userSearch') || {}).value || '';
      var data = await api('users', { params: { page: page, search: search } });
      if (!data.success) {
        toast(data.message || 'Failed to load users', 'error');
        return;
      }

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>User</th><th>Role</th><th>Gems</th><th>XP</th><th>Level</th><th>Pixels</th><th>Games</th><th>Score</th><th>Last Login</th><th>Actions</th></tr></thead><tbody>';
      if (data.users.length === 0) {
        html += '<tr><td colspan="11" class="admin-table-empty">No users found</td></tr>';
      } else {
        data.users.forEach(function(u) {
          html += '<tr>' +
            '<td style="color:var(--text-muted);">#' + u.id + '</td>' +
            '<td><div class="admin-user-cell"><div class="avatar avatar-sm" style="background-color:' + (u.avatar_color || 'var(--accent)') + ';">' + esc(u.username.charAt(0).toUpperCase()) + '</div><div><div class="admin-user-name">' + esc(u.username) + '</div><div class="admin-user-email">' + esc(u.email || '') + '</div></div></div></td>' +
            '<td><span class="badge ' + (u.role === 'admin' ? 'badge-admin' : 'badge-user') + '">' + u.role + '</span></td>' +
            '<td><span class="admin-gem-positive">' + u.balance.toLocaleString() + '</span></td>' +
            '<td>' + u.xp.toLocaleString() + '</td>' +
            '<td><span class="badge badge-default">' + u.level + '</span></td>' +
            '<td>' + (u.total_pixels_placed || 0).toLocaleString() + '</td>' +
            '<td>' + (u.total_games_played || 0).toLocaleString() + '</td>' +
            '<td class="admin-score">' + (u.total_score || 0).toLocaleString() + '</td>' +
            '<td style="font-size:var(--text-xs);color:var(--text-muted);">' + (u.last_login_date ? new Date(u.last_login_date).toLocaleDateString() : '\u2014') + '</td>' +
            '<td><div style="display:flex;gap:var(--space-1);">' +
              '<button class="admin-action-btn admin-action-edit" data-edit="' + u.id + '" data-username="' + esc(u.username) + '" data-email="' + esc(u.email || '') + '" data-role="' + u.role + '" data-balance="' + u.balance + '" data-xp="' + u.xp + '" data-level="' + u.level + '" data-avatar-color="' + (u.avatar_color || '#7c3aed') + '" data-streak-days="' + (u.streak_days || 0) + '">Edit</button>' +
              (u.role !== 'admin' ? '<button class="admin-action-btn admin-action-delete" data-delete="' + u.id + '">Delete</button>' : '') +
            '</div></td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('usersTable').innerHTML = html;
      renderPagination('users', data.page, data.pages);
    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load users', 'error');
    }
  }

  // --- Pixels ---
  async function loadPixels(page) {
    state.pages.pixels = page;
    try {
      var data = await api('pixels', { params: { page: page } });
      if (!data.success) {
        toast(data.message || 'Failed to load pixels', 'error');
        return;
      }

      var html = '<table class="admin-table"><thead><tr><th>X</th><th>Y</th><th>Color</th><th>Preview</th><th>Placed By</th><th>When</th></tr></thead><tbody>';
      if (data.pixels.length === 0) {
        html += '<tr><td colspan="6" class="admin-table-empty">No pixels placed yet</td></tr>';
      } else {
        data.pixels.forEach(function(p) {
          html += '<tr>' +
            '<td>' + p.x + '</td>' +
            '<td>' + p.y + '</td>' +
            '<td><span class="admin-color-swatch" style="background-color:' + esc(p.color) + ';"></span>' + esc(p.color) + '</td>' +
            '<td><span class="admin-color-swatch admin-color-swatch-lg" style="background-color:' + esc(p.color) + ';"></span></td>' +
            '<td>' + esc(p.username || 'Unknown') + '</td>' +
            '<td style="font-size:var(--text-xs);color:var(--text-muted);">' + timeAgo(p.placed_at) + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('pixelsTable').innerHTML = html;
      renderPagination('pixels', data.page, data.pages);
    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load pixels', 'error');
    }
  }

  // --- Sessions ---
  async function loadSessions(page) {
    state.pages.sessions = page;
    try {
      var data = await api('sessions', { params: { page: page } });
      if (!data.success) {
        toast(data.message || 'Failed to load sessions', 'error');
        return;
      }

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>Player</th><th>Score</th><th>Combo</th><th>Moves</th><th>Status</th><th>Started</th><th>Ended</th></tr></thead><tbody>';
      if (data.sessions.length === 0) {
        html += '<tr><td colspan="8" class="admin-table-empty">No sessions found</td></tr>';
      } else {
        data.sessions.forEach(function(s) {
          var statusClass = s.status === 'completed' ? 'badge-completed' : s.status === 'active' ? 'badge-active' : 'badge-abandoned';
          html += '<tr>' +
            '<td style="color:var(--text-muted);">#' + s.id + '</td>' +
            '<td>' + esc(s.username || 'Unknown') + '</td>' +
            '<td class="admin-score">' + (s.score || 0).toLocaleString() + '</td>' +
            '<td>' + (s.combo_max || 0) + 'x</td>' +
            '<td>' + (s.moves_left || 0) + '</td>' +
            '<td><span class="badge ' + statusClass + '">' + s.status + '</span></td>' +
            '<td style="font-size:var(--text-xs);color:var(--text-muted);">' + timeAgo(s.started_at) + '</td>' +
            '<td style="font-size:var(--text-xs);color:var(--text-muted);">' + (s.completed_at ? timeAgo(s.completed_at) : '\u2014') + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('sessionsTable').innerHTML = html;
      renderPagination('sessions', data.page, data.pages);
    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load sessions', 'error');
    }
  }

  // --- Transactions ---
  async function loadTransactions(page) {
    state.pages.transactions = page;
    try {
      var data = await api('transactions', { params: { page: page } });
      if (!data.success) {
        toast(data.message || 'Failed to load transactions', 'error');
        return;
      }

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Type</th><th>Description</th><th>When</th></tr></thead><tbody>';
      if (data.transactions.length === 0) {
        html += '<tr><td colspan="6" class="admin-table-empty">No transactions found</td></tr>';
      } else {
        data.transactions.forEach(function(t) {
          var amtClass = t.amount >= 0 ? 'admin-gem-positive' : 'admin-gem-negative';
          var amtSign = t.amount >= 0 ? '+' : '';
          var typeClass = t.type === 'earn' ? 'badge-earn' : 'badge-spend';
          html += '<tr>' +
            '<td style="color:var(--text-muted);">#' + t.id + '</td>' +
            '<td>' + esc(t.username || 'Unknown') + '</td>' +
            '<td class="' + amtClass + '">' + amtSign + t.amount + ' gem</td>' +
            '<td><span class="badge ' + typeClass + '">' + t.type + '</span></td>' +
            '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + esc(t.description || '') + '</td>' +
            '<td style="font-size:var(--text-xs);color:var(--text-muted);">' + timeAgo(t.created_at) + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('transactionsTable').innerHTML = html;
      renderPagination('transactions', data.page, data.pages);
    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load transactions', 'error');
    }
  }

  // --- Achievements ---
  async function loadAchievements() {
    try {
      var data = await api('achievements');
      if (!data.success) {
        toast(data.message || 'Failed to load achievements', 'error');
        return;
      }

      if (data.achievements.length === 0) {
        document.getElementById('achievementsList').innerHTML = '<div class="admin-table-empty">No achievements defined</div>';
        return;
      }

      var achievementIcons = {
        spark: 'fa-wand-magic-sparkles',
        chain: 'fa-link',
        flame: 'fa-fire',
        trophy: 'fa-trophy',
        crown: 'fa-crown',
        diamond: 'fa-gem',
        gamepad: 'fa-gamepad',
        star: 'fa-star',
        lightning: 'fa-bolt',
        brush: 'fa-paintbrush',
        palette: 'fa-palette',
        art: 'fa-pen-nib',
        canvas: 'fa-table-cells-large',
        fire: 'fa-fire',
        calendar: 'fa-calendar',
        'arrow-up': 'fa-arrow-up'
      };

      document.getElementById('achievementsList').innerHTML = data.achievements.map(function(a) {
        var iconClass = achievementIcons[a.icon] || 'fa-circle-question';
        return '<div class="admin-achievement-card">' +
          '<div class="admin-achievement-icon"><i class="fa-duotone fa-light ' + iconClass + '"></i></div>' +
          '<div class="admin-achievement-info">' +
            '<div class="admin-achievement-name">' + esc(a.name) + '</div>' +
            '<div class="admin-achievement-desc">' + esc(a.description || '') + '</div>' +
            '<div class="admin-achievement-meta">' +
              '<span class="admin-achievement-reward">+' + a.reward + ' gem</span>' +
              '<span class="admin-achievement-earned">' + a.earned_count + ' player(s)</span>' +
            '</div>' +
          '</div>' +
        '</div>';
      }).join('');
    } catch (e) {
      if (!state.authChecked) return;
      toast(e.message || 'Failed to load achievements', 'error');
    }
  }

  // --- Pagination ---
  function renderPagination(section, page, pages) {
    var container = document.getElementById(section + 'Pagination');
    if (!container || pages <= 1) { if (container) container.innerHTML = ''; return; }

    var html = '<div class="admin-pagination">';
    html += '<button ' + (page <= 1 ? 'disabled' : '') + ' data-page-section="' + section + '" data-page-num="' + (page - 1) + '">&laquo;</button>';

    var range = getPageRange(page, pages);
    range.forEach(function(p) {
      if (p === '...') {
        html += '<span style="color:var(--text-muted);padding:0 4px;">...</span>';
      } else {
        html += '<button class="' + (p === page ? 'active-page' : '') + '" data-page-section="' + section + '" data-page-num="' + p + '">' + p + '</button>';
      }
    });

    html += '<button ' + (page >= pages ? 'disabled' : '') + ' data-page-section="' + section + '" data-page-num="' + (page + 1) + '">&raquo;</button>';
    html += '<span class="admin-page-info">Page ' + page + ' / ' + pages + '</span>';
    html += '</div>';
    container.innerHTML = html;
  }

  function getPageRange(current, total) {
    if (total <= 7) return Array.from({ length: total }, function(_, i) { return i + 1; });
    var pages = [];
    pages.push(1);
    if (current > 3) pages.push('...');
    for (var i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
      pages.push(i);
    }
    if (current < total - 2) pages.push('...');
    pages.push(total);
    return pages;
  }

  function loadPage(section, page) {
    var loaders = { users: loadUsers, pixels: loadPixels, sessions: loadSessions, transactions: loadTransactions };
    if (loaders[section]) loaders[section](page);
  }

  // --- Edit modal ---
  function openEditModal(id, username, email, role, balance, xp, level, avatarColor, streakDays) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editUsername').value = username || '';
    document.getElementById('editEmail').value = email || '';
    document.getElementById('editRole').value = role;
    document.getElementById('editBalance').value = balance;
    document.getElementById('editXp').value = xp || 0;
    document.getElementById('editLevel').value = level;
    document.getElementById('editAvatarColor').value = avatarColor || '#7c3aed';
    document.getElementById('editStreakDays').value = streakDays || 0;
    document.getElementById('editModal').classList.add('active');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
  }

  async function handleSaveUser() {
    var id = parseInt(document.getElementById('editUserId').value);
    var email = document.getElementById('editEmail').value;
    var role = document.getElementById('editRole').value;
    var balance = parseInt(document.getElementById('editBalance').value) || 0;
    var xp = parseInt(document.getElementById('editXp').value) || 0;
    var level = parseInt(document.getElementById('editLevel').value) || 1;
    var avatarColor = document.getElementById('editAvatarColor').value;
    var streakDays = parseInt(document.getElementById('editStreakDays').value) || 0;

    try {
      var data = await api('user_update', { body: { id: id, email: email, role: role, balance: balance, xp: xp, level: level, avatar_color: avatarColor, streak_days: streakDays } });
      if (data.success) {
        toast('User updated successfully', 'success');
        closeEditModal();
        loadUsers(state.pages.users);
      } else {
        toast(data.message || 'Failed to update user', 'error');
      }
    } catch (e) {
      toast(e.message || 'Failed to update user', 'error');
    }
  }

  async function handleDeleteUser(id) {
    var yes = await confirm('Delete User', 'This action cannot be undone. All user data will be permanently removed.');
    if (!yes) return;

    try {
      var data = await api('user_delete', { body: { id: id } });
      if (data.success) {
        toast('User deleted', 'success');
        loadUsers(state.pages.users);
      } else {
        toast(data.message || 'Failed to delete user', 'error');
      }
    } catch (e) {
      toast(e.message || 'Failed to delete user', 'error');
    }
  }

  // --- Events ---
  function initEvents() {
    document.addEventListener('click', function(e) {
      var target = e.target;

      var editBtn = target.closest('[data-edit]');
      if (editBtn) {
        openEditModal(
          editBtn.dataset.edit,
          editBtn.dataset.username,
          editBtn.dataset.email,
          editBtn.dataset.role,
          editBtn.dataset.balance,
          editBtn.dataset.xp,
          editBtn.dataset.level,
          editBtn.dataset.avatarColor,
          editBtn.dataset.streakDays
        );
        return;
      }

      var deleteBtn = target.closest('[data-delete]');
      if (deleteBtn) {
        handleDeleteUser(parseInt(deleteBtn.dataset.delete));
        return;
      }

      if (target.id === 'modalCloseBtn' || target.id === 'modalCancelBtn' || target.id === 'editModal') {
        closeEditModal();
        return;
      }

      if (target.id === 'modalSaveBtn') {
        handleSaveUser();
        return;
      }

      var pageBtn = target.closest('[data-page-section]');
      if (pageBtn && !pageBtn.disabled) {
        loadPage(pageBtn.dataset.pageSection, parseInt(pageBtn.dataset.pageNum));
        return;
      }
    });

    var searchInput = document.getElementById('userSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(state.searchTimeout);
        state.searchTimeout = setTimeout(function() {
          loadUsers(1);
        }, 300);
      });
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeEditModal();
        var confirmOverlay = document.getElementById('confirmOverlay');
        if (confirmOverlay) confirmOverlay.classList.remove('active');
      }
    });
  }

  // --- Helpers ---
  function esc(s) {
    if (!s) return '';
    var div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
  }

  function timeAgo(dateStr) {
    if (!dateStr) return '\u2014';
    var d = new Date(dateStr);
    if (isNaN(d.getTime())) return '\u2014';
    var diff = Math.floor((Date.now() - d.getTime()) / 1000);
    if (diff < 0) diff = 0;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return d.toLocaleDateString();
  }

  // --- Init ---
  document.addEventListener('DOMContentLoaded', async function() {
    initMobile();
    initEvents();

    // Check auth first before loading anything
    try {
      var res = await fetch('/codes/pixelforge/api/auth.php?action=me', { credentials: 'same-origin' });
      var ct = res.headers.get('content-type') || '';
      if (ct.indexOf('application/json') === -1) {
        showAuthState('login');
        return;
      }
      var authData = await res.json();
      if (!authData.success || !authData.user) {
        showAuthState('login');
        return;
      }
      if (authData.user.role !== 'admin') {
        showAuthState('admin');
        return;
      }
      state.authChecked = true;
      state.isAdmin = true;
      initNav();
    } catch (e) {
      showAuthState('login');
    }
  });

})();
