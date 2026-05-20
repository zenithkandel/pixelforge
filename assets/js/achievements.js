window.achievementQueue = [];
window.achievementTimeout = null;

window.showAchievements = function(achievements) {
    if (!achievements || achievements.length === 0) return;

    achievements.forEach((a, i) => {
        setTimeout(() => {
            showAchievementToast(a);
        }, i * 3000);
    });
};

function showAchievementToast(achievement) {
    const toast = document.createElement('div');
    toast.className = 'achievement-toast';
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #111;
        border: 1px solid #222;
        border-left: 4px solid #f59e0b;
        border-radius: 8px;
        padding: 1rem 1.5rem;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 1rem;
    `;

    toast.innerHTML = `
        <div style="font-size:2rem;">${achievement.icon || '🏆'}</div>
        <div>
            <div style="color:#7c3aed;font-weight:600;">Achievement Unlocked!</div>
            <div style="color:#f5f5f5;font-weight:bold;">${achievement.name}</div>
            <div style="color:#9ca3af;font-size:0.85rem;">${achievement.description}</div>
            <div style="color:#f59e0b;font-weight:bold;margin-top:0.25rem;">+${achievement.reward} currency</div>
        </div>
    `;

    document.body.appendChild(toast);

    setTimeout(() => toast.style.transform = 'translateX(0)', 10);

    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

window.showToast = function(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #111;
        border: 1px solid #222;
        border-left: 4px solid ${type === 'success' ? '#22c55e' : '#7c3aed'};
        border-radius: 8px;
        padding: 1rem 1.5rem;
        transform: translateX(400px);
        transition: transform 0.3s ease;
        z-index: 1000;
        color: #f5f5f5;
    `;

    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.style.transform = 'translateX(0)', 10);

    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
};