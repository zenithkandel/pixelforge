const canvas = document.getElementById('gameCanvas');
const ctx = canvas.getContext('2d');

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

const COLORS = {
    cyan: '#00f7ff',
    pink: '#ff00aa',
    yellow: '#ffe600',
    green: '#00ff88',
    purple: '#aa00ff',
    red: '#ff3333',
    white: '#ffffff'
};

class Player {
    constructor() {
        this.x = canvas.width / 2;
        this.y = canvas.height - 100;
        this.width = 40;
        this.height = 50;
        this.speed = 7;
        this.color = COLORS.cyan;
        this.lives = 3;
        this.invincible = false;
        this.invincibleTimer = 0;
        this.shootCooldown = 0;
        this.weaponLevel = 1;
    }

    update(keys) {
        if (keys.left && this.x > this.width) this.x -= this.speed;
        if (keys.right && this.x < canvas.width - this.width) this.x += this.speed;
        if (keys.up && this.y > this.height) this.y -= this.speed;
        if (keys.down && this.y < canvas.height - this.height) this.y += this.speed;
        
        if (this.invincible) {
            this.invincibleTimer--;
            if (this.invincibleTimer <= 0) {
                this.invincible = false;
            }
        }
        
        if (this.shootCooldown > 0) this.shootCooldown--;
    }

    draw() {
        if (this.invincible && Math.floor(Date.now() / 100) % 2 === 0) return;
        
        ctx.save();
        ctx.translate(this.x, this.y);
        
        ctx.shadowBlur = 20;
        ctx.shadowColor = this.color;
        
        ctx.beginPath();
        ctx.moveTo(0, -this.height / 2);
        ctx.lineTo(-this.width / 2, this.height / 2);
        ctx.lineTo(-this.width / 4, this.height / 3);
        ctx.lineTo(0, this.height / 2);
        ctx.lineTo(this.width / 4, this.height / 3);
        ctx.lineTo(this.width / 2, this.height / 2);
        ctx.closePath();
        
        const gradient = ctx.createLinearGradient(0, -this.height/2, 0, this.height/2);
        gradient.addColorStop(0, COLORS.white);
        gradient.addColorStop(0.5, this.color);
        gradient.addColorStop(1, COLORS.pink);
        ctx.fillStyle = gradient;
        ctx.fill();
        
        ctx.strokeStyle = COLORS.white;
        ctx.lineWidth = 2;
        ctx.stroke();
        
        ctx.restore();
    }

    shoot() {
        if (this.shootCooldown > 0) return;
        
        const bullets = [];
        const bulletSpeed = 12;
        
        if (this.weaponLevel === 1) {
            bullets.push(new Bullet(this.x, this.y - this.height / 2, 0, -bulletSpeed));
        } else if (this.weaponLevel === 2) {
            bullets.push(new Bullet(this.x - 10, this.y - this.height / 2, 0, -bulletSpeed));
            bullets.push(new Bullet(this.x + 10, this.y - this.height / 2, 0, -bulletSpeed));
        } else {
            bullets.push(new Bullet(this.x, this.y - this.height / 2, 0, -bulletSpeed));
            bullets.push(new Bullet(this.x - 15, this.y - this.height / 3, -2, -bulletSpeed));
            bullets.push(new Bullet(this.x + 15, this.y - this.height / 3, 2, -bulletSpeed));
        }
        
        this.shootCooldown = 8;
        return bullets;
    }

    hit() {
        if (this.invincible) return false;
        
        this.lives--;
        this.invincible = true;
        this.invincibleTimer = 120;
        
        updateLivesDisplay();
        
        if (this.lives <= 0) {
            return true;
        }
        return false;
    }
}

class Bullet {
    constructor(x, y, vx, vy, isEnemy = false) {
        this.x = x;
        this.y = y;
        this.vx = vx;
        this.vy = vy;
        this.radius = isEnemy ? 8 : 4;
        this.isEnemy = isEnemy;
        this.color = isEnemy ? COLORS.pink : COLORS.yellow;
        this.alive = true;
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;
        
        if (this.y < -20 || this.y > canvas.height + 20 || 
            this.x < -20 || this.x > canvas.width + 20) {
            this.alive = false;
        }
    }

