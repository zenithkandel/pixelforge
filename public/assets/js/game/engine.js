import { SeededPRNG } from './prng.js';
import { ObstacleManager } from './obstacles.js';
import { CollectibleManager } from './collectibles.js';
import { AudioManager } from './audio.js';

const GRAVITY = 2800;
const JUMP_VELOCITY = -900;
const DOUBLE_JUMP_VELOCITY = -750;
const GROUND_Y_OFFSET = 60;
const CANVAS_WIDTH = 800;
const CANVAS_HEIGHT = 300;

const SPEED_TIERS = [
    { bps: 5, threshold: 0 },
    { bps: 6.5, threshold: 300 },
    { bps: 8, threshold: 800 },
    { bps: 10, threshold: 1800 },
    { bps: 12, threshold: 3500 },
    { bps: 14, threshold: 6000 },
    { bps: 15.5, threshold: 10000 },
    { bps: 15.5, threshold: Infinity }
];

export class GameEngine {
    constructor(canvas, seed, sessionId, clientKey) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.canvas.width = CANVAS_WIDTH;
        this.canvas.height = CANVAS_HEIGHT;

        this.prng = new SeededPRNG(seed);
        this.sessionId = sessionId;
        this.clientKey = clientKey;

        this.audio = new AudioManager();
        this.obstacles = new ObstacleManager(this.prng);
        this.collectibles = new CollectibleManager(this.prng);

        this.state = {
            running: false,
            paused: false,
            gameOver: false,
            score: 0,
            lives: 3,
            combo: 0,
            maxCombo: 0,
            comboMultiplier: 1,
            speedBPS: 5.0,
            speedTier: 1,
            elapsedMs: 0,
            distance: 0,
            lastCheckpointMs: 0,
            prismsCollected: 0,
            bombUsed: false,
            invincibleUntil: 0,
            powerup: null,
            powerupExpiresAt: null
        };

        this.pxlr = {
            x: 100,
            y: CANVAS_HEIGHT - GROUND_Y_OFFSET - 16,
            vy: 0,
            width: 16,
            height: 16,
            onGround: true,
            canDoubleJump: true,
            isSliding: false,
            animFrame: 0,
            animTimer: 0
        };

