(function() {
    var canvas = document.getElementById('game-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var GRAVITY = 0.35;
    var FLAP_VELOCITY = -7;
    var PIPE_WIDTH = 60;
    var BIRD_SIZE = 24;

    var bird = { x: 80, y: 300, vy: 0, size: BIRD_SIZE };
    var pipes = [];
    var coins = [];
    var powerUps = [];
    var frame = 0;
    var score = 0;
    var pipesSinceLastPowerUp = 0;
    var gameRunning = false;
    var gameStarted = false;
    var animFrame = null;

    var activePowerUp = null;
    var activeShield = false;
    var coinsCollected = 0;

    var pipeSpeed = 2.5;
    var gapHeight = 150;
    var speedTier = 1;
    var flashMessage = '';
    var flashTimer = 0;

    var multiplier = 1.0;

    var backgroundStars = [];
    for (var i = 0; i < 60; i++) {
        backgroundStars.push({ x: Math.random() * 480, y: Math.random() * 640, r: Math.random() * 1.2 + 0.3, speed: Math.random() * 0.3 + 0.1 });
    }

    var scoreEl = document.getElementById('game-hud-score');
    var multiplierEl = document.getElementById('game-hud-multiplier');
    var powerupHud = document.getElementById('game-hud-powerup');
    var powerupIcon = document.getElementById('powerup-icon');
    var powerupBar = document.getElementById('powerup-bar');
    var overlay = document.getElementById('game-overlay');

    var gameToken = '';
    var csrfToken = '';

    function reset() {
        bird.y = 300;
        bird.vy = 0;
        pipes = [];
        coins = [];
        powerUps = [];
        frame = 0;
        score = 0;
        pipesSinceLastPowerUp = 0;
        gameRunning = false;
        gameStarted = false;
        activePowerUp = null;
        activeShield = false;
        coinsCollected = 0;
        pipeSpeed = 2.5;
        gapHeight = 150;
        speedTier = 1;
        multiplier = 1.0;
        flashMessage = '';
        flashTimer = 0;
        if (scoreEl) scoreEl.textContent = '0';
        if (multiplierEl) multiplierEl.style.display = 'none';
        if (powerupHud) powerupHud.style.display = 'none';
        if (overlay) overlay.style.display = 'none';
        render();
    }

    function spawnPipe() {
        var minGap = Math.max(80, gapHeight - 30);
        var maxGap = Math.min(350, gapHeight + 30);
        var gap = minGap + Math.random() * (maxGap - minGap);
        var gapY = 100 + Math.random() * (540 - gap - 100);
        pipes.push({ x: 500, gapY: gapY, gapH: gap, scored: false });
    }

    function spawnCoins(pipe) {
        var gapCenter = pipe.gapY + pipe.gapH / 2;
        var count = 2 + Math.floor(Math.random() * 3);
        for (var i = 0; i < count; i++) {
            var offset = (i - (count - 1) / 2) * 30;
            coins.push({ x: pipe.x + PIPE_WIDTH / 2, y: gapCenter + offset, collected: false, r: 6 });
        }
    }

    function spawnPowerUp(pipe) {
        var gapCenter = pipe.gapY + pipe.gapH / 2;
        var types = ['shield', 'slowmo', 'magnet', 'double_coin'];
        var type = types[Math.floor(Math.random() * types.length)];
        var colors = { shield: '#3b82f6', slowmo: '#a78bfa', magnet: '#fbbf24', double_coin: '#22c55e' };
        var icons = { shield: '\u{1f6e1}\u{fe0f}', slowmo: '\u{23f1}\u{fe0f}', magnet: '\u{1f9f2}', double_coin: '\u{1f48e}' };
        powerUps.push({ x: pipe.x + PIPE_WIDTH / 2, y: gapCenter, type: type, collected: false, r: 10, color: colors[type], icon: icons[type] });
    }

    function flap() {
        if (!gameStarted) {
            gameStarted = true;
            gameRunning = true;
            bird.vy = FLAP_VELOCITY;
            loop();
            return;
        }
        if (!gameRunning) return;
        bird.vy = FLAP_VELOCITY;
    }

    function hitTest(ax, ay, ar, bx, by, br) {
        var dx = ax - bx;
        var dy = ay - by;
        return Math.sqrt(dx * dx + dy * dy) < ar + br;
    }

    function update() {
        if (!gameRunning) return;
        frame++;

        bird.vy += activePowerUp && activePowerUp.type === 'slowmo' ? GRAVITY * 0.5 : GRAVITY;
        bird.y += activePowerUp && activePowerUp.type === 'slowmo' ? bird.vy * 0.5 : bird.vy;

        if (bird.y < 0) bird.y = 0;
        if (bird.y > 640) die();

        if (frame % 90 === 0) {
            spawnPipe();
            pipesSinceLastPowerUp++;
            if (pipesSinceLastPowerUp >= 8 && powerUps.length === 0 && Math.random() < 0.5) {
                pipesSinceLastPowerUp = 0;
            }
        }

        var actualSpeed = activePowerUp && activePowerUp.type === 'slowmo' ? pipeSpeed * 0.5 : pipeSpeed;

        for (var i = pipes.length - 1; i >= 0; i--) {
            var p = pipes[i];
            p.x -= actualSpeed;

            if (!p.scored && p.x + PIPE_WIDTH < bird.x - BIRD_SIZE / 2) {
                p.scored = true;
                score++;
                if (scoreEl) scoreEl.textContent = score;

                if (score % 15 === 0 && speedTier < 5) {
                    speedTier++;
                    if (speedTier === 2) { pipeSpeed = 3.0; gapHeight = 140; flashMessage = 'Speeding up!'; }
                    else if (speedTier === 3) { pipeSpeed = 3.5; gapHeight = 130; flashMessage = 'Getting harder!'; }
                    else if (speedTier === 4) { pipeSpeed = 4.0; gapHeight = 120; flashMessage = 'Expert mode!'; }
                    else if (speedTier >= 5) { pipeSpeed = 4.5; gapHeight = 115; flashMessage = 'Max speed!'; }
                    flashTimer = 90;
                }

                if (score >= 40) multiplier = 3.0;
                else if (score >= 30) multiplier = 2.5;
                else if (score >= 20) multiplier = 2.0;
                else if (score >= 10) multiplier = 1.5;
                else multiplier = 1.0;

                if (multiplier > 1.0 && multiplierEl) {
                    multiplierEl.style.display = 'block';
                    multiplierEl.textContent = multiplier.toFixed(1) + '\u00d7';
                }

                if (!p.scoredWithPU && pipesSinceLastPowerUp >= 8 && Math.random() < 0.6) {
                    spawnPowerUp(p);
                    pipesSinceLastPowerUp = 0;
                }

                spawnCoins(p);
            }

            if (p.x + PIPE_WIDTH < -10) pipes.splice(i, 1);
        }

        for (var i = coins.length - 1; i >= 0; i--) {
            var c = coins[i];
            if (!c.collected) {
                c.x -= actualSpeed;
                if (activePowerUp && activePowerUp.type === 'magnet') {
                    var dx2 = bird.x - c.x;
                    var dy2 = bird.y - c.y;
                    var dist2 = Math.sqrt(dx2 * dx2 + dy2 * dy2);
                    if (dist2 < 120 && dist2 > 5) {
                        c.x += dx2 / dist2 * 4;
                        c.y += dy2 / dist2 * 4;
                    }
                }
                if (hitTest(c.x, c.y, c.r, bird.x, bird.y, BIRD_SIZE / 2)) {
                    c.collected = true;
                    coinsCollected++;
                    coins.splice(i, 1);
                }
            }
            if (c.x + c.r < -10) coins.splice(i, 1);
        }

        for (var i = powerUps.length - 1; i >= 0; i--) {
            var pu = powerUps[i];
            if (!pu.collected) {
                pu.x -= actualSpeed;
                if (hitTest(pu.x, pu.y, pu.r, bird.x, bird.y, BIRD_SIZE / 2)) {
                    pu.collected = true;
                    activatePowerUp(pu);
                    powerUps.splice(i, 1);
                }
            }
            if (pu.x + pu.r < -10) powerUps.splice(i, 1);
        }

        if (activePowerUp) {
            activePowerUp.timeLeft--;
            if (powerupBar) powerupBar.style.width = (activePowerUp.timeLeft / activePowerUp.duration * 100) + '%';
            if (activePowerUp.timeLeft <= 0) {
                if (activePowerUp.type === 'slowmo') pipeSpeed = pipeSpeed * 2;
                activePowerUp = null;
                if (powerupHud) powerupHud.style.display = 'none';
            }
        }

        if (flashTimer > 0) flashTimer--;

        for (var i = pipes.length - 1; i >= 0; i--) {
            var p = pipes[i];
            if (bird.x + BIRD_SIZE / 2 > p.x && bird.x - BIRD_SIZE / 2 < p.x + PIPE_WIDTH) {
                if (bird.y - BIRD_SIZE / 2 < p.gapY) {
                    if (activeShield) {
                        activeShield = false;
                        if (powerupHud) powerupHud.style.display = 'none';
                    } else die();
                }
                if (bird.y + BIRD_SIZE / 2 > p.gapY + p.gapH) {
                    if (activeShield) {
                        activeShield = false;
                        if (powerupHud) powerupHud.style.display = 'none';
                    } else die();
                }
            }
        }
        if (bird.y - BIRD_SIZE / 2 < 0 || bird.y + BIRD_SIZE / 2 > 640) {
            if (activeShield) {
                activeShield = false;
                bird.y = Math.max(BIRD_SIZE / 2, Math.min(640 - BIRD_SIZE / 2, bird.y));
            } else die();
        }
    }

    function activatePowerUp(pu) {
        if (pu.type === 'shield') {
            activeShield = true;
            if (powerupHud) { powerupHud.style.display = 'flex'; powerupIcon.textContent = pu.icon; powerupBar.style.width = '100%'; }
        } else if (pu.type === 'slowmo') {
            activePowerUp = { type: 'slowmo', duration: 300, timeLeft: 300 };
        } else {
            activePowerUp = { type: pu.type, duration: pu.type === 'magnet' ? 480 : 600, timeLeft: pu.type === 'magnet' ? 480 : 600 };
        }
        if (activePowerUp && powerupHud) {
            powerupHud.style.display = 'flex';
            powerupIcon.textContent = pu.icon;
        }
    }

    function die() {
        gameRunning = false;
        if (animFrame) { cancelAnimationFrame(animFrame); animFrame = null; }
        showGameOver();
    }

    function showGameOver() {
        if (!overlay) return;
        var currencyEarned = Math.round(score * multiplier * 2) + (coinsCollected * 5);
        var xpEarned = score + (coinsCollected * 2);

        overlay.style.display = 'flex';
        document.getElementById('go-score').textContent = score;
        document.getElementById('go-multiplier').textContent = multiplier.toFixed(1) + '\u00d7 multiplier';
        document.getElementById('go-currency').textContent = '+' + currencyEarned + ' \u{1f4b0}';
        document.getElementById('go-xp').textContent = '+' + xpEarned + ' XP';
        document.getElementById('go-coins').textContent = coinsCollected + ' coins';
        document.getElementById('go-best').textContent = '';

        var body = 'game_token=' + encodeURIComponent(gameToken) +
            '&score=' + score +
            '&multiplier=' + multiplier +
            '&coins_collected=' + coinsCollected +
            '&csrf_token=' + encodeURIComponent(csrfToken);

        fetch('api/save_score.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function(res) { return res.json(); })
          .then(function(data) {
              if (data.success && data.new_achievements && data.new_achievements.length > 0) {
                  if (typeof window.queueAchievements === 'function') {
                      window.queueAchievements(data.new_achievements);
                  }
              }
          }).catch(function() {});
    }
              }
          }).catch(function() {});
    }

    function render() {
        ctx.fillStyle = '#0a0a18';
        ctx.fillRect(0, 0, 480, 640);

        if (gameRunning) {
            for (var i = 0; i < backgroundStars.length; i++) {
                var s = backgroundStars[i];
                s.x -= s.speed;
                if (s.x < 0) s.x = 480;
                ctx.fillStyle = 'rgba(255,255,255,' + (0.2 + s.r * 0.3) + ')';
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fill();
            }
        } else {
            for (var i = 0; i < backgroundStars.length; i++) {
                var s = backgroundStars[i];
                ctx.fillStyle = 'rgba(255,255,255,' + (0.2 + s.r * 0.3) + ')';
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        for (var i = 0; i < pipes.length; i++) {
            var p = pipes[i];
            var gradient = ctx.createLinearGradient(p.x, 0, p.x + PIPE_WIDTH, 0);
            gradient.addColorStop(0, '#1a4a1a');
            gradient.addColorStop(0.5, '#2d8a2d');
            gradient.addColorStop(1, '#1a5a1a');
            ctx.fillStyle = gradient;
            ctx.fillRect(p.x, 0, PIPE_WIDTH, p.gapY);
            ctx.fillRect(p.x, p.gapY + p.gapH, PIPE_WIDTH, 640 - p.gapY - p.gapH);
            ctx.fillStyle = '#267526';
            ctx.fillRect(p.x - 3, p.gapY - 20, PIPE_WIDTH + 6, 20);
            ctx.fillRect(p.x - 3, p.gapY + p.gapH, PIPE_WIDTH + 6, 20);
        }

        for (var i = 0; i < coins.length; i++) {
            var c = coins[i];
            ctx.fillStyle = '#fbbf24';
            ctx.shadowColor = 'rgba(251,191,36,0.5)';
            ctx.shadowBlur = 8;
            ctx.beginPath();
            ctx.arc(c.x, c.y, c.r, 0, Math.PI * 2);
            ctx.fill();
            ctx.shadowBlur = 0;
        }

        for (var i = 0; i < powerUps.length; i++) {
            var pu = powerUps[i];
            ctx.fillStyle = pu.color;
            ctx.shadowColor = pu.color;
            ctx.shadowBlur = 12;
            ctx.beginPath();
            ctx.arc(pu.x, pu.y, pu.r, 0, Math.PI * 2);
            ctx.fill();
            ctx.shadowBlur = 0;
        }

        ctx.fillStyle = '#fbbf24';
        ctx.shadowColor = 'rgba(251,191,36,0.4)';
        ctx.shadowBlur = 10;
        ctx.beginPath();
        ctx.arc(bird.x, bird.y, BIRD_SIZE / 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.shadowBlur = 0;
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(bird.x - 4, bird.y - 4, 5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(bird.x - 3, bird.y - 5, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#f97316';
        ctx.beginPath();
        ctx.moveTo(bird.x + 10, bird.y);
        ctx.lineTo(bird.x + 20, bird.y - 5);
        ctx.lineTo(bird.x + 20, bird.y + 5);
        ctx.fill();

        if (activeShield) {
            ctx.strokeStyle = '#3b82f6';
            ctx.shadowColor = 'rgba(59,130,246,0.6)';
            ctx.shadowBlur = 10;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(bird.x, bird.y, BIRD_SIZE / 2 + 4, 0, Math.PI * 2);
            ctx.stroke();
            ctx.shadowBlur = 0;
        }

        if (flashTimer > 0 && flashMessage) {
            ctx.fillStyle = 'rgba(255,255,255,' + Math.min(1, flashTimer / 30) + ')';
            ctx.font = 'bold 24px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(flashMessage, 240, 320);
        }
    }

    function loop() {
        update();
        render();
        animFrame = requestAnimationFrame(loop);
    }

    canvas.addEventListener('click', function() { flap(); });
    document.addEventListener('keydown', function(e) { if (e.code === 'Space') { e.preventDefault(); flap(); } });
    canvas.addEventListener('touchstart', function(e) { e.preventDefault(); flap(); });

    document.getElementById('btn-play-again').addEventListener('click', function() {
        location.reload();
    });

    function startPregame() {
        bird.y = 300;
        bird.vy = 0;
        gameStarted = false;
        gameRunning = false;
        render();
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.font = '20px sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText('Tap to start', 240, 300);
    }

    function loadLeaderboard(period) {
        var lbBody = document.getElementById('lb-body');
        var lbRank = document.getElementById('lb-your-rank');
        if (!lbBody) return;

        fetch('leaderboard.php?ajax=1&period=' + encodeURIComponent(period), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(res) { return res.text(); })
            .then(function(html) {
                if (html.trim().startsWith('<tr') || html.trim().startsWith('<td')) {
                    lbBody.innerHTML = html;
                } else {
                    lbBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">No scores yet</td></tr>';
                }
            }).catch(function() {
                lbBody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:var(--text-muted);">Could not load leaderboard</td></tr>';
            });
    }

    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            loadLeaderboard(this.dataset.lb);
        });
    });

    gameToken = GAME_TOKEN;
    csrfToken = CSRF_TOKEN;

    reset();
    startPregame();
    loadLeaderboard('all');
})();
