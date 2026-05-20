(function () {
    const canvas = document.getElementById('game-canvas');
    const ctx = canvas.getContext('2d');

    const CANVAS_WIDTH = 480;
    const CANVAS_HEIGHT = 640;

    let gameState = 'start';
    let score = 0;
    let multiplier = 1.0;
    let coinsCollected = 0;
    let pipesPassed = 0;
    let personalBest = parseInt(localStorage.getItem('flappy_best') || '0');

    let bird = { x: 100, y: 200, velocity: 0, radius: 15 };
    const gravity = 0.5;
    const flapStrength = -8;

    let pipes = [];
    let coins = [];
    let powerup = null;
    let activePowerup = null;
    let powerupTimer = 0;
    let powerupEndTime = 0;

    let difficulty = 1;
    let baseSpeed = 2.5;
    let gapSize = 150;

    let animationId;
    let lastTime = 0;

    const difficultyTiers = [
        { speed: 2.5, gap: 150 },
        { speed: 3.0, gap: 140 },
        { speed: 3.5, gap: 130 },
        { speed: 4.0, gap: 120 },
        { speed: 4.5, gap: 115 }
    ];

    const powerupTypes = [
        { type: 'shield', color: '#3b82f6', icon: '🛡️', duration: 0 },
        { type: 'slowmo', color: '#8b5cf6', icon: '⏳', duration: 5000 },
        { type: 'magnet', color: '#f59e0b', icon: '🧲', duration: 8000 },
        { type: 'double', color: '#22c55e', icon: '✖️', duration: 10000 }
    ];

    function init() {
        bird = { x: 100, y: 200, velocity: 0, radius: 15 };
        pipes = [];
        coins = [];
        powerup = null;
        activePowerup = null;
        score = 0;
        multiplier = 1.0;
        coinsCollected = 0;
        pipesPassed = 0;
        difficulty = 1;
        baseSpeed = 2.5;
        gapSize = 150;
    }

    function startGame() {
        document.getElementById('start-screen').classList.add('hidden');
        document.getElementById('game-over').classList.add('hidden');
        gameState = 'playing';
        init();
        lastTime = performance.now();
        gameLoop(lastTime);
    }

    function flap() {
        if (gameState === 'playing') {
            bird.velocity = flapStrength;
        }
    }

    function update(deltaTime) {
        if (gameState !== 'playing') return;

        const dt = deltaTime / 16.67;
        const speed = activePowerup === 'slowmo' ? baseSpeed * 0.5 : baseSpeed;

        bird.velocity += gravity * dt;
        bird.y += bird.velocity * dt;

        if (bird.y + bird.radius > CANVAS_HEIGHT || bird.y - bird.radius < 0) {
            if (activePowerup === 'shield') {
                activePowerup = null;
                bird.y = Math.max(bird.radius, Math.min(CANVAS_HEIGHT - bird.radius, bird.y));
                bird.velocity = 0;
            } else {
                gameOver();
                return;
            }
        }

        if (pipes.length === 0 || pipes[pipes.length - 1].x < CANVAS_WIDTH - 200) {
            const pipeY = Math.random() * (CANVAS_HEIGHT - gapSize - 100) + 50;
            pipes.push({
                x: CANVAS_WIDTH,
                topHeight: pipeY,
                bottomY: pipeY + gapSize,
                passed: false
            });

            const coinCount = Math.floor(Math.random() * 3) + 2;
            for (let i = 0; i < coinCount; i++) {
                coins.push({
                    x: CANVAS_WIDTH + 50 + Math.random() * 100,
                    y: pipeY + 30 + Math.random() * (gapSize - 60),
                    collected: false
                });
            }

            if (Math.random() < 0.1 && !powerup) {
                const type = powerupTypes[Math.floor(Math.random() * powerupTypes.length)];
                powerup = {
                    x: CANVAS_WIDTH + 80,
                    y: pipeY + gapSize / 2,
                    ...type
                };
            }
        }

        pipes.forEach(pipe => {
            pipe.x -= speed * dt;
        });

        pipes = pipes.filter(pipe => {
            if (!pipe.passed && pipe.x + 50 < bird.x) {
                pipe.passed = true;
                pipesPassed++;
                score++;
                updateMultiplier();

                const tierIndex = Math.min(Math.floor(pipesPassed / 15), 4);
                if (tierIndex >= difficulty) {
                    difficulty = tierIndex + 1;
                    const tier = difficultyTiers[Math.min(tierIndex, 4)];
                    baseSpeed = tier.speed;
                    gapSize = tier.gap;
                    showDifficultyFlash();
                }
            }
            return pipe.x > -100;
        });

        coins.forEach(coin => {
            if (!coin.collected) {
                coin.x -= speed * dt;
                const dx = coin.x - bird.x;
                const dy = coin.y - bird.y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                let collectRadius = 25;
                if (activePowerup === 'magnet') {
                    collectRadius = 120;
                    if (dist < collectRadius) {
                        coin.x += (bird.x - coin.x) * 0.1;
                        coin.y += (bird.y - coin.y) * 0.1;
                    }
                }

                if (dist < collectRadius) {
                    coin.collected = true;
                    const value = activePowerup === 'double' ? 10 : 5;
                    coinsCollected = Math.min(coinsCollected + value, 50);
                }
            }
        });
        coins = coins.filter(c => c.x > -30 || !c.collected);

        if (powerup) {
            powerup.x -= speed * dt;
            const dx = powerup.x - bird.x;
            const dy = powerup.y - bird.y;
            if (Math.sqrt(dx * dx + dy * dy) < 30) {
                activePowerup = powerup.type;
                powerupEndTime = performance.now() + powerup.duration;
                powerup = null;
                if (powerup.duration === 0) {
                    activePowerup = null;
                }
            } else if (powerup.x < -30) {
                powerup = null;
            }
        }

        if (activePowerup && powerup.duration > 0 && performance.now() > powerupEndTime) {
            activePowerup = null;
        }

        pipes.forEach(pipe => {
            const hitTop = bird.x + bird.radius > pipe.x && bird.x - bird.radius < pipe.x + 50 && bird.y - bird.radius < pipe.topHeight;
            const hitBottom = bird.x + bird.radius > pipe.x && bird.x - bird.radius < pipe.x + 50 && bird.y + bird.radius > pipe.bottomY;

            if (hitTop || hitBottom) {
                if (activePowerup === 'shield') {
                    activePowerup = null;
                } else {
                    gameOver();
                }
            }
        });

        updateHUD();
    }

    function updateMultiplier() {
        const tier = Math.floor(pipesPassed / 10);
        multiplier = Math.min(3.0, 1.0 + tier * 0.5);
    }

    function showDifficultyFlash() {
        const flash = document.createElement('div');
        flash.textContent = 'Speed up!';
        flash.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: bold;
            color: #f59e0b;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            animation: fadeUp 1s ease forwards;
            pointer-events: none;
        `;
        document.querySelector('.game-container').appendChild(flash);
        setTimeout(() => flash.remove(), 1000);
    }

    function draw() {
        ctx.fillStyle = '#1a1a2e';
        ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

        ctx.fillStyle = '#0f0f1a';
        for (let i = 0; i < 20; i++) {
            ctx.beginPath();
            ctx.arc((i * 73) % CANVAS_WIDTH, (i * 47) % CANVAS_HEIGHT, 2, 0, Math.PI * 2);
            ctx.fill();
        }

        pipes.forEach(pipe => {
            ctx.fillStyle = '#2d5a27';
            ctx.fillRect(pipe.x, 0, 50, pipe.topHeight);
            ctx.fillStyle = '#1a3d16';
            ctx.fillRect(pipe.x + 5, 0, 40, pipe.topHeight - 5);

            ctx.fillStyle = '#2d5a27';
            ctx.fillRect(pipe.x, pipe.bottomY, 50, CANVAS_HEIGHT - pipe.bottomY);
            ctx.fillStyle = '#1a3d16';
            ctx.fillRect(pipe.x + 5, pipe.bottomY + 5, 40, CANVAS_HEIGHT - pipe.bottomY - 5);
        });

        coins.forEach(coin => {
            if (!coin.collected) {
                ctx.beginPath();
                ctx.arc(coin.x, coin.y, 10, 0, Math.PI * 2);
                ctx.fillStyle = '#f59e0b';
                ctx.fill();
                ctx.strokeStyle = '#fbbf24';
                ctx.lineWidth = 2;
                ctx.stroke();
            }
        });

        if (powerup) {
            ctx.beginPath();
            ctx.arc(powerup.x, powerup.y, 15, 0, Math.PI * 2);
            const gradient = ctx.createRadialGradient(powerup.x, powerup.y, 0, powerup.x, powerup.y, 15);
            gradient.addColorStop(0, powerup.color);
            gradient.addColorStop(1, 'transparent');
            ctx.fillStyle = gradient;
            ctx.fill();
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.fillText(powerup.icon, powerup.x, powerup.y + 5);
        }

        ctx.beginPath();
        ctx.arc(bird.x, bird.y, bird.radius, 0, Math.PI * 2);
        const birdGradient = ctx.createRadialGradient(bird.x - 5, bird.y - 5, 0, bird.x, bird.y, bird.radius);
        birdGradient.addColorStop(0, '#fbbf24');
        birdGradient.addColorStop(1, '#d97706');
        ctx.fillStyle = birdGradient;
        ctx.fill();
        ctx.strokeStyle = '#b45309';
        ctx.lineWidth = 2;
        ctx.stroke();

        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(bird.x + 5, bird.y - 3, 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(bird.x + 6, bird.y - 4, 2, 0, Math.PI * 2);
        ctx.fill();

        const style = document.createElement('style');
        style.textContent = '@keyframes fadeUp { from { opacity: 1; transform: translate(-50%, -50%); } to { opacity: 0; transform: translate(-50%, -150%); } }';
        document.head.appendChild(style);
    }

    function updateHUD() {
        document.getElementById('score').textContent = score;
        const multEl = document.getElementById('multiplier');
        multEl.textContent = `×${multiplier.toFixed(1)}`;
        multEl.classList.toggle('hidden', multiplier === 1.0);

        const powerupBar = document.getElementById('powerup-bar');
        if (activePowerup && powerupTypes.find(p => p.type === activePowerup)?.duration > 0) {
            powerupBar.classList.remove('hidden');
            const type = powerupTypes.find(p => p.type === activePowerup);
            document.getElementById('powerup-icon').textContent = type.icon;
        } else {
            powerupBar.classList.add('hidden');
        }
    }

    function gameLoop(timestamp) {
        const deltaTime = timestamp - lastTime;
        lastTime = timestamp;

        update(deltaTime);
        draw();

        if (gameState === 'playing') {
            animationId = requestAnimationFrame(gameLoop);
        }
    }

    function gameOver() {
        gameState = 'gameover';
        cancelAnimationFrame(animationId);

        const isNewBest = score > personalBest;
        if (isNewBest) {
            personalBest = score;
            localStorage.setItem('flappy_best', personalBest.toString());
        }

        const gameOverEl = document.getElementById('game-over');
        gameOverEl.classList.remove('hidden');

        animateValue('final-score-value', 0, score, 1200);
        document.getElementById('multiplier-result-value').textContent = `×${multiplier.toFixed(1)}`;

        const currencyEarned = Math.floor(score * multiplier * 2) + coinsCollected;
        animateValue('currency-value', 0, currencyEarned, 1200, '+');
        animateValue('xp-value', 0, score, 1200, '+');

        document.getElementById('personal-best').classList.toggle('hidden', !isNewBest);

        saveScore();
    }

    function animateValue(id, start, end, duration, prefix = '') {
        const el = document.getElementById(id);
        const range = end - start;
        const startTime = performance.now();

        function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const value = Math.floor(start + range * progress);
            el.textContent = prefix + value;
            if (progress < 1) {
                requestAnimationFrame(update);
            }
        }
        requestAnimationFrame(update);
    }

    async function saveScore() {
        const token = document.getElementById('game-token').value;
        const csrf = document.getElementById('csrf-token').value;

        try {
            const response = await fetch(APP_URL + '/api/save_score.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `game_token=${encodeURIComponent(token)}&score=${score}&multiplier=${multiplier}&coins_collected=${coinsCollected}&csrf_token=${encodeURIComponent(csrf)}`
            });

            const data = await response.json();

            if (data.success) {
                const balanceInput = document.getElementById('user-balance');
                if (balanceInput) balanceInput.value = data.new_balance;

                const balanceSpan = document.querySelector('.balance');
                if (balanceSpan) balanceSpan.textContent = '💰' + data.new_balance;

                if (data.new_achievements && data.new_achievements.length > 0) {
                    setTimeout(() => {
                        window.showAchievements(data.new_achievements);
                    }, 500);
                }

                if (data.level_up) {
                    showLevelUp(data.new_level);
                }
            }
        } catch (e) {
            console.error('Failed to save score:', e);
        }
    }

    function showLevelUp(level) {
        const flash = document.createElement('div');
        flash.className = 'level-up-flash';
        document.body.appendChild(flash);
        setTimeout(() => flash.classList.add('show'), 10);
        setTimeout(() => flash.remove(), 500);

        window.showToast(`Level up! You're now level ${level}`, 'success');
    }

    document.getElementById('start-btn').addEventListener('click', startGame);
    document.getElementById('play-again').addEventListener('click', () => {
        window.location.reload();
    });

    canvas.addEventListener('click', flap);
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space') {
            e.preventDefault();
            flap();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.code === 'Escape' && gameState === 'playing') {
            gameState = 'paused';
            cancelAnimationFrame(animationId);
            document.getElementById('pause-btn').textContent = '▶';
        } else if (e.code === 'Escape' && gameState === 'paused') {
            gameState = 'playing';
            lastTime = performance.now();
            gameLoop(lastTime);
            document.getElementById('pause-btn').textContent = '⏸';
        }
    });

    document.getElementById('pause-btn').addEventListener('click', () => {
        if (gameState === 'playing') {
            gameState = 'paused';
            cancelAnimationFrame(animationId);
            document.getElementById('pause-btn').textContent = '▶';
        } else if (gameState === 'paused') {
            gameState = 'playing';
            lastTime = performance.now();
            gameLoop(lastTime);
            document.getElementById('pause-btn').textContent = '⏸';
        }
    });
})();