        this.lastTimestamp = 0;
        this.animationId = null;
        this.backgroundOffset = 0;
    }

    start() {
        this.state.running = true;
        this.state.paused = false;
        this.state.gameOver = false;
        this.lastTimestamp = performance.now();
        this.gameLoop(this.lastTimestamp);
    }

    pause() {
        this.state.paused = true;
    }

    resume() {
        this.state.paused = false;
        this.lastTimestamp = performance.now();
        this.gameLoop(this.lastTimestamp);
    }

    quit() {
        this.state.running = false;
        if (this.animationId) {
            cancelAnimationFrame(this.animationId);
        }
    }

    gameLoop(timestamp) {
        if (!this.state.running || this.state.paused) return;

        const dt = Math.min((timestamp - this.lastTimestamp) / 1000, 0.05);
        this.lastTimestamp = timestamp;

        this.update(dt);
        this.render();

        if (this.state.gameOver) {
            this.onGameOver();
            return;
        }

        this.maybeSendCheckpoint();

        this.animationId = requestAnimationFrame(this.gameLoop.bind(this));
    }

    update(dt) {
        this.state.elapsedMs += dt * 1000;
        this.state.distance += this.state.speedBPS * dt * 60;

        this.updateSpeed();
        this.updatePlayer(dt);
        this.updateObstacles(dt);
        this.updateCollectibles(dt);
        this.checkCollisions();
        this.updatePowerups(dt);
        this.updateBackground(dt);
    }

    updateSpeed() {
        const currentTier = SPEED_TIERS.find(t => this.state.score >= t.threshold) || SPEED_TIERS[SPEED_TIERS.length - 1];
        const prevTier = SPEED_TIERS[this.state.speedTier - 1] || SPEED_TIERS[0];

        const tierIndex = SPEED_TIERS.indexOf(currentTier);
        if (tierIndex + 1 !== this.state.speedTier && this.state.score >= currentTier.threshold) {
            this.state.speedTier = tierIndex + 1;
            this.state.speedBPS = currentTier.bps;
            this.audio.playLevelUp();
        } else if (this.state.speedTier === 1 && this.state.score > 0) {
            const progress = (this.state.score - prevTier.threshold) / (currentTier.threshold - prevTier.threshold);
            this.state.speedBPS = prevTier.bps + (currentTier.bps - prevTier.bps) * Math.min(progress, 1);
        }
    }

    updatePlayer(dt) {
        if (Date.now() < this.state.invincibleUntil) {
            this.pxlr.isInvincible = true;
        } else {
            this.pxlr.isInvincible = false;
        }

        this.pxlr.vy += GRAVITY * dt;
        this.pxlr.y += this.pxlr.vy * dt;

        const groundY = CANVAS_HEIGHT - GROUND_Y_OFFSET - (this.pxlr.isSliding ? 8 : 16);
        if (this.pxlr.y >= groundY) {
            this.pxlr.y = groundY;
            this.pxlr.vy = 0;
            this.pxlr.onGround = true;
            this.pxlr.canDoubleJump = true;
        } else {
            this.pxlr.onGround = false;
        }

        if (this.pxlr.isSliding && this.pxlr.onGround) {
            this.pxlr.height = 8;
        } else {
            this.pxlr.height = 16;
        }

        this.pxlr.animTimer += dt;
        if (this.pxlr.animTimer > 0.1) {
            this.pxlr.animTimer = 0;
            this.pxlr.animFrame = (this.pxlr.animFrame + 1) % 4;
        }
    }

    jump() {
        if (this.pxlr.onGround) {
            this.pxlr.vy = JUMP_VELOCITY;
            this.pxlr.onGround = false;
            this.audio.playJump();
        } else if (this.pxlr.canDoubleJump) {
            this.pxlr.vy = DOUBLE_JUMP_VELOCITY;
            this.pxlr.canDoubleJump = false;
            this.audio.playJump();
        }
    }

    slide() {
        if (this.pxlr.onGround) {
            this.pxlr.isSliding = true;
        }
    }

    stopSlide() {
        this.pxlr.isSliding = false;
    }

    updateObstacles(dt) {
        this.obstacles.update(this.state.speedBPS, dt, this.state.speedTier);

        this.obstacles.obstacles = this.obstacles.obstacles.filter(o => {
            o.x -= this.state.speedBPS * dt * 60;
            return o.x > -50;
        });

        if (this.obstacles.shouldSpawn(this.state.elapsedMs, this.state.speedTier)) {
            const newObstacle = this.obstacles.spawn(this.state.elapsedMs, this.state.speedTier);
            if (newObstacle) {
                this.obstacles.obstacles.push(newObstacle);
            }
        }
    }

    updateCollectibles(dt) {
        this.collectibles.update(this.state.speedBPS, dt);

        this.collectibles.collectibles = this.collectibles.collectibles.filter(c => {
            c.x -= this.state.speedBPS * dt * 60;
            return c.x > -20;
        });

        if (this.collectibles.shouldSpawn(this.state.elapsedMs)) {
            const newCollectible = this.collectibles.spawn(this.state.elapsedMs, this.state.speedTier, this.obstacles.obstacles);
            if (newCollectible) {
                this.collectibles.collectibles.push(newCollectible);
            }
        }
    }

    checkCollisions() {
        const p = this.pxlr;

        for (const obstacle of this.obstacles.obstacles) {
            if (this.checkAABBCollision(p, obstacle)) {
                if (!this.state.invincibleUntil) {
                    this.onObstacleHit();
                }
                break;
            }
        }

        for (const collectible of this.collectibles.collectibles) {
            if (this.checkCircleCollision(p, collectible)) {
                this.onCollectiblePickup(collectible);
            }
        }

        if (this.state.powerup === 'magnet') {
            const magnetRange = 120;
            for (const collectible of this.collectibles.collectibles) {
                const dx = collectible.x - (p.x + p.width / 2);
                const dy = collectible.y - (p.y + p.height / 2);
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < magnetRange) {
                    collectible.x -= 8;
                    collectible.y += (p.y + p.height / 2 - collectible.y) * 0.1;
                }
            }
        }
    }

    checkAABBCollision(a, b) {
        return a.x < b.x + b.width &&
               a.x + a.width > b.x &&
               a.y < b.y + b.height &&
               a.y + a.height > b.y;
    }

    checkCircleCollision(a, b) {
        const ax = a.x + a.width / 2;
        const ay = a.y + a.height / 2;
        const dx = b.x - ax;
        const dy = b.y - ay;
        const dist = Math.sqrt(dx * dx + dy * dy);
        return dist < (a.width / 2 + b.radius);
    }

    onObstacleHit() {
        if (this.state.powerup === 'shield') {
            this.state.powerup = null;
            this.state.powerupExpiresAt = null;
            this.audio.playPowerup();
            return;
        }

        this.state.lives--;
        this.audio.playHit();

        if (this.state.lives <= 0) {
            this.state.gameOver = true;
            this.audio.playDeath();
        } else {
            this.state.invincibleUntil = Date.now() + 2500;
            this.combo = 0;
            this.updateComboMultiplier();
        }
    }

    onCollectiblePickup(collectible) {
        const baseScore = collectible.value;
        const finalScore = Math.floor(baseScore * this.state.comboMultiplier);

        this.state.score += finalScore;

        if (collectible.type === 'prism') {
            this.state.prismsCollected++;
        }

        if (collectible.type === 'power_cell') {
            this.activatePowerup(collectible.powerup);
        }

        this.combo++;
        if (this.combo > this.state.maxCombo) {
            this.state.maxCombo = this.combo;
        }

        this.updateComboMultiplier();
        this.audio.playCollect();

        this.collectibles.collectibles = this.collectibles.collectibles.filter(c => c !== collectible);
    }

    updateComboMultiplier() {
        if (this.combo >= 35) {
            this.state.comboMultiplier = 4;
        } else if (this.combo >= 20) {
            this.state.comboMultiplier = 3;
        } else if (this.combo >= 10) {
            this.state.comboMultiplier = 2;
        } else if (this.combo >= 5) {
            this.state.comboMultiplier = 1.5;
        } else {
            this.state.comboMultiplier = 1;
        }
    }

    activatePowerup(powerup) {
        const durations = {
            shield: 8000,
            magnet: 12000,
            timewarp: 6000,
            score_surge: 15000
        };

        const instant = ['extra_life', 'pixel_bomb'];

        if (instant.includes(powerup)) {
            if (powerup === 'extra_life' && this.state.lives < 3) {
                this.state.lives++;
                this.audio.playPowerup();
            } else if (powerup === 'pixel_bomb') {
                this.state.bombUsed = true;
                for (const obstacle of this.obstacles.obstacles) {
                    this.collectibles.collectibles.push({
                        type: 'shard',
                        subtype: 'gray',
                        x: obstacle.x,
                        y: CANVAS_HEIGHT - GROUND_Y_OFFSET - 10,
                        value: 1,
                        radius: 4
                    });
                }
                this.obstacles.obstacles = [];
                this.audio.playPowerup();
            }
        } else {
            this.state.powerup = powerup;
            this.state.powerupExpiresAt = Date.now() + (durations[powerup] || 10000);

            if (powerup === 'timewarp') {
                this.state.speedBPS *= 0.6;
            }
            if (powerup === 'score_surge') {
                this.state.comboMultiplier *= 3;
            }

            this.audio.playPowerup();
        }
    }

    updatePowerups(dt) {
        if (this.state.powerupExpiresAt && Date.now() >= this.state.powerupExpiresAt) {
            if (this.state.powerup === 'timewarp') {
                this.state.speedBPS = SPEED_TIERS[this.state.speedTier - 1].bps;
            }
            if (this.state.powerup === 'score_surge') {
                this.updateComboMultiplier();
            }
            this.state.powerup = null;
            this.state.powerupExpiresAt = null;
        }
    }

    updateBackground(dt) {
        this.backgroundOffset += this.state.speedBPS * dt * 60 * 0.2;
    }

    maybeSendCheckpoint() {
        if (this.state.elapsedMs - this.state.lastCheckpointMs >= 30000) {
            this.state.lastCheckpointMs = this.state.elapsedMs;
            this.sendCheckpoint();
        }
    }

    async sendCheckpoint() {
        const data = {
            session_id: this.sessionId,
            score: this.state.score,
            lives: this.state.lives,
            speed_tier: this.state.speedTier,
            elapsed_ms: Math.floor(this.state.elapsedMs),
            hmac: await this.computeHMAC(this.state.score, Math.floor(this.state.elapsedMs))
        };

        try {
            await fetch('/api/game/checkpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify(data)
            });
        } catch (e) {
            console.error('Checkpoint failed:', e);
        }
    }

    async computeHMAC(score, elapsedMs) {
        const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(this.clientKey), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
        const msg = `${this.sessionId}:${score}:${elapsedMs}`;
        const sig = await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(msg));
        return Array.from(new Uint8Array(sig)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    onGameOver() {
        this.submitScore();
    }

    async submitScore() {
        const hmac = await this.computeHMAC(this.state.score, Math.floor(this.state.elapsedMs));

        const data = {
            session_id: this.sessionId,
            final_score: this.state.score,
            duration_ms: Math.floor(this.state.elapsedMs),
            lives_remaining: this.state.lives,
            max_speed_tier: this.state.speedTier,
            max_combo: this.state.maxCombo,
            prisms_collected: this.state.prismsCollected,
            bomb_used: this.state.bombUsed,
            hmac: hmac
        };

        try {
            const response = await fetch('/api/game/submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.ok) {
                window.gameResult = result.data;
            }
        } catch (e) {
            console.error('Score submission failed:', e);
        }
    }

    render() {
        this.ctx.fillStyle = '#0A0A1A';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        this.renderBackground();
        this.renderGround();
        this.renderCollectibles();
        this.renderObstacles();
        this.renderPlayer();
    }

    renderBackground() {
        this.ctx.fillStyle = 'rgba(0, 245, 255, 0.03)';
        for (let i = 0; i < 20; i++) {
            const x = ((i * 100 - this.backgroundOffset) % (this.canvas.width + 100)) - 50;
            this.ctx.fillRect(x, 0, 1, this.canvas.height);
        }
    }

    renderGround() {
        this.ctx.fillStyle = '#111122';
        this.ctx.fillRect(0, CANVAS_HEIGHT - GROUND_Y_OFFSET, this.canvas.width, GROUND_Y_OFFSET);

        this.ctx.fillStyle = '#00F5FF';
        this.ctx.fillRect(0, CANVAS_HEIGHT - GROUND_Y_OFFSET, this.canvas.width, 2);
    }

    renderPlayer() {
        const p = this.pxlr;

        if (p.isInvincible && Math.floor(Date.now() / 100) % 2 === 0) {
            return;
        }

        let color = '#00F5FF';
        if (this.state.powerup === 'shield') color = '#0066FF';
        if (this.state.powerup === 'magnet') color = '#FFCC00';
        if (this.state.powerup === 'timewarp') color = '#6600CC';
        if (this.state.powerup === 'score_surge') color = '#FF6600';

        this.ctx.fillStyle = color;
        this.ctx.fillRect(p.x, p.y, p.width, p.height);

        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(p.x + 4, p.y + 3, 3, 3);
    }

    renderObstacles() {
        for (const o of this.obstacles.obstacles) {
            if (o.type === 'glitch_block') {
                this.ctx.fillStyle = '#FF00FF';
                this.ctx.fillRect(o.x, o.y, o.width, o.height);
            } else if (o.type === 'spike') {
                this.ctx.fillStyle = '#FF3366';
                this.ctx.beginPath();
                this.ctx.moveTo(o.x, o.y + o.height);
                this.ctx.lineTo(o.x + o.width / 2, o.y);
                this.ctx.lineTo(o.x + o.width, o.y + o.height);
                this.ctx.fill();
            } else if (o.type === 'beam') {
                this.ctx.fillStyle = '#FF6600';
                this.ctx.fillRect(o.x, o.y, o.width, 4);
            } else {
                this.ctx.fillStyle = '#FF3366';
                this.ctx.fillRect(o.x, o.y, o.width, o.height);
            }
        }
    }

    renderCollectibles() {
        for (const c of this.collectibles.collectibles) {
            if (c.type === 'shard') {
                const colors = { gray: '#888888', red: '#FF3366', blue: '#3366FF', green: '#33FF66', rainbow: '#FF00FF' };
                this.ctx.fillStyle = colors[c.subtype] || '#888888';
            } else if (c.type === 'power_cell') {
                this.ctx.fillStyle = '#FFFFFF';
            }

            this.ctx.beginPath();
            this.ctx.arc(c.x, c.y, c.radius, 0, Math.PI * 2);
            this.ctx.fill();
        }
    }
}

export { CANVAS_WIDTH, CANVAS_HEIGHT, GROUND_Y_OFFSET };