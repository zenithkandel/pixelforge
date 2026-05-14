import { api } from '../api.js';
import { showError, showSuccess } from '../ui.js';
import { GameEngine } from './engine.js';
import { GameRenderer } from './renderer.js';
import { ObstacleManager } from './obstacles.js';
import { CollectibleManager } from './collectibles.js';
import { AudioManager } from './audio.js';
import { HUDRenderer } from './hud.js';

// Main game controller
class GameMain {
    constructor() {
        this.canvas = document.getElementById('gameCanvas');
        this.engine = null;
        this.renderer = null;
        this.isInitialized = false;
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.getElementById('play-btn')?.addEventListener('click', () => this.startGame());
        document.getElementById('play-again-btn')?.addEventListener('click', () => this.startGame());
        document.getElementById('pause-btn')?.addEventListener('click', () => this.togglePause());
        document.getElementById('mute-btn')?.addEventListener('click', () => this.toggleMute());
        document.getElementById('forge-btn')?.addEventListener('click', () => window.location.href = '/canvas.php');
        document.getElementById('logout-btn')?.addEventListener('click', () => this.logout());
    }

    async startGame() {
        // Get game session from server
        const session = await api.post('/api/game/start.php', {});
        if (!session) return;

        // Initialize game
        this.engine = new GameEngine(session.seed);
        this.renderer = new GameRenderer(this.canvas);
        this.audioManager = new AudioManager();
        this.hudRenderer = new HUDRenderer(this.canvas);
        this.obstacleManager = new ObstacleManager(this.engine.prng);
        this.collectibleManager = new CollectibleManager(this.engine.prng);

        this.sessionToken = session.session_token;
        this.hmacKey = session.hmac_key;

        // Show game viewport
        document.getElementById('game-menu').style.display = 'none';
        document.getElementById('game-over').style.display = 'none';
        document.getElementById('game-viewport').style.display = 'block';

        this.engine.start();
        this.gameLoop();
    }

    gameLoop = () => {
        if (!this.engine.isRunning) {
            this.endGame();
            return;
        }

        this.renderer.clear();
        this.engine.update(16);
        this.obstacleManager.update(this.engine.gameTime);
        this.collectibleManager.update(this.engine.gameTime);

        // Render HUD
        this.hudRenderer.drawHUD(this.engine.score, this.engine.lives, this.engine.combo, 0, this.engine.speedTier);

        // Draw a placeholder obstacle and shard
        this.renderer.drawCharacter(100, this.canvas.height - 100, false);
        this.renderer.drawShard(200, this.canvas.height - 100, '#FF3366');

        requestAnimationFrame(this.gameLoop);
    };

    async endGame() {
        // Submit score to server
        const result = await api.post('/api/game/submit.php', {
            session_token: this.sessionToken,
            final_score: this.engine.score,
            duration: this.engine.gameTime / 1000,
            highest_combo: this.engine.combo,
            total_shards: 0,
            final_speed_tier: this.engine.speedTier,
            lives_at_end: this.engine.lives,
            hmac: this.hmacKey
        });

        if (result) {
            document.getElementById('final-score').textContent = this.engine.score;
            document.getElementById('final-pxl').textContent = result.pxl_earned;
            document.getElementById('final-rank').textContent = result.daily_rank;
        }

        // Show game over screen
        document.getElementById('game-viewport').style.display = 'none';
        document.getElementById('game-over').style.display = 'block';
    }

    togglePause() {
        if (this.engine.isPaused) {
            this.engine.resume();
        } else {
            this.engine.pause();
        }
    }

    toggleMute() {
        if (this.audioManager) {
            this.audioManager.toggle();
            const btn = document.getElementById('mute-btn');
            btn.textContent = this.audioManager.isMuted ? '🔇' : '🔊';
        }
    }

    async logout() {
        await api.post('/api/auth/logout.php', {});
        window.location.href = '/index.php';
    }
}

// Initialize game on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new GameMain());
} else {
    new GameMain();
}