    draw() {
        ctx.save();
        ctx.shadowBlur = 10;
        ctx.shadowColor = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.restore();
    }
}

class Enemy {
    constructor(type, wave) {
        this.type = type;
        this.x = Math.random() * (canvas.width - 100) + 50;
        this.y = -50;
        this.width = 40;
        this.height = 40;
        this.alive = true;
        
        const baseSpeed = 2 + wave * 0.3;
        
        if (type === 'basic') {
            this.speed = baseSpeed;
            this.hp = 1;
            this.color = COLORS.pink;
            this.score = 100;
        } else if (type === 'fast') {
            this.speed = baseSpeed * 1.8;
            this.hp = 1;
            this.color = COLORS.purple;
            this.score = 150;
        } else if (type === 'tank') {
            this.speed = baseSpeed * 0.6;
            this.hp = 4 + Math.floor(wave / 3);
            this.color = COLORS.red;
            this.score = 300;
        } else if (type === 'shooter') {
            this.speed = baseSpeed * 0.8;
            this.hp = 2;
            this.color = COLORS.green;
            this.score = 200;
            this.shootTimer = 60;
        }
        
        this.angle = 0;
    }

    update(playerX, playerY) {
        this.y += this.speed;
        this.angle += 0.05;
        
        if (this.type === 'shooter') {
            this.shootTimer--;
            if (this.shootTimer <= 0) {
                this.shootTimer = 90;
                const dx = playerX - this.x;
                const dy = playerY - this.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                const speed = 5;
                return [new Bullet(this.x, this.y + this.height/2, (dx/dist) * speed, (dy/dist) * speed, true)];
            }
        }
        
        if (this.y > canvas.height + 50) {
            this.alive = false;
        }
        return [];
    }

    draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        ctx.shadowBlur = 15;
        ctx.shadowColor = this.color;
        
        if (this.type === 'basic') {
            ctx.fillStyle = this.color;
            ctx.fillRect(-this.width/2, -this.height/2, this.width, this.height);
            ctx.strokeStyle = COLORS.white;
            ctx.lineWidth = 2;
            ctx.strokeRect(-this.width/2, -this.height/2, this.width, this.height);
        } else if (this.type === 'fast') {
            ctx.beginPath();
            ctx.moveTo(0, this.height/2);
            ctx.lineTo(-this.width/2, -this.height/2);
            ctx.lineTo(this.width/2, -this.height/2);
            ctx.closePath();
            ctx.fillStyle = this.color;
            ctx.fill();
        } else if (this.type === 'tank') {
            ctx.beginPath();
            ctx.arc(0, 0, this.width/2, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
            ctx.strokeStyle = COLORS.white;
            ctx.lineWidth = 3;
            ctx.stroke();
            
            for (let i = 0; i < this.hp; i++) {
                ctx.beginPath();
                ctx.arc(-10 + i * 8, 0, 3, 0, Math.PI * 2);
                ctx.fillStyle = COLORS.yellow;
                ctx.fill();
            }
        } else if (this.type === 'shooter') {
            ctx.beginPath();
            ctx.moveTo(0, this.height/2);
            ctx.lineTo(-this.width/2, -this.height/2);
            ctx.lineTo(0, -this.height/4);
            ctx.lineTo(this.width/2, -this.height/2);
            ctx.closePath();
            ctx.fillStyle = this.color;
            ctx.fill();
        }
        
        ctx.restore();
    }
}

class Boss {
    constructor(wave) {
        this.x = canvas.width / 2;
        this.y = -100;
        this.targetY = 150;
        this.width = 120;
        this.height = 80;
        this.alive = true;
        this.hp = 20 + wave * 10;
        this.maxHp = this.hp;
        this.phase = 0;
        this.phaseTimer = 0;
        this.moveDir = 1;
        this.shootTimer = 0;
        this.entering = true;
    }

