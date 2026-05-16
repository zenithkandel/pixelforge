(function() {
    'use strict';

    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');

    const GAME_WIDTH = 800;
    const GAME_HEIGHT = 400;
    const GROUND_Y = 350;

    const config = {
        baseSpeed: 4,
        maxSpeed: 7,
        speedIncreaseRate: 0.0003,
        jumpForce: -12,
        secondJumpForce: -10,
        gravity: 0.45,
        slideDuration: 45
    };

    const state = {
        running: false,
        gameOver: false,
        score: 0,
        coins: 0,
        highScore: parseInt(localStorage.getItem('retroRunnerHigh')) || 0,
        speed: config.baseSpeed,
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
        x: 80,
        y: GROUND_Y - 40,
        width: 36,
        height: 40,
        velocityY: 0,
        jumping: false,
        canDoubleJump: false,
        sliding: false,
        slideTimer: 0,
        invincible: false,
        invincibilityTimer: 0,
        groundY: GROUND_Y - 40
    };

    const obstacles = [];
    const coins = [];
    const powerups = [];
    const particles = [];
    const stars = [];
    const groundLines = [];

    const keys = { space: false, spaceLocked: false };

    for (let i = 0; i < 60; i++) {
        stars.push({
            x: Math.random() * GAME_WIDTH,
            y: Math.random() * (GROUND_Y - 60),
            size: Math.random() * 1.5 + 0.5,
            speed: Math.random() * 0.2 + 0.05
        });
    }

    for (let i = 0; i < 15; i++) {
        groundLines.push({
            x: Math.random() * GAME_WIDTH,
            y: GROUND_Y + 12 + Math.random() * 25,
            w: 2 + Math.random() * 6,
            h: 1 + Math.random() * 3
        });
    }

    document.addEventListener('keydown', e => {
        if (e.code === 'Space') {
            e.preventDefault();
            if (!state.running && !state.gameOver) {
                startGame();
            } else if (state.gameOver) {
                restartGame();
            } else if (!keys.spaceLocked) {
                keys.space = true;
                keys.spaceLocked = true;
                handleJump();
            }
        }
        if (e.code === 'ArrowDown' || e.code === 'KeyS') {
            e.preventDefault();
            if (!player.jumping && !player.sliding && state.running) {
                startSlide();
            }
        }
    });

    document.addEventListener('keyup', e => {
        if (e.code === 'Space') {
            keys.space = false;
            keys.spaceLocked = false;
        }
        if (e.code === 'ArrowDown' || e.code === 'KeyS') {
            if (player.sliding) endSlide();
        }
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
        state.speed = config.baseSpeed;
        state.distance = 0;
        state.frameCount = 0;
        state.powerup = null;
        state.powerupTimer = 0;
        state.combo = 0;
        state.comboTimer = 0;
        state.level = 1;

        player.y = player.groundY;
        player.velocityY = 0;
        player.jumping = false;
        player.canDoubleJump = false;
        player.sliding = false;
        player.slideTimer = 0;
        player.invincible = false;

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
        const finalScore = state.score + state.coins * 5;
        fetch('/api/game/score', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ score: finalScore, player: name })
        }).then(r => r.json()).then(d => {
            if (d.ok) alert(`Rank: #${d.rank}`);
        });
        restartGame();
    }

    function handleJump() {
        if (!player.sliding) {
            if (!player.jumping) {
                player.velocityY = config.jumpForce;
                player.jumping = true;
                player.canDoubleJump = true;
                spawnParticles(player.x + player.width/2, player.y + player.height, 6, '#00fff5');
            } else if (player.canDoubleJump) {
                player.velocityY = config.secondJumpForce;
                player.canDoubleJump = false;
                spawnParticles(player.x + player.width/2, player.y + player.height/2, 10, '#ffd700');
                
                state.combo = state.comboTimer > 0 ? state.combo + 1 : 1;
                state.comboTimer = 90;
            }
        }
    }

    function startSlide() {
        player.sliding = true;
        player.slideTimer = config.slideDuration;
        player.height = 18;
        player.y = GROUND_Y - 18;
        spawnParticles(player.x, player.y + 5, 4, '#e94560');
    }

    function endSlide() {
        player.sliding = false;
        player.height = 40;
        player.y = player.groundY;
    }

    function spawnParticles(x, y, count, color) {
        for (let i = 0; i < count; i++) {
            particles.push({
                x, y,
                vx: (Math.random() - 0.5) * 5,
                vy: (Math.random() - 0.8) * 4,
                life: 20 + Math.random() * 10,
                color,
                size: Math.random() * 4 + 2
            });
        }
    }

    function update() {
        if (!state.running) return;

        state.frameCount++;
        state.distance += state.speed;

        if (state.speed < config.maxSpeed) {
            state.speed += config.speedIncreaseRate;
        }

        state.score = Math.floor(state.distance / 12);
        state.level = 1 + Math.floor(state.distance / 3000);

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
            if (player.invincibilityTimer <= 0) player.invincible = false;
        }

        if (player.jumping) {
            player.velocityY += config.gravity;
            player.y += player.velocityY;

            if (player.y >= player.groundY) {
                player.y = player.groundY;
                player.jumping = false;
                player.canDoubleJump = false;
                player.velocityY = 0;
            }
        }

        if (player.sliding) {
            player.slideTimer--;
            if (player.slideTimer <= 0) endSlide();
        }

        const minFrames = Math.max(70, 100 - state.level * 8);
        if (state.frameCount - state.lastObstacle > minFrames + Math.random() * 40) {
            spawnObstacle();
            state.lastObstacle = state.frameCount;
        }

        if (state.frameCount - state.lastCoin > 25 + Math.random() * 35) {
            spawnCoin();
            state.lastCoin = state.frameCount;
        }

        if (state.frameCount - state.lastPowerup > 500 + Math.random() * 500) {
            spawnPowerup();
            state.lastPowerup = state.frameCount;
        }

        updateGameObjects();
        checkCollisions();
        updateUI();
    }

    function spawnObstacle() {
        const types = ['ground', 'flying', 'double'];
        const type = types[Math.floor(Math.random() * types.length)];
        
        let obs = { x: GAME_WIDTH + 30, type };

        if (type === 'ground') {
            obs.width = 28 + Math.random() * 18;
            obs.height = 28 + Math.random() * 15;
            obs.y = GROUND_Y - obs.height;
        } else if (type === 'flying') {
            obs.width = 32;
            obs.height = 22;
            obs.y = GROUND_Y - 55 - Math.random() * 35;
        } else {
            obs.width = 50;
            obs.height = 22;
            obs.y = GROUND_Y - 22;
        }

        obs.color = ['#ff6b6b', '#feca57', '#a29bfe', '#fd79a8'][Math.floor(Math.random() * 4)];
        obstacles.push(obs);
    }

    function spawnCoin() {
        const y = GROUND_Y - 30 - Math.random() * 80;
        coins.push({ x: GAME_WIDTH + 30, y, collected: false, offset: Math.random() * Math.PI * 2 });
    }

    function spawnPowerup() {
        const types = ['shield', 'magnet', 'double'];
        const type = types[Math.floor(Math.random() * types.length)];
        powerups.push({
            x: GAME_WIDTH + 30,
            y: GROUND_Y - 45 - Math.random() * 45,
            type,
            width: 26,
            height: 26,
            rot: 0
        });
    }

    function updateGameObjects() {
        for (let i = obstacles.length - 1; i >= 0; i--) {
            obstacles[i].x -= state.speed;
            if (obstacles[i].x + obstacles[i].width < -20) obstacles.splice(i, 1);
        }

        for (let i = coins.length - 1; i >= 0; i--) {
            const c = coins[i];
            c.x -= state.speed;

            if (state.powerup === 'magnet') {
                const dx = (player.x + player.width/2) - (c.x + 10);
                const dy = (player.y + player.height/2) - (c.y + 10);
                const dist = Math.sqrt(dx*dx + dy*dy);
                if (dist < 180) {
                    c.x += dx * 0.06;
                    c.y += dy * 0.06;
                }
            }

            if (c.x < -20 || c.collected) {
                if (c.collected) coins.splice(i, 1);
                else if (c.x < -20) coins.splice(i, 1);
            }
        }

        for (let i = powerups.length - 1; i >= 0; i--) {
            powerups[i].x -= state.speed;
            powerups[i].rot += 0.08;
            if (powerups[i].x < -20) powerups.splice(i, 1);
        }

        for (let i = particles.length - 1; i >= 0; i--) {
            const p = particles[i];
            p.x += p.vx;
            p.y += p.vy;
            p.vy += 0.2;
            p.life--;
            if (p.life <= 0) particles.splice(i, 1);
        }

        for (const s of stars) {
            s.x -= s.speed * (state.running ? 0.25 : 0.1);
            if (s.x < 0) s.x = GAME_WIDTH;
        }

        for (const l of groundLines) {
            l.x -= state.speed * 0.4;
            if (l.x + l.w < 0) l.x = GAME_WIDTH + Math.random() * 30;
        }
    }

    function checkCollisions() {
        const pBox = {
            x: player.x + 6,
            y: player.y + 4,
            w: player.width - 12,
            h: player.height - 8
        };

        for (const obs of obstacles) {
            if (rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, obs.x, obs.y, obs.width, obs.height)) {
                if (player.invincible) {
                    spawnParticles(obs.x + obs.width/2, obs.y + obs.height/2, 12, obs.color);
                    obstacles.splice(obstacles.indexOf(obs), 1);
                    state.score += 5;
                } else {
                    endGame();
                    return;
                }
            }
        }

        for (const c of coins) {
            if (!c.collected && rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, c.x, c.y, 16, 16)) {
                c.collected = true;
                state.coins++;
                state.score += state.powerup === 'double' ? 20 : 10;
                spawnParticles(c.x + 8, c.y + 8, 8, '#ffd700');
            }
        }

        for (let i = powerups.length - 1; i >= 0; i--) {
            const p = powerups[i];
            if (rectIntersect(pBox.x, pBox.y, pBox.w, pBox.h, p.x, p.y, p.width, p.height)) {
                activatePowerup(p.type);
                powerups.splice(i, 1);
                spawnParticles(p.x + p.width/2, p.y + p.height/2, 15, '#00fff5');
            }
        }
    }

    function activatePowerup(type) {
        const ind = document.getElementById('powerup');
        if (type === 'shield') {
            player.invincible = true;
            player.invincibilityTimer = 350;
            ind.textContent = 'SHIELD';
        } else if (type === 'magnet') {
            state.powerup = 'magnet';
            state.powerupTimer = 350;
            ind.textContent = 'MAGNET';
        } else if (type === 'double') {
            state.powerup = 'double';
            state.powerupTimer = 350;
            ind.textContent = '2X';
        }
        ind.classList.add('active');
    }

    function rectIntersect(x1, y1, w1, h1, x2, y2, w2, h2) {
        return x1 < x2 + w2 && x1 + w1 > x2 && y1 < y2 + h2 && y1 + h1 > y2;
    }

    function endGame() {
        state.running = false;
        state.gameOver = true;

        const finalScore = state.score;
        if (finalScore > state.highScore) {
            state.highScore = finalScore;
            localStorage.setItem('retroRunnerHigh', state.highScore);
            document.getElementById('highscore').textContent = state.highScore;
        }

        document.getElementById('final-score').textContent = state.score;
        document.getElementById('final-coins').textContent = state.coins;
        document.getElementById('gameOver').style.display = 'block';
        document.getElementById('player-name').focus();

        for (let i = 0; i < 30; i++) {
            spawnParticles(player.x + player.width/2, player.y + player.height/2, 2, '#e94560');
        }
    }

    function updateUI() {
        document.getElementById('score').textContent = state.score;
        document.getElementById('coins').textContent = state.coins;
    }

    function draw() {
        ctx.fillStyle = '#0d0d14';
        ctx.fillRect(0, 0, GAME_WIDTH, GAME_HEIGHT);

        drawStars();
        drawGround();
        drawObstacles();
        drawCoins();
        drawPowerups();
        drawPlayer();
        drawParticles();
        drawHUD();
    }

    function drawStars() {
        for (const s of stars) {
            const a = 0.3 + Math.sin(state.frameCount * 0.03 + s.x * 0.01) * 0.3;
            ctx.fillStyle = `rgba(255,255,255,${a})`;
            ctx.beginPath();
            ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawGround() {
        const g = ctx.createLinearGradient(0, GROUND_Y, 0, GAME_HEIGHT);
        g.addColorStop(0, '#1a1a2e');
        g.addColorStop(1, '#0d0d14');
        ctx.fillStyle = g;
        ctx.fillRect(0, GROUND_Y, GAME_WIDTH, GAME_HEIGHT - GROUND_Y);

        ctx.fillStyle = '#e94560';
        ctx.fillRect(0, GROUND_Y, GAME_WIDTH, 2);

        ctx.fillStyle = '#0f3460';
        for (let x = 0; x < GAME_WIDTH; x += 60) {
            ctx.fillRect(x, GROUND_Y + 6, 30, 2);
        }

        ctx.fillStyle = 'rgba(15,52,96,0.4)';
        for (const l of groundLines) {
            ctx.fillRect(l.x, l.y, l.w, l.h);
        }
    }

    function drawPlayer() {
        const flash = player.invincible && Math.floor(state.frameCount / 3) % 2 === 0;
        if (flash) ctx.globalAlpha = 0.5;

        ctx.fillStyle = player.invincible ? '#00fff5' : '#e94560';
        ctx.fillRect(player.x, player.y, player.width, player.height);

        ctx.fillStyle = '#fff';
        ctx.fillRect(player.x + 5, player.y + 8, 9, 9);
        ctx.fillRect(player.x + 20, player.y + 8, 9, 9);

        ctx.fillStyle = '#0d0d14';
        ctx.fillRect(player.x + 7, player.y + 10, 5, 5);
        ctx.fillRect(player.x + 22, player.y + 10, 5, 5);

        if (player.jumping && player.canDoubleJump) {
            ctx.fillStyle = '#ffd700';
            ctx.fillRect(player.x + 2, player.y - 8, 8, 8);
            ctx.fillRect(player.x + 24, player.y - 8, 8, 8);
        }

        if (player.sliding) {
            ctx.fillStyle = '#e94560';
            ctx.fillRect(player.x - 4, player.y + 2, 8, 16);
        }

        ctx.globalAlpha = 1;
    }

    function drawObstacles() {
        for (const o of obstacles) {
            ctx.fillStyle = o.color;
            ctx.fillRect(o.x, o.y, o.width, o.height);

            ctx.fillStyle = 'rgba(0,0,0,0.25)';
            ctx.fillRect(o.x + 2, o.y + 2, o.width - 4, o.height - 4);

            ctx.fillStyle = o.color;
            ctx.fillRect(o.x + 5, o.y + 5, o.width - 10, o.height - 10);
        }
    }

    function drawCoins() {
        for (const c of coins) {
            if (c.collected) continue;

            const bob = Math.sin(state.frameCount * 0.12 + c.offset) * 3;

            ctx.fillStyle = '#ffd700';
            ctx.beginPath();
            ctx.arc(c.x + 8, c.y + 8 + bob, 8, 0, Math.PI * 2);
            ctx.fill();

            ctx.fillStyle = '#ffed4a';
            ctx.beginPath();
            ctx.arc(c.x + 6, c.y + 6 + bob, 3, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    function drawPowerups() {
        for (const p of powerups) {
            const psize = 1 + Math.sin(state.frameCount * 0.1) * 0.1;
            ctx.save();
            ctx.translate(p.x + p.width/2, p.y + p.height/2);
            ctx.scale(psize, psize);
            ctx.rotate(p.rot);

            if (p.type === 'shield') {
                ctx.fillStyle = '#00fff5';
                ctx.beginPath();
                ctx.arc(0, 0, 12, 0, Math.PI * 2);
                ctx.fill();
            } else if (p.type === 'magnet') {
                ctx.fillStyle = '#e74c3c';
                ctx.beginPath();
                ctx.arc(-6, 0, 7, 0, Math.PI * 2);
                ctx.arc(6, 0, 7, 0, Math.PI * 2);
                ctx.fill();
            } else if (p.type === 'double') {
                ctx.fillStyle = '#2ecc71';
                ctx.beginPath();
                ctx.moveTo(0, -12);
                ctx.lineTo(10, 6);
                ctx.lineTo(-10, 6);
                ctx.closePath();
                ctx.fill();
            }

            ctx.restore();
        }
    }

    function drawParticles() {
        for (const p of particles) {
            ctx.globalAlpha = p.life / 30;
            ctx.fillStyle = p.color;
            ctx.fillRect(p.x - p.size/2, p.y - p.size/2, p.size, p.size);
        }
        ctx.globalAlpha = 1;
    }

    function drawHUD() {
        if (state.combo > 1) {
            ctx.fillStyle = '#ffd700';
            ctx.font = '14px "Press Start 2P"';
            ctx.fillText(`COMBO x${state.combo}`, GAME_WIDTH - 160, 28);
        }

        ctx.fillStyle = '#e94560';
        ctx.font = '10px "Press Start 2P"';
        ctx.fillText(`LV${state.level}`, 10, 22);
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