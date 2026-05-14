import { api } from './api.js';
import { showError, showSuccess } from './ui.js';

// Initialize page
async function initPage() {
    await loadUserData();
    setupEventListeners();
    await loadLeaderboard('daily');
}

async function loadUserData() {
    const user = await api.get('/api/auth/me.php');
    if (user) {
        document.getElementById('username-display').textContent = user.username;
        document.getElementById('pxl-display').textContent = user.pxl_balance;
    }
}

function setupEventListeners() {
    document.querySelectorAll('.leaderboard-tabs .tab-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const type = e.target.dataset.tab;
            document.querySelectorAll('.leaderboard-tabs .tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.leaderboard-tab').forEach(t => t.style.display = 'none');
            e.target.classList.add('active');
            document.getElementById(type).style.display = 'block';
            await loadLeaderboard(type);
        });
    });

    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
}

async function loadLeaderboard(type, page = 1) {
    const result = await api.get(`/api/leaderboard.php?type=${type}&page=${page}`);
    if (!result || !result.scores) return;

    const tbody = document.getElementById(`${type}-scores`);
    tbody.innerHTML = '';

    result.scores.forEach((score, idx) => {
        const tr = document.createElement('tr');
        const rank = (page - 1) * result.limit + idx + 1;

        let rankBadge = `<span class="rank-badge">${rank}</span>`;
        if (rank === 1) rankBadge = `<span class="rank-badge gold">🥇</span>`;
        else if (rank === 2) rankBadge = `<span class="rank-badge silver">🥈</span>`;
        else if (rank === 3) rankBadge = `<span class="rank-badge bronze">🥉</span>`;

        tr.innerHTML = `
            <td>${rankBadge}</td>
            <td>${score.username}</td>
            <td>${score.score}</td>
            <td>${score.pxl_earned}</td>
            <td>${formatTime(score.duration_seconds)}</td>
        `;
        tbody.appendChild(tr);
    });

    updatePagination(type, page, result.total, result.limit);
}

function updatePagination(type, page, total, limit) {
    const totalPages = Math.ceil(total / limit);
    document.getElementById('page-info').textContent = `Page ${page} of ${totalPages}`;

    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    prevBtn.disabled = page === 1;
    nextBtn.disabled = page === totalPages;

    prevBtn.onclick = () => page > 1 && loadLeaderboard(type, page - 1);
    nextBtn.onclick = () => page < totalPages && loadLeaderboard(type, page + 1);
}

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}m ${secs}s`;
}

async function handleLogout() {
    await api.post('/api/auth/logout.php', {});
    showSuccess('Logged out');
    setTimeout(() => window.location.href = '/index.php', 500);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPage);
} else {
    initPage();
}