    update(player) {
        if (this.entering) {
            this.y += 2;
            if (this.y >= this.targetY) {
                this.y = this.targetY;
                this.entering = false;
            }
            return [];
        }
        
        this.phaseTimer++;
        
        if (this.phaseTimer > 300) {
            this.phase = (this.phase + 1) % 3;
            this.phaseTimer = 0;
        }
        
        this.x += this.moveDir * 3;
        if (this.x > canvas.width - 100 || this.x < 100) {
            this.moveDir *= -1;
        }
        
        const bullets = [];
        this.shootTimer++;
        
        if (this.phase === 0 && this.shootTimer > 20) {
            this.shootTimer = 0;
            for (let i = -2; i <= 2; i++) {
                bullets.push(new Bullet(this.x, this.y + this.height/2, i * 2, 6, true));
            }
        } else if (this.phase === 1 && this.shootTimer > 30) {
            this.shootTimer = 0;
            const dx = player.x - this.x;
            const dy = player.y - this.y;
            const dist = Math.sqrt(dx*dx + dy*dy);
            bullets.push(new Bullet(this.x, this.y + this.height/2, (dx/dist) * 8, (dy/dist) * 8, true));
        } else if (this.phase === 2 && this.shootTimer > 15) {
            this.shootTimer = 0;
            const angle = (Date.now() / 1000) * 2;
            bullets.push(new Bullet(this.x, this.y + this.height/2, Math.cos(angle) * 5, Math.sin(angle) * 5 + 3, true));
            bullets.push(new Bullet(this.x, this.y + this.height/2, Math.cos(angle + Math.PI) * 5, Math.sin(angle + Math.PI) * 5 + 3, true));
        }
        
        return bullets;
    }

    draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.shadowBlur = 30;
        ctx.shadowColor = COLORS.red;
        
        const gradient = ctx.createRadialGradient(0, 0, 0, 0, 0, this.width);
        gradient.addColorStop(0, COLORS.pink);
        gradient.addColorStop(1, COLORS.red);
        
        ctx.beginPath();
        ctx.moveTo(0, -this.height/2);
        ctx.lineTo(-this.width/2, this.height/2);
        ctx.lineTo(-this.width/4, this.height/3);
        ctx.lineTo(0, this.height/2);
        ctx.lineTo(this.width/4, this.height/3);
        ctx.lineTo(this.width/2, this.height/2);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.strokeStyle = COLORS.white;
        ctx.lineWidth = 3;
        ctx.stroke();
        
        const hpBarWidth = 100;
        const hpPercent = this.hp / this.maxHp;
        ctx.fillStyle = '#333';
        ctx.fillRect(-hpBarWidth/2, -this.height/2 - 20, hpBarWidth, 10);
        ctx.fillStyle = COLORS.red;
        ctx.fillRect(-hpBarWidth/2, -this.height/2 - 20, hpBarWidth * hpPercent, 10);
        
        ctx.fillStyle = COLORS.white;
        ctx.font = '12px Orbitron';
        ctx.textAlign = 'center';
        ctx.fillText(`HP: ${this.hp}/${this.maxHp}`, 0, -this.height/2 - 28);
        
        ctx.restore();
    }
}

class PowerUp {
    constructor(x, y, type) {
        this.x = x;
        this.y = y;
        this.type = type;
        this.width = 30;
        this.height = 30;
        this.alive = true;
        this.angle = 0;
        this.vy = 2;
        
        if (type === 'weapon') {
            this.color = COLORS.yellow;
            this.icon = 'W';
        } else if (type === 'shield') {
            this.color = COLORS.green;
            this.icon = 'S';
        } else if (type === 'score') {
            this.color = COLORS.purple;
            this.icon = '★';
        }
    }

    update() {
        this.y += this.vy;
        this.angle += 0.1;
        
        if (this.y > canvas.height + 50) {
            this.alive = false;
        }
    }

    draw() {
        ctx.save();
        ctx.translate(this.x, this.y);
        ctx.rotate(this.angle);
        ctx.shadowBlur = 15;
        ctx.shadowColor = this.color;
        
        ctx.beginPath();
        ctx.arc(0, 0, 15, 0, Math.PI * 2);
        ctx.fillStyle = this.color;
        ctx.fill();
        ctx.strokeStyle = COLORS.white;
        ctx.lineWidth = 2;
        ctx.stroke();
        
        ctx.fillStyle = COLORS.white;
        ctx.font = 'bold 14px Orbitron';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(this.icon, 0, 0);
        
        ctx.restore();
    }
}

