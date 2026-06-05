/**
 * PixelForge Admin Panel — SPA Controller
 * Hash-based routing, no dependencies.
 */
(function() {
  'use strict';

  var API_BASE = '/codes/pixelforge/admin/api.php';
  var PAGE_SIZE = { users: 20, pixels: 50, sessions: 30, transactions: 50 };

  var state = {
    currentSection: 'dashboard',
    pages: { users: 1, pixels: 1, sessions: 1, transactions: 1 },
    searchTimeout: null
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

    return fetch(url, config).then(function(r) { return r.json(); });
  }

  // --- Toast ---
  function toast(message, type) {
    var container = document.getElementById('toastContainer');
    var el = document.createElement('div');
    el.className = 'toast toast-' + (type || 'info');

    var icons = {
      success: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M5.5 9l2.5 2.5L12.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
      error: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M6 6l6 6M12 6l-6 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
      info: '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M9 8v5M9 5.5h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>'
    };

    el.innerHTML =
      '<span class="toast-icon" style="color: var(--' + (type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info') + ');">' + (icons[type] || icons.info) + '</span>' +
      '<div class="toast-content"><div class="toast-message">' + esc(message) + '</div></div>';

    container.appendChild(el);
    setTimeout(function() {
      el.style.opacity = '0';
      el.style.transform = 'translateX(100%)';
      el.style.transition = 'all 0.2s ease';
      setTimeout(function() { el.remove(); }, 200);
    }, 3500);
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
    document.getElementById('hamburgerBtn').addEventListener('click', function() {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('open');
    });
    document.getElementById('sidebarOverlay').addEventListener('click', closeMobileSidebar);
  }

  function closeMobileSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
  }

  // --- Dashboard ---
  async function loadDashboard() {
    try {
      var data = await api('dashboard');
      if (!data.success) return;

      var stats = data.stats;
      var cards = [
        { label: 'Total Users', value: stats.total_users, color: 'accent', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="6" cy="5" r="2" stroke="currentColor" stroke-width="1.3"/><path d="M3 13c0-1.7 1.3-3 3-3s3 1.3 3 3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>' },
        { label: 'Total Pixels', value: stats.total_pixels, color: 'success', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.3"/></svg>' },
        { label: 'Games Played', value: stats.total_games, color: 'info', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><polygon points="6,3 13,8 6,13" fill="currentColor"/></svg>' },
        { label: 'Total Score', value: stats.total_score.toLocaleString(), color: 'warning', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2l1.5 3 3.5.5-2.5 2.5.6 3.5L8 9.5 4.9 11.5l.6-3.5L3 5.5l3.5-.5z" fill="currentColor"/></svg>' },
        { label: 'Gems in Circulation', value: stats.total_balance.toLocaleString(), color: 'info', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><polygon points="8,1 15,5 15,11 8,15 1,11 1,5" stroke="currentColor" stroke-width="1.3"/></svg>' },
        { label: 'Users Today', value: stats.users_today, color: 'accent', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="6" r="2.5" stroke="currentColor" stroke-width="1.3"/><path d="M4 14c0-2.2 1.8-4 4-4s4 1.8 4 4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>' },
        { label: 'Games Today', value: stats.games_today, color: 'info', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><polygon points="6,3 13,8 6,13" fill="currentColor"/></svg>' },
        { label: 'Pixels Today', value: stats.pixels_today, color: 'success', icon: '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.3"/></svg>' }
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
            '<td><code style="font-family: var(--font-mono, monospace); font-size: var(--text-xs);">(' + p.x + ', ' + p.y + ')</code></td>' +
            '<td><span class="admin-color-swatch" style="background-color:' + esc(p.color) + ';"></span>' + esc(p.color) + '</td>' +
            '<td>' + esc(p.username || 'Unknown') + '</td>' +
            '<td style="color: var(--text-muted); font-size: var(--text-xs);">' + timeAgo(p.placed_at) + '</td>' +
          '</tr>';
        });
      }
      pixHtml += '</tbody></table>';
      document.getElementById('recentPixels').innerHTML = pixHtml;

    } catch (e) {
      toast('Failed to load dashboard', 'error');
    }
  }

  // --- Users ---
  async function loadUsers(page) {
    state.pages.users = page;
    try {
      var search = (document.getElementById('userSearch') || {}).value || '';
      var data = await api('users', { params: { page: page, search: search } });
      if (!data.success) return;

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>User</th><th>Role</th><th>Gems</th><th>XP</th><th>Level</th><th>Pixels</th><th>Games</th><th>Score</th><th>Joined</th><th>Actions</th></tr></thead><tbody>';
      if (data.users.length === 0) {
        html += '<tr><td colspan="11" class="admin-table-empty">No users found</td></tr>';
      } else {
        data.users.forEach(function(u) {
          html += '<tr>' +
            '<td style="color: var(--text-muted);">#' + u.id + '</td>' +
            '<td><div class="admin-user-cell"><div class="avatar avatar-sm" style="background-color:' + (u.avatar_color || 'var(--accent)') + ';">' + esc(u.username.charAt(0).toUpperCase()) + '</div><div><div class="admin-user-name">' + esc(u.username) + '</div><div class="admin-user-email">' + esc(u.email || '') + '</div></div></div></td>' +
            '<td><span class="badge ' + (u.role === 'admin' ? 'badge-admin' : 'badge-user') + '">' + u.role + '</span></td>' +
            '<td><span class="admin-gem-positive">' + u.balance.toLocaleString() + '</span></td>' +
            '<td>' + u.xp.toLocaleString() + '</td>' +
            '<td><span class="badge badge-default">' + u.level + '</span></td>' +
            '<td>' + (u.total_pixels_placed || 0).toLocaleString() + '</td>' +
            '<td>' + (u.total_games_played || 0).toLocaleString() + '</td>' +
            '<td class="admin-score">' + (u.total_score || 0).toLocaleString() + '</td>' +
            '<td style="font-size: var(--text-xs); color: var(--text-muted);">' + new Date(u.created_at).toLocaleDateString() + '</td>' +
            '<td><div style="display:flex;gap:var(--space-1);">' +
              '<button class="admin-action-btn admin-action-edit" data-edit="' + u.id + '" data-role="' + u.role + '" data-balance="' + u.balance + '" data-level="' + u.level + '">Edit</button>' +
              (u.role !== 'admin' ? '<button class="admin-action-btn admin-action-delete" data-delete="' + u.id + '">Delete</button>' : '') +
            '</div></td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('usersTable').innerHTML = html;
      renderPagination('users', data.page, data.pages);
    } catch (e) {
      toast('Failed to load users', 'error');
    }
  }

  // --- Pixels ---
  async function loadPixels(page) {
    state.pages.pixels = page;
    try {
      var data = await api('pixels', { params: { page: page } });
      if (!data.success) return;

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
            '<td style="font-size: var(--text-xs); color: var(--text-muted);">' + timeAgo(p.placed_at) + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('pixelsTable').innerHTML = html;
      renderPagination('pixels', data.page, data.pages);
    } catch (e) {
      toast('Failed to load pixels', 'error');
    }
  }

  // --- Sessions ---
  async function loadSessions(page) {
    state.pages.sessions = page;
    try {
      var data = await api('sessions', { params: { page: page } });
      if (!data.success) return;

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>Player</th><th>Score</th><th>Combo</th><th>Moves</th><th>Status</th><th>Started</th><th>Ended</th></tr></thead><tbody>';
      if (data.sessions.length === 0) {
        html += '<tr><td colspan="8" class="admin-table-empty">No sessions found</td></tr>';
      } else {
        data.sessions.forEach(function(s) {
          var statusClass = s.status === 'completed' ? 'badge-completed' : s.status === 'active' ? 'badge-active' : 'badge-abandoned';
          html += '<tr>' +
            '<td style="color: var(--text-muted);">#' + s.id + '</td>' +
            '<td>' + esc(s.username || 'Unknown') + '</td>' +
            '<td class="admin-score">' + (s.score || 0).toLocaleString() + '</td>' +
            '<td>' + (s.combo_max || 0) + 'x</td>' +
            '<td>' + (s.moves_left || 0) + '</td>' +
            '<td><span class="badge ' + statusClass + '">' + s.status + '</span></td>' +
            '<td style="font-size: var(--text-xs); color: var(--text-muted);">' + timeAgo(s.started_at) + '</td>' +
            '<td style="font-size: var(--text-xs); color: var(--text-muted);">' + (s.completed_at ? timeAgo(s.completed_at) : '—') + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('sessionsTable').innerHTML = html;
      renderPagination('sessions', data.page, data.pages);
    } catch (e) {
      toast('Failed to load sessions', 'error');
    }
  }

  // --- Transactions ---
  async function loadTransactions(page) {
    state.pages.transactions = page;
    try {
      var data = await api('transactions', { params: { page: page } });
      if (!data.success) return;

      var html = '<table class="admin-table"><thead><tr><th>ID</th><th>User</th><th>Amount</th><th>Type</th><th>Description</th><th>When</th></tr></thead><tbody>';
      if (data.transactions.length === 0) {
        html += '<tr><td colspan="6" class="admin-table-empty">No transactions found</td></tr>';
      } else {
        data.transactions.forEach(function(t) {
          var amtClass = t.amount >= 0 ? 'admin-gem-positive' : 'admin-gem-negative';
          var amtSign = t.amount >= 0 ? '+' : '';
          var typeClass = t.type === 'earn' ? 'badge-earn' : 'badge-spend';
          html += '<tr>' +
            '<td style="color: var(--text-muted);">#' + t.id + '</td>' +
            '<td>' + esc(t.username || 'Unknown') + '</td>' +
            '<td class="' + amtClass + '">' + amtSign + t.amount + ' gem</td>' +
            '<td><span class="badge ' + typeClass + '">' + t.type + '</span></td>' +
            '<td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + esc(t.description || '') + '</td>' +
            '<td style="font-size: var(--text-xs); color: var(--text-muted);">' + timeAgo(t.created_at) + '</td>' +
          '</tr>';
        });
      }
      html += '</tbody></table>';
      document.getElementById('transactionsTable').innerHTML = html;
      renderPagination('transactions', data.page, data.pages);
    } catch (e) {
      toast('Failed to load transactions', 'error');
    }
  }

  // --- Achievements ---
  async function loadAchievements() {
    try {
      var data = await api('achievements');
      if (!data.success) return;

      if (data.achievements.length === 0) {
        document.getElementById('achievementsList').innerHTML = '<div class="admin-table-empty">No achievements defined</div>';
        return;
      }

      document.getElementById('achievementsList').innerHTML = data.achievements.map(function(a) {
        return '<div class="admin-achievement-card">' +
          '<div class="admin-achievement-icon">' + esc(a.icon || '?') + '</div>' +
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
      toast('Failed to load achievements', 'error');
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
        html += '<span style="color: var(--text-muted); padding: 0 4px;">...</span>';
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
  function openEditModal(id, role, balance, level) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editRole').value = role;
    document.getElementById('editBalance').value = balance;
    document.getElementById('editLevel').value = level;
    document.getElementById('editModal').classList.add('active');
  }

  function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
  }

  async function handleSaveUser() {
    var id = parseInt(document.getElementById('editUserId').value);
    var role = document.getElementById('editRole').value;
    var balance = parseInt(document.getElementById('editBalance').value) || 0;
    var level = parseInt(document.getElementById('editLevel').value) || 1;

    try {
      var data = await api('user_update', { body: { id: id, role: role, balance: balance, level: level } });
      if (data.success) {
        toast('User updated successfully', 'success');
        closeEditModal();
        loadUsers(state.pages.users);
      } else {
        toast(data.message || 'Failed to update user', 'error');
      }
    } catch (e) {
      toast('Failed to update user', 'error');
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
      toast('Failed to delete user', 'error');
    }
  }

  // --- Events ---
  function initEvents() {
    // Delegated click handler
    document.addEventListener('click', function(e) {
      var target = e.target;

      // Edit user
      var editBtn = target.closest('[data-edit]');
      if (editBtn) {
        openEditModal(
          editBtn.dataset.edit,
          editBtn.dataset.role,
          editBtn.dataset.balance,
          editBtn.dataset.level
        );
        return;
      }

      // Delete user
      var deleteBtn = target.closest('[data-delete]');
      if (deleteBtn) {
        handleDeleteUser(parseInt(deleteBtn.dataset.delete));
        return;
      }

      // Modal close
      if (target.id === 'modalCloseBtn' || target.id === 'modalCancelBtn' || target.id === 'editModal') {
        closeEditModal();
        return;
      }

      // Modal save
      if (target.id === 'modalSaveBtn') {
        handleSaveUser();
        return;
      }

      // Pagination
      var pageBtn = target.closest('[data-page-section]');
      if (pageBtn && !pageBtn.disabled) {
        loadPage(pageBtn.dataset.pageSection, parseInt(pageBtn.dataset.pageNum));
        return;
      }
    });

    // Search
    var searchInput = document.getElementById('userSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        clearTimeout(state.searchTimeout);
        state.searchTimeout = setTimeout(function() {
          loadUsers(1);
        }, 300);
      });
    }

    // Keyboard
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeEditModal();
        document.getElementById('confirmOverlay').classList.remove('active');
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
    if (!dateStr) return '—';
    var diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  }

  // --- Init ---
  document.addEventListener('DOMContentLoaded', function() {
    initNav();
    initMobile();
    initEvents();
  });

})();
