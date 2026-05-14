import { api } from './api.js';
import { showError, showSuccess } from './ui.js';

async function initPage() {
    await loadUserData();
    await loadProfile();
    setupEventListeners();
}

async function loadUserData() {
    const user = await api.get('/api/auth/me.php');
    if (user) {
        document.getElementById('username-display').textContent = user.username;
        document.getElementById('pxl-display').textContent = user.pxl_balance;
    }
}

async function loadProfile() {
    // This would load the current user's full profile with all stats
    const user = await api.get('/api/auth/me.php');
    if (!user) return;

    document.getElementById('profile-username').textContent = user.username;
    document.getElementById('profile-joined').textContent = `Joined ${new Date(user.created_at).toLocaleDateString()}`;

    if (user.login_streak > 0) {
        document.getElementById('streak-badge').style.display = 'block';
        document.getElementById('streak-days').textContent = user.login_streak;
    }

    // Load achievements
    const achievements = await api.get('/api/user/achievements.php');
    if (achievements) {
        const grid = document.getElementById('achievements-grid');
        grid.innerHTML = '';
        achievements.forEach(ach => {
            const div = document.createElement('div');
            div.className = `achievement-item ${ach.is_claimed ? 'earned' : 'unearned'}`;
            div.innerHTML = `
                <div class="achievement-icon">🏆</div>
                <div class="achievement-name">${ach.title}</div>
            `;
            div.title = ach.description;
            div.onclick = () => ach.is_claimed === 0 && claimAchievement(ach.achievement_key);
            grid.appendChild(div);
        });
    }
}

async function claimAchievement(key) {
    const result = await api.post('/api/user/claim-achievement.php', { achievement_key: key });
    if (result) {
        showSuccess('Achievement claimed!');
        await loadProfile();
    }
}

function setupEventListeners() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const tab = e.target.dataset.tab;
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            document.getElementById(tab).classList.add('active');
        });
    });

    document.getElementById('logout-btn')?.addEventListener('click', handleLogout);
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