class Particle {
    constructor(x, y, color, size, vx, vy, life) {
        this.x = x;
        this.y = y;
        this.color = color;
        this.size = size;
        this.vx = vx;
        this.vy = vy;
        this.life = life;
        this.maxLife = life;
    }

    update() {
        this.x += this.vx;
        this.y += this.vy;
        this.life--;
        this.vy += 0.1;
    }

    draw() {
        const alpha = this.life / this.maxLife;
        ctx.save();
        ctx.globalAlpha = alpha;
        ctx.shadowBlur = 10;
        ctx.shadowColor = this.color;
        ctx.fillStyle = this.color;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size * alpha, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}

class Star {
    constructor() {
        this.reset();
    }
    
    reset() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 2 + 0.5;
        this.speed = Math.random() * 2 + 0.5;
        this.brightness = Math.random();
    }
    
    update() {
        this.y += this.speed;
        this.brightness += 0.02;
        if (this.y > canvas.height) {
            this.y = 0;
            this.x = Math.random() * canvas.width;
        }
    }
    
    draw() {
        const alpha = 0.3 + Math.sin(this.brightness) * 0.3;
        ctx.fillStyle = `rgba(255, 255, 255, ${alpha})`;
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fill();
    }
}

let player;
let bullets = [];
let enemies = [];
let powerUps = [];
let particles = [];
let stars = [];
let boss = null;

let score = 0;
let wave = 1;
let combo = 0;
let comboTimer = 0;
let gameRunning = false;
let gamePaused = false;
let gameStarted = false;

let keys = {
    left: false,
    right: false,
    up: false,
    down: false,
    shoot: false
};

let waveTimer = 0;
let enemiesSpawned = 0;
let enemiesPerWave = 10;
let bossWave = false;

function init() {
    gameRunning = false;
    gamePaused = false;
    gameStarted = false;
    
    player = new Player();
    bullets = [];
    enemies = [];
    powerUps = [];
    particles = [];
    boss = null;
    score = 0;
    wave = 1;
    combo = 0;
    comboTimer = 0;
    waveTimer = 0;
    enemiesSpawned = 0;
    enemiesPerWave = 10;
    bossWave = false;
    
    stars = [];
    for (let i = 0; i < 100; i++) {
        stars.push(new Star());
    }
    
    updateScoreDisplay();
    updateWaveDisplay();
    updateLivesDisplay();
}

function spawnEnemy() {
    if (bossWave || enemies.length >= 15) return;
    
    const rand = Math.random();
    let type;
    
    if (rand < 0.5) type = 'basic';
    else if (rand < 0.75) type = 'fast';
    else if (rand < 0.9) type = 'tank';
    else type = 'shooter';
    
    enemies.push(new Enemy(type, wave));
    enemiesSpawned++;
}

function spawnBoss() {
    boss = new Boss(wave);
    bossWave = true;
}

function createExplosion(x, y, color, count = 20) {
    for (let i = 0; i < count; i++) {
        const angle = Math.random() * Math.PI * 2;
        const speed = Math.random() * 5 + 2;
        particles.push(new Particle(
            x, y, color, 
            Math.random() * 4 + 2,
            Math.cos(angle) * speed,
            Math.sin(angle) * speed,
            40 + Math.random() * 20
        ));
    }
}

