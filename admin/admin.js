/* ============================================================
   PixelForge — Admin Panel JavaScript
   Modular SPA with hash routing
   ============================================================ */
(function() {
    'use strict';

    const API = '/codes/pixelforge/admin/api.php';
    const PAGESIZE = { users: 20, pixels: 50, sessions: 30, transactions: 50 };

    const state = {
        currentSection: 'dashboard',
        pages: { users: 1, pixels: 1, sessions: 1, transactions: 1 },
        searchTimeout: null
    };

    /* ── API Helper ── */
    async function api(action, opts = {}) {
        let url = `${API}?action=${action}`;
        if (opts.params) {
            for (const [k, v] of Object.entries(opts.params)) {
                url += `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`;
            }
        }
        const fetchOpts = { credentials: 'same-origin' };
        if (opts.method === 'POST') {
            fetchOpts.method = 'POST';
            fetchOpts.headers = { 'Content-Type': 'application/json' };
            fetchOpts.body = JSON.stringify(opts.body || {});
        }
        const res = await fetch(url, fetchOpts);
        return res.json();
    }

    /* ── Toast System ── */
    function toast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const icons = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `${icons[type] || icons.info}<span>${esc(message)}</span>`;
        container.appendChild(el);
        setTimeout(() => {
            el.classList.add('toast-out');
            el.addEventListener('animationend', () => el.remove());
        }, 3500);
    }

    /* ── Confirm Dialog ── */
    function confirm(title, message) {
        return new Promise(resolve => {
            const overlay = document.getElementById('confirm-overlay');
            overlay.querySelector('h3').textContent = title;
            overlay.querySelector('p').textContent = message;
            overlay.classList.add('open');

            const yesBtn = overlay.querySelector('.confirm-yes');
            const noBtn = overlay.querySelector('.confirm-no');

            function close(result) {
                overlay.classList.remove('open');
                yesBtn.removeEventListener('click', onYes);
                noBtn.removeEventListener('click', onNo);
                resolve(result);
            }
            function onYes() { close(true); }
            function onNo() { close(false); }
            yesBtn.addEventListener('click', onYes);
            noBtn.addEventListener('click', onNo);
            overlay.addEventListener('click', e => { if (e.target === overlay) close(false); }, { once: true });
        });
    }

    /* ── Navigation ── */
    function initNav() {
        document.querySelectorAll('[data-section]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                navigateTo(link.dataset.section);
            });
        });

        window.addEventListener('hashchange', () => {
            const hash = location.hash.slice(1) || 'dashboard';
            if (hash !== state.currentSection) navigateTo(hash, false);
        });

        const initial = location.hash.slice(1) || 'dashboard';
        navigateTo(initial, false);
    }

    function navigateTo(name, pushHash = true) {
        state.currentSection = name;
        if (pushHash) location.hash = name;

        document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
        document.querySelectorAll('[data-section]').forEach(a => a.classList.remove('active'));

        const section = document.getElementById('section-' + name);
        if (section) section.classList.add('active');
        const link = document.querySelector(`[data-section="${name}"]`);
        if (link) link.classList.add('active');

        closeMobileSidebar();

        const loaders = {
            dashboard: loadDashboard,
            users: loadUsers,
            pixels: loadPixels,
            sessions: loadSessions,
            transactions: loadTransactions,
            achievements: loadAchievements
        };
        if (loaders[name]) loaders[name]();
    }

    /* ── Mobile Sidebar ── */
    function initMobile() {
        const btn = document.getElementById('hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');

        if (btn) {
            btn.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            });
        }
        if (overlay) {
            overlay.addEventListener('click', closeMobileSidebar);
        }
    }

    function closeMobileSidebar() {
        document.querySelector('.sidebar')?.classList.remove('open');
        document.querySelector('.sidebar-overlay')?.classList.remove('open');
    }

    /* ── Dashboard ── */
    async function loadDashboard() {
        const data = await api('dashboard');
        if (!data.success) return toast('Failed to load dashboard', 'error');

        const s = data.stats;
        document.getElementById('dashboard-stats').innerHTML = [
            statCard('Users', s.total_users, 'purple', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'),
            statCard('Pixels', s.total_pixels.toLocaleString(), 'green', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>'),
            statCard('Games', s.total_games.toLocaleString(), 'blue', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'),
            statCard('Total Score', s.total_score.toLocaleString(), 'amber', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>'),
            statCard('Gems in Circ.', s.total_balance.toLocaleString(), 'cyan', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'),
            statCard('Users Today', s.users_today, 'pink', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'),
            statCard('Games Today', s.games_today, 'blue', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3h-8l-2 4h12z"/></svg>'),
            statCard('Pixels Today', s.pixels_today, 'green', '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 3v18"/></svg>')
        ].join('');

        let topHtml = '';
        data.top_players.forEach((p, i) => {
            const color = p.avatar_color || '#7c3aed';
            topHtml += `<tr>
                <td style="font-weight:600;color:#475569;">${i + 1}</td>
                <td><div class="user-cell"><div class="avatar" style="background:${esc(color)}">${esc((p.username || '?')[0])}</div><span class="user-cell-name">${esc(p.username)}</span></div></td>
                <td class="score-value">${p.total_score.toLocaleString()}</td>
                <td><span class="badge badge-user">Lv.${p.level}</span></td>
                <td><span class="gem">&#x1F48E;</span> ${p.balance.toLocaleString()}</td>
            </tr>`;
        });
        document.getElementById('top-players').innerHTML = topHtml || emptyRow(5, 'No players yet');

        let pixHtml = '';
        data.recent_pixels.forEach(p => {
            pixHtml += `<tr>
                <td style="font-family:var(--font-mono);font-size:0.78rem;">(${p.x}, ${p.y})</td>
                <td><div class="user-cell"><span class="color-swatch" style="background:${esc(p.color)}"></span><span style="font-family:var(--font-mono);font-size:0.78rem;color:#94a3b8;">${esc(p.color)}</span></div></td>
                <td>${esc(p.username || '?')}</td>
                <td style="color:#475569;">${timeAgo(p.placed_at)}</td>
            </tr>`;
        });
        document.getElementById('recent-pixels').innerHTML = pixHtml || emptyRow(4, 'No pixels placed yet');
    }

    function statCard(label, value, color, icon) {
        return `<div class="stat-card ${color}">
            <div class="stat-icon">${icon}</div>
            <div class="stat-label">${label}</div>
            <div class="stat-value">${value}</div>
        </div>`;
    }

    /* ── Users ── */
    async function loadUsers(page) {
        page = page || state.pages.users;
        state.pages.users = page;
        const search = document.getElementById('user-search')?.value || '';
        const data = await api('users', { params: { page, search } });
        if (!data.success) return toast('Failed to load users', 'error');

        let html = '';
        data.users.forEach(u => {
            const color = u.avatar_color || '#7c3aed';
            html += `<tr>
                <td style="color:#475569;font-size:0.78rem;">#${u.id}</td>
                <td><div class="user-cell"><div class="avatar" style="background:${esc(color)}">${esc((u.username || '?')[0])}</div><div class="user-cell-info"><div class="user-cell-name">${esc(u.username)}</div><div class="user-cell-email">${esc(u.email)}</div></div></div></td>
                <td><span class="badge badge-${u.role}">${u.role}</span></td>
                <td><span class="gem">&#x1F48E;</span> ${u.balance.toLocaleString()}</td>
                <td>${u.xp.toLocaleString()}</td>
                <td><span class="badge badge-user">Lv.${u.level}</span></td>
                <td>${u.total_pixels_placed}</td>
                <td>${u.total_games_played}</td>
                <td class="score-value">${u.total_score.toLocaleString()}</td>
                <td style="color:#475569;font-size:0.78rem;">${(u.created_at || '').split(' ')[0]}</td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <button class="action-btn action-btn-edit" data-edit="${u.id}" data-role="${u.role || 'user'}" data-balance="${u.balance || 0}" data-level="${u.level || 1}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            Edit
                        </button>
                        ${u.role !== 'admin' ? `<button class="action-btn action-btn-delete" data-delete="${u.id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            Del
                        </button>` : ''}
                    </div>
                </td>
            </tr>`;
        });
        document.getElementById('users-list').innerHTML = html || emptyRow(11, 'No users found');
        renderPagination('users', data.page, data.pages);
    }

    /* ── Pixels ── */
    async function loadPixels(page) {
        page = page || state.pages.pixels;
        state.pages.pixels = page;
        const data = await api('pixels', { params: { page } });
        if (!data.success) return toast('Failed to load pixels', 'error');

        let html = '';
        data.pixels.forEach(p => {
            html += `<tr>
                <td style="font-family:var(--font-mono);font-size:0.82rem;">${p.x}</td>
                <td style="font-family:var(--font-mono);font-size:0.82rem;">${p.y}</td>
                <td><div class="user-cell"><span class="color-swatch" style="background:${esc(p.color)}"></span><span style="font-family:var(--font-mono);font-size:0.78rem;color:#94a3b8;">${esc(p.color)}</span></div></td>
                <td><span class="color-swatch color-swatch-lg" style="background:${esc(p.color)}"></span></td>
                <td>${esc(p.username || '?')}</td>
                <td style="color:#475569;font-size:0.82rem;">${timeAgo(p.placed_at)}</td>
            </tr>`;
        });
        document.getElementById('pixels-list').innerHTML = html || emptyRow(6, 'No pixels yet');
        renderPagination('pixels', data.page, data.pages);
    }

    /* ── Sessions ── */
    async function loadSessions(page) {
        page = page || state.pages.sessions;
        state.pages.sessions = page;
        const data = await api('sessions', { params: { page } });
        if (!data.success) return toast('Failed to load sessions', 'error');

        let html = '';
        data.sessions.forEach(s => {
            html += `<tr>
                <td style="font-family:var(--font-mono);font-size:0.82rem;color:#475569;">#${s.id}</td>
                <td>${esc(s.username || '?')}</td>
                <td class="score-value">${(s.score || 0).toLocaleString()}</td>
                <td>${(s.combo_max || 0)}x</td>
                <td>${s.moves_left}</td>
                <td><span class="badge badge-${s.status}">${s.status}</span></td>
                <td style="color:#475569;font-size:0.82rem;">${timeAgo(s.started_at)}</td>
                <td style="color:#475569;font-size:0.82rem;">${s.completed_at ? timeAgo(s.completed_at) : '-'}</td>
            </tr>`;
        });
        document.getElementById('sessions-list').innerHTML = html || emptyRow(8, 'No sessions yet');
        renderPagination('sessions', data.page, data.pages);
    }

    /* ── Transactions ── */
    async function loadTransactions(page) {
        page = page || state.pages.transactions;
        state.pages.transactions = page;
        const data = await api('transactions', { params: { page } });
        if (!data.success) return toast('Failed to load transactions', 'error');

        let html = '';
        data.transactions.forEach(t => {
            const isEarn = t.type === 'earn';
            html += `<tr>
                <td style="font-family:var(--font-mono);font-size:0.82rem;color:#475569;">#${t.id}</td>
                <td>${esc(t.username || '?')}</td>
                <td><span class="gem ${isEarn ? 'gem-positive' : 'gem-negative'}">${isEarn ? '+' : '-'}${t.amount} &#x1F48E;</span></td>
                <td><span class="badge badge-${t.type}">${t.type}</span></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(t.description || '')}</td>
                <td style="color:#475569;font-size:0.82rem;">${timeAgo(t.created_at)}</td>
            </tr>`;
        });
        document.getElementById('transactions-list').innerHTML = html || emptyRow(6, 'No transactions yet');
        renderPagination('transactions', data.page, data.pages);
    }

    /* ── Achievements ── */
    async function loadAchievements() {
        const data = await api('achievements');
        if (!data.success) return toast('Failed to load achievements', 'error');

        let html = '';
        data.achievements.forEach(a => {
            html += `<div class="achievement-card">
                <div class="achievement-icon">${esc(a.icon || '?')}</div>
                <div class="achievement-info">
                    <div class="achievement-name">${esc(a.name)}</div>
                    <div class="achievement-desc">${esc(a.description)}</div>
                    <div class="achievement-meta">
                        <span class="reward">+${a.reward} &#x1F48E;</span>
                        <span class="earned">${a.earned_count} player(s)</span>
                    </div>
                </div>
            </div>`;
        });
        document.getElementById('achievements-list').innerHTML = html || '<div style="text-align:center;padding:48px;color:#475569;">No achievements</div>';
    }

    /* ── Pagination ── */
    function renderPagination(section, page, pages) {
        const el = document.getElementById(section + '-pagination');
        if (!el || pages <= 1) { if (el) el.innerHTML = ''; return; }

        let html = '';
        html += `<button data-page-section="${section}" data-page-num="${page - 1}" ${page <= 1 ? 'disabled' : ''}>&laquo;</button>`;

        const range = getPageRange(page, pages);
        range.forEach(p => {
            if (p === '...') {
                html += `<span class="page-info">...</span>`;
            } else {
                html += `<button class="${p === page ? 'active-page' : ''}" data-page-section="${section}" data-page-num="${p}">${p}</button>`;
            }
        });

        html += `<button data-page-section="${section}" data-page-num="${page + 1}" ${page >= pages ? 'disabled' : ''}>&raquo;</button>`;
        html += `<span class="page-info">${page} / ${pages}</span>`;
        el.innerHTML = html;
    }

    function getPageRange(current, total) {
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const range = [];
        range.push(1);
        if (current > 3) range.push('...');
        for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
            range.push(i);
        }
        if (current < total - 2) range.push('...');
        range.push(total);
        return range;
    }

    function loadPage(section, page) {
        const loaders = { users: loadUsers, pixels: loadPixels, sessions: loadSessions, transactions: loadTransactions };
        if (loaders[section]) loaders[section](page);
    }

    /* ── Event Delegation ── */
    function initEvents() {
        document.addEventListener('click', e => {
            // Edit user
            const editBtn = e.target.closest('[data-edit]');
            if (editBtn) {
                openEditModal(
                    parseInt(editBtn.dataset.edit),
                    editBtn.dataset.role || 'user',
                    parseInt(editBtn.dataset.balance) || 0,
                    parseInt(editBtn.dataset.level) || 1
                );
                return;
            }

            // Delete user
            const deleteBtn = e.target.closest('[data-delete]');
            if (deleteBtn) {
                handleDeleteUser(parseInt(deleteBtn.dataset.delete));
                return;
            }

            // Modal close
            if (e.target.closest('#modal-close-btn') || e.target.closest('.modal-close-btn') || e.target.id === 'edit-modal') {
                if (e.target.id === 'edit-modal' && e.target !== e.currentTarget) return;
                closeEditModal();
                return;
            }

            // Modal save
            if (e.target.closest('#modal-save-btn')) {
                handleSaveUser();
                return;
            }

            // Pagination
            const pageBtn = e.target.closest('[data-page-section]');
            if (pageBtn) {
                loadPage(pageBtn.dataset.pageSection, parseInt(pageBtn.dataset.pageNum));
                return;
            }
        });

        // Search
        const searchInput = document.getElementById('user-search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(state.searchTimeout);
                state.searchTimeout = setTimeout(() => loadUsers(1), 300);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                closeEditModal();
                const confirmOverlay = document.getElementById('confirm-overlay');
                if (confirmOverlay.classList.contains('open')) {
                    confirmOverlay.classList.remove('open');
                }
            }
        });
    }

    /* ── Edit Modal ── */
    function openEditModal(id, role, balance, level) {
        document.getElementById('edit-user-id').value = id;
        document.getElementById('edit-role').value = role;
        document.getElementById('edit-balance').value = balance;
        document.getElementById('edit-level').value = level;
        document.getElementById('edit-modal').classList.add('open');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.remove('open');
    }

    async function handleSaveUser() {
        const id = parseInt(document.getElementById('edit-user-id').value);
        const data = await api('user_update', {
            method: 'POST',
            body: {
                id,
                role: document.getElementById('edit-role').value,
                balance: parseInt(document.getElementById('edit-balance').value),
                level: parseInt(document.getElementById('edit-level').value)
            }
        });
        if (data.success) {
            closeEditModal();
            toast('User updated successfully', 'success');
            loadUsers();
        } else {
            toast(data.message || 'Update failed', 'error');
        }
    }

    async function handleDeleteUser(id) {
        const yes = await confirm('Delete User', 'This will permanently delete this user and all their data. This cannot be undone.');
        if (!yes) return;

        const data = await api('user_delete', { method: 'POST', body: { id } });
        if (data.success) {
            toast('User deleted', 'success');
            loadUsers();
        } else {
            toast(data.message || 'Delete failed', 'error');
        }
    }

    /* ── Helpers ── */
    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function timeAgo(dateStr) {
        if (!dateStr) return '';
        const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
        if (diff < 0) return 'just now';
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return new Date(dateStr).toLocaleDateString();
    }

    function emptyRow(colspan, msg) {
        return `<tr><td colspan="${colspan}" class="table-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>${msg}</td></tr>`;
    }

    /* ── Init ── */
    function init() {
        initNav();
        initMobile();
        initEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
