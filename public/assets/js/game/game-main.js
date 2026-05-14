import { GameEngine } from './engine.js';

let engine = null;
let gameState = 'lobby';

document.getElementById('playBtn').addEventListener('click', startGame);

document.addEventListener('keydown', (e) => {
    if (gameState !== 'playing') return;

    if (e.code === 'Space' || e.code === 'ArrowUp' || e.code === 'KeyW') {
        e.preventDefault();
        engine?.jump();
    } else if (e.code === 'ArrowDown' || e.code === 'KeyS') {
        e.preventDefault();
        engine?.slide();
    } else if (e.code === 'Escape' || e.code === 'KeyP') {
        e.preventDefault();
        togglePause();
    }
});

document.addEventListener('keyup', (e) => {
    if (e.code === 'ArrowDown' || e.code === 'KeyS') {
        engine?.stopSlide();
    }
});

document.getElementById('pauseBtn').addEventListener('click', togglePause);
document.getElementById('resumeBtn').addEventListener('click', togglePause);
document.getElementById('quitBtn').addEventListener('click', quitToLobby);
document.getElementById('playAgainBtn').addEventListener('click', () => { quitToLobby(); setTimeout(startGame, 100); });
document.getElementById('goToForgeBtn').addEventListener('click', () => window.location.href = '/canvas.php');
document.getElementById('muteBtn').addEventListener('click', () => {
    const btn = document.getElementById('muteBtn');
    const enabled = engine?.audio.toggle() ?? true;
    btn.textContent = enabled ? '🔊' : '🔇';
});

// Tutorial Modal
document.getElementById('tutorialBtn')?.addEventListener('click', () => {
    document.getElementById('tutorialModal').classList.add('active');
});

document.getElementById('tutorialClose')?.addEventListener('click', () => {
    document.getElementById('tutorialModal').classList.remove('active');
});

document.querySelector('.tutorial-overlay')?.addEventListener('click', () => {
    document.getElementById('tutorialModal').classList.remove('active');
});

function updateHUD() {
    if (!engine) return;

    const livesContainer = document.getElementById('hudLives');
    livesContainer.innerHTML = Array(3).fill(0).map((_, i) =>
        `<div class="life-icon ${i >= engine.state.lives ? 'empty' : ''}"></div>`
    ).join('');

    document.getElementById('hudScore').textContent = engine.state.score.toLocaleString();

    const comboEl = document.getElementById('hudCombo');
    const mult = engine.state.comboMultiplier;
    comboEl.textContent = `x${mult}`;
    comboEl.className = `hud-combo x${mult.toString().replace('.', '')}`;

    const powerupBar = document.getElementById('powerupBar');
    const powerupFill = document.getElementById('powerupBarFill');
    if (engine.state.powerup && engine.state.powerupExpiresAt) {
        powerupBar.style.display = 'block';
        const remaining = Math.max(0, engine.state.powerupExpiresAt - Date.now());
        const total = engine.state.powerup === 'shield' ? 8000 :
                     engine.state.powerup === 'magnet' ? 12000 :
                     engine.state.powerup === 'timewarp' ? 6000 : 15000;
        powerupFill.style.width = (remaining / total * 100) + '%';
    } else {
        powerupBar.style.display = 'none';
    }
}

function showPauseOverlay() {
    document.getElementById('pauseOverlay').classList.remove('hidden');
}

function hidePauseOverlay() {
    document.getElementById('pauseOverlay').classList.add('hidden');
}

function togglePause() {
    if (gameState !== 'playing' && gameState !== 'paused') return;

    if (gameState === 'playing') {
        engine.pause();
        gameState = 'paused';
        showPauseOverlay();
    } else if (gameState === 'paused') {
        engine.resume();
        gameState = 'playing';
        hidePauseOverlay();
    }
}

function showGameOver(result) {
    gameState = 'gameover';

    document.getElementById('finalScore').textContent = engine.state.score.toLocaleString();
    document.getElementById('pxlEarned').textContent = `+${result?.pxl_earned ?? 0} PXL`;

    const stats = document.getElementById('gameOverStats');
    stats.innerHTML = `
        <span>Max Combo: ${engine.state.maxCombo}x</span>
        <span>Speed Tier: ${engine.state.speedTier}</span>
        <span>Rank: #${result?.daily_rank ?? '-'}</span>
    `;

    if (result?.personal_best) {
        document.getElementById('pxlEarned').classList.add('personal-best');
        document.getElementById('pxlEarned').textContent += ' (NEW BEST!)';
    }

    document.getElementById('gameOverOverlay').classList.remove('hidden');

    if (result?.achievements_unlocked?.length > 0) {
        result.achievements_unlocked.forEach((ach, i) => {
            setTimeout(() => showAchievement(ach), i * 500);
        });
    }
}

function showAchievement(ach) {
    const popup = document.createElement('div');
    popup.className = 'achievement-popup';
    popup.innerHTML = `
        <div class="achievement-title">ACHIEVEMENT UNLOCKED</div>
        <div class="achievement-name">${ach.title}</div>
        <div class="achievement-reward">+${ach.pxl} PXL</div>
    `;
    document.body.appendChild(popup);
    setTimeout(() => popup.remove(), 4000);
}

async function quitToLobby() {
    gameState = 'lobby';
    if (engine && engine.state.score > 0) {
        await engine.submitScore();
    }
    engine?.quit();
    engine = null;

    document.getElementById('lobby').style.display = 'block';
    document.getElementById('gameArea').style.display = 'none';
    document.getElementById('gameOverOverlay').classList.add('hidden');
    hidePauseOverlay();
}

async function startGame() {
    document.getElementById('lobby').style.display = 'none';
    document.getElementById('gameArea').style.display = 'block';
    document.getElementById('gameOverOverlay').classList.add('hidden');

    try {
        const response = await fetch('/api/game/start.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({})
        });

        const result = await response.json();

        if (!result.ok) {
            alert(result.message || 'Failed to start game');
            quitToLobby();
            return;
        }

        const canvas = document.getElementById('gameCanvas');
        engine = new GameEngine(canvas, result.data.seed, result.data.session_id, result.data.client_key);

        engine.start();
        gameState = 'playing';

        engine.render = (function(orig) {
            return function() {
                orig.call(this);
                updateHUD();
            };
        })(engine.render);

        engine.onGameOver = (function(orig) {
            return function() {
                orig.call(this);
                setTimeout(() => {
                    showGameOver(window.gameResult);
                }, 500);
            };
        })(engine.onGameOver);

    } catch (e) {
        console.error('Failed to start game:', e);
        alert('Failed to start game');
        quitToLobby();
    }
}