function checkCollisions() {
    for (let i = bullets.length - 1; i >= 0; i--) {
        const b = bullets[i];
        
        if (!b.isEnemy) {
            for (let j = enemies.length - 1; j >= 0; j--) {
                const e = enemies[j];
                const dx = b.x - e.x;
                const dy = b.y - e.y;
                const dist = Math.sqrt(dx*dx + dy*dy);
                
                if (dist < e.width/2 + b.radius) {
                    e.hp--;
                    b.alive = false;
                    
                    if (e.hp <= 0) {
                        e.alive = false;
                        score += e.score;
                        combo++;
                        comboTimer = 120;
                        createExplosion(e.x, e.y, e.color);
                        
                        if (Math.random() < 0.15) {
                            const types = ['weapon', 'shield', 'score'];
                            const type = types[Math.floor(Math.random() * types.length)];
                            powerUps.push(new PowerUp(e.x, e.y, type));
                        }
                        
                        updateScoreDisplay();
                        updateComboDisplay();
                    }
                    break;
                }
            }
            
            if (boss && b.alive) {
                const dx = b.x - b.x;
                const dy = b.y - b.y;
                const dist = Math.sqrt((b.x - b.x) ** 2 + (b.y - b.y) ** 2);
                if (Math.abs(b.x - boss.x) < boss.width/2 && 
                    Math.abs(b.y - boss.y) < boss.height/2) {
                    boss.hp--;
                    b.alive = false;
                    createExplosion(b.x, b.y, COLORS.yellow, 5);
                    
                    if (boss.hp <= 0) {
                        boss.alive = false;
                        score += 5000;
                        combo += 10;
                        createExplosion(boss.x, boss.y, COLORS.red, 50);
                        updateScoreDisplay();
                        
                        setTimeout(() => {
                            wave++;
                            bossWave = false;
                            enemiesPerWave += 3;
                            enemiesSpawned = 0;
                            updateWaveDisplay();
                        }, 2000);
                    }
                }
            }
        } else {
            const dx = b.x - player.x;
            const dy = b.y - player.y;
            const dist = Math.sqrt(dx*dx + dy*dy);
            
            if (dist < player.width/2 + b.radius) {
                b.alive = false;
                if (player.hit()) {
                    gameOver();
                } else {
                    createExplosion(player.x, player.y, COLORS.cyan, 15);
                }
            }
        }
    }
    
    for (let i = enemies.length - 1; i >= 0; i--) {
        const e = enemies[i];
        const dx = e.x - player.x;
        const dy = e.y - player.y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        
        if (dist < (e.width + player.width) / 2) {
            e.alive = false;
            if (player.hit()) {
                gameOver();
            } else {
                createExplosion(e.x, e.y, e.color);
            }
        }
    }
    
    for (let i = powerUps.length - 1; i >= 0; i--) {
        const p = powerUps[i];
        const dx = p.x - player.x;
        const dy = p.y - player.y;
        const dist = Math.sqrt(dx*dx + dy*dy);
        
        if (dist < (p.width + player.width) / 2) {
            p.alive = false;
            
            if (p.type === 'weapon' && player.weaponLevel < 3) {
                player.weaponLevel++;
                score += 500;
            } else if (p.type === 'shield' && player.lives < 5) {
                player.lives++;
                updateLivesDisplay();
                score += 300;
            } else {
                score += 1000;
            }
            
            updateScoreDisplay();
            createExplosion(p.x, p.y, p.color, 10);
        }
    }
}

function updateScoreDisplay() {
    document.getElementById('score').textContent = score.toLocaleString();
}

function updateWaveDisplay() {
    document.getElementById('wave').textContent = wave;
}

function updateLivesDisplay() {
    const livesContainer = document.getElementById('lives');
    let html = '';
    for (let i = 0; i < 5; i++) {
        html += `<span class="life ${i < player.lives ? 'active' : ''}">◆</span>`;
    }
    livesContainer.innerHTML = html;
}

function updateComboDisplay() {
    const comboEl = document.getElementById('combo');
    if (combo > 1) {
        comboEl.querySelector('.combo-text').textContent = `${combo}x COMBO!`;
        comboEl.querySelector('.combo-text').classList.add('visible');
    }
}

