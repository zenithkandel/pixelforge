(function() {
    var queue = [];
    var active = false;
    var container = null;

    function ensureContainer() {
        if (container) return container;
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(container);
        return container;
    }

    function showToast(achievement) {
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML =
            '<div class="toast-icon">' + (achievement.icon || '🏆') + '</div>' +
            '<div class="toast-body">' +
                '<div class="toast-name">' + (achievement.name || 'Achievement') + '</div>' +
                '<div class="toast-desc">' + (achievement.description || '') + '</div>' +
            '</div>' +
            '<div class="toast-reward">+' + (achievement.reward || 0) + ' 💰</div>';

        var c = ensureContainer();
        c.appendChild(toast);

        setTimeout(function() {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(function() {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 4000);
    }

    function processQueue() {
        if (active || queue.length === 0) return;
        active = true;
        var achievement = queue.shift();
        showToast(achievement);
        setTimeout(function() {
            active = false;
            processQueue();
        }, 500);
    }

    window.queueAchievements = function(achievements) {
        if (!achievements || achievements.length === 0) return;
        queue = queue.concat(achievements);
        processQueue();
    };
})();
