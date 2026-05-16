(function() {
    'use strict';

    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');

    const GAME_WIDTH = 800;
    const GAME_HEIGHT = 400;
    const GROUND_Y = 350;

    const BASE_SPEED = 5;
    const MAX_SPEED = 10;
    const SPEED_INCREMENT = 0.001;

    const state = {
        running: false,
        gameOver: false,
        score: 0,
        coins: 0,
        highScore: parseInt(localStorage.getItem('retroRunnerHigh')) || 0,
        speed: BASE_SPEED,
        distance: 0,
        frameCount: 0,
        lastObstacle: 0,
        lastCoin: 0,
        lastPowerup: 0,
        powerup: null,
        powerupTimer: 0,
        combo: 0,
        comboTimer: 0,
        level: 1
    };

    const player = {
        x: 100,
        y: GROUND_Y - 40,
        width: 40,
        height: 40,
        velocityY: 0,
        jumping: false,
        secondJump: false,
        sliding: false,
        slideTimer: 0,
        invincible: false,
        invincibilityTimer: 0,
        trail: []
    };

    const obstacles = [];
    const coins = [];
    const powerups = [];
    const particles = [];
    const backgroundStars = [];
    const groundDetails = [];

    const keys = { space: false, down: false, spacePressed: false };

    for (let i = 0; i < 80; i++) {
        backgroundStars.push({
            x: Math.random() * GAME_WIDTH,
            y: Math.random() * (GROUND_Y - 80),
            size: Math.random() * 2 + 0.5,
            speed: Math.random() * 0.3 + 0.1,
            brightness: Math.random()
        });
    }

    for (let i = 0; i < 20; i++) {
        groundDetails.push({
            x: Math.random() * GAME_WIDTH,
            y: GROUND_Y + 10 + Math.random() * 30,
            width: 3 + Math.random() * 8,
            height: 2 + Math.random() * 4
        });
    }

    document.addEventListener('keydown', e => {
        if (e.code === 'Space') {
            e.preventDefault();
            if (!keys.spacePressed) {
                keys.space = true;
                keys.spacePressed = true;
                if (!state.running && !state.gameOver) startGame();
                else if (state.gameOver) restartGame();
                else doJump();
            }
        }
        if (e.code === 'ArrowDown' || e.code === 'KeyS') {
            e.preventDefault();
            keys.down = true;
        }
    });

    document.addEventListener('keyup', e => {
        if (e.code === 'Space') {
            keys.space = false;
            keys.spacePressed = false;
        }
        if (e.code === 'ArrowDown' || e.code === 'KeyS') keys.down = false;
    });

    document.getElementById('restart-btn').addEventListener('click', restartGame);
    document.getElementById('submit-score').addEventListener('click', submitScore);
    document.getElementById('player-name').addEventListener('keydown', e => {
        if (e.code === 'Enter') submitScore();
    });

    document.getElementById('highscore').textContent = state.highScore;

    function startGame() {
        state.running = true;
        state.gameOver = false;
        state.score = 0;
        state.coins = 0;
        state.speed = BASE_SPEED;
        state.distance = 0;
        state.frameCount = 0;
        state.powerup = null;
        state.powerupTimer = 0;
        state.combo = 0;
        state.comboTimer = 0;
        state.level = 1;

        player.y = GROUND_Y - player.height;
        player.velocityY = 0;
        player.jumping = false;
        player.secondJump = false;
        player.sliding = false;
        player.slideTimer = 0;
        player.invincible = false;
        player.invincibilityTimer = 0;
        player.trail = [];

        obstacles.length = 0;
        coins.length = 0;
        powerups.length = 0;
        particles.length = 0;

        document.getElementById('startScreen').style.display = 'none';
        document.getElementById('gameOver').style.display = 'none';
        document.getElementById('powerup').classList.remove('active');

        gameLoop();
    }

    function restartGame() {
        startGame();
    }

    function submitScore() {
        const name = document.getElementById('player-name').value.trim() || 'Player';
        const finalScore = state.score + state.coins * 5 + state.combo * 10;
        fetch('/api/game/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ score: finalScore, player: name })
        }).then(r => r.json()).then(d => {
            if (d.ok) {
                alert(`Score submitted! Rank: #${d.rank}`);
            }
        });
        restartGame();
    }

    function doJump() {
        if (!player.sliding) {
            if (!player.jumping) {
                player.velocityY = -13;
                player.jumping = true;
                player.secondJump = true;
                spawnParticles(player.x + player.width / 2, player.y + player.height, 8, '#00fff5');
                addTrail();
            } else if (player.secondJump) {
                player.velocityY = -11;
                player.secondJump = false;
                spawnParticles(player.x + player.width / 2, player.y + player.height, 12, '#ffd700');
                addTrail();
                if (state.comboTimer <= 0) {
                    state.combo = 1;
                } else {
                    state.combo++;
                }
                state.comboTimer = 60;
            }
        }
    }

    function addTrail() {
        player.trail.push({ x: player.x, y: player.y, alpha: 1 });
        if (player.trail.length > 8) player.trail.shift();
    }

    function slide() {
        if (!player.sliding && !player.jumping) {
            player.sliding = true;
            player.slideTimer = 50;
            player.height = 20;
            player.y = GROUND_Y - 20;
            spawnParticles(player.x, player.y + 10, 5, '#e94560');
        }
    }

    function stopSlide() {
        player.sliding = false;
        player.height = 40;
        player.y = GROUND_Y - 40;
    }

    function spawnParticles(x, y, count, color) {
        for (let i = 0; i < count; i++) {
            particles.push({
                x, y,
                vx: (Math.random() - 0.5) * 8,
                vy: (Math.random() - 0.5) * 8 - 3,
                life: 25 + Math.random() * 15,
                color,
                size: Math.random() * 5 + 2
            });
        }
    }

    function update() {
        if (!state.running) return;

        state.frameCount++;
        state.distance += state.speed;
        
        if (state.speed < MAX_SPEED) {
            state.speed += SPEED_INCREMENT;
        }

        state.score = Math.floor(state.distance / 10);
        state.level = 1 + Math.floor(state.distance / 2000);

        if (state.comboTimer > 0) {
            state.comboTimer--;
            if (state.comboTimer <= 0) state.combo = 0;
        }

        if (state.powerup) {
            state.powerupTimer--;
            if (state.powerupTimer <= 0) {
                state.powerup = null;
                document.getElementById('powerup').classList.remove('active');
            }
        }

        if (player.invincible) {
            player.invincibilityTimer--;
            if (player.invincibilityTimer <= 0) {
                player.invincible = false;
            }
        }

        if (keys.down && !player.jumping && !player.sliding) slide();

        if (player.jumping) {
            player.velocityY += 0.6;
            player.y += player.velocityY;
            
            if (player.y >= GROUND_Y - 40) {
                player.y = GROUND_Y - 40;
                player.jumping = false;
                player.velocityY = 0;
                player.secondJump = false;
                spawnParticles(player.x + player.width / 2, player.y + 40, 5, '#e94560');
            }
        }

        if (player.sliding) {
            player.slideTimer--;
            if (player.slideTimer <= 0) stopSlide();
        }

        for (let i = player.trail.length - 1; i >= 0; i--) {
            player.trail[i].alpha -= 0.15;
            if (player.trail[i].alpha <= 0) player.trail.splice(i, 1);
        }
        if (state.running && !player.sliding) addTrail();

        const minGap = Math.max(50, 120 - state.level * 5);
        const randomGap = minGap + Math.random() * 60;
        
        if (state.frameCount - state.lastObstacle > randomGap) {
            spawnObstacle();
            state.lastObstacle = state.frameCount;
        }

        if (state.frameCount - state.lastCoin > 30 + Math.random() * 50) {
            spawnCoin();
            state.lastCoin = state.frameCount;
        }

        if (state.frameCount - state.lastPowerup > 600 + Math.random() * 400) {
            spawnPowerup();
            state.lastPowerup = state.frameCount;
        }

        updateObstacles();
        updateCoins();
        updatePowerups();
        updateParticles();
        updateStars();
        checkCollisions();

        document.getElementById('score').textContent = state.score;
        document.getElementById('coins').textContent = state.coins;
    }

    function spawnObstacle() {
        const typeRandom = Math.random();
        let type, obs;
        
        if (typeRandom < 0.5) {
            type = 'ground';
            obs = {
                x: GAME_WIDTH + 50,
                type,
                width: 30 + Math.random() * 25,
                height: 25 + Math.random() * 20,
                y: GROUND_Y
            };
            obs.y = GROUND_Y - obs.height;
        } else if (typeRandom < 0.8) {
            type = 'flying';
            obs = {
                x: GAME_WIDTH + 50,
                type,
                width: 35,
                height: 25,
                y: GROUND_Y - 60 - Math.random() * 40
            };
        } else {
            type = 'wide';
            obs = {
                x: GAME_WIDTH + 50,
                type,
                width: 60 + Math.random() * 30,
                height: 25,
                y: GROUND_Y - 25
            };
        }

        obs.color = getObstacleColor();
        obstacles.push(obs);
    }

    function getObstacleColor() {
        const colors = ['#ff6b6b', '#feca57', '#5f27cd', '#ff9ff3', '#54a0ff'];
        return colors[Math.floor(Math.random() * colors.length)];
    }

    function spawnCoin() {
        const coinY = GROUND_Y - 35 - Math.random() * 90;
        const patterns = ['single', 'arc', 'line'];
        const pattern = patterns[Math.floor(Math.random() * patterns.length)];
        
        if (pattern === 'single') {
            coins.push({ x: GAME_WIDTH + 50, y: coinY, collected: false, bounceOffset: Math.random() * Math.PI * 2 });
        } else if (pattern === 'arc') {
            for (let i = 0; i < 3; i++) {
                coins.push({
                    x: GAME_WIDTH + 50 + i * 30,
                    y: coinY - Math.abs(i - 1) * 20,
                    collected: false,
                    bounceOffset: Math.random() * Math.PI * 2
                });
            }
        } else {
            for (let i = 0; i < 4; i++) {
                coins.push({
                    x: GAME_WIDTH + 50 + i * 25,
                    y: coinY,
                    collected: false,
                    bounceOffset: Math.random() * Math.PI * 2
                });
            }
        }
    }

    function spawnPowerup() {
        const types = ['shield', 'magnet', 'double'];
        const type = types[Math.floor(Math.random() * types.length)];
        powerups.push({
            x: GAME_WIDTH + 50,
            y: GROUND_Y - 50 - Math.random() * 50,
            type,
            width: 28,
            height: 28,
            rotation: 0
        });
    }

    function updateObstacles() {
        for (let i = obstacles.length - 1; i >= 0; i--) {
            obstacles[i].x -= state.speed;
            if (obstacles[i].x + obstacles[i].width < -50) {
                obstacles.splice(i, 1);
            }
        }
    }

    function updateCoins() {
        for (let i = coins.length - 1; i >= 0; i--) {
            const coin = coins[i];
            coin.x -= state.speed;
            
            if (state.powerup === 'magnet') {
                const dx = player.x + player.width/2 - (coin.x + 10);
                const dy = player.y + player.height/2 - (coin.y + 10);
                const dist = Math.sqrt(dx*dx + dy*dy);
                if (dist < 200) {
                    coin.x += dx * 0.08;
                    coin.y += dy * 0.08;
                }
            }

            if (coin.x + 20 < -50 || coin.collected) {
                if (coin.collected) {
                    coins.splice(i, 1);
                } else if (coin.x < -20) {
                    coins.splice(i, 1);
                }
            }
        }
    }

    function updatePowerups() {
        for (let i = powerups.length - 1; i >= 0; i--) {
            powerups[i].x -= state.speed;
            powerups[i].rotation += 0.1;
            if (powerups[i].x + powerups[i].width < -50) {
                powerups.splice(i, 1);
            }
        }
    }

    function updateParticles() {
        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.25;
            p.life--;
            if (p.life <= 0) particles.splice(i, 1);
        }
    }

    function updateStars() {
        for (const star of backgroundStars) {
            star.x -= star.speed * (state.running ? 0.3 : 0.1);
            if (star.x < 0) star.x = GAME_WIDTH;
            star.brightness = 0.4 + Math.sin(state.frameCount * 0.05 + star.x * 0.01) * 0.4;
        }
        
        for (const detail of groundDetails) {
            detail.x -= state.speed * 0.5;
            if (detail.x + detail.width < 0) {
                detail.x = GAME_WIDTH + Math.random() * 50;
            }
        }
    }

    function checkCollisions() {
        const pBox = {
            x: player.x + 8,
            y: player.y + 5,
            w: player.width - 16,
            h: player.height - 10
        };

        for (const obs of obstacles) {
            if (rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, obs.x, obs.y, obs.width, obs.height)) {
                if (player.invincible) {
                    spawnParticles(obs.x + obs.width/2, obs.y + obs.height/2, 15, obs.color);
                    const idx = obstacles.indexOf(obs);
                    if (idx > -1) obstacles.splice(idx, 1);
                    state.score += 5;
                } else {
                    gameOver();
                    return;
                }
            }
        }

        for (const coin of coins) {
            if (!coin.collected && rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, coin.x, coin.y, 18, 18)) {
                coin.collected = true;
                state.coins++;
                const coinValue = state.powerup === 'double' ? 20 : 10;
                state.score += coinValue;
                spawnParticles(coin.x + 9, coin.y + 9, 10, '#ffd700');
            }
        }

        for (let i = powerups.length - 1; i >= 0; i--) {
            const pu = powerups[i];
            if (rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, pu.x, pu.y, pu.width, pu.height)) {
                activatePowerup(pu.type);
                powerups.splice(i, 1);
                spawnParticles(pu.x + pu.width/2, pu.y + pu.height/2, 20, '#00fff5');
            }
        }
    }

    function activatePowerup(type) {
        const indicator = document.getElementById('powerup');
        
        if (type === 'shield') {
            player.invincible = true;
            player.invincibilityTimer = 400;
            indicator.textContent = '🛡️ SHIELD';
        } else if (type === 'magnet') {
            state.powerup = 'magnet';
            state.powerupTimer = 400;
            indicator.textContent = '🧲 MAGNET';
        } else if (type === 'double') {
            state.powerup = 'double';
            state.powerupTimer = 400;
            indicator.textContent = '✨ 2X POINTS';
        }
        
        indicator.classList.add('active');
    }

    function rectIntersect(x1, y1, w1, h1, x2, y2, w2, h2) {
        return x1 < x2 + w2 && x1 + w1 > x2 && y1 < y2 + h2 && y1 + h1 > y2;
    }

    function gameOver() {
        state.running = false;
        state.gameOver = true;

        const finalScore = state.score + state.coins * 5 + state.combo * 10;
        if (finalScore > state.highScore) {
            state.highScore = finalScore;
            localStorage.setItem('retroRunnerHigh', state.highScore);
            document.getElementById('highscore').textContent = state.highScore;
        }

        document.getElementById('final-score').textContent = state.score;
        document.getElementById('final-coins').textContent = state.coins;
        document.getElementById('gameOver').style.display = 'block';
        document.getElementById('player-name').focus();

        for (let i = 0; i < 40; i++) {
            spawnParticles(
                player.x + player.width/2,
                player.y + player.height/2,
                3, '#e94560'
            );
        }
    }

    function draw() {
        ctx.fillStyle = '#0a0a15';
        ctx.fillRect(0, 0, GAME_WIDTH, GAME_HEIGHT);

        drawStars();
        drawGround();
        drawObstacles();
        drawCoins();
        drawPowerups();
        drawPlayer();
        drawHUD();
        drawParticles();
    }

    function drawStars() {
        for (const star of backgroundStars) {
            ctx.fillStyle = `rgba(255, 255, 255, ${star.brightness})`;
            ctx.beginPath();
            ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawGround() {
        const gradient = ctx.createLinearGradient(0, GROUND_Y, 0, GAME_HEIGHT);
        gradient.addColorStop(0, '#1a1a2e');
        gradient.addColorStop(1, '#0f0f1a');
        ctx.fillStyle = gradient;
        ctx.fillRect(0, GROUND_Y, GAME_WIDTH, GAME_HEIGHT - GROUND_Y);

        ctx.fillStyle = '#e94560';
        ctx.fillRect(0, GROUND_Y, GAME_WIDTH, 3);

        ctx.fillStyle = '#0f3460';
        for (let x = 0; x < GAME_WIDTH; x += 80) {
            ctx.fillRect(x, GROUND_Y + 8, 40, 2);
        }

        ctx.fillStyle = 'rgba(15, 52, 96, 0.5)';
        for (const detail of groundDetails) {
            ctx.fillRect(detail.x, detail.y, detail.width, detail.height);
        }
    }

    function drawPlayer() {
        for (const t of player.trail) {
            ctx.globalAlpha = t.alpha * 0.5;
            ctx.fillStyle = '#e94560';
            ctx.fillRect(t.x, t.y, player.width, player.height);
        }
        ctx.globalAlpha = 1;

        const flash = player.invincible && Math.floor(state.frameCount / 4) % 2 === 0;
        if (flash) {
            ctx.globalAlpha = 0.6;
        }

        ctx.fillStyle = player.invincible ? '#00fff5' : '#e94560';
        ctx.fillRect(player.x, player.y, player.width, player.height);

        ctx.fillStyle = '#fff';
        ctx.fillRect(player.x + 6, player.y + 8, 10, 10);
        ctx.fillRect(player.x + 24, player.y + 8, 10, 10);

        ctx.fillStyle = '#0a0a15';
        ctx.fillRect(player.x + 8, player.y + 10, 6, 6);
        ctx.fillRect(player.x + 26, player.y + 10, 6, 6);

        if (player.jumping) {
            ctx.fillStyle = '#fff';
            ctx.globalAlpha = 0.8;
            ctx.fillRect(player.x + 3, player.y - 8, 12, 12);
            ctx.fillRect(player.x + 25, player.y - 8, 12, 12);
            ctx.globalAlpha = 1;
        }

        if (player.secondJump && player.jumping) {
            ctx.fillStyle = '#ffd700';
            ctx.fillText('!', player.x + 17, player.y - 15);
        }

        ctx.globalAlpha = 1;
    }

    function drawObstacles() {
        for (const obs of obstacles) {
            ctx.fillStyle = obs.color;
            ctx.fillRect(obs.x, obs.y, obs.width, obs.height);

            ctx.fillStyle = 'rgba(0,0,0,0.3)';
            ctx.fillRect(obs.x + 3, obs.y + 3, obs.width - 6, obs.height - 6);

            ctx.fillStyle = obs.color;
            ctx.fillRect(obs.x + 6, obs.y + 6, obs.width - 12, obs.height - 12);

            ctx.fillStyle = 'rgba(255,255,255,0.2)';
            ctx.fillRect(obs.x + 8, obs.y + 8, obs.width - 20, 4);
        }
    }

    function drawCoins() {
        for (const coin of coins) {
            if (coin.collected) continue;

            const bounce = Math.sin(state.frameCount * 0.15 + coin.bounceOffset) * 4;
            const rotation = state.frameCount * 0.1;
            
            ctx.save();
            ctx.translate(coin.x + 10, coin.y + 10 + bounce);
            ctx.rotate(rotation);
            
            ctx.fillStyle = '#ffd700';
            ctx.beginPath();
            ctx.arc(0, 0, 9, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffed4a';
            ctx.beginPath();
            ctx.arc(-2, -2, 4, 0, Math.PI * 2);
            ctx.fill();
            
            ctx.restore();
        }
    }

    function drawPowerups() {
        for (const pu of powerups) {
            const pulse = 1 + Math.sin(state.frameCount * 0.1) * 0.15;
            ctx.save();
            ctx.translate(pu.x + pu.width/2, pu.y + pu.height/2);
            ctx.scale(pulse, pulse);
            ctx.rotate(pu.rotation);

            if (pu.type === 'shield') {
                ctx.fillStyle = '#00fff5';
                ctx.beginPath();
                ctx.arc(0, 0, 14, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = 'rgba(255,255,255,0.3)';
                ctx.beginPath();
                ctx.arc(-3, -3, 5, 0, Math.PI * 2);
                ctx.fill();
            } else if (pu.type === 'magnet') {
                ctx.fillStyle = '#e74c3c';
                ctx.beginPath();
                ctx.arc(-8, 0, 8, 0, Math.PI * 2);
                ctx.arc(8, 0, 8, 0, Math.PI * 2);
                ctx.fill();
            } else if (pu.type === 'double') {
                ctx.fillStyle = '#2ecc71';
                ctx.beginPath();
                ctx.moveTo(0, -14);
                ctx.lineTo(12, 7);
                ctx.lineTo(-12, 7);
                ctx.closePath();
                ctx.fill();
            }

            ctx.restore();
        }
    }

    function drawHUD() {
        if (state.combo > 1) {
            ctx.fillStyle = '#ffd700';
            ctx.font = '16px "Press Start 2P"';
            ctx.fillText(`COMBO x${state.combo}`, GAME_WIDTH - 180, 30);
        }

        ctx.fillStyle = '#e94560';
        ctx.font = '12px "Press Start 2P"';
        ctx.fillText(`LV ${state.level}`, 10, 25);
    }

    function drawParticles() {
        for (const p of particles) {
            ctx.globalAlpha = p.life / 40;
            ctx.fillStyle = p.color;
            ctx.fillRect(p.x - p.size/2, p.y - p.size/2, p.size, p.size);
        }
        ctx.globalAlpha = 1;
    }

    function gameLoop() {
        update();
        draw();
        if (state.running || particles.length > 0) {
            requestAnimationFrame(gameLoop);
        }
    }

    draw();
})();