function update() {
    if (!gameRunning || gamePaused) return;
    
    player.update(keys);
    
    if (keys.shoot) {
        const newBullets = player.shoot();
        if (newBullets) bullets.push(...newBullets);
    }
    
    waveTimer++;
    
    if (!bossWave && enemiesSpawned < enemiesPerWave && waveTimer % 40 === 0) {
        spawnEnemy();
    }
    
    if (!bossWave && enemiesSpawned >= enemiesPerWave && enemies.length === 0) {
        if (wave % 3 === 0) {
            spawnBoss();
        } else {
            wave++;
            enemiesPerWave += 3;
            enemiesSpawned = 0;
            waveTimer = 0;
            updateWaveDisplay();
        }
    }
    
    const newBullets = boss ? boss.update(player) : [];
    bullets.push(...newBullets);
    
    for (let i = enemies.length - 1; i >= 0; i--) {
        const newEnemBullets = enemies[i].update(player.x, player.y);
        bullets.push(...newEnemBullets);
        if (!enemies[i].alive) enemies.splice(i, 1);
    }
    
    for (let b of bullets) b.update();
    bullets = bullets.filter(b => b.alive);
    
    for (let p of powerUps) p.update();
    powerUps = powerUps.filter(p => p.alive);
    
    for (let p of particles) p.update();
    particles = particles.filter(p => p.life > 0);
    
    for (let s of stars) s.update();
    
    if (comboTimer > 0) {
        comboTimer--;
        if (comboTimer === 0) combo = 0;
    }
    
    checkCollisions();
}

function draw() {
    ctx.fillStyle = '#050508';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    const gradient = ctx.createRadialGradient(
        canvas.width/2, canvas.height/2, 0,
        canvas.width/2, canvas.height/2, canvas.width
    );
    gradient.addColorStop(0, 'rgba(20, 0, 40, 0.3)');
    gradient.addColorStop(1, 'transparent');
    ctx.fillStyle = gradient;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    for (let s of stars) s.draw();
    
    for (let p of powerUps) p.draw();
    for (let e of enemies) e.draw();
    if (boss) boss.draw();
    for (let b of bullets) b.draw();
    for (let p of particles) p.draw();
    
    if (player) player.draw();
}

function gameLoop() {
    update();
    draw();
    requestAnimationFrame(gameLoop);
}

function gameOver() {
    gameRunning = false;
    document.getElementById('gameOverScreen').classList.remove('hidden');
    document.getElementById('finalScore').textContent = score.toLocaleString();
    document.getElementById('finalWave').textContent = wave;
}

let gameStarted = false;

function startGame() {
    if (gameStarted) return;
    gameStarted = true;
    
    init();
    gameRunning = true;
    gamePaused = false;
    document.getElementById('startScreen').classList.add('hidden');
    document.getElementById('gameOverScreen').classList.add('hidden');
    document.getElementById('pauseOverlay').classList.add('hidden');
    
    gameLoop();
}

document.getElementById('startGameBtn').addEventListener('click', startGame);

document.addEventListener('keydown', (e) => {
    if (e.code === 'ArrowLeft' || e.code === 'KeyA') keys.left = true;
    if (e.code === 'ArrowRight' || e.code === 'KeyD') keys.right = true;
    if (e.code === 'ArrowUp' || e.code === 'KeyW') keys.up = true;
    if (e.code === 'ArrowDown' || e.code === 'KeyS') keys.down = true;
    if (e.code === 'Space') {
        keys.shoot = true;
        if (!gameStarted) startGame();
        e.preventDefault();
    }
    if (e.code === 'KeyP' || e.code === 'Escape') {
        if (gameRunning && !gamePaused) {
            gamePaused = true;
            document.getElementById('pauseOverlay').classList.remove('hidden');
        } else if (gamePaused) {
            gamePaused = false;
            document.getElementById('pauseOverlay').classList.add('hidden');
        }
    }
});

document.addEventListener('keyup', (e) => {
    if (e.code === 'ArrowLeft' || e.code === 'KeyA') keys.left = false;
    if (e.code === 'ArrowRight' || e.code === 'KeyD') keys.right = false;
    if (e.code === 'ArrowUp' || e.code === 'KeyW') keys.up = false;
    if (e.code === 'ArrowDown' || e.code === 'KeyS') keys.down = false;
    if (e.code === 'Space') keys.shoot = false;
});

document.getElementById('submitScore').addEventListener('click', () => {
    const name = document.getElementById('playerName').value.trim() || 'Anonymous';
    fetch('/api/scores', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            score, 
            player: name, 
            wave,
            time: 0
        })
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            alert(`Score submitted! Rank: #${d.rank}`);
        }
    });
});

window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});

init();
